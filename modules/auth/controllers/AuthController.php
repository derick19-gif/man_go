<?php

namespace App\Modules\Auth\Controllers;

// 1. L'ARME ABSOLUE : On force PHP à charger les classes que l'Autoloader ignore
require_once __DIR__ . '/../../../classes/Security.php';
require_once __DIR__ . '/../../../core/Countries.php'; // Par précaution

// 2. Les classes MVC modernes
use App\Core\Controller;
use App\Core\Request;
use App\Modules\Auth\Models\User;

// 3. Les classes globales
use Session;
use Countries;
use Security;

/**
 * Authentication Controller
 * 
 * Handles login, logout, and authentication routes
 */
class AuthController extends Controller
{
    
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function loginAction(): void
    {
        if (Session::isAuthenticated()) {
            $this->redirect(APP_URL . '/dashboard.php');
        }

        // Check for flash messages
        $message = Session::getFlash('message');
        $error = Session::getFlash('error');
        $expired = $this->request->query('expired');

        echo $this->render('login', [
            'csrf_token' => Security::generateCsrfToken(),
            'message'    => $message,
            'error'      => $error,
            'expired'    => $expired,
        ]);
    }

    public function registerAction(): void
    {
        if (Session::isAuthenticated()) {
            $this->redirect(APP_URL . '/dashboard.php');
        }

        echo $this->render('register', [
            'csrf_token'      => Security::generateCsrfToken(),
            'country_options' => Countries::renderSelectOptions('+228'),
        ]);
    }

    public function registerProcessAction(): void
    {
        if (!$this->request->isPost()) {
            $this->redirect(APP_URL . '/register');
        }

        // Verify CSRF token
        $token = $this->request->post('_token') ?? $this->request->post('csrf_token');
        if (!Security::verifyCsrfToken($token)) {
            Session::flash('error', 'Token invalide.');
            $this->redirect(APP_URL . '/register');
        }

        $name = trim($this->request->post('name', ''));
        $email = trim($this->request->post('email', ''));
        $phone = trim($this->request->post('phone', ''));
        $countryCode = $this->request->post('country_code', '+228');
        $password = $this->request->post('password', '');

        if (empty($name) || empty($email) || empty($password)) {
            Session::flash('error', 'Veuillez remplir tous les champs obligatoires.');
            $this->redirect(APP_URL . '/register');
        }

        // --- 1. DÉTECTION SILENCIEUSE DU VPN (SANS BLOQUER L'UTILISATEUR) ---
        $userIp = $this->request->getIp();
        $isVpnUser = 0;

        if ($userIp && $userIp !== '127.0.0.1' && $userIp !== '::1') {
            $apiUrl = "https://proxycheck.io/v2/{$userIp}?vpn=1&asn=1";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 secondes max pour ne pas ralentir
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data[$userIp]['proxy']) && $data[$userIp]['proxy'] === 'yes') {
                    $isVpnUser = 1; // On note secrètement qu'il utilise un VPN
                }
            }
        }
        // --- FIN DE LA DÉTECTION VPN ---

        // Séparation du nom en first_name et last_name pour correspondre au Model User
        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        $userData = [
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'email'          => $email,
            'password_hash'  => $password, // Sera haché par le modèle User
            'phone'          => $phone,
            'is_active'      => 1,
            'is_verified'    => 0,
            // Les données secrètes VPN et IP injectées silencieusement :
            'registration_ip'=> $userIp,
            'is_vpn'         => $isVpnUser
        ];

        try {
            $userModel = new User();
            
            // Note : Il faudra s'assurer que votre méthode create() dans User.php accepte ces 2 champs, 
            // ou bien ils seront ignorés s'ils ne sont pas gérés. 
            // Voyons l'insertion :
            $success = $userModel->create($userData);

            if ($success) {
                Session::flash('message', 'Compte créé avec succès. Vous pouvez vous connecter.');
                $this->redirect(APP_URL . '/login');
            } else {
                Session::flash('error', 'Erreur lors de la création du compte.');
                $this->redirect(APP_URL . '/register');
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Cette adresse email est déjà utilisée ou une erreur est survenue.');
            $this->redirect(APP_URL . '/register');
        }
    }

    public function authenticateAction(): void
    {
        if (!$this->request->isPost()) {
            $this->redirect(APP_URL . '/login');
        }

        // Verify CSRF token
        $token = $this->request->post('_token') ?? $this->request->post('csrf_token');
        if (!\Security::verifyCsrfToken($token)) {
            \Security::logSecurityEvent('CSRF_FAILED', ['ip' => $this->request->getIp()]);
            Session::flash('error', 'Session expired or invalid token. Please try again.');
            $this->redirect(APP_URL . '/login');
        }

        // Rate limiting
        $rateLimitKey = 'login_' . $this->request->getIp();
        $rateLimit = \Security::checkRateLimit($rateLimitKey, AUTH_ATTEMPTS_MAX ?? 5, AUTH_LOCKOUT_TIME ?? 900);

        if (!$rateLimit['allowed']) {
            \Security::logSecurityEvent('LOGIN_RATE_LIMIT', [
                'ip'          => $this->request->getIp(),
                'retry_after' => $rateLimit['retry_after'],
            ]);

            Session::flash('error', 'Too many login attempts. Please try again later.');
            $this->redirect(APP_URL . '/login');
        }

        // Get credentials
        $email    = trim($this->request->post('email', ''));
        $password = $this->request->post('password', '');

        if (empty($email) || empty($password)) {
            \Security::logSecurityEvent('LOGIN_EMPTY_CREDENTIALS', ['email' => $email, 'ip' => $this->request->getIp()]);
            Session::flash('error', 'Please enter your email/phone and password.');
            $this->redirect(APP_URL . '/login');
        }

        $isEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
        $isPhone = preg_match('/^[0-9+\s\-]{8,15}$/', $email);

        if (!$isEmail && !$isPhone) {
            \Security::logSecurityEvent('LOGIN_INVALID_IDENTIFIER', ['email' => $email, 'ip' => $this->request->getIp()]);
            Session::flash('error', 'Invalid email address or phone number.');
            $this->redirect(APP_URL . '/login');
        }

        // Find user
        $userModel = new User();
        $user = method_exists($userModel, 'findByEmailOrPhone') 
            ? $userModel->findByEmailOrPhone($email) 
            : $userModel->findByEmail($email);

        if (!$user || !$user->exists()) {
            \Security::logSecurityEvent('LOGIN_USER_NOT_FOUND', [
                'email' => $email,
                'ip'    => $this->request->getIp(),
            ]);

            Session::flash('error', 'Invalid credentials. Please try again.');
            $this->redirect(APP_URL . '/login');
        }

        // Check if user is active
        if (!$user->isActive()) {
            \Security::logSecurityEvent('LOGIN_USER_INACTIVE', [
                'user_id' => $user->getId(),
                'email'   => $email,
                'ip'      => $this->request->getIp(),
            ]);

            Session::flash('error', 'Your account has been disabled. Contact support.');
            $this->redirect(APP_URL . '/login');
        }

        // Verify password
        if (!$user->verifyPassword($password)) {
            \Security::logSecurityEvent('LOGIN_WRONG_PASSWORD', [
                'user_id' => $user->getId(),
                'email'   => $email,
                'ip'      => $this->request->getIp(),
            ]);

            Session::flash('error', 'Invalid credentials. Please try again.');
            $this->redirect(APP_URL . '/login');
        }

        // Update last login
        $user->updateLastLogin();

        // Create session
        $roles = $user->getRoles();
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $sessionData = [
            'user_id' => $user->getId(),
            'email'   => $user->getEmail(),
            'roles'   => $roles,
            'name'    => trim(
                ($user->getData('first_name') ?? '') . ' ' . ($user->getData('last_name') ?? '')
            ) ?: $user->getEmail(),
        ];

        Session::create($sessionData);

        \Security::logSecurityEvent('LOGIN_SUCCESS', [
            'user_id' => $user->getId(),
            'email'   => $email,
            'ip'      => $this->request->getIp(),
        ]);

        if (function_exists('apcu_delete')) {
            apcu_delete('ratelimit:' . $rateLimitKey);
        }

        $redirectUrl = $this->request->post('redirect') ?: $this->request->query('redirect');

        if (!$redirectUrl) {
            if (in_array('admin', $roles, true) || in_array('administrator', $roles, true)) {
                $redirectUrl = APP_URL . '/admin/dashboard';
            } elseif (in_array('vendor', $roles, true)) {
                $redirectUrl = APP_URL . '/vendor/dashboard';
            } else {
                $redirectUrl = APP_URL . '/dashboard.php';
            }
        }

        $this->redirect($redirectUrl);
    }

    public function logoutAction(): void
    {
        $userId = Session::getUserId();
        $email  = Session::get('email');

        Session::destroy();

        Security::logSecurityEvent('LOGOUT_SUCCESS', [
            'user_id' => $userId,
            'email'   => $email,
            'ip'      => $this->request->getIp(),
        ]);

        Session::flash('message', 'You have been logged out successfully.');
        $this->redirect(APP_URL . '/login');
    }

    public function checkAction(): void
    {
        if (!$this->request->isAjax()) {
            $this->error('Invalid request', 400);
        }

        $this->json([
            'authenticated' => Session::isAuthenticated(),
            'user_id'       => Session::getUserId(),
            'user_email'    => Session::get('email'),
            'roles'         => Session::get('roles', []),
        ]);
    }
}