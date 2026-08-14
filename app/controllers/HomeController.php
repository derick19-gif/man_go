<?php

class HomeController {

    public function index() {
        // Gestion de la session et de la configuration de l'URL
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';

        // Connexion sécurisée à la base de données
        $dbInstance = Database::getInstance();
        $db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;

        // Gestion de la langue
        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'fr';
        if (!in_array($lang, ['fr', 'en'], true)) {
            $lang = 'fr';
        }
        $_SESSION['lang'] = $lang;

        // Paramètres de recherche et de pagination
        $search_query = trim($_GET['q'] ?? '');
        $search_city  = trim($_GET['city'] ?? '');
        $itemsPerPage = 6;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        // Récupération des catégories
        try {
            $stmtCats = $db->query("SELECT * FROM categories ORDER BY name ASC LIMIT 6");
            $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $categories = [];
        }

        // Mapping des icônes FontAwesome
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

        // Récupération et pagination des annonces
        $listings = [];
        $totalListings = 0;
        $totalPages = 1;

        try {
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

            // Total des éléments pour pagination
            $sqlCount = "SELECT COUNT(*) FROM listings l" . $whereSql;
            $stmtCount = $db->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalListings = (int) $stmtCount->fetchColumn();

            $totalPages = max(1, ceil($totalListings / $itemsPerPage));
            if ($currentPage > $totalPages) {
                $currentPage = $totalPages;
            }

            $offset = ($currentPage - 1) * $itemsPerPage;

            // Requête des annonces limitées
            $sqlListings = "SELECT l.*, c.name AS category_name 
                            FROM listings l 
                            LEFT JOIN categories c ON l.category_id = c.id 
                            " . $whereSql . " 
                            ORDER BY l.created_at DESC 
                            LIMIT :limit OFFSET :offset";

            $stmtListings = $db->prepare($sqlListings);
            foreach ($params as $key => $val) {
                $stmtListings->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmtListings->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
            $stmtListings->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmtListings->execute();
            $listings = $stmtListings->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $listings = [];
            $totalListings = 0;
            $totalPages = 1;
        }

        // Vérification de l'authentification
        $isLoggedIn = false;
        if (class_exists('Session') && method_exists('Session', 'get')) {
            $isLoggedIn = (bool) Session::get('user_id');
        } elseif (isset($_SESSION['user_id'])) {
            $isLoggedIn = true;
        }

        // Helper de génération d'URL
        $buildUrl = function(array $newParams = []): string {
            $queryParams = $_GET;
            foreach ($newParams as $key => $value) {
                if ($value === null) {
                    unset($queryParams[$key]);
                } else {
                    $queryParams[$key] = $value;
                }
            }
            return '?' . http_build_query($queryParams);
        };

        $getCategoryIcon = function(?string $name, array $map): string {
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
        };

        // Chargement du header séparé
        require_once __DIR__ . '/../views/layouts/header.php';
        ?>

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
                        <?php $icon = $getCategoryIcon($cat['name'] ?? '', $iconMap); ?>
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
                                $absolutePath = dirname(__DIR__, 2) . '/' . $cleanPath;
                                $imageSrc = file_exists($absolutePath) ? $baseUrl . '/' . $cleanPath : 'https://via.placeholder.com/600x400/1e293b/f59e0b?text=MAN+GO';
                            }
                            $cityLocation = !empty($item['city']) ? $item['city'] : (!empty($item['location']) ? $item['location'] : 'Lomé');
                        ?>
                        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-lg transition flex flex-col group">
                            <div class="relative h-52 bg-gray-100 overflow-hidden">
                                <img src="<?= htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                <?php if (!empty($item['category_name'])): ?>
                                    <span class="absolute top-3 left-3 bg-white/90 backdrop-blur-md text-gray-900 text-xs font-extrabold px-3 py-1 rounded-full shadow-sm">
                                        <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
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
                    <?php if ($currentPage > 1): ?>
                        <a href="<?= $buildUrl(['page' => $currentPage - 1]) ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-bold text-gray-700 hover:bg-amber-500 hover:text-slate-950 hover:border-amber-500 transition shadow-sm">
                            <i class="fa-solid fa-chevron-left mr-1"></i> Précédent
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-xs font-bold text-gray-400 cursor-not-allowed">
                            <i class="fa-solid fa-chevron-left mr-1"></i> Précédent
                        </span>
                    <?php endif; ?>

                    <div class="hidden sm:flex items-center space-x-1">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p == $currentPage): ?>
                                <span class="px-3.5 py-2 bg-amber-500 text-slate-950 border border-amber-500 rounded-lg text-xs font-black shadow-sm">
                                    <?= $p ?>
                                </span>
                            <?php else: ?>
                                <a href="<?= $buildUrl(['page' => $p]) ?>" class="px-3.5 py-2 bg-white border border-gray-300 rounded-lg text-xs font-bold text-gray-700 hover:bg-amber-50 hover:text-amber-600 transition">
                                    <?= $p ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <span class="sm:hidden text-xs font-bold text-gray-500 px-2">
                        Page <?= $currentPage ?> / <?= $totalPages ?>
                    </span>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= $buildUrl(['page' => $currentPage + 1]) ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-bold text-gray-700 hover:bg-amber-500 hover:text-slate-950 hover:border-amber-500 transition shadow-sm">
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

        <?php
        // Chargement du footer séparé
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}