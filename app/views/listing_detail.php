<?php
// listing-detail.php

// Définition d'un chemin de base robuste vers la racine du projet
$projectRoot = dirname(dirname(__DIR__)); // Remonte de app/views à man_go

// Inclusions avec le chemin absolu
require_once $projectRoot . '/core/Database.php';
require_once $projectRoot . '/core/Session.php';

Session::init();

// Sécurité : On s'assure de récupérer l'ID depuis l'URL si le contrôleur l'a perdu
$id = $id ?? (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if (!$id || $id <= 0) { 
    header('Location: ' . (defined('APP_URL') ? APP_URL : '/man_go') . '/listings'); 
    exit; 
}

$db = \App\Core\Database::getInstance();
if (method_exists($db, 'getConnection')) {
    $db = $db->getConnection();
}

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$currentUserId = $_SESSION['user_id'] ?? null;
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

try {
    // CORRECTION MAJEURE : On remplace u.full_name par CONCAT(u.firstname, ' ', u.lastname)
    $stmt = $db->prepare("
        SELECT l.*, 
               c.name_key AS category_name, 
               CONCAT(u.firstname, ' ', u.lastname) AS seller_name, 
               u.phone AS seller_account_phone, 
               u.created_at AS seller_joined, 
               u.is_premium AS seller_is_premium
        FROM listings l 
        LEFT JOIN categories c ON l.category_id = c.id 
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.id = :id AND LOWER(l.status) = 'active'
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) { header('Location: listings.php'); exit; }

    $isOwner = ($currentUserId && $currentUserId == $listing['user_id']);
    $canModifyOrDelete = ($isOwner || $isAdmin);
    $sellerIsPremium = (bool)($listing['seller_is_premium'] ?? false);

    // LOGIQUE DE SUPPRESSION
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        if ($canModifyOrDelete) {
            $db->prepare("DELETE FROM listings WHERE id = :id")->execute([':id' => $id]);
            header("Location: $baseUrl/listings.php?msg=deleted");
            exit;
        }
    }

    $lang = $_SESSION['lang'] ?? 'fr';
    $categoryTranslations = [
        'fr' => ['cat_products' => 'Électronique & High-Tech', 'cat_stands' => 'Boutiques & Stands', 'cat_services' => 'Services Professionnels', 'cat_ads' => 'Immobilier & Véhicules'],
    ];
    $rawCatName = strtolower(trim($listing['category_name'] ?? ''));
    $translatedCategory = $categoryTranslations[$lang][$rawCatName] ?? 'Général';

    // MULTI-IMAGES AVEC CORRECTION DE CHEMIN ABSOLU
    $allImages = [];
    $imgPath = trim($listing['image_path'] ?? '');
    
    // CORRECTION : On utilise le chemin exact de la DB sans forcer "uploads/"
    if (!empty($imgPath) && $imgPath !== 'assets/images/placeholder.jpg') {
        $allImages[] = rtrim($baseUrl, '/') . '/' . ltrim($imgPath, '/');
    }
    
    try {
        $stmtImg = $db->prepare("SELECT image_path FROM listing_images WHERE listing_id = :id ORDER BY created_at ASC");
        $stmtImg->execute([':id' => $id]);
        while ($row = $stmtImg->fetch(PDO::FETCH_ASSOC)) {
            $subImgPath = trim($row['image_path'] ?? '');
            if (!empty($subImgPath)) {
                $allImages[] = rtrim($baseUrl, '/') . '/' . ltrim($subImgPath, '/');
            }
        }
    } catch(Exception $e) {}
    
    // Fallback propre en cas d'absence totale d'image
    if (empty($allImages)) {
        $allImages[] = rtrim($baseUrl, '/') . '/assets/images/default-product.jpg';
    }

    $vendorName = trim($listing['seller_name'] ?? 'Vendeur MAN GO');
    $vendorInitial = strtoupper(substr($vendorName, 0, 1));
    $datePublished = date('d/m/Y', strtotime($listing['created_at']));
    $clean_phone = preg_replace('/[^0-9]/', '', $listing['phone'] ?? $listing['seller_account_phone'] ?? '');

    // Génération du lien de l'annonce pour le partage
    $currentListingUrl = urlencode("http://localhost$baseUrl/listing_detail?id=" . $listing['id']);
    $listingTitleUrl = urlencode($listing['title']);

} catch (Exception $e) { die("Erreur : " . htmlspecialchars($e->getMessage())); }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($listing['title']) ?> - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50">
<?php 
$headerPath = $projectRoot . '/themes/default/templates/layouts/header.php';
if (file_exists($headerPath)) {
    require_once $headerPath;
} else {
    // Fallback au cas où l'arborescence est différente
    $headerFallback = $projectRoot . '/app/views/layouts/header.php';
    if(file_exists($headerFallback)) require_once $headerFallback;
}
?>

<div class="bg-slate-50 min-h-screen py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <nav class="text-sm font-medium text-slate-500 mb-6 flex items-center space-x-2">
            <a href="<?= $baseUrl ?>/" class="hover:text-amber-500 transition"><i class="fa-solid fa-house mr-1"></i> Accueil</a>
            <i class="fa-solid fa-chevron-right text-[10px]"></i>
            <span class="text-slate-800 truncate"><?= htmlspecialchars($listing['title']) ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white rounded-3xl border border-slate-200 p-4 shadow-sm">
                    <div class="w-full h-80 sm:h-[500px] bg-slate-100 rounded-2xl overflow-hidden relative mb-4 flex items-center justify-center">
                        <!-- CORRECTION : Remplacement du onerror d'Unsplash par l'image par défaut locale -->
                        <img id="mainGalleryImage" src="<?= htmlspecialchars($allImages[0]) ?>" class="w-full h-full object-contain transition duration-300" onerror="this.src='<?= rtrim($baseUrl, '/') ?>/assets/images/default-product.jpg'">
                    </div>
                    <?php if(count($allImages) > 1): ?>
                    <div class="flex space-x-3 overflow-x-auto pb-2">
                        <?php foreach($allImages as $imgUrl): ?>
                            <div class="flex-shrink-0 w-24 h-24 rounded-xl overflow-hidden cursor-pointer border-2 hover:border-amber-500 bg-slate-100 flex items-center justify-center" onclick="document.getElementById('mainGalleryImage').src='<?= htmlspecialchars($imgUrl) ?>'">
                                <!-- CORRECTION : onerror local pour la galerie -->
                                <img src="<?= htmlspecialchars($imgUrl) ?>" class="w-full h-full object-cover" onerror="this.src='<?= rtrim($baseUrl, '/') ?>/assets/images/default-product.jpg'">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm">
                    <h2 class="text-2xl font-black text-slate-900 mb-6 border-b border-slate-100 pb-4">Description complète</h2>
                    <div class="prose prose-slate max-w-none whitespace-pre-line text-slate-600 leading-relaxed"><?= htmlspecialchars($listing['description']) ?></div>
                </div>
            </div>

            <div class="space-y-6">
                <!-- PRIX ET ACTIONS -->
                <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm text-center">
                    <h1 class="text-2xl font-black text-slate-900 mb-4"><?= htmlspecialchars($listing['title']) ?></h1>
                    <p class="text-4xl font-black text-amber-500 mb-2"><?= number_format($listing['price'], 0, ',', ' ') ?> <span class="text-xl"><?= htmlspecialchars($listing['currency'] ?? 'FCFA') ?></span></p>
                    <?php if(!empty($listing['original_price']) && $listing['original_price'] > $listing['price']): ?>
                        <p class="text-lg text-slate-400 line-through font-bold mb-4"><?= number_format($listing['original_price'], 0, ',', ' ') ?> <?= htmlspecialchars($listing['currency'] ?? 'FCFA') ?></p>
                    <?php endif; ?>

                    <?php if($canModifyOrDelete): ?>
                        <a href="<?= $baseUrl ?>/publish.php?id=<?= $listing['id'] ?>" class="w-full block bg-amber-100 text-amber-800 font-black py-3 rounded-xl mb-3 hover:bg-amber-200 transition-colors"><i class="fa-solid fa-pen-to-square"></i> Modifier l'annonce</a>
                        
                        <!-- MODULE PARTAGE RÉSEAUX SOCIAUX (PREMIUM) -->
                        <?php if($sellerIsPremium): ?>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <a href="https://api.whatsapp.com/send?text=Découvrez mon annonce <?= $listingTitleUrl ?> sur MAN GO : <?= $currentListingUrl ?>" target="_blank" class="bg-[#25D366] text-white font-bold py-2 rounded-xl text-sm"><i class="fa-brands fa-whatsapp"></i> Partager</a>
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $currentListingUrl ?>" target="_blank" class="bg-[#1877F2] text-white font-bold py-2 rounded-xl text-sm"><i class="fa-brands fa-facebook"></i> Partager</a>
                            </div>
                        <?php else: ?>
                            <div class="bg-slate-50 border border-slate-200 p-3 rounded-xl mb-3 text-xs text-slate-500">
                                <i class="fa-solid fa-lock text-amber-500 mb-1"></i><br>
                                <a href="#" class="font-bold text-amber-600 hover:underline">Passez Premium</a> pour partager en 1 clic sur vos réseaux sociaux.
                            </div>
                        <?php endif; ?>

                        <form method="POST" onsubmit="return confirm('Supprimer définitivement cette annonce ?');">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="w-full bg-red-50 text-red-600 font-bold py-3 rounded-xl text-sm"><i class="fa-solid fa-trash"></i> Supprimer</button>
                        </form>
                    <?php else: ?>
                        <a href="<?= $baseUrl ?>/chat.php?vendor_id=<?= $listing['user_id'] ?>&listing_id=<?= $listing['id'] ?>" class="w-full block bg-slate-900 text-white font-bold py-4 rounded-xl hover:bg-slate-800 transition-colors"><i class="fa-solid fa-message"></i> Contacter le vendeur</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>