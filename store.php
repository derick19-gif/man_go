<?php
// =========================================================================
// BOUTIQUE DU VENDEUR - store.php (Page Publique)
// =========================================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

Session::init();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

$db = \App\Core\Database::connect();

// 1. Récupération de l'ID du vendeur depuis l'URL
$vendorId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$vendorId) {
    header("Location: $baseUrl/listings.php");
    exit();
}

try {
    // 2. Récupération des informations du vendeur
    $stmtVendor = $db->prepare("SELECT id, firstname, lastname, created_at, is_premium, phone FROM users WHERE id = ?");
    $stmtVendor->execute([$vendorId]);
    $vendor = $stmtVendor->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        header("Location: $baseUrl/listings.php");
        exit();
    }

    $vendorName = trim($vendor['firstname'] . ' ' . $vendor['lastname']);
    if (empty($vendorName)) $vendorName = "Vendeur MAN GO";

    // 3. Calcul des Statistiques d'Avis (Moyenne et Total)
    $stmtReviewsStats = $db->prepare("SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating FROM seller_reviews WHERE vendor_id = ? AND status = 'published'");
    $stmtReviewsStats->execute([$vendorId]);
    $reviewStats = $stmtReviewsStats->fetch(PDO::FETCH_ASSOC);
    
    $totalReviews = (int)$reviewStats['total_reviews'];
    $avgRating = $totalReviews > 0 ? round($reviewStats['avg_rating'], 1) : 0;

    // 4. Récupération du Catalogue (Toutes les annonces actives du vendeur)
    $stmtListings = $db->prepare("
        SELECT l.*, c.name_key as category_name 
        FROM listings l 
        LEFT JOIN categories c ON l.category_id = c.id 
        WHERE l.user_id = ? AND l.status = 'active' 
        ORDER BY l.created_at DESC
    ");
    $stmtListings->execute([$vendorId]);
    $vendorListings = $stmtListings->fetchAll(PDO::FETCH_ASSOC);

    // 5. Récupération des Derniers Avis (Les 10 plus récents)
    $stmtReviews = $db->prepare("
        SELECT sr.rating, sr.comment, sr.created_at, u.firstname 
        FROM seller_reviews sr 
        JOIN users u ON sr.buyer_id = u.id 
        WHERE sr.vendor_id = ? AND sr.status = 'published' 
        ORDER BY sr.created_at DESC LIMIT 10
    ");
    $stmtReviews->execute([$vendorId]);
    $recentReviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur base de données.");
}

$pageTitle = "Boutique de $vendorName - MAN GO";
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-50 min-h-screen pb-24 font-sans text-slate-800">
    
    <!-- EN-TÊTE DE LA BOUTIQUE -->
    <div class="bg-slate-900 border-b-4 border-amber-500 pt-32 pb-16 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 relative z-10">
            <div class="flex flex-col md:flex-row items-center md:items-end gap-6 text-center md:text-left">
                
                <!-- Avatar du Vendeur -->
                <div class="w-32 h-32 bg-white rounded-3xl p-2 shadow-2xl shrink-0 transform -translate-y-4 md:translate-y-8">
                    <div class="w-full h-full bg-slate-100 rounded-2xl flex items-center justify-center text-4xl font-black text-amber-500">
                        <?= strtoupper(substr($vendorName, 0, 1)) ?>
                    </div>
                </div>

                <!-- Infos Principales -->
                <div class="flex-grow md:pb-2">
                    <div class="flex flex-col md:flex-row md:items-center gap-3 mb-2">
                        <h1 class="text-4xl font-black text-white"><?= htmlspecialchars($vendorName) ?></h1>
                        <?php if ($vendor['is_premium']): ?>
                            <span class="inline-flex items-center gap-1 bg-gradient-to-r from-amber-400 to-amber-600 text-slate-900 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider shadow-lg shadow-amber-500/30 w-max mx-auto md:mx-0">
                                <i class="fa-solid fa-crown"></i> Vendeur Pro
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 text-slate-400 text-sm font-bold">
                        <span class="flex items-center gap-1"><i class="fa-solid fa-calendar-days text-amber-500"></i> Membre depuis <?= date('M Y', strtotime($vendor['created_at'])) ?></span>
                        <span class="flex items-center gap-1"><i class="fa-solid fa-box-open text-amber-500"></i> <?= count($vendorListings) ?> Annonces actives</span>
                        
                        <!-- Bloc Étoiles -->
                        <div class="flex items-center gap-1 bg-slate-800 px-3 py-1 rounded-lg">
                            <span class="text-amber-400">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= floor($avgRating)) echo '<i class="fa-solid fa-star"></i>';
                                    elseif ($i - 0.5 <= $avgRating) echo '<i class="fa-solid fa-star-half-stroke"></i>';
                                    else echo '<i class="fa-regular fa-star text-slate-600"></i>';
                                }
                                ?>
                            </span>
                            <span class="text-white ml-2"><?= $avgRating ?>/5</span>
                            <span class="text-slate-500 text-xs">(<?= $totalReviews ?> avis)</span>
                        </div>
                    </div>
                </div>
                
                <!-- Action Bouton (Contact) -->
                <?php if (Session::getUserId() != $vendorId): ?>
                <div class="md:pb-2">
                    <a href="<?= $baseUrl ?>/chat.php?vendor_id=<?= $vendor['id'] ?>" class="bg-amber-500 hover:bg-amber-400 text-slate-900 font-black px-6 py-3.5 rounded-xl shadow-lg shadow-amber-500/20 transition flex items-center justify-center gap-2 w-full md:w-auto">
                        <i class="fa-solid fa-message"></i> Contacter
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CONTENU DE LA BOUTIQUE -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 md:pt-24">
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            
            <!-- COLONNE GAUCHE (CATALOGUE : 8/12) -->
            <div class="lg:col-span-8">
                <div class="flex items-center justify-between mb-8 border-b border-slate-200 pb-4">
                    <h2 class="text-2xl font-black text-slate-900 flex items-center gap-3">
                        <i class="fa-solid fa-boxes-stacked text-amber-500"></i> Catalogue
                    </h2>
                </div>

                <?php if (empty($vendorListings)): ?>
                    <div class="bg-white rounded-3xl border border-dashed border-slate-300 p-12 text-center shadow-sm">
                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 text-slate-300"><i class="fa-solid fa-box-open"></i></div>
                        <h3 class="text-xl font-bold text-slate-900 mb-2">Catalogue vide</h3>
                        <p class="text-slate-500 text-sm">Ce vendeur n'a aucune annonce active pour le moment.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <?php foreach ($vendorListings as $item): ?>
                            <?php $img = !empty($item['image_path']) ? $baseUrl.'/'.$item['image_path'] : $baseUrl.'/assets/images/placeholder.jpg'; ?>
                            <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $item['id'] ?>" class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group transform hover:-translate-y-1">
                                <div class="h-48 overflow-hidden bg-slate-100 relative">
                                    <img src="<?= htmlspecialchars($img) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                                </div>
                                <div class="p-5 flex-1 flex flex-col justify-between">
                                    <div>
                                        <span class="text-[10px] uppercase font-black text-amber-600 tracking-wider mb-2 block"><?= htmlspecialchars($item['category_name']) ?></span>
                                        <h4 class="font-bold text-slate-900 line-clamp-2 group-hover:text-amber-500 transition"><?= htmlspecialchars($item['title']) ?></h4>
                                    </div>
                                    <div class="mt-4 pt-4 border-t border-slate-100">
                                        <span class="font-black text-slate-900 text-xl">
                                            <?= number_format($item['price'], 0, ',', ' ') ?> <span class="text-xs text-slate-500 uppercase">FCFA</span>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- COLONNE DROITE (AVIS CLIENTS : 4/12) -->
            <div class="lg:col-span-4">
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm sticky top-24">
                    <h2 class="text-xl font-black text-slate-900 flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
                        <i class="fa-solid fa-comments text-amber-500"></i> Avis Clients
                    </h2>

                    <?php if (empty($recentReviews)): ?>
                        <div class="text-center py-8">
                            <i class="fa-regular fa-comment-dots text-4xl text-slate-200 mb-3 block"></i>
                            <p class="text-slate-500 text-sm font-medium">Aucun avis pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentReviews as $review): ?>
                                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($review['firstname']) ?></span>
                                        <div class="flex text-amber-400 text-[10px]">
                                            <?php for($i=1; $i<=5; $i++) { echo ($i <= $review['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-solid fa-star text-slate-200"></i>'; } ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($review['comment'])): ?>
                                        <p class="text-slate-600 text-xs leading-relaxed">"<?= nl2br(htmlspecialchars($review['comment'])) ?>"</p>
                                    <?php endif; ?>
                                    <p class="text-[9px] text-slate-400 mt-2 font-bold uppercase tracking-wider"><?= date('d M Y', strtotime($review['created_at'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/themes/default/templates/layouts/footer.php';
?>