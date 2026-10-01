<?php
// =========================================================================
// Webhook (Postback) - Réception des validations de la régie publicitaire
// URL à donner à la régie : https://votre-site.com/man_go/webhook_ads.php
// =========================================================================

if (!defined('APP_PATH')) define('APP_PATH', __DIR__);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/WalletManager.php';

// 1. CLÉ DE SÉCURITÉ (Secret Key)
// À configurer dans votre panel publicitaire (Monetag/Adsterra) pour éviter que n'importe qui appelle cette URL
$secretKey = "MANGO_SECRET_2026_XYZ987"; 

// 2. Récupération des paramètres envoyés par la régie (souvent en GET)
// Exemple d'URL appelée par la régie : webhook_ads.php?user_id=45&reward=5&hash=...
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$rewardAmount = isset($_GET['reward']) ? intval($_GET['reward']) : 5;
$hash = $_GET['hash'] ?? ''; // Signature de sécurité

if ($userId <= 0) {
    http_response_code(400);
    die("ID utilisateur invalide.");
}

// 3. (Optionnel mais recommandé) Vérification de la signature cryptographique
// $expectedHash = md5($userId . $rewardAmount . $secretKey);
// if ($hash !== $expectedHash) {
//     http_response_code(403);
//     die("Signature de sécurité invalide.");
// }

$db = \App\Core\Database::connect();

try {
    // 4. On crédite RÉELLEMENT l'utilisateur de manière sécurisée
    \App\Core\WalletManager::addCredits($db, $userId, $rewardAmount, 'watch_ad_real', 'Visionnage vidéo partenaire');
    
    // On répond "OK" à la régie pour qu'elle sache que la transaction est validée
    http_response_code(200);
    echo "OK";

} catch (Exception $e) {
    http_response_code(500);
    echo "Erreur serveur : " . $e->getMessage();
}