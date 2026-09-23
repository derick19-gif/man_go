<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Autoloader.php';
require_once __DIR__ . '/../core/Database.php';

// Initialisation de la session
Session::init();

// Vérifier si l'utilisateur est connecté
if (!Session::isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// Aiguillage par rôle : si ce n'est pas un vendeur, on le renvoie vers l'espace client
if (Session::get('user_role') !== 'vendor') {
    header('Location: ../client/views/dashboard.php');
    exit;
}

$current_user_id = Session::getUserId();
$currency = $_SESSION['user_currency'] ?? 'FCFA';
$userName = Session::get('user_name') ?? 'Vendeur';
$userId = Session::get('user_id') ?? $current_user_id;

$dbInstance = \App\Core\Database::getInstance();
$db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;

// =====================================================================
// ACTION : SUPPRESSION D'UNE ANNONCE DEPUIS LE TABLEAU DE BORD
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_listing') {
    $listingIdToDelete = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
    if ($listingIdToDelete) {
        // Vérification de sécurité : s'assurer que l'annonce appartient bien à l'utilisateur
        $stmtCheck = $db->prepare("SELECT id FROM listings WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$listingIdToDelete, $userId]);
        if ($stmtCheck->fetch()) {
            $stmtDel = $db->prepare("DELETE FROM listings WHERE id = ?");
            $stmtDel->execute([$listingIdToDelete]);
            // Redirection pour éviter la resoumission du formulaire
            header('Location: dashboard.php?tab=tab-listings&msg=deleted');
            exit;
        }
    }
}

// =====================================================================
// Récupération des annonces du vendeur depuis la base de données
// =====================================================================
$myListings = [];
try {
    $stmt = $db->prepare("SELECT * FROM listings WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->execute([':user_id' => $userId]);
    $myListings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $myListings = []; 
}

$categoryNames = [
    1 => 'Électronique & High-Tech',
    2 => 'Services & Prestations',
    3 => 'Immobilier & Foncier',
    4 => 'Mode & Style',
    5 => 'Véhicules & Transports'
];

// Vérifier si on doit ouvrir un onglet spécifique (ex: après une suppression)
$activeTab = $_GET['tab'] ?? 'tab-stats';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Vendeur & Prestataire - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 500: '#f59e0b', 600: '#d97706', 950: '#090d16' } },
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 flex h-screen overflow-hidden relative">

    <!-- VOILE FONCÉ POUR MOBILE (Overlay) -->
    <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity opacity-0"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 h-full flex flex-col p-6 transform -translate-x-full lg:translate-x-0 lg:static transition-transform duration-300 ease-in-out shadow-2xl lg:shadow-none">
        
        <div class="flex items-center justify-between mb-10">
            <div class="flex items-center">
                <div class="bg-amber-400 text-slate-900 font-black rounded-lg flex items-center justify-center mr-3 shadow-sm" style="width: 40px; height: 40px; font-size: 1.2rem;">M</div>
                <h2 class="font-extrabold text-slate-900 text-xl m-0">Espace <span class="text-amber-500">Pro</span></h2>
            </div>
            <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-red-500 text-2xl transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <nav class="flex flex-col gap-2 flex-1">
            <button onclick="switchTab('tab-stats')" id="tab-stats-btn" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold transition-all bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-md">
                <i class="fa-solid fa-chart-line w-6 text-center mr-2"></i> Statistiques
            </button>
            <button onclick="switchTab('tab-listings')" id="tab-listings-btn" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-box-open w-6 text-center mr-2"></i> Mes Annonces
            </button>
            <button onclick="switchTab('tab-publish')" id="tab-publish-btn" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-plus-circle w-6 text-center mr-2"></i> Publier
            </button>

            <!-- NOUVEAU BOUTON : MA BOUTIQUE -->
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go' ?>/stands/create" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-store w-6 text-center mr-2"></i> Ma Boutique / Stand
            </a>

            <!-- NOUVEAU BOUTON : MA MESSAGERIE -->
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go' ?>/chat.php" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all mt-2">
                <i class="fa-solid fa-message w-6 text-center mr-2 text-indigo-400"></i> Ma Messagerie
                <span class="ml-auto bg-red-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full shadow-sm">Nouveau</span>
            </a>
            
            <hr class="border-slate-200 my-4">
            
            <button onclick="switchTab('tab-settings')" id="tab-settings-btn" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-robot w-6 text-center mr-2"></i> Rép. Auto
            </button>
            <button onclick="switchTab('tab-quick')" id="tab-quick-btn" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-bolt w-6 text-center mr-2"></i> Raccourcis
            </button>

            <!-- NOUVEAU BOUTON : PARAMÈTRES (Verrouillage profil, infos, etc.) -->
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go' ?>/settings" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all mt-auto">
                <i class="fa-solid fa-user-gear w-6 text-center mr-2"></i> Paramètres
            </a>
        </nav>

        <div class="mt-4 pt-4 border-t border-slate-200">
            <!-- 1. Bouton Retour au site en premier -->
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go' ?>/" class="w-full flex items-center justify-center px-4 py-3 border-2 border-slate-900 text-slate-900 rounded-full font-bold hover:bg-slate-900 hover:text-white transition-all mb-3">
                <i class="fa-solid fa-arrow-left mr-2"></i> Retour au site
            </a>

            <!-- 2. Bouton Déconnexion tout en bas -->
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go' ?>/logout.php" class="w-full flex items-center justify-center px-4 py-3 border-2 border-red-100 text-red-500 rounded-full font-bold hover:bg-red-50 transition-all">
                <i class="fa-solid fa-arrow-right-from-bracket mr-2"></i> Déconnexion
            </a>
        </div>
    </aside>

    <!-- ZONE DE CONTENU PRINCIPAL -->
    <div class="flex-1 flex flex-col h-full overflow-hidden">
        
        <!-- HEADER MOBILE -->
        <header class="lg:hidden bg-white border-b border-slate-200 h-16 flex items-center justify-between px-4 shrink-0 shadow-sm">
            <div class="flex items-center">
                <div class="bg-slate-900 text-white font-black rounded w-8 h-8 flex items-center justify-center mr-2">M</div>
                <span class="font-extrabold text-slate-900">MAN <span class="text-amber-500">GO</span></span>
            </div>
            <button onclick="toggleSidebar()" class="text-slate-600 hover:text-amber-500 text-2xl p-2 focus:outline-none transition">
                <i class="fa-solid fa-bars"></i>
            </button>
        </header>

        <!-- CONTENU DU DASHBOARD -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-8 lg:p-10">
            <header class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-8 gap-4">
                <h3 class="font-extrabold text-2xl text-slate-900 m-0">Bonjour, <?= htmlspecialchars($userName) ?> 👋</h3>
                <span class="bg-slate-900 text-amber-500 px-4 py-2 rounded-full text-sm font-bold shadow-sm self-start sm:self-auto">
                    <i class="fa-solid fa-store mr-1"></i> Compte Vendeur
                </span>
            </header>

            <!-- TAB 1: STATISTIQUES -->
            <div id="tab-stats" class="tab-pane">
                
                <?php
                // =====================================================================
                // ⚙️ RÉCUPÉRATION DES VRAIES DONNÉES DEPUIS LA BASE DE DONNÉES
                // =====================================================================
                
                try {
                    // 1. Trouver le stand de cet utilisateur et vérifier s'il a payé le Premium
                    $stmtStand = $db->prepare("SELECT id, is_premium FROM stands WHERE user_id = ? LIMIT 1");
                    $stmtStand->execute([$userId]);
                    $myStand = $stmtStand->fetch(PDO::FETCH_ASSOC);

                    $standId = $myStand ? $myStand['id'] : 0;
                    $isPremium = $myStand ? (bool)$myStand['is_premium'] : false;

                    // 2. Vraies Vues Totales
                    $stmtViews = $db->prepare("SELECT COUNT(*) FROM ad_views WHERE stand_id = ?");
                    $stmtViews->execute([$standId]);
                    $stats_views = $stmtViews->fetchColumn() ?: 0;
                    
                    // Simulation d'une croissance à 0% pour le moment
                    $stats_views_growth = 0;  
                    
                    // 3. Vrais Clics (Si vous n'avez pas encore de table pour les clics, on met à 0)
                    $stats_clicks = 0;
                    $stats_clicks_growth = 0;  
                    
                    // Taux d'intérêt calculé mathématiquement
                    $stats_rate = ($stats_views > 0) ? round(($stats_clicks / $stats_views) * 100, 1) : 0;

                    // 4. Vrais Abonnés (Followers)
                    $stmtFollowers = $db->prepare("SELECT COUNT(*) FROM followers WHERE stand_id = ?");
                    $stmtFollowers->execute([$standId]);
                    $stats_followers = $stmtFollowers->fetchColumn() ?: 0;
                    
                    // 5. Vraie Géolocalisation
                    $stmtGeo = $db->prepare("
                        SELECT CONCAT(country, ' (', city, ')') as name, COUNT(*) as total 
                        FROM ad_views 
                        WHERE stand_id = ? AND country != 'Inconnu'
                        GROUP BY country, city 
                        ORDER BY total DESC 
                        LIMIT 3
                    ");
                    $stmtGeo->execute([$standId]);
                    $geoData = $stmtGeo->fetchAll(PDO::FETCH_ASSOC);

                    $stats_locations = [];
                    $colors = ['bg-blue-500', 'bg-blue-400', 'bg-blue-300'];
                    $i = 0;
                    
                    if (count($geoData) > 0) {
                        foreach($geoData as $geo) {
                            $percent = ($stats_views > 0) ? round(($geo['total'] / $stats_views) * 100) : 0;
                            $stats_locations[] = [
                                'name' => $geo['name'],
                                'percent' => $percent,
                                'color' => $colors[$i] ?? 'bg-slate-300'
                            ];
                            $i++;
                        }
                    } else {
                        // S'il n'a pas encore de visites, on affiche un message vide
                        $stats_locations[] = ['name' => 'En attente de visiteurs...', 'percent' => 0, 'color' => 'bg-slate-200'];
                    }

                } catch (Exception $e) {
                    // Sécurité anti-crash au cas où la base de données met du temps à se mettre à jour
                    $isPremium = false;
                    $stats_views = $stats_clicks = $stats_rate = $stats_followers = 0;
                    $stats_views_growth = $stats_clicks_growth = 0;
                    $stats_locations = [['name' => 'Données indisponibles', 'percent' => 0, 'color' => 'bg-slate-200']];
                }
                ?>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-8 mt-2 gap-4">
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Performances du Stand</h1>
                        <p class="text-sm text-slate-500 mt-1">Analysez l'impact de vos annonces sur les 30 derniers jours.</p>
                    </div>
                    
                    <?php if (!$isPremium): ?>
                        <a href="upgrade.php" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg transition-all hover:-translate-y-0.5">
                            <i class="fa-solid fa-crown text-amber-500"></i> Passer en Premium
                        </a>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-2 bg-amber-100 text-amber-700 px-4 py-2.5 rounded-xl font-black text-sm border border-amber-200">
                            <i class="fa-solid fa-crown"></i> Compte PRO Actif
                        </span>
                    <?php endif; ?>
                </div>

                <!-- ================================================== -->
                <!-- SECTION 1 : STATISTIQUES GRATUITES -->
                <!-- ================================================== -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-semibold text-slate-500 mb-1">Vues Totales</p>
                                <h3 class="text-3xl font-black text-slate-900"><?= number_format($stats_views, 0, ',', ' ') ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-xl">
                                <i class="fa-solid fa-eye"></i>
                            </div>
                        </div>
                        <p class="text-xs font-medium text-emerald-500 mt-4 flex items-center gap-1">
                            <i class="fa-solid fa-arrow-trend-up"></i> +<?= $stats_views_growth ?>% cette semaine
                        </p>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-semibold text-slate-500 mb-1">Clics sur Annonces</p>
                                <h3 class="text-3xl font-black text-slate-900"><?= number_format($stats_clicks, 0, ',', ' ') ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center text-xl">
                                <i class="fa-solid fa-hand-pointer"></i>
                            </div>
                        </div>
                        <p class="text-xs font-medium text-emerald-500 mt-4 flex items-center gap-1">
                            <i class="fa-solid fa-arrow-trend-up"></i> +<?= $stats_clicks_growth ?>% cette semaine
                        </p>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-semibold text-slate-500 mb-1">Taux d'intérêt</p>
                                <h3 class="text-3xl font-black text-slate-900"><?= $stats_rate ?>%</h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl">
                                <i class="fa-solid fa-bolt"></i>
                            </div>
                        </div>
                        <p class="text-xs font-medium text-slate-400 mt-4">
                            Ratio clics / vues
                        </p>
                    </div>
                </div>

                <!-- ================================================== -->
                <!-- SECTION 2 : STATISTIQUES PREMIUM -->
                <!-- ================================================== -->
                <h2 class="text-lg font-black text-slate-900 mb-4">Analyses Avancées & Audience</h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 relative">
                    
                    <?php if (!$isPremium): ?>
                        <div class="absolute inset-0 z-10 bg-slate-50/60 backdrop-blur-sm rounded-3xl flex flex-col items-center justify-center border border-white/50">
                            <div class="bg-slate-900 p-8 rounded-3xl shadow-2xl text-center max-w-sm border border-slate-800 transform transition hover:scale-105 m-4">
                                <div class="w-16 h-16 bg-gradient-to-tr from-amber-400 to-orange-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-amber-500/30">
                                    <i class="fa-solid fa-lock text-white text-2xl"></i>
                                </div>
                                <h3 class="text-xl font-black text-white mb-2">Passez en mode PRO</h3>
                                <p class="text-slate-400 text-sm mb-6 leading-relaxed">Débloquez la géolocalisation de vos clients, activez le bouton "Suivre" et bâtissez votre communauté.</p>
                                <a href="upgrade.php" class="block w-full bg-amber-500 hover:bg-amber-400 text-slate-900 font-black py-3 rounded-xl transition shadow-[0_0_15px_rgba(245,158,11,0.4)]">
                                    Voir les abonnements
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm <?= !$isPremium ? 'opacity-50' : '' ?>">
                        <div class="flex items-center gap-3 mb-6">
                            <i class="fa-solid fa-earth-africa text-slate-400 text-xl"></i>
                            <h3 class="font-bold text-slate-800">Origine de vos visiteurs</h3>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($stats_locations as $location): ?>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="font-semibold text-slate-700"><?= htmlspecialchars($location['name']) ?></span>
                                        <span class="text-slate-500"><?= $location['percent'] ?>%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2">
                                        <div class="<?= $location['color'] ?> h-2 rounded-full" style="width: <?= $location['percent'] ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm flex flex-col items-center justify-center text-center <?= !$isPremium ? 'opacity-50' : '' ?>">
                        <div class="w-20 h-20 rounded-full bg-orange-50 flex items-center justify-center mb-4">
                            <i class="fa-solid fa-users text-3xl text-orange-500"></i>
                        </div>
                        <h3 class="text-4xl font-black text-slate-900 mb-2"><?= number_format($stats_followers, 0, ',', ' ') ?></h3>
                        <p class="font-bold text-slate-800 mb-1">Abonnés actifs</p>
                        <p class="text-sm text-slate-500 px-4">Ces clients reçoivent une notification à chaque fois que vous publiez un produit.</p>
                    </div>

                </div>
            </div>

            <!-- TAB 2: MES ANNONCES -->
            <div id="tab-listings" class="tab-pane hidden">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="font-bold text-xl m-0 text-slate-900">Gestion de mes annonces</h4>
                    <button onclick="switchTab('tab-publish')" class="bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold py-2 px-6 rounded-full hover:shadow-lg transition transform hover:-translate-y-0.5 text-sm">
                        <i class="fa-solid fa-plus mr-1"></i> Créer
                    </button>
                </div>
                
                <?php if(isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-xl text-sm font-bold flex items-center">
                        <i class="fa-solid fa-circle-check text-xl mr-3"></i> Annonce supprimée avec succès.
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                                <tr>
                                    <th class="py-4 px-6">Produit / Service</th>
                                    <th class="py-4 px-6">Catégorie</th>
                                    <th class="py-4 px-6">Prix</th>
                                    <th class="py-4 px-6 text-center">Vues</th>
                                    <th class="py-4 px-6">Statut</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                
                                <?php if (empty($myListings)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-16">
                                            <div class="bg-slate-50 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                                                <i class="fa-solid fa-box-open text-3xl text-slate-400"></i>
                                            </div>
                                            <h5 class="font-bold text-lg text-slate-900">Votre vitrine est vide</h5>
                                            <p class="text-slate-500 mt-1 mb-4">Commencez à vendre en publiant votre première annonce.</p>
                                            <button onclick="switchTab('tab-publish')" class="border-2 border-slate-900 text-slate-900 font-bold py-2 px-6 rounded-full hover:bg-slate-900 hover:text-white transition">Publier maintenant</button>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($myListings as $listing): ?>
                                        <?php 
                                            // Récupérer le nombre de vues spécifique à cette annonce
                                            $stmtAdView = $db->prepare("SELECT COUNT(*) FROM ad_views WHERE listing_id = ?");
                                            $stmtAdView->execute([$listing['id']]);
                                            $adViewsCount = $stmtAdView->fetchColumn() ?: 0;
                                        ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="py-4 px-6 flex items-center">
                                                <?php if (!empty($listing['image_path'])): ?>
                                                    <img src="../<?= htmlspecialchars($listing['image_path']) ?>" class="w-12 h-12 rounded-lg object-cover mr-4 shadow-sm">
                                                <?php else: ?>
                                                    <div class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center mr-4 text-slate-400 shadow-sm"><i class="fa-solid fa-camera"></i></div>
                                                <?php endif; ?>
                                                <span class="font-bold text-slate-900 truncate max-w-[200px]">
                                                    <a href="../listing-detail.php?id=<?= $listing['id'] ?>" target="_blank" class="hover:text-amber-500 transition">
                                                        <?= htmlspecialchars($listing['title']) ?>
                                                    </a>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-slate-500 font-medium">
                                                <?= $categoryNames[$listing['category_id']] ?? 'Général' ?>
                                            </td>
                                            <td class="py-4 px-6 font-black text-amber-600">
                                                <?= number_format($listing['price'], 0, ',', ' ') ?> <?= $currency ?>
                                            </td>
                                            <td class="py-4 px-6 text-center">
                                                <span class="bg-blue-50 text-blue-600 px-2.5 py-1 rounded-md text-xs font-bold">
                                                    <i class="fa-solid fa-eye mr-1"></i> <?= $adViewsCount ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <?php if($listing['status'] === 'active'): ?>
                                                    <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide">Actif</span>
                                                <?php elseif($listing['status'] === 'scheduled'): ?>
                                                    <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide" title="<?= date('d/m/Y H:i', strtotime($listing['scheduled_at'])) ?>"><i class="fa-regular fa-clock"></i> Programmé</span>
                                                <?php else: ?>
                                                    <span class="bg-slate-200 text-slate-600 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide"><?= htmlspecialchars($listing['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4 px-6 text-right flex justify-end space-x-2">
                                                <!-- Bouton Modifier (Lien vers publish.php) -->
                                                <a href="../publish.php?id=<?= $listing['id'] ?>" class="text-slate-400 hover:text-blue-500 p-2 transition" title="Modifier">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                
                                                <!-- Formulaire de Suppression -->
                                                <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette annonce ?');" class="inline">
                                                    <input type="hidden" name="action" value="delete_listing">
                                                    <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                                                    <button type="submit" class="text-slate-400 hover:text-red-500 p-2 transition" title="Supprimer">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: PUBLIER UNE ANNONCE -->
            <div id="tab-publish" class="tab-pane hidden">
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-10 text-center shadow-sm relative overflow-hidden">
                    <div class="absolute inset-0" style="background: radial-gradient(circle at top right, rgba(245, 158, 11, 0.05), transparent 40%); pointer-events: none;"></div>
                    <div class="relative z-10 py-4 sm:py-8">
                        <i class="fa-solid fa-rocket text-amber-500 text-5xl sm:text-6xl mb-6" style="filter: drop-shadow(0 0 15px rgba(245,158,11,0.4));"></i>
                        <h2 class="font-extrabold text-2xl sm:text-3xl text-slate-900 mb-4">Prêt à conquérir le marché ?</h2>
                        <p class="text-slate-500 text-base sm:text-lg mb-8 max-w-2xl mx-auto px-4">
                            <strong class="text-slate-900">One Market, One Movement.</strong><br>
                            Que vous soyez artisan, entreprise, ou prestataire de services, MAN GO connecte vos offres au monde entier.
                        </p>
                        <a href="../publish.php" class="inline-block bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold py-3 px-6 sm:py-4 sm:px-8 rounded-full shadow-lg hover:shadow-xl transition transform hover:-translate-y-1">
                            <i class="fa-solid fa-pen-nib mr-2"></i> Accéder à l'éditeur complet
                        </a>
                    </div>
                </div>
            </div>

            <!-- TAB 4: PARAMÈTRES BUSINESS -->
            <div id="tab-settings" class="tab-pane hidden">
                <h4 class="font-bold text-xl mb-6 text-slate-900">Chat & Réponses Automatiques</h4>
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm">
                    <form id="businessSettingsForm" onsubmit="saveBusinessSettings(event)">
                         <div class="bg-slate-50 p-6 rounded-xl border border-slate-100 mb-6">
                            <label class="flex items-center cursor-pointer mb-2">
                                <div class="relative">
                                    <input type="checkbox" id="isAway" class="sr-only">
                                    <div class="block bg-slate-300 w-10 h-6 rounded-full transition-colors" id="bg-isAway"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition transform" id="dot-isAway"></div>
                                </div>
                                <div class="ml-3 font-bold text-slate-900">Activer le Mode Absence</div>
                            </label>
                            <p class="text-xs sm:text-sm text-slate-500 ml-14 mb-4">Répond automatiquement à tous les messages reçus.</p>
                            <div class="ml-0 sm:ml-14 mt-4 sm:mt-0">
                                <label class="block text-sm font-bold text-slate-700 mb-2">Message d'absence</label>
                                <textarea id="autoReplyMessage" class="w-full border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none text-sm" rows="3" placeholder="Bonjour, je suis actuellement indisponible..."></textarea>
                            </div>
                        </div>
                        <div class="text-right mt-8">
                            <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold py-3 px-8 rounded-full shadow hover:shadow-lg transition">
                                <i class="fa-solid fa-save mr-2"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 5: RÉPONSES RAPIDES -->
            <div id="tab-quick" class="tab-pane hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                    <h4 class="font-bold text-xl m-0 text-slate-900">Gestion des Raccourcis</h4>
                    <button onclick="openQuickModal()" class="w-full sm:w-auto bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold py-2 px-5 rounded-full text-sm shadow hover:shadow-lg transition">
                        <i class="fa-solid fa-plus mr-1"></i> Ajouter un raccourci
                    </button>
                </div>
                 <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                                <tr>
                                    <th class="py-4 px-6">Raccourci</th>
                                    <th class="py-4 px-6">Message</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="quickRepliesTable" class="divide-y divide-slate-100">
                                <tr><td colspan="3" class="text-center py-8 text-slate-500">Chargement...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL AJOUT RÉPONSE RAPIDE -->
    <div id="quickReplyModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center opacity-0 transition-opacity duration-300 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md transform scale-95 transition-transform duration-300" id="quickReplyModalContent">
            <div class="flex justify-between items-center p-6 border-b border-slate-100">
                <h5 class="font-extrabold text-xl text-slate-900">Nouveau Raccourci</h5>
                <button onclick="closeQuickModal()" class="text-slate-400 hover:text-slate-700 transition text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6">
                <form id="quickReplyForm" onsubmit="saveQuickReply(event)">
                    <div class="mb-5">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Raccourci</label>
                        <input type="text" id="quickShortcut" class="w-full border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Message</label>
                        <textarea id="quickMessage" class="w-full border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold py-3 rounded-xl shadow hover:shadow-lg transition">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>

    <!-- TOUT LE JAVASCRIPT COMPLET -->
    <script>
        const CURRENCY = <?=json_encode($currency)?>;
        const INITIAL_TAB = <?= json_encode($activeTab) ?>;

        document.addEventListener('DOMContentLoaded', () => {
            loadVendorStats();
            loadBusinessSettings();
            loadQuickReplies();
            setupToggleSwitches();
            // Ouvrir le bon onglet (ex: après une suppression, on reste sur l'onglet Annonces)
            switchTab(INITIAL_TAB);
        });

        // Menu Mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('-translate-x-full'); 
            
            if(overlay.classList.contains('hidden')) {
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            } else {
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }

        // Navigation par onglets
        function switchTab(tabId) {
            document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.nav-link').forEach(el => {
                el.className = 'nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all';
            });
            
            const targetPane = document.getElementById(tabId);
            if(targetPane) targetPane.classList.remove('hidden');
            
            const activeBtn = document.getElementById(tabId + '-btn');
            if(activeBtn) activeBtn.className = 'nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold transition-all bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-md';
            
            if(window.innerWidth < 1024) toggleSidebar(); 
        }

        // Animation des boutons switch
        function setupToggleSwitches() {
            ['isAway', 'autoReplyEnabled'].forEach(id => {
                const checkbox = document.getElementById(id);
                if(checkbox) {
                    const bg = document.getElementById('bg-' + (id==='isAway'?'isAway':'autoReply'));
                    const dot = document.getElementById('dot-' + (id==='isAway'?'isAway':'autoReply'));
                    
                    checkbox.addEventListener('change', (e) => {
                        if(e.target.checked) {
                            bg.classList.replace('bg-slate-300', 'bg-amber-500');
                            dot.classList.add('translate-x-4');
                        } else {
                            bg.classList.replace('bg-amber-500', 'bg-slate-300');
                            dot.classList.remove('translate-x-4');
                        }
                    });
                }
            });
        }

        // Gestion du Modal
        function openQuickModal() {
            const modal = document.getElementById('quickReplyModal');
            const modalContent = document.getElementById('quickReplyModalContent');
            document.getElementById('quickReplyForm').reset();
            modal.classList.remove('hidden');
            setTimeout(() => { modal.classList.remove('opacity-0'); modalContent.classList.remove('scale-95'); }, 10);
        }

        function closeQuickModal() {
            const modal = document.getElementById('quickReplyModal');
            const modalContent = document.getElementById('quickReplyModalContent');
            modal.classList.add('opacity-0');
            modalContent.classList.add('scale-95');
            setTimeout(() => { modal.classList.add('hidden'); }, 300);
        }

        // =====================================
        // APPELS API (STATS, CHAT, RACCOURCIS)
        // =====================================
        async function loadVendorStats() {
            try {
                const res = await fetch('../api/vendor.php?action=getStats');
                const data = await res.json();
                const container = document.getElementById('statsContainer');
                if(!container) return; // Sécurité si l'élément n'existe pas
                
                container.innerHTML = `
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <div class="bg-amber-50 text-amber-500 w-14 h-14 rounded-xl flex items-center justify-center text-2xl"><i class="fa-solid fa-box"></i></div>
                        <div>
                            <p class="text-sm text-slate-500 font-bold">Annonces Actives</p>
                            <h4 class="font-black text-2xl text-slate-900 m-0">${data.active_products || 0}</h4>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <div class="bg-emerald-50 text-emerald-500 w-14 h-14 rounded-xl flex items-center justify-center text-2xl"><i class="fa-solid fa-cart-check"></i></div>
                        <div>
                            <p class="text-sm text-slate-500 font-bold">Ventes Réalisées</p>
                            <h4 class="font-black text-2xl text-slate-900 m-0">${data.total_sales || 0}</h4>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <div class="bg-blue-50 text-blue-500 w-14 h-14 rounded-xl flex items-center justify-center text-2xl"><i class="fa-solid fa-wallet"></i></div>
                        <div>
                            <p class="text-sm text-slate-500 font-bold">Revenus Total</p>
                            <h4 class="font-black text-2xl text-slate-900 m-0">${Number(data.total_revenue || 0).toLocaleString()} ${CURRENCY}</h4>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <div class="bg-indigo-50 text-indigo-500 w-14 h-14 rounded-xl flex items-center justify-center text-2xl"><i class="fa-solid fa-comments"></i></div>
                        <div>
                            <p class="text-sm text-slate-500 font-bold">Discussions</p>
                            <h4 class="font-black text-2xl text-slate-900 m-0">${data.active_chats || 0}</h4>
                        </div>
                    </div>
                `;
            } catch (e) {
                console.error("Erreur Stats:", e);
                const container = document.getElementById('statsContainer');
                if(container) container.innerHTML = `<p class="col-span-full text-red-500">Erreur lors du chargement des statistiques.</p>`;
            }
        }

        async function loadBusinessSettings() {
            try {
                const res = await fetch('../api/chat.php?action=getBusinessSettings');
                const data = await res.json();

                const isAwayCb = document.getElementById('isAway');
                if(isAwayCb) {
                    isAwayCb.checked = data.is_away == 1;
                    isAwayCb.dispatchEvent(new Event('change'));
                }
                
                const autoReplyCb = document.getElementById('autoReplyEnabled');
                if(autoReplyCb) {
                    autoReplyCb.checked = data.auto_reply_enabled == 1;
                    autoReplyCb.dispatchEvent(new Event('change'));
                }

                if(document.getElementById('autoReplyMessage')) document.getElementById('autoReplyMessage').value = data.auto_reply_message || '';
                if(document.getElementById('welcomeMessage')) document.getElementById('welcomeMessage').value = data.welcome_message || '';
            } catch (e) { console.error("Erreur Settings:", e); }
        }

        async function saveBusinessSettings(e) {
            e.preventDefault();
            const formData = new URLSearchParams({
                action: 'saveBusinessSettings',
                is_away: document.getElementById('isAway').checked ? 1 : 0,
                auto_reply_message: document.getElementById('autoReplyMessage').value,
                auto_reply_enabled: document.getElementById('autoReplyEnabled') ? (document.getElementById('autoReplyEnabled').checked ? 1 : 0) : 0,
                welcome_message: document.getElementById('welcomeMessage') ? document.getElementById('welcomeMessage').value : ''
            });

            try {
                const res = await fetch('../api/chat.php', { method: 'POST', body: formData });
                const result = await res.json();
                if (result.status === 'success') alert('Paramètres sauvegardés avec succès !');
            } catch (e) { console.error(e); }
        }

        async function loadQuickReplies() {
            try {
                const res = await fetch('../api/chat.php?action=getQuickReplies');
                const replies = await res.json();
                const tbody = document.getElementById('quickRepliesTable');
                if(!tbody) return;
                
                if (!replies || replies.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-8 text-slate-500">Aucun raccourci enregistré.</td></tr>';
                    return;
                }

                let html = '';
                replies.forEach(r => {
                    html += `
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-4 px-6"><span class="bg-slate-900 text-white rounded-md px-2 py-1 text-xs font-mono">${escapeHtml(r.shortcut)}</span></td>
                            <td class="py-4 px-6 text-slate-600">${escapeHtml(r.message)}</td>
                            <td class="py-4 px-6 text-right">
                                <button onclick="deleteQuickReply(${r.id})" class="text-red-400 hover:text-red-600 hover:bg-red-50 p-2 rounded-full transition" title="Supprimer">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } catch (e) { console.error("Erreur Shortcuts:", e); }
        }

        async function saveQuickReply(e) {
            e.preventDefault();
            const formData = new URLSearchParams({
                action: 'addQuickReply',
                shortcut: document.getElementById('quickShortcut').value,
                message: document.getElementById('quickMessage').value
            });

            try {
                const res = await fetch('../api/chat.php', { method: 'POST', body: formData });
                const result = await res.json();
                if (result.status === 'success') {
                    closeQuickModal();
                    loadQuickReplies();
                }
            } catch (e) { console.error(e); }
        }

        async function deleteQuickReply(id) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer ce raccourci ?')) return;
            try {
                const res = await fetch(`../api/chat.php?action=deleteQuickReply&id=${id}`, { method: 'POST' });
                const result = await res.json();
                if (result.status === 'success') loadQuickReplies();
            } catch (e) { console.error(e); }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        }
    </script>
</body>
</html>