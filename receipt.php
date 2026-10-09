<?php
// =========================================================================
// REÇU OFFICIEL MAN GO - receipt.php (Format Imprimable A4)
// =========================================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

Session::init();

if (!Session::isAuthenticated()) {
    die("Accès refusé. Veuillez vous connecter.");
}

$db = \App\Core\Database::connect();
$userId = (int)Session::get('user_id');
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId) {
    die("Numéro de commande invalide.");
}

// 1. Récupération sécurisée de la commande
$stmt = $db->prepare("
    SELECT o.*, 
           l.title AS item_title, 
           l.price AS item_price,
           u_buyer.firstname AS buyer_fname, u_buyer.lastname AS buyer_lname, u_buyer.email AS buyer_email,
           u_vendor.firstname AS vendor_fname, u_vendor.lastname AS vendor_lname, u_vendor.phone AS vendor_phone
    FROM orders o
    JOIN listings l ON o.listing_id = l.id
    JOIN users u_buyer ON o.buyer_id = u_buyer.id
    JOIN users u_vendor ON o.vendor_id = u_vendor.id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Commande introuvable.");
}

// 2. Vérification de sécurité : Seul l'acheteur ou le vendeur peut voir ce reçu
if ($order['buyer_id'] != $userId && $order['vendor_id'] != $userId) {
    die("Accès non autorisé à ce document confidentiel.");
}

$statusText = $order['status'] === 'paid' ? 'PAYÉE' : ($order['status'] === 'pending' ? 'EN ATTENTE' : 'ANNULÉE');
$statusColor = $order['status'] === 'paid' ? 'text-emerald-600 border-emerald-600' : 'text-amber-500 border-amber-500';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu Officiel - <?= htmlspecialchars($order['order_number']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background-color: white !important; }
            .print-border { border: 1px solid #e2e8f0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 py-10">

    <!-- Boutons d'action (Masqués à l'impression) -->
    <div class="max-w-3xl mx-auto mb-6 flex justify-between items-center no-print px-4">
        <a href="javascript:history.back()" class="text-slate-500 hover:text-slate-900 font-bold flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> Retour
        </a>
        <button onclick="window.print()" class="bg-slate-900 hover:bg-slate-800 text-white font-black px-6 py-3 rounded-xl shadow-lg transition flex items-center gap-2">
            <i class="fa-solid fa-print"></i> Imprimer le reçu
        </button>
    </div>

    <!-- FEUILLE DU REÇU (Format A4) -->
    <div class="max-w-3xl mx-auto bg-white p-10 sm:p-16 rounded-none sm:rounded-2xl shadow-xl print-border">
        
        <!-- EN-TÊTE -->
        <div class="flex justify-between items-start border-b-2 border-slate-100 pb-8 mb-8">
            <div>
                <h1 class="text-3xl font-black text-amber-500 tracking-tighter">MAN GO<span class="text-slate-900">.</span></h1>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Marketplace Universelle</p>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-black text-slate-900 uppercase tracking-widest mb-2">Reçu Officiel</h2>
                <p class="text-sm text-slate-500 font-bold">N° <?= htmlspecialchars($order['order_number']) ?></p>
                <p class="text-sm text-slate-500 font-bold">Date : <?= date('d/m/Y', strtotime($order['created_at'])) ?></p>
            </div>
        </div>

        <!-- INFORMATIONS -->
        <div class="grid grid-cols-2 gap-12 mb-12">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Émis par (Vendeur)</p>
                <h3 class="text-lg font-black text-slate-900"><?= htmlspecialchars($order['vendor_fname'] . ' ' . $order['vendor_lname']) ?></h3>
                <p class="text-sm text-slate-600 font-medium mt-1"><i class="fa-solid fa-phone text-slate-400 text-xs w-4"></i> <?= htmlspecialchars($order['vendor_phone'] ?: 'Non renseigné') ?></p>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Facturé à (Client)</p>
                <h3 class="text-lg font-black text-slate-900"><?= htmlspecialchars($order['buyer_fname'] . ' ' . $order['buyer_lname']) ?></h3>
                <p class="text-sm text-slate-600 font-medium mt-1"><i class="fa-solid fa-envelope text-slate-400 text-xs w-4"></i> <?= htmlspecialchars($order['buyer_email']) ?></p>
                <p class="text-sm text-slate-600 font-medium mt-1"><i class="fa-solid fa-location-dot text-slate-400 text-xs w-4"></i> <?= htmlspecialchars($order['shipping_address'] . ', ' . $order['shipping_city']) ?></p>
            </div>
        </div>

        <!-- TABLEAU DE COMMANDE -->
        <div class="mb-12">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-slate-800 text-slate-900">
                        <th class="py-3 px-2 font-black uppercase text-xs tracking-widest">Désignation</th>
                        <th class="py-3 px-2 font-black uppercase text-xs tracking-widest text-right">Méthode de Paiement</th>
                        <th class="py-3 px-2 font-black uppercase text-xs tracking-widest text-right">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-100">
                        <td class="py-4 px-2 text-sm font-bold text-slate-700"><?= htmlspecialchars($order['item_title']) ?></td>
                        <td class="py-4 px-2 text-sm font-bold text-slate-500 text-right uppercase"><?= htmlspecialchars($order['payment_method']) ?></td>
                        <td class="py-4 px-2 text-base font-black text-slate-900 text-right"><?= number_format($order['amount'], 0, ',', ' ') ?> FCFA</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- TOTAUX & STATUT -->
        <div class="flex justify-between items-end bg-slate-50 p-6 rounded-2xl border border-slate-100">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Statut de la transaction</p>
                <div class="inline-block border-2 px-4 py-1.5 rounded-lg font-black text-sm tracking-wider <?= $statusColor ?>">
                    <?= $statusText ?>
                </div>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Payé</p>
                <p class="text-3xl font-black text-slate-900"><?= number_format($order['amount'], 0, ',', ' ') ?> <span class="text-lg text-slate-500">FCFA</span></p>
            </div>
        </div>

        <!-- PIED DE PAGE -->
        <div class="mt-16 text-center border-t border-slate-100 pt-8">
            <p class="text-xs font-bold text-slate-400 mb-1">Merci de votre confiance sur MAN GO.</p>
            <p class="text-[10px] text-slate-300 uppercase tracking-widest">Ce document tient lieu de preuve d'achat sécurisée.</p>
        </div>

    </div>
</body>
</html>