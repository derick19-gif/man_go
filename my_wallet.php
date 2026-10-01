<?php
// =========================================================================
// Page Mon Portefeuille (Wallet) - MAN GO
// Gestion des Crédits, Commissions et Transferts P2P Sécurisés
// =========================================================================

if (!defined('APP_PATH')) define('APP_PATH', __DIR__);
require_once __DIR__ . '/config/config.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (empty($currentUserId)) {
    header("Location: $baseUrl/login.php?redirect=my_wallet.php");
    exit();
}

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/WalletManager.php';

$db = \App\Core\Database::connect();
$successMessage = '';
$errorMessage = '';

// =========================================================================
// TRAITEMENT DU TRANSFERT P2P VIA ADRESSE CRYPTO
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer') {
    $receiverWalletId = trim($_POST['receiver_wallet_id'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);

    if (empty($receiverWalletId) || $amount < 100) {
        $errorMessage = "L'adresse du portefeuille ou le montant (min. 100) est invalide.";
    } else {
        try {
            // Lancement du transfert via le nouveau système (qui gère la déduction des 1% de frais)
            $result = \App\Core\WalletManager::transferCommissionsByWalletId($db, $currentUserId, $receiverWalletId, $amount);
            
            $successMessage = "Félicitations ! Vous avez envoyé " . number_format($amount, 0, ',', ' ') . " FCFA. (Frais de réseau : " . $result['fee'] . " FCFA).";
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

// =========================================================================
// CHARGEMENT DES DONNÉES DU PORTEFEUILLE
// =========================================================================
$wallets = \App\Core\WalletManager::getWallets($db, $currentUserId);
$creditsBalance = $wallets['credits_balance'];
$commissionBalance =$wallets['commission_balance'];
$walletAddress =$wallets['wallet_id']; // Ex: MGO-A1B2C3D4

// Récupérer l'historique complet
$stmtHistory =$db->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
$stmtHistory->execute([$currentUserId]);
$transactions =$stmtHistory->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Mon Portefeuille - MAN GO";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

<?php 
$headerPath = __DIR__ . '/app/views/layouts/header.php';
if (file_exists($headerPath)) require_once$headerPath;
?>

<main class="flex-1 max-w-6xl mx-auto px-4 py-12 w-full">
    
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-900">Mon Portefeuille</h1>
        <p class="text-slate-500 mt-1">Gérez vos crédits, vos revenus et effectuez des transferts hautement sécurisés.</p>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
            <i class="fa-solid fa-circle-check text-xl mr-3"></i> <?= $successMessage ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-xl mr-3"></i> <?= $errorMessage ?>
        </div>
    <?php endif; ?>

    <!-- LES DEUX CARTES DE SOLDE -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        
        <!-- CARTE 1 : CRÉDITS (Monnaie Virtuelle) -->
        <div class="bg-slate-900 rounded-3xl p-8 relative overflow-hidden shadow-xl">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-amber-500 rounded-full blur-3xl opacity-20"></div>
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div>
                    <div class="flex justify-between items-start mb-2">
                        <span class="bg-amber-500/20 text-amber-400 text-xs font-black px-3 py-1 rounded-full uppercase tracking-wider border border-amber-500/30"><i class="fa-solid fa-bolt mr-1"></i> Virtuel</span>
                        <i class="fa-solid fa-coins text-3xl text-slate-700"></i>
                    </div>
                    <p class="text-slate-400 text-sm font-bold">Crédits MAN GO</p>
                    <h2 class="text-4xl font-black text-white mt-1"><?= number_format($creditsBalance, 0, ',', ' ') ?></h2>
                    <p class="text-slate-500 text-xs mt-2">Utilisables pour réduire vos factures d'abonnement.</p>
                </div>
                <div class="mt-8 flex gap-3">
                    <a href="<?= $baseUrl ?>/watch_ads.php" class="inline-block bg-slate-800 hover:bg-slate-700 text-amber-400 text-sm font-bold py-3 px-6 rounded-xl transition-colors border border-slate-700">
                        <i class="fa-solid fa-play mr-2"></i> Gagner des Crédits
                    </a>
                </div>
            </div>
        </div>

        <!-- CARTE 2 : COMMISSIONS (Argent Réel) & ADRESSE DE RÉCEPTION -->
        <div class="bg-gradient-to-br from-emerald-600 to-teal-800 rounded-3xl p-8 relative overflow-hidden shadow-xl shadow-emerald-600/20">
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white rounded-full blur-3xl opacity-10"></div>
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div>
                    <div class="flex justify-between items-start mb-2">
                        <span class="bg-white/20 text-white text-xs font-black px-3 py-1 rounded-full uppercase tracking-wider backdrop-blur-sm border border-white/30"><i class="fa-solid fa-money-bill-wave mr-1"></i> Argent Réel</span>
                        <i class="fa-solid fa-wallet text-3xl text-emerald-800/50"></i>
                    </div>
                    <p class="text-emerald-100 text-sm font-bold">Solde Disponible</p>
                    <h2 class="text-4xl font-black text-white mt-1"><?= number_format($commissionBalance, 0, ',', ' ') ?> <span class="text-xl opacity-80">FCFA</span></h2>
                </div>
                
                <!-- BOÎTE DE L'ADRESSE DU PORTEFEUILLE (Comme en Crypto) -->
                <div class="mt-6 bg-emerald-900/40 rounded-xl p-3 border border-emerald-500/30 flex items-center justify-between backdrop-blur-sm">
                    <div>
                        <p class="text-[10px] text-emerald-300 uppercase font-bold tracking-widest mb-1">Votre Adresse MAN GO Pay</p>
                        <p class="text-white font-mono font-black text-lg tracking-widest" id="walletAddress"><?= $walletAddress ?></p>
                    </div>
                    <button type="button" onclick="copyWalletAddress()" class="w-10 h-10 bg-emerald-700/50 hover:bg-emerald-600 text-white rounded-lg transition-colors flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-white">
                        <i class="fa-solid fa-copy" id="copyIcon"></i>
                    </button>
                </div>
                <p class="text-emerald-200/60 text-[10px] mt-2">Partagez cette adresse chiffrée pour recevoir des paiements en toute sécurité.</p>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- SECTION TRANSFERT (1/3) -->
        <div class="lg:col-span-1 bg-white rounded-3xl border border-slate-200 p-6 shadow-sm h-fit">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-lg">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
                <h3 class="font-black text-slate-800 text-lg">Envoyer des FCFA</h3>
            </div>
            
            <p class="text-xs text-slate-500 mb-6">Envoyez des fonds de manière instantanée. Le réseau MAN GO prélève <strong class="text-slate-800">1% de frais</strong> sur l'envoi.</p>

            <form action="my_wallet.php" method="POST" class="space-y-5" onsubmit="return confirm('Attention : Les envois via la blockchain MAN GO sont irréversibles. Confirmez-vous cette opération ?');">
                <input type="hidden" name="action" value="transfer">
                
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Adresse du Destinataire</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fa-solid fa-link text-slate-400"></i>
                        </div>
                        <!-- On demande maintenant l'adresse type MGO-XXXX -->
                        <input type="text" name="receiver_wallet_id" required placeholder="Ex: MGO-A1B2C3D4" class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition text-sm font-bold uppercase">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Montant à envoyer (FCFA)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fa-solid fa-money-bill text-slate-400"></i>
                        </div>
                        <input type="number" name="amount" id="transferAmount" required min="100" placeholder="Ex: 5000" class="w-full pl-10 pr-16 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition text-sm font-bold" oninput="calculateFee()">
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                            <span class="text-slate-400 font-bold text-xs">FCFA</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <p class="text-[10px] text-slate-400">Max dispo : <?= number_format($commissionBalance, 0, ',', ' ') ?></p>
                        <p class="text-[11px] font-bold text-red-500" id="feeDisplay">Frais : 0 FCFA</p>
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition text-sm shadow-lg shadow-blue-500/30 flex items-center justify-center gap-2">
                    Valider le Transfert <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>

        <!-- HISTORIQUE DES TRANSACTIONS (2/3) -->
        <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="font-black text-slate-800 uppercase text-sm tracking-wider"><i class="fa-solid fa-clock-rotate-left text-slate-400 mr-2"></i> Historique des opérations</h3>
                <span class="text-xs text-slate-500">15 dernières</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white border-b border-slate-100 text-slate-400 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4 font-bold">Date</th>
                            <th class="px-6 py-4 font-bold">Description</th>
                            <th class="px-6 py-4 font-bold text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if(empty($transactions)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-slate-400 italic">Aucune transaction pour le moment.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($transactions as$tx): ?>
                                <?php 
                                    $isCredit = ($tx['type'] === 'credit');
                                    $isFcfa = ($tx['currency'] === 'commission');
                                    $amountColor =$isCredit ? 'text-emerald-500' : 'text-slate-700';
                                    $icon =$isCredit ? 'fa-arrow-down' : 'fa-arrow-up';
                                    $iconBg =$isCredit ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-500';
                                ?>
                                <tr class="hover:bg-slate-50/50 transition group">
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">
                                        <?= date('d/m/Y', strtotime($tx['created_at'])) ?><br>
                                        <span class="text-[10px]"><?= date('H:i', strtotime($tx['created_at'])) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 <?= $iconBg ?>">
                                                <i class="fa-solid <?= $icon ?> text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-800 text-sm group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($tx['description']) ?></p>
                                                <p class="text-[10px] uppercase font-bold tracking-wider <?= $isFcfa ? 'text-emerald-600' : 'text-amber-500' ?>">
                                                    Portefeuille <?= $isFcfa ? 'Commissions (FCFA)' : 'Crédits Virtuels' ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <span class="font-black text-base <?= $amountColor ?>">
                                            <?= $isCredit ? '+' : '-' ?><?= number_format($tx['amount'], 0, ',', ' ') ?>
                                        </span>
                                        <span class="text-xs font-bold text-slate-400 ml-1">
                                            <?= $isFcfa ? 'FCFA' : 'Crédits' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</main>

<script>
    // 1. Fonction pour copier l'adresse du portefeuille
    function copyWalletAddress() {
        const address = document.getElementById('walletAddress').innerText;
        navigator.clipboard.writeText(address).then(() => {
            const icon = document.getElementById('copyIcon');
            icon.className = "fa-solid fa-check text-white"; // Change l'icône en coche
            setTimeout(() => { icon.className = "fa-solid fa-copy"; }, 2000); // Remet l'icône normale
        }).catch(err => {
            alert('Erreur lors de la copie');
        });
    }

    // 2. Calcul dynamique des frais de 1% (Min 5 FCFA)
    function calculateFee() {
        const amountInput = document.getElementById('transferAmount').value;
        const amount = parseFloat(amountInput);
        const feeDisplay = document.getElementById('feeDisplay');
        
        if (isNaN(amount) || amount < 100) {
            feeDisplay.innerText = "Frais : 0 FCFA";
            feeDisplay.className = "text-[11px] font-bold text-slate-400";
        } else {
            let fee = Math.ceil((amount * 1) / 100);
            if (fee < 5) fee = 5; // Minimum 5 FCFA
            feeDisplay.innerText = `Frais de réseau : -${fee} FCFA`;
            feeDisplay.className = "text-[11px] font-black text-red-500";
        }
    }
</script>
</body>
</html>