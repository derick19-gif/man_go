<?php
// =========================================================================
// PROCESS PAYMENT - Hub Crypto USDT (Avec affiliation Izichange/Perfect Money)
// =========================================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

Session::init();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

if (!Session::isAuthenticated()) {
    header("Location: $baseUrl/login.php");
    exit();
}

$db = \App\Core\Database::connect();
$userId = (int)Session::get('user_id');

// =========================================================================
// ADRESSE DE RÉCEPTION MAN GO (TRC20)
// =========================================================================
$mangoCryptoAddress = "TP2B2v1RJrcWC8AfTXv4RKrRU9mfcHZRBn"; 
$cryptoNetwork = "TRON (TRC20)";
// =========================================================================

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$planType = isset($_GET['plan']) ? trim($_GET['plan']) : '';
$depositAmount = isset($_GET['deposit']) ? (float)$_GET['deposit'] : 0;

$type = 'order';
if (!empty($planType)) $type = 'subscription';
if ($depositAmount > 0) $type = 'deposit';

$amountFCFA = 0;
$orderTitle = '';

if ($type === 'subscription') {
    $planDbId = ($planType === 'starter') ? 3 : 2;
    $amountFCFA = ($planType === 'starter') ? 2500 : 5000;
    $orderTitle = "Abonnement " . (($planType === 'starter') ? "Starter Pro" : "Premium VIP");
} elseif ($type === 'deposit') {
    $amountFCFA = $depositAmount;
    $orderTitle = "Recharge Portefeuille MAN GO";
} else {
    if ($orderId === 0) die("Aucune commande spécifiée.");
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND buyer_id = ? LIMIT 1");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) die("Commande introuvable.");
    
    $amountFCFA = (float)$order['amount'];
    $orderTitle = "Commande N° " . $order['order_number'];
}

// Calcul du montant en USDT
$taux_usd = 600; 
$amountUSDT = number_format($amountFCFA / $taux_usd, 2, '.', '');

$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_crypto') {
    $txid = trim($_POST['txid'] ?? '');

    if (empty($txid) || strlen($txid) < 10) {
        $errorMessage = "Veuillez fournir un hash de transaction (TXID) valide.";
    } else {
        try {
            $db->beginTransaction();

            if ($type === 'subscription') {
                $stmtSub = $db->prepare("INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active')");
                $stmtSub->execute([$userId, $planDbId]);

                $stmtTx = $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'crypto_usdt', ?, 'completed', NOW())");
                $stmtTx->execute([$userId, $amountFCFA, $txid]);
                $db->commit();
                header("Location: $baseUrl/vendor_dir/dashboard.php?msg=subscribed");
                exit();
            } elseif ($type === 'deposit') {
                $stmtWallet = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmtWallet->execute([$amountFCFA, $userId]);

                $stmtTx = $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'crypto_deposit', ?, 'completed', NOW())");
                $stmtTx->execute([$userId, $amountFCFA, $txid]);
                $db->commit();
                header("Location: $baseUrl/my_wallet.php?msg=deposited");
                exit();
            } else {
                $stmtUpdate = $db->prepare("UPDATE orders SET status = 'paid', updated_at = NOW() WHERE id = ?");
                $stmtUpdate->execute([$order['id']]);

                $stmtTx = $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'crypto_usdt', ?, 'completed', NOW())");
                $stmtTx->execute([$userId, $amountFCFA, $txid]);
                $db->commit();
                header("Location: $baseUrl/purchases.php?msg=paid");
                exit();
            }
        } catch (Exception $e) {
            $db->rollBack();
            $errorMessage = "Erreur de validation : " . $e->getMessage();
        }
    }
}

$pageTitle = "Paiement Crypto - MAN GO";
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-950 min-h-screen pt-16 pb-24 font-sans text-slate-100 px-4 relative z-0">
    <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
        
        <!-- COLONNE GAUCHE (7/12) : CARTE DE PAIEMENT & QR CODE -->
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-[0_20px_50px_rgba(0,0,0,0.5)] relative overflow-hidden group">
            <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-amber-400 to-orange-500"></div>

            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-amber-500/10 border border-amber-500/30 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-400 text-2xl group-hover:scale-110 transition duration-500">
                    <i class="fa-brands fa-bitcoin"></i>
                </div>
                <h1 class="text-3xl font-black text-white mb-2"><?= htmlspecialchars($orderTitle) ?></h1>
                <p class="text-[11px] text-emerald-400 uppercase tracking-widest font-black flex items-center justify-center gap-2">
                    <i class="fa-solid fa-shield-check"></i> Règlement Sécurisé en USDT
                </p>
            </div>

            <div class="bg-slate-950 rounded-2xl p-6 border border-slate-800 text-center mb-8 shadow-inner relative overflow-hidden">
                <div class="absolute inset-0 bg-amber-500/5 mix-blend-overlay"></div>
                <span class="relative block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Montant exact à envoyer</span>
                <div class="relative flex items-baseline justify-center gap-3">
                    <span class="text-5xl font-black text-white tracking-tight animate-pulse"><?= $amountUSDT ?></span>
                    <span class="text-amber-500 font-black text-2xl">USDT</span>
                </div>
                <span class="relative block text-xs text-slate-500 mt-2 font-bold">≈ <?= number_format($amountFCFA, 0, ',', ' ') ?> FCFA (Réseau <?= $cryptoNetwork ?>)</span>
            </div>

            <div class="flex justify-center mb-8">
                <div class="bg-white p-4 rounded-3xl shadow-[0_0_30px_rgba(245,158,11,0.15)] inline-block transform hover:scale-105 transition duration-300">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode($mangoCryptoAddress) ?>" alt="QR Code MAN GO" class="w-40 h-40">
                </div>
            </div>

            <div class="mb-8">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Adresse de dépôt (<?= $cryptoNetwork ?>)</label>
                <div class="flex items-center gap-3">
                    <input type="text" id="walletAddr" readonly value="<?= htmlspecialchars($mangoCryptoAddress) ?>" class="w-full bg-slate-950 border border-slate-800 text-amber-500 px-5 py-4 rounded-xl text-xs sm:text-sm font-mono font-black select-all focus:outline-none shadow-inner">
                    <button type="button" onclick="copyAddress()" class="bg-amber-500 hover:bg-amber-400 text-slate-950 w-14 h-14 rounded-xl font-black transition flex items-center justify-center shrink-0 shadow-lg" title="Copier l'adresse">
                        <i class="fa-solid fa-copy text-xl"></i>
                    </button>
                </div>
            </div>

            <form method="POST" class="space-y-5 border-t border-slate-800 pt-8">
                <input type="hidden" name="action" value="confirm_crypto">
                
                <?php if (!empty($errorMessage)): ?>
                    <div class="text-xs text-rose-400 font-bold bg-rose-500/10 p-4 rounded-xl border border-rose-500/20 flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-lg"></i> <?= htmlspecialchars($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-widest mb-3">Hash de transaction (TXID) *</label>
                    <input type="text" name="txid" required placeholder="Collez l'ID de transaction après votre transfert..." class="w-full bg-slate-950 border-2 border-slate-800 text-white px-5 py-4 rounded-xl text-xs sm:text-sm font-mono focus:border-amber-500 focus:outline-none transition shadow-inner">
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black py-5 rounded-xl shadow-[0_10px_20px_rgba(245,158,11,0.2)] transition transform hover:-translate-y-1 text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check-double text-lg"></i> Confirmer mon transfert
                </button>
            </form>
        </div>

        <!-- COLONNE DROITE (5/12) : GUIDE & PARTENAIRES -->
        <div class="lg:col-span-5 space-y-8 pt-2">
            
            <!-- ENCART IZICHANGE / PERFECT MONEY ANIMÉ -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 border border-slate-700 rounded-3xl p-8 shadow-xl relative overflow-hidden">
                <div class="absolute -right-6 -top-6 opacity-5">
                    <i class="fa-solid fa-money-bill-transfer text-9xl text-white"></i>
                </div>
                <div class="relative z-10">
                    <h3 class="font-black text-xl text-white mb-3">Où acheter vos USDT ?</h3>
                    <p class="text-sm text-slate-400 leading-relaxed mb-6">
                        Achetez de la crypto facilement avec votre <strong>T-Money</strong> ou <strong>Flooz</strong> via nos plateformes locales partenaires. C'est rapide et 100% sécurisé.
                    </p>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <!-- LIEN DE PARRAINAGE IZICHANGE -->
                        <a href="https://home.izichange.com/sign-up?ref=e8b1428b-982a-11f0-aaad-06c2f6bc7eb9" target="_blank" class="bg-white hover:bg-slate-50 p-4 rounded-2xl flex flex-col items-center justify-center gap-3 transition transform hover:-translate-y-1 shadow-lg group">
                            <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center">
                                <i class="fa-solid fa-bolt text-2xl text-emerald-500 group-hover:scale-110 transition-transform"></i>
                            </div>
                            <span class="font-black text-slate-900 text-xs uppercase tracking-widest">Izichange</span>
                        </a>
                        
                        <!-- LIEN PERFECT MONEY -->
                        <a href="https://perfectmoney.com/" target="_blank" class="bg-white hover:bg-slate-50 p-4 rounded-2xl flex flex-col items-center justify-center gap-3 transition transform hover:-translate-y-1 shadow-lg group">
                            <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center">
                                <i class="fa-solid fa-p text-2xl text-rose-500 font-serif group-hover:scale-110 transition-transform"></i>
                            </div>
                            <span class="font-black text-slate-900 text-xs uppercase tracking-widest">Perfect Money</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- GUIDE D'UTILISATION -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-xl">
                <h3 class="font-black text-sm uppercase tracking-widest text-slate-300 mb-6 flex items-center gap-3 border-b border-slate-800 pb-4">
                    <i class="fa-solid fa-circle-question text-amber-500 text-lg"></i> Comment finaliser l'achat ?
                </h3>
                
                <ol class="space-y-6 text-sm text-slate-400 font-medium">
                    <li class="flex items-start gap-4 group">
                        <span class="w-8 h-8 rounded-full bg-slate-800 text-slate-300 font-black flex items-center justify-center shrink-0 border border-slate-700 group-hover:bg-amber-500 group-hover:text-slate-950 transition-colors">1</span>
                        <span class="mt-1">Ouvrez votre portefeuille crypto (Trust Wallet, Izichange, etc.) sur votre téléphone.</span>
                    </li>
                    <li class="flex items-start gap-4 group">
                        <span class="w-8 h-8 rounded-full bg-slate-800 text-slate-300 font-black flex items-center justify-center shrink-0 border border-slate-700 group-hover:bg-amber-500 group-hover:text-slate-950 transition-colors">2</span>
                        <span class="mt-1">Initiez un retrait/envoi de <strong>USDT</strong> et sélectionnez impérativement le réseau <strong><?= $cryptoNetwork ?></strong>.</span>
                    </li>
                    <li class="flex items-start gap-4 group">
                        <span class="w-8 h-8 rounded-full bg-slate-800 text-slate-300 font-black flex items-center justify-center shrink-0 border border-slate-700 group-hover:bg-amber-500 group-hover:text-slate-950 transition-colors">3</span>
                        <span class="mt-1">Scannez le QR Code ou copiez l'adresse de réception affichée à gauche.</span>
                    </li>
                    <li class="flex items-start gap-4">
                        <span class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-500 font-black flex items-center justify-center shrink-0 border border-amber-500/30 animate-pulse">4</span>
                        <span class="mt-1 text-slate-300">Une fois le transfert effectué, copiez le <strong>TXID</strong> (Hash) depuis votre application et collez-le ici pour valider automatiquement la commande.</span>
                    </li>
                </ol>
            </div>

        </div>
    </div>
</div>

<script>
function copyAddress() {
    const input = document.getElementById('walletAddr');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
    
    const btn = event.currentTarget;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check text-slate-950 text-xl"></i>';
    btn.classList.replace('bg-amber-500', 'bg-emerald-500');
    
    setTimeout(() => { 
        btn.innerHTML = originalHTML; 
        btn.classList.replace('bg-emerald-500', 'bg-amber-500');
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>