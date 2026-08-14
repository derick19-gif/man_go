<?php
// ============================================================================
// MAN GO Global Configuration File
// ============================================================================

// Base de donnes
define('DB_HOST', 'localhost');
define('DB_NAME', 'mango_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Environnement et Paramtres
// Route et URL de base
define('BASE_URL_PATH', '/man_go');
define('BASE_URL', 'http://localhost/man_go');
define('APP_URL', 'http://localhost/man_go');
define('APP_NAME', 'MAN GO Marketplace');
define('APP_VERSION', '1.0.0');
define('DEFAULT_LANG', 'fr');
define('APP_DEBUG', true); // Mettre  false en production

if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__));
}

// Scurit & Sessions (Dfinition de toutes les constantes pour Session.php)
define('SESSION_NAME', 'MANGOSESS');
define('SESSION_TIMEOUT', 3600);
define('SESSION_SECURE', false);       // true en production HTTPS
define('SESSION_HTTPONLY', true);     // empche l'accs JS au cookie de session
define('SESSION_SAMESITE', 'Lax');    // Strict, Lax ou None
define('CSRF_TOKEN_NAME', '_token');
define('AUTH_ATTEMPTS_MAX', 5);
define('AUTH_LOCKOUT_TIME', 900);

// Connexion PDO globale (Singleton)
function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            die("Erreur critique de connexion  la base de donnes MAN GO : " . $e->getMessage());
        }
    }
    return $pdo;
}

// Helper Multilingue
if (!function_exists('__t')) {
    function __t(string $key, mixed $params = [], string $lang = DEFAULT_LANG): string {
        // Scurisation : si $params n'est pas un tableau (ex: null ou string transmise par erreur), on le convertit
        if (!is_array($params)) {
            if (is_string($params) && strlen($params) <= 5) {
                // Si le 2me argument semble tre un code langue (ex: 'fr', 'en'), on l'utilise
                $lang = $params;
            }
            $params = [];
        }

        static $translationsCache = [];

        if (!isset($translationsCache[$lang])) {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT trans_key, trans_value FROM translations WHERE lang_code = ?");
                $stmt->execute([$lang]);
                $rows = $stmt->fetchAll();
                
                $translationsCache[$lang] = [];
                foreach ($rows as $row) {
                    $translationsCache[$lang][$row['trans_key']] = $row['trans_value'];
                }
            } catch (Exception $e) {
                $translationsCache[$lang] = [];
            }
        }

        $text = $translationsCache[$lang][$key] ?? $key;

        if (!empty($params)) {
            foreach ($params as $search => $replace) {
                $text = str_replace(':' . $search, (string)$replace, $text);
            }
        }

        return $text;
    }
}