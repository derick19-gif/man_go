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
$successMsg = '';
$errorMsg = '';

// =========================================================================
// TRAITEMENT DU FORMULAIRE D'ÉVALUATION (AVIS)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $vendorId = filter_input(INPUT_POST, 'vendor_id', FILTER_VALIDATE_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating >= 1 && $rating <= 5 && $orderId > 0 && $vendorId > 0) {
        try {
            // 1. Vérifier que la commande appartient bien à l'utilisateur ET qu'elle est livrée
            $stmtCheck = $db->prepare("SELECT id FROM orders WHERE id = ? AND buyer_id = ? AND status IN ('delivered', 'completed')");
            $stmtCheck->execute([$orderId, $userId]);
            
            if ($stmtCheck->fetch()) {
                // 2. Insérer l'avis
                $stmtIns = $db->prepare("INSERT INTO seller_reviews (vendor_id, buyer_id, order_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
                $stmtIns->execute([$vendorId, $userId, $orderId, $rating, $comment]);
                $successMsg = "Merci pour votre avis ! Votre note a bien été enregistrée pour ce vendeur.";
            } else {
                $errorMsg = "Vous ne pouvez pas évaluer cette commande (statut incorrect ou non autorisée).";
            }
        } catch (PDOException $e) {
            // Code 23000 = Violation de contrainte d'unicité (Déjà évalué)
            if ($e->getCode() == 23000) {
                $errorMsg = "Vous avez déjà laissé un avis pour cette commande.";
            } else {
                $errorMsg = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    } else {
        $errorMsg = "Veuillez sélectionner une note valide (entre 1 et 5 étoiles).";
    }
}

// =========================================================================
// RÉCUPÉRATION DES COMMANDES ET DES AVIS EXISTANTS
// =========================================================================
try {
    // AJOUT : LEFT JOIN seller_reviews pour savoir si la commande a déjà été notée
    $stmt = $db->prepare("
        SELECT o.id, o.order_number, o.amount, o.currency, o.status, o.created_at, o.vendor_id,
               l.title AS product_title, l.image_path,
               u.firstname AS vendor_firstname, u.lastname AS vendor_lastname,
               sr.id AS review_id, sr.rating AS review_rating
        FROM orders o
        LEFT JOIN listings l ON o.listing_id = l.id
        LEFT JOIN users u ON o.vendor_id = u.id
        LEFT JOIN seller_reviews sr ON o.id = sr.order_id
        WHERE o.buyer_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dbError = true;
    $sqlErrorMessage = $e->getMessage();
}

$pageTitle = "Mes Achats - MAN GO";
$headerPath = __DIR__ . '/themes/default/templates/layouts/header.php';
if (!file_exists($headerPath)) { $headerPath = __DIR__ . '/app/views/layouts/header.php'; }
if (file_exists($headerPath)) { require_once $headerPath; }

function getStatusBadge($status) {
    switch (strtolower($status)) {
        case 'pending':
            return '<span class="bg-amber-100 text-amber-700 border border-amber-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1"><i class="fa-solid fa-clock"></i> En attente</span>';
        case 'paid':
            return '<span class="bg-blue-100 text-blue-700 border border-blue-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1"><i class="fa-solid fa-check"></i> Payée</span>';
        case 'shipped':
            return '<span class="bg-purple-100 text-purple-700 border border-purple-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1"><i class="fa-solid fa-truck-fast"></i> Expédiée</span>';
        case 'delivered':
        case 'completed':
            return '<span class="bg-emerald-100 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1"><i class="fa-solid fa-box-open"></i> Livrée</span>';
        case 'cancelled':
            return '<span class="bg-rose-100 text-rose-700 border border-rose-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1"><i class="fa-solid fa-ban"></i> Annulée</span>';
        default:
            return '<span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Inconnu</span>';
    }
}
?>

<div class="bg-slate-50 min-h-screen pt-28 pb-24 font-sans text-slate-800 relative z-0">
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

        <?php if ($successMsg): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
                <i class="fa-solid fa-circle-check text-xl mr-3"></i> <?= htmlspecialchars($successMsg) ?>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-700 p-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
                <i class="fa-solid fa-triangle-exclamation text-xl mr-3"></i> <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

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
            <!-- EMPTY STATE -->
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
                        $isDelivered = in_array(strtolower($order['status']), ['delivered', 'completed']);
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
                                        Vendu par : <a href="<?= $baseUrl ?>/store.php?id=<?= $order['vendor_id'] ?>" class="text-amber-600 hover:underline font-bold" title="Voir la boutique du vendeur">
                                            <i class="fa-solid fa-store text-xs mr-1"></i><?= htmlspecialchars($vendorName) ?>
                                        </a>
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
                                
                                <?php if (in_array(strtolower($order['status']), ['paid', 'shipped', 'delivered', 'completed'])): ?>
                                    <div class="relative group w-full text-left">
                                        <a href="<?= $baseUrl ?>/receipt.php?id=<?= $order['id'] ?>" target="_blank" class="w-full bg-white hover:bg-amber-500 text-slate-700 hover:text-slate-900 border border-slate-200 hover:border-amber-500 font-black py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-2 shadow-sm">
                                            <i class="fa-solid fa-file-invoice text-amber-500 group-hover:text-slate-900"></i> Le Reçu
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- SYSTÈME DE NOTATION -->
                                <?php if ($isDelivered): ?>
                                    <?php if (!empty($order['review_id'])): ?>
                                        <!-- Déjà noté -->
                                        <div class="w-full bg-emerald-50 border border-emerald-100 text-emerald-600 font-bold py-2.5 px-4 rounded-xl text-xs text-center flex items-center justify-center gap-2 mt-1 cursor-default">
                                            <div class="flex text-amber-400">
                                                <?php for($i=1; $i<=5; $i++) { echo ($i <= $order['review_rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star text-amber-200"></i>'; } ?>
                                            </div>
                                            <span class="text-emerald-700">Noté</span>
                                        </div>
                                    <?php else: ?>
                                        <!-- Bouton pour noter -->
                                        <button onclick="openReviewModal(<?= $order['id'] ?>, <?= $order['vendor_id'] ?>, '<?= addslashes(htmlspecialchars($vendorName)) ?>', '<?= addslashes(htmlspecialchars($order['product_title'])) ?>')" class="w-full bg-slate-900 text-white hover:bg-slate-800 font-bold py-2.5 px-4 rounded-xl transition text-xs text-center flex items-center justify-center gap-2 mt-1 shadow-md">
                                            <i class="fa-regular fa-star text-amber-400"></i> Noter l'article
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- ================= MODALE D'ÉVALUATION ================= -->
<div id="reviewModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl overflow-hidden max-w-md w-full shadow-2xl border border-slate-200">
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
            <h4 class="font-black text-lg text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-star text-amber-500"></i> Évaluer le vendeur
            </h4>
            <button type="button" onclick="closeReviewModal()" class="text-slate-400 hover:text-rose-500 text-xl">&times;</button>
        </div>
        <div class="p-6">
            <div class="text-center mb-6">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Commande reçue de</p>
                <h5 id="modalVendorName" class="text-lg font-black text-slate-900">Nom du vendeur</h5>
                <p id="modalProductName" class="text-sm text-slate-500 truncate mt-1">Produit</p>
            </div>

            <form method="POST" id="reviewForm">
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" name="order_id" id="modalOrderId" value="">
                <input type="hidden" name="vendor_id" id="modalVendorId" value="">
                <input type="hidden" name="rating" id="modalRatingValue" value="0">

                <!-- Widget Étoiles Interactif -->
                <div class="flex justify-center gap-2 mb-6" id="starContainer">
                    <i class="fa-solid fa-star text-3xl text-slate-200 cursor-pointer transition hover:scale-110" data-val="1" onclick="setRating(1)"></i>
                    <i class="fa-solid fa-star text-3xl text-slate-200 cursor-pointer transition hover:scale-110" data-val="2" onclick="setRating(2)"></i>
                    <i class="fa-solid fa-star text-3xl text-slate-200 cursor-pointer transition hover:scale-110" data-val="3" onclick="setRating(3)"></i>
                    <i class="fa-solid fa-star text-3xl text-slate-200 cursor-pointer transition hover:scale-110" data-val="4" onclick="setRating(4)"></i>
                    <i class="fa-solid fa-star text-3xl text-slate-200 cursor-pointer transition hover:scale-110" data-val="5" onclick="setRating(5)"></i>
                </div>
                
                <p id="ratingFeedback" class="text-center text-sm font-bold text-amber-500 mb-6 h-5"></p>

                <div class="mb-6">
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Laissez un commentaire (Optionnel)</label>
                    <textarea name="comment" rows="3" placeholder="Comment s'est passée votre transaction ? Le produit correspond-il à la description ?" class="w-full bg-slate-50 border-2 border-slate-200 text-slate-900 rounded-xl p-4 text-sm outline-none focus:border-amber-500 focus:bg-white transition"></textarea>
                </div>

                <button type="submit" id="submitReviewBtn" disabled class="w-full py-4 bg-slate-200 text-slate-400 font-black text-xs uppercase rounded-xl transition cursor-not-allowed">
                    Sélectionnez une note
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Logique d'affichage de la modale d'évaluation
function openReviewModal(orderId, vendorId, vendorName, productName) {
    document.getElementById('modalOrderId').value = orderId;
    document.getElementById('modalVendorId').value = vendorId;
    document.getElementById('modalVendorName').innerText = vendorName;
    document.getElementById('modalProductName').innerText = productName;
    
    // Reset du formulaire
    setRating(0);
    document.getElementById('reviewForm').reset();
    
    document.getElementById('reviewModal').classList.remove('hidden');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
}

// Logique du système d'étoiles
const ratingTexts = {
    1: "Très déçu 😠",
    2: "Pas terrible 😕",
    3: "Correct 😐",
    4: "Très bien 🙂",
    5: "Parfait ! 🤩"
};

function setRating(val) {
    document.getElementById('modalRatingValue').value = val;
    const stars = document.getElementById('starContainer').children;
    
    for (let i = 0; i < stars.length; i++) {
        if (i < val) {
            stars[i].classList.replace('text-slate-200', 'text-amber-400');
        } else {
            stars[i].classList.replace('text-amber-400', 'text-slate-200');
        }
    }

    const btn = document.getElementById('submitReviewBtn');
    const feedback = document.getElementById('ratingFeedback');

    if (val > 0) {
        feedback.innerText = ratingTexts[val];
        btn.disabled = false;
        btn.className = "w-full py-4 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs uppercase rounded-xl transition shadow-md hover:shadow-lg transform hover:-translate-y-0.5";
        btn.innerText = "Publier mon avis";
    } else {
        feedback.innerText = "";
        btn.disabled = true;
        btn.className = "w-full py-4 bg-slate-200 text-slate-400 font-black text-xs uppercase rounded-xl transition cursor-not-allowed";
        btn.innerText = "Sélectionnez une note";
    }
}
</script>

<?php 
$footerPath = __DIR__ . '/themes/default/templates/layouts/footer.php';
if (!file_exists($footerPath)) { $footerPath = __DIR__ . '/app/views/layouts/footer.php'; }
if (file_exists($footerPath)) { require_once $footerPath; }
?>