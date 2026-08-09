<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../core/Database.php';
require_once '../core/Session.php';
require_once '../classes/VendorController.php';

use App\Core\Database;
use App\Core\Session;
use App\Controllers\VendorController;

if (!Session::isAuthenticated()) {
    echo json_encode(['status' => 'error', 'message' => 'Non autoris']);
    exit;
}

$db = Database::getInstance();
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