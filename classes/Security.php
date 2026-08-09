<?php
/**
 * Security Helper Class
 * 
 * Provides security utilities: password hashing, CSRF tokens, input validation, etc.
 */

class Security
{
    /**
     * Hash a password using bcrypt
     * 
     * @param string $password
     * @return string
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]);
    }

    /**
     * Verify password against hash
     * 
     * @param string $password
     * @param string $hash
     * @return boolean
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehashing
     * 
     * @param string $hash
     * @return boolean
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 11]);
    }

    /**
     * Generate CSRF token
     * 
     * @return string
     */
    public static function generateCsrfToken(): string
    {
        if (!Session::has(CSRF_TOKEN_NAME)) {
            $token = bin2hex(random_bytes(32));
            Session::set(CSRF_TOKEN_NAME, [
                'token' => $token,
                'created_at' => time(),
            ]);
        }

        return Session::get(CSRF_TOKEN_NAME)['token'];
    }

    /**
     * Verify CSRF token
     * 
     * @param string $token
     * @return boolean
     */
    public static function verifyCsrfToken(string $token): bool
    {
        if (!Session::has(CSRF_TOKEN_NAME)) {
            return false;
        }

        $stored = Session::get(CSRF_TOKEN_NAME);
        
        // Check if token expired
        if (time() - $stored['created_at'] > CSRF_TOKEN_LIFETIME) {
            Session::remove(CSRF_TOKEN_NAME);
            return false;
        }

        return hash_equals($stored['token'], $token);
    }

    /**
     * Sanitize input string
     * 
     * @param string $input
     * @return string
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email address
     * Accepts standard emails and local/reserved TLDs (e.g. .localhost, .test, .invalid)
     * 
     * @param string $email
     * @return boolean
     */
    public static function validateEmail(string $email): bool
    {
        // Standard validation
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        // Fallback: accept emails with local/reserved TLDs that FILTER_VALIDATE_EMAIL rejects
        // e.g. admin@localhost, user@test, contact@invalid
        $localTlds = [
            'localhost',
            'test',
            'invalid',
            'example',
            'local',
        ];

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        [$local, $domain] = $parts;

        // Validate local part (basic email-safe characters)
        if (!preg_match('/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+$/', $local)) {
            return false;
        }

        // Check if domain is a local/reserved TLD (no dots)
        if (in_array(strtolower($domain), $localTlds, true)) {
            return strlen($local) >= 1 && strlen($local) <= 64;
        }

        return false;
    }

    /**
     * Validate password strength
     * 
     * @param string $password
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            $errors[] = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters long";
        }

        if (REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        if (REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        if (REQUIRE_SPECIAL_CHARS && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }

    /**
     * Generate a secure random token
     * 
     * @param int $length
     * @return string
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Verify IP address hasn't changed
     * 
     * @return boolean
     */
    public static function verifyIpAddress(): bool
    {
        if (!Session::has('_ip_address')) {
            Session::set('_ip_address', $_SERVER['REMOTE_ADDR']);
            return true;
        }

        return Session::get('_ip_address') === $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Get client IP address
     * 
     * @return string
     */
    public static function getClientIp(): string
    {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return trim($ip);
    }

    /**
     * Rate limit check using key-value store (can be extended to Redis)
     * Falls back to $_SESSION if APCu is not available.
     * 
     * @param string $key
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int|null]
     */
    public static function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 900): array
    {
        $cacheKey = "ratelimit:{$key}";

        if (function_exists('apcu_fetch')) {
            return self::checkRateLimitApcu($cacheKey, $maxAttempts, $windowSeconds);
        }

        return self::checkRateLimitSession($cacheKey, $maxAttempts, $windowSeconds);
    }

    /**
     * Rate limit check using APCu cache
     * 
     * @param string $cacheKey
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @return array
     */
    private static function checkRateLimitApcu(string $cacheKey, int $maxAttempts, int $windowSeconds): array
    {
        $attempts = apcu_fetch($cacheKey);

        if ($attempts === false) {
            apcu_store($cacheKey, 1, $windowSeconds);
            return [
                'allowed' => true,
                'remaining' => $maxAttempts - 1,
                'retry_after' => null,
            ];
        }

        if ($attempts >= $maxAttempts) {
            $ttl = apcu_cache_info('user')[$cacheKey]['ttl'] ?? $windowSeconds;
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $ttl,
            ];
        }

        apcu_inc($cacheKey);

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - $attempts - 1,
            'retry_after' => null,
        ];
    }

    /**
     * Rate limit check using $_SESSION (fallback when APCu is unavailable)
     * 
     * @param string $cacheKey
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @return array
     */
    private static function checkRateLimitSession(string $cacheKey, int $maxAttempts, int $windowSeconds): array
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize rate limit storage in session if needed
        if (!isset($_SESSION['_rate_limits'])) {
            $_SESSION['_rate_limits'] = [];
        }

        $now = time();

        // Clean expired entries
        foreach ($_SESSION['_rate_limits'] as $existingKey => $data) {
            if ($data['expires_at'] <= $now) {
                unset($_SESSION['_rate_limits'][$existingKey]);
            }
        }

        // Check current key
        if (!isset($_SESSION['_rate_limits'][$cacheKey])) {
            $_SESSION['_rate_limits'][$cacheKey] = [
                'attempts' => 1,
                'expires_at' => $now + $windowSeconds,
            ];
            return [
                'allowed' => true,
                'remaining' => $maxAttempts - 1,
                'retry_after' => null,
            ];
        }

        $record = &$_SESSION['_rate_limits'][$cacheKey];

        // If window expired, reset
        if ($record['expires_at'] <= $now) {
            $record['attempts'] = 1;
            $record['expires_at'] = $now + $windowSeconds;
            return [
                'allowed' => true,
                'remaining' => $maxAttempts - 1,
                'retry_after' => null,
            ];
        }

        if ($record['attempts'] >= $maxAttempts) {
            $retryAfter = $record['expires_at'] - $now;
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $retryAfter,
            ];
        }

        $record['attempts']++;

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - $record['attempts'],
            'retry_after' => null,
        ];
    }

    /**
     * Log security event
     * 
     * @param string $event
     * @param array $data
     * @return void
     */
    public static function logSecurityEvent(string $event, array $data = []): void
    {
        $logData = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        ], $data);

        $logMessage = json_encode($logData);
        error_log("[SECURITY] {$logMessage}", 0);
    }
}
