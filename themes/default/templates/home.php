<?php 
$pageTitle = "MAN GO - One Market, One Movement";
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
require_once __DIR__ . '/layouts/header.php'; 
?>

<!-- Alpine JS pour le menu de recherche avancé -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- SECTION HERO (RECHERCHE FUTURISTE) -->
<div class="bg-slate-900 pt-24 pb-32 relative overflow-hidden">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-5"></div>
    <div class="max-w-5xl mx-auto px-4 relative z-10 text-center">
        <span class="bg-amber-500/20 text-amber-400 text-xs font-black px-4 py-1.5 rounded-full uppercase tracking-widest mb-4 inline-block">Marché International 2.0</span>
        <h1 class="text-4xl md:text-6xl font-black text-white mb-8 tracking-tight">Trouvez tout, <span class="text-amber-500">simplement.</span></h1>
        
        <!-- MOTEUR DE RECHERCHE -->
        <div x-data="advancedSearch()">
            <form action="<?= $baseUrl ?>/listings" method="GET" class="bg-slate-900/95 backdrop-blur-xl p-3 sm:p-4 rounded-3xl shadow-2xl border border-slate-700/60 grid grid-cols-1 md:grid-cols-12 gap-3 items-center text-left">
                
                <!-- Champ Recherche (Mots-clés) -->
                <div class="relative md:col-span-5">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-amber-500">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <input type="text" name="q" placeholder="Que recherchez-vous aujourd'hui ?" autocomplete="off" class="w-full pl-12 pr-4 py-4 bg-slate-800/80 text-white placeholder-slate-400 text-sm rounded-2xl border border-slate-700 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all shadow-inner">
                </div>

                <!-- Champ Localisation -->
                <div class="relative md:col-span-5">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-amber-500">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <button type="button" @click="openGeoModal = !openGeoModal" class="w-full pl-12 pr-10 py-4 bg-slate-800/80 text-left text-sm text-white rounded-2xl border border-slate-700 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all flex items-center justify-between shadow-inner truncate">
                        <span x-text="geoLabel || 'Pays, Région, Ville...'" :class="geoLabel ? 'text-white' : 'text-slate-400'"></span>
                        <i class="fa-solid fa-chevron-down text-slate-400"></i>
                    </button>

                    <!-- Panneau Flottant Géographique -->
                    <div x-show="openGeoModal" @click.away="openGeoModal = false" x-transition class="absolute left-0 right-0 mt-2 bg-slate-900 border border-slate-700 p-5 rounded-3xl shadow-2xl z-50 space-y-4">
                        <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">Localisation exacte</h3>
                            <button type="button" @click="openGeoModal = false" class="text-slate-400 hover:text-white">&times;</button>
                        </div>
                        <div>
                            <input type="text" x-model="selectedCity" placeholder="Ex: Lomé, Paris..." class="w-full bg-slate-800 border border-slate-700 text-white text-xs rounded-xl p-3 focus:border-amber-500 focus:outline-none mb-3">
                            <button type="button" @click="applyGeo()" class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs uppercase tracking-wider rounded-xl transition shadow-lg shadow-amber-500/20">
                                Appliquer
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="city" x-model="selectedCity">
                </div>

                <!-- Bouton de Recherche -->
                <div class="md:col-span-2">
                    <button type="submit" class="w-full py-4 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-extrabold text-sm rounded-2xl shadow-xl shadow-amber-500/20 transition-all flex items-center justify-center gap-2 transform active:scale-95">
                        <span>Rechercher</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<main class="max-w-7xl mx-auto px-4 sm:px-6 relative z-20 -mt-10 mb-24">
    
    <!-- CATÉGORIES -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-16">
        <?php foreach ($categories as $cat): ?>
            <a href="<?= $baseUrl ?>/listings?category=<?= $cat['id'] ?>" class="bg-white rounded-2xl p-6 text-center shadow-lg hover:shadow-xl hover:-translate-y-1 transition border border-slate-100 group relative">
                <div class="w-12 h-12 mx-auto bg-slate-50 text-slate-600 rounded-full flex items-center justify-center text-xl group-hover:bg-amber-50 group-hover:text-amber-500 transition mb-3">
                    <i class="<?= htmlspecialchars($cat['icon_class'] ?? 'fa-solid fa-tag') ?>"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800 mb-1"><?= htmlspecialchars($cat['name_key']) ?></h3>
                <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full"><?= $cat['total_listings'] ?> offres</span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ANNONCES VIP -->
    <?php if (!empty($vipListings)): ?>
    <div class="mb-16">
        <div class="flex justify-between items-end mb-6 border-b border-slate-200 pb-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-crown text-amber-500"></i> Boutiques VIP Recommandées
                </h2>
            </div>
            <a href="<?= $baseUrl ?>/listings?filter=vip" class="text-sm font-bold text-amber-600 hover:text-amber-700 hidden sm:block">Tout voir &rarr;</a>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($vipListings as $listing): ?>
                <div class="bg-white rounded-3xl border-2 border-amber-200 overflow-hidden shadow-sm hover:shadow-xl transition group relative">
                    <div class="absolute top-3 right-3 bg-amber-500 text-slate-900 text-[10px] font-black px-3 py-1 rounded-full shadow-md z-10 uppercase tracking-widest">
                        VIP
                    </div>
                    <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $listing['id'] ?>" class="block relative h-48 overflow-hidden bg-slate-100">
                        <img src="<?= htmlspecialchars(!empty($listing['image_path']) ? $baseUrl.'/'.$listing['image_path'] : $baseUrl.'/assets/images/placeholder.jpg') ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                    </a>
                    <div class="p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1">
                            <i class="fa-solid fa-store text-amber-500"></i> <?= htmlspecialchars(trim($listing['firstname'] . ' ' . $listing['lastname'])) ?>
                        </div>
                        <h3 class="font-bold text-slate-900 leading-tight mb-2 line-clamp-2 hover:text-amber-600 transition">
                            <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $listing['id'] ?>"><?= htmlspecialchars($listing['title']) ?></a>
                        </h3>
                        <div class="flex justify-between items-end mt-4">
                            <span class="text-lg font-black text-amber-600"><?= number_format($listing['price'], 0, ',', ' ') ?> FCFA</span>
                            <span class="text-xs text-slate-400"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($listing['city'] ?? 'Lomé') ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ANNONCES RÉCENTES -->
    <div>
        <div class="flex justify-between items-end mb-6 border-b border-slate-200 pb-4">
            <h2 class="text-2xl font-black text-slate-900">Récemment publiées</h2>
            <a href="<?= $baseUrl ?>/listings" class="text-sm font-bold text-slate-600 hover:text-slate-900 hidden sm:block">Explorer &rarr;</a>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($recentListings as $listing): ?>
                <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-lg transition group">
                    <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $listing['id'] ?>" class="block relative h-48 overflow-hidden bg-slate-100">
                        <img src="<?= htmlspecialchars(!empty($listing['image_path']) ? $baseUrl.'/'.$listing['image_path'] : $baseUrl.'/assets/images/placeholder.jpg') ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                    </a>
                    <div class="p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                            <?= htmlspecialchars(trim($listing['firstname'] . ' ' . $listing['lastname'])) ?>
                        </div>
                        <h3 class="font-bold text-slate-900 leading-tight mb-2 line-clamp-2 hover:text-amber-500 transition">
                            <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $listing['id'] ?>"><?= htmlspecialchars($listing['title']) ?></a>
                        </h3>
                        <div class="flex justify-between items-end mt-4">
                            <span class="text-lg font-black text-slate-900"><?= number_format($listing['price'], 0, ',', ' ') ?> FCFA</span>
                            <span class="text-xs text-slate-400"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($listing['city'] ?? 'Lomé') ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('advancedSearch', () => ({
        openGeoModal: false,
        selectedCity: '',
        geoLabel: '',

        applyGeo() {
            this.geoLabel = this.selectedCity ? this.selectedCity : '';
            this.openGeoModal = false;
        }
    }));
});
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>