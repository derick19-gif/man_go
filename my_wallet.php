<?php
// =========================================================================
// MON PORTEFEUILLE - MAN GO (Lisibilité Max, Infobulles, 100% Dynamique)
// =========================================================================
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

Session::init();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

if (!Session::isAuthenticated()) { header("Location: $baseUrl/login.php"); exit(); }

$db = \App\Core\Database::connect();
$userId = (int)Session::get('user_id');
$successMsg = $errorMsg = '';

// --- RÉCUPÉRATION DE TOUTES LES DONNÉES DYNAMIQUES (ZÉRO VALEUR EN DUR) ---
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('transaction_fee', 'rate_usd', 'rate_eur')");
$settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);

$txFeePercent = isset($settings['transaction_fee']) ? (float)$settings['transaction_fee'] : 2; // 2% par défaut
$taux_usd = isset($settings['rate_usd']) ? (float)$settings['rate_usd'] : 600; // 600 FCFA par défaut
$taux_eur = isset($settings['rate_eur']) ? (float)$settings['rate_eur'] : 655; // 655 FCFA par défaut

// --- TRAITEMENT DES FORMULAIRES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. DEMANDE DE RETRAIT
    if ($_POST['action'] === 'withdraw_request') {
        $amountRaw = preg_replace('/[^0-9.]/', '', $_POST['amount_xof'] ?? $_POST['amount'] ?? '0');
        $amount = (float)$amountRaw;
        $wallet_destination = trim($_POST['wallet_destination'] ?? '');

        if (!$amount || $amount < 2000) $errorMsg = "Le montant minimum de retrait est de 2 000 FCFA.";
        elseif (empty($wallet_destination)) $errorMsg = "Veuillez fournir une adresse de réception valide.";
        else {
            try {
                $db->beginTransaction();
                $stmtUser = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
                $stmtUser->execute([$userId]);
                if ($stmtUser->fetchColumn() < $amount) {
                    $errorMsg = "Solde insuffisant pour ce retrait."; $db->rollBack();
                } else {
                    $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $userId]);
                    $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'withdrawal_request', ?, 'pending', NOW())")->execute([$userId, -$amount, "To: " . $wallet_destination]);
                    $db->commit();
                    $successMsg = "Demande enregistrée. Les frais de réseau seront déduits lors de l'envoi.";
                }
            } catch (Exception $e) { $db->rollBack(); $errorMsg = "Erreur : " . $e->getMessage(); }
        }
    }

    // 2. TRANSFERT ENTRE UTILISATEURS
    elseif ($_POST['action'] === 'transfer_funds') {
        $amountRaw = preg_replace('/[^0-9.]/', '', $_POST['transfer_amount_xof'] ?? '0');
        $amount = (float)$amountRaw;
        $destination = trim($_POST['transfer_destination'] ?? '');
        $transferType = $_POST['transfer_type'] ?? 'real';

        if (!$amount || $amount <= 0) {
            $errorMsg = "Veuillez entrer un montant valide.";
        } elseif (empty($destination)) {
            $errorMsg = "Veuillez fournir l'ID MAN GO, l'email ou le numéro du destinataire.";
        } else {
            try {
                $db->beginTransaction();
                
                $stmtDest = $db->prepare("SELECT id FROM users WHERE CONCAT('MGO-', UPPER(SUBSTRING(MD5(CONCAT(id, 'mango')), 1, 8))) = ? OR email = ? OR phone = ? LIMIT 1");
                $stmtDest->execute([$destination, $destination, $destination]);
                $receiverId = $stmtDest->fetchColumn();

                if (!$receiverId) {
                    $errorMsg = "Destinataire introuvable. Vérifiez l'ID ou l'email.";
                    $db->rollBack();
                } elseif ($receiverId == $userId) {
                    $errorMsg = "Vous ne pouvez pas vous transférer des fonds à vous-même.";
                    $db->rollBack();
                } else {
                    $stmtUser = $db->prepare("SELECT balance, virtual_credits FROM users WHERE id = ? FOR UPDATE");
                    $stmtUser->execute([$userId]);
                    $senderData = $stmtUser->fetch(PDO::FETCH_ASSOC);

                    if ($transferType === 'real') {
                        // Calcul dynamique des frais de transfert
                        $feeAmount = $amount * ($txFeePercent / 100);
                        $totalToDeduct = $amount + $feeAmount;

                        if ($senderData['balance'] < $totalToDeduct) {
                            $errorMsg = "Solde insuffisant. Prévoyez les frais de $txFeePercent% (" . number_format($feeAmount, 0) . " FCFA).";
                            $db->rollBack();
                        } else {
                            $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$totalToDeduct, $userId]);
                            $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $receiverId]);
                            
                            $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'transfer_out', ?, 'completed', NOW())")->execute([$userId, -$totalToDeduct, "Transfert vers #$receiverId (Frais: $feeAmount)"]);
                            $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'transfer_in', ?, 'completed', NOW())")->execute([$receiverId, $amount, "Reçu de #$userId"]);
                            
                            $db->commit();
                            $successMsg = "Transfert de " . number_format($amount, 0) . " FCFA réussi. Frais appliqués : $feeAmount FCFA.";
                        }
                    } else {
                        // Transfert virtuel
                        if ($senderData['virtual_credits'] < $amount) {
                            $errorMsg = "Crédits virtuels insuffisants.";
                            $db->rollBack();
                        } else {
                            $db->prepare("UPDATE users SET virtual_credits = virtual_credits - ? WHERE id = ?")->execute([$amount, $userId]);
                            $db->prepare("UPDATE users SET virtual_credits = virtual_credits + ? WHERE id = ?")->execute([$amount, $receiverId]);
                            
                            $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'virtual_transfer_out', ?, 'completed', NOW())")->execute([$userId, -$amount, "Crédits envoyés à #$receiverId"]);
                            $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'virtual_transfer_in', ?, 'completed', NOW())")->execute([$receiverId, $amount, "Crédits reçus de #$userId"]);
                            
                            $db->commit();
                            $successMsg = "Transfert de " . number_format($amount, 0) . " crédits réussi.";
                        }
                    }
                }
            } catch (Exception $e) { $db->rollBack(); $errorMsg = "Erreur : " . $e->getMessage(); }
        }
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
$balance = $virtual_credits = 0; $transactions = [];
try {
    $stmtUser = $db->prepare("SELECT balance, virtual_credits FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $u = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $balance = $u['balance'] ?? 0; $virtual_credits = $u['virtual_credits'] ?? 0;
    
    $stmtTx = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmtTx->execute([$userId]);
    $transactions = $stmtTx->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$mangoPayAddress = "MGO-" . strtoupper(substr(md5($userId . 'mango'), 0, 8));
$pageTitle = "Mon Portefeuille - MAN GO";
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-50 min-h-screen pt-12 pb-24 font-sans text-slate-800 relative z-0">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        
        <!-- HEADER & DEVISE -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 border-b border-slate-200 pb-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-gradient-to-br from-amber-400 to-amber-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-amber-500/30 transform hover:rotate-6 transition"><i class="fa-solid fa-wallet text-xl"></i></div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Mon Portefeuille</h1>
                    <p class="text-slate-500 text-xs font-medium">Gérez vos revenus en toute sécurité.</p>
                </div>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-1.5 flex items-center shadow-sm relative group cursor-help">
                <!-- INFOBULLE DEVISE -->
                <div class="absolute top-full right-0 mt-2 hidden group-hover:block w-48 bg-slate-900 text-white text-[10px] p-2.5 rounded-lg shadow-xl z-50">
                    Les montants s'affichent dans votre devise, mais les transferts réels se font sur la base du Franc CFA.
                </div>
                
                <span class="text-xs font-bold text-slate-500 px-3 flex items-center gap-2"><i class="fa-solid fa-globe text-amber-500"></i> Devise <i class="fa-solid fa-circle-info text-[10px] text-slate-400"></i></span>
                <select id="wallet-currency" onchange="convertWalletPrices()" class="bg-slate-100 text-slate-900 text-sm font-black rounded-lg cursor-pointer py-1.5 px-3 border-none outline-none hover:bg-slate-200 transition">
                    <option value="XOF">XOF (FCFA)</option><option value="USD">USD ($)</option><option value="EUR">EUR (€)</option>
                </select>
            </div>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if ($successMsg): ?><div class="bg-emerald-50 text-emerald-800 px-6 py-4 rounded-xl mb-6 font-black text-sm flex items-center shadow-sm border border-emerald-200"><i class="fa-solid fa-circle-check mr-3 text-lg"></i> <?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg): ?><div class="bg-rose-50 text-rose-800 px-6 py-4 rounded-xl mb-6 font-black text-sm flex items-center shadow-sm border border-rose-200"><i class="fa-solid fa-triangle-exclamation mr-3 text-lg"></i> <?= $errorMsg ?></div><?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- COLONNE GAUCHE -->
            <div class="lg:col-span-5 space-y-6">
                <!-- CARTE ARGENT -->
                <div class="bg-slate-900 rounded-3xl p-8 text-white relative overflow-hidden border border-emerald-500/30 shadow-[0_10px_30px_rgba(16,185,129,0.2)] group">
                    <div class="absolute -right-4 -top-4 p-4 opacity-10 group-hover:scale-110 transition duration-500"><i class="fa-solid fa-sack-dollar text-8xl"></i></div>
                    <div class="relative z-10">
                        <span class="bg-gradient-to-r from-emerald-500 to-emerald-400 text-slate-950 px-3 py-1 rounded-full text-[10px] font-black tracking-widest uppercase mb-4 inline-flex items-center"><i class="fa-solid fa-money-bill-wave mr-2"></i> Argent Réel</span>
                        <p class="text-sm font-bold text-slate-400 mb-1">Solde Disponible</p>
                        <h2 class="text-4xl sm:text-5xl font-black mb-6 flex items-baseline gap-2 text-white">
                            <span class="price-element" data-xof="<?= $balance ?>"><?= number_format($balance, 0, ',', ' ') ?></span><span class="currency-symbol text-xl text-emerald-400">FCFA</span>
                        </h2>
                        
                        <!-- INFOBULLE ID MAN GO -->
                        <div class="relative group/id cursor-help">
                            <div class="absolute bottom-full left-0 mb-2 hidden group-hover/id:block w-full bg-emerald-900 text-emerald-100 text-[10px] p-2 rounded-lg shadow-xl z-50">
                                Copiez cet identifiant. C'est votre "Numéro de compte" sur MAN GO pour recevoir des fonds d'autres utilisateurs instantanément.
                            </div>
                            <div class="bg-slate-800/80 p-3 rounded-xl border border-slate-700 flex justify-between items-center hover:bg-slate-800 transition" onclick="copyMangoId()">
                                <div><p class="text-[9px] uppercase tracking-widest text-slate-400 font-black flex items-center gap-1">ID MAN GO PAY <i class="fa-solid fa-circle-info text-slate-500"></i></p><span class="font-mono text-sm font-black text-white" id="mangoIdToCopy"><?= $mangoPayAddress ?></span></div>
                                <div class="text-emerald-400"><i class="fa-solid fa-copy"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CARTE VIRTUELLE -->
                <div class="bg-white rounded-3xl p-6 border border-amber-200 relative overflow-hidden shadow-[0_10px_30px_rgba(245,158,11,0.1)] group">
                    <div class="absolute -right-4 -bottom-4 p-4 opacity-5 group-hover:scale-110 transition duration-500"><i class="fa-solid fa-bolt text-8xl text-amber-500"></i></div>
                    <div class="relative z-10">
                        <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-[10px] font-black tracking-widest uppercase mb-3 inline-flex items-center"><i class="fa-solid fa-bolt mr-1 text-amber-500"></i> Virtuel</span>
                        <p class="text-xs font-bold text-slate-500 mb-1">Crédits MAN GO</p>
                        <h2 class="text-3xl font-black mb-4 text-slate-900"><?= number_format($virtual_credits, 0, ',', ' ') ?></h2>
                        <a href="<?= $baseUrl ?>/watch_ads.php" class="inline-flex items-center bg-slate-900 hover:bg-amber-500 text-white hover:text-slate-900 text-xs font-black py-2.5 px-5 rounded-xl transition"><i class="fa-solid fa-play mr-2"></i> Gagner des crédits</a>
                    </div>
                </div>

                <!-- BOUTONS ACTION -->
                <div class="grid grid-cols-3 gap-3">
                    <button onclick="document.getElementById('depositModal').classList.remove('hidden')" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black py-4 rounded-xl shadow-[0_5px_15px_rgba(16,185,129,0.3)] transition transform hover:-translate-y-0.5 flex flex-col items-center gap-1"><i class="fa-solid fa-arrow-down text-xl"></i><span class="text-[10px] uppercase tracking-widest">Recharger</span></button>
                    <button onclick="document.getElementById('withdrawModal').classList.remove('hidden')" class="bg-slate-900 hover:bg-slate-800 text-amber-400 font-black py-4 rounded-xl shadow-[0_5px_15px_rgba(15,23,42,0.3)] transition transform hover:-translate-y-0.5 flex flex-col items-center gap-1"><i class="fa-solid fa-arrow-up text-xl"></i><span class="text-[10px] uppercase tracking-widest text-white">Retirer</span></button>
                    <button onclick="document.getElementById('transferModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-xl shadow-[0_5px_15px_rgba(37,99,235,0.3)] transition transform hover:-translate-y-0.5 flex flex-col items-center gap-1"><i class="fa-solid fa-paper-plane text-xl"></i><span class="text-[10px] uppercase tracking-widest">Transférer</span></button>
                </div>
            </div>

            <!-- COLONNE DROITE (HISTORIQUE) -->
            <div class="lg:col-span-7">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden h-full flex flex-col">
                    <div class="p-6 border-b border-slate-200 flex items-center gap-3 bg-slate-50"><div class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center"><i class="fa-solid fa-clock-rotate-left"></i></div><h3 class="font-black text-lg text-slate-900">Historique</h3></div>
                    <div class="p-0 flex-1 overflow-y-auto">
                        <?php if (empty($transactions)): ?>
                            <div class="text-center py-16 px-4 text-slate-500"><div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-200"><i class="fa-solid fa-receipt text-3xl text-slate-300"></i></div><p class="font-black text-slate-700">Aucune transaction.</p></div>
                        <?php else: ?>
                            <ul class="divide-y divide-slate-100">
                                <?php foreach ($transactions as $tx): 
                                    $isPositive = $tx['amount'] > 0;
                                    $sColor = $tx['status'] === 'pending' ? 'text-amber-600 bg-amber-50 border-amber-200' : ($tx['status'] === 'completed' ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : 'text-slate-500 bg-slate-50 border-slate-200');
                                    $sText = $tx['status'] === 'pending' ? 'En cours' : ($tx['status'] === 'completed' ? 'Terminé' : 'Annulé');
                                    
                                    if(str_contains($tx['payment_method'], 'transfer')) $type = "Transfert";
                                    elseif($tx['payment_method'] === 'crypto_deposit') $type = "Dépôt USDT";
                                    elseif($tx['payment_method'] === 'withdrawal_request') $type = "Retrait";
                                    else $type = "Transaction";
                                ?>
                                    <li class="p-4 sm:p-5 hover:bg-slate-50 transition flex items-center justify-between gap-3 group">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 border <?= $isPositive ? 'bg-emerald-100 text-emerald-600 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' ?>"><i class="fa-solid <?= $isPositive ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i></div>
                                            <div>
                                                <p class="font-black text-slate-900 text-sm"><?= $type ?></p>
                                                <div class="flex items-center gap-2 mt-1"><span class="text-[10px] font-bold text-slate-500"><?= date('d/m/y H:i', strtotime($tx['created_at'])) ?></span><span class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded border <?= $sColor ?>"><?= $sText ?></span></div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-black text-base flex items-baseline gap-1 <?= $isPositive ? 'text-emerald-600' : 'text-slate-900' ?>"><?= $isPositive ? '+' : '' ?><span class="price-element" data-xof="<?= abs($tx['amount']) ?>"><?= number_format(abs($tx['amount']), 0, ',', ' ') ?></span><span class="<?= str_contains($tx['payment_method'], 'virtual') ? '' : 'currency-symbol' ?> text-[9px] text-slate-400"><?= str_contains($tx['payment_method'], 'virtual') ? 'Crédits' : 'FCFA' ?></span></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODALES (SÉCURISÉES & ÉQUITABLES) ================= -->

<!-- DÉPÔT -->
<div id="depositModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl overflow-hidden max-w-md w-full shadow-2xl border border-slate-200">
        <div class="p-5 border-b border-slate-200 flex justify-between items-center"><h4 class="font-black text-lg text-slate-900 flex items-center gap-2"><i class="fa-solid fa-arrow-down text-emerald-500"></i> Recharger</h4><button onclick="document.getElementById('depositModal').classList.add('hidden')" class="text-slate-400 hover:text-rose-500 text-xl">&times;</button></div>
        <div class="p-6">
            <p class="text-xs font-bold text-slate-500 mb-3 text-center">Acheter des USDT via nos partenaires :</p>
            <div class="flex justify-center gap-2 mb-6">
                <a href="https://home.izichange.com/sign-up?ref=e8b1428b-982a-11f0-aaad-06c2f6bc7eb9" target="_blank" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl p-3 text-center hover:border-emerald-500 hover:shadow-sm transition group"><i class="fa-solid fa-bolt text-xl text-emerald-500 mb-1 group-hover:scale-110 transition"></i><span class="block text-[9px] font-black uppercase text-slate-700">Izichange</span></a>
                <a href="https://perfectmoney.com/" target="_blank" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl p-3 text-center hover:border-red-500 hover:shadow-sm transition group"><i class="fa-solid fa-p text-xl text-red-500 font-serif mb-1 group-hover:scale-110 transition"></i><span class="block text-[9px] font-black uppercase text-slate-700">P. Money</span></a>
                <a href="https://p2p.binance.com/" target="_blank" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl p-3 text-center hover:border-yellow-500 hover:shadow-sm transition group"><i class="fa-brands fa-binance text-xl text-yellow-500 mb-1 group-hover:scale-110 transition"></i><span class="block text-[9px] font-black uppercase text-slate-700">Binance</span></a>
            </div>
            <form action="/man_go/process_payment.php" method="GET" id="depositForm">
                <input type="hidden" name="deposit" id="deposit_amount_xof" value="">
                <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Montant (<span class="currency-label">FCFA</span>) *</label>
                <div class="relative mb-5">
                    <input type="text" inputmode="numeric" id="deposit_amount_display" placeholder="Ex: 5000" required oninput="this.value = this.value.replace(/[^0-9.]/g, ''); updateHiddenAmount(this.value, 'deposit_amount_xof');" class="w-full bg-white border-2 border-slate-300 text-slate-900 rounded-xl p-4 pr-16 text-xl font-black outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 placeholder-slate-400">
                    <span class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 font-black currency-symbol">FCFA</span>
                </div>
                <button type="submit" class="w-full py-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs uppercase rounded-xl transition shadow-md">Générer l'adresse <i class="fa-solid fa-arrow-right ml-1"></i></button>
            </form>
        </div>
    </div>
</div>

<!-- RETRAIT -->
<div id="withdrawModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl overflow-hidden max-w-md w-full shadow-2xl border border-slate-200">
        <div class="p-5 border-b border-slate-200 flex justify-between items-center"><h4 class="font-black text-lg text-slate-900 flex items-center gap-2"><i class="fa-solid fa-arrow-up text-amber-500"></i> Retirer</h4><button onclick="document.getElementById('withdrawModal').classList.add('hidden')" class="text-slate-400 hover:text-rose-500 text-xl">&times;</button></div>
        <div class="p-6">
            <div class="bg-amber-50 p-4 rounded-xl border border-amber-300 mb-5 flex gap-3 shadow-inner">
                <i class="fa-solid fa-shield-halved text-amber-500 text-2xl"></i>
                <div>
                    <h5 class="font-black text-amber-900 text-xs uppercase mb-1">Sécurité & Frais</h5>
                    <p class="text-[11px] text-amber-800 font-medium leading-relaxed">Retrait manuel garanti sous 1 à 24h. <strong>Les frais de réseau</strong> seront déduits lors de l'envoi.</p>
                </div>
            </div>
            <form method="POST" action="my_wallet.php" id="withdrawForm">
                <input type="hidden" name="action" value="withdraw_request">
                <input type="hidden" name="amount_xof" id="withdraw_amount_xof" value="">
                <div class="mb-4">
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2 flex justify-between"><span>Montant (<span class="currency-label">FCFA</span>) *</span><span class="text-emerald-600 font-bold">Max: <span class="price-element" data-xof="<?= $balance ?>"><?= number_format($balance, 0, ',', ' ') ?></span> <span class="currency-symbol">FCFA</span></span></label>
                    <div class="relative">
                        <input type="text" inputmode="numeric" id="withdraw_amount_display" placeholder="Ex: 15000" required oninput="this.value = this.value.replace(/[^0-9.]/g, ''); updateHiddenAmount(this.value, 'withdraw_amount_xof');" class="w-full bg-white border-2 border-slate-300 text-slate-900 rounded-xl p-4 pr-16 text-xl font-black outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 placeholder-slate-400">
                        <span class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 font-black currency-symbol">FCFA</span>
                    </div>
                </div>
                <div class="mb-6">
                    <!-- INFOBULLE DESTINATAIRE -->
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2 flex items-center gap-1 group relative cursor-help w-max">
                        Adresse de destination * <i class="fa-solid fa-circle-info text-blue-500"></i>
                        <div class="absolute bottom-full left-0 mb-2 hidden group-hover:block w-48 bg-slate-800 text-white text-[10px] p-2.5 rounded-lg shadow-xl z-50 normal-case font-medium">
                            Entrez votre adresse cryptomonnaie (USDT TRC20) ou votre numéro de compte Mobile Money (T-Money, Flooz).
                        </div>
                    </label>
                    <input type="text" name="wallet_destination" placeholder="Adresse USDT ou N° T-Money/Flooz" required class="w-full bg-white border-2 border-slate-300 text-slate-900 rounded-xl p-4 text-sm font-bold outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 placeholder-slate-400">
                </div>
                <button type="submit" class="w-full py-4 bg-slate-900 hover:bg-slate-800 text-white font-black text-xs uppercase rounded-xl transition shadow-md">Confirmer la demande</button>
            </form>
        </div>
    </div>
</div>

<!-- TRANSFERT -->
<div id="transferModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl overflow-hidden max-w-md w-full shadow-2xl border border-slate-200">
        <div class="p-5 border-b border-slate-200 flex justify-between items-center"><h4 class="font-black text-lg text-slate-900 flex items-center gap-2"><i class="fa-solid fa-paper-plane text-blue-600"></i> Transférer</h4><button onclick="document.getElementById('transferModal').classList.add('hidden')" class="text-slate-400 hover:text-rose-500 text-xl">&times;</button></div>
        <div class="p-6">
            <div class="bg-blue-50 p-4 rounded-xl border border-blue-200 mb-5 flex gap-3 shadow-inner">
                <i class="fa-solid fa-circle-info text-blue-500 text-2xl"></i>
                <div>
                    <h5 class="font-black text-blue-900 text-xs uppercase mb-1">Envoi instantané</h5>
                    <p class="text-[11px] text-blue-800 font-medium leading-relaxed">Transférez des fonds à un autre utilisateur. <strong class="text-rose-600">Frais MAN GO : <?= $txFeePercent ?>%</strong> sur l'argent réel.</p>
                </div>
            </div>
            <form method="POST" action="my_wallet.php" id="transferForm">
                <input type="hidden" name="action" value="transfer_funds">
                <input type="hidden" name="transfer_amount_xof" id="transfer_amount_xof" value="">
                
                <div class="mb-4">
                    <!-- INFOBULLE TYPE DE FONDS -->
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2 flex items-center gap-1 group relative cursor-help w-max">
                        Type de fonds <i class="fa-solid fa-circle-info text-blue-500"></i>
                        <div class="absolute bottom-full left-0 mb-2 hidden group-hover:block w-56 bg-slate-800 text-white text-[10px] p-2.5 rounded-lg shadow-xl z-50 normal-case font-medium">
                            L'envoi de crédits virtuels est gratuit. L'envoi d'argent réel applique une déduction supplémentaire de <?= $txFeePercent ?>% sur votre solde pour les frais MAN GO.
                        </div>
                    </label>
                    <select name="transfer_type" class="w-full bg-white border-2 border-slate-300 text-slate-900 rounded-xl p-3 text-sm font-bold outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                        <option value="real">Argent Réel (Frais <?= $txFeePercent ?>%)</option>
                        <option value="virtual">Crédits Virtuels (Sans frais)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Montant à envoyer *</label>
                    <div class="relative">
                        <input type="text" inputmode="numeric" id="transfer_amount_display" placeholder="Ex: 2000" required oninput="this.value = this.value.replace(/[^0-9.]/g, ''); updateHiddenAmount(this.value, 'transfer_amount_xof');" class="w-full bg-white border-2 border-slate-300 text-slate-900 rounded-xl p-4 pr-16 text-xl font-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 placeholder-slate-400">
                        <span class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 font-black currency-symbol">FCFA</span>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Destinataire *</label>
                    <input type="text" name="transfer_destination" placeholder="ID MAN GO (ex: MGO-A1B2C3D4), Email ou Tél" required class="w-full bg-white border-2 border-slate-300 text-slate-900 rounded-xl p-4 text-sm font-bold outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 placeholder-slate-400">
                </div>
                <button type="submit" class="w-full py-4 bg-blue-600 hover:bg-blue-500 text-white font-black text-xs uppercase rounded-xl transition shadow-md">Envoyer les fonds</button>
            </form>
        </div>
    </div>
</div>

<script>
// Les variables sont maintenant récupérées depuis la Base de Données via PHP !
const rateUSD = <?= $taux_usd ?>;
const rateEUR = <?= $taux_eur ?>;

function updateHiddenAmount(displayValue, hiddenInputId) {
    let amt = parseFloat(displayValue);
    if (isNaN(amt)) amt = 0;
    const currency = document.getElementById('wallet-currency').value;
    let xofAmount = amt;
    if (currency === 'USD') xofAmount = amt * rateUSD;
    else if (currency === 'EUR') xofAmount = amt * rateEUR;
    document.getElementById(hiddenInputId).value = Math.round(xofAmount);
}

function convertWalletPrices() {
    const currency = document.getElementById('wallet-currency').value;
    document.querySelectorAll('.currency-symbol').forEach(sym => { sym.innerText = currency === 'XOF' ? 'FCFA' : currency; });
    document.querySelectorAll('.currency-label').forEach(lbl => { lbl.innerText = currency === 'XOF' ? 'FCFA' : currency; });
    
    const depInput = document.getElementById('deposit_amount_display');
    const withInput = document.getElementById('withdraw_amount_display');
    const transInput = document.getElementById('transfer_amount_display');
    
    if(currency === 'XOF') { depInput.placeholder = "Ex: 5000"; withInput.placeholder = "Ex: 15000"; transInput.placeholder = "Ex: 2000"; }
    else { depInput.placeholder = "Ex: 10"; withInput.placeholder = "Ex: 25"; transInput.placeholder = "Ex: 5"; }

    document.querySelectorAll('.price-element').forEach(el => {
        let amt = parseFloat(el.getAttribute('data-xof')), dec = 0;
        if (currency === 'USD') { amt /= rateUSD; dec = 2; } 
        else if (currency === 'EUR') { amt /= rateEUR; dec = 2; }
        el.innerText = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: dec, maximumFractionDigits: dec }).format(amt);
    });
    
    [ {disp: depInput, hid: 'deposit_amount_xof'}, {disp: withInput, hid: 'withdraw_amount_xof'}, {disp: transInput, hid: 'transfer_amount_xof'} ].forEach(inp => {
        if(inp.disp && inp.disp.value) {
            let xofVal = parseFloat(document.getElementById(inp.hid).value || 0);
            if(currency === 'USD') inp.disp.value = (xofVal / rateUSD).toFixed(2);
            else if(currency === 'EUR') inp.disp.value = (xofVal / rateEUR).toFixed(2);
            else inp.disp.value = xofVal;
        }
    });
}
function copyMangoId() { navigator.clipboard.writeText(document.getElementById('mangoIdToCopy').innerText); alert("ID MAN GO PAY copié !"); }
</script>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>