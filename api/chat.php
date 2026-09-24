<?php
// api/chat.php - Backend de la messagerie MAN GO
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/Autoloader.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Session.php';

Session::init();
header('Content-Type: application/json; charset=utf-8');

// Vérification de sécurité
if (!Session::isAuthenticated()) {
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit;
}

$current_user_id = Session::getUserId();
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

try {
    $db = \App\Core\Database::connect();

    // --- NOUVEAU : On met à jour l'heure de présence de l'utilisateur actuel ---
    $db->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?")->execute([$current_user_id]);

    // --- NOUVEAU : Action pour vérifier si le contact est en ligne ---
    if ($action === 'status') {
        $contact_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $stmt = $db->prepare("SELECT last_seen FROM users WHERE id = ?");
        $stmt->execute([$contact_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $isOnline = false;
        if ($user && $user['last_seen']) {
            $last_seen_time = strtotime($user['last_seen']);
            // Si sa dernière action date de moins de 120 secondes (2 minutes)
            if ((time() - $last_seen_time) < 120) {
                $isOnline = true;
            }
        }
        echo json_encode(['online' => $isOnline]);
        exit;
    }
    
    // =========================================================================
    // 1. CHARGER LA BOÎTE DE RÉCEPTION (INBOX)
    // =========================================================================
    if ($action === 'inbox') {
        // Cette requête trouve le dernier message échangé avec chaque contact
        // Que l'utilisateur soit l'expéditeur (sender) ou le destinataire (receiver)
        $query = "
            SELECT 
                u.id as contact_id, 
                u.firstname, 
                u.lastname, 
                m.message, 
                m.created_at, 
                m.is_read,
                m.receiver_id
            FROM users u
            JOIN (
                SELECT 
                    CASE 
                        WHEN sender_id = ? THEN receiver_id 
                        ELSE sender_id 
                    END as other_user_id,
                    MAX(id) as last_msg_id
                FROM messages
                WHERE sender_id = ? OR receiver_id = ?
                GROUP BY other_user_id
            ) as latest_msg ON u.id = latest_msg.other_user_id
            JOIN messages m ON latest_msg.last_msg_id = m.id
            ORDER BY m.created_at DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user_id, $current_user_id, $current_user_id]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($conversations);
        exit;
    }

    // =========================================================================
    // 2. CHARGER LES MESSAGES D'UNE DISCUSSION (GET)
    // =========================================================================
    if ($action === 'get') {
        $contact_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
        
        if ($contact_id > 0) {
            // Marquer les messages reçus de ce contact comme "lus"
            $update = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
            $update->execute([$contact_id, $current_user_id]);
            
            // Récupérer tout l'historique
            $stmt = $db->prepare("
                SELECT * FROM messages 
                WHERE (sender_id = ? AND receiver_id = ?) 
                   OR (sender_id = ? AND receiver_id = ?)
                ORDER BY created_at ASC
            ");
            $stmt->execute([$current_user_id, $contact_id, $contact_id, $current_user_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($messages);
        } else {
            echo json_encode([]);
        }
        exit;
    }

    // =========================================================================
    // 3. ENVOYER UN MESSAGE (SEND)
    // =========================================================================
    if ($action === 'send') {
        $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        
        if ($receiver_id > 0 && !empty($message)) {
            
            // --- MAN GO SHIELD (Filtre basique backend de sécurité) ---
            $isLocation = (strpos($message, '📍 Ma position : https://www.google.com/maps') !== false);
            if (!$isLocation) {
                $message = preg_replace('/(\+?\d{1,4}[ -]?)?\(?\d{2,3}\)?[ -]?\d{3}[ -]?\d{4}/', '[NUMÉRO MASQUÉ]', $message);
                $message = preg_replace('/(https?:\/\/[^\s]+)/', '[LIEN EXTERNE BLOQUÉ]', $message);
            }
            $badWords = ['con', 'connard', 'salope', 'merde', 'putain'];
            foreach ($badWords as $word) {
                $message = str_ireplace($word, str_repeat('*', strlen($word)), $message);
            }
            $message = strip_tags($message);
            // ------------------------------------------------------------
            
            $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            if ($stmt->execute([$current_user_id, $receiver_id, $message])) {
                echo json_encode(['status' => 'success', 'message_id' => $db->lastInsertId()]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Erreur base de données.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Données invalides.']);
        }
        exit;
    }

    // =========================================================================
    // 4. SUPPRIMER UN MESSAGE (DELETE)
    // =========================================================================
    if ($action === 'delete') {
        $msg_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($msg_id > 0) {
            // Un utilisateur ne peut supprimer que SES propres messages
            $stmt = $db->prepare("UPDATE messages SET message = '🚫 Ce message a été supprimé.' WHERE id = ? AND sender_id = ?");
            $stmt->execute([$msg_id, $current_user_id]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }

} catch (Exception $e) {
    // Si la table n'existe pas, ou autre erreur SQL, on renvoie une erreur JSON propre
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur API: ' . $e->getMessage()]);
    exit;
}
?>