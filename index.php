<?php
// =========================================================================
// POINT D'ENTRÉE UNIQUE (FRONT CONTROLLER) - index.php à la racine
// =========================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🛡️ NETTOYAGE GLOBAL : Si l'URL contient /index.php, on le retire discrètement 
// pour que le routeur voie uniquement une route propre (ex: / au lieu de /index.php)
if (isset($_SERVER['REQUEST_URI'])) {
    // Remplace /man_go/index.php par /man_go/ ou /index.php par /
    $_SERVER['REQUEST_URI'] = str_replace('/index.php', '', $_SERVER['REQUEST_URI']);
}

// Inclusion des fichiers du noyau et de configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Router.php';

// --- CONFIGURATION DES ROUTES ---
$router = new Router();

// Route de la page d'accueil
$router->get('/', 'HomeController@index');

// Routes principales (Correction des correspondances)
$router->get('/listings', 'ListingController@index');
$router->get('/publish', 'ListingController@create');
$router->post('/publish', 'ListingController@store');
$router->get('/stands', 'StandController@index');
$router->get('/services', 'ServiceController@index'); 

// Routes d'authentification
$router->get('/login', 'AuthController@loginAction');
$router->post('/login', 'AuthController@authenticateAction');
$router->get('/register', 'AuthController@registerAction');
$router->get('/logout', 'AuthController@logoutAction');

// --- LANCEMENT DU ROUTEUR ---
try {
    $router->resolve();
} catch (Exception $e) {
    http_response_code(404);
    echo "<h1>404 - Page non trouvée</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
}