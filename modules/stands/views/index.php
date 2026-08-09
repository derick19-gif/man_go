<?php
// modules/stands/views/index.php

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
require_once __DIR__ . '/../../../themes/default/templates/layouts/header.php';
?>

<!-- Hero Header avec Barre de Recherche -->
<section class="bg-[#0B132B] text-white py-12 px-4 text-center border-t border-slate-800">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl md:text-5xl font-black tracking-tight mb-3">
            Découvrez les <span class="text-[#F59E0B]">Boutiques & Stands</span> Officiels
        </h1>
        <p class="text-slate-300 text-sm md:text-base max-w-2xl mx-auto mb-8">
            Explorez des centaines de vendeurs locaux certifiés, découvrez leurs catalogues complets et contactez-les en direct.
        </p>

        <!-- Formulaire de Recherche -->
        <form action="<?= $baseUrl ?>/stands" method="GET" class="bg-white/10 p-2 md:p-3 rounded-2xl backdrop-blur-md border border-white/10 grid grid-cols-1 md:grid-cols-12 gap-2 text-slate-800">
            <div class="md:col-span-5 relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Nom du stand ou mot-clé..." class="w-full pl-11 pr-4 py-3 bg-white rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#F59E0B]">
            </div>

            <div class="md:col-span-4 relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                    <i class="fa-solid fa-location-dot"></i>
                </span>
                <input type="text" name="location" value="<?= htmlspecialchars($location ?? '') ?>" placeholder="Ville ou région (ex: Lomé)" class="w-full pl-11 pr-4 py-3 bg-white rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#F59E0B]">
            </div>

            <div class="md:col-span-3">
                <button type="submit" class="w-full bg-[#F59E0B] hover:bg-amber-600 text-white font-bold py-3 px-6 rounded-xl text-sm transition-all shadow-md flex items-center justify-center gap-2">
                    <i class="fa-solid fa-filter"></i>
                    <span>Filtrer</span>
                </button>
            </div>
        </form>
    </div>
</section>

<!-- Contenu Principal -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex-grow w-full">
    
    <!-- Filtres rapides par catégorie -->
    <?php if (!empty($categories)): ?>
        <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-8 no-scrollbar">
            <a href="<?= $baseUrl ?>/stands" class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap <?= empty($category) ? 'bg-[#0B132B] text-white shadow-md' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                Toutes les catégories
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?= $baseUrl ?>/stands?category=<?= urlencode($cat) ?>&search=<?= urlencode($search ?? '') ?>&location=<?= urlencode($location ?? '') ?>" 
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
            <span><?= (int)($totalStands ?? 0) ?> Boutique<?= ($totalStands ?? 0) > 1 ? 's' : '' ?> & Stand<?= ($totalStands ?? 0) > 1 ? 's' : '' ?> disponible<?= ($totalStands ?? 0) > 1 ? 's' : '' ?></span>
        </h2>
        <?php if (!empty($search) || !empty($location) || !empty($category)): ?>
            <a href="<?= $baseUrl ?>/stands" class="text-xs text-red-500 hover:underline font-semibold flex items-center gap-1">
                <i class="fa-solid fa-xmark"></i> Réinitialiser les filtres
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
            <p class="text-slate-500 text-sm mb-6">Ajustez vos critères de recherche ou explorez d'autres catégories.</p>
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
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col overflow-hidden group">
                    <div class="h-28 bg-slate-200 relative overflow-hidden">
                        <img src="<?= $banner ?>" alt="<?= htmlspecialchars($stand['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=500&auto=format&fit=crop'">
                        <span class="absolute top-3 right-3 bg-[#0B132B]/80 text-white text-[10px] font-bold px-2.5 py-1 rounded-full backdrop-blur-sm">
                            <?= (int) ($stand['total_annonces'] ?? 0) ?> annonce<?= ($stand['total_annonces'] ?? 0) > 1 ? 's' : '' ?>
                        </span>
                    </div>

                    <div class="p-5 pt-0 flex-grow flex flex-col relative">
                        <div class="-mt-10 mb-3 flex items-end justify-between">
                            <div class="w-16 h-16 rounded-2xl border-4 border-white bg-white shadow-md overflow-hidden flex-shrink-0">
                                <img src="<?= $logo ?>" alt="Logo <?= htmlspecialchars($stand['name']) ?>" class="w-full h-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($stand['name']) ?>&background=0B132B&color=F59E0B'">
                            </div>
                            <?php if (!empty($stand['city'])): ?>
                                <span class="text-xs font-semibold text-slate-500 flex items-center gap-1 mb-1">
                                    <i class="fa-solid fa-location-dot text-[#F59E0B]"></i> <?= htmlspecialchars($stand['city']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3 class="text-base font-bold text-slate-800 line-clamp-1 group-hover:text-[#F59E0B] transition-colors">
                            <?= htmlspecialchars($stand['name']) ?>
                        </h3>

                        <?php if (!empty($stand['category'])): ?>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md w-max my-1.5">
                                <?= htmlspecialchars($stand['category']) ?>
                            </span>
                        <?php endif; ?>

                        <p class="text-xs text-slate-500 line-clamp-2 my-2 flex-grow">
                            <?= htmlspecialchars($stand['description'] ?? 'Bienvenue dans notre boutique officielle sur MAN GO Marketplace !') ?>
                        </p>

                        <div class="pt-3 border-t border-slate-100 flex items-center gap-2 mt-auto">
                            <a href="<?= $baseUrl ?>/stands/<?= urlencode($stand['slug'] ?? (string)$stand['id']) ?>" 
                               class="flex-grow bg-[#0B132B] hover:bg-slate-800 text-white font-bold py-2.5 px-3 rounded-xl text-xs text-center transition-all flex items-center justify-center gap-1.5">
                                <span>Visiter le Stand</span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>

                            <?php if (!empty($stand['whatsapp'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $stand['whatsapp']) ?>" 
                                   target="_blank" 
                                   title="Contacter sur WhatsApp"
                                   class="bg-emerald-500 hover:bg-emerald-600 text-white w-9 h-9 rounded-xl flex items-center justify-center transition-all shadow-md shadow-emerald-500/20">
                                    <i class="fa-brands fa-whatsapp text-base"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if (($totalPages ?? 1) > 1): ?>
            <div class="flex justify-center items-center space-x-2 mt-12">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?= $baseUrl ?>/stands?page=<?= $i ?>&search=<?= urlencode($search ?? '') ?>&location=<?= urlencode($location ?? '') ?>&category=<?= urlencode($category ?? '') ?>" 
                       class="w-10 h-10 rounded-xl font-bold text-xs flex items-center justify-center transition-all <?= $i === ($page ?? 1) ? 'bg-[#0B132B] text-white shadow-md' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../../../themes/default/templates/layouts/footer.php'; ?>