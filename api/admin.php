<?php
require_once '../config/config.php';
require_once '../core/Session.php';
require_once '../core/Database.php';
require_once '../classes/AdminController.php';

use App\Core\Session;
use App\Controllers\AdminController;

header('Content-Type: application/json; charset=utf-8');

// Scurit : Vrifier que l'utilisateur est authentifi et ADMIN
if (!Session::isAuthenticated() || Session::get('role') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Accs refus. Privilges administrateur requis.']);
    exit;
}

$controller = new AdminController();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'getStats') {
    echo json_encode($controller->getGlobalStats());
    exit;
}

if ($action === 'getPendingProducts') {
    echo json_encode($controller->getPendingProducts());
    exit;
}

if ($action === 'moderateProduct') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($productId > 0 && in_array($status, ['active', 'rejected', 'suspended'])) {
        $success = $controller->updateProductStatus($productId, $status);
        echo json_encode(['success' => $success]);
    } else {
        echo json_encode(['error' => 'Paramtres invalides']);
    }
    exit;
}

echo json_encode(['error' => 'Action invalide']);