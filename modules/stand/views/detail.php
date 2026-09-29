<?php
// modules/stands/views/detail.php
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$pageTitle = htmlspecialchars($stand['name']) . ' - MAN GO';

// Inclusion correcte du header (en supposant la structure standard de MAN GO)
require_once __DIR__ . '/../../../themes/default/templates/layouts/header.php';

$logo = !empty($stand['logo']) ? $baseUrl . '/uploads/stands/' . htmlspecialchars($stand['logo']) : $baseUrl . '/assets/images/default-shop.png';
$banner = !empty($stand['banner']) ? $baseUrl . '/uploads/stands/' . htmlspecialchars($stand['banner']) : $baseUrl . '/assets/images/default-banner.jpg';
$isVip = isset($stand['is_premium']) && $stand['is_premium'] == 1;
?>

<div class="bg-slate-50 min-h-screen pb-24">
    
    <!-- En-tête / Bannière de la Boutique -->
    <div class="relative bg-slate-900 text-white">
        <!-- Image de fond (Bannière) -->
        <div class="absolute inset-0 z-0">
            <img src="<?= $banner ?>" class="w-full h-full object-cover opacity-40" alt="Bannière" onerror="this.src='https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=1600&auto=format&fit=crop'">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/60 to-transparent"></div>
        </div>

        <div class="relative z-10 max-w-6xl mx-auto px-4 pt-24 pb-12 sm:pt-32 sm:pb-16 flex flex-col md:flex-row items-center md:items-end gap-6 text-center md:text-left">
            
            <!-- Logo -->
            <div class="w-32 h-32 md:w-40 md:h-40 rounded-3xl border-4 <?= $isVip ? 'border-amber-400 bg-amber-50 shadow-[0_0_30px_rgba(245,158,11,0.3)]' : 'border-white bg-white shadow-xl' ?> overflow-hidden flex-shrink-0 relative">
                <img src="<?= $logo ?>" class="w-full h-full object-cover" alt="Logo de <?= htmlspecialchars($stand['name']) ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($stand['name']) ?>&background=0B132B&color=F59E0B'">
            </div>

            <!-- Informations -->
            <div class="flex-grow">
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 mb-2">
                    <span class="bg-[#0B132B] border border-slate-700 text-amber-500 text-xs font-black px-3 py-1 rounded-full uppercase tracking-widest shadow-sm">
                        <?= htmlspecialchars($stand['category'] ?? 'Boutique') ?>
                    </span>
                    <?php if ($isVip): ?>
                        <span class="bg-gradient-to-r from-amber-400 to-amber-600 text-white text-xs font-black px-3 py-1 rounded-full shadow-lg flex items-center gap-1">
                            <i class="fa-solid fa-crown"></i> VIP
                        </span>
                    <?php endif; ?>
                </div>
                
                <h1 class="text-3xl md:text-5xl font-black mb-2 flex items-center justify-center md:justify-start gap-3">
                    <?= htmlspecialchars($stand['name']) ?>
                    <?php if ($isVip): ?>
                        <i class="fa-solid fa-circle-check text-blue-500 text-2xl" title="Vendeur Certifié"></i>
                    <?php endif; ?>
                </h1>
                
                <p class="text-slate-300 text-sm md:text-base font-medium flex items-center justify-center md:justify-start gap-2">
                    <i class="fa-solid fa-location-dot text-amber-500"></i> 
                    <?= htmlspecialchars($stand['city']) ?> <?= !empty($stand['address']) ? '- ' . htmlspecialchars($stand['address']) : '' ?>
                </p>
                <div class="text-xs text-slate-400 mt-1 flex items-center justify-center md:justify-start gap-2">
                    <i class="fa-solid fa-user-tie"></i> Géré par : <span class="font-bold text-slate-200"><?= htmlspecialchars($stand['vendor_name'] ?? 'Vendeur Indépendant') ?></span>
                </div>
            </div>
            
            <!-- Boutons d'Action Rapide -->
            <div class="flex flex-col sm:flex-row gap-3 mt-6 md:mt-0 w-full md:w-auto">
                <a href="<?= $baseUrl ?>/chat.php?vendor_id=<?= $stand['user_id'] ?>" class="bg-white hover:bg-slate-100 text-slate-900 font-black py-3 px-6 rounded-xl shadow-lg transition-all flex items-center justify-center gap-2 border border-slate-200">
                    <i class="fa-solid fa-shield-halved text-amber-500"></i> Message Sécurisé
                </a>
                <?php if (!empty($stand['phone'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $stand['phone']) ?>?text=Bonjour,%20je%20suis%20sur%20votre%20vitrine%20MAN%20GO." target="_blank" class="bg-emerald-500 hover:bg-emerald-600 text-white font-black py-3 px-6 rounded-xl shadow-lg shadow-emerald-500/30 transition-all flex items-center justify-center gap-2">
                        <i class="fa-brands fa-whatsapp text-lg"></i> WhatsApp
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Colonne Gauche : Catalogue -->
        <div class="lg:col-span-8 xl:col-span-9 order-2 lg:order-1">
            <div class="flex justify-between items-end mb-6 border-b border-slate-200 pb-4">
                <h2 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked text-amber-500"></i> Catalogue des offres
                </h2>
                <span class="text-sm font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-lg"><?= count($listings ?? []) ?> produit(s)</span>
            </div>
            
            <?php if (!empty($listings)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($listings as $item): ?>
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col group relative">
                            <!-- Image du produit -->
                            <div class="h-48 bg-slate-100 relative overflow-hidden flex items-center justify-center">
                                <?php 
                                    $imgPath = trim($item['image_path'] ?? '');
                                    
                                    // On utilise EXACTEMENT la logique qui fonctionne sur la page de détail
                                    if (!empty($imgPath) && $imgPath !== 'assets/images/placeholder.jpg') {
                                        $itemImg = rtrim($baseUrl, '/') . '/' . ltrim($imgPath, '/');
                                    } else {
                                        $itemImg = rtrim($baseUrl, '/') . '/assets/images/default-product.jpg';
                                    }
                                ?>
                                <img src="<?= $itemImg ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="<?= htmlspecialchars($item['title']) ?>" onerror="this.src='<?= rtrim($baseUrl, '/') ?>/assets/images/default-product.jpg'">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </div>
                            
                            <!-- Infos du produit -->
                            <div class="p-5 flex-1 flex flex-col relative bg-white">
                                <h4 class="font-bold text-slate-800 line-clamp-1 text-sm mb-1 group-hover:text-amber-600 transition-colors">
                                    <?= htmlspecialchars($item['title']) ?>
                                </h4>
                                
                                <p class="text-xs text-slate-500 line-clamp-2 mb-3 flex-grow">
                                    <?= htmlspecialchars($item['description'] ?? 'Aucune description fournie.') ?>
                                </p>
                                
                                <div class="mt-auto pt-3 border-t border-slate-100 flex justify-between items-end">
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-0.5">Prix de vente</p>
                                        <p class="text-amber-600 font-black text-lg leading-none">
                                            <?= number_format($item['price'], 0, ',', ' ') ?> <span class="text-sm"><?= htmlspecialchars($item['currency'] ?? 'FCFA') ?></span>
                                        </p>
                                        <?php if (!empty($item['original_price']) && $item['original_price'] > $item['price']): ?>
                                            <p class="text-xs text-slate-400 line-through mt-1"><?= number_format($item['original_price'], 0, ',', ' ') ?> <?= htmlspecialchars($item['currency'] ?? 'FCFA') ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?= rtrim($baseUrl, '/') ?>/listing_detail?id=<?= $item['id'] ?>" class="w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center hover:bg-amber-500 transition-colors shadow-md">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white p-12 rounded-3xl text-center border border-slate-200 shadow-sm">
                    <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Catalogue vide</h3>
                    <p class="text-slate-500 text-sm">Ce stand n'a publié aucune offre pour le moment. Revenez plus tard !</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Colonne Droite : À propos -->
        <div class="lg:col-span-4 xl:col-span-3 order-1 lg:order-2">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 sticky top-24">
                <h3 class="text-lg font-black text-slate-900 mb-4 pb-4 border-b border-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-circle-info text-amber-500"></i> À propos
                </h3>
                <div class="text-slate-600 text-sm leading-relaxed mb-6">
                    <?php if (!empty($stand['description'])): ?>
                        <?= nl2br(htmlspecialchars($stand['description'])) ?>
                    <?php else: ?>
                        <p class="italic text-slate-400">Aucune description fournie par le vendeur.</p>
                    <?php endif; ?>
                </div>

                <div class="space-y-4 pt-4 border-t border-slate-100">
                    <?php if (!empty($stand['website'])): ?>
                        <a href="<?= htmlspecialchars($stand['website']) ?>" target="_blank" class="flex items-center gap-3 text-sm text-slate-600 hover:text-amber-600 font-semibold group transition-colors">
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-amber-100 group-hover:text-amber-600 transition-colors">
                                <i class="fa-solid fa-globe"></i>
                            </div>
                            Site internet officiel
                        </a>
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-3 text-sm text-slate-600 font-semibold">
                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        Inscrit depuis <?= date('F Y', strtotime($stand['created_at'])) ?>
                    </div>
                </div>

                <!-- Partage -->
                <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Partager la vitrine</p>
                    <div class="flex justify-center gap-2">
                        <a href="https://wa.me/?text=Découvrez%20cette%20boutique%20sur%20MAN%20GO%20:%20<?= urlencode($baseUrl . '/stands/detail?id=' . $stand['id']) ?>" target="_blank" class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center hover:bg-emerald-500 hover:text-white transition-colors">
                            <i class="fa-brands fa-whatsapp text-lg"></i>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($baseUrl . '/stands/detail?id=' . $stand['id']) ?>" target="_blank" class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-colors">
                            <i class="fa-brands fa-facebook-f text-lg"></i>
                        </a>
                        <button onclick="navigator.clipboard.writeText('<?= $baseUrl . '/stands/detail?id=' . $stand['id'] ?>'); alert('Lien copié !');" class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center hover:bg-slate-800 hover:text-white transition-colors" title="Copier le lien">
                            <i class="fa-solid fa-link"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../../../themes/default/templates/layouts/footer.php'; ?>