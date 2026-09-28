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

    // Mise à jour de l'heure de présence
    $db->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?")->execute([$current_user_id]);

    // =========================================================================
    // STATUS / INBOX / GET (Inchangés)
    // =========================================================================
    if ($action === 'status') {
        $contact_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $stmt = $db->prepare("SELECT last_seen FROM users WHERE id = ?");
        $stmt->execute([$contact_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $isOnline = false;
        if ($user && $user['last_seen']) {
            $last_seen_time = strtotime($user['last_seen']);
            if ((time() - $last_seen_time) < 120) { $isOnline = true; }
        }
        echo json_encode(['online' => $isOnline]);
        exit;
    }
    
    if ($action === 'inbox') {
        $query = "
            SELECT 
                u.id as contact_id, u.firstname, u.lastname, 
                m.message, m.created_at, m.is_read, m.receiver_id
            FROM users u
            JOIN (
                SELECT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_user_id,
                       MAX(id) as last_msg_id
                FROM messages WHERE sender_id = ? OR receiver_id = ? GROUP BY other_user_id
            ) as latest_msg ON u.id = latest_msg.other_user_id
            JOIN messages m ON latest_msg.last_msg_id = m.id
            ORDER BY m.created_at DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user_id, $current_user_id, $current_user_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'get') {
        $contact_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
        if ($contact_id > 0) {
            $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$contact_id, $current_user_id]);
            
            $stmt = $db->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
            $stmt->execute([$current_user_id, $contact_id, $contact_id, $current_user_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            echo json_encode([]);
        }
        exit;
    }

    // =========================================================================
    // 3. ENVOYER UN MESSAGE & DÉCLENCHER L'IA (NOUVEAU)
    // =========================================================================
    if ($action === 'send') {
        $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        
        if ($receiver_id > 0 && !empty($message)) {
            
            // --- MAN GO SHIELD (Filtre basique backend de sécurité) ---
            $isLocation = (strpos($message, '📍 Ma position :') !== false);
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
            
            // 1. Sauvegarder le message de l'acheteur
            $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            if ($stmt->execute([$current_user_id, $receiver_id, $message])) {
                $lastMsgId = $db->lastInsertId();

                // 2. LOGIQUE D'ABSENCE & INTELLIGENCE ARTIFICIELLE
                // On vérifie si le destinataire est un vendeur et on lit ses paramètres
                $stmtBiz = $db->prepare("
                    SELECT b.*, u.firstname, s.plan_id 
                    FROM business_settings b
                    JOIN users u ON b.user_id = u.id
                    LEFT JOIN user_subscriptions s ON s.user_id = u.id AND s.status = 'active' AND s.end_date > NOW()
                    WHERE b.user_id = ?
                ");
                $stmtBiz->execute([$receiver_id]);
                $biz = $stmtBiz->fetch(PDO::FETCH_ASSOC);

                if ($biz) {
                    $current_time = date('H:i:s');
                    $is_closed = ($current_time < $biz['open_time'] || $current_time > $biz['close_time']);
                    
                    // Si le vendeur est absent (horaires fermés OU bouton forcé actif)
                    if ($is_closed || $biz['is_away'] == 1) {
                        
                        $ai_reply_text = '';

                        // A. SI VIP ET IA ACTIVÉE -> APPEL RÉEL À GOOGLE GEMINI
                        if ($biz['ai_assistant_enabled'] == 1 && $biz['plan_id'] == 2) {
                            
                            // IL FAUDRA METTRE VOTRE CLÉ ICI PLUS TARD (Créable sur Google AI Studio)
                            $gemini_api_key = "VOTRE_CLE_API_GEMINI"; 
                            
                            if ($gemini_api_key !== "VOTRE_CLE_API_GEMINI") {
                                $shopDesc = !empty($biz['seo_description']) ? $biz['seo_description'] : "Boutique professionnelle de confiance.";
                                $prompt = "Tu es l'assistant IA de la boutique '{$biz['firstname']}' sur la marketplace MAN GO. Description de la boutique : $shopDesc. Le vendeur est actuellement absent. Le client vient d'écrire : '$message'. Réponds au client poliment, de façon concise (maximum 3 phrases), informe-le que le vendeur prendra le relais, et tente de répondre à sa question si cela concerne la description de la boutique. Ne propose pas d'appeler un numéro.";
                                
                                $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $gemini_api_key;
                                $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
                                
                                $ch = curl_init($url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                                curl_setopt($ch, CURLOPT_POST, true);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                                $response = curl_exec($ch);
                                curl_close($ch);
                                
                                $result = json_decode($response, true);
                                if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                                    $ai_reply_text = "🤖 IA : " . trim($result['candidates'][0]['content']['parts'][0]['text']);
                                }
                            }
                            
                            // Fallback si Gemini met du temps ou si la clé n'est pas configurée
                            if (empty($ai_reply_text)) {
                                $ai_reply_text = "🤖 IA : Bonjour ! Le gérant est momentanément indisponible. J'ai bien noté votre message concernant votre besoin, il vous répondra dès son retour !";
                            }
                        } 
                        // B. SINON, SI LE MESSAGE D'ABSENCE STANDARD EST ACTIVÉ
                        elseif ($biz['auto_reply_enabled'] == 1 && !empty($biz['auto_reply_message'])) {
                            $ai_reply_text = "🤖 Réponse automatique : " . $biz['auto_reply_message'];
                        }

                        // 3. Sauvegarder la réponse automatique dans la base de données (is_ai = 1)
                        if (!empty($ai_reply_text)) {
                            // On attend 1 seconde pour simuler que l'IA réfléchit/écrit
                            sleep(1); 
                            $stmtAi = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_ai) VALUES (?, ?, ?, 1)");
                            $stmtAi->execute([$receiver_id, $current_user_id, $ai_reply_text]);
                        }
                    }
                }

                echo json_encode(['status' => 'success', 'message_id' => $lastMsgId]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Erreur base de données.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Données invalides.']);
        }
        exit;
    }

    if ($action === 'delete') {
        $msg_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($msg_id > 0) {
            $stmt = $db->prepare("UPDATE messages SET message = '🚫 Ce message a été supprimé.' WHERE id = ? AND sender_id = ?");
            $stmt->execute([$msg_id, $current_user_id]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur API: ' . $e->getMessage()]);
    exit;
}
?>