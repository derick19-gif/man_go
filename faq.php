<?php
// =========================================================================
// Page FAQ - MAN GO Marketplace
// =========================================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foire aux Questions (FAQ) - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

<!-- En-tête / Header -->
<header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        <a href="http://localhost/man_go/" class="flex items-center space-x-2 text-white">
            <span class="bg-amber-500 text-slate-950 font-black text-xl w-10 h-10 rounded-full flex items-center justify-center">M</span>
            <span class="font-extrabold text-2xl tracking-tight">MAN <span class="text-amber-500">GO</span></span>
        </a>
        <nav class="hidden md:flex items-center space-x-8 text-sm font-semibold text-gray-300">
            <a href="http://localhost/man_go/" class="hover:text-amber-500 transition">Accueil</a>
            <a href="http://localhost/man_go/listings" class="hover:text-amber-500 transition">Annonces</a>
            <a href="http://localhost/man_go/stands" class="hover:text-amber-500 transition">Boutiques & Stands</a>
            <a href="http://localhost/man_go/services" class="hover:text-amber-500 transition">Services</a>
        </nav>
        <div>
            <a href="http://localhost/man_go/login.php" class="text-xs font-bold text-white hover:text-amber-500 transition">Connexion</a>
        </div>
    </div>
</header>

<!-- Contenu Principal -->
<main class="flex-1 max-w-4xl mx-auto px-4 py-12 w-full">
    <div class="text-center mb-12">
        <span class="bg-amber-100 text-amber-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Centre d'aide</span>
        <h1 class="text-3xl sm:text-4xl font-black text-slate-900 mt-3">Foire Aux Questions</h1>
        <p class="text-gray-600 text-sm sm:text-base mt-2">Retrouvez les réponses aux questions les plus fréquentes concernant l'utilisation de la marketplace MAN GO.</p>
    </div>

    <div class="space-y-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <h3 class="text-base sm:text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-circle-question text-amber-500 mr-3"></i> Comment publier une annonce sur MAN GO ?
            </h3>
            <p class="text-gray-600 text-sm mt-3 pl-7 leading-relaxed">
                Cliquez simplement sur le bouton orange "Publier une annonce", connectez-vous à votre compte, puis remplissez le formulaire avec les détails de votre article ou service.
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <h3 class="text-base sm:text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-circle-question text-amber-500 mr-3"></i> Qu'est-ce qu'un Stand ou une Boutique certifiée ?
            </h3>
            <p class="text-gray-600 text-sm mt-3 pl-7 leading-relaxed">
                Un stand certifié est un espace vendeur vérifié qui permet aux professionnels de regrouper l'ensemble de leurs produits et services sous une même vitrine de confiance.
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <h3 class="text-base sm:text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-circle-question text-amber-500 mr-3"></i> La publication d'annonces est-elle gratuite ?
            </h3>
            <p class="text-gray-600 text-sm mt-3 pl-7 leading-relaxed">
                Oui, la publication d'annonces standard est totalement gratuite pour tous les utilisateurs de la plateforme.
            </p>
        </div>
    </div>

    <div class="mt-12 bg-slate-900 rounded-2xl p-8 text-center text-white">
        <h3 class="text-lg font-bold">Vous avez d'autres questions ?</h3>
        <p class="text-gray-400 text-sm mt-2">Notre équipe est à votre écoute pour vous accompagner.</p>
        <a href="http://localhost/man_go/" class="inline-block mt-5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-6 py-2.5 rounded-full text-xs transition shadow-md">
            Retour à l'accueil
        </a>
    </div>
</main>

<!-- Pied de page -->
<footer class="bg-slate-950 text-gray-400 text-sm border-t border-slate-800 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-xs text-gray-500">
        &copy; 2026 MAN GO Marketplace. Tous droits réservés.
    </div>
</footer>
</body>
</html>