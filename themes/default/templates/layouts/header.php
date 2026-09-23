<?php
// themes/default/templates/layouts/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// 1. GESTION ULTRA-ROBUSTE DE LA SESSION
$isLoggedIn = false;
$userName = 'Utilisateur';
$userRole = '';

// On vérifie d'abord si la classe Session de l'App existe
if (class_exists('App\Core\Session') && \App\Core\Session::isAuthenticated()) {
    $isLoggedIn = true;
    $userName = \App\Core\Session::get('user_name') ?: 'Utilisateur';
    $userRole = \App\Core\Session::get('user_role') ?: '';
} 
// Fallback 1: Si une classe Session simple existe
elseif (class_exists('Session') && Session::get('user_id')) {
    $isLoggedIn = true;
    $userName = Session::get('user_name') ?: 'Utilisateur';
    $userRole = Session::get('user_role') ?: '';
}
// Fallback 2: Lecture directe du tableau $_SESSION natif
elseif (isset($_SESSION['user_id']) || isset($_SESSION['user'])) {
    $isLoggedIn = true;
    $userName = $_SESSION['user_name'] ?? $_SESSION['user']['name'] ?? 'Utilisateur';
    $userRole = $_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? '';
}

// 2. Variables de contrôle global pour l'affichage
// Ces variables seront lues par le Header ET par le Footer !
$isVendor = ($userRole === 'vendor' || $userRole === 'vendeur' || $userRole === '4');
$isAdmin = ($userRole === 'admin' || $userRole === 'super_admin' || $userRole === '1' || $userRole === '2');
$isBuyer = ($userRole === 'buyer' || $userRole === 'client' || $userRole === '5');

// Routage dynamique du bouton d'espace perso
if ($isVendor) {
    $dashboardLink = $baseUrl . '/vendor_dir/dashboard.php';
    $btnLabel = 'Espace Pro';
    $btnIcon = 'fa-store';
} elseif ($isAdmin) {
    $dashboardLink = $baseUrl . '/admin/dashboard.php';
    $btnLabel = 'Administration';
    $btnIcon = 'fa-hammer';
} else {
    $dashboardLink = $baseUrl . '/client/views/dashboard.php';
    $btnLabel = 'Mon Compte';
    $btnIcon = 'fa-user';
}

// On cache le bouton "Publier" pour les acheteurs et les admins
$showPublishButton = (!$isLoggedIn || $isVendor);

// On en profite pour envoyer une info au FOOTER : Faut-il cacher "Devenir Vendeur" ?
define('HIDE_BECOME_VENDOR', ($isVendor || $isAdmin));
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
                <a href="<?= $dashboardLink ?>" class="text-sm font-bold text-slate-200 hover:text-amber-400 transition flex items-center space-x-2 bg-slate-800/50 px-4 py-2 rounded-full border border-slate-700/50">
                    <i class="fa-solid <?= $btnIcon ?> text-amber-500"></i>
                    <span><?= $btnLabel ?></span>
                </a>
                <a href="<?= $baseUrl ?>/logout.php" class="text-xs font-bold text-red-400 hover:text-red-300 p-2.5 rounded-full hover:bg-red-500/10 transition" title="Déconnexion">
                    <i class="fa-solid fa-power-off text-sm"></i>
                </a>
            <?php else: ?>
                <a href="<?= $baseUrl ?>/login.php" class="text-sm font-bold text-slate-300 hover:text-amber-400 px-3 py-2 transition">Connexion</a>
                <a href="<?= $baseUrl ?>/register.php" class="text-sm font-bold text-amber-400 border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/20 px-5 py-2.5 rounded-full transition shadow-sm">Inscription</a>
            <?php endif; ?>

            <?php if($showPublishButton): ?>
                <a href="<?= $baseUrl ?>/publish.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-extrabold px-5 py-2.5 rounded-full text-sm transition-all duration-300 shadow-futuristic flex items-center space-x-2 transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-plus-circle"></i><span>Publier</span>
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Menu Mobile Toggle (à garder si vous en avez besoin) -->
        <div class="flex md:hidden items-center space-x-3">
            <a href="<?= $isLoggedIn ? $dashboardLink . '#tab-publish' : $baseUrl . '/login.php' ?>" class="bg-amber-500 text-slate-950 font-bold p-2.5 rounded-full text-xs shadow-md">
                <i class="fa-solid fa-plus"></i>
            </a>
            <button id="mobile-menu-button" type="button" class="text-slate-300 hover:text-white focus:outline-none p-2 rounded-lg bg-slate-900 border border-slate-800">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
        </div>
    </div>
</header>