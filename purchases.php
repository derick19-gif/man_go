<?php
// =========================================================================
// MES ACHATS - Historique des commandes client (MAN GO)
// =========================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

Session::init();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// Vérification de connexion
if (!Session::isAuthenticated()) {
    header("Location: $baseUrl/login.php");
    exit();
}

$db = \App\Core\Database::connect();
$userId = (int)Session::get('user_id');
$userRole = Session::get('user_role');

// Lien de retour dynamique
$dashboardLink = ($userRole === 'vendor') ? "$baseUrl/vendor_dir/dashboard.php" : "$baseUrl/client/views/dashboard.php";

$orders = [];
$dbError = false;

try {
    // On récupère les commandes de l'utilisateur avec les détails du produit et du vendeur
    $stmt = $db->prepare("
        SELECT o.id, o.order_number, o.amount, o.currency, o.status, o.created_at, o.vendor_id,
               l.title AS product_title, l.image_path,
               u.firstname AS vendor_firstname, u.lastname AS vendor_lastname
        FROM orders o
        LEFT JOIN listings l ON o.listing_id = l.id
        LEFT JOIN users u ON o.vendor_id = u.id
        WHERE o.buyer_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si une erreur survient, on capture le VRAI message d'erreur SQL
    $dbError = true;
    $sqlErrorMessage = $e->getMessage();
}

$pageTitle = "Mes Achats - MAN GO";
$headerPath = __DIR__ . '/themes/default/templates/layouts/header.php';
if (!file_exists($headerPath)) { $headerPath = __DIR__ . '/app/views/layouts/header.php'; }
if (file_exists($headerPath)) { require_once $headerPath; }

// Fonction pour formater le statut avec des couleurs Tailwind
function getStatusBadge($status) {
    switch (strtolower($status)) {
        case 'pending':
            return '<span class="bg-amber-100 text-amber-700 border border-amber-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1"><i class="fa-solid fa-clock"></i> En attente</span>';
        case 'paid':
            return '<span class="bg-blue-100 text-blue-700 border border-blue-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1"><i class="fa-solid fa-check"></i> Payée</span>';
        case 'shipped':
            return '<span class="bg-purple-100 text-purple-700 border border-purple-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1"><i class="fa-solid fa-truck-fast"></i> Expédiée</span>';
        case 'delivered':
            return '<span class="bg-emerald-100 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1"><i class="fa-solid fa-box-open"></i> Livrée</span>';
        case 'cancelled':
            return '<span class="bg-rose-100 text-rose-700 border border-rose-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1"><i class="fa-solid fa-ban"></i> Annulée</span>';
        default:
            return '<span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Inconnu</span>';
    }
}
?>

<div class="bg-slate-50 min-h-screen pt-28 pb-24 font-sans text-slate-800">
    <main class="max-w-5xl mx-auto px-4 sm:px-6">
        
        <!-- En-tête de la page -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Mes Achats</h1>
                <p class="text-slate-500 mt-1">Suivez l'historique et le statut de vos commandes.</p>
            </div>
            <a href="<?= $dashboardLink ?>" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-900 hover:text-white font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center self-start sm:self-auto">
                <i class="fa-solid fa-arrow-left mr-2"></i> Retour au Dashboard
            </a>
        </div>

        <?php if ($dbError): ?>
            <div class="bg-rose-50 text-rose-700 border border-rose-200 p-6 rounded-2xl flex items-center gap-4 shadow-sm">
                <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
                <div>
                    <h3 class="font-bold text-lg">Erreur de requête SQL</h3>
                    <p class="text-sm">Une erreur s'est produite lors de la lecture des données :</p>
                    <code class="block mt-2 bg-rose-100 p-2 rounded text-xs font-mono text-rose-800"><?= htmlspecialchars($sqlErrorMessage) ?></code>
                </div>
            </div>
        <?php elseif (empty($orders)): ?>
            <!-- EMPTY STATE (Si aucune commande n'a été passée) -->
            <div class="bg-white rounded-3xl border border-dashed border-slate-300 p-12 text-center shadow-sm max-w-2xl mx-auto mt-10">
                <div class="w-24 h-24 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 shadow-inner">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-900 mb-2">Aucun achat pour le moment</h3>
                <p class="text-slate-500 text-sm mb-8 leading-relaxed max-w-md mx-auto">
                    Votre historique de commandes est vide. Découvrez des milliers de produits et services proposés par nos vendeurs certifiés.
                </p>
                <a href="<?= $baseUrl ?>/listings.php" class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black py-4 px-8 rounded-xl shadow-lg shadow-amber-500/20 transition-transform transform hover:-translate-y-1">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Explorer le marché</span>
                </a>
            </div>
        <?php else: ?>
            <!-- LISTE DES COMMANDES -->
            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <?php 
                        $vendorName = trim(($order['vendor_firstname'] ?? '') . ' ' . ($order['vendor_lastname'] ?? ''));
                        if (empty($vendorName)) $vendorName = "Vendeur Pro";
                        $imageSrc = !empty($order['image_path']) ? $baseUrl . '/' . $order['image_path'] : $baseUrl . '/assets/images/placeholder.jpg';
                    ?>
                    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                        
                        <!-- Header de la carte commande -->
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center pb-4 border-b border-slate-100 gap-4 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="bg-slate-100 text-slate-600 p-2.5 rounded-lg">
                                    <i class="fa-solid fa-receipt"></i>
                                </div>
                                <div>
                                    <h4 class="font-black text-slate-900">Commande N° <?= htmlspecialchars($order['order_number']) ?></h4>
                                    <p class="text-xs font-bold text-slate-400">Passée le <?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></p>
                                </div>
                            </div>
                            <div>
                                <?= getStatusBadge($order['status']) ?>
                            </div>
                        </div>

                        <!-- Détails du produit -->
                        <div class="flex flex-col sm:flex-row gap-6">
                            <img src="<?= htmlspecialchars($imageSrc) ?>" alt="Produit" class="w-24 h-24 object-cover rounded-2xl border border-slate-100 shadow-sm flex-shrink-0">
                            
                            <div class="flex-grow flex flex-col justify-between">
                                <div>
                                    <h3 class="text-lg font-black text-slate-900 leading-tight mb-1"><?= htmlspecialchars($order['product_title']) ?></h3>
                                    <p class="text-sm font-semibold text-slate-500 mb-2">
                                        Vendu par : <a href="<?= $baseUrl ?>/chat.php?vendor_id=<?= $order['vendor_id'] ?>" class="text-amber-600 hover:underline"><?= htmlspecialchars($vendorName) ?></a>
                                    </p>
                                </div>
                                <div class="text-xl font-black text-slate-900">
                                    <?= number_format($order['amount'], 0, ',', ' ') ?> <span class="text-xs text-slate-500 uppercase"><?= htmlspecialchars($order['currency']) ?></span>
                                </div>
                            </div>
                            
                            <!-- Actions Rapides -->
                            <div class="flex flex-col gap-2 justify-end sm:min-w-[200px]">
                                <a href="<?= $baseUrl ?>/chat.php?vendor_id=<?= $order['vendor_id'] ?>" class="w-full bg-indigo-50 text-indigo-600 hover:bg-indigo-100 font-bold py-2.5 px-4 rounded-xl transition text-xs text-center flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-message"></i> Contacter le vendeur
                                </a>
                                
                                <!-- ========================================================= -->
                                <!-- NOUVEAU BOUTON DYNAMIQUE : LE REÇU OFFICIEL -->
                                <!-- ========================================================= -->
                                <?php if (in_array($order['status'], ['paid', 'shipped', 'delivered'])): ?>
                                    <div class="relative group w-full text-left">
                                        <a href="<?= $baseUrl ?>/receipt.php?id=<?= $order['id'] ?>" target="_blank" class="w-full bg-white hover:bg-amber-500 text-slate-700 hover:text-slate-900 border border-slate-200 hover:border-amber-500 font-black py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-2 shadow-sm">
                                            <i class="fa-solid fa-file-invoice text-amber-500 group-hover:text-slate-900"></i> Le Reçu
                                        </a>
                                        <!-- Infobulle -->
                                        <div class="opacity-0 absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 bg-slate-800 text-white text-[10px] py-2.5 px-3 rounded-xl pointer-events-none transition-opacity duration-300 group-hover:opacity-100 shadow-2xl text-center z-20 font-medium">
                                            <strong class="text-amber-500 block mb-1 uppercase tracking-widest text-[9px]">Document Sécurisé</strong>
                                            Téléchargez ou imprimez votre preuve d'achat en un clic.
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($order['status'] === 'delivered'): ?>
                                    <button onclick="alert('Module d\'évaluation en cours de construction.')" class="w-full bg-slate-900 text-white hover:bg-slate-800 font-bold py-2.5 px-4 rounded-xl transition text-xs text-center flex items-center justify-center gap-2 mt-1">
                                        <i class="fa-solid fa-star text-amber-400"></i> Noter l'article
                                    </button>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php 
$footerPath = __DIR__ . '/themes/default/templates/layouts/footer.php';
if (!file_exists($footerPath)) { $footerPath = __DIR__ . '/app/views/layouts/footer.php'; }
if (file_exists($footerPath)) { require_once $footerPath; }
?>