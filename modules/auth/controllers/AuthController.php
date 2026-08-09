<?php

use App\Models\User;

/**
 * Authentication Controller
 * 
 * Handles login, logout, and authentication routes
 */
class AuthController extends Controller
{
    /**
     * Show login form
     * 
     * @return void
     */
    public function loginAction(): void
    {
        if (Session::isAuthenticated()) {
            $this->redirect(APP_URL . '/dashboard');
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

    /**
     * Process login
     * 
     * @return void
     */
    public function authenticateAction(): void
    {
        if (!$this->request->isPost()) {
            $this->redirect(APP_URL . '/login');
        }

        // Verify CSRF token
        $token = $this->request->post('_token');
        if (!Security::verifyCsrfToken($token)) {
            Security::logSecurityEvent('CSRF_FAILED', ['ip' => $this->request->getIp()]);
            Session::flash('error', 'Session expired or invalid token. Please try again.');
            $this->redirect(APP_URL . '/login');
        }

        // Rate limiting
        $rateLimitKey = 'login_' . $this->request->getIp();
        $rateLimit = Security::checkRateLimit($rateLimitKey, AUTH_ATTEMPTS_MAX, AUTH_LOCKOUT_TIME);

        if (!$rateLimit['allowed']) {
            Security::logSecurityEvent('LOGIN_RATE_LIMIT', [
                'ip'          => $this->request->getIp(),
                'retry_after' => $rateLimit['retry_after'],
            ]);

            Session::flash('error', 'Too many login attempts. Please try again later.');
            $this->redirect(APP_URL . '/login');
        }

        // Get credentials
        $email    = trim($this->request->post('email', ''));
        $password = $this->request->post('password', '');

        // Validate input
        if (empty($email) || empty($password)) {
            Security::logSecurityEvent('LOGIN_EMPTY_CREDENTIALS', ['email' => $email, 'ip' => $this->request->getIp()]);
            Session::flash('error', 'Please enter your email and password.');
            $this->redirect(APP_URL . '/login');
        }

        if (!Security::validateEmail($email)) {
            Security::logSecurityEvent('LOGIN_INVALID_EMAIL', ['email' => $email, 'ip' => $this->request->getIp()]);
            Session::flash('error', 'Invalid email address.');
            $this->redirect(APP_URL . '/login');
        }

        // Find user
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user || !$user->exists()) {
            Security::logSecurityEvent('LOGIN_USER_NOT_FOUND', ['email' => $email, 'ip' => $this->request->getIp()]);
            Session::flash('error', 'Invalid credentials. Please try again.');
            $this->redirect(APP_URL . '/login');
        }

        // Check if user is active
        if (!$user->isActive()) {
            Security::logSecurityEvent('LOGIN_USER_INACTIVE', [
                'user_id' => $user->getId(),
                'email'   => $email,
                'ip'      => $this->request->getIp(),
            ]);

            Session::flash('error', 'Your account has been disabled. Contact support.');
            $this->redirect(APP_URL . '/login');
        }

        // Verify password
        if (!$user->verifyPassword($password)) {
            Security::logSecurityEvent('LOGIN_WRONG_PASSWORD', [
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

        Security::logSecurityEvent('LOGIN_SUCCESS', [
            'user_id' => $user->getId(),
            'email'   => $email,
            'ip'      => $this->request->getIp(),
        ]);

        // Clear rate limit on success
        if (function_exists('apcu_delete')) {
            apcu_delete('ratelimit:' . $rateLimitKey);
        }

        // Redirection par rle ou URL de redirection explicite
        $redirectUrl = $this->request->post('redirect') ?: $this->request->query('redirect');

        if (!$redirectUrl) {
            if (in_array('admin', $roles, true) || in_array('administrator', $roles, true)) {
                $redirectUrl = APP_URL . '/admin/dashboard';
            } elseif (in_array('vendor', $roles, true)) {
                $redirectUrl = APP_URL . '/vendor/dashboard';
            } else {
                $redirectUrl = APP_URL . '/';
            }
        }

        $this->redirect($redirectUrl);
    }

    /**
     * Logout
     * 
     * @return void
     */
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

    /**
     * Check if user is authenticated (AJAX)
     * 
     * @return void
     */
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