<?php
// =========================================================================
// Page de Paiement (Split Payment : Crédits + Argent Réel) & Commissions
// =========================================================================

if (!defined('APP_PATH')) define('APP_PATH', __DIR__);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/WalletManager.php'; // Inclusion du WalletManager
require_once __DIR__ . '/core/Settings.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// 1. Authentification requise
if (empty($_SESSION['user_id'])) {
    header("Location: $baseUrl/login.php?redirect=checkout.php?plan=" . urlencode($_GET['plan'] ?? 'premium'));
    exit();
}

$userId = $_SESSION['user_id'];
$db = \App\Core\Database::connect();
$success = false;
$error = '';

// 2. Détermination dynamique du Forfait
$selectedPlan = $_GET['plan'] ?? 'premium';

if ($selectedPlan === 'starter') {
    $planName = "Starter Pro";
    $planPrice = (int)\App\Core\Settings::get('starter_price', 2500); // LECTURE DYNAMIQUE
    $planDbId = 3; 
} else {
    $planName = "Premium VIP";
    $planPrice = (int)\App\Core\Settings::get('premium_price', 5000); // LECTURE DYNAMIQUE
    $planDbId = 2; 
}

$durationDays = 30;

// 3. Récupération des Crédits MAN GO de l'utilisateur
$wallets = \App\Core\WalletManager::getWallets($db, $userId);
$availableCredits = $wallets['credits_balance'];

// Calcul de la réduction (1 Crédit = 1 FCFA)
// On utilise soit le maximum possible pour que ça tombe à 0, soit tout son solde
$maxCreditsUsable = min($availableCredits, $planPrice); 
$restToPay = $planPrice - $maxCreditsUsable;

// 4. Traitement du paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = trim($_POST['payment_method'] ?? 'Mixx by Yas');
    $phoneOrCard   = trim($_POST['payment_account'] ?? '');
    
    // Le serveur recalcule pour éviter la triche (on ne fait pas confiance au HTML)
    $creditsToDeduct = min($availableCredits, $planPrice);
    $amountToPayReal = $planPrice - $creditsToDeduct;

    // Si on a un reste à payer, le numéro de téléphone est obligatoire
    if ($amountToPayReal > 0 && empty($phoneOrCard)) {
        $error = "Veuillez renseigner votre numéro de compte ou téléphone de paiement.";
    } else {
        try {
            $db->beginTransaction();

            // A. Déduire les crédits MAN GO utilisés
            if ($creditsToDeduct > 0) {
                \App\Core\WalletManager::deductFunds($db, $userId, $creditsToDeduct, 'credit', 'subscription_payment', "Achat abonnement $planName (Paiement Fractionné)");
            }

            // B. Enregistrement de la transaction (Argent Réel)
            $txRef = 'TX-' . strtoupper(bin2hex(random_bytes(6)));
            $stmtTx = $db->prepare("
                INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at)
                VALUES (?, ?, ?, ?, 'completed', NOW())
            ");
            // On enregistre uniquement l'argent réel payé
            $stmtTx->execute([$userId, $amountToPayReal, $paymentMethod, $txRef]);

            // C. Activation de l'abonnement
            $stmtSub = $db->prepare("
                INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status)
                VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active')
            ");
            $stmtSub->execute([$userId, $planDbId, $durationDays]);

            // D. Calcul et attribution de la commission au parrain (Toujours calculée sur le prix de base, pas sur le reste à payer !)
            $stmtUser = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

            $commissionRate = (int)\App\Core\Settings::get('referral_commission_percent', 20); // LECTURE DYNAMIQUE

            if (!empty($currentUser['referred_by']) && $commissionRate > 0) {
                $referrerId = (int)$currentUser['referred_by'];
                // La commission s'adapte au prix de base (ex: 20% de 5000 = 1000 FCFA)
                $commissionAmount = ($planPrice * $commissionRate) / 100;

                // On ajoute l'argent réel (commission) dans le Wallet du parrain !
                \App\Core\WalletManager::addFunds($db, $referrerId, $commissionAmount, 'commission', 'referral_bonus', "Commission (20%) sur l'abonnement $planName de l'utilisateur ID: $userId");

                // On garde la trace dans la table historique
                $stmtComm = $db->prepare("
                    INSERT INTO referral_commissions 
                    (referrer_id, referred_id, plan_name, order_amount, commission_amount, commission_percent, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())
                ");
                $stmtComm->execute([
                    $referrerId, $userId, $planName, $planPrice, $commissionAmount, $commissionRate
                ]);
            }

            $db->commit();
            $success = true;

        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erreur lors de la validation : " . $e->getMessage();
        }
    }
}

$pageTitle = "Paiement Sécurisé - MAN GO";
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-950 min-h-screen py-16 text-slate-100">
    <main class="max-w-4xl mx-auto px-4 sm:px-6">
        
        <?php if ($success): ?>
            <!-- Écran de Confirmation Succès -->
            <div class="bg-slate-900 border border-emerald-500/40 rounded-3xl p-8 sm:p-12 text-center shadow-2xl">
                <div class="w-20 h-20 bg-emerald-500/10 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h1 class="text-3xl font-black text-white mb-2">Félicitations !</h1>
                <p class="text-slate-300 text-sm sm:text-base max-w-md mx-auto mb-6">
                    Votre abonnement <strong class="text-amber-400"><?= htmlspecialchars($planName) ?></strong> est actif pour 30 jours.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="<?= $baseUrl ?>/vendor_dir/dashboard.php" class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-black px-6 py-3 rounded-xl transition text-sm">
                        Aller à mon Espace Pro
                    </a>
                    <a href="<?= $baseUrl ?>/" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-6 py-3 rounded-xl transition text-sm">
                        Retour à l'accueil
                    </a>
                </div>
            </div>

        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                
                <!-- COLONNE 1 : Le Formulaire -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl">
                    <div class="border-b border-slate-800 pb-6 mb-6">
                        <span class="text-amber-500 text-xs font-black uppercase tracking-wider">Finalisation</span>
                        <h1 class="text-2xl font-black text-white mt-1">Passer à <?= htmlspecialchars($planName) ?></h1>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs p-4 rounded-xl mb-6 flex items-center space-x-3">
                            <i class="fa-solid fa-triangle-exclamation text-base"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="?plan=<?= htmlspecialchars($selectedPlan) ?>" class="space-y-6">
                        
                        <?php if ($restToPay > 0): ?>
                            <!-- Choix du moyen de paiement (Seulement s'il reste à payer) -->
                            <div>
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-3">Moyen de paiement</label>
                                <div class="grid grid-cols-1 gap-3">
                                    <label class="flex items-center justify-between p-4 bg-slate-950 border border-slate-800 rounded-xl cursor-pointer hover:border-amber-500/40 transition">
                                        <div class="flex items-center space-x-3">
                                            <input type="radio" name="payment_method" value="Mixx by Yas" checked class="text-amber-500 focus:ring-0">
                                            <span class="text-sm font-bold text-white">Mixx by Yas</span>
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between p-4 bg-slate-950 border border-slate-800 rounded-xl cursor-pointer hover:border-amber-500/40 transition">
                                        <div class="flex items-center space-x-3">
                                            <input type="radio" name="payment_method" value="Flooz" class="text-amber-500 focus:ring-0">
                                            <span class="text-sm font-bold text-white">Flooz</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Coordonnées -->
                            <div>
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Numéro de téléphone / Compte</label>
                                <input type="text" name="payment_account" required placeholder="Ex: 90 00 00 00" 
                                       class="w-full bg-slate-950 border border-slate-800 text-white font-mono px-4 py-3 rounded-xl focus:outline-none focus:border-amber-500 transition">
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="w-full <?= $selectedPlan === 'starter' ? 'bg-blue-600 hover:bg-blue-500 text-white' : 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950' ?> font-black py-4 rounded-xl shadow-lg transition-all text-sm uppercase tracking-wider">
                            <?= ($restToPay == 0) ? 'Activer Gratuitement' : 'Payer ' . number_format($restToPay, 0, ',', ' ') . ' FCFA' ?>
                        </button>
                    </form>
                </div>

                <!-- COLONNE 2 : Résumé & Split Payment -->
                <div class="bg-slate-900 rounded-3xl p-8 border border-slate-800 relative overflow-hidden">
                    <h3 class="text-xl font-black mb-6 flex items-center gap-2 text-white">
                        <i class="fa-solid fa-file-invoice text-slate-400"></i> Résumé de la commande
                    </h3>

                    <div class="space-y-4 mb-8">
                        <!-- Prix de base -->
                        <div class="flex justify-between items-center pb-4 border-b border-slate-800">
                            <span class="text-slate-400">Abonnement <?= htmlspecialchars($planName) ?></span>
                            <span class="font-bold text-lg text-white"><?= number_format($planPrice, 0, ',', ' ') ?> FCFA</span>
                        </div>

                        <!-- Réduction (Crédits MAN GO) -->
                        <?php if ($availableCredits > 0): ?>
                        <div class="flex justify-between items-center pb-4 border-b border-slate-800">
                            <div>
                                <span class="text-emerald-400 font-bold block"><i class="fa-solid fa-coins"></i> Crédits MAN GO</span>
                                <span class="text-xs text-slate-500">Solde dispo : <?= number_format($availableCredits, 0, ',', ' ') ?></span>
                            </div>
                            <span class="font-bold text-lg text-emerald-400">- <?= number_format($maxCreditsUsable, 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <?php else: ?>
                        <div class="bg-slate-950 rounded-xl p-4 border border-slate-800">
                            <p class="text-sm text-slate-400 mb-2">Gagnez des crédits pour réduire votre facture.</p>
                            <a href="<?= $baseUrl ?>/watch_ads.php" class="text-amber-400 text-xs font-bold hover:underline"><i class="fa-solid fa-play"></i> Regarder une vidéo</a>
                        </div>
                        <?php endif; ?>

                        <!-- Total Final -->
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-slate-300 font-bold uppercase tracking-wider text-sm">Reste à payer</span>
                            <span class="font-black text-3xl text-white"><?= number_format($restToPay, 0, ',', ' ') ?> <span class="text-xl <?= $selectedPlan === 'starter' ? 'text-blue-500' : 'text-amber-500' ?>">FCFA</span></span>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </main>
</div>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>