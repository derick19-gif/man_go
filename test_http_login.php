<?php
// Simulate an HTTP request to /login
define('APP_PATH', __DIR__);

// Mock the HTTP request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/login';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Test Client';

// Suppress headers (for CLI testing)
if (!function_exists('header')) {
    function header($header) {} // Mock header function
}

ob_start();

try {
    require APP_PATH . '/index.php';
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

$output = ob_get_clean();

// Check if login form is in the output
if (strpos($output, 'login-card') !== false || strpos($output, 'Sign In') !== false) {
    echo "o. Login page rendered successfully!\n";
    echo "\nFirst 500 chars of HTML:\n";
    echo substr($output, 0, 500) . "...\n";
} else {
    echo "O Login page not found in output\n";
    echo "Output length: " . strlen($output) . " chars\n";
    if ($output) {
        echo "\nFirst 500 chars:\n";
        echo substr($output, 0, 500) . "\n";
    }
}
?>
