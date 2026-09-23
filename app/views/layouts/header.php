<?php
// app/views/layouts/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// 1. GESTION INTELLIGENTE DU NAMESPACE (C'est ÇA la clé !)
$sessionClass = class_exists('App\Core\Session') ? 'App\Core\Session' : (class_exists('Session') ? 'Session' : null);

if ($sessionClass) {
    if (method_exists($sessionClass, 'init')) {
        $sessionClass::init();
    }
    $isLoggedIn = $sessionClass::isAuthenticated();
    $userName = $sessionClass::get('user_name') ?: 'Utilisateur';
    $userRole = $sessionClass::get('user_role') ?: '';
} else {
    // 2. Fallback direct sur la mémoire native si aucune classe n'est trouvée
    $isLoggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['user']);
    $userName = $_SESSION['user_name'] ?? $_SESSION['user']['name'] ?? 'Utilisateur';
    $userRole = $_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? '';
}

// 3. Routage dynamique
$dashboardLink = ($userRole === 'vendor') ? $baseUrl . '/vendor_dir/dashboard.php' : $baseUrl . '/client/views/dashboard.php';

// 4. Condition pour afficher le bouton Publier
// On l'affiche SI l'utilisateur n'est PAS connecté, OU SI c'est un vendeur. 
// On le CACHE pour les acheteurs purs et les admins.
$showPublishButton = (!$isLoggedIn || $userRole === 'vendor' || $userRole === 'vendeur');
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : 'MAN GO - One Market, One Movement.'; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 500: '#f59e0b', 600: '#d97706', 950: '#090d16' } },
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'] },
                    boxShadow: { 'futuristic': '0 10px 30px -10px rgba(245, 158, 11, 0.3)', 'glow': '0 0 20px rgba(245, 158, 11, 0.5)' }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-header { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        .nav-link-futuristic { position: relative; }
        .nav-link-futuristic::after { content: ''; position: absolute; width: 0; height: 2px; bottom: -4px; left: 0; background-color: #f59e0b; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-link-futuristic:hover::after { width: 100%; }
        
        /* Cacher la barre de défilement mais garder le fonctionnement du swipe */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-950 text-gray-100 flex flex-col min-h-screen font-sans antialiased selection:bg-amber-500 selection:text-slate-950">

<header class="glass-header border-b border-slate-800/80 sticky top-0 z-50 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        
        <a href="<?= $baseUrl ?>/" class="flex items-center space-x-3 text-white group focus:outline-none">
            <div class="bg-gradient-to-tr from-amber-600 to-amber-400 text-slate-950 font-black text-xl w-11 h-11 rounded-2xl flex items-center justify-center shadow-futuristic group-hover:scale-105 transition transform duration-300">M</div>
            <div class="flex flex-col">
                <span class="font-extrabold text-xl tracking-tight leading-none">MAN <span class="text-amber-500">GO</span></span>
                <span class="text-[0.65rem] tracking-wider text-amber-500/90 font-bold uppercase mt-0.5">One Market, One Movement.</span>
            </div>
        </a>
        
        <nav class="hidden md:flex items-center space-x-8 text-sm font-semibold text-slate-300">
            <a href="<?= $baseUrl ?>/" class="nav-link-futuristic hover:text-amber-400 transition py-1">Accueil</a>
            <a href="<?= $baseUrl ?>/listings.php" class="nav-link-futuristic hover:text-amber-400 transition py-1">Annonces</a>
            <a href="<?= $baseUrl ?>/stands" class="nav-link-futuristic hover:text-amber-400 transition py-1">Boutiques & Stands</a>
            <a href="<?= $baseUrl ?>/services" class="nav-link-futuristic hover:text-amber-400 transition py-1">Services</a>
        </nav>

        <div class="hidden md:flex items-center space-x-4">
            <?php if ($isLoggedIn): ?>
                <!-- Bouton Mon Compte / Espace Pro -->
                <a href="<?= $dashboardLink ?>" class="text-sm font-bold text-slate-200 hover:text-amber-400 transition flex items-center space-x-2 bg-slate-800/50 px-4 py-2 rounded-full border border-slate-700/50">
                    <i class="fa-solid <?= ($userRole === 'vendor' || $userRole === 'vendeur') ? 'fa-store' : 'fa-user' ?> text-amber-500"></i>
                    <span><?= ($userRole === 'vendor' || $userRole === 'vendeur') ? 'Espace Pro' : 'Mon Compte' ?></span>
                </a>
                <!-- Bouton Déconnexion -->
                <a href="<?= $baseUrl ?>/logout.php" class="text-xs font-bold text-red-400 hover:text-red-300 p-2.5 rounded-full hover:bg-red-500/10 transition" title="Déconnexion">
                    <i class="fa-solid fa-power-off text-sm"></i>
                </a>
            <?php else: ?>
                <!-- Liens Connexion / Inscription -->
                <a href="<?= $baseUrl ?>/login.php" class="text-sm font-bold text-slate-300 hover:text-amber-400 px-3 py-2 transition">Connexion</a>
                <a href="<?= $baseUrl ?>/register.php" class="text-sm font-bold text-amber-400 border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/20 px-5 py-2.5 rounded-full transition shadow-sm">Inscription</a>
            <?php endif; ?>

            <!-- LE BOUTON PUBLIER (Affiché conditionnellement) -->
            <?php if($showPublishButton): ?>
                <a href="<?= $baseUrl ?>/publish.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-extrabold px-5 py-2.5 rounded-full text-sm transition-all duration-300 shadow-futuristic flex items-center space-x-2 transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-plus-circle"></i><span>Publier</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>