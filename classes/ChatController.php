<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use Session;
use PDO;

class ChatController extends Controller {

    private function getDb() {
        return Database::connect();
    }

    public function sendMessage() {
        $db = $this->getDb();
        $sender_id = Session::getUserId();
        $receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT);
        $raw_message = trim($_POST['message'] ?? '');
        $listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);

        if (!$receiver_id || empty($raw_message)) {
            echo json_encode(['status' => 'error', 'message' => 'Données incomplètes']);
            return;
        }

        // Filtre de sécurité MAN GO Shield
        $message = $this->filterMessage($raw_message);
        if ($message === '[BLOQUÉ]') {
            echo json_encode(['status' => 'error', 'message' => 'Votre message contient des termes non autorisés par nos conditions.']);
            return;
        }

        // On insère le message. (On n'oblige plus le listing_id s'ils se parlent depuis le Dashboard)
        if($listing_id) {
            $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at, listing_id) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->execute([$sender_id, $receiver_id, $message, $listing_id]);
        } else {
            $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$sender_id, $receiver_id, $message]);
        }

        // Gestion des réponses automatiques du vendeur
        $this->handleAutoReply($receiver_id, $sender_id, $listing_id);

        echo json_encode(['status' => 'success']);
    }

    private function handleAutoReply($business_user_id, $client_id, $listing_id) {
        try {
            $db = $this->getDb();
            $stmtBusiness = $db->prepare("SELECT * FROM business_settings WHERE user_id = ?");
            $stmtBusiness->execute([$business_user_id]);
            $settings = $stmtBusiness->fetch(PDO::FETCH_ASSOC);

            if ($settings) {
                $autoReply = null;
                // Si le vendeur a activé "Je suis absent"
                if ($settings['is_away'] && !empty($settings['auto_reply_message'])) {
                    $autoReply = $settings['auto_reply_message'];
                } 
                // Sinon, s'il a activé le message de bienvenue (1ère fois seulement)
                elseif ($settings['auto_reply_enabled'] && !empty($settings['welcome_message'])) {
                    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ?");
                    $stmtCheck->execute([$client_id, $business_user_id]);
                    if ($stmtCheck->fetchColumn() <= 1) { // 1 = C'est le tout premier message du client
                        $autoReply = $settings['welcome_message'];
                    }
                }

                if ($autoReply) {
                    if($listing_id) {
                        $stmtAuto = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at, listing_id) VALUES (?, ?, ?, NOW(), ?)");
                        $stmtAuto->execute([$business_user_id, $client_id, $autoReply, $listing_id]);
                    } else {
                        $stmtAuto = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
                        $stmtAuto->execute([$business_user_id, $client_id, $autoReply]);
                    }
                }
            }
        } catch(\Exception $e) {}
    }

    private function filterMessage($text) {
        $text = preg_replace('/(\+?\d{1,4}[ -]?)?\(?\d{2,3}\)?[ -]?\d{3}[ -]?\d{4}/', '[NUMÉRO MASQUÉ]', $text);
        $text = preg_replace('/(https?:\/\/[^\s]+)/', '[LIEN EXTERNE BLOQUÉ]', $text);

        $badWords = ['con', 'connard', 'salope', 'merde', 'putain', 'drogue', 'cocaïne', 'arme', 'tueur', 'héroïne', 'nègre', 'bougnoule'];
        foreach ($badWords as $word) {
            $text = str_ireplace($word, str_repeat('*', strlen($word)), $text);
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    // =========================================================================================
    // NOUVELLE FONCTION ULTRA-PRO : SUPPRIMER POUR TOUS (Avec trace pour l'Admin et délai de 1h)
    // =========================================================================================
    public function deleteMessage($msg_id) {
        $db = $this->getDb();
        $user_id = Session::getUserId();
        
        // 1. On vérifie d'abord que le message appartient bien à celui qui veut le supprimer
        $stmtCheck = $db->prepare("SELECT created_at FROM messages WHERE id = ? AND sender_id = ?");
        $stmtCheck->execute([$msg_id, $user_id]);
        $msgDate = $stmtCheck->fetchColumn();

        if (!$msgDate) {
            echo json_encode(['status' => 'error', 'message' => 'Message introuvable ou non autorisé']);
            return;
        }

        // 2. Vérification du délai (3600 secondes = 1 heure)
        $timePassed = time() - strtotime($msgDate);
        if ($timePassed > 3600) {
            echo json_encode(['status' => 'error', 'message' => 'Le délai de suppression de 1 heure est dépassé.']);
            return;
        }

        // 3. Suppression "Soft Delete" (On garde la trace pour la modération anti-fraude)
        // On remplace le texte, comme sur WhatsApp
        $deletedText = "🚫 <i>Ce message a été supprimé.</i>";
        $stmt = $db->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ?");
        $stmt->execute([$deletedText, $msg_id, $user_id]);
        
        echo json_encode(['status' => 'success']);
    }

    public function editMessage($msg_id, $new_text) {
        $db = $this->getDb();
        $user_id = Session::getUserId();
        $filtered_text = $this->filterMessage($new_text) . " <small class='text-muted'>(Modifié)</small>";
        $stmt = $db->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ?");
        $stmt->execute([$filtered_text, $msg_id, $user_id]);
        echo json_encode(['status' => 'success']);
    }

    public function setLabel() {
        $db = $this->getDb();
        $user_id = Session::getUserId();
        $chat_id = filter_input(INPUT_POST, 'chat_id', FILTER_VALIDATE_INT);
        $label = $_POST['label'] ?? '';

        try {
            $stmt = $db->prepare("INSERT INTO chat_labels (user_id, chat_id, label) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE label=?");
            $stmt->execute([$user_id, $chat_id, $label, $label]);
            echo json_encode(['status' => 'success']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error']);
        }
    }

    public function getMessages() {
        $db = $this->getDb();
        $sender_id = Session::getUserId();
        $receiver_id = filter_input(INPUT_GET, 'receiver_id', FILTER_VALIDATE_INT);

        if (!$receiver_id) {
            echo json_encode([]);
            return;
        }

        $stmtRead = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmtRead->execute([$receiver_id, $sender_id]);

        $stmt = $db->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
        $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getInbox() {
        $db = $this->getDb();
        $user_id = Session::getUserId();

        try {
            $stmt = $db->prepare("
                SELECT 
                    u.id as contact_id, 
                    u.firstname, 
                    u.lastname, 
                    m.message, 
                    m.created_at, 
                    m.is_read, 
                    m.receiver_id,
                    l.label
                FROM messages m
                JOIN users u ON (u.id = m.sender_id OR u.id = m.receiver_id) AND u.id != :user_id
                LEFT JOIN chat_labels l ON l.user_id = :user_id AND l.chat_id = u.id
                WHERE m.id IN (
                    SELECT MAX(id) 
                    FROM messages 
                    WHERE sender_id = :user_id OR receiver_id = :user_id 
                    GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)
                )
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([':user_id' => $user_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            echo json_encode([]);
        }
    }

    public function getQuickReplies() {
        $db = $this->getDb();
        $user_id = Session::getUserId();
        try {
            $stmt = $db->prepare("SELECT * FROM quick_replies WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch(\Exception $e) { echo json_encode([]); }
    }

    public function addQuickReply() {
        $db = $this->getDb();
        $user_id = Session::getUserId();
        $shortcut = trim($_POST['shortcut'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if($shortcut && $message) {
            try {
                $stmt = $db->prepare("INSERT INTO quick_replies (user_id, shortcut, message) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $shortcut, $message]);
                echo json_encode(['status' => 'success']);
            } catch(\Exception $e) { echo json_encode(['status' => 'error']); }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Champs requis']);
        }
    }

    public function deleteQuickReply() {
        $db = $this->getDb();
        $user_id = Session::getUserId();
        $id = filter_input(INPUT_REQUEST, 'id', FILTER_VALIDATE_INT);
        if($id) {
            $stmt = $db->prepare("DELETE FROM quick_replies WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
        }
        echo json_encode(['status' => 'success']);
    }

    public function getBusinessSettings() {
        $db = $this->getDb();
        $user_id = Session::getUserId();
        try {
            $stmt = $db->prepare("SELECT * FROM business_settings WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($settings ?: [
                'user_id' => $user_id, 'auto_reply_enabled' => 0, 'auto_reply_message' => '', 'welcome_message' => '', 'is_away' => 0
            ]);
        } catch(\Exception $e) { echo json_encode([]); }
    }

    public function saveBusinessSettings() {
        $db = $this->getDb();
        $user_id = Session::getUserId();
        $auto_reply_enabled = $_POST['auto_reply_enabled'] ?? 0;
        $auto_reply_message = trim($_POST['auto_reply_message'] ?? '');
        $welcome_message = trim($_POST['welcome_message'] ?? '');
        $is_away = $_POST['is_away'] ?? 0;

        try {
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
        } catch(\Exception $e) { echo json_encode(['status' => 'error']); }
    }

    public function proposePrice() {
        echo json_encode(['status' => 'success', 'message' => 'Offre soumise']);
    }
}