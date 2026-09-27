<?php
// =========================================================================
// Page de Paiement Sécurisé & Attribution de Commission - MAN GO
// =========================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';

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

// 2. Détermination dynamique du Forfait choisi via l'URL
$selectedPlan = $_GET['plan'] ?? 'premium';

if ($selectedPlan === 'starter') {
    $planName = "Starter Pro";
    $planPrice = 2500;
    $planDbId = 3; // ID du plan Starter dans la base de données
} else {
    $planName = "Premium VIP";
    $planPrice = 5000;
    $planDbId = 2; // ID du plan Premium dans la base de données
}

$durationDays = 30;

// 3. Traitement du paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = trim($_POST['payment_method'] ?? 'Mixx by Yas');
    $phoneOrCard   = trim($_POST['payment_account'] ?? '');

    if (empty($phoneOrCard)) {
        $error = "Veuillez renseigner votre numéro de compte ou téléphone de paiement.";
    } else {
        try {
            $db->beginTransaction();

            // A. Enregistrement de la transaction
            $txRef = 'TX-' . strtoupper(bin2hex(random_bytes(6)));
            $stmtTx = $db->prepare("
                INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at)
                VALUES (?, ?, ?, ?, 'completed', NOW())
            ");
            $stmtTx->execute([$userId, $planPrice, $paymentMethod, $txRef]);

            // B. Activation de l'abonnement pour 30 jours (avec le bon ID de forfait)
            $stmtSub = $db->prepare("
                INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status)
                VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active')
            ");
            $stmtSub->execute([$userId, $planDbId, $durationDays]);

            // C. Calcul et attribution de la commission au parrain
            $stmtUser = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

            $commissionRate = defined('REFERRAL_COMMISSION_PERCENT') ? (int)REFERRAL_COMMISSION_PERCENT : 20;

            if (!empty($currentUser['referred_by']) && $commissionRate > 0) {
                $referrerId = (int)$currentUser['referred_by'];
                // La commission s'adapte au prix (20% de 2500 = 500, 20% de 5000 = 1000)
                $commissionAmount = ($planPrice * $commissionRate) / 100;

                $stmtComm = $db->prepare("
                    INSERT INTO referral_commissions 
                    (referrer_id, referred_id, plan_name, order_amount, commission_amount, commission_percent, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())
                ");
                $stmtComm->execute([
                    $referrerId,
                    $userId,
                    $planName,
                    $planPrice,
                    $commissionAmount,
                    $commissionRate
                ]);
            }

            $db->commit();
            $success = true;

        } catch (Exception $e) {
            $db->rollBack();
            $error = "Une erreur est survenue lors de la validation du paiement : " . $e->getMessage();
        }
    }
}

$pageTitle = "Paiement Sécurisé - MAN GO";
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-950 min-h-screen py-16 text-slate-100">
    <main class="max-w-3xl mx-auto px-4 sm:px-6">
        
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
            <!-- Formulaire de Paiement -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl">
                <div class="border-b border-slate-800 pb-6 mb-6">
                    <span class="text-amber-500 text-xs font-black uppercase tracking-wider">Finalisation de commande</span>
                    <h1 class="text-2xl sm:text-3xl font-black text-white mt-1">Passer à <?= htmlspecialchars($planName) ?></h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs p-4 rounded-xl mb-6 flex items-center space-x-3">
                        <i class="fa-solid fa-triangle-exclamation text-base"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Récapitulatif Plan -->
                <div class="bg-slate-950/60 border <?= $selectedPlan === 'starter' ? 'border-blue-500/30' : 'border-amber-500/30' ?> rounded-2xl p-5 mb-8 flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-white"><?= htmlspecialchars($planName) ?></h3>
                        <p class="text-xs text-slate-400">Validité 30 jours</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-black <?= $selectedPlan === 'starter' ? 'text-blue-400' : 'text-amber-400' ?>">
                            <?= number_format($planPrice, 0, ',', ' ') ?> FCFA
                        </div>
                        <span class="text-[11px] text-emerald-400 font-bold uppercase tracking-wider">TVA incluse</span>
                    </div>
                </div>

                <form method="POST" action="?plan=<?= htmlspecialchars($selectedPlan) ?>" class="space-y-6">
                    <!-- Choix du moyen de paiement -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-3">Moyen de paiement</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
                            <label class="flex items-center justify-between p-4 bg-slate-950 border border-slate-800 rounded-xl cursor-pointer hover:border-amber-500/40 transition">
                                <div class="flex items-center space-x-3">
                                    <input type="radio" name="payment_method" value="Carte Bancaire" class="text-amber-500 focus:ring-0">
                                    <span class="text-sm font-bold text-white">Carte</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Coordonnées de paiement -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Numéro de téléphone / Compte</label>
                        <input type="text" name="payment_account" required placeholder="Ex: 90 00 00 00" 
                               class="w-full bg-slate-950 border border-slate-800 text-white font-mono px-4 py-3 rounded-xl focus:outline-none focus:border-amber-500 transition">
                    </div>

                    <button type="submit" class="w-full <?= $selectedPlan === 'starter' ? 'bg-blue-600 hover:bg-blue-500 text-white' : 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950' ?> font-black py-4 rounded-xl shadow-lg transition-all text-sm uppercase tracking-wider">
                        Payer <?= number_format($planPrice, 0, ',', ' ') ?> FCFA
                    </button>
                </form>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>