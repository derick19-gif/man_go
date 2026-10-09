<?php
// =========================================================================
// TABLEAU DE BORD VENDEUR - MAN GO PRO
// =========================================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Autoloader.php';
require_once __DIR__ . '/../core/Database.php';

// Initialisation de la session
Session::init();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// Vérifier si l'utilisateur est connecté
if (!Session::isAuthenticated()) {
    header("Location: $baseUrl/login.php");
    exit;
}

// Aiguillage par rôle
if (Session::get('user_role') !== 'vendor') {
    header("Location: $baseUrl/client/views/dashboard.php");
    exit;
}

$current_user_id = Session::getUserId();
$currency = $_SESSION['user_currency'] ?? 'FCFA';
$userName = Session::get('user_name') ?? 'Vendeur';
$userId = Session::get('user_id') ?? $current_user_id;

$dbInstance = \App\Core\Database::getInstance();
$db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;

// =====================================================================
// VÉRIFICATION DE L'ABONNEMENT
// =====================================================================
$stmtSub = $db->prepare("
    SELECT s.end_date, p.name as plan_name, p.id as plan_id
    FROM user_subscriptions s
    JOIN subscription_plans p ON s.plan_id = p.id
    WHERE s.user_id = ? AND s.status = 'active' AND s.end_date > NOW()
    ORDER BY s.id DESC LIMIT 1
");
$stmtSub->execute([$_SESSION['user_id']]);
$activeSub = $stmtSub->fetch(PDO::FETCH_ASSOC);

$isPremium = false;
$currentPlanName = '';
$daysRemaining = 0;

$planBadgeColor = 'bg-amber-100 text-amber-700 border-amber-200'; 
$planIconColor = 'text-amber-500';

if ($activeSub) {
    $isPremium = true;
    $currentPlanName = $activeSub['plan_name'];
    $endDate = new DateTime($activeSub['end_date']);
    $now = new DateTime();
    $daysRemaining = $now->diff($endDate)->days;
    
    if ($activeSub['plan_id'] == 3) { 
        $planBadgeColor = 'bg-blue-100 text-blue-700 border-blue-200';
        $planIconColor = 'text-blue-500';
    }
}

// =====================================================================
// SUPPRESSION D'UNE ANNONCE
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_listing') {
    $listingIdToDelete = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
    if ($listingIdToDelete) {
        $stmtCheck = $db->prepare("SELECT id FROM listings WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$listingIdToDelete, $userId]);
        if ($stmtCheck->fetch()) {
            $stmtDel = $db->prepare("DELETE FROM listings WHERE id = ?");
            $stmtDel->execute([$listingIdToDelete]);
            header("Location: dashboard.php?tab=tab-listings&msg=deleted");
            exit;
        }
    }
}

// =====================================================================
// RÉCUPÉRATION DES ANNONCES & COMMANDES RÉELLES
// =====================================================================
$myListings = [];
$mySales = []; // AJOUT POUR LES VENTES
$total_sales = 0;
$total_revenue = 0;

try {
    // Les annonces
    $stmt = $db->prepare("SELECT * FROM listings WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->execute([':user_id' => $userId]);
    $myListings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $active_products = count($myListings);

    // Les vraies ventes (Table 'orders')
    $stmtOrders = $db->prepare("
        SELECT COUNT(id) as total_sales, SUM(amount) as total_revenue 
        FROM orders 
        WHERE vendor_id = ? AND status IN ('paid', 'shipped', 'delivered')
    ");
    $stmtOrders->execute([$userId]);
    $orderStats = $stmtOrders->fetch(PDO::FETCH_ASSOC);
    if ($orderStats) {
        $total_sales = (int)$orderStats['total_sales'];
        $total_revenue = (float)$orderStats['total_revenue'];
    }

    // AJOUT : Récupération détaillée des ventes pour le tableau et les reçus
    $stmtMySales = $db->prepare("
        SELECT o.*, l.title as listing_title, u.firstname as buyer_fname, u.lastname as buyer_lname 
        FROM orders o 
        JOIN listings l ON o.listing_id = l.id 
        JOIN users u ON o.buyer_id = u.id 
        WHERE o.vendor_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmtMySales->execute([$userId]);
    $mySales = $stmtMySales->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $myListings = []; 
    $mySales = [];
    $active_products = 0;
}

$categoryNames = [
    1 => 'Électronique & High-Tech',
    2 => 'Services & Prestations',
    3 => 'Immobilier & Foncier',
    4 => 'Mode & Style',
    5 => 'Véhicules & Transports'
];

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
    <style>
        #sidebar nav::-webkit-scrollbar { width: 4px; }
        #sidebar nav::-webkit-scrollbar-track { background: transparent; }
        #sidebar nav::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
        #sidebar nav::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 flex h-screen overflow-hidden relative">

    <!-- VOILE FONCÉ MOBILE -->
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
            
            <!-- AJOUT : BOUTON MES VENTES DANS LE MENU -->
            <button onclick="switchTab('tab-sales')" id="tab-sales-btn" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-cart-arrow-down w-6 text-center mr-2 text-emerald-500"></i> Mes Ventes
            </button>

            <a href="<?= $baseUrl ?>/publish.php" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-plus-circle w-6 text-center mr-2"></i> Publier
            </a>

            <a href="<?= $baseUrl ?>/settings.php" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-store w-6 text-center mr-2"></i> Ma Boutique (SEO)
            </a>

            <a href="<?= $baseUrl ?>/chat.php" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all mt-2">
                <i class="fa-solid fa-message w-6 text-center mr-2 text-indigo-400"></i> Ma Messagerie
            </a>
            
            <hr class="border-slate-200 my-4">
            
            <button onclick="switchTab('tab-settings')" id="tab-settings-btn" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-robot w-6 text-center mr-2"></i> Rép. Auto
            </button>
            <button onclick="switchTab('tab-quick')" id="tab-quick-btn" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-bolt w-6 text-center mr-2"></i> Raccourcis Chat
            </button>

            <a href="<?= $baseUrl ?>/profile.php" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all mt-auto">
                <i class="fa-solid fa-user-gear w-6 text-center mr-2"></i> Mon Compte Personnel
            </a>
        </nav>

        <div class="mt-4 pt-4 border-t border-slate-200">
            <a href="<?= $baseUrl ?>/" class="w-full flex items-center justify-center px-4 py-3 border-2 border-slate-900 text-slate-900 rounded-full font-bold hover:bg-slate-900 hover:text-white transition-all mb-3">
                <i class="fa-solid fa-arrow-left mr-2"></i> Retour au site
            </a>
            <a href="<?= $baseUrl ?>/logout.php" class="w-full flex items-center justify-center px-4 py-3 border-2 border-red-100 text-red-500 rounded-full font-bold hover:bg-red-50 transition-all">
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
            <header class="flex flex-col sm:flex-row sm:justify-between sm:items-start sm:items-center mb-8 gap-4">
                <div class="flex items-center gap-3 flex-wrap">
                    <h3 class="font-extrabold text-2xl text-slate-900 m-0">Bonjour, <?= htmlspecialchars($userName) ?> 👋</h3>
                    <span class="bg-slate-900 text-amber-500 px-3 py-1 rounded-full text-xs font-black shadow-sm flex items-center">
                        <i class="fa-solid fa-store mr-1.5"></i> Compte Vendeur
                    </span>
                </div>
                
                <a href="<?= $baseUrl ?>/my_wallet.php" class="flex items-center justify-center px-5 py-2.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white rounded-xl transition-all duration-300 font-black shadow-sm border border-emerald-200 group">
                    <i class="fa-solid fa-wallet mr-2 group-hover:scale-110 transition-transform"></i> Mon Portefeuille
                </a>
            </header>

            <!-- TAB 1: STATISTIQUES -->
            <div id="tab-stats" class="tab-pane">
                
                <?php
                // =====================================================================
                // ⚙️ RÉCUPÉRATION DES VRAIES DONNÉES DEPUIS LA BASE DE DONNÉES
                // =====================================================================
                try {
                    $stmtStand = $db->prepare("SELECT id FROM stands WHERE user_id = ? LIMIT 1");
                    $stmtStand->execute([$userId]);
                    $myStand = $stmtStand->fetch(PDO::FETCH_ASSOC);
                    $standId = $myStand ? $myStand['id'] : 0;

                    $stmtViews = $db->prepare("SELECT COUNT(*) FROM ad_views WHERE stand_id = ?");
                    $stmtViews->execute([$standId]);
                    $stats_views = $stmtViews->fetchColumn() ?: 0;
                    
                    $stats_views_growth = 0;  
                    $stats_clicks = 0;
                    $stats_clicks_growth = 0;  
                    
                    $stats_rate = ($stats_views > 0) ? round(($stats_clicks / $stats_views) * 100, 1) : 0;

                    $stmtFollowers = $db->prepare("SELECT COUNT(*) FROM followers WHERE stand_id = ?");
                    $stmtFollowers->execute([$standId]);
                    $stats_followers = $stmtFollowers->fetchColumn() ?: 0;
                    
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
                        $stats_locations[] = ['name' => 'En attente de visiteurs...', 'percent' => 0, 'color' => 'bg-slate-200'];
                    }

                } catch (Exception $e) {
                    $stats_views = $stats_clicks = $stats_rate = $stats_followers = 0;
                    $stats_views_growth = $stats_clicks_growth = 0;
                    $stats_locations = [['name' => 'Données indisponibles', 'percent' => 0, 'color' => 'bg-slate-200']];
                }
                ?>
                <!-- BANNIÈRE WATCH TO EARN -->
                <div class="mb-8">
                    <a href="<?= $baseUrl ?>/watch_ads.php" class="bg-gradient-to-r from-slate-900 to-slate-800 border border-amber-500/30 hover:border-amber-500 text-white p-6 rounded-3xl shadow-lg flex flex-col sm:flex-row items-center justify-between group transition-all relative">
                        <div class="flex items-center gap-5 text-center sm:text-left mb-4 sm:mb-0">
                            <div class="w-14 h-14 bg-amber-500/20 rounded-full flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform flex-shrink-0">
                                <i class="fa-solid fa-bolt text-2xl"></i>
                            </div>
                            <div>
                                <h4 class="font-black text-xl mb-1">Financer mon compte PRO</h4>
                                <p class="text-sm text-slate-400">Regardez de courtes vidéos sponsorisées pour gagner des crédits et débloquer vos abonnements gratuitement.</p>
                            </div>
                        </div>
                        <div class="bg-amber-500 text-slate-900 font-black px-6 py-3 rounded-xl text-sm group-hover:bg-amber-400 transition-colors flex-shrink-0 whitespace-nowrap shadow-md">
                            Gagner des Crédits <i class="fa-solid fa-arrow-right ml-2"></i>
                        </div>
                    </a>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-8 mt-2 gap-4">
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Performances du Stand</h1>
                        <p class="text-sm text-slate-500 mt-1">Analysez l'impact de vos annonces et vos ventes réelles.</p>
                    </div>
                    
                    <?php if (!$isPremium): ?>
                        <a href="<?= $baseUrl ?>/pricing.php" class="bg-slate-900 hover:bg-slate-800 text-amber-500 text-sm font-bold py-2 px-4 rounded-xl transition flex items-center border border-slate-700">
                            <i class="fa-solid fa-crown mr-2"></i> Voir les Forfaits Pro
                        </a>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-2 <?= $planBadgeColor ?> px-4 py-2.5 rounded-xl font-black text-sm border shadow-sm cursor-default">
                            <i class="fa-solid fa-crown"></i> Abonné : <?= htmlspecialchars($currentPlanName) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- ================================================== -->
                <!-- SECTION 1 : VENTES ET REVENUS RÉELS -->
                <!-- ================================================== -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex items-center gap-4 hover:shadow-md transition relative group">
                        <div class="bg-amber-50 text-amber-500 w-14 h-14 rounded-xl flex items-center justify-center text-2xl"><i class="fa-solid fa-box"></i></div>
                        <div>
                            <p class="text-sm text-slate-500 font-bold">Annonces Actives</p>
                            <h4 class="font-black text-2xl text-slate-900 m-0"><?= $active_products ?></h4>
                        </div>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex items-center gap-4 hover:shadow-md transition relative group">
                        <div class="bg-emerald-50 text-emerald-500 w-14 h-14 rounded-xl flex items-center justify-center text-2xl"><i class="fa-solid fa-cart-check"></i></div>
                        <div>
                            <p class="text-sm text-slate-500 font-bold">Ventes Validées</p>
                            <h4 class="font-black text-2xl text-slate-900 m-0"><?= $total_sales ?></h4>
                        </div>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex items-center gap-4 hover:shadow-md transition col-span-1 md:col-span-2 relative group">
                        <!-- Infobulle Explicative -->
                        <div class="opacity-0 absolute bottom-full left-1/2 -translate-x-1/2 mb-2 bg-slate-800 text-white text-[10px] font-normal py-1.5 px-3 rounded-lg pointer-events-none transition-opacity duration-300 group-hover:opacity-100 whitespace-nowrap shadow-xl">
                            Cumul de toutes les commandes livrées et payées.
                        </div>
                        <div class="bg-blue-50 text-blue-500 w-14 h-14 rounded-xl flex items-center justify-center text-2xl"><i class="fa-solid fa-sack-dollar"></i></div>
                        <div>
                            <p class="text-sm text-slate-500 font-bold">Revenus Totals Générés</p>
                            <h4 class="font-black text-3xl text-blue-600 m-0"><?= number_format($total_revenue, 0, ',', ' ') ?> <span class="text-lg text-slate-400"><?= $currency ?></span></h4>
                        </div>
                    </div>
                </div>

                <!-- ================================================== -->
                <!-- SECTION 2 : STATISTIQUES D'AUDIENCE -->
                <!-- ================================================== -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition relative group">
                        <div class="opacity-0 absolute bottom-full left-1/2 -translate-x-1/2 mb-2 bg-slate-800 text-white text-[10px] py-1.5 px-3 rounded-lg pointer-events-none transition-opacity duration-300 group-hover:opacity-100 whitespace-nowrap">
                            Nombre total de fois où vos annonces ont été vues.
                        </div>
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-semibold text-slate-500 mb-1">Vues Totales</p>
                                <h3 class="text-3xl font-black text-slate-900"><?= number_format($stats_views, 0, ',', ' ') ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xl">
                                <i class="fa-solid fa-eye"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition relative group">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-semibold text-slate-500 mb-1">Clics d'intérêt</p>
                                <h3 class="text-3xl font-black text-slate-900"><?= number_format($stats_clicks, 0, ',', ' ') ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xl">
                                <i class="fa-solid fa-hand-pointer"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition relative group">
                        <div class="opacity-0 absolute bottom-full left-1/2 -translate-x-1/2 mb-2 bg-slate-800 text-white text-[10px] py-1.5 px-3 rounded-lg pointer-events-none transition-opacity duration-300 group-hover:opacity-100 whitespace-nowrap">
                            Calculé en divisant les clics par les vues.
                        </div>
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-semibold text-slate-500 mb-1">Taux de conversion</p>
                                <h3 class="text-3xl font-black text-slate-900"><?= $stats_rate ?>%</h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl">
                                <i class="fa-solid fa-bolt"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================== -->
                <!-- SECTION 3 : STATISTIQUES PREMIUM -->
                <!-- ================================================== -->
                <h2 class="text-lg font-black text-slate-900 mb-4">Analyses Géographiques & Audience</h2>
                
                <?php if ($isPremium): ?>
                    <div class="p-6 sm:p-8 bg-slate-900 rounded-3xl border <?= ($activeSub['plan_id'] == 3) ? 'border-blue-500/30' : 'border-amber-500/30' ?> relative overflow-hidden mb-6 shadow-xl">
                        <div class="absolute -right-6 -top-6 text-[8rem] opacity-5 <?= $planIconColor ?>">
                            <i class="fa-solid fa-crown"></i>
                        </div>
                        
                        <div class="relative z-10">
                            <h3 class="text-xl font-black text-white mb-2 flex items-center">
                                <i class="fa-solid fa-circle-check text-emerald-400 mr-3"></i> Outils Pro Déverrouillés
                            </h3>
                            <p class="text-slate-300 text-sm mb-6">
                                Vous profitez actuellement des avantages du forfait <strong><?= htmlspecialchars($currentPlanName) ?></strong>. Vos annonces bénéficient d'une priorité.
                            </p>
                            
                            <div class="flex items-center space-x-4">
                                <div class="bg-slate-950/60 inline-block px-5 py-3 rounded-xl border border-slate-800">
                                    <span class="text-[10px] text-slate-400 block uppercase tracking-widest mb-1 font-bold">Temps restant</span>
                                    <span class="text-xl font-black <?= $planIconColor ?>"><?= $daysRemaining ?> Jours</span>
                                </div>
                                <a href="<?= $baseUrl ?>/pricing.php" class="text-xs text-slate-400 hover:text-white underline font-bold transition">Upgrader l'abonnement</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 relative">
                    
                    <?php if (!$isPremium): ?>
                        <div class="absolute inset-0 z-10 bg-slate-50/60 backdrop-blur-sm rounded-3xl flex flex-col items-center justify-center border border-white/50">
                            <div class="bg-slate-950 p-8 rounded-3xl border border-slate-800 shadow-2xl max-w-sm w-full transform hover:scale-105 transition-transform duration-300">
                                <div class="w-16 h-16 bg-gradient-to-br from-amber-400 to-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-amber-500/30">
                                    <i class="fa-solid fa-lock text-2xl text-slate-950"></i>
                                </div>
                                <h3 class="text-xl font-black text-white mb-2 text-center">Passez en mode PRO</h3>
                                <p class="text-slate-400 text-sm mb-6 leading-relaxed text-center">Débloquez la géolocalisation, les statistiques avancées et l'IA.</p>
                                <a href="<?= $baseUrl ?>/pricing.php" class="block w-full bg-amber-500 hover:bg-amber-400 text-slate-900 text-center font-black py-3 rounded-xl transition shadow-[0_0_15px_rgba(245,158,11,0.4)]">
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
                        <p class="text-sm text-slate-500 px-4">Ces clients reçoivent une notification à chaque fois que vous publiez.</p>
                    </div>

                </div>
            </div>

            <!-- TAB 2: MES ANNONCES -->
            <div id="tab-listings" class="tab-pane hidden">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="font-bold text-xl m-0 text-slate-900">Gestion de mes annonces</h4>
                    <a href="<?= $baseUrl ?>/publish.php" class="bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold py-2 px-6 rounded-full hover:shadow-lg transition transform hover:-translate-y-0.5 text-sm">
                        <i class="fa-solid fa-plus mr-1"></i> Créer
                    </a>
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
                                            <a href="<?= $baseUrl ?>/publish.php" class="border-2 border-slate-900 text-slate-900 font-bold py-2 px-6 rounded-full hover:bg-slate-900 hover:text-white transition">Publier maintenant</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($myListings as $listing): ?>
                                        <?php 
                                            $stmtAdView = $db->prepare("SELECT COUNT(*) FROM ad_views WHERE listing_id = ?");
                                            $stmtAdView->execute([$listing['id']]);
                                            $adViewsCount = $stmtAdView->fetchColumn() ?: 0;
                                        ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="py-4 px-6 flex items-center">
                                                <img src="<?= htmlspecialchars(!empty($listing['image_path']) ? $baseUrl.'/'.$listing['image_path'] : $baseUrl.'/assets/images/placeholder.jpg') ?>" class="w-12 h-12 rounded-lg object-cover mr-4 shadow-sm border border-slate-200">
                                                <span class="font-bold text-slate-900 truncate max-w-[200px]">
                                                    <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $listing['id'] ?>" target="_blank" class="hover:text-amber-500 transition">
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
                                                <a href="<?= $baseUrl ?>/publish.php?id=<?= $listing['id'] ?>" class="text-slate-400 hover:text-blue-500 p-2 transition" title="Modifier">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
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

            <!-- ================================================== -->
            <!-- TAB 3: LE NOUVEL ONGLET DES VENTES (AJOUTÉ ICI) -->
            <!-- ================================================== -->
            <div id="tab-sales" class="tab-pane hidden">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="font-bold text-xl m-0 text-slate-900">Historique de mes Ventes</h4>
                </div>
                
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                                <tr>
                                    <th class="py-4 px-6 text-[10px] uppercase tracking-widest">N° Commande</th>
                                    <th class="py-4 px-6 text-[10px] uppercase tracking-widest">Produit vendu</th>
                                    <th class="py-4 px-6 text-[10px] uppercase tracking-widest">Client</th>
                                    <th class="py-4 px-6 text-[10px] uppercase tracking-widest text-right">Montant</th>
                                    <th class="py-4 px-6 text-[10px] uppercase tracking-widest text-center">Statut</th>
                                    <th class="py-4 px-6 text-[10px] uppercase tracking-widest text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($mySales)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-16">
                                            <div class="bg-emerald-50 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4 border border-emerald-100">
                                                <i class="fa-solid fa-cart-shopping text-3xl text-emerald-400"></i>
                                            </div>
                                            <h5 class="font-bold text-lg text-slate-900">Aucune vente pour le moment</h5>
                                            <p class="text-slate-500 mt-1">Vos commandes apparaîtront ici dès qu'un client achètera vos produits.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mySales as $order): ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="py-4 px-6 font-mono text-xs font-bold text-slate-500"><?= htmlspecialchars($order['order_number']) ?></td>
                                            <td class="py-4 px-6 font-bold text-slate-900 truncate max-w-[200px]"><?= htmlspecialchars($order['listing_title']) ?></td>
                                            <td class="py-4 px-6 text-slate-600 text-xs"><?= htmlspecialchars($order['buyer_fname'] . ' ' . $order['buyer_lname']) ?></td>
                                            <td class="py-4 px-6 font-black text-emerald-600 text-right"><?= number_format($order['amount'], 0, ',', ' ') ?> <?= $currency ?></td>
                                            <td class="py-4 px-6 text-center">
                                                <?php if($order['status'] === 'paid'): ?>
                                                    <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wide shadow-sm"><i class="fa-solid fa-check mr-1"></i> Payée</span>
                                                <?php elseif($order['status'] === 'pending'): ?>
                                                    <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wide"><i class="fa-solid fa-hourglass-half mr-1"></i> En attente</span>
                                                <?php else: ?>
                                                    <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wide"><?= htmlspecialchars($order['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4 px-6 text-right">
                                                <?php if($order['status'] === 'paid' || $order['status'] === 'shipped' || $order['status'] === 'delivered'): ?>
                                                    <!-- BOUTON REÇU OFFICIEL AVEC INFOBULLE (Seulement si payé) -->
                                                    <div class="relative group inline-block text-left">
                                                        <a href="<?= $baseUrl ?>/receipt.php?id=<?= $order['id'] ?>" target="_blank" class="bg-white hover:bg-slate-900 text-slate-700 hover:text-white border border-slate-200 font-bold py-2 px-4 rounded-xl text-xs transition-all flex items-center gap-2 shadow-sm">
                                                            <i class="fa-solid fa-file-invoice text-amber-500"></i> Le Reçu
                                                        </a>
                                                        <!-- Infobulle -->
                                                        <div class="opacity-0 absolute bottom-full right-0 mb-2 w-56 bg-slate-800 text-white text-[10px] py-2.5 px-3 rounded-xl pointer-events-none transition-opacity duration-300 group-hover:opacity-100 shadow-2xl text-center z-20 font-medium">
                                                            <strong class="text-amber-500 block mb-1 uppercase tracking-widest text-[9px]">Reçu Automatique</strong>
                                                            Généré de manière sécurisée par le système. Vous pouvez l'imprimer ou l'envoyer au client.
                                                            <div class="absolute top-full right-6 border-4 border-transparent border-t-slate-800"></div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-slate-400 italic font-bold">Paiement en attente</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: PARAMÈTRES BUSINESS (RÉPONSE AUTO) -->
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
                            <p class="text-xs sm:text-sm text-slate-500 ml-14 mb-4">Répond automatiquement à tous les messages reçus avec le texte ci-dessous.</p>
                            <div class="ml-0 sm:ml-14 mt-4 sm:mt-0">
                                <label class="block text-sm font-bold text-slate-700 mb-2">Message d'absence</label>
                                <textarea id="autoReplyMessage" class="w-full border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none text-sm" rows="3" placeholder="Bonjour, je suis actuellement indisponible..."></textarea>
                            </div>
                        </div>
                        <div class="text-right mt-8">
                            <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold py-3 px-8 rounded-full shadow hover:shadow-lg transition">
                                <i class="fa-solid fa-save mr-2"></i> Enregistrer les paramètres
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
                                    <th class="py-4 px-6">Raccourci <span class="text-xs font-normal text-slate-400">(Taper /mot)</span></th>
                                    <th class="py-4 px-6">Message automatique généré</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="quickRepliesTable" class="divide-y divide-slate-100">
                                <tr><td colspan="3" class="text-center py-8 text-slate-500">Chargement de vos raccourcis...</td></tr>
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
                        <label class="block text-sm font-bold text-slate-700 mb-2">Mot raccourci (ex: 'bonjour')</label>
                        <input type="text" id="quickShortcut" class="w-full border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Message complet à envoyer</label>
                        <textarea id="quickMessage" class="w-full border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold py-3 rounded-xl shadow hover:shadow-lg transition">Enregistrer le raccourci</button>
                </form>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT COMPLET -->
    <script>
        const CURRENCY = <?=json_encode($currency)?>;
        const INITIAL_TAB = <?= json_encode($activeTab) ?>;

        document.addEventListener('DOMContentLoaded', () => {
            loadBusinessSettings();
            loadQuickReplies();
            setupToggleSwitches();
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

        // Navigation par onglets (AJOUT DE LA COULEUR VERTE POUR LES VENTES)
        function switchTab(tabId) {
            document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.nav-link').forEach(el => {
                if(!el.getAttribute('href') || el.getAttribute('href') === '#') {
                    el.className = 'nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all';
                }
            });
            
            const targetPane = document.getElementById(tabId);
            if(targetPane) targetPane.classList.remove('hidden');
            
            const activeBtn = document.getElementById(tabId + '-btn');
            if(activeBtn) {
                if(tabId === 'tab-sales') {
                    activeBtn.className = 'nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold transition-all bg-emerald-600 text-white shadow-md';
                } else {
                    activeBtn.className = 'nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold transition-all bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-md';
                }
            }
            
            if(window.innerWidth < 1024) toggleSidebar(); 
        }

        // Animation des boutons switch
        function setupToggleSwitches() {
            ['isAway'].forEach(id => {
                const checkbox = document.getElementById(id);
                if(checkbox) {
                    const bg = document.getElementById('bg-' + id);
                    const dot = document.getElementById('dot-' + id);
                    
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

        async function loadBusinessSettings() {
            try {
                const res = await fetch('../api/chat.php?action=getBusinessSettings');
                const data = await res.json();

                const isAwayCb = document.getElementById('isAway');
                if(isAwayCb) {
                    isAwayCb.checked = data.is_away == 1;
                    isAwayCb.dispatchEvent(new Event('change'));
                }

                if(document.getElementById('autoReplyMessage')) document.getElementById('autoReplyMessage').value = data.auto_reply_message || '';
            } catch (e) {}
        }

        async function saveBusinessSettings(e) {
            e.preventDefault();
            const formData = new URLSearchParams({
                action: 'saveBusinessSettings',
                is_away: document.getElementById('isAway').checked ? 1 : 0,
                auto_reply_message: document.getElementById('autoReplyMessage').value
            });

            try {
                const res = await fetch('../api/chat.php', { method: 'POST', body: formData });
                const result = await res.json();
                if (result.status === 'success') alert('Paramètres d\'absence sauvegardés avec succès !');
            } catch (e) { console.error(e); }
        }

        async function loadQuickReplies() {
            const tbody = document.getElementById('quickRepliesTable');
            if(!tbody) return;
            
            try {
                const res = await fetch('../api/chat.php?action=getQuickReplies');
                if (!res.ok) throw new Error('API non prête'); 
                
                const replies = await res.json();
                
                if (!replies || replies.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-8 text-slate-500">Aucun raccourci enregistré. Cliquez sur "Ajouter".</td></tr>';
                    return;
                }

                let html = '';
                replies.forEach(r => {
                    html += `
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-4 px-6"><span class="bg-slate-900 text-white rounded-md px-2 py-1 text-xs font-mono">/${escapeHtml(r.shortcut)}</span></td>
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
            } catch (e) { 
                console.error("Module Raccourcis en construction:", e);
                tbody.innerHTML = '<tr><td colspan="3" class="text-center py-8 text-slate-500">Le module de raccourcis est en cours de configuration.</td></tr>';
            }
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