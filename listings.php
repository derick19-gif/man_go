<?php
// =========================================================================
// PAGE EXPLORATION DES ANNONCES - listings.php
// =========================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$db = \App\Core\Database::connect();

// Paramètres de recherche
$q = trim($_GET['q'] ?? '');
$category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$min_price = filter_input(INPUT_GET, 'min_price', FILTER_VALIDATE_INT);
$max_price = filter_input(INPUT_GET, 'max_price', FILTER_VALIDATE_INT);
$promo_only = isset($_GET['promo']) ? true : false;
$sort = $_GET['sort'] ?? 'recent';

// Construction de la requête SQL Dynamique
$where = ["LOWER(l.status) IN ('active', 'published', 'actif', '1')"];
$params = [];

if (!empty($q)) {
    $where[] = "(l.title LIKE :q OR l.description LIKE :q)";
    $params[':q'] = "%$q%";
}
if ($category) {
    $where[] = "l.category_id = :cat";
    $params[':cat'] = $category;
}
if ($min_price) {
    $where[] = "l.price >= :min_p";
    $params[':min_p'] = $min_price;
}
if ($max_price) {
    $where[] = "l.price <= :max_p";
    $params[':max_p'] = $max_price;
}
if ($promo_only) {
    $where[] = "l.original_price > l.price AND l.original_price IS NOT NULL";
}

$orderClause = "ORDER BY l.created_at DESC";
if ($sort === 'price_asc') $orderClause = "ORDER BY l.price ASC";
if ($sort === 'price_desc') $orderClause = "ORDER BY l.price DESC";

$whereSql = implode(" AND ", $where);

try {
    $stmt = $db->prepare("
        SELECT l.*, c.name_key as category_name, u.firstname, u.lastname 
        FROM listings l
        LEFT JOIN categories c ON l.category_id = c.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE $whereSql
        $orderClause
        LIMIT 50
    ");
    $stmt->execute($params);
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $listings = [];
}

// Récupération de toutes les catégories pour le menu déroulant
$cats = $db->query("SELECT id, name_key FROM categories ORDER BY name_key ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Explorer les annonces - MAN GO";
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-900 pt-20 pb-24 text-slate-100 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-black text-white mb-2">Parcourez des milliers d'opportunités.</h1>
        <p class="text-slate-400">Biens, produits et services locaux et internationaux.</p>
    </div>
</div>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 -mt-16 relative z-10 flex flex-col md:flex-row gap-8">
    
    <!-- COLONNE FILTRES (Gauche) -->
    <aside class="w-full md:w-64 flex-shrink-0">
        <form method="GET" class="bg-white p-6 rounded-3xl shadow-xl border border-slate-200 sticky top-24">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-black text-lg text-slate-900">Affiner</h3>
                <a href="?" class="text-xs font-bold text-amber-500 hover:text-amber-600">Réinitialiser</a>
            </div>

            <!-- Catégories -->
            <div class="mb-6">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Domaine / Type</label>
                <select name="category" class="w-full bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 rounded-xl p-3 focus:ring-amber-500 focus:border-amber-500 cursor-pointer">
                    <option value="">Toutes les catégories</option>
                    <?php foreach($cats as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($category == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name_key']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Budget -->
            <div class="mb-6">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Budget (FCFA)</label>
                <div class="flex gap-2">
                    <input type="number" name="min_price" value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>" placeholder="Min" class="w-1/2 bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm focus:ring-amber-500 outline-none">
                    <input type="number" name="max_price" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>" placeholder="Max" class="w-1/2 bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm focus:ring-amber-500 outline-none">
                </div>
            </div>

            <!-- Offres Spéciales -->
            <div class="mb-6">
                <label class="flex items-center cursor-pointer group">
                    <input type="checkbox" name="promo" value="1" <?= $promo_only ? 'checked' : '' ?> class="w-5 h-5 rounded text-amber-500 border-slate-300 focus:ring-amber-500">
                    <span class="ml-3 text-sm font-bold text-slate-700 group-hover:text-amber-600 transition flex items-center">
                        <i class="fa-solid fa-bolt text-amber-500 mr-2"></i> Offres spéciales
                    </span>
                </label>
            </div>

            <!-- Tri -->
            <div class="mb-8">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Trier par</label>
                <select name="sort" class="w-full bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 rounded-xl p-3 focus:ring-amber-500 focus:border-amber-500 cursor-pointer">
                    <option value="recent" <?= ($sort === 'recent') ? 'selected' : '' ?>>Les plus récents</option>
                    <option value="price_asc" <?= ($sort === 'price_asc') ? 'selected' : '' ?>>Prix croissant</option>
                    <option value="price_desc" <?= ($sort === 'price_desc') ? 'selected' : '' ?>>Prix décroissant</option>
                </select>
            </div>

            <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-3 rounded-xl shadow-md transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-filter text-xs"></i> Appliquer les filtres
            </button>
        </form>
    </aside>

    <!-- COLONNE RÉSULTATS (Droite) -->
    <div class="flex-1">
        
        <div class="bg-white rounded-2xl p-4 shadow-md border border-slate-200 mb-6 flex justify-between items-center">
            <div class="text-sm font-bold text-slate-600">
                Le marché a trouvé : <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full ml-2"><?= count($listings) ?> opportunité(s)</span>
            </div>
            <!-- Convertisseur Global Rapide -->
            <div class="hidden sm:flex items-center gap-2">
                <span class="text-xs text-slate-400 font-bold">Devise :</span>
                <select id="global-currency" onchange="updateAllPrices()" class="bg-slate-50 border border-slate-200 rounded text-xs font-bold p-1 cursor-pointer outline-none">
                    <option value="XOF">FCFA</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </div>
        </div>

        <?php if(empty($listings)): ?>
            <div class="bg-white rounded-3xl p-16 text-center border border-slate-200 shadow-sm">
                <div class="w-20 h-20 bg-slate-100 text-slate-300 rounded-full flex items-center justify-center text-4xl mx-auto mb-4">
                    <i class="fa-solid fa-ghost"></i>
                </div>
                <h3 class="text-xl font-black text-slate-900">Aucun résultat</h3>
                <p class="text-slate-500 mt-2">Essayez de modifier vos filtres ou de retirer la recherche textuelle.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach($listings as $ad): 
                    $discount = 0;
                    if(!empty($ad['original_price']) && $ad['original_price'] > $ad['price']) {
                        $discount = round((($ad['original_price'] - $ad['price']) / $ad['original_price']) * 100);
                    }
                ?>
                    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition group relative flex flex-col">
                        
                        <!-- Badges -->
                        <?php if($discount > 0): ?>
                            <div class="absolute top-3 right-3 bg-red-500 text-white text-[10px] font-black px-3 py-1 rounded-full shadow-md z-10">
                                -<?= $discount ?>%
                            </div>
                        <?php endif; ?>
                        <div class="absolute top-3 left-3 bg-slate-900 text-white text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full shadow-md z-10">
                            <?= htmlspecialchars($ad['category_name'] ?? 'Général') ?>
                        </div>

                        <!-- Image -->
                        <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $ad['id'] ?>" class="block relative h-48 bg-slate-100 overflow-hidden">
                            <img src="<?= htmlspecialchars(!empty($ad['image_path']) ? $baseUrl.'/'.$ad['image_path'] : $baseUrl.'/assets/images/placeholder.jpg') ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        </a>

                        <!-- Contenu -->
                        <div class="p-5 flex flex-col flex-grow">
                            <div class="text-[10px] font-bold text-amber-500 uppercase tracking-wider mb-2 flex items-center gap-1">
                                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($ad['location'] ?? 'Global') ?>
                                <span class="text-slate-300 mx-1">•</span>
                                <span class="text-slate-400"><?= date('d/m/Y', strtotime($ad['created_at'])) ?></span>
                            </div>
                            
                            <h3 class="font-bold text-lg text-slate-900 leading-tight mb-4 line-clamp-2 hover:text-amber-600 transition">
                                <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $ad['id'] ?>"><?= htmlspecialchars($ad['title']) ?></a>
                            </h3>
                            
                            <!-- Section Prix (Toujours en bas) -->
                            <div class="mt-auto flex justify-between items-end">
                                <div>
                                    <!-- AFFICHE L'ANCIEN PRIX BARRÉ SI DISPONIBLE -->
                                    <?php if($discount > 0): ?>
                                        <div class="text-xs text-slate-400 line-through font-bold mb-0.5 price-element" data-xof="<?= $ad['original_price'] ?>">
                                            <?= number_format($ad['original_price'], 0, ',', ' ') ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-xl font-black text-amber-600 flex items-baseline gap-1">
                                        <span class="price-element" data-xof="<?= $ad['price'] ?>"><?= number_format($ad['price'], 0, ',', ' ') ?></span>
                                        <span class="currency-symbol text-xs text-slate-500 uppercase">FCFA</span>
                                    </div>
                                </div>
                                <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $ad['id'] ?>" class="w-10 h-10 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 group-hover:bg-amber-500 group-hover:text-white group-hover:border-amber-500 transition shadow-sm">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    function updateAllPrices() {
        const currency = document.getElementById('global-currency').value;
        const rateUSD = 600;
        const rateEUR = 655;

        // Mise à jour des valeurs numériques
        document.querySelectorAll('.price-element').forEach(el => {
            const amountFCFA = parseFloat(el.getAttribute('data-xof'));
            let newAmount = amountFCFA;
            let decimals = 0;

            if (currency === 'USD') { newAmount = amountFCFA / rateUSD; decimals = 2; }
            else if (currency === 'EUR') { newAmount = amountFCFA / rateEUR; decimals = 2; }

            el.innerText = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(newAmount);
        });

        // Mise à jour des symboles (FCFA, $, €)
        document.querySelectorAll('.currency-symbol').forEach(sym => {
            if (currency === 'XOF') sym.innerText = 'FCFA';
            else if (currency === 'USD') sym.innerText = 'USD';
            else if (currency === 'EUR') sym.innerText = 'EUR';
        });
    }
</script>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>