<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../core/Database.php';
require_once '../core/Session.php';
require_once '../classes/OrderController.php';

use App\Core\Database;
use App\Core\Session;
use App\Controllers\OrderController;

$db = Database::getInstance();
$controller = new OrderController($db);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$current_user_id = Session::getUserId();

switch ($action) {
    case 'getProductDetail':
        $id = (int)($_GET['id'] ?? 0);
        $product = $controller->getProductDetail($id);
        if ($product) {
            echo json_encode(['status' => 'success', 'product' => $product]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Produit non trouv']);
        }
        break;

    case 'proposeOffer':
        if (!Session::isAuthenticated()) {
            echo json_encode(['status' => 'error', 'message' => 'Non authentifi']);
            exit;
        }
        $productId  = (int)($_POST['product_id'] ?? 0);
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $price      = (float)($_POST['price'] ?? 0);
        $message    = $_POST['message'] ?? '';

        $res = $controller->proposeOffer($current_user_id, $receiverId, $productId, $price, $message);
        echo json_encode($res);
        break;

    case 'createOrder':
        if (!Session::isAuthenticated()) {
            echo json_encode(['status' => 'error', 'message' => 'Non authentifi']);
            exit;
        }
        $productId = (int)($_POST['product_id'] ?? 0);
        $vendorId  = (int)($_POST['vendor_id'] ?? 0);
        $amount    = (float)($_POST['amount'] ?? 0);

        $res = $controller->createOrder($current_user_id, $vendorId, $productId, $amount);
        echo json_encode($res);
        break;

    case 'getUserOrders':
        if (!Session::isAuthenticated()) {
            echo json_encode([]);
            exit;
        }
        $orders = $controller->getUserOrders($current_user_id);
        echo json_encode($orders);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Action inconnue']);
        break;
}