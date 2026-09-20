<?php
// =========================================================================
// 1. INITIALISATION ET CONNEXION (Sécurité et Robustesse)
// =========================================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Session.php';
use App\Core\Database;

Session::init();

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// Connexion propre à la base de données
$dbInstance = Database::getInstance();
$db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;

// =========================================================================
// 2. PARAMÈTRES DE RECHERCHE, FILTRES ET PAGINATION
// =========================================================================
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

// =========================================================================
// 3. RÉCUPÉRATION DES DONNÉES POUR LES FILTRES (Catégories & Villes)
// =========================================================================
try {
    $stmtCats = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
    $categoriesList = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

    $stmtLocs = $db->query("SELECT DISTINCT location FROM listings WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
    $locationsList = $stmtLocs->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categoriesList = [];
    $locationsList = [];
}

// =========================================================================
// 4. CONSTRUCTION DE LA REQUÊTE SQL DYNAMIQUE (Le Moteur de Recherche)
// =========================================================================
$where = ["l.status = 'ACTIVE'"];
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

// Tri des résultats (Algorithme de tri)
switch ($sort) {
    case 'price_asc': $orderBy = "l.price ASC, l.created_at DESC"; break;
    case 'price_desc': $orderBy = "l.price DESC, l.created_at DESC"; break;
    case 'promo': $orderBy = "(CASE WHEN l.original_price > l.price THEN (l.original_price - l.price) ELSE 0 END) DESC, l.created_at DESC"; break;
    case 'oldest': $orderBy = "l.created_at ASC"; break;
    case 'newest': default: $orderBy = "l.created_at DESC"; break;
}

// =========================================================================
// 5. COMPTAGE GLOBAL ET RÉCUPÉRATION DES ANNONCES
// =========================================================================
$totalListings = 0;
$listings = [];

try {
    $countSql = "SELECT COUNT(*) FROM listings l WHERE {$whereSQL}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalListings = (int)$countStmt->fetchColumn();

    $totalPages = max(1, ceil($totalListings / $limit));

    $sql = "SELECT l.*, c.name AS category_name, c.slug AS category_slug 
            FROM listings l 
            LEFT JOIN categories c ON l.category_id = c.id 
            WHERE {$whereSQL} 
            ORDER BY {$orderBy} 
            LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
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
        if ($value === null) unset($queryParams[$key]);
        else $queryParams[$key] = $value;
    }
    return 'listings.php?' . http_build_query($queryParams);
}

// =========================================================================
// CHARGEMENT DE L'INTERFACE (Vues)
// =========================================================================

// Chargement du Header unifié futuriste
require_once __DIR__ . '/app/views/layouts/header.php';
?>

<!-- En-tête / Recherche principale -->
<section class="bg-slate-900 text-white py-10 px-4 sm:px-6 lg:px-8 border-b border-slate-800">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-extrabold mb-2 text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400">Le monde à portée de clic</h1>
        <p class="text-gray-400 text-sm mb-8 font-medium">Parcourez des milliers d'opportunités, de l'immobilier aux services professionnels, partout dans le monde.</p>

        <form action="listings.php" method="GET" class="bg-slate-800/80 p-3 rounded-2xl border border-slate-700 shadow-2xl grid grid-cols-1 md:grid-cols-12 gap-3 backdrop-blur-sm">
            
            <!-- Recherche mot-clé -->
            <div class="md:col-span-5 flex items-center bg-slate-950 rounded-xl px-4 py-3 border border-slate-700/50 focus-within:border-amber-500 transition-colors">
                <i class="fa-solid fa-magnifying-glass text-amber-500 mr-3"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Ex: Avocat, iPhone, Hôtel, Plombier..." class="bg-transparent w-full focus:outline-none text-sm text-white placeholder-gray-500">
            </div>

            <!-- Sélecteur Catégorie Dynamique -->
            <div class="md:col-span-3 flex items-center bg-slate-950 rounded-xl px-4 py-3 border border-slate-700/50 focus-within:border-amber-500 transition-colors">
                <i class="fa-solid fa-layer-group text-amber-500 mr-3"></i>
                <select name="category" class="bg-transparent w-full focus:outline-none text-sm text-gray-300 cursor-pointer appearance-none">
                    <option value="0" class="text-gray-900">Tous les domaines</option>
                    <?php foreach ($categoriesList as $cat): ?>
                        <option value="<?= $cat['id'] ?>" class="text-gray-900" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sélecteur Localisation Dynamique -->
            <div class="md:col-span-2 flex items-center bg-slate-950 rounded-xl px-4 py-3 border border-slate-700/50 focus-within:border-amber-500 transition-colors">
                <i class="fa-solid fa-earth-americas text-amber-500 mr-3"></i>
                <select name="location" class="bg-transparent w-full focus:outline-none text-sm text-gray-300 cursor-pointer appearance-none">
                    <option value="" class="text-gray-900">Le monde entier</option>
                    <?php foreach ($locationsList as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>" class="text-gray-900" <?= ($location === $loc) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loc) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Bouton Recherche -->
            <div class="md:col-span-2">
                <button type="submit" class="w-full h-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black py-3 px-4 rounded-xl transition-all shadow-glow flex items-center justify-center space-x-2 text-sm transform hover:-translate-y-0.5">
                    <span>Explorer</span>
                </button>
            </div>
        </form>
    </div>
</section>

<!-- Zone principale : Filtres avancés (Gauche) + Résultats (Droite) -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full flex-1">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <!-- PANNEAU FILTRES AVANCÉS -->
        <aside class="lg:col-span-1">
            <form action="listings.php" method="GET" class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm space-y-6 sticky top-28">
                
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>

                <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                    <h2 class="font-black text-gray-900 text-base flex items-center">
                        <i class="fa-solid fa-sliders text-amber-500 mr-2"></i> Affiner
                    </h2>
                    <a href="listings.php" class="text-xs text-amber-600 hover:underline font-bold">Réinitialiser</a>
                </div>

                <!-- Catégorie Dynamique -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Domaine d'activité</label>
                    <select name="category" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold focus:outline-none focus:border-amber-500 focus:bg-white transition text-gray-700">
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
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Budget (<?= htmlspecialchars($_SESSION['user_currency'] ?? 'FCFA') ?>)</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" name="min_price" value="<?= htmlspecialchars($min_price ?? '') ?>" placeholder="Min" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold focus:outline-none focus:border-amber-500 focus:bg-white transition">
                        <input type="number" name="max_price" value="<?= htmlspecialchars($max_price ?? '') ?>" placeholder="Max" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold focus:outline-none focus:border-amber-500 focus:bg-white transition">
                    </div>
                </div>

                <!-- Villes Dynamique -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Zone Géographique</label>
                    <select name="location" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold focus:outline-none focus:border-amber-500 focus:bg-white transition text-gray-700">
                        <option value="">Monde entier</option>
                        <?php foreach ($locationsList as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>" <?= ($location === $loc) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Case à cocher : En promotion uniquement -->
                <div class="pt-2 border-t border-gray-100">
                    <label class="flex items-center space-x-3 cursor-pointer group">
                        <input type="checkbox" name="promo" value="1" <?= $promo_only ? 'checked' : '' ?> class="w-5 h-5 text-amber-500 border-gray-300 rounded focus:ring-amber-500 transition">
                        <span class="text-sm font-bold text-gray-700 group-hover:text-amber-600 transition">
                            <i class="fa-solid fa-bolt text-amber-500 mr-1"></i> Offres spéciales
                        </span>
                    </label>
                </div>

                <!-- Tri -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Trier les résultats</label>
                    <div class="relative">
                        <select name="sort" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-bold text-gray-700 focus:outline-none focus:border-amber-500 focus:bg-white transition appearance-none cursor-pointer">
                            <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Les plus récents</option>
                            <option value="price_asc" <?= ($sort === 'price_asc') ? 'selected' : '' ?>>Prix : Moins cher d'abord</option>
                            <option value="price_desc" <?= ($sort === 'price_desc') ? 'selected' : '' ?>>Prix : Plus cher d'abord</option>
                            <option value="promo" <?= ($sort === 'promo') ? 'selected' : '' ?>>Meilleures réductions</option>
                            <option value="oldest" <?= ($sort === 'oldest') ? 'selected' : '' ?>>Plus anciens</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-xs text-gray-400 pointer-events-none"></i>
                    </div>
                </div>

                <button type="submit" class="w-full bg-slate-900 hover:bg-amber-500 text-white hover:text-slate-950 font-black py-3.5 rounded-xl transition-all shadow-md text-sm">
                    Mettre à jour
                </button>
            </form>
        </aside>

        <!-- RÉSULTATS -->
        <section class="lg:col-span-3">
            
            <!-- Barre d'info résultats et tri rapide -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-white p-5 rounded-3xl border border-gray-100 shadow-sm mb-8 gap-4">
                <div class="flex items-center space-x-2">
                    <span class="text-gray-400 text-sm font-medium">Le marché a trouvé :</span>
                    <span class="font-black text-slate-900 bg-amber-100 px-3 py-1 rounded-lg text-sm"><?= $totalListings ?> opportunité(s)</span>
                </div>

                <div class="flex items-center space-x-2 text-sm">
                    <span class="text-gray-500 font-medium">Trier :</span>
                    <a href="<?= buildUrl(['sort' => 'newest', 'page' => 1]) ?>" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= ($sort === 'newest') ? 'bg-slate-900 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">Récents</a>
                    <a href="<?= buildUrl(['sort' => 'price_asc', 'page' => 1]) ?>" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= ($sort === 'price_asc') ? 'bg-slate-900 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">Prix <i class="fa-solid fa-arrow-up text-[10px] ml-1"></i></a>
                    <a href="<?= buildUrl(['sort' => 'price_desc', 'page' => 1]) ?>" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= ($sort === 'price_desc') ? 'bg-slate-900 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">Prix <i class="fa-solid fa-arrow-down text-[10px] ml-1"></i></a>
                </div>
            </div>

            <!-- Grille d'Annonces -->
            <?php if (empty($listings)): ?>
                <div class="bg-white rounded-3xl border border-dashed border-gray-300 p-16 text-center shadow-sm">
                    <div class="w-24 h-24 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-6 text-4xl shadow-inner">
                        <i class="fa-solid fa-globe"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-900 mb-2">Le marché vous attend</h3>
                    <p class="text-gray-500 text-base font-medium mb-8 max-w-md mx-auto">Aucune opportunité ne correspond à cette recherche précise pour le moment.</p>
                    <div class="flex justify-center space-x-4">
                        <a href="listings.php" class="inline-flex items-center px-6 py-3 bg-gray-100 text-gray-700 font-bold rounded-xl text-sm hover:bg-gray-200 transition">
                            <i class="fa-solid fa-rotate-left mr-2"></i> Effacer les filtres
                        </a>
                        <a href="publish.php" class="inline-flex items-center px-6 py-3 bg-amber-500 text-slate-950 font-black rounded-xl text-sm hover:bg-amber-400 transition shadow-lg transform hover:-translate-y-0.5">
                            <i class="fa-solid fa-plus-circle mr-2"></i> Publier la vôtre
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($listings as $item): ?>
                        <?php 
                            // Logique mathématique des promotions récupérée de l'ancien code
                            $hasDiscount = !empty($item['original_price']) && $item['original_price'] > $item['price'];
                            $discountPercent = 0;
                            if ($hasDiscount) {
                                $discountPercent = round((($item['original_price'] - $item['price']) / $item['original_price']) * 100);
                            }
                            $imageSrc = htmlspecialchars(!empty($item['image_url']) ? $item['image_url'] : 'assets/images/placeholder.jpg', ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group transform hover:-translate-y-1">
                            
                            <!-- Image & Badges (Design UI avancé) -->
                            <div class="relative aspect-video bg-gray-100 overflow-hidden">
                                <img src="<?= $imageSrc ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                                
                                <span class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-md text-white text-[11px] font-black uppercase tracking-wider px-3 py-1 rounded-full shadow-sm">
                                    <?= htmlspecialchars($item['category_name'] ?? 'Divers') ?>
                                </span>

                                <?php if ($hasDiscount): ?>
                                    <span class="absolute top-3 right-3 bg-red-600 text-white text-[11px] font-black px-2.5 py-1 rounded-full shadow-lg border border-red-500 animate-pulse">
                                        -<?= $discountPercent ?>%
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Contenu (Titres, dates, etc.) -->
                            <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                                <div>
                                    <div class="flex items-center text-[11px] font-bold text-gray-400 mb-2 uppercase tracking-wide">
                                        <span class="text-amber-500"><i class="fa-solid fa-location-dot mr-1"></i><?= htmlspecialchars($item['location'] ?? 'Global') ?></span>
                                        <span class="mx-2">•</span>
                                        <span><?= date('d/m/Y', strtotime($item['created_at'])) ?></span>
                                    </div>
                                    <h3 class="font-black text-gray-900 text-lg line-clamp-2 hover:text-amber-500 transition leading-tight">
                                        <a href="listing-detail.php?id=<?= $item['id'] ?>">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </a>
                                    </h3>
                                </div>

                                <!-- Bloc Prix -->
                                <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                                    <div>
                                        <div class="text-amber-600 font-black text-xl">
                                            <?= number_format($item['price'], 0, ',', ' ') ?> <span class="text-sm font-bold text-gray-500"><?= htmlspecialchars($item['currency'] ?? 'FCFA') ?></span>
                                        </div>
                                        <?php if ($hasDiscount): ?>
                                            <div class="text-xs text-gray-400 line-through font-semibold mt-0.5">
                                                <?= number_format($item['original_price'], 0, ',', ' ') ?> <?= htmlspecialchars($item['currency'] ?? 'FCFA') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <a href="listing-detail.php?id=<?= $item['id'] ?>" class="w-10 h-10 rounded-full bg-slate-50 border border-gray-200 group-hover:bg-amber-500 group-hover:border-amber-500 group-hover:text-slate-950 text-slate-400 flex items-center justify-center transition shadow-sm">
                                        <i class="fa-solid fa-arrow-right text-sm"></i>
                                    </a>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINATION DYNAMIQUE -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-14 flex items-center justify-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="<?= buildUrl(['page' => $page - 1]) ?>" class="px-5 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition shadow-sm">
                                <i class="fa-solid fa-chevron-left mr-2"></i> Précédent
                            </a>
                        <?php endif; ?>

                        <div class="hidden sm:flex items-center space-x-1">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="w-10 h-10 flex items-center justify-center bg-slate-900 text-white font-black rounded-xl text-sm shadow-md">
                                        <?= $i ?>
                                    </span>
                                <?php elseif ($i == 1 || $i == $totalPages || abs($i - $page) <= 2): ?>
                                    <a href="<?= buildUrl(['page' => $i]) ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 text-gray-700 font-bold rounded-xl text-sm hover:bg-amber-50 hover:border-amber-500 hover:text-amber-600 transition shadow-sm">
                                        <?= $i ?>
                                    </a>
                                <?php elseif (abs($i - $page) == 3): ?>
                                    <span class="px-2 text-gray-400 font-bold">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= buildUrl(['page' => $page + 1]) ?>" class="px-5 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition shadow-sm">
                                Suivant <i class="fa-solid fa-chevron-right ml-2"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </section>
    </div>
</main>

<?php 
// Chargement du Footer unifié
require_once __DIR__ . '/app/views/layouts/footer.php'; 
?>
