<?php
/**
 * Core Session Management
 * 
 * Gestion de session haute sécurité avec protection contre le vol de session (fingerprint),
 * gestion du timeout, messages flash et repli automatique si les constantes globales manquent.
 */

class Session
{
    private static array $data = [];

    /**
     * Chargement préventif des configurations globales si elles manquent
     */
    private static function ensureConfigLoaded(): void
    {
        if (!defined('SESSION_SECURE') && file_exists(__DIR__ . '/../config/config.php')) {
            require_once __DIR__ . '/../config/config.php';
        } elseif (!defined('SESSION_SECURE') && file_exists(__DIR__ . '/../config.php')) {
            require_once __DIR__ . '/../config.php';
        }
    }

    /**
     * Initialise la session de façon sécurisée
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::ensureConfigLoaded();

            $isSecure = defined('SESSION_SECURE') 
                ? (bool) SESSION_SECURE 
                : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

            $httpOnly    = defined('SESSION_HTTPONLY') ? (bool) SESSION_HTTPONLY : true;
            $sameSite    = defined('SESSION_SAMESITE') ? SESSION_SAMESITE : 'Lax';
            $sessionName = defined('SESSION_NAME')     ? SESSION_NAME     : 'MANGO_SESSID';

            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => $httpOnly,
                'samesite' => $sameSite
            ]);

            session_name($sessionName);
            session_start();

            self::validate();

            self::$data = &$_SESSION;
        }
    }

    /**
     * Valide l'empreinte de la session et gère l'expiration par inactivité
     */
    private static function validate(): void
    {
        if (isset($_SESSION['_auth_fingerprint'])) {
            
            $currentFingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? ''));
            if (!hash_equals($_SESSION['_auth_fingerprint'], $currentFingerprint)) {
                self::destroy();
                return;
            }

            $timeout = defined('SESSION_TIMEOUT') ? (int) SESSION_TIMEOUT : 1800;
            if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity'] > $timeout)) {
                self::destroy();
                
                // Redirection sécurisée MVC via BASE_URL_PATH
                $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '/man_go';
                header('Location: ' . $basePath . '/login?expired=1');
                exit;
            }

            $_SESSION['_last_activity'] = time();
        }
    }

    public static function create(array $data): void
    {
        self::init();

        session_regenerate_id(true);

        foreach ($data as $key => $value) {
            $_SESSION[$key] = $value;
        }

        $_SESSION['_auth_fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $_SESSION['_last_activity']    = time();
        $_SESSION['_created_at']       = time();
    }

    public static function get(?string $key = null, mixed $default = null): mixed
    {
        self::init();

        if ($key === null) {
            return $_SESSION;
        }

        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::init();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::init();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::init();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            $_SESSION = [];

            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            session_destroy();
        }
    }

    public static function isAuthenticated(): bool
    {
        self::init();
        return isset($_SESSION['user_id']) && isset($_SESSION['_auth_fingerprint']);
    }

    public static function getUserId(): ?int
    {
        self::init();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function flash(string $key, string $message, string $type = 'info'): void
    {
        self::init();
        $_SESSION['_flash'][$key] = [
            'message' => $message,
            'type'    => $type,
        ];
    }

    public static function getFlash(string $key): ?array
    {
        self::init();

        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }

        $flash = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);

        return $flash;
    }
}