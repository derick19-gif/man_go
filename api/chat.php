<?php
// api/chat.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Autoloader.php';

use App\Controllers\ChatController;

// Plus AUCUN "use App\Core\..." ici !
Session::init();

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit;
}

$request = new App\Core\Request();
$controller = new ChatController($request);
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'send':
            $controller->sendMessage();
            break;
        case 'propose':
            $controller->proposePrice();
            break;
        case 'edit':
            $msg_id = $_POST['id'] ?? 0;
            $new_text = $_POST['message'] ?? '';
            $controller->editMessage($msg_id, $new_text);
            break;
        case 'delete':
            $msg_id = $_REQUEST['id'] ?? 0;
            $controller->deleteMessage($msg_id);
            break;
        case 'get':
            $controller->getMessages();
            break;
        case 'inbox':
            $controller->getInbox();
            break;
        case 'setLabel':
            $controller->setLabel();
            break;
        case 'getQuickReplies':
            $controller->getQuickReplies();
            break;
        case 'addQuickReply':
            $controller->addQuickReply();
            break;
        case 'deleteQuickReply':
            $controller->deleteQuickReply();
            break;
        case 'getBusinessSettings':
            $controller->getBusinessSettings();
            break;
        case 'saveBusinessSettings':
            $controller->saveBusinessSettings();
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action invalide']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}