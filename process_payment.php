<?php
// =========================================================================
// PROCESS PAYMENT - Hub Crypto USDT (Validation Visuelle & Anti-Bot)
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

$db = \App\Core\Database::connect();$userId = (int)Session::get('user_id');

// =========================================================================
// ADRESSE DE RÉCEPTION MAN GO (TRC20)
// =========================================================================
$mangoCryptoAddress = "TP2B2v1RJrcWC8AfTXv4RKrRU9mfcHZRBn"; 
$cryptoNetwork = "TRON (TRC20)";
// =========================================================================

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$planType = isset($_GET['plan']) ? trim($_GET['plan']) : '';$depositAmount = isset($_GET['deposit']) ? (float)$_GET['deposit'] : 0;

$type = 'order';
if (!empty($planType))$type = 'subscription';
if ($depositAmount > 0)$type = 'deposit';

$amountFCFA = 0;
$orderTitle = '';

// Récupération du taux USD
$stmtSettings =$db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'rate_usd'");
$taux_usd = (float)($stmtSettings->fetchColumn() ?: 600);

if ($type === 'subscription') {
    $planDbId = ($planType === 'starter') ? 3 : 2;
    $amountFCFA = ($planType === 'starter') ? 2500 : 5000;
    $orderTitle = "Abonnement " . (($planType === 'starter') ? "Starter Pro" : "Premium VIP");
} elseif ($type === 'deposit') {
    $amountFCFA =$depositAmount;
    $orderTitle = "Recharge Portefeuille MAN GO";
} else {
    if ($orderId === 0) die("Aucune commande spécifiée.");
    $stmt =$db->prepare("SELECT * FROM orders WHERE id = ? AND buyer_id = ? LIMIT 1");
    $stmt->execute([$orderId,$userId]);
    $order =$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) die("Commande introuvable.");
    
    $amountFCFA = (float)$order['amount'];$orderTitle = "Commande N° " . $order['order_number'];
}

$amountUSDT = number_format($amountFCFA / $taux_usd, 2, '.', '');
$errorMessage = '';$isPending = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'confirm_crypto') {
    $txid = trim($_POST['txid'] ?? '');
    
    // Vérification finale en PHP (Sécurité serveur)
    if (!preg_match('/^[a-fA-F0-9]{64}$/', $txid)) {$errorMessage = "Le format du TXID est invalide. Il doit faire 64 caractères hexadécimaux.";
    } else {
        try {
            $db->beginTransaction();$paymentMethodLabel = '';
            if ($type === 'subscription') {$paymentMethodLabel = 'crypto_subscription_pending';
                $transactionRef = "Abonnement (TXID: $txid)";
            } elseif ($type === 'deposit') {
                $paymentMethodLabel = 'crypto_deposit';$transactionRef = "Dépôt USDT (TXID: $txid)";
            } else {
                $paymentMethodLabel = 'crypto_order_pending';$transactionRef = "Commande #$orderId (TXID:$txid)";
            }

            $stmtTx =$db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmtTx->execute([$userId,$amountFCFA, $paymentMethodLabel,$transactionRef]);
            
            $db->commit();$isPending = true;

        } catch (Exception $e) {
            $db->rollBack();$errorMessage = "Erreur d'enregistrement : " . $e->getMessage();
        }
    }
}

$pageTitle = "Paiement Crypto - MAN GO";
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-950 min-h-screen pt-16 pb-24 font-sans text-slate-100 px-4 relative z-0">
    <div class="max-w-6xl mx-auto">
        
        <?php if ($isPending): ?>
            <!-- ÉCRAN D'ATTENTE -->
            <div class="max-w-2xl mx-auto bg-slate-900 border border-slate-800 rounded-3xl p-8 sm:p-12 text-center shadow-2xl mt-10">
                <div class="w-24 h-24 bg-amber-500/10 border border-amber-500/30 rounded-full flex items-center justify-center mx-auto mb-6 text-amber-500 text-4xl animate-pulse">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <h1 class="text-3xl font-black text-white mb-4">Transfert en cours de vérification</h1>
                <p class="text-slate-400 font-medium leading-relaxed mb-8">
                    Nous avons bien reçu votre numéro de transaction (TXID). Notre équipe financière est en train de vérifier la réception des fonds sur le réseau <?= $cryptoNetwork ?>.
                </p>
                <div class="bg-slate-950 p-6 rounded-2xl border border-slate-800 mb-8 inline-block text-left w-full sm:w-auto">
                    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-1">Montant attendu</p>
                    <p class="text-2xl font-black text-emerald-400 mb-3"><?= $amountUSDT ?> USDT <span class="text-sm text-slate-500">(<?= number_format($amountFCFA, 0, ',', ' ') ?> FCFA)</span></p>
                    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-1">Statut</p>
                    <p class="text-amber-500 font-bold flex items-center gap-2"><i class="fa-solid fa-spinner fa-spin"></i> En attente de validation Admin</p>
                </div>
                <p class="text-xs text-slate-500 italic mb-8">Votre compte sera mis à jour automatiquement dès validation (sous 5 à 15 minutes).</p>
                
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="<?= $baseUrl ?>/my_wallet.php" class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-black px-6 py-3 rounded-xl transition text-sm">Mon portefeuille</a>
                    <a href="<?= $baseUrl ?>/" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-6 py-3 rounded-xl transition text-sm">Retour à l'accueil</a>
                </div>
            </div>
        <?php else: ?>
            <!-- INTERFACE DE PAIEMENT -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
                
                <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-[0_20px_50px_rgba(0,0,0,0.5)] relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-amber-400 to-orange-500"></div>

                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-amber-500/10 border border-amber-500/30 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-400 text-2xl">
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

                    <div class="mb-8">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Adresse de dépôt (<?= $cryptoNetwork ?>)</label>
                        <div class="flex items-center gap-3">
                            <input type="text" id="walletAddr" readonly value="<?= htmlspecialchars($mangoCryptoAddress) ?>" class="w-full bg-slate-950 border border-slate-800 text-amber-500 px-5 py-4 rounded-xl text-xs sm:text-sm font-mono font-black select-all focus:outline-none shadow-inner">
                            <button type="button" onclick="copyAddress(this)" class="bg-amber-500 hover:bg-amber-400 text-slate-950 w-14 h-14 rounded-xl font-black transition flex items-center justify-center shrink-0 shadow-lg" title="Copier l'adresse">
                                <i class="fa-solid fa-copy text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <!-- FORMULAIRE AVEC VALIDATION VISUELLE ET ANTI-BOT -->
                    <form method="POST" id="txidForm" onsubmit="return validateForm(event)" class="space-y-6 border-t border-slate-800 pt-8">
                        <input type="hidden" name="action" value="confirm_crypto">
                        
                        <?php if (!empty($errorMessage)): ?>
                            <div class="text-xs text-rose-400 font-bold bg-rose-500/10 p-4 rounded-xl border border-rose-500/20 flex items-center gap-2">
                                <i class="fa-solid fa-circle-exclamation text-lg"></i> <?= htmlspecialchars($errorMessage) ?>
                            </div>
                        <?php endif; ?>

                        <!-- CHAMP TXID AVEC ICONE DE VALIDATION -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-widest mb-3">Hash de transaction (TXID) *</label>
                            <div class="relative">
                                <input type="text" name="txid" id="txidInput" required placeholder="Collez les 64 caractères du Hash..." oninput="checkTXIDFormat()" class="w-full bg-slate-950 border-2 border-slate-800 text-white pl-5 pr-12 py-4 rounded-xl text-xs sm:text-sm font-mono focus:outline-none transition shadow-inner">
                                <div id="txidIcon" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-slate-600 text-lg transition-colors">
                                    <i class="fa-solid fa-circle-question"></i>
                                </div>
                            </div>
                            <p id="txidHint" class="text-[10px] text-slate-500 mt-2 font-medium">Un TXID Tron ou Binance valide fait 64 caractères hexadécimaux.</p>
                        </div>

                        <!-- CAPTCHA MATHÉMATIQUE (ANTI-BOT) -->
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center text-blue-400">
                                    <i class="fa-solid fa-robot"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold">Sécurité Anti-Robot</p>
                                    <p class="text-sm font-black text-white" id="captchaQuestion">Combien font ? + ?</p>
                                </div>
                            </div>
                            <input type="number" id="captchaAnswer" required placeholder="Réponse" oninput="checkCaptcha()" class="w-24 bg-slate-900 border-2 border-slate-700 text-white text-center font-black py-2 rounded-lg outline-none focus:border-blue-500 transition">
                        </div>

                        <button type="submit" id="submitBtn" disabled class="w-full bg-slate-800 text-slate-500 font-black py-5 rounded-xl transition transform text-sm uppercase tracking-wider flex items-center justify-center gap-2 cursor-not-allowed opacity-50">
                            <i class="fa-solid fa-lock"></i> Remplissez les champs
                        </button>
                    </form>
                </div>

                <!-- COLONNE DROITE (GUIDE) -->
                <div class="lg:col-span-5 space-y-8 pt-2">
                    <div class="bg-gradient-to-br from-slate-900 to-slate-800 border border-slate-700 rounded-3xl p-8 shadow-xl relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 opacity-5"><i class="fa-solid fa-money-bill-transfer text-9xl text-white"></i></div>
                        <div class="relative z-10">
                            <h3 class="font-black text-xl text-white mb-3">Où acheter vos USDT ?</h3>
                            <p class="text-sm text-slate-400 leading-relaxed mb-6">Achetez de la crypto facilement avec <strong>T-Money</strong> ou <strong>Flooz</strong> via nos plateformes partenaires.</p>
                            <div class="grid grid-cols-2 gap-4">
                                <a href="https://home.izichange.com/sign-up?ref=e8b1428b-982a-11f0-aaad-06c2f6bc7eb9" target="_blank" class="bg-white hover:bg-slate-50 p-4 rounded-2xl flex flex-col items-center justify-center gap-3 transition transform hover:-translate-y-1 shadow-lg group">
                                    <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center"><i class="fa-solid fa-bolt text-2xl text-emerald-500 group-hover:scale-110 transition-transform"></i></div>
                                    <span class="font-black text-slate-900 text-xs uppercase tracking-widest">Izichange</span>
                                </a>
                                <a href="https://perfectmoney.com/" target="_blank" class="bg-white hover:bg-slate-50 p-4 rounded-2xl flex flex-col items-center justify-center gap-3 transition transform hover:-translate-y-1 shadow-lg group">
                                    <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center"><i class="fa-solid fa-p text-2xl text-rose-500 font-serif group-hover:scale-110 transition-transform"></i></div>
                                    <span class="font-black text-slate-900 text-xs uppercase tracking-widest">P. Money</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Copie de l'adresse
function copyAddress(btn) {
    const input = document.getElementById('walletAddr');
    input.select(); input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
    
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check text-slate-950 text-xl"></i>';
    btn.classList.replace('bg-amber-500', 'bg-emerald-500');
    
    setTimeout(() => { 
        btn.innerHTML = originalHTML; 
        btn.classList.replace('bg-emerald-500', 'bg-amber-500');
    }, 2000);
}

// Variables pour la validation globale
let isTxidValid = false;
let isCaptchaValid = false;
let captchaExpected = 0;

// Génération du Captcha au chargement
window.onload = function() {
    const num1 = Math.floor(Math.random() * 10) + 1;
    const num2 = Math.floor(Math.random() * 10) + 1;
    captchaExpected = num1 + num2;
    document.getElementById('captchaQuestion').innerText = `Combien font ${num1} + ${num2} ?`;
};

// Vérification en temps réel du TXID (Format 64 caractères Hex)
function checkTXIDFormat() {
    const input = document.getElementById('txidInput');
    const icon = document.getElementById('txidIcon');
    const hint = document.getElementById('txidHint');
    const val = input.value.trim();
    
    const regex = /^[a-fA-F0-9]{64}$/;
    isTxidValid = regex.test(val);

    if (val.length === 0) {
        input.className = "w-full bg-slate-950 border-2 border-slate-800 text-white pl-5 pr-12 py-4 rounded-xl text-xs sm:text-sm font-mono focus:outline-none transition shadow-inner focus:border-amber-500";
        icon.innerHTML = '<i class="fa-solid fa-circle-question text-slate-600"></i>';
        hint.innerText = "Un TXID Tron ou Binance valide fait 64 caractères hexadécimaux.";
        hint.className = "text-[10px] text-slate-500 mt-2 font-medium";
    } else if (isTxidValid) {
        input.className = "w-full bg-slate-950 border-2 border-emerald-500 text-emerald-400 pl-5 pr-12 py-4 rounded-xl text-xs sm:text-sm font-mono focus:outline-none transition shadow-inner";
        icon.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-500"></i>';
        hint.innerText = "Format du Hash valide !";
        hint.className = "text-[10px] text-emerald-400 mt-2 font-bold";
    } else {
        input.className = "w-full bg-slate-950 border-2 border-rose-500 text-rose-400 pl-5 pr-12 py-4 rounded-xl text-xs sm:text-sm font-mono focus:outline-none transition shadow-inner";
        icon.innerHTML = '<i class="fa-solid fa-circle-xmark text-rose-500"></i>';
        hint.innerText = `Format incorrect. Actuellement : ${val.length}/64 caractères.`;
        hint.className = "text-[10px] text-rose-400 mt-2 font-bold";
    }
    updateSubmitButton();
}

// Vérification en temps réel du Captcha
function checkCaptcha() {
    const answer = parseInt(document.getElementById('captchaAnswer').value);
    const input = document.getElementById('captchaAnswer');
    isCaptchaValid = (answer === captchaExpected);
    
    if (isCaptchaValid) {
        input.classList.remove('border-slate-700', 'border-rose-500');
        input.classList.add('border-emerald-500', 'text-emerald-400');
    } else {
        input.classList.remove('border-emerald-500', 'text-emerald-400');
        if (input.value !== "") input.classList.add('border-rose-500');
    }
    updateSubmitButton();
}

// Mise à jour du bouton d'envoi
function updateSubmitButton() {
    const btn = document.getElementById('submitBtn');
    if (isTxidValid && isCaptchaValid) {
        btn.disabled = false;
        btn.className = "w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black py-5 rounded-xl shadow-[0_10px_20px_rgba(245,158,11,0.2)] transition transform hover:-translate-y-1 text-sm uppercase tracking-wider flex items-center justify-center gap-2";
        btn.innerHTML = '<i class="fa-solid fa-paper-plane text-lg"></i> Soumettre pour vérification';
    } else {
        btn.disabled = true;
        btn.className = "w-full bg-slate-800 text-slate-500 font-black py-5 rounded-xl transition transform text-sm uppercase tracking-wider flex items-center justify-center gap-2 cursor-not-allowed opacity-50";
        btn.innerHTML = '<i class="fa-solid fa-lock"></i> Remplissez les champs correctement';
    }
}

// Vérification finale au clic (sécurité supplémentaire côté client)
function validateForm(e) {
    if (!isTxidValid || !isCaptchaValid) {
        e.preventDefault();
        alert("Veuillez remplir correctement le TXID et la question de sécurité.");
        return false;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>