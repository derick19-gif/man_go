<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'fr', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'MAN GO - Marketplace Universelle'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

<header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/" class="flex items-center space-x-2 text-white">
            <span class="bg-amber-500 text-slate-950 font-black text-xl w-10 h-10 rounded-full flex items-center justify-center">M</span>
            <span class="font-extrabold text-2xl tracking-tight">MAN <span class="text-amber-500">GO</span></span>
        </a>
        
        <nav class="hidden md:flex items-center space-x-8 text-sm font-semibold text-gray-300">
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/" class="hover:text-amber-500 transition">Accueil</a>
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/listings" class="hover:text-amber-500 transition">Annonces</a>
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/stands" class="hover:text-amber-500 transition">Boutiques & Stands</a>
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/services" class="hover:text-amber-500 transition">Services</a>
        </nav>

        <div class="flex items-center space-x-4">
            <?php if (isset($_SESSION['user'])): ?>
                <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/dashboard" class="hidden sm:inline-block text-xs font-bold text-white bg-slate-800 border border-slate-700 px-4 py-2.5 rounded-full hover:bg-slate-700 transition">Tableau de bord</a>
                <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/logout" class="text-xs font-bold text-red-400 hover:text-red-300 transition">Déconnexion</a>
            <?php else: ?>
                <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/login.php" class="hidden sm:inline-block text-xs font-bold text-white hover:text-amber-500 transition">Connexion</a>
                <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/register" class="hidden sm:inline-block text-xs font-bold text-amber-500 hover:text-amber-400 transition">Inscription</a>
            <?php endif; ?>

            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/publish" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-extrabold px-5 py-2.5 rounded-full text-xs transition shadow-md flex items-center space-x-2">
                <i class="fa-solid fa-plus"></i>
                <span>Publier une annonce</span>
            </a>
        </div>
    </div>
</header>