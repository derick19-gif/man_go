<?php
// =========================================================================
// Moteur AJAX : Réclamation Horaire (Le Robinet / Faucet)
// =========================================================================

if (!defined('APP_PATH')) define('APP_PATH', __DIR__);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/WalletManager.php';
require_once __DIR__ . '/core/Settings.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (empty($currentUserId)) {
    echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter.']);
    exit;
}

// Plus tard, nous ajouterons ici la validation du reCAPTCHA (POST)
// $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
// if(empty($recaptchaResponse)) die json_encode error...

try {
    $db = \App\Core\Database::connect();
    $rewardAmount = (int)\App\Core\Settings::get('faucet_reward_amount', 2); // Récompense (ajustable selon vos revenus publicitaires)
    
    $db->beginTransaction();
    
    // 1. Vérifier la dernière heure de réclamation
    $stmtCheck = $db->prepare("SELECT last_claim_time FROM hourly_faucet WHERE user_id = ? FOR UPDATE");
    $stmtCheck->execute([$currentUserId]);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $lastClaim = strtotime($row['last_claim_time']);
        $now = time();
        $diffSeconds = $now - $lastClaim;
        
        if ($diffSeconds < 3600) { // 3600 secondes = 1 Heure
            $remaining = 3600 - $diffSeconds;
            $db->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Trop tôt.', 
                'remaining_seconds' => $remaining
            ]);
            exit;
        }
        // Mise à jour de l'heure
        $db->prepare("UPDATE hourly_faucet SET last_claim_time = NOW() WHERE user_id = ?")->execute([$currentUserId]);
    } else {
        // Première fois
        $db->prepare("INSERT INTO hourly_faucet (user_id, last_claim_time) VALUES (?, NOW())")->execute([$currentUserId]);
    }
    
    // 2. PAIEMENT des Crédits
    \App\Core\WalletManager::addFunds($db, $currentUserId, $rewardAmount, 'credit', 'hourly_faucet', "Réclamation bonus horaire");
    
    $wallets = \App\Core\WalletManager::getWallets($db, $currentUserId);
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Succès ! Vous avez reçu +$rewardAmount Crédits.",
        'new_balance' => $wallets['credits_balance'],
        'reward' => $rewardAmount
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur système.']);
}