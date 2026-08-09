<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Database;

class ChatController extends Controller {

    /**
     * Envoie un message avec gestion automatique des filtres et rponses automatiques (accueil / absence)
     */
    public function sendMessage() {
        $db = Database::connect();
        $sender_id = Session::getUserId();
        $receiver_id = $_POST['receiver_id'] ?? 0;
        $message = $this->filterMessage($_POST['message'] ?? '');
        $product_id = $_POST['product_id'] ?? null;

        if (empty($receiver_id) || empty($message)) {
            echo json_encode(['status' => 'error', 'message' => 'Donnes incompltes']);
            return;
        }

        // Insertion du message principal
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at, product_id) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([$sender_id, $receiver_id, $message, $product_id]);

        // Vrification si le destinataire possde des rponses automatiques / absence
        $stmtBusiness = $db->prepare("SELECT * FROM business_settings WHERE user_id = ?");
        $stmtBusiness->execute([$receiver_id]);
        $settings = $stmtBusiness->fetch(\PDO::FETCH_ASSOC);

        if ($settings) {
            $autoReply = null;

            // Si le destinataire est en mode absence
            if ($settings['is_away'] && !empty($settings['auto_reply_message'])) {
                $autoReply = $settings['auto_reply_message'];
            } 
            // Sinon, s'il s'agit de la premire interaction et qu'un message d'accueil est dfini
            elseif ($settings['auto_reply_enabled'] && !empty($settings['welcome_message'])) {
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ?");
                $stmtCheck->execute([$sender_id, $receiver_id]);
                if ($stmtCheck->fetchColumn() <= 1) {
                    $autoReply = $settings['welcome_message'];
                }
            }

            // Envoi de la rponse automatique du systme au nom du destinataire
            if ($autoReply) {
                $stmtAuto = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at, product_id) VALUES (?, ?, ?, NOW(), ?)");
                $stmtAuto->execute([$receiver_id, $sender_id, $autoReply, $product_id]);
            }
        }

        echo json_encode(['status' => 'success']);
    }

    public function deleteMessage($msg_id) {
        $db = Database::connect();
        $user_id = Session::getUserId();
        $stmt = $db->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
        $stmt->execute([$msg_id, $user_id]);
        echo json_encode(['status' => 'success']);
    }

    public function editMessage($msg_id, $new_text) {
        $db = Database::connect();
        $user_id = Session::getUserId();
        $filtered_text = $this->filterMessage($new_text);
        $stmt = $db->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ?");
        $stmt->execute([$filtered_text, $msg_id, $user_id]);
        echo json_encode(['status' => 'success']);
    }

    public function setLabel() {
        $db = Database::connect();
        $user_id = Session::getUserId();
        $chat_id = $_POST['chat_id'] ?? 0;
        $label = $_POST['label'] ?? '';

        $stmt = $db->prepare("INSERT INTO chat_labels (user_id, chat_id, label) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE label=?");
        $stmt->execute([$user_id, $chat_id, $label, $label]);
        echo json_encode(['status' => 'success']);
    }

    private function filterMessage($text) {
        $text = preg_replace('/(\+?\d{1,4}[ -]?)?\(?\d{2,3}\)?[ -]?\d{3}[ -]?\d{4}/', '[NUMRO MASQU]', $text);
        $text = preg_replace('/(https?:\/\/[^\s]+)/', '[LIEN EXTERNE BLOQU]', $text);
        return htmlspecialchars($text);
    }

    public function getMessages() {
        $db = Database::connect();
        $sender_id = Session::getUserId();
        $receiver_id = $_GET['receiver_id'] ?? 0;

        // Marquer les messages reus comme lus
        $stmtRead = $db->prepare("UPDATE messages SET is_read = 1, status = 'read' WHERE sender_id = ? AND receiver_id = ?");
        $stmtRead->execute([$receiver_id, $sender_id]);

        $stmt = $db->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
        $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function getInbox() {
        $db = Database::connect();
        $user_id = Session::getUserId();

        $stmt = $db->prepare("
            SELECT 
                m1.*,
                CASE WHEN m1.sender_id = ? THEN m1.receiver_id ELSE m1.sender_id END AS contact_id,
                l.label
            FROM messages m1
            INNER JOIN (
                SELECT 
                    LEAST(sender_id, receiver_id) AS user1,
                    GREATEST(sender_id, receiver_id) AS user2,
                    MAX(id) AS max_id
                FROM messages
                WHERE sender_id = ? OR receiver_id = ?
                GROUP BY user1, user2
            ) m2 ON m1.id = m2.max_id
            LEFT JOIN chat_labels l ON l.user_id = ? AND l.chat_id = (CASE WHEN m1.sender_id = ? THEN m1.receiver_id ELSE m1.sender_id END)
            ORDER BY m1.created_at DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // --- RPONSES RAPIDES (QUICK REPLIES) ---

    public function getQuickReplies() {
        $db = Database::connect();
        $user_id = Session::getUserId();
        $stmt = $db->prepare("SELECT * FROM quick_replies WHERE user_id = ?");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function addQuickReply() {
        $db = Database::connect();
        $user_id = Session::getUserId();
        $shortcut = $_POST['shortcut'] ?? '';
        $message = $_POST['message'] ?? '';

        $stmt = $db->prepare("INSERT INTO quick_replies (user_id, shortcut, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $shortcut, $message]);
        echo json_encode(['status' => 'success']);
    }

    public function deleteQuickReply() {
        $db = Database::connect();
        $user_id = Session::getUserId();
        $id = $_REQUEST['id'] ?? 0;

        $stmt = $db->prepare("DELETE FROM quick_replies WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['status' => 'success']);
    }

    // --- MESSAGES D'ACCUEIL & D'ABSENCE (BUSINESS SETTINGS) ---

    public function getBusinessSettings() {
        $db = Database::connect();
        $user_id = Session::getUserId();
        $stmt = $db->prepare("SELECT * FROM business_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch(\PDO::FETCH_ASSOC);

        echo json_encode($settings ?: [
            'user_id' => $user_id,
            'auto_reply_enabled' => 0,
            'auto_reply_message' => '',
            'welcome_message' => '',
            'is_away' => 0
        ]);
    }

    public function saveBusinessSettings() {
        $db = Database::connect();
        $user_id = Session::getUserId();
        $auto_reply_enabled = $_POST['auto_reply_enabled'] ?? 0;
        $auto_reply_message = $_POST['auto_reply_message'] ?? '';
        $welcome_message = $_POST['welcome_message'] ?? '';
        $is_away = $_POST['is_away'] ?? 0;

        $stmt = $db->prepare("
            INSERT INTO business_settings (user_id, auto_reply_enabled, auto_reply_message, welcome_message, is_away)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                auto_reply_enabled = VALUES(auto_reply_enabled),
                auto_reply_message = VALUES(auto_reply_message),
                welcome_message = VALUES(welcome_message),
                is_away = VALUES(is_away)
        ");
        $stmt->execute([$user_id, $auto_reply_enabled, $auto_reply_message, $welcome_message, $is_away]);
        echo json_encode(['status' => 'success']);
    }

    public function proposePrice() {
        // Ngociation d'offre / proposition de prix
        echo json_encode(['status' => 'success', 'message' => 'Offre soumise']);
    }
}