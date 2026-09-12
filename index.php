<?php
// =========================================================================
// POINT D'ENTRÉE UNIQUE (FRONT CONTROLLER) - index.php à la racine
// =========================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 🛡️ NETTOYAGE GLOBAL : Si l'URL contient /index.php, on le retire discrètement 
if (isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = str_replace('/index.php', '', $_SERVER['REQUEST_URI']);
}

// Inclusion des fichiers du noyau et de configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Router.php';

// ✨ LA CORRECTION MAGIQUE EST ICI : 
// On supprime le vieux session_start() et on initialise la session 
// UNIQUEMENT via votre classe haute sécurité, APRÈS l'avoir chargée.
Session::init();

use App\Core\Router;

// --- CONFIGURATION DES ROUTES ---
$router = new Router();

// Route de la page d'accueil
$router->get('/', 'HomeController@index');

// Routes principales
$router->get('/listings', 'ListingController@index');
$router->get('/publish', 'ListingController@create');
$router->post('/publish', 'ListingController@store');
$router->get('/client/dashboard', 'DashboardController@index');
$router->get('/stands', 'StandController@index');
$router->get('/services', 'ServiceController@index'); 

// Routes d'authentification
$router->get('/login', 'AuthController@loginAction');
$router->post('/login', 'AuthController@authenticateAction');
$router->get('/register', 'AuthController@registerAction');
$router->get('/logout', 'AuthController@logoutAction');
$router->get('/stands/detail', 'StandController@detail');
$router->get('/stands/create', 'StandController@create');
$router->post('/stands/store', 'StandController@store');

// Routes pour le module KYC
$router->get('/kyc', 'Modules\Kyc\Controllers\KycController@index');
$router->post('/kyc/submit', 'Modules\Kyc\Controllers\KycController@submit');

// --- LANCEMENT DU ROUTEUR ---
try {
    $router->resolve();
} catch (Exception $e) {
    http_response_code(404);
    echo "<h1>404 - Page non trouvée</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
}