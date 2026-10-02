<?php
// =========================================================================
// MOTEUR DE RECHERCHE & LISTING MAN GO (Produits, Biens & Annonces)
// =========================================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Database.php';
use App\Core\Database;

Session::init();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

$dbInstance = Database::getInstance();
$db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;

// 1. PARAMÈTRES DE RECHERCHE
$search      = trim($_GET['q'] ?? '');
$location    = trim($_GET['location'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$min_price   = filter_var($_GET['min_price'] ?? null, FILTER_VALIDATE_FLOAT);
$max_price   = filter_var($_GET['max_price'] ?? null, FILTER_VALIDATE_FLOAT);
$promo_only  = isset($_GET['promo']) && $_GET['promo'] === '1';
$sort        = trim($_GET['sort'] ?? 'newest');

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 12; 
$offset = ($page - 1) * $limit;

// =========================================================================
// 2. CHARGEMENT DE L'ARBRE DES CATÉGORIES (Parents + Enfants)
// =========================================================================
$categoriesTree = [];
$locationsList = [];
try {
    // Les catégories
    $stmtCats = $db->query("SELECT id, name_key AS name, parent_id FROM categories ORDER BY name_key ASC");
    $allCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($allCats as $cat) {
        if (empty($cat['parent_id'])) {
            $categoriesTree[$cat['id']] = ['id' => $cat['id'], 'name' => $cat['name'], 'subcategories' => []];
        }
    }
    foreach($allCats as $cat) {
        if (!empty($cat['parent_id']) && isset($categoriesTree[$cat['parent_id']])) {
            $categoriesTree[$cat['parent_id']]['subcategories'][] = $cat;
        }
    }
    usort($categoriesTree, function($a, $b) { return strcmp($a['name'], $b['name']); });

    // Les Villes
    $stmtLocs = $db->query("SELECT DISTINCT location FROM listings WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
    $locationsList = $stmtLocs->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// =========================================================================
// 3. CONSTRUCTION DE LA REQUÊTE SQL (Moteur)
// =========================================================================
$where = ["l.status = 'active'"];
$params = [];

if (!empty($search)) {
    $where[] = "(l.title LIKE :search OR l.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($location)) {
    $where[] = "(l.location LIKE :location OR l.city LIKE :location OR l.country LIKE :location)";
    $params[':location'] = '%' . $location . '%';
}
if ($category_id > 0) {
    // ASTUCE PARENT/ENFANT : Cherche la catégorie OU ses sous-catégories
    $where[] = "(l.category_id = :cat_id OR l.category_id IN (SELECT id FROM categories WHERE parent_id = :cat_parent))";
    $params[':cat_id'] = $category_id;
    $params[':cat_parent'] = $category_id;
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

switch ($sort) {
    case 'price_asc': $orderBy = "l.price ASC, l.created_at DESC"; break;
    case 'price_desc': $orderBy = "l.price DESC, l.created_at DESC"; break;
    case 'promo': $orderBy = "(CASE WHEN l.original_price > l.price THEN (l.original_price - l.price) ELSE 0 END) DESC, l.created_at DESC"; break;
    case 'oldest': $orderBy = "l.created_at ASC"; break;
    case 'newest': default: $orderBy = "l.created_at DESC"; break;
}

// Récupération des annonces
$totalListings = 0;
$listings = [];
try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM listings l WHERE {$whereSQL}");
    $countStmt->execute($params);
    $totalListings = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalListings / $limit));

    $sql = "SELECT l.*, c.name_key AS category_name 
            FROM listings l 
            LEFT JOIN categories c ON l.category_id = c.id 
            WHERE {$whereSQL} 
            ORDER BY {$orderBy} 
            LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

function buildUrl($extraParams = []) {
    $queryParams = $_GET;
    foreach ($extraParams as $key => $value) {
        if ($value === null) unset($queryParams[$key]);
        else $queryParams[$key] = $value;
    }
    return 'listings.php?' . http_build_query($queryParams);
}

require_once __DIR__ . '/app/views/layouts/header.php';
?>

<!-- En-tête / Recherche principale -->
<section class="bg-slate-900 text-white py-10 px-4 sm:px-6 lg:px-8 border-b border-slate-800">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-extrabold mb-2 text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400">Le monde à portée de clic</h1>
        <p class="text-gray-400 text-sm mb-8 font-medium">Parcourez des milliers d'opportunités de biens, de produits et de services.</p>

        <!-- FORMULAIRE HAUT DE PAGE (AVEC OPTGROUP) -->
        <form action="listings.php" method="GET" class="bg-slate-800/80 p-3 rounded-2xl border border-slate-700 shadow-2xl grid grid-cols-1 md:grid-cols-12 gap-3 backdrop-blur-sm">
            
            <div class="md:col-span-5 flex items-center bg-slate-950 rounded-xl px-4 py-3 border border-slate-700/50 focus-within:border-amber-500 transition-colors">
                <i class="fa-solid fa-magnifying-glass text-amber-500 mr-3"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Produit, marque, tag..." class="bg-transparent w-full focus:outline-none text-sm text-white placeholder-gray-500">
            </div>

            <!-- Catégories Arborescentes -->
            <div class="md:col-span-3 flex items-center bg-slate-950 rounded-xl px-4 py-3 border border-slate-700/50 focus-within:border-amber-500 transition-colors">
                <i class="fa-solid fa-layer-group text-amber-500 mr-3"></i>
                <select name="category" class="bg-transparent w-full focus:outline-none text-sm text-gray-300 cursor-pointer appearance-none">
                    <option value="0" class="text-gray-900 font-bold">Toutes les catégories</option>
                    <?php foreach ($categoriesTree as $parent): ?>
                        <optgroup label="■ <?= htmlspecialchars($parent['name']) ?>" class="text-slate-900 bg-slate-200">
                            <option value="<?= $parent['id'] ?>" <?= ($category_id == $parent['id']) ? 'selected' : '' ?> class="text-slate-900 font-bold bg-white">
                                ▶ TOUT : <?= htmlspecialchars($parent['name']) ?>
                            </option>
                            <?php foreach ($parent['subcategories'] as $sub): ?>
                                <option value="<?= $sub['id'] ?>" <?= ($category_id == $sub['id']) ? 'selected' : '' ?> class="text-slate-700 bg-white">
                                    &nbsp;&nbsp;&nbsp;↳ <?= htmlspecialchars($sub['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="md:col-span-2 flex items-center bg-slate-950 rounded-xl px-4 py-3 border border-slate-700/50 focus-within:border-amber-500 transition-colors">
                <i class="fa-solid fa-location-dot text-amber-500 mr-3"></i>
                <input type="text" name="location" value="<?= htmlspecialchars($location) ?>" placeholder="Ville, Pays..." class="bg-transparent w-full focus:outline-none text-sm text-white placeholder-gray-500">
            </div>

            <div class="md:col-span-2">
                <button type="submit" class="w-full h-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black py-3 px-4 rounded-xl transition-all flex items-center justify-center space-x-2 text-sm">
                    <span>Explorer</span>
                </button>
            </div>
        </form>
    </div>
</section>

<!-- Zone principale : Filtres avancés + Résultats -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full flex-1">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <!-- PANNEAU FILTRES AVANCÉS (GAUCHE) -->
        <aside class="lg:col-span-1">
            <form action="listings.php" method="GET" class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-6 sticky top-28">
                
                <?php if (!empty($search)): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                <?php if (!empty($location)): ?><input type="hidden" name="location" value="<?= htmlspecialchars($location) ?>"><?php endif; ?>

                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <h2 class="font-black text-slate-900 text-base flex items-center">
                        <i class="fa-solid fa-sliders text-amber-500 mr-2"></i> Affiner
                    </h2>
                    <a href="listings.php" class="text-xs text-amber-600 hover:text-amber-700 hover:underline font-bold">Réinitialiser</a>
                </div>

                <!-- Sélecteur Catégorie Latéral avec Infobulle -->
                <div>
                    <label class="flex items-center text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">
                        Domaine / Type
                        <div class="group relative inline-block ml-2">
                            <i class="fa-solid fa-circle-info text-slate-300 hover:text-amber-500 cursor-help transition text-xs"></i>
                            <div class="opacity-0 w-48 bg-slate-800 text-white text-[10px] font-normal normal-case tracking-normal rounded-lg py-2 px-3 absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-2 pointer-events-none group-hover:opacity-100 transition-opacity duration-300 shadow-xl text-center">
                                Filtrez par grande famille ou par spécialité précise.
                            </div>
                        </div>
                    </label>
                    <select name="category" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition cursor-pointer">
                        <option value="0" class="font-black text-slate-900">Toutes les catégories</option>
                        <?php foreach ($categoriesTree as $parent): ?>
                            <optgroup label="■ <?= htmlspecialchars($parent['name']) ?>">
                                <option value="<?= $parent['id'] ?>" <?= ($category_id == $parent['id']) ? 'selected' : '' ?> class="font-black text-slate-900">
                                    ▶ TOUT : <?= htmlspecialchars($parent['name']) ?>
                                </option>
                                <?php foreach ($parent['subcategories'] as $sub): ?>
                                    <option value="<?= $sub['id'] ?>" <?= ($category_id == $sub['id']) ? 'selected' : '' ?> class="font-semibold text-slate-600">
                                        &nbsp;&nbsp;&nbsp;↳ <?= htmlspecialchars($sub['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Budget avec Contraste parfait, Sécurité Numérique et Infobulle -->
                <div>
                    <label class="flex items-center text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">
                        Budget (<?= htmlspecialchars($_SESSION['user_currency'] ?? 'FCFA') ?>)
                        <div class="group relative inline-block ml-2">
                            <i class="fa-solid fa-circle-info text-slate-300 hover:text-amber-500 cursor-help transition text-xs"></i>
                            <div class="opacity-0 w-48 bg-slate-800 text-white text-[10px] font-normal normal-case tracking-normal rounded-lg py-2 px-3 absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-2 pointer-events-none group-hover:opacity-100 transition-opacity duration-300 shadow-xl text-center">
                                Saisissez uniquement des chiffres (sans virgules ni lettres).
                            </div>
                        </div>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" min="0" step="1" oninput="this.value = this.value.replace(/[^0-9]/g, '');" name="min_price" value="<?= htmlspecialchars($min_price ?? '') ?>" placeholder="Min" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition">
                        <input type="number" min="0" step="1" oninput="this.value = this.value.replace(/[^0-9]/g, '');" name="max_price" value="<?= htmlspecialchars($max_price ?? '') ?>" placeholder="Max" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition">
                    </div>
                </div>

                <!-- Promo avec Infobulle -->
                <div class="pt-4 border-t border-slate-100">
                    <label class="flex items-center space-x-3 cursor-pointer group">
                        <input type="checkbox" name="promo" value="1" <?= $promo_only ? 'checked' : '' ?> class="w-5 h-5 text-amber-500 border-slate-300 rounded focus:ring-amber-500 cursor-pointer">
                        <span class="text-sm font-bold text-slate-700 group-hover:text-amber-600 transition flex items-center">
                            <i class="fa-solid fa-bolt text-amber-500 mr-2"></i> Offres spéciales
                            <div class="group/tooltip relative inline-block ml-2">
                                <i class="fa-solid fa-circle-info text-slate-300 hover:text-amber-500 cursor-help transition text-xs"></i>
                                <div class="opacity-0 w-48 bg-slate-800 text-white text-[10px] font-normal normal-case tracking-normal rounded-lg py-2 px-3 absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-2 pointer-events-none group-hover/tooltip:opacity-100 transition-opacity duration-300 shadow-xl text-center">
                                    Affiche uniquement les annonces ayant une réduction de prix.
                                </div>
                            </div>
                        </span>
                    </label>
                </div>

                <!-- Tri -->
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Trier</label>
                    <select name="sort" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition cursor-pointer">
                        <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Les plus récents</option>
                        <option value="price_asc" <?= ($sort === 'price_asc') ? 'selected' : '' ?>>Prix : Moins cher</option>
                        <option value="price_desc" <?= ($sort === 'price_desc') ? 'selected' : '' ?>>Prix : Plus cher</option>
                        <option value="promo" <?= ($sort === 'promo') ? 'selected' : '' ?>>Meilleures réductions</option>
                    </select>
                </div>

                <button type="submit" class="w-full bg-slate-900 hover:bg-amber-500 text-white hover:text-slate-950 font-black py-4 rounded-xl transition-all shadow-md text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-filter"></i> Appliquer les filtres
                </button>
            </form>
        </aside>

        <!-- RÉSULTATS (DROITE) -->
        <section class="lg:col-span-3">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-white p-5 rounded-3xl border border-gray-100 shadow-sm mb-8 gap-4">
                <div class="flex items-center space-x-2">
                    <span class="text-gray-400 text-sm font-medium">Le marché a trouvé :</span>
                    <span class="font-black text-slate-900 bg-amber-100 px-3 py-1 rounded-lg text-sm"><?= $totalListings ?> opportunité(s)</span>
                </div>
            </div>

            <?php if (empty($listings)): ?>
                <div class="bg-white rounded-3xl border border-dashed border-gray-300 p-16 text-center shadow-sm">
                    <div class="w-24 h-24 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-6 text-4xl shadow-inner">
                        <i class="fa-solid fa-globe"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-900 mb-2">Le marché vous attend</h3>
                    <p class="text-gray-500 text-base font-medium mb-8 max-w-md mx-auto">Aucune opportunité ne correspond à cette recherche précise pour le moment.</p>
                    <div class="flex justify-center space-x-4">
                        <a href="listings.php" class="px-6 py-3 bg-gray-100 text-gray-700 font-bold rounded-xl text-sm hover:bg-gray-200 transition">
                            <i class="fa-solid fa-rotate-left mr-2"></i> Effacer filtres
                        </a>
                        <a href="publish.php" class="px-6 py-3 bg-amber-500 text-slate-950 font-black rounded-xl text-sm hover:bg-amber-400 transition shadow-lg">
                            <i class="fa-solid fa-plus-circle mr-2"></i> Publier une annonce
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($listings as $item): ?>
                        <?php 
                            $hasDiscount = !empty($item['original_price']) && $item['original_price'] > $item['price'];
                            $discountPercent = $hasDiscount ? round((($item['original_price'] - $item['price']) / $item['original_price']) * 100) : 0;
                            $imageSrc = htmlspecialchars(!empty($item['image_path']) ? $item['image_path'] : 'assets/images/placeholder.jpg', ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group transform hover:-translate-y-1">
                            
                            <div class="relative aspect-video bg-white overflow-hidden">
                                <img src="<?= $imageSrc ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-contain bg-slate-50 p-2 group-hover:scale-110 transition duration-500">
                                <span class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-md text-white text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full shadow-sm">
                                    <?= htmlspecialchars($item['category_name'] ?? 'Divers') ?>
                                </span>
                                <?php if ($hasDiscount): ?>
                                    <span class="absolute top-3 right-3 bg-red-600 text-white text-[10px] font-black px-2.5 py-1 rounded-full shadow-lg border border-red-500 animate-pulse">
                                        -<?= $discountPercent ?>%
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center text-[11px] font-bold text-gray-400 mb-2 uppercase tracking-wide">
                                        <span class="text-amber-500"><i class="fa-solid fa-location-dot mr-1"></i><?= htmlspecialchars($item['location'] ?? 'Global') ?></span>
                                        <span class="mx-2">•</span>
                                        <span><?= date('d/m/Y', strtotime($item['created_at'])) ?></span>
                                    </div>
                                    <h3 class="font-black text-gray-900 text-lg line-clamp-2 hover:text-amber-500 transition leading-tight">
                                        <a href="listing-detail.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                                    </h3>
                                </div>

                                <div class="pt-4 mt-4 border-t border-gray-100 flex items-center justify-between">
                                    <div>
                                        <div class="text-amber-600 font-black text-xl">
                                            <?= number_format($item['price'], 0, ',', ' ') ?> <span class="text-xs font-bold text-gray-500"><?= htmlspecialchars($item['currency'] ?? 'FCFA') ?></span>
                                        </div>
                                    </div>
                                    <a href="listing-detail.php?id=<?= $item['id'] ?>" class="w-10 h-10 rounded-full bg-slate-50 border border-gray-200 group-hover:bg-amber-500 group-hover:border-amber-500 group-hover:text-slate-950 text-slate-400 flex items-center justify-center transition shadow-sm">
                                        <i class="fa-solid fa-arrow-right text-sm"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-center items-center space-x-2 mt-12">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="<?= buildUrl(['page' => $i]) ?>" class="w-10 h-10 rounded-xl font-bold text-xs flex items-center justify-center transition-all <?= $i === $page ? 'bg-slate-900 text-white shadow-md' : 'bg-white border border-gray-200 text-slate-600 hover:bg-slate-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </section>
    </div>
</main>

<?php require_once __DIR__ . '/app/views/layouts/footer.php'; ?>