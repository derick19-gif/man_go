<?php

use App\Core\Database;

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

        // Récupération des catégories (élargies pour la vision internationale)
        try {
            $stmtCats = $db->query("SELECT * FROM categories ORDER BY name ASC LIMIT 12");
            $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $categories = [];
        }

        // Mapping des icônes FontAwesome étendu pour les multiples domaines d'activités
        $iconMap = [
            'electronique' => 'fa-laptop',
            'high-tech'    => 'fa-mobile-screen-button',
            'emploi'       => 'fa-user-tie',
            'services'     => 'fa-handshake',
            'immobilier'   => 'fa-building',
            'maison'       => 'fa-couch',
            'jardin'       => 'fa-leaf',
            'mode'         => 'fa-shirt',
            'vehicule'     => 'fa-car',
            'auto'         => 'fa-car',
            'hotel'        => 'fa-hotel',
            'restauration' => 'fa-utensils',
            'artisan'      => 'fa-hammer',
            'ong'          => 'fa-globe',
            'droit'        => 'fa-scale-balanced',
            'comptable'    => 'fa-calculator'
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

        // =========================================================================
        // DÉBUT DE LA VUE HTML (Sera séparé plus tard dans app/views/home.php)
        // =========================================================================
        
        // Chargement du header futuriste
        require_once __DIR__ . '/../views/layouts/header.php';
        ?>

        <!-- Arrière-plan : Slider Dynamique de 4 secteurs d'activités -->
            <div class="absolute inset-0 z-0 overflow-hidden" id="hero-slider">
                
                <!-- Image 1 : Tech & Business (Active par défaut) -->
                <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1920&q=80" 
                     class="bg-slide absolute w-full h-full object-cover transition-opacity duration-1000 opacity-60 mix-blend-overlay" />
                
                <!-- Image 2 : Logistique & Transport Mondiale -->
                <img src="https://images.unsplash.com/photo-1586528116311-ad8ed7c663b0?auto=format&fit=crop&w=1920&q=80" 
                     class="bg-slide absolute w-full h-full object-cover transition-opacity duration-1000 opacity-0 mix-blend-overlay" />
                
                <!-- Image 3 : BTP & Immobilier -->
                <img src="https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1920&q=80" 
                     class="bg-slide absolute w-full h-full object-cover transition-opacity duration-1000 opacity-0 mix-blend-overlay" />
                
                <!-- Image 4 : Artisanat & Commerce local -->
                <img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1920&q=80" 
                     class="bg-slide absolute w-full h-full object-cover transition-opacity duration-1000 opacity-0 mix-blend-overlay" />
                
                <!-- Dégradé ajusté : Moins sombre pour rendre les images beaucoup plus claires -->
                <div class="absolute inset-0 bg-gradient-to-b from-slate-950/30 via-slate-900/50 to-slate-950"></div>
            </div>

            <!-- Script d'animation du Slider -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const slides = document.querySelectorAll('.bg-slide');
                    let currentSlide = 0;
                    
                    if(slides.length > 0) {
                        setInterval(() => {
                            // Cacher l'image actuelle
                            slides[currentSlide].classList.remove('opacity-60');
                            slides[currentSlide].classList.add('opacity-0');
                            
                            // Passer à l'image suivante
                            currentSlide = (currentSlide + 1) % slides.length;
                            
                            // Afficher la nouvelle image
                            slides[currentSlide].classList.remove('opacity-0');
                            slides[currentSlide].classList.add('opacity-60');
                        }, 5000); // L'image change toutes les 5 secondes
                    }
                });
            </script>

            <!-- Contenu du Héros -->
            <div class="max-w-5xl mx-auto relative z-10">
                
                <!-- LE SLOGAN STARTUP -->
                <div class="inline-flex items-center space-x-2 mb-6 px-5 py-2 rounded-full border border-amber-500/40 bg-amber-500/10 backdrop-blur-md shadow-glow animate-pulse">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                    </span>
                    <span class="text-amber-400 font-extrabold tracking-widest text-sm uppercase">
                        One Market, One Movement.
                    </span>
                </div>

                <h1 class="text-4xl sm:text-6xl md:text-7xl font-black tracking-tight leading-tight mb-6">
                    L'écosystème mondial de <br class="hidden sm:block">
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-amber-600 drop-shadow-lg">MAN GO</span>
                </h1>
                
                <p class="text-gray-300 text-base sm:text-xl mt-4 max-w-3xl mx-auto font-medium leading-relaxed mb-10">
                    Artisans, startups, multinationales, avocats, restaurants ou particuliers. 
                    Quel que soit votre domaine, connectez-vous au marché universel sans limite.
                </p>

                <!-- Formulaire de recherche intelligent -->
                <form action="<?= $baseUrl ?>/" method="GET" class="mt-8 bg-white/10 backdrop-blur-xl p-3 rounded-2xl sm:rounded-full border border-white/20 shadow-2xl flex flex-col sm:flex-row gap-2 max-w-4xl mx-auto">
                    <?php if(!empty($lang)): ?>
                        <input type="hidden" name="lang" value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
            
                    <div class="flex-1 flex items-center bg-white rounded-xl sm:rounded-full px-5 py-3.5 text-gray-800 shadow-inner">
                        <i class="fa-solid fa-magnifying-glass text-amber-500 text-lg mr-3"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Un bien, un service, une entreprise..." class="w-full text-base bg-transparent border-none focus:outline-none placeholder-gray-400 font-medium">
                    </div>
                    
                    <div class="sm:w-1/3 flex items-center bg-white rounded-xl sm:rounded-full px-5 py-3.5 text-gray-800 shadow-inner">
                        <i class="fa-solid fa-location-dot text-amber-500 text-lg mr-3"></i>
                        <input type="text" name="city" value="<?= htmlspecialchars($search_city, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ville ou Pays" class="w-full text-base bg-transparent border-none focus:outline-none placeholder-gray-400 font-medium">
                    </div>
                    
                    <button type="submit" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black px-10 py-3.5 rounded-xl sm:rounded-full text-base transition transform hover:-translate-y-1 shadow-futuristic flex items-center justify-center space-x-2">
                        <span>Explorer</span>
                    </button>
                </form>
            </div>
        </section>

        <!-- Section Exploration par Catégories (Vaste et diversifiée) -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full relative z-20 -mt-10">
            <div class="flex items-center space-x-3 mb-8 bg-slate-950 inline-flex px-6 py-2 rounded-full border border-slate-800 shadow-lg">
                <i class="fa-solid fa-globe text-amber-500 text-xl"></i>
                <h2 class="text-xl font-bold text-white">Un monde d'opportunités</h2>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-5">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <?php $icon = $getCategoryIcon($cat['name'] ?? '', $iconMap); ?>
                        <a href="<?= $baseUrl ?>/listings?category=<?= (int)($cat['id'] ?? 0) ?>" class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:border-amber-400 transition-all duration-300 transform hover:-translate-y-2 flex flex-col items-center text-center group">
                            <div class="w-14 h-14 bg-slate-50 text-slate-400 rounded-2xl flex items-center justify-center mb-4 group-hover:bg-amber-500 group-hover:text-slate-950 transition-colors duration-300 shadow-sm group-hover:shadow-glow">
                                <i class="fa-solid <?= $icon ?> text-2xl"></i>
                            </div>
                            <span class="text-sm font-extrabold text-slate-800 group-hover:text-amber-600 transition leading-snug">
                                <?= htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback étendu si la base de données est vide pour montrer l'étendue du marché -->
                    <a href="<?= $baseUrl ?>/listings" class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center group">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 group-hover:bg-amber-500 group-hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-building text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 group-hover:text-amber-600">Immobilier & Foncier</span>
                    </a>
                    <a href="<?= $baseUrl ?>/listings" class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center group">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 group-hover:bg-amber-500 group-hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-hotel text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 group-hover:text-amber-600">Hôtellerie & Restauration</span>
                    </a>
                    <a href="<?= $baseUrl ?>/listings" class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center group">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 group-hover:bg-amber-500 group-hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-scale-balanced text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 group-hover:text-amber-600">Avocats & Juristes</span>
                    </a>
                    <a href="<?= $baseUrl ?>/listings" class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center group">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 group-hover:bg-amber-500 group-hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-truck-fast text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 group-hover:text-amber-600">Logistique & Livraison</span>
                    </a>
                    <a href="<?= $baseUrl ?>/listings" class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center group">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 group-hover:bg-amber-500 group-hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-laptop text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 group-hover:text-amber-600">Tech & Digital</span>
                    </a>
                    <a href="<?= $baseUrl ?>/listings" class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center group">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 group-hover:bg-amber-500 group-hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-hammer text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 group-hover:text-amber-600">Artisans & Bâtiment</span>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Section Dernières Annonces -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mb-20 w-full flex-1">
            <div class="flex items-center justify-between mb-10">
                <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-bolt text-amber-500 text-2xl"></i>
                    <h2 class="text-2xl sm:text-3xl font-black text-gray-900">
                        <?= (!empty($search_query) || !empty($search_city)) ? 'Résultats de votre recherche' : 'En direct du marché' ?>
                    </h2>
                    <span class="text-sm font-bold bg-slate-100 text-slate-600 px-3 py-1 rounded-full shadow-inner">
                        <?= $totalListings ?> offres
                    </span>
                </div>
                <a href="<?= $baseUrl ?>/listings" class="hidden sm:flex items-center space-x-2 text-slate-900 bg-amber-500 hover:bg-amber-400 px-5 py-2.5 rounded-full text-sm font-bold transition shadow-sm">
                    <span>Tout explorer</span>
                    <i class="fa-solid fa-arrow-right-long"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
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
                            $cityLocation = !empty($item['city']) ? $item['city'] : (!empty($item['location']) ? $item['location'] : 'International');
                        ?>
                        <div class="bg-white rounded-3xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-300 flex flex-col group transform hover:-translate-y-2">
                            <div class="relative h-60 bg-slate-100 overflow-hidden">
                                <img src="<?= htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                                <?php if (!empty($item['category_name'])): ?>
                                    <span class="absolute top-4 left-4 bg-slate-950/80 backdrop-blur-md text-white text-xs font-black uppercase tracking-wider px-4 py-1.5 rounded-full shadow-lg">
                                        <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="p-6 flex flex-col flex-1 justify-between">
                                <div>
                                    <h3 class="font-black text-slate-900 text-xl line-clamp-2 group-hover:text-amber-500 transition leading-tight mb-3">
                                        <a href="<?= $baseUrl ?>/listings/<?= (int)($item['id'] ?? 0) ?>">
                                            <?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </h3>
                                    <p class="text-sm text-slate-500 flex items-center font-medium">
                                        <i class="fa-solid fa-earth-americas mr-2 text-slate-400"></i>
                                        <?= htmlspecialchars($cityLocation, ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </div>
                                <div class="mt-6 pt-5 border-t border-gray-100 flex items-center justify-between">
                                    <div>
                                        <span class="text-2xl font-black text-slate-900">
                                            <?= number_format((float)($item['price'] ?? 0), 0, ',', ' ') ?> 
                                            <span class="text-amber-500 text-lg"><?= htmlspecialchars($item['currency'] ?? 'FCFA', ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                    </div>
                                    <a href="<?= $baseUrl ?>/listings/<?= (int)($item['id'] ?? 0) ?>" class="bg-slate-100 text-slate-900 group-hover:bg-amber-500 group-hover:text-white w-10 h-10 rounded-full flex items-center justify-center transition">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full bg-slate-50 p-16 rounded-3xl border border-dashed border-gray-300 text-center">
                        <div class="w-20 h-20 bg-white shadow-sm rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fa-solid fa-globe text-4xl text-amber-500"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">Le marché vous attend</h3>
                        <p class="text-slate-500 font-medium mb-6 max-w-md mx-auto">Aucune annonce ne correspond à votre recherche pour le moment.</p>
                        <a href="<?= $baseUrl ?>/publish" class="inline-block bg-slate-900 hover:bg-amber-500 text-white hover:text-slate-950 font-bold px-8 py-3.5 rounded-full transition shadow-lg">
                            Soyez le premier à publier !
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination dynamique -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-16 flex justify-center items-center space-x-3">
                    <?php if ($currentPage > 1): ?>
                        <a href="<?= $buildUrl(['page' => $currentPage - 1]) ?>" class="px-5 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-amber-500 hover:text-white transition shadow-sm">
                            <i class="fa-solid fa-arrow-left mr-2"></i> Précédent
                        </a>
                    <?php else: ?>
                        <span class="px-5 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold text-gray-300 cursor-not-allowed">
                            <i class="fa-solid fa-arrow-left mr-2"></i> Précédent
                        </span>
                    <?php endif; ?>

                    <div class="hidden sm:flex items-center space-x-2">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p == $currentPage): ?>
                                <span class="w-10 h-10 flex items-center justify-center bg-slate-900 text-white rounded-xl text-sm font-black shadow-md">
                                    <?= $p ?>
                                </span>
                            <?php else: ?>
                                <a href="<?= $buildUrl(['page' => $p]) ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-amber-100 hover:text-amber-600 transition">
                                    <?= $p ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= $buildUrl(['page' => $currentPage + 1]) ?>" class="px-5 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-amber-500 hover:text-white transition shadow-sm">
                            Suivant <i class="fa-solid fa-arrow-right ml-2"></i>
                        </a>
                    <?php else: ?>
                        <span class="px-5 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold text-gray-300 cursor-not-allowed">
                            Suivant <i class="fa-solid fa-arrow-right ml-2"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php
        // Chargement du footer
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}