<?php
// Quick bootstrap test
echo "[1] Setting up APP_PATH\n";
define('APP_PATH', __DIR__);
echo "APP_PATH = " . APP_PATH . "\n\n";

echo "[2] Loading config\n";
$config = include APP_PATH . '/config/config.php';
echo "APP_DEBUG = " . (APP_DEBUG ? 'true' : 'false') . "\n";
echo "APP_URL = " . APP_URL . "\n\n";

echo "[3] Loading core classes\n";
require APP_PATH . '/core/Autoloader.php';
echo "o" Autoloader loaded\n";
require APP_PATH . '/core/Database.php';
echo "o" Database loaded\n";
require APP_PATH . '/core/Session.php';
echo "o" Session loaded\n";
require APP_PATH . '/core/Request.php';
echo "o" Request loaded\n";
require APP_PATH . '/core/Response.php';
echo "o" Response loaded\n";
require APP_PATH . '/core/Router.php';
echo "o" Router loaded\n";
require APP_PATH . '/core/Controller.php';
echo "o" Controller loaded\n";
require APP_PATH . '/classes/Security.php';
echo "o" Security loaded\n\n";

echo "[4] Registering Autoloader\n";
Autoloader::register();
echo "o" Autoloader registered\n\n";

echo "[5] Testing Session initialization\n";
Session::init();
echo "o" Session initialized\n";
echo "Session name: " . session_name() . "\n\n";

echo "[6] Testing Router\n";
$router = new Router();
echo "o" Router created\n\n";

echo "o. All systems go!\n";
?>
