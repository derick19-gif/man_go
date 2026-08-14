<?php
// listing-detail.php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

Session::init();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: listings.php');
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Récupération de l'annonce + catégorie + informations du vendeur
    $stmt = $db->prepare("
        SELECT 
            l.*, 
            c.name AS category_name,
            u.full_name AS seller_name,
            u.phone AS seller_account_phone,
            u.email AS seller_email,
            u.created_at AS seller_joined
        FROM listings l 
        LEFT JOIN categories c ON l.category_id = c.id 
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.id = :id AND l.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        header('Location: listings.php');
        exit;
    }

    // Récupération des annonces similaires (même catégorie)
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

} catch (Exception $e) {
    die("Erreur lors de la récupération de l'annonce : " . htmlspecialchars($e->getMessage()));
}

// Calcul de la réduction (Promo)
$discountPercent = 0;
if (!empty($listing['original_price']) && $listing['original_price'] > $listing['price']) {
    $discountPercent = round((($listing['original_price'] - $listing['price']) / $listing['original_price']) * 100);
}

// Numéro de contact : priorité au téléphone spécifique de l'annonce, sinon celui du profil
$raw_phone = !empty($listing['phone']) ? $listing['phone'] : ($listing['seller_account_phone'] ?? '');

// Nettoyage et formatage du numéro pour WhatsApp
$clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);
if (strlen($clean_phone) === 8) {
    $clean_phone = '228' . $clean_phone; // Par défaut Togo si 8 chiffres
}

$wa_message = rawurlencode("Bonjour " . ($listing['seller_name'] ?? '') . ", je suis intéressé par votre annonce : \"" . $listing['title'] . "\" vue sur MAN GO.");
$whatsapp_url = !empty($clean_phone) ? "https://wa.me/" . $clean_phone . "?text=" . $wa_message : "#";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($listing['title']) ?> - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 text-gray-800 font-sans min-h-screen flex flex-col">

    <!-- Header Navigation -->
    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-2">
                <span class="bg-amber-500 text-slate-900 font-black text-2xl w-10 h-10 rounded-full flex items-center justify-center">M</span>
                <span class="font-extrabold text-2xl tracking-wide">MAN <span class="text-amber-500">GO</span></span>
            </a>

            <nav class="hidden md:flex space-x-8 text-sm font-medium">
                <a href="index.php" class="hover:text-amber-500 transition">Accueil</a>
                <a href="listings.php" class="text-amber-500 hover:text-amber-400">Annonces</a>
                <a href="stands" class="hover:text-amber-500 transition">Boutiques & Stands</a>
                <a href="services.php" class="hover:text-amber-500 transition">Services</a>
            </nav>

            <div class="flex items-center space-x-4">
                <?php if (Session::get('user_id')): ?>
                    <a href="dashboard.php" class="text-sm font-medium hover:text-amber-500">Mon Compte</a>
                <?php else: ?>
                    <a href="login.php" class="text-sm font-medium hover:text-amber-500">Connexion</a>
                <?php endif; ?>

                <a href="publish.php" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-sm px-5 py-2.5 rounded-lg shadow transition">
                    <i class="fa-solid fa-plus-circle mr-1"></i> Publier une annonce
                </a>
            </div>
        </div>
    </header>

    <!-- Fil d'Ariane -->
    <div class="bg-white border-b border-gray-200 py-3">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-xs text-gray-500 flex items-center space-x-2">
            <a href="index.php" class="hover:text-amber-600">Accueil</a>
            <span>/</span>
            <a href="listings.php" class="hover:text-amber-600">Annonces</a>
            <span>/</span>
            <span class="text-gray-800 font-semibold truncate"><?= htmlspecialchars($listing['title']) ?></span>
        </div>
    </div>

    <!-- Contenu Principal -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-grow">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Section Visuelle et Description -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden relative">
                    <?php if ($discountPercent > 0): ?>
                        <span class="absolute top-4 right-4 bg-red-600 text-white text-xs font-black px-3 py-1.5 rounded-full shadow-md animate-pulse">
                            -<?= $discountPercent ?>% PROMO
                        </span>
                    <?php endif; ?>
                    <div class="bg-black/5 h-[350px] sm:h-[480px] flex items-center justify-center">
                        <img src="<?= htmlspecialchars(!empty($listing['image_url']) ? $listing['image_url'] : 'assets/images/placeholder.jpg') ?>" 
                             alt="<?= htmlspecialchars($listing['title']) ?>" 
                             class="max-h-full max-w-full object-contain">
                    </div>
                </div>

                <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-2.5 py-1 rounded-full">
                            <?= htmlspecialchars($listing['category_name'] ?: 'Général') ?>
                        </span>
                        <span class="text-xs text-gray-400">
                            <i class="fa-regular fa-clock mr-1"></i>Publié le <?= date('d/m/Y à H:i', strtotime($listing['created_at'])) ?>
                        </span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900"><?= htmlspecialchars($listing['title']) ?></h1>

                    <div class="flex items-center gap-2 text-gray-500 text-sm">
                        <i class="fa-solid fa-location-dot text-amber-500"></i>
                        <span><?= htmlspecialchars(!empty($listing['city']) ? $listing['city'] : ($listing['location'] ?? 'Lomé')) ?></span>
                    </div>

                    <hr class="border-gray-100">

                    <div>
                        <h2 class="text-lg font-bold text-gray-900 mb-2 flex items-center">
                            <i class="fa-solid fa-align-left text-amber-500 mr-2"></i> Description de l'offre
                        </h2>
                        <div class="text-gray-700 text-sm leading-relaxed whitespace-pre-line">
                            <?= htmlspecialchars($listing['description'] ?: 'Aucune description fournie.') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Prix, Vendeur et Contact -->
            <div class="space-y-6">
                
                <!-- Bloc Prix -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-4">
                    <span class="text-xs font-bold uppercase text-gray-400 tracking-wider">Prix de l'article</span>
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl font-black text-amber-600">
                            <?= number_format($listing['price'], 0, ',', ' ') ?> <?= htmlspecialchars($listing['currency'] ?? 'FCFA') ?>
                        </span>
                        <?php if ($discountPercent > 0): ?>
                            <span class="text-lg text-gray-400 line-through font-semibold">
                                <?= number_format($listing['original_price'], 0, ',', ' ') ?> <?= htmlspecialchars($listing['currency'] ?? 'FCFA') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informations Vendeur & Contact -->
                <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-5">
                    <h3 class="font-bold text-gray-900 text-sm border-b border-gray-100 pb-3 flex items-center">
                        <i class="fa-solid fa-user-check text-amber-500 mr-2"></i> Informations du vendeur
                    </h3>

                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-slate-900 text-amber-500 rounded-full font-bold text-lg flex items-center justify-center">
                            <?= strtoupper(substr($listing['seller_name'] ?? 'V', 0, 1)) ?>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($listing['seller_name'] ?? 'Vendeur MAN GO') ?></h4>
                            <p class="text-xs text-gray-500">Membre depuis <?= !empty($listing['seller_joined']) ? date('m/Y', strtotime($listing['seller_joined'])) : 'N/A' ?></p>
                        </div>
                    </div>

                    <div class="space-y-3 pt-2">
                        <?php if (!empty($clean_phone)): ?>
                            <a href="<?= $whatsapp_url ?>" target="_blank" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-xl shadow transition flex items-center justify-center space-x-2 text-sm">
                                <i class="fa-brands fa-whatsapp text-lg"></i>
                                <span>Discuter sur WhatsApp</span>
                            </a>

                            <a href="tel:+<?= htmlspecialchars($clean_phone) ?>" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 px-4 rounded-xl shadow transition flex items-center justify-center space-x-2 text-sm">
                                <i class="fa-solid fa-phone text-amber-500"></i>
                                <span>Appeler : <?= htmlspecialchars($raw_phone) ?></span>
                            </a>
                        <?php else: ?>
                            <div class="text-xs text-gray-400 text-center italic">Numéro de téléphone non spécifié</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Conseil de sécurité -->
                <div class="bg-slate-900 text-gray-300 p-5 rounded-2xl text-xs space-y-2">
                    <div class="font-bold text-amber-500 flex items-center space-x-2 text-sm">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Conseil de sécurité</span>
                    </div>
                    <p class="leading-relaxed">Ne payez jamais à l'avance. Inspectez le produit et remettez l'argent uniquement en mains propres dans un lieu public sécurisé.</p>
                </div>

            </div>
        </div>

        <!-- Section Annonces Similaires -->
        <?php if (!empty($similar_listings)): ?>
            <div class="mt-16">
                <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fa-solid fa-layer-group text-amber-500 mr-2"></i> Annonces similaires
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                    <?php foreach ($similar_listings as $sim): ?>
                        <a href="listing-detail.php?id=<?= $sim['id'] ?>" class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition flex flex-col justify-between">
                            <img src="<?= htmlspecialchars(!empty($sim['image_url']) ? $sim['image_url'] : 'assets/images/placeholder.jpg') ?>" class="w-full h-40 object-cover" alt="<?= htmlspecialchars($sim['title']) ?>">
                            <div class="p-4 flex-1 flex flex-col justify-between">
                                <h4 class="font-bold text-sm text-gray-900 truncate"><?= htmlspecialchars($sim['title']) ?></h4>
                                <div class="mt-2 flex justify-between items-baseline">
                                    <span class="font-extrabold text-amber-600 text-sm">
                                        <?= number_format($sim['price'], 0, ',', ' ') ?> <?= htmlspecialchars($sim['currency'] ?? 'FCFA') ?>
                                    </span>
                                    <?php if (!empty($sim['original_price']) && $sim['original_price'] > $sim['price']): ?>
                                        <span class="text-xs text-gray-400 line-through">
                                            <?= number_format($sim['original_price'], 0, ',', ' ') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-gray-400 py-8 mt-12 border-t border-slate-800 text-center text-xs">
        &copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.
    </footer>

</body>
</html>