<?php
// =========================================================================
// POINT D'ENTRÉE UNIQUE (FRONT CONTROLLER) - index.php à la racine
// =========================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Inclusion des fichiers du noyau et de configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Router.php';

// 2. Initialisation de la session HAUTE SÉCURITÉ
Session::init();

// 3. NETTOYAGE GLOBAL : Retrait de /index.php de l'URL si présent
if (isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = str_replace('/index.php', '', $_SERVER['REQUEST_URI']);
}

use App\Core\Router;

// --- CONFIGURATION DES ROUTES ---
$router = new Router();

// Route de la page d'accueil (C'est lui qui ira chercher les annonces !)
$router->get('/', 'HomeController@index');

// Routes des Annonces (Listings)
$router->get('/listings', 'ListingController@index');
$router->get('/listing_detail', 'ListingController@show'); // Pour voir les détails d'une annonce
$router->get('/publish', 'ListingController@create');
$router->post('/publish', 'ListingController@store');

// Routes des Boutiques & Services
$router->get('/stands', 'StandController@index');
$router->get('/stands/detail', 'StandController@detail');
$router->get('/stands/create', 'StandController@create');
$router->post('/stands/store', 'StandController@store');
$router->get('/services', 'ServiceController@index'); 

// Routes des Tableaux de bord
$router->get('/client/dashboard', 'DashboardController@index');

// Routes d'authentification
$router->get('/register', 'AuthController@registerAction');
$router->get('/logout', 'AuthController@logoutAction');

// Routes pour le module KYC (Sécurité Vendeur)
$router->get('/kyc', 'Modules\Kyc\Controllers\KycController@index');
$router->post('/kyc/submit', 'Modules\Kyc\Controllers\KycController@submit');

// --- LANCEMENT DU ROUTEUR ---
try {
    $router->resolve();
} catch (Exception $e) {
    http_response_code(404);
    echo "<h1>404 - Page non trouvée</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
}