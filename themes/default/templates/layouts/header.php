<?php
// =========================================================================
// HEADER.PHP (Thème par défaut) - Version Startup & Robuste
// =========================================================================

// 1. Sécurisation et initialisation de la session native
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Détermination propre de l'URL de base
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// 3. INITIALISATION FORCÉE DE LA CLASSE SESSION (Le secret pour que ça marche)
if (class_exists('Session') && method_exists('Session', 'init')) {
    Session::init();
}

// 4. Vérification ultra-robuste de l'état de connexion
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['user']) || (class_exists('Session') && Session::isAuthenticated());

// 5. Récupération sécurisée des informations de l'utilisateur
$userName = $_SESSION['user_name'] ?? $_SESSION['user']['name'] ?? (class_exists('Session') ? Session::get('user_name') : 'Mon Compte');
$userRole = $_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? (class_exists('Session') ? Session::get('user_role') : '');
$userAvatar = $_SESSION['user_avatar'] ?? $_SESSION['user']['avatar'] ?? (class_exists('Session') ? Session::get('user_avatar') : null);

// 6. Routage intelligent : On envoie le vendeur vers son espace Pro, et le client vers son espace client
$dashboardLink = ($userRole === 'vendor') ? $baseUrl . '/vendor_dir/dashboard.php' : $baseUrl . '/client/views/dashboard.php';
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : 'MAN GO - One Market, One Movement.'; ?></title>
    
    <!-- Tailwind CSS v3 CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#f59e0b', // Amber 500
                            600: '#d97706',
                            950: '#090d16', // Dark
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
                    },
                    boxShadow: {
                        'futuristic': '0 10px 30px -10px rgba(245, 158, 11, 0.3)',
                        'glow': '0 0 20px rgba(245, 158, 11, 0.5)',
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .glass-header {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .nav-link-futuristic {
            position: relative;
        }
        .nav-link-futuristic::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background-color: #f59e0b;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .nav-link-futuristic:hover::after {
            width: 100%;
        }
    </style>
</head>
<body class="bg-slate-950 text-gray-100 flex flex-col min-h-screen font-sans antialiased selection:bg-amber-500 selection:text-slate-950">

<header class="glass-header border-b border-slate-800/80 sticky top-0 z-50 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        
        <!-- Logo Brand & SLOGAN -->
        <a href="<?= $baseUrl ?>/" class="flex items-center space-x-3 text-white group focus:outline-none">
            <div class="bg-gradient-to-tr from-amber-600 to-amber-400 text-slate-950 font-black text-xl w-11 h-11 rounded-2xl flex items-center justify-center shadow-futuristic group-hover:scale-105 transition transform duration-300">
                M
            </div>
            <div class="flex flex-col">
                <span class="font-extrabold text-xl tracking-tight leading-none">MAN <span class="text-amber-500">GO</span></span>
                <!-- Le fameux Slogan intégré subtilement -->
                <span class="text-[0.65rem] tracking-wider text-amber-500/90 font-bold uppercase mt-0.5">One Market, One Movement.</span>
            </div>
        </a>
        
        <!-- Navigation Desktop -->
        <nav class="hidden md:flex items-center space-x-8 text-sm font-semibold text-slate-300">
            <a href="<?= $baseUrl ?>/" class="nav-link-futuristic hover:text-amber-400 transition py-1">Accueil</a>
            <a href="<?= $baseUrl ?>/listings" class="nav-link-futuristic hover:text-amber-400 transition py-1">Annonces</a>
            <a href="<?= $baseUrl ?>/stands" class="nav-link-futuristic hover:text-amber-400 transition py-1">Boutiques & Stands</a>
            <a href="<?= $baseUrl ?>/services" class="nav-link-futuristic hover:text-amber-400 transition py-1">Services</a>
        </nav>

        <!-- Actions / Espace Utilisateur Desktop -->
        <div class="hidden md:flex items-center space-x-4">
            
            <?php if ($isLoggedIn): ?>
                <!-- Interface pour utilisateur CONNECTÉ -->
                <a href="<?= $dashboardLink ?>" class="text-sm font-bold text-slate-200 hover:text-amber-400 transition flex items-center space-x-2 bg-slate-800/50 px-4 py-2 rounded-full border border-slate-700/50">
                    <i class="fa-solid <?= ($userRole === 'vendor') ? 'fa-store' : 'fa-user' ?> text-amber-500"></i>
                    <span><?= ($userRole === 'vendor') ? 'Espace Pro' : 'Mon Compte' ?></span>
                </a>
                <a href="<?= $baseUrl ?>/logout.php" class="text-xs font-bold text-red-400 hover:text-red-300 p-2.5 rounded-full hover:bg-red-500/10 transition" title="Déconnexion">
                    <i class="fa-solid fa-power-off text-sm"></i>
                </a>
            <?php else: ?>
                <!-- Interface pour utilisateur DÉCONNECTÉ -->
                <a href="<?= $baseUrl ?>/login.php" class="text-sm font-bold text-slate-300 hover:text-amber-400 px-3 py-2 transition">Connexion</a>
                <a href="<?= $baseUrl ?>/register.php" class="text-sm font-bold text-amber-400 border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/20 px-5 py-2.5 rounded-full transition shadow-sm">Inscription</a>
            <?php endif; ?>

            <!-- L'UNIQUE BOUTON PUBLIER -->
            <a href="<?= $baseUrl ?>/publish.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-extrabold px-5 py-2.5 rounded-full text-sm transition-all duration-300 shadow-futuristic hover:shadow-glow flex items-center space-x-2 transform hover:-translate-y-0.5">
                <i class="fa-solid fa-plus-circle"></i><span>Publier</span>
            </a>
        </div>

        <!-- Bouton Menu Mobile Toggle -->
        <div class="flex md:hidden items-center space-x-3">
            <a href="<?= $isLoggedIn ? $dashboardLink . '#tab-publish' : $baseUrl . '/login.php' ?>" class="bg-amber-500 text-slate-950 font-bold p-2.5 rounded-full text-xs shadow-md">
                <i class="fa-solid fa-plus"></i>
            </a>
            <button id="mobile-menu-button" type="button" class="text-slate-300 hover:text-white focus:outline-none p-2 rounded-lg bg-slate-900 border border-slate-800">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
        </div>
    </div>

    <!-- Menu Mobile Dropdown -->
    <div id="mobile-menu" class="hidden md:hidden bg-slate-900 border-b border-slate-800 px-4 pt-3 pb-5 space-y-3 shadow-2xl">
        <nav class="flex flex-col space-y-2 text-sm font-semibold text-slate-300">
            <a href="<?= $baseUrl ?>/" class="px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-amber-400 transition">Accueil</a>
            <a href="<?= $baseUrl ?>/listings" class="px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-amber-400 transition">Annonces</a>
            <a href="<?= $baseUrl ?>/stands" class="px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-amber-400 transition">Boutiques & Stands</a>
            <a href="<?= $baseUrl ?>/services" class="px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-amber-400 transition">Services</a>
        </nav>
        <hr class="border-slate-800 my-2">
        <div class="flex flex-col space-y-2 pt-1">
            <?php if ($isLoggedIn): ?>
                <a href="<?= $dashboardLink ?>" class="flex items-center justify-center space-x-3 px-3 py-3 rounded-xl bg-slate-800/80 text-white font-bold text-sm">
                    <i class="fa-solid <?= ($userRole === 'vendor') ? 'fa-store' : 'fa-user' ?> text-amber-500"></i>
                    <span><?= ($userRole === 'vendor') ? 'Mon Espace Pro' : 'Mon Compte' ?></span>
                </a>
                <a href="<?= $baseUrl ?>/logout.php" class="text-center px-3 py-3 rounded-xl text-red-400 font-bold text-sm hover:bg-red-500/10 transition">Déconnexion</a>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-2">
                    <a href="<?= $baseUrl ?>/login.php" class="text-center font-bold text-sm text-slate-200 bg-slate-800 hover:bg-slate-700 py-3 rounded-xl transition">Connexion</a>
                    <a href="<?= $baseUrl ?>/register.php" class="text-center font-bold text-sm text-slate-950 bg-amber-500 hover:bg-amber-400 py-3 rounded-xl transition">Inscription</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Script pour le menu mobile -->
<script>
    document.getElementById('mobile-menu-button').addEventListener('click', function () {
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenu.classList.toggle('hidden');
    });
</script>