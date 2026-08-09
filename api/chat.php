<?php
// api/chat.php - Routeur d'API pour le Chat MAN GO Shield
require_once '../config/config.php';
require_once '../core/Session.php';
require_once '../core/Database.php';
require_once '../classes/ChatController.php';

use App\Controllers\ChatController;
use App\Core\Session;

header('Content-Type: application/json');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autoris']);
    exit;
}

$controller = new ChatController();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        // --- MESSAGES DE BASE ---
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

        // --- TIQUETTES (LABELS) ---
        case 'setLabel':
            $controller->setLabel();
            break;

        // --- RPONSES RAPIDES (QUICK REPLIES) ---
        case 'getQuickReplies':
            $controller->getQuickReplies();
            break;
        case 'addQuickReply':
            $controller->addQuickReply();
            break;
        case 'deleteQuickReply':
            $controller->deleteQuickReply();
            break;

        // --- PARAMTRES BUSINESS (ACCUEIL & ABSENCE) ---
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