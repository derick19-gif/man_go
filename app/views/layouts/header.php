<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['user']);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'fr', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'MAN GO - Marketplace Universelle'; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js CDN (Indispensable pour le moteur de recherche dynamique) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

<!-- En-tête / Header Complet et Robuste -->
<header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        
        <!-- Logo -->
        <a href="/man_go/" class="flex items-center space-x-2 text-white shrink-0">
            <span class="bg-amber-500 text-slate-950 font-black text-xl w-10 h-10 rounded-full flex items-center justify-center">M</span>
            <span class="font-extrabold text-xl sm:text-2xl tracking-tight">MAN <span class="text-amber-500">GO</span></span>
        </a>

        <!-- Navigation principale (Responsive avec défilement fluide sur mobile) -->
        <nav class="flex items-center space-x-3 sm:space-x-6 text-xs sm:text-sm font-semibold text-gray-300 overflow-x-auto py-2 mx-2">
            <a href="/man_go/" class="hover:text-amber-500 transition whitespace-nowrap">Accueil</a>
            <a href="/man_go/listings.php" class="hover:text-amber-500 transition whitespace-nowrap">Annonces</a>
            <a href="/man_go/stands" class="hover:text-amber-500 transition whitespace-nowrap">Boutiques</a>
            <a href="/man_go/services" class="hover:text-amber-500 transition whitespace-nowrap">Services</a>
        </nav>

        <!-- Actions Utilisateur & Bouton Publier -->
        <div class="flex items-center space-x-2 sm:space-x-4 shrink-0">
            <?php if ($isLoggedIn): ?>
                <a href="/man_go/dashboard.php" class="hidden md:inline-block text-xs font-bold text-white bg-slate-800 border border-slate-700 px-3.5 py-2 rounded-full hover:bg-slate-700 transition whitespace-nowrap">Tableau de bord</a>
                <a href="/man_go/logout.php" class="text-xs font-bold text-red-400 hover:text-red-300 transition whitespace-nowrap" title="Se déconnecter"><i class="fa-solid fa-power-off"></i></a>
            <?php else: ?>
                <a href="/man_go/login.php" class="hidden sm:inline-block text-xs font-bold text-white hover:text-amber-500 transition whitespace-nowrap">Connexion</a>
                <a href="/man_go/register.php" class="hidden md:inline-block text-xs font-bold text-amber-500 hover:text-amber-400 transition whitespace-nowrap">Inscription</a>
            <?php endif; ?>

            <a href="/man_go/publish.php" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-extrabold px-3 py-2 sm:px-4 sm:py-2.5 rounded-full text-xs transition shadow-md flex items-center space-x-2 whitespace-nowrap">
                <i class="fa-solid fa-plus"></i>
                <span class="hidden sm:inline">Publier une annonce</span>
                <span class="sm:hidden">Publier</span>
            </a>
        </div>
    </div>
</header>