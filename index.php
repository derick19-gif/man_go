<?php
// =========================================================================
// 1. GESTION DES SESSIONS ET CONFIGURATION INITIALE
// =========================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';

// Définition de BASE_URL pour éviter toute erreur d'URL indéfinie
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// Obtention sécurisée de la connexion PDO
$dbInstance = Database::getInstance();
$pdo = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;
$db = $pdo;

// =========================================================================
// 2. GESTION DE LA LANGUE ET HELPER DE LIENS
// =========================================================================
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'fr';
if (!in_array($lang, ['fr', 'en'], true)) {
    $lang = 'fr';
}
$_SESSION['lang'] = $lang;

/**
 * Génère une URL en conservant l'ensemble des paramètres GET existants
 * et en mettant à jour uniquement les paramètres spécifiés.
 */
function buildUrl(array $newParams = []): string {
    $queryParams = $_GET;
    foreach ($newParams as $key => $value) {
        if ($value === null) {
            unset($queryParams[$key]);
        } else {
            $queryParams[$key] = $value;
        }
    }
    $queryString = http_build_query($queryParams);
    return '?' . $queryString;
}

// =========================================================================
// 3. RECUPERATION DES PARAMETRES DE RECHERCHE ET PAGINATION
// =========================================================================
$search_query = trim($_GET['q'] ?? '');
$search_city  = trim($_GET['city'] ?? '');

// Paramètres de pagination
$itemsPerPage = 6;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

// =========================================================================
// 4. RECUPERATION DES CATEGORIES DEPUIS LA BASE DE DONNEES
// =========================================================================
try {
    $stmtCats = $db->query("SELECT * FROM categories ORDER BY name ASC LIMIT 6");
    $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Mapping des icônes FontAwesome selon le nom de la catégorie
$iconMap = [
    'electronique' => 'fa-mobile-screen-button',
    'high-tech'    => 'fa-mobile-screen-button',
    'emploi'       => 'fa-briefcase',
    'services'     => 'fa-briefcase',
    'immobilier'   => 'fa-house-chimney',
    'maison'       => 'fa-couch',
    'jardin'       => 'fa-couch',
    'mode'         => 'fa-shirt',
    'style'        => 'fa-shirt',
    'vehicule'     => 'fa-car',
    'auto'         => 'fa-car'
];

/**
 * Détermine l'icône FontAwesome appropriée selon le nom de la catégorie.
 */
function getCategoryIcon(?string $name, array $map): string {
    if (empty($name)) {
        return 'fa-layer-group';
    }
    $lower = mb_strtolower($name, 'UTF-8');
    foreach ($map as $key => $icon) {
        if (str_contains($lower, $key)) {
            return $icon;
        }
    }
    return 'fa-layer-group';
}

// =========================================================================
// 5. RECUPERATION ET PAGINATION DES ANNONCES (SQL OPTIMISE)
// =========================================================================
$listings = [];
$totalListings = 0;
$totalPages = 1;

try {
    // 5.1 Construction des conditions SQL dynamiques
    $whereConditions = ["l.status = 'active'"];
    $params = [];

    if (!empty($search_query)) {
        $whereConditions[] = "(l.title LIKE :q OR l.description LIKE :q)";
        $params[':q'] = '%' . $search_query . '%';
    }

    if (!empty($search_city)) {
        $whereConditions[] = "(l.city LIKE :city OR l.location LIKE :city)";
        $params[':city'] = '%' . $search_city . '%';
    }

    $whereSql = " WHERE " . implode(" AND ", $whereConditions);

    // 5.2 Compter le nombre total d'éléments (Pour calculer le nombre total de pages)
    $sqlCount = "SELECT COUNT(*) FROM listings l" . $whereSql;
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalListings = (int) $stmtCount->fetchColumn();

    // Calcul du nombre de pages totales
    $totalPages = max(1, ceil($totalListings / $itemsPerPage));

    // Ajustement si la page demandée est supérieure au nombre total de pages
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $itemsPerPage;

    // 5.3 Récupération des annonces de la page courante
    $sqlListings = "SELECT l.*, c.name AS category_name 
                    FROM listings l 
                    LEFT JOIN categories c ON l.category_id = c.id 
                    " . $whereSql . " 
                    ORDER BY l.created_at DESC 
                    LIMIT :limit OFFSET :offset";

    $stmtListings = $db->prepare($sqlListings);

    // Lier les paramètres de filtres
    foreach ($params as $key => $val) {
        $stmtListings->bindValue($key, $val, PDO::PARAM_STR);
    }
    // Lier les paramètres LIMIT et OFFSET comme entier strict
    $stmtListings->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmtListings->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmtListings->execute();
    $listings = $stmtListings->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $listings = [];
    $totalListings = 0;
    $totalPages = 1;
}

// =========================================================================
// 6. VERIFICATION DE LA SESSION UTILISATEUR
// =========================================================================
$isLoggedIn = false;
if (class_exists('Session') && method_exists('Session', 'get')) {
    $isLoggedIn = (bool) Session::get('user_id');
} elseif (isset($_SESSION['user_id'])) {
    $isLoggedIn = true;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('APP_NAME') ? htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') : 'MAN GO' ?> - Marketplace & Annonces Réseau</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen font-sans">

    <!-- En-tête / Header -->
    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- Logo -->
            <a href="<?= $baseUrl ?>/<?= buildUrl(['lang' => $lang]) ?>" class="flex items-center space-x-2">
                <span class="bg-amber-500 text-slate-900 font-black text-2xl w-10 h-10 rounded-full flex items-center justify-center shadow-md">M</span>
                <span class="font-extrabold text-2xl tracking-wide">MAN <span class="text-amber-500">GO</span></span>
            </a>

            <!-- Navigation Principale -->
            <nav class="hidden md:flex items-center space-x-8 text-sm font-medium">
                <a href="<?= $baseUrl ?>/<?= buildUrl() ?>" class="text-amber-500 font-semibold transition">Accueil</a>
                <a href="<?= $baseUrl ?>/listings" class="hover:text-amber-500 transition">Annonces</a>
                <a href="<?= $baseUrl ?>/stands" class="hover:text-amber-500 transition">Boutiques & Stands</a>
                <a href="<?= $baseUrl ?>/services" class="hover:text-amber-500 transition">Services</a>
            </nav>

            <!-- Actions Droite & Sélecteur de Langue -->
            <div class="flex items-center space-x-5">
                <div class="text-xs font-semibold text-gray-300 hidden sm:block">
                    <a href="<?= buildUrl(['lang' => 'fr']) ?>" class="<?= $lang === 'fr' ? 'text-amber-500 font-bold' : 'hover:text-white' ?>">FR</a> | 
                    <a href="<?= buildUrl(['lang' => 'en']) ?>" class="<?= $lang === 'en' ? 'text-amber-500 font-bold' : 'hover:text-white' ?>">EN</a>
                </div>

                <?php if ($isLoggedIn): ?>
                    <a href="<?= $baseUrl ?>/dashboard" class="text-sm font-medium hover:text-amber-500 transition">Mon Compte</a>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>/login" class="text-sm font-medium hover:text-amber-500 transition">Connexion</a>
                <?php endif; ?>

                <a href="<?= $baseUrl ?>/publish" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-4 py-2.5 rounded-full text-xs sm:text-sm flex items-center space-x-2 shadow-lg transition transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-circle-plus text-base"></i>
                    <span>Publier une annonce</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Section Héros / Hero Section -->
    <section class="bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 text-white py-16 sm:py-24 px-4 text-center relative overflow-hidden">
        <div class="max-w-4xl mx-auto relative z-10">
            <h1 class="text-3xl sm:text-5xl font-black tracking-tight leading-tight">
                Trouvez tout ce dont vous avez besoin sur <span class="text-amber-500">MAN GO</span>
            </h1>
            <p class="text-gray-300 text-sm sm:text-lg mt-4 max-w-2xl mx-auto">
                Achetez, vendez et découvrez des boutiques locales en toute simplicité.
            </p>

            <!-- Formulaire de recherche rapide -->
            <form action="<?= $baseUrl ?>/" method="GET" class="mt-8 bg-white/10 backdrop-blur-md p-2.5 rounded-2xl sm:rounded-full border border-white/20 shadow-2xl flex flex-col sm:flex-row gap-2 max-w-3xl mx-auto">
                <?php if(!empty($lang)): ?>
                    <input type="hidden" name="lang" value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                
                <div class="flex-1 flex items-center bg-white rounded-xl sm:rounded-full px-4 py-3 text-gray-800">
                    <i class="fa-solid fa-magnifying-glass text-gray-400 mr-3"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Que recherchez-vous aujourd'hui ?" class="w-full text-sm bg-transparent border-none focus:outline-none">
                </div>
                <div class="sm:w-1/3 flex items-center bg-white rounded-xl sm:rounded-full px-4 py-3 text-gray-800">
                    <i class="fa-solid fa-location-dot text-gray-400 mr-3"></i>
                    <input type="text" name="city" value="<?= htmlspecialchars($search_city, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ville ou région" class="w-full text-sm bg-transparent border-none focus:outline-none">
                </div>
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-extrabold px-8 py-3 rounded-xl sm:rounded-full text-sm transition shadow-md flex items-center justify-center space-x-2">
                    <span>Rechercher</span>
                </button>
            </form>
        </div>
    </section>

    <!-- Section Exploration par Catégories -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
        <div class="flex items-center space-x-3 mb-8">
            <i class="fa-solid fa-layer-group text-amber-500 text-xl"></i>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Exploration par catégories</h2>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <?php $icon = getCategoryIcon($cat['name'] ?? '', $iconMap); ?>
                    <a href="<?= $baseUrl ?>/listings?category=<?= (int)($cat['id'] ?? 0) ?>" class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-amber-400 transition flex flex-col items-center text-center group">
                        <div class="w-12 h-12 bg-amber-100/70 text-amber-600 rounded-full flex items-center justify-center mb-3 group-hover:bg-amber-500 group-hover:text-slate-950 transition">
                            <i class="fa-solid <?= $icon ?> text-lg"></i>
                        </div>
                        <span class="text-xs font-bold text-gray-800 group-hover:text-amber-600 transition leading-snug">
                            <?= htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <a href="<?= $baseUrl ?>/listings" class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
                    <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-mobile-screen-button"></i></div>
                    <span class="text-xs font-bold">Électronique & High-Tech</span>
                </a>
                <a href="<?= $baseUrl ?>/listings" class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
                    <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-briefcase"></i></div>
                    <span class="text-xs font-bold">Emplois & Services</span>
                </a>
                <a href="<?= $baseUrl ?>/listings" class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
                    <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-house-chimney"></i></div>
                    <span class="text-xs font-bold">Immobilier</span>
                </a>
                <a href="<?= $baseUrl ?>/listings" class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
                    <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-couch"></i></div>
                    <span class="text-xs font-bold">Maison & Jardin</span>
                </a>
                <a href="<?= $baseUrl ?>/listings" class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
                    <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-shirt"></i></div>
                    <span class="text-xs font-bold">Mode & Style</span>
                </a>
                <a href="<?= $baseUrl ?>/listings" class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
                    <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-car"></i></div>
                    <span class="text-xs font-bold">Véhicules & Auto</span>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section Dernières Annonces -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mb-12 w-full flex-1">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <i class="fa-solid fa-fire text-amber-500 text-xl"></i>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">
                    <?= (!empty($search_query) || !empty($search_city)) ? 'Résultats de recherche' : 'Dernières annonces' ?>
                </h2>
                <span class="text-xs font-semibold bg-gray-200 text-gray-700 px-2.5 py-1 rounded-full">
                    <?= $totalListings ?>
                </span>
            </div>
            <a href="<?= $baseUrl ?>/listings" class="text-amber-600 hover:text-amber-700 text-xs sm:text-sm font-bold flex items-center space-x-1">
                <span>Voir tout</span>
                <i class="fa-solid fa-arrow-right-long"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (!empty($listings)): ?>
                <?php foreach ($listings as $item): ?>
                    <?php 
                        $imgPath = trim($item['image_url'] ?? '');
                        if (empty($imgPath)) {
                            $imageSrc = 'https://via.placeholder.com/600x400/1e293b/f59e0b?text=MAN+GO';
                        } elseif (str_starts_with($imgPath, 'http://') || str_starts_with($imgPath, 'https://')) {
                            $imageSrc = $imgPath;
                        } else {
                            $cleanPath = ltrim($imgPath, '/');
                            $imageSrc = file_exists(__DIR__ . '/' . $cleanPath) ? $baseUrl . '/' . $cleanPath : 'https://via.placeholder.com/600x400/1e293b/f59e0b?text=MAN+GO';
                        }
                        $cityLocation = !empty($item['city']) ? $item['city'] : (!empty($item['location']) ? $item['location'] : 'Lomé');
                    ?>
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-lg transition flex flex-col group">
                        
                        <!-- Image & Badge Catégorie -->
                        <div class="relative h-52 bg-gray-100 overflow-hidden">
                            <img src="<?= htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                            <?php if (!empty($item['category_name'])): ?>
                                <span class="absolute top-3 left-3 bg-white/90 backdrop-blur-md text-gray-900 text-xs font-extrabold px-3 py-1 rounded-full shadow-sm">
                                    <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Corps de la carte -->
                        <div class="p-5 flex flex-col flex-1 justify-between">
                            <div>
                                <h3 class="font-bold text-gray-900 text-base line-clamp-1 group-hover:text-amber-600 transition">
                                    <a href="<?= $baseUrl ?>/listings/<?= (int)($item['id'] ?? 0) ?>">
                                        <?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </h3>
                                <p class="text-xs text-gray-400 mt-2 flex items-center">
                                    <i class="fa-solid fa-location-dot mr-1.5 text-gray-400"></i>
                                    <?= htmlspecialchars($cityLocation, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>

                            <!-- Prix & Bouton Détails -->
                            <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                                <div>
                                    <span class="text-lg font-black text-amber-600">
                                        <?= number_format((float)($item['price'] ?? 0), 0, ',', ' ') ?> <?= htmlspecialchars($item['currency'] ?? 'FCFA', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <a href="<?= $baseUrl ?>/listings/<?= (int)($item['id'] ?? 0) ?>" class="border border-amber-500 text-amber-600 hover:bg-amber-500 hover:text-slate-950 text-xs font-bold px-4 py-2 rounded-full transition">
                                    Détails
                                </a>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full bg-white p-12 rounded-2xl border border-gray-200 text-center">
                    <i class="fa-solid fa-box-open text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500 font-medium text-sm">Aucune annonce trouvée correspondant à vos critères.</p>
                    <a href="<?= $baseUrl ?>/publish" class="inline-block mt-4 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-5 py-2.5 rounded-full text-xs transition">
                        Publier la première annonce !
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination dynamique -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-12 flex justify-center items-center space-x-2">
                
                <!-- Bouton Précédent -->
                <?php if ($currentPage > 1): ?>
                    <a href="<?= buildUrl(['page' => $currentPage - 1]) ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-bold text-gray-700 hover:bg-amber-500 hover:text-slate-950 hover:border-amber-500 transition shadow-sm">
                        <i class="fa-solid fa-chevron-left mr-1"></i> Précédent
                    </a>
                <?php else: ?>
                    <span class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-xs font-bold text-gray-400 cursor-not-allowed">
                        <i class="fa-solid fa-chevron-left mr-1"></i> Précédent
                    </span>
                <?php endif; ?>

                <!-- Numéros de pages -->
                <div class="hidden sm:flex items-center space-x-1">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php if ($p == $currentPage): ?>
                            <span class="px-3.5 py-2 bg-amber-500 text-slate-950 border border-amber-500 rounded-lg text-xs font-black shadow-sm">
                                <?= $p ?>
                            </span>
                        <?php else: ?>
                            <a href="<?= buildUrl(['page' => $p]) ?>" class="px-3.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-bold text-gray-700 hover:bg-amber-50 hover:text-amber-600 transition">
                                <?= $p ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <!-- Indicateur Mobile -->
                <span class="sm:hidden text-xs font-bold text-gray-500 px-2">
                    Page <?= $currentPage ?> / <?= $totalPages ?>
                </span>

                <!-- Bouton Suivant -->
                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= buildUrl(['page' => $currentPage + 1]) ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-bold text-gray-700 hover:bg-amber-500 hover:text-slate-950 hover:border-amber-500 transition shadow-sm">
                        Suivant <i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                <?php else: ?>
                    <span class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-xs font-bold text-gray-400 cursor-not-allowed">
                        Suivant <i class="fa-solid fa-chevron-right ml-1"></i>
                    </span>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </section>

    <!-- Pied de page / Footer -->
    <footer class="bg-slate-950 text-gray-400 text-sm border-t border-slate-800 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="space-y-4">
                <a href="<?= $baseUrl ?>/<?= buildUrl(['lang' => $lang]) ?>" class="flex items-center space-x-2 text-white">
                    <span class="bg-amber-500 text-slate-950 font-black text-xl w-8 h-8 rounded-full flex items-center justify-center">M</span>
                    <span class="font-extrabold text-xl">MAN <span class="text-amber-500">GO</span></span>
                </a>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Plateforme universelle d'annonces, de boutiques virtuelles et de services.
                </p>
            </div>
            <div>
                <h4 class="text-white font-bold mb-4 text-xs tracking-wider uppercase">Navigation</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="<?= $baseUrl ?>/listings" class="hover:text-amber-500 transition">Toutes les annonces</a></li>
                    <li><a href="<?= $baseUrl ?>/stands" class="hover:text-amber-500 transition">Boutiques certifiées</a></li>
                    <li><a href="<?= $baseUrl ?>/services" class="hover:text-amber-500 transition">Prestataires de services</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-bold mb-4 text-xs tracking-wider uppercase">Support</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="<?= $baseUrl ?>/faq" class="hover:text-amber-500 transition">Foire aux questions</a></li>
                    <li><a href="<?= $baseUrl ?>/terms" class="hover:text-amber-500 transition">Conditions d'utilisation</a></li>
                    <li><a href="<?= $baseUrl ?>/contact" class="hover:text-amber-500 transition">Nous contacter</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-bold mb-4 text-xs tracking-wider uppercase">Compte</h4>
                <ul class="space-y-2 text-xs">
                    <?php if ($isLoggedIn): ?>
                        <li><a href="<?= $baseUrl ?>/dashboard" class="hover:text-amber-500 transition">Tableau de bord</a></li>
                        <li><a href="<?= $baseUrl ?>/logout" class="hover:text-amber-500 transition">Déconnexion</a></li>
                    <?php else: ?>
                        <li><a href="<?= $baseUrl ?>/login" class="hover:text-amber-500 transition">Connexion</a></li>
                        <li><a href="<?= $baseUrl ?>/register" class="hover:text-amber-500 transition">Inscription</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-900 py-6 text-center text-xs text-gray-500">
            &copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.
        </div>
    </footer>

</body>
</html>