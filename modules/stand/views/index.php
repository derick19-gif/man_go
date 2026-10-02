<?php
// modules/stands/views/index.php

$baseUrl = defined('APP_URL') ? APP_URL : '';
require_once __DIR__ . '/../../../themes/default/templates/layouts/header.php';

// Récupération intelligente des mots-clés (q ou search pour compatibilité)
$currentSearch = $_GET['q'] ?? $_GET['search'] ?? '';
$currentLocation = $_GET['location'] ?? '';
?>

<!-- Hero Header avec Barre de Recherche -->
<section class="bg-[#0B132B] text-white py-12 px-4 text-center border-t border-slate-800 relative">
    <div class="max-w-4xl mx-auto relative z-10">
        <h1 class="text-3xl md:text-5xl font-black tracking-tight mb-3">
            Découvrez les <span class="text-[#F59E0B]">Boutiques & Stands</span> Officiels
        </h1>
        <p class="text-slate-300 text-sm md:text-base max-w-2xl mx-auto mb-8">
            Explorez des centaines de vendeurs locaux certifiés, découvrez leurs catalogues complets et contactez-les en direct.
        </p>

        <!-- FORMULAIRE DE RECHERCHE DYNAMIQUE (STANDS) -->
        <form action="<?= $baseUrl ?>/stands" method="GET" class="w-full max-w-4xl mx-auto bg-white rounded-2xl md:rounded-full p-2 shadow-2xl flex flex-col md:flex-row items-center border border-slate-200 mt-8 relative z-20 text-slate-800">
            
            <!-- Champ 1 : Mots-clés -->
            <div class="flex-1 flex items-center px-4 py-3 md:py-0 w-full border-b md:border-b-0 md:border-r border-slate-200">
                <i class="fa-solid fa-magnifying-glass text-amber-500 mr-3"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($searchQuery ?? '') ?>" placeholder="Nom de boutique, marque..." class="w-full bg-transparent border-none focus:outline-none text-slate-900 font-bold placeholder-slate-400 text-sm">
            </div>

            <!-- Champ 2 : Catégorie (Arborescence) -->
            <div class="flex-1 flex items-center px-4 py-3 md:py-0 w-full border-b md:border-b-0 md:border-r border-slate-200 relative hidden md:flex">
                <i class="fa-solid fa-layer-group text-amber-500 mr-3"></i>
                <select name="category" class="w-full bg-transparent border-none focus:outline-none text-slate-900 font-bold text-sm cursor-pointer appearance-none">
                    <option value="0" class="font-black text-slate-900">Toutes les boutiques</option>
                    <!-- RUBAN DES GRANDES FAMILLES (Parents Uniquement) -->
                    <?php if (!empty($categoriesTree)): ?>
                        <div class="flex items-center gap-3 overflow-x-auto pb-6 mb-4 no-scrollbar">
                            <a href="<?= $baseUrl ?>/stands?q=<?= urlencode($searchQuery ?? '') ?>&location=<?= urlencode($searchLocation ?? '') ?>" 
                            class="px-5 py-2.5 rounded-xl text-xs font-black transition-all whitespace-nowrap border <?= empty($searchCategory) ? 'bg-[#0B132B] text-white border-[#0B132B] shadow-md' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
                                <i class="fa-solid fa-border-all mr-2"></i> Toutes
                            </a>
                            
                            <?php foreach ($categoriesTree as $parent): ?>
                                <a href="<?= $baseUrl ?>/stands?category=<?= $parent['id'] ?>&q=<?= urlencode($searchQuery ?? '') ?>&location=<?= urlencode($searchLocation ?? '') ?>" 
                                class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap border <?= (($searchCategory ?? 0) == $parent['id']) ? 'bg-[#0B132B] text-white border-[#0B132B] shadow-md' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
                                    <?= htmlspecialchars($parent['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </select>
                <i class="fa-solid fa-chevron-down text-xs text-slate-400 absolute right-4 pointer-events-none"></i>
            </div>
            
            <!-- Champ 3 : Localisation -->
            <div class="flex-1 flex items-center px-4 py-3 md:py-0 w-full">
                <i class="fa-solid fa-location-dot text-amber-500 mr-3"></i>
                <input type="text" name="location" value="<?= htmlspecialchars($searchLocation ?? '') ?>" placeholder="Ville ou région..." class="w-full bg-transparent border-none focus:outline-none text-slate-900 font-bold placeholder-slate-400 text-sm">
            </div>
            
            <!-- Bouton de validation -->
            <button type="submit" class="w-full md:w-auto mt-2 md:mt-0 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-900 font-black px-8 py-3.5 rounded-xl md:rounded-full transition shadow-lg flex items-center justify-center text-sm">
                Explorer
            </button>
        </form>
    </div>
</section>

<!-- Contenu Principal -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex-grow w-full">
    
    <!-- Filtres rapides par catégorie -->
    <?php if (!empty($categories)): ?>
        <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-8 no-scrollbar">
            <a href="<?= $baseUrl ?>/stands?q=<?= urlencode($currentSearch) ?>&location=<?= urlencode($currentLocation) ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap <?= empty($category) ? 'bg-[#0B132B] text-white shadow-md' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                Toutes les catégories
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?= $baseUrl ?>/stands?category=<?= urlencode($cat) ?>&q=<?= urlencode($currentSearch) ?>&location=<?= urlencode($currentLocation) ?>" 
                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap <?= ($category ?? '') === $cat ? 'bg-[#0B132B] text-white shadow-md' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- En-tête des résultats -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
            <i class="fa-solid fa-store text-[#F59E0B]"></i>
            <span><?= (int)($totalStands ?? count($stands ?? [])) ?> Boutique(s) trouvée(s)</span>
        </h2>
        <?php if (!empty($currentSearch) || !empty($currentLocation) || !empty($category)): ?>
            <a href="<?= $baseUrl ?>/stands" class="text-xs text-red-500 hover:underline font-semibold flex items-center gap-1">
                <i class="fa-solid fa-xmark"></i> Effacer les filtres
            </a>
        <?php endif; ?>
    </div>

    <!-- Grille des Boutiques / Stands -->
    <?php if (empty($stands)): ?>
        <div class="bg-white rounded-2xl p-12 text-center border border-slate-200 max-w-lg mx-auto my-8">
            <div class="w-16 h-16 bg-amber-50 text-[#F59E0B] rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fa-solid fa-store-slash"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2">Aucune boutique trouvée</h3>
            <p class="text-slate-500 text-sm mb-6">Ajustez vos critères de recherche ou explorez le monde entier.</p>
            <a href="<?= $baseUrl ?>/stands" class="inline-block bg-[#0B132B] text-white font-bold px-6 py-2.5 rounded-xl text-sm hover:bg-slate-800 transition-all">
                Voir toutes les boutiques
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($stands as $stand): ?>
                <?php 
                    $logo = !empty($stand['logo']) ? $baseUrl . '/uploads/stands/' . htmlspecialchars($stand['logo']) : $baseUrl . '/assets/images/default-shop.png';
                    $banner = !empty($stand['banner']) ? $baseUrl . '/uploads/stands/' . htmlspecialchars($stand['banner']) : $baseUrl . '/assets/images/default-banner.jpg';
                    $isVip = isset($stand['is_premium']) && $stand['is_premium'] == 1;
                ?>
                <div class="bg-white rounded-2xl border <?= $isVip ? 'border-amber-400 shadow-amber-500/20' : 'border-slate-200 shadow-sm' ?> hover:shadow-xl transition-all duration-300 flex flex-col overflow-hidden group relative">
                    
                    <?php if ($isVip): ?>
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-amber-400 to-amber-600 z-20"></div>
                        <div class="absolute top-3 left-3 bg-gradient-to-r from-amber-400 to-amber-600 text-white text-[10px] font-black px-3 py-1 rounded-full shadow-lg z-20 flex items-center gap-1">
                            <i class="fa-solid fa-crown"></i> VIP CERTIFIÉ
                        </div>
                    <?php endif; ?>

                    <!-- Bannière de la boutique -->
                    <div class="h-28 bg-slate-200 relative overflow-hidden">
                        <img src="<?= $banner ?>" alt="<?= htmlspecialchars($stand['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=500&auto=format&fit=crop'">
                        <span class="absolute top-3 right-3 bg-[#0B132B]/80 text-white text-[10px] font-bold px-2.5 py-1 rounded-full backdrop-blur-sm z-10">
                            <?= (int) ($stand['total_annonces'] ?? 0) ?> annonce<?= ($stand['total_annonces'] ?? 0) > 1 ? 's' : '' ?>
                        </span>
                    </div>

                    <div class="p-5 pt-0 flex-grow flex flex-col relative">
                        <!-- Logo (Avec object-cover car c'est un carré défini) -->
                        <div class="-mt-10 mb-3 flex items-end justify-between relative z-10">
                            <div class="w-16 h-16 rounded-2xl border-4 <?= $isVip ? 'border-amber-100' : 'border-white' ?> bg-white shadow-md overflow-hidden flex-shrink-0">
                                <img src="<?= $logo ?>" alt="Logo <?= htmlspecialchars($stand['name']) ?>" class="w-full h-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($stand['name']) ?>&background=0B132B&color=F59E0B'">
                            </div>
                            <?php if (!empty($stand['city'])): ?>
                                <span class="text-xs font-semibold text-slate-500 flex items-center gap-1 mb-1 bg-slate-50 px-2 py-1 rounded-full">
                                    <i class="fa-solid fa-location-dot text-[#F59E0B]"></i> <?= htmlspecialchars($stand['city']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3 class="text-base font-bold text-slate-800 line-clamp-1 group-hover:text-[#F59E0B] transition-colors flex items-center gap-2">
                            <?= htmlspecialchars($stand['name']) ?>
                            <?php if ($isVip): ?>
                                <i class="fa-solid fa-circle-check text-blue-500 text-sm" title="Vendeur Vérifié"></i>
                            <?php endif; ?>
                        </h3>

                        <?php if (!empty($stand['category'])): ?>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md w-max my-1.5 border border-amber-100">
                                <?= htmlspecialchars($stand['category']) ?>
                            </span>
                        <?php endif; ?>

                        <p class="text-xs text-slate-500 line-clamp-2 my-2 flex-grow">
                            <?= htmlspecialchars($stand['description'] ?? 'Bienvenue dans notre boutique officielle sur MAN GO.') ?>
                        </p>
                        
                        <div class="text-[10px] font-bold text-slate-400 mb-3 flex items-center gap-1">
                            <i class="fa-solid fa-user-tie"></i> Géré par : <?= htmlspecialchars($stand['vendor_name'] ?? 'Inconnu') ?>
                        </div>

                        <div class="pt-3 border-t <?= $isVip ? 'border-amber-100' : 'border-slate-100' ?> flex items-center gap-2 mt-auto">
                            <a href="<?= $baseUrl ?>/stands/detail?id=<?= $stand['id'] ?>" class="flex-grow bg-[#0B132B] hover:bg-slate-800 text-white font-bold py-2.5 px-3 rounded-xl text-xs text-center transition-all flex items-center justify-center gap-1.5 shadow-md">
                                <span>Visiter la Boutique</span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>

                            <?php if (!empty($stand['phone'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $stand['phone']) ?>?text=Bonjour,%20je%20viens%20depuis%20votre%20boutique%20MAN%20GO." 
                                   target="_blank" 
                                   title="Contacter sur WhatsApp"
                                   class="bg-emerald-500 hover:bg-emerald-600 text-white w-9 h-9 rounded-xl flex items-center justify-center transition-all shadow-md shadow-emerald-500/30">
                                    <i class="fa-brands fa-whatsapp text-base"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination dynamique connectée à la recherche -->
        <?php if (($totalPages ?? 1) > 1): ?>
            <div class="flex justify-center items-center space-x-2 mt-12">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?= $baseUrl ?>/stands?page=<?= $i ?>&q=<?= urlencode($currentSearch) ?>&location=<?= urlencode($currentLocation) ?>&category=<?= urlencode($category ?? '') ?>" 
                       class="w-10 h-10 rounded-xl font-bold text-xs flex items-center justify-center transition-all <?= $i === ($page ?? 1) ? 'bg-[#0B132B] text-white shadow-md' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../../../themes/default/templates/layouts/footer.php'; ?>