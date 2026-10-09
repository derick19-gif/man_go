<?php
// =========================================================================
// PAIEMENT ABONNEMENT (Split Payment) & PARRAINAGE DYNAMIQUE
// checkout_subscription.php
// =========================================================================

ini_set('display_errors', 1); error_reporting(E_ALL);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

Session::init();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// 1. Redirection si non connecté
$planChoisi = $_GET['plan'] ?? 'starter';
if (!Session::isAuthenticated()) { 
    header("Location: $baseUrl/login.php?redirect=checkout_subscription.php?plan=$planChoisi"); 
    exit(); 
}

$db = \App\Core\Database::connect();
$userId = (int)Session::get('user_id');
$successMsg = $errorMsg = '';

// 2. Récupération des paramètres dynamiques (Prix, Commissions, Taux)
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM system_settings");
$settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);

$prixStarter = isset($settings['price_starter']) ? (int)$settings['price_starter'] : 2500;
$prixPremium = isset($settings['price_premium']) ? (int)$settings['price_premium'] : 5000;
$txCommission = isset($settings['referral_commission']) ? (float)$settings['referral_commission'] : 10;
$taux_usd = isset($settings['rate_usd']) ? (float)$settings['rate_usd'] : 600;
$taux_eur = isset($settings['rate_eur']) ? (float)$settings['rate_eur'] : 655;

// Définition du plan
if ($planChoisi === 'starter') {
    $planName = "Starter Pro";
    $planPrice = $prixStarter;
    $planDbId = 3; 
} else {
    $planName = "Premium VIP";
    $planPrice = $prixPremium;
    $planDbId = 2; 
}
$durationDays = 30;

// 3. Récupération des Soldes (Argent Réel + Crédits Virtuels)
$stmtUser = $db->prepare("SELECT balance, virtual_credits, is_premium, referred_by FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

$userBalance = (float)$userData['balance'];
$availableCredits = (int)$userData['virtual_credits'];

// Calcul du Split Payment
$maxCreditsUsable = min($availableCredits, $planPrice); 
$restToPay = $planPrice - $maxCreditsUsable;

// --- TRAITEMENT DU PAIEMENT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_subscription') {
    
    if ($userData['is_premium'] == 1) {
        $errorMsg = "Vous êtes déjà abonné à une offre Pro.";
    } elseif ($userBalance < $restToPay) {
        $errorMsg = "Solde insuffisant pour couvrir le reste à payer ($restToPay FCFA).";
    } else {
        try {
            $db->beginTransaction();
            
            // A. Déduire les Crédits Virtuels (si utilisés)
            if ($maxCreditsUsable > 0) {
                $db->prepare("UPDATE users SET virtual_credits = virtual_credits - ? WHERE id = ?")->execute([$maxCreditsUsable, $userId]);
                $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'credit', ?, 'completed', NOW())")->execute([$userId, -$maxCreditsUsable, "Paiement partiel Abonnement $planName (Crédits)"]);
            }
            
            // B. Déduire l'Argent Réel (si besoin)
            if ($restToPay > 0) {
                $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$restToPay, $userId]);
                $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'subscription_payment', ?, 'completed', NOW())")->execute([$userId, -$restToPay, "Paiement Abonnement $planName (Solde)"]);
            }

            // C. Mettre à jour le statut du compte
            $db->prepare("UPDATE users SET is_premium = 1, role_id = 4 WHERE id = ?")->execute([$userId]);
            
            // D. GESTION DU PARRAINAGE
            if (!empty($userData['referred_by']) && $txCommission > 0) {
                $referrerId = $userData['referred_by'];
                // La commission est calculée sur le prix total de l'abonnement
                $commissionAmount = $planPrice * ($txCommission / 100);

                $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$commissionAmount, $referrerId]);
                $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'referral_bonus', ?, 'completed', NOW())")->execute([$referrerId, $commissionAmount, "Commission Affiliation ($txCommission%) - $planName"]);

                try {
                    $db->prepare("INSERT INTO referral_commissions (referrer_id, referred_user_id, commission_amount, status, created_at) VALUES (?, ?, ?, 'approved', NOW())")->execute([$referrerId, $userId, $commissionAmount]);
                } catch (Exception $e) {}
            }

            $db->commit();
            $successMsg = "Félicitations ! Votre compte est maintenant passé au niveau $planName.";
            
            // Mise à jour de l'affichage
            $userBalance -= $restToPay;
            $availableCredits -= $maxCreditsUsable;
            $userData['is_premium'] = 1;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errorMsg = "Une erreur est survenue : " . $e->getMessage();
        }
    }
}

$pageTitle = "Validation Abonnement - MAN GO";
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-950 min-h-screen pt-20 pb-24 font-sans text-slate-100">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        
        <div class="text-center mb-10">
            <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight mb-3">Validation de l'abonnement</h1>
            <p class="text-slate-400 font-medium">Vous êtes sur le point de passer au niveau <strong class="<?= $planChoisi === 'premium' ? 'text-amber-500' : 'text-blue-500' ?>"><?= $planName ?></strong>.</p>
        </div>

        <?php if ($successMsg): ?>
            <div class="bg-slate-900 border border-emerald-500/40 rounded-3xl p-8 sm:p-12 text-center shadow-2xl">
                <div class="w-20 h-20 bg-emerald-500/10 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl"><i class="fa-solid fa-circle-check"></i></div>
                <h2 class="text-3xl font-black text-white mb-2">Félicitations !</h2>
                <p class="text-emerald-400 font-bold mb-6"><?= $successMsg ?></p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="<?= $baseUrl ?>/my_wallet.php" class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-black px-6 py-3 rounded-xl transition text-sm">Mon Portefeuille</a>
                    <a href="<?= $baseUrl ?>/" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-6 py-3 rounded-xl transition text-sm">Retour à l'accueil</a>
                </div>
            </div>
        <?php else: ?>

            <?php if ($errorMsg): ?>
                <div class="bg-rose-500/10 text-rose-400 px-6 py-4 rounded-xl mb-8 font-black text-sm flex items-center border border-rose-500/30">
                    <i class="fa-solid fa-triangle-exclamation mr-3 text-lg"></i> <?= $errorMsg ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                
                <!-- COLONNE 1 : Résumé & Split Payment -->
                <div class="bg-slate-900 rounded-3xl p-8 border border-slate-800 relative overflow-hidden shadow-xl">
                    <h3 class="text-xl font-black mb-6 flex items-center gap-2 text-white">
                        <i class="fa-solid fa-file-invoice text-slate-400"></i> Résumé de la commande
                    </h3>

                    <div class="space-y-4 mb-8">
                        <div class="flex justify-between items-center pb-4 border-b border-slate-800">
                            <span class="text-slate-400">Forfait <strong class="<?= $planChoisi === 'premium' ? 'text-amber-500' : 'text-blue-500' ?>"><?= $planName ?></strong></span>
                            <span class="font-bold text-lg text-white"><?= number_format($planPrice, 0, ',', ' ') ?> FCFA</span>
                        </div>

                        <?php if ($availableCredits > 0): ?>
                        <div class="flex justify-between items-center pb-4 border-b border-slate-800">
                            <div>
                                <span class="text-emerald-400 font-bold block"><i class="fa-solid fa-coins"></i> Crédits MAN GO Utilisés</span>
                                <span class="text-xs text-slate-500">Solde restant : <?= number_format($availableCredits - $maxCreditsUsable, 0, ',', ' ') ?></span>
                            </div>
                            <span class="font-bold text-lg text-emerald-400">- <?= number_format($maxCreditsUsable, 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <?php else: ?>
                        <div class="bg-slate-950 rounded-xl p-4 border border-slate-800">
                            <p class="text-sm text-slate-400 mb-2">Gagnez des crédits virtuels pour réduire votre facture.</p>
                            <a href="<?= $baseUrl ?>/watch_ads.php" class="text-amber-400 text-xs font-bold hover:underline"><i class="fa-solid fa-play"></i> Regarder une vidéo</a>
                        </div>
                        <?php endif; ?>

                        <!-- Total Final avec Convertisseur -->
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-slate-300 font-bold uppercase tracking-wider text-sm">Reste à payer</span>
                            <div class="text-right flex flex-col items-end">
                                <div class="flex items-center gap-2">
                                    <span id="sub-display-amount" class="font-black text-3xl text-white"><?= number_format($restToPay, 0, ',', ' ') ?></span>
                                    <select id="sub-currency-selector" onchange="convertSubCurrency()" class="bg-slate-800 text-slate-300 text-xs font-bold rounded-lg cursor-pointer focus:ring-0 py-1 px-2 border-none outline-none">
                                        <option value="XOF">FCFA</option>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COLONNE 2 : MOYEN DE PAIEMENT (SOLDE) -->
                <div class="bg-slate-900 rounded-3xl p-8 border border-slate-800 shadow-xl text-white">
                    <h3 class="text-lg font-black text-white mb-6 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-emerald-400"></i> Paiement sécurisé
                    </h3>

                    <div class="bg-slate-800 p-5 rounded-2xl border <?= ($userBalance >= $restToPay) ? 'border-emerald-500/50' : 'border-rose-500/50' ?> mb-8">
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mb-1">Votre solde actuel MAN GO</p>
                        <p class="text-3xl font-black <?= ($userBalance >= $restToPay) ? 'text-emerald-400' : 'text-rose-400' ?>">
                            <?= number_format($userBalance, 0, ',', ' ') ?> <span class="text-sm">FCFA</span>
                        </p>
                        <?php if ($userBalance < $restToPay): ?>
                            <p class="text-xs text-rose-400 mt-2 font-medium flex items-center gap-1"><i class="fa-solid fa-circle-xmark"></i> Il vous manque <?= number_format($restToPay - $userBalance, 0, ',', ' ') ?> FCFA.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($userData['is_premium'] == 1): ?>
                        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-xl text-center text-sm font-bold">
                            Vous possédez déjà un abonnement actif.
                        </div>
                    <?php elseif ($userBalance >= $restToPay): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="pay_subscription">
                            <button type="submit" class="w-full <?= $planChoisi === 'starter' ? 'bg-blue-600 hover:bg-blue-500' : 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950' ?> font-black py-4 rounded-xl transition shadow-lg text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                                <i class="fa-solid fa-lock"></i> <?= ($restToPay == 0) ? 'Activer Gratuitement' : 'Confirmer et Payer' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= $baseUrl ?>/my_wallet.php" class="block w-full text-center bg-slate-800 hover:bg-slate-700 text-white font-black py-4 rounded-xl transition border border-slate-700 text-sm uppercase tracking-wider">
                            Recharger mon solde
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const subAmountFCFA = <?= $restToPay ?>;
    const subRateUSD = <?= $taux_usd ?>;
    const subRateEUR = <?= $taux_eur ?>;

    function convertSubCurrency() {
        const currency = document.getElementById('sub-currency-selector').value;
        const display = document.getElementById('sub-display-amount');

        let newAmount = subAmountFCFA;
        let decimals = 0;

        if (currency === 'USD') { newAmount = subAmountFCFA / subRateUSD; decimals = 2; } 
        else if (currency === 'EUR') { newAmount = subAmountFCFA / subRateEUR; decimals = 2; }

        display.innerText = new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(newAmount);
    }
</script>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>