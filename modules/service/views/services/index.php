<?php
$title = $title ?? 'Services Professionnels - MAN GO';
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">

    <!-- En-tête / Navigation -->
    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="<?= $baseUrl ?>/" class="text-2xl font-black tracking-wider flex items-center space-x-2">
                    <span class="text-amber-500">MAN</span><span>GO</span>
                </a>
            </div>
            <nav class="hidden md:flex items-center space-x-6 text-sm font-medium">
                <a href="<?= $baseUrl ?>/" class="hover:text-amber-400 transition">Accueil</a>
                <a href="<?= $baseUrl ?>/listings.php" class="hover:text-amber-400 transition">Annonces</a>
                <a href="<?= $baseUrl ?>/stands" class="hover:text-amber-400 transition">Boutiques & Stands</a>
                <a href="<?= $baseUrl ?>/services" class="text-amber-400 font-bold border-b-2 border-amber-400 pb-1">Services</a>
            </nav>
            <div>
                <a href="<?= $baseUrl ?>/login.php" class="bg-gradient-to-r from-amber-500 to-amber-600 text-slate-900 px-5 py-2.5 rounded-full font-bold shadow-lg hover:shadow-xl transition">
                    Espace Membre
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section & Moteur de Recherche -->
    <section class="bg-[#0B132B] text-white pt-16 pb-12 px-4 text-center border-b border-slate-800 relative overflow-hidden">
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-amber-500 rounded-full blur-3xl opacity-10"></div>
        <div class="max-w-5xl mx-auto relative z-10">
            <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-4">
                Services & Prestations <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-amber-600">Professionnelles</span>
            </h1>
            <p class="text-slate-400 text-sm md:text-base max-w-2xl mx-auto mb-10">
                Découvrez des experts qualifiés, des prestataires de confiance et des services sur mesure adaptés à tous vos besoins.
            </p>

            <!-- FORMULAIRE DE RECHERCHE -->
            <form action="<?= $baseUrl ?>/services" method="GET" class="w-full bg-white rounded-2xl md:rounded-full p-2 shadow-2xl flex flex-col md:flex-row items-center border border-slate-200 text-slate-800">
                
                <div class="flex-1 flex items-center px-4 py-3 md:py-0 w-full border-b md:border-b-0 md:border-r border-slate-200">
                    <i class="fa-solid fa-magnifying-glass text-amber-500 mr-3"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery ?? '') ?>" placeholder="Ex: Plombier, Avocat, Création site web..." class="w-full bg-transparent border-none focus:outline-none text-slate-900 font-bold placeholder-slate-400 text-sm">
                </div>
                
                <!-- SÉLECTEUR AVEC PARENTS ET ENFANTS -->
                <div class="flex-1 flex items-center px-4 py-3 md:py-0 w-full border-b md:border-b-0 md:border-r border-slate-200 relative hidden md:flex">
                    <i class="fa-solid fa-layer-group text-amber-500 mr-3"></i>
                    <select name="category" class="w-full bg-transparent border-none focus:outline-none text-slate-900 font-bold text-sm cursor-pointer appearance-none">
                        <option value="0" class="font-black text-slate-900">Toutes les catégories</option>
                        <?php if(!empty($categoriesTree)): ?>
                            <?php foreach ($categoriesTree as $parent): ?>
                                <optgroup label="■ <?= htmlspecialchars($parent['name']) ?>">
                                    <option value="<?= $parent['id'] ?>" <?= (($searchCategory ?? 0) == $parent['id']) ? 'selected' : '' ?> class="font-bold text-slate-800">
                                        ▶ TOUT : <?= htmlspecialchars($parent['name']) ?>
                                    </option>
                                    <?php foreach ($parent['subcategories'] as $sub): ?>
                                        <option value="<?= $sub['id'] ?>" <?= (($searchCategory ?? 0) == $sub['id']) ? 'selected' : '' ?> class="text-slate-600">
                                            &nbsp;&nbsp;&nbsp;↳ <?= htmlspecialchars($sub['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <i class="fa-solid fa-chevron-down text-xs text-slate-400 absolute right-4 pointer-events-none"></i>
                </div>

                <div class="flex-1 flex items-center px-4 py-3 md:py-0 w-full">
                    <i class="fa-solid fa-location-dot text-amber-500 mr-3"></i>
                    <input type="text" name="location" value="<?= htmlspecialchars($searchLocation ?? '') ?>" placeholder="Ville ou région..." class="w-full bg-transparent border-none focus:outline-none text-slate-900 font-bold placeholder-slate-400 text-sm">
                </div>
                
                <button type="submit" class="w-full md:w-auto mt-2 md:mt-0 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-900 font-black px-8 py-3.5 rounded-xl md:rounded-full transition shadow-lg flex items-center justify-center text-sm">
                    Rechercher
                </button>
            </form>
        </div>
    </section>

    <!-- Contenu Principal -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        
        <!-- RUBAN DES GRANDES FAMILLES -->
        <?php if (!empty($categoriesTree)): ?>
            <div class="flex items-center gap-3 overflow-x-auto pb-6 mb-4 no-scrollbar">
                <a href="<?= $baseUrl ?>/services?q=<?= urlencode($searchQuery ?? '') ?>&location=<?= urlencode($searchLocation ?? '') ?>" 
                   class="px-5 py-2.5 rounded-xl text-xs font-black transition-all whitespace-nowrap border <?= empty($searchCategory) ? 'bg-[#0B132B] text-white border-[#0B132B] shadow-md' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-border-all mr-2"></i> Toutes
                </a>
                
                <?php foreach ($categoriesTree as $parent): ?>
                    <a href="<?= $baseUrl ?>/services?category=<?= $parent['id'] ?>&q=<?= urlencode($searchQuery ?? '') ?>&location=<?= urlencode($searchLocation ?? '') ?>" 
                       class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap border <?= (($searchCategory ?? 0) == $parent['id']) ? 'bg-[#0B132B] text-white border-[#0B132B] shadow-md' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
                        <?= htmlspecialchars($parent['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- En-tête des résultats -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-briefcase text-amber-500"></i>
                <span><?= count($services ?? []) ?> Service(s) trouvé(s)</span>
            </h2>
            <?php if (!empty($searchQuery) || !empty($searchLocation) || !empty($searchCategory)): ?>
                <a href="<?= $baseUrl ?>/services" class="text-xs text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1">
                    <i class="fa-solid fa-xmark"></i> Effacer les filtres
                </a>
            <?php endif; ?>
        </div>

        <!-- Grille des Résultats -->
        <?php if (empty($services)): ?>
            <div class="bg-white rounded-3xl border border-slate-200 p-16 text-center shadow-sm max-w-3xl mx-auto mt-8">
                <div class="w-20 h-20 bg-slate-50 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-6 text-4xl shadow-inner">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-900 mb-2">Aucun service disponible pour le moment</h3>
                <p class="text-slate-500 text-sm mb-8 font-medium">Ajustez vos filtres de recherche ou soyez le premier à proposer vos compétences !</p>
                <div class="flex justify-center gap-4">
                    <a href="<?= $baseUrl ?>/publish_service.php" class="bg-slate-900 hover:bg-amber-500 text-white hover:text-slate-900 font-bold px-6 py-3 rounded-xl transition text-sm shadow-md">
                        Publier mon propre service
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($services as $service): ?>
                    <?php 
                        $imageSrc = !empty($service['image_path']) ? $baseUrl . '/' . htmlspecialchars($service['image_path']) : $baseUrl . '/assets/images/placeholder-service.jpg';
                    ?>
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group transform hover:-translate-y-1">
                        
                        <div class="relative aspect-video overflow-hidden bg-slate-100">
                            <!-- Image avec object-cover -->
                            <img src="<?= $imageSrc ?>" alt="<?= htmlspecialchars($service['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                            <span class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-md text-white text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full shadow-sm border border-slate-700">
                                <?= htmlspecialchars($service['category_name'] ?? 'Prestation') ?>
                            </span>
                        </div>

                        <div class="p-5 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-[11px] font-bold text-slate-500 bg-slate-50 px-2 py-1 rounded-md flex items-center border border-slate-100">
                                        <i class="fa-solid fa-location-dot text-amber-500 mr-1.5"></i> <?= htmlspecialchars($service['location'] ?? 'À distance') ?>
                                    </span>
                                </div>
                                <h3 class="font-black text-slate-900 text-lg line-clamp-2 hover:text-amber-500 transition leading-tight mb-2">
                                    <a href="<?= $baseUrl ?>/services/show?id=<?= $service['id'] ?>">
                                        <?= htmlspecialchars($service['title']) ?>
                                    </a>
                                </h3>
                                <p class="text-sm text-slate-500 line-clamp-2">
                                    <?= htmlspecialchars($service['description']) ?>
                                </p>
                            </div>

                            <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between">
                                <div class="text-amber-600 font-black text-lg">
                                    <?= !empty($service['price']) ? number_format($service['price'], 0, ',', ' ') . ' <span class="text-[10px] font-bold text-slate-500 uppercase">' . htmlspecialchars($service['currency'] ?? 'FCFA') . '</span>' : '<span class="text-sm">Sur devis</span>' ?>
                                </div>
                                <a href="<?= $baseUrl ?>/services/show?id=<?= $service['id'] ?>" class="w-10 h-10 rounded-full bg-slate-50 text-slate-500 hover:bg-amber-500 hover:text-slate-900 flex items-center justify-center transition shadow-sm border border-slate-200 group-hover:border-amber-400">
                                    <i class="fa-solid fa-arrow-right text-sm"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Pied de page -->
    <footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm font-medium">
            <p>&copy; <?= date('Y') ?> MAN GO — One Market, One Movement. Tous droits réservés.</p>
        </div>
    </footer>

</body>
</html>