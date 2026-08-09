<?php
// Quick test to verify controller loading
define('APP_PATH', __DIR__);

// Load all necessary classes
require APP_PATH . '/config/config.php';
require APP_PATH . '/core/Autoloader.php';
require APP_PATH . '/core/Database.php';
require APP_PATH . '/core/Session.php';
require APP_PATH . '/core/Request.php';
require APP_PATH . '/core/Response.php';
require APP_PATH . '/core/Router.php';
require APP_PATH . '/core/Controller.php';
require APP_PATH . '/classes/Security.php';

Autoloader::register();

echo "[TEST] Checking controller file path resolution:\n";

$controller = 'auth';
$controllerFile = APP_PATH . '/modules/' . strtolower($controller) . '/controllers/' . ucfirst($controller) . 'Controller.php';
echo "Controller file path: " . $controllerFile . "\n";
echo "File exists: " . (file_exists($controllerFile) ? 'YES o"' : 'NO o-') . "\n";

if (file_exists($controllerFile)) {
    include_once $controllerFile;
    $controllerClass = ucfirst($controller) . 'Controller';
    echo "Class exists: " . (class_exists($controllerClass) ? 'YES o"' : 'NO o-') . "\n";
    
    if (class_exists($controllerClass)) {
        // Create a mock request
        $mockRequest = new Request();
        $instance = new $controllerClass($mockRequest);
        echo "Controller instance created: YES o"\n";
        echo "Has loginAction method: " . (method_exists($instance, 'loginAction') ? 'YES o"' : 'NO o-') . "\n";
        echo "Has authenticateAction method: " . (method_exists($instance, 'authenticateAction') ? 'YES o"' : 'NO o-') . "\n";
    }
}

echo "\no. Controller loading test complete!\n";
?>
