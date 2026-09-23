<?php
// =========================================================================
// PAGE DÉTAIL D'UNE ANNONCE - listing-detail.php
// =========================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';

// Initialisation propre de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (class_exists('Session') && method_exists('Session', 'init')) {
    Session::init();
}

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$currentUserId = $_SESSION['user_id'] ?? (class_exists('Session') ? Session::get('user_id') : null);
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    header("Location: $baseUrl/listings.php");
    exit;
}

try {
    $db = \App\Core\Database::connect();

    $stmt = $db->prepare("
        SELECT 
            l.*, 
            c.name_key AS category_name,
            CONCAT(u.firstname, ' ', u.lastname) AS seller_name,
            u.phone AS seller_account_phone,
            u.email AS seller_email,
            u.created_at AS seller_joined,
            u.is_premium AS seller_is_premium
        FROM listings l 
        LEFT JOIN categories c ON l.category_id = c.id 
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.id = :id AND l.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        header("Location: $baseUrl/listings.php");
        exit;
    }

    if (empty(trim($listing['seller_name']))) {
        $listing['seller_name'] = !empty($listing['seller_email']) ? explode('@', $listing['seller_email'])[0] : 'Vendeur MAN GO';
    }

    $isOwner = ($currentUserId && $currentUserId == $listing['user_id']);
    $canModifyOrDelete = ($isOwner || $isAdmin);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        if ($canModifyOrDelete) {
            $db->prepare("DELETE FROM listings WHERE id = :id")->execute([':id' => $id]);
            header("Location: $baseUrl/vendor_dir/dashboard.php?tab=tab-listings&msg=deleted");
            exit;
        }
    }

    if (!$isOwner) {
        try {
            $stmtView = $db->prepare("INSERT INTO ad_views (listing_id, stand_id, country, city, created_at) VALUES (?, ?, 'Togo', 'Lomé', NOW())");
            $stmtView->execute([$listing['id'], $listing['stand_id'] ?? 0]);
        } catch(Exception $e) {} 
    }

    $similar_listings = [];
    if (!empty($listing['category_id'])) {
        $stmtSimilar = $db->prepare("
            SELECT * FROM listings 
            WHERE category_id = :cat_id AND id != :current_id AND status = 'active' 
            ORDER BY created_at DESC LIMIT 4
        ");
        $stmtSimilar->execute([
            ':cat_id' => $listing['category_id'],
            ':current_id' => $listing['id']
        ]);
        $similar_listings = $stmtSimilar->fetchAll(PDO::FETCH_ASSOC);
    }

    $allImages = [];
    $imgPath = trim($listing['image_path'] ?? '');
    if (!empty($imgPath)) $allImages[] = $baseUrl . '/' . ltrim($imgPath, '/');

    try {
        $stmtImg = $db->prepare("SELECT image_path FROM listing_images WHERE listing_id = :id ORDER BY created_at ASC");
        $stmtImg->execute([':id' => $id]);
        while ($row = $stmtImg->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['image_path'])) $allImages[] = $baseUrl . '/' . ltrim($row['image_path'], '/');
        }
    } catch(Exception $e) {}

    if (empty($allImages)) $allImages[] = 'https://via.placeholder.com/800x600/1e293b/f59e0b?text=Image+Non+Disponible';

} catch (Exception $e) {
    die("Erreur : " . htmlspecialchars($e->getMessage()));
}

$discountPercent = 0;
if (!empty($listing['original_price']) && $listing['original_price'] > $listing['price']) {
    $discountPercent = round((($listing['original_price'] - $listing['price']) / $listing['original_price']) * 100);
}

$raw_phone = !empty($listing['phone']) ? $listing['phone'] : ($listing['seller_account_phone'] ?? '');
$clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);
if (strlen($clean_phone) === 8) {
    $clean_phone = '228' . $clean_phone; 
}

$wa_message = rawurlencode("Bonjour, je suis intéressé par votre annonce : \"" . $listing['title'] . "\" vue sur MAN GO.");
$whatsapp_url = !empty($clean_phone) ? "https://wa.me/" . $clean_phone . "?text=" . $wa_message : "#";
$listingTitleUrl = urlencode($listing['title']);
$currentListingUrl = urlencode("http://localhost$baseUrl/listing-detail.php?id=" . $listing['id']);

$pageTitle = $listing['title'] . " - MAN GO";

// ON FORCE LE BON HEADER EN LUI PASSANT LES BONNES VARIABLES
$_SESSION['user_id'] = $currentUserId; 
$_SESSION['user_role'] = $_SESSION['user_role'] ?? ($isAdmin ? 'admin' : ($isOwner ? 'vendor' : 'buyer'));
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-white border-b border-slate-200 py-3 mt-0">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-xs text-slate-500 flex items-center space-x-2">
        <a href="<?= $baseUrl ?>/" class="hover:text-amber-600 transition">Accueil</a>
        <span>/</span>
        <a href="<?= $baseUrl ?>/listings.php" class="hover:text-amber-600 transition">Annonces</a>
        <span>/</span>
        <span class="text-slate-800 font-bold truncate"><?= htmlspecialchars($listing['title']) ?></span>
    </div>
</div>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-grow mb-12">
    
    <?php if ($isOwner): ?>
        <div class="bg-amber-100 border border-amber-300 text-amber-900 px-6 py-4 rounded-2xl mb-8 flex flex-col sm:flex-row items-center justify-between shadow-sm">
            <div class="flex items-center mb-3 sm:mb-0">
                <i class="fa-solid fa-star text-amber-600 text-2xl mr-4"></i>
                <div>
                    <h4 class="font-black text-lg">C'est votre annonce !</h4>
                    <p class="text-sm">Voici comment les clients voient votre produit sur le marché.</p>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="<?= $baseUrl ?>/publish.php?id=<?= $listing['id'] ?>" class="bg-white text-amber-700 hover:bg-amber-50 font-bold py-2 px-5 rounded-full shadow-sm text-sm border border-amber-200 transition">
                    <i class="fa-solid fa-pen mr-1"></i> Modifier
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4">
                <div class="w-full h-[350px] sm:h-[500px] bg-slate-100 rounded-2xl overflow-hidden relative mb-4 flex items-center justify-center">
                    <?php if ($discountPercent > 0): ?>
                        <span class="absolute top-4 right-4 bg-red-600 text-white text-xs font-black px-3 py-1.5 rounded-full shadow-md z-10">
                            -<?= $discountPercent ?>% PROMO
                        </span>
                    <?php endif; ?>
                    <img id="mainGalleryImage" src="<?= htmlspecialchars($allImages[0]) ?>" class="max-h-full max-w-full object-contain transition duration-300">
                </div>
                
                <?php if(count($allImages) > 1): ?>
                <div class="flex space-x-3 overflow-x-auto pb-2 hide-scrollbar flex-nowrap" style="cursor: grab;">
                    <?php foreach($allImages as $imgUrl): ?>
                        <div class="flex-shrink-0 w-24 h-24 rounded-xl overflow-hidden cursor-pointer border-2 border-transparent hover:border-amber-500 transition" onclick="document.getElementById('mainGalleryImage').src='<?= htmlspecialchars($imgUrl) ?>'">
                            <img src="<?= htmlspecialchars($imgUrl) ?>" class="w-full h-full object-cover">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200 space-y-6">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="bg-slate-900 text-amber-500 text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-full">
                        <?= htmlspecialchars($listing['category_name'] ?: 'Général') ?>
                    </span>
                    <span class="text-xs font-bold text-slate-400 flex items-center">
                        <i class="fa-regular fa-clock mr-1"></i> <?= date('d/m/Y', strtotime($listing['created_at'])) ?>
                    </span>
                    <span class="text-xs font-bold text-slate-400 flex items-center">
                        <i class="fa-solid fa-location-dot mr-1"></i> <?= htmlspecialchars(!empty($listing['city']) ? $listing['city'] : ($listing['location'] ?? 'Global')) ?>
                    </span>
                </div>

                <h1 class="text-3xl sm:text-4xl font-black text-slate-900 leading-tight"><?= htmlspecialchars($listing['title']) ?></h1>

                <hr class="border-slate-100">

                <div>
                    <h2 class="text-xl font-bold text-slate-900 mb-4 flex items-center">
                        <i class="fa-solid fa-align-left text-amber-500 mr-2"></i> Détails
                    </h2>
                    <div class="text-slate-600 text-base leading-relaxed whitespace-pre-line">
                        <?= !empty($listing['description']) ? nl2br(htmlspecialchars($listing['description'])) : '<span class="italic text-slate-400">Aucune description fournie par le vendeur.</span>' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            
            <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200 text-center">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider block mb-2">Prix de vente</span>
                <div class="flex items-center justify-center gap-3 mb-6">
                    <span class="text-4xl font-black text-amber-500">
                        <?= number_format($listing['price'], 0, ',', ' ') ?> <span class="text-2xl"><?= htmlspecialchars($listing['currency'] ?? 'FCFA') ?></span>
                    </span>
                    <?php if ($discountPercent > 0): ?>
                        <span class="text-lg text-slate-300 line-through font-bold">
                            <?= number_format($listing['original_price'], 0, ',', ' ') ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($isOwner): ?>
                    <div class="bg-slate-50 border border-slate-100 p-4 rounded-2xl mb-4">
                        <p class="text-sm font-bold text-slate-600 mb-3">Gérer mon annonce</p>
                        <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette annonce ?');">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="w-full bg-red-50 hover:bg-red-100 text-red-600 font-bold py-3 rounded-xl transition flex items-center justify-center">
                                <i class="fa-solid fa-trash mr-2"></i> Supprimer
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-2 gap-2 mt-4">
                    <a href="https://api.whatsapp.com/send?text=Découvrez l'annonce <?= $listingTitleUrl ?> sur MAN GO : <?= $currentListingUrl ?>" target="_blank" class="bg-slate-100 hover:bg-[#25D366] hover:text-white text-slate-600 font-bold py-2 rounded-xl text-xs transition flex items-center justify-center">
                        <i class="fa-brands fa-whatsapp mr-1 text-lg"></i> Partager
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $currentListingUrl ?>" target="_blank" class="bg-slate-100 hover:bg-[#1877F2] hover:text-white text-slate-600 font-bold py-2 rounded-xl text-xs transition flex items-center justify-center">
                        <i class="fa-brands fa-facebook mr-1 text-lg"></i> Partager
                    </a>
                </div>
            </div>

            <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm space-y-6">
                <h3 class="font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center">
                    <i class="fa-solid fa-store text-amber-500 mr-2"></i> Le Vendeur
                </h3>

                <div class="flex items-center space-x-4">
                    <div class="w-14 h-14 bg-slate-900 text-amber-500 rounded-2xl font-black text-xl flex items-center justify-center shadow-sm">
                        <?= strtoupper(substr($listing['seller_name'] ?? 'V', 0, 1)) ?>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 text-lg"><?= htmlspecialchars($listing['seller_name']) ?></h4>
                        <p class="text-xs font-bold text-slate-400">Membre depuis <?= !empty($listing['seller_joined']) ? date('Y', strtotime($listing['seller_joined'])) : 'N/A' ?></p>
                        <?php if(!empty($listing['seller_is_premium'])): ?>
                            <span class="inline-flex mt-1 bg-amber-100 text-amber-700 text-[10px] font-black px-2 py-0.5 rounded-full uppercase"><i class="fa-solid fa-crown mr-1"></i> PRO</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$isOwner): ?>
                    <div class="space-y-3 pt-4">
                        <!-- BOUTON : CHAT INTERNE MAN GO SHIELD (Lien Absolu Sécurisé) -->
                            <a href="/man_go/chat.php?vendor_id=<?= $listing['user_id'] ?>" 
                               class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 px-4 rounded-xl shadow-lg transition flex items-center justify-center space-x-2 text-sm transform hover:-translate-y-0.5">
                                <i class="fa-solid fa-message text-amber-500 text-xl"></i>
                                <span>Discuter sur MAN GO</span>
                            </a>

                        <?php if (!empty($clean_phone)): ?>
                            <a href="<?= $whatsapp_url ?>" target="_blank" class="w-full bg-[#25D366] hover:bg-[#20bd5a] text-white font-bold py-4 px-4 rounded-xl shadow transition flex items-center justify-center space-x-2 text-sm">
                                <i class="fa-brands fa-whatsapp text-xl"></i>
                                <span>WhatsApp Direct</span>
                            </a>
                            <a href="tel:+<?= htmlspecialchars($clean_phone) ?>" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold py-4 px-4 rounded-xl shadow transition flex items-center justify-center space-x-2 text-sm">
                                <i class="fa-solid fa-phone text-slate-500"></i>
                                <span>Appeler le vendeur</span>
                            </a>
                        <?php else: ?>
                            <div class="bg-slate-50 p-4 rounded-xl text-sm text-slate-500 text-center font-bold border border-slate-100 mt-2">
                                <i class="fa-solid fa-phone-slash mb-2 text-lg"></i><br>Téléphone masqué
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-slate-900 text-slate-300 p-6 rounded-3xl text-xs space-y-3 shadow-lg">
                <div class="font-black text-amber-500 flex items-center space-x-2 text-sm uppercase tracking-wider">
                    <i class="fa-solid fa-shield-halved text-lg"></i>
                    <span>Sécurité MAN GO</span>
                </div>
                <p class="leading-relaxed">Ne payez jamais à l'avance. Inspectez le produit et remettez l'argent uniquement en mains propres dans un lieu public.</p>
            </div>
        </div>
    </div>

    <?php if (!empty($similar_listings)): ?>
        <div class="mt-20">
            <h2 class="text-2xl font-black text-slate-900 mb-8 flex items-center">
                <i class="fa-solid fa-layer-group text-amber-500 mr-3"></i> Dans la même catégorie
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($similar_listings as $sim): ?>
                    <a href="listing-detail.php?id=<?= $sim['id'] ?>" class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group transform hover:-translate-y-1">
                        <div class="h-48 overflow-hidden bg-slate-100">
                            <img src="<?= htmlspecialchars(!empty($sim['image_path']) ? $baseUrl.'/'.$sim['image_path'] : $baseUrl.'/assets/images/placeholder.jpg') ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500" alt="<?= htmlspecialchars($sim['title']) ?>">
                        </div>
                        <div class="p-5 flex-1 flex flex-col justify-between">
                            <h4 class="font-bold text-slate-900 line-clamp-2 group-hover:text-amber-500 transition"><?= htmlspecialchars($sim['title']) ?></h4>
                            <div class="mt-4 pt-4 border-t border-slate-100 flex justify-between items-center">
                                <span class="font-black text-amber-600 text-lg">
                                    <?= number_format($sim['price'], 0, ',', ' ') ?> <span class="text-sm"><?= htmlspecialchars($sim['currency'] ?? 'FCFA') ?></span>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
require_once __DIR__ . '/themes/default/templates/layouts/footer.php';
?>