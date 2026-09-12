<?php
// api/vendor.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Autoloader.php';

use App\Controllers\VendorController;

// Plus AUCUN "use App\Core\..." ici ! On appelle directement Session et Database
Session::init();

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit;
}

try {
    $db = function_exists('getDBConnection') ? getDBConnection() : Database::getInstance();
    $controller = new VendorController($db);
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $current_user_id = Session::getUserId();

    switch ($action) {
        case 'getStats':
            $stats = $controller->getVendorStats($current_user_id);
            echo json_encode($stats);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action invalide']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}