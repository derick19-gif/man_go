<?php
// Charger la configuration et la session
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Session.php';
Session::init();

$db = getDBConnection();

// -------------------------------------------------------------------------
// 1. PARAM^TRES DE RECHERCHE, FILTRES ET PAGINATION
// -------------------------------------------------------------------------
$search      = trim($_GET['q'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$location    = trim($_GET['location'] ?? '');
$min_price   = filter_var($_GET['min_price'] ?? null, FILTER_VALIDATE_FLOAT);
$max_price   = filter_var($_GET['max_price'] ?? null, FILTER_VALIDATE_FLOAT);
$promo_only  = isset($_GET['promo']) && $_GET['promo'] === '1';
$sort        = trim($_GET['sort'] ?? 'newest');

// Pagination
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 12; // Nombre d'annonces par page
$offset = ($page - 1) * $limit;

// -------------------------------------------------------------------------
// 2. R?CUP?RATION DES DONN?ES POUR LES FILTRES (Catégories & Villes)
// -------------------------------------------------------------------------
try {
    // Liste des catégories
    $stmtCats = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
    $categoriesList = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

    // Liste distincte des localisations existantes
    $stmtLocs = $db->query("SELECT DISTINCT location FROM listings WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
    $locationsList = $stmtLocs->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categoriesList = [];
    $locationsList = [];
}

// -------------------------------------------------------------------------
// 3. CONSTRUCTION DE LA REQUSTE SQL DYNAMIQUE
// -------------------------------------------------------------------------
$where = ["l.status = 'active'"];
$params = [];

if (!empty($search)) {
    $where[] = "(l.title LIKE :search OR l.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($category_id > 0) {
    $where[] = "l.category_id = :category_id";
    $params[':category_id'] = $category_id;
}

if (!empty($location)) {
    $where[] = "l.location = :location";
    $params[':location'] = $location;
}

if ($min_price !== false && $min_price !== null && $min_price >= 0) {
    $where[] = "l.price >= :min_price";
    $params[':min_price'] = $min_price;
}

if ($max_price !== false && $max_price !== null && $max_price > 0) {
    $where[] = "l.price <= :max_price";
    $params[':max_price'] = $max_price;
}

if ($promo_only) {
    $where[] = "(l.original_price IS NOT NULL AND l.original_price > l.price)";
}

$whereSQL = implode(' AND ', $where);

// Tri des résultats
switch ($sort) {
    case 'price_asc':
        $orderBy = "l.price ASC, l.created_at DESC";
        break;
    case 'price_desc':
        $orderBy = "l.price DESC, l.created_at DESC";
        break;
    case 'promo':
        $orderBy = "(CASE WHEN l.original_price > l.price THEN (l.original_price - l.price) ELSE 0 END) DESC, l.created_at DESC";
        break;
    case 'oldest':
        $orderBy = "l.created_at ASC";
        break;
    case 'newest':
    default:
        $orderBy = "l.created_at DESC";
        break;
}

// -------------------------------------------------------------------------
// 4. COMPTAGE GLOBAL ET R?CUP?RATION DES ANNONCES
// -------------------------------------------------------------------------
$totalListings = 0;
$listings = [];

try {
    // Compter le total d'annonces correspondant aux critères
    $countSql = "SELECT COUNT(*) FROM listings l WHERE {$whereSQL}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalListings = (int)$countStmt->fetchColumn();

    $totalPages = ceil($totalListings / $limit);

    // Récupérer les annonces pour la page actuelle
    $sql = "SELECT l.*, c.name AS category_name, c.slug AS category_slug 
            FROM listings l 
            LEFT JOIN categories c ON l.category_id = c.id 
            WHERE {$whereSQL} 
            ORDER BY {$orderBy} 
            LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    
    // Binding manuel pour les entiers LIMIT et OFFSET
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $listings = [];
}

// Utilitaire pour reconstruire les URLs avec les filtres conservés
function buildUrl($extraParams = []) {
    $queryParams = $_GET;
    foreach ($extraParams as $key => $value) {
        if ($value === null) {
            unset($queryParams[$key]);
        } else {
            $queryParams[$key] = $value;
        }
    }
    return 'listings.php?' . http_build_query($queryParams);
}
?>
<!DOCTYPE html>
<html lang="<?= DEFAULT_LANG ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue des Annonces - <?= APP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <!-- Header / Navbar -->
    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-2">
                <span class="bg-amber-500 text-slate-900 font-black text-2xl w-10 h-10 rounded-full flex items-center justify-center">M</span>
                <span class="font-extrabold text-2xl tracking-wide">MAN <span class="text-amber-500">GO</span></span>
            </a>

            <nav class="hidden md:flex space-x-8 text-sm font-medium">
                <a href="index.php" class="hover:text-amber-500 transition">Accueil</a>
                <a href="listings.php" class="text-amber-500 font-bold border-b-2 border-amber-500 pb-1">Annonces</a>
                <a href="shops.php" class="hover:text-amber-500 transition">Boutiques & Stands</a>
                <a href="services.php" class="hover:text-amber-500 transition">Services</a>
            </nav>

            <div class="flex items-center space-x-4">
                <?php if (Session::get('user_id')): ?>
                    <a href="publish.php" class="bg-amber-500 hover:bg-amber-600 text-slate-950 px-4 py-2 rounded-xl text-sm font-bold transition flex items-center space-x-2">
                        <i class="fa-solid fa-plus"></i>
                        <span>Publier</span>
                    </a>
                    <a href="dashboard.php" class="text-sm font-medium hover:text-amber-500">Mon Compte</a>
                <?php else: ?>
                    <a href="login.php" class="text-sm font-medium hover:text-amber-500">Connexion</a>
                    <a href="publish.php" class="bg-amber-500 hover:bg-amber-600 text-slate-950 px-4 py-2 rounded-xl text-sm font-bold transition">Publier</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- En-tête / Recherche principale -->
    <section class="bg-slate-800 text-white py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-3xl font-extrabold mb-2">Trouvez tout ce dont vous avez besoin</h1>
            <p class="text-gray-300 text-sm mb-6">Parcourez des milliers d'annonces vérifiées partout au Togo et sous-région.</p>

            <form action="listings.php" method="GET" class="bg-white p-2 sm:p-3 rounded-2xl shadow-lg grid grid-cols-1 md:grid-cols-12 gap-3 text-gray-800">
                
                <!-- Recherche mot-clé -->
                <div class="md:col-span-5 flex items-center bg-gray-100 rounded-xl px-3 py-2">
                    <i class="fa-solid fa-magnifying-glass text-gray-400 mr-3"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Que recherchez-vous ? (ex: iPhone, Voiture, Studio...)" class="bg-transparent w-full focus:outline-none text-sm">
                </div>

                <!-- Sélecteur Catégorie -->
                <div class="md:col-span-3 flex items-center bg-gray-100 rounded-xl px-3 py-2">
                    <i class="fa-solid fa-layer-group text-gray-400 mr-3"></i>
                    <select name="category" class="bg-transparent w-full focus:outline-none text-sm cursor-pointer">
                        <option value="0">Toutes les catégories</option>
                        <?php foreach ($categoriesList as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sélecteur Localisation -->
                <div class="md:col-span-2 flex items-center bg-gray-100 rounded-xl px-3 py-2">
                    <i class="fa-solid fa-location-dot text-gray-400 mr-3"></i>
                    <select name="location" class="bg-transparent w-full focus:outline-none text-sm cursor-pointer">
                        <option value="">Toutes les villes</option>
                        <?php foreach ($locationsList as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>" <?= ($location === $loc) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Bouton Recherche -->
                <div class="md:col-span-2">
                    <button type="submit" class="w-full h-full bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold py-2.5 px-4 rounded-xl transition flex items-center justify-center space-x-2 text-sm">
                        <span>Rechercher</span>
                    </button>
                </div>

            </form>
        </div>
    </section>

    <!-- Zone principale : Filtres avancés (Gauche) + Résultats (Droite) -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full flex-1">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- PANNEAU FILTRES AVANC?S -->
            <aside class="lg:col-span-1">
                <form action="listings.php" method="GET" class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-6">
                    
                    <!-- Conserver le terme de recherche si présent -->
                    <?php if (!empty($search)): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>

                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <h2 class="font-bold text-gray-900 text-base flex items-center">
                            <i class="fa-solid fa-sliders text-amber-500 mr-2"></i> Filtres
                        </h2>
                        <a href="listings.php" class="text-xs text-amber-600 hover:underline">Réinitialiser</a>
                    </div>

                    <!-- Catégorie -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Catégorie</label>
                        <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="0">Toutes les catégories</option>
                            <?php foreach ($categoriesList as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Plage de Prix -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Prix (FCFA)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" name="min_price" value="<?= htmlspecialchars($min_price ?? '') ?>" placeholder="Min" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <input type="number" name="max_price" value="<?= htmlspecialchars($max_price ?? '') ?>" placeholder="Max" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                        </div>
                    </div>

                    <!-- Villes -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Localisation</label>
                        <select name="location" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="">Toutes les villes</option>
                            <?php foreach ($locationsList as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>" <?= ($location === $loc) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Case à cocher : En promotion uniquement -->
                    <div class="pt-2 border-t border-gray-100">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="promo" value="1" <?= $promo_only ? 'checked' : '' ?> class="w-4 h-4 text-amber-500 border-gray-300 rounded focus:ring-amber-500">
                            <span class="text-sm font-semibold text-gray-800">
                                <i class="fa-solid fa-bolt text-amber-500 mr-1"></i> Promotions uniquement
                            </span>
                        </label>
                    </div>

                    <!-- Tri -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Trier par</label>
                        <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Plus récents</option>
                            <option value="price_asc" <?= ($sort === 'price_asc') ? 'selected' : '' ?>>Prix croissant</option>
                            <option value="price_desc" <?= ($sort === 'price_desc') ? 'selected' : '' ?>>Prix décroissant</option>
                            <option value="promo" <?= ($sort === 'promo') ? 'selected' : '' ?>>Meilleures reductions</option>
                            <option value="oldest" <?= ($sort === 'oldest') ? 'selected' : '' ?>>Plus anciens</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl transition text-sm">
                        Appliquer les filtres
                    </button>

                </form>
            </aside>

            <!-- CONTENU / GRILLE DE R?SULTATS -->
            <section class="lg:col-span-3">
                
                <!-- Barre d'info résultats et tri rapide -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-white p-4 rounded-2xl border border-gray-200 mb-6 gap-4">
                    <div>
                        <span class="text-gray-500 text-sm">Résultats trouvés :</span>
                        <span class="font-extrabold text-gray-900 text-base ml-1"><?= $totalListings ?> annonce(s)</span>
                    </div>

                    <div class="flex items-center space-x-2 text-sm">
                        <span class="text-gray-500">Trier :</span>
                        <a href="<?= buildUrl(['sort' => 'newest', 'page' => 1]) ?>" class="px-3 py-1 rounded-lg text-xs font-semibold <?= ($sort === 'newest') ? 'bg-amber-500 text-slate-950' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Récents</a>
                        <a href="<?= buildUrl(['sort' => 'price_asc', 'page' => 1]) ?>" class="px-3 py-1 rounded-lg text-xs font-semibold <?= ($sort === 'price_asc') ? 'bg-amber-500 text-slate-950' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Prix <i class="fa-solid fa-arrow-up text-[10px]"></i></a>
                        <a href="<?= buildUrl(['sort' => 'price_desc', 'page' => 1]) ?>" class="px-3 py-1 rounded-lg text-xs font-semibold <?= ($sort === 'price_desc') ? 'bg-amber-500 text-slate-950' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Prix <i class="fa-solid fa-arrow-down text-[10px]"></i></a>
                    </div>
                </div>

                <!-- Grille d'Annonces -->
                <?php if (empty($listings)): ?>
                    <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                        <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                            <i class="fa-solid fa-folder-open"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-1">Aucune annonce trouvée</h3>
                        <p class="text-gray-500 text-sm mb-6">Essayez de modifier ou de réinitialiser vos filtres de recherche.</p>
                        <a href="listings.php" class="inline-flex items-center px-4 py-2 bg-slate-900 text-white font-bold rounded-xl text-sm hover:bg-slate-800 transition">
                            <i class="fa-solid fa-rotate-left mr-2"></i> Recommencer la recherche
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($listings as $item): ?>
                            <?php 
                                $hasDiscount = !empty($item['original_price']) && $item['original_price'] > $item['price'];
                                $discountPercent = 0;
                                if ($hasDiscount) {
                                    $discountPercent = round((($item['original_price'] - $item['price']) / $item['original_price']) * 100);
                                }
                            ?>
                            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition flex flex-col group">
                                
                                <!-- Image & Badges -->
                                <div class="relative aspect-video bg-gray-100 overflow-hidden">
                                    <img src="<?= htmlspecialchars($item['image_url'] ?? 'assets/images/placeholder.jpg') ?>" 
                                         alt="<?= htmlspecialchars($item['title']) ?>" 
                                         class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    
                                    <!-- Badge Catégorie -->
                                    <span class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-md text-white text-[11px] font-bold px-2.5 py-1 rounded-lg">
                                        <?= htmlspecialchars($item['category_name'] ?? 'Général') ?>
                                    </span>

                                    <!-- Badge Réduction Promo -->
                                    <?php if ($hasDiscount): ?>
                                        <span class="absolute top-3 right-3 bg-red-600 text-white text-[11px] font-black px-2.5 py-1 rounded-lg shadow-sm">
                                            -<?= $discountPercent ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Contenu -->
                                <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                                    <div>
                                        <div class="flex items-center text-xs text-gray-400 mb-1 space-x-2">
                                            <span><i class="fa-solid fa-location-dot text-amber-500 mr-1"></i><?= htmlspecialchars($item['location']) ?></span>
                                            <span>?</span>
                                            <span><?= date('d/m/Y', strtotime($item['created_at'])) ?></span>
                                        </div>
                                        <h3 class="font-bold text-gray-900 text-base line-clamp-2 hover:text-amber-600 transition">
                                            <a href="listing-detail.php?id=<?= $item['id'] ?>">
                                                <?= htmlspecialchars($item['title']) ?>
                                            </a>
                                        </h3>
                                    </div>

                                    <!-- Bloc Prix -->
                                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                                        <div>
                                            <div class="text-amber-600 font-black text-lg">
                                                <?= number_format($item['price'], 0, ',', ' ') ?> <span class="text-xs"><?= htmlspecialchars($item['currency']) ?></span>
                                            </div>
                                            <?php if ($hasDiscount): ?>
                                                <div class="text-xs text-gray-400 line-through">
                                                    <?= number_format($item['original_price'], 0, ',', ' ') ?> <?= htmlspecialchars($item['currency']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <a href="listing-detail.php?id=<?= $item['id'] ?>" class="w-9 h-9 rounded-xl bg-gray-100 group-hover:bg-amber-500 group-hover:text-slate-950 text-gray-600 flex items-center justify-center transition">
                                            <i class="fa-solid fa-arrow-right text-sm"></i>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- PAGINATION ILLIMIT?E -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mt-10 flex items-center justify-center space-x-2">
                            
                            <!-- Précédent -->
                            <?php if ($page > 1): ?>
                                <a href="<?= buildUrl(['page' => $page - 1]) ?>" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
                                    <i class="fa-solid fa-chevron-left mr-1"></i> Précédent
                                </a>
                            <?php endif; ?>

                            <!-- Numéros de pages -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="w-10 h-10 flex items-center justify-center bg-amber-500 text-slate-950 font-black rounded-xl text-sm">
                                        <?= $i ?>
                                    </span>
                                <?php elseif ($i == 1 || $i == $totalPages || abs($i - $page) <= 2): ?>
                                    <a href="<?= buildUrl(['page' => $i]) ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 text-gray-700 font-bold rounded-xl text-sm hover:bg-gray-50 transition">
                                        <?= $i ?>
                                    </a>
                                <?php elseif (abs($i - $page) == 3): ?>
                                    <span class="px-1 text-gray-400">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Suivant -->
                            <?php if ($page < $totalPages): ?>
                                <a href="<?= buildUrl(['page' => $page + 1]) ?>" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
                                    Suivant <i class="fa-solid fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>

                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </section>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-gray-400 py-8 mt-12 text-center text-xs border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 space-y-3">
            <p>&copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.</p>
            <p class="text-gray-500">Plateforme de mise en relation commerciale et de gestion de boutiques en ligne.</p>
        </div>
    </footer>

</body>
</html>