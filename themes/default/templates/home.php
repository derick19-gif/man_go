<?php
// Initialisation globale de la langue pour le template
$lang = $lang ?? $_GET['lang'] ?? $_SESSION['lang'] ?? 'fr';
$heroSlides = $heroSlides ?? [];
$categories = $categories ?? [];
$recentAds = $recentAds ?? [];
$featuredStands = $featuredStands ?? [];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAN GO - One Market, One Movement.</title>
    <!-- Tailwind CSS CDN avec palette officielle MAN GO -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        mango: {
                            DEFAULT: '#FF8C00', // Orange Mangue (Principal)
                            dark: '#E07B00',
                            light: '#FFA500'
                        },
                        nature: {
                            DEFAULT: '#00C853', // Vert Nature (Secondaire)
                            dark: '#009624'
                        },
                        night: {
                            DEFAULT: '#0F172A', // Bleu Nuit (Premium)
                            light: '#1E293B'
                        },
                        gold: '#FFD700' // Accent Or
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen font-sans">

    <!-- HEADER NAVIGATION (BLEU NUIT & ORANGE MANGUE) -->
    <header class="bg-night/95 backdrop-blur-md text-white sticky top-0 z-50 shadow-md border-b border-night-light">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo MAN GO -->
                <a href="/?lang=<?= urlencode($lang) ?>" class="flex items-center gap-2 font-black text-2xl tracking-wide text-white">
                    <span class="w-8 h-8 rounded-full bg-mango flex items-center justify-center text-white text-lg shadow">M</span>
                    MAN <span class="text-mango">GO</span>
                </a>

                <!-- Navigation principale -->
                <nav class="hidden md:flex space-x-6 text-sm font-medium">
                    <a href="/?lang=<?= urlencode($lang) ?>" class="text-mango font-bold transition">Accueil</a>
                    <a href="/annonces" class="text-gray-300 hover:text-white transition">Annonces</a>
                    <a href="/boutiques" class="text-gray-300 hover:text-white transition">Boutiques & Stands</a>
                    <a href="/services" class="text-gray-300 hover:text-white transition">Services</a>
                </nav>

                <!-- Actions et Slecteur de Langue -->
                <div class="flex items-center gap-4">
                    <!-- Slecteur de Langue -->
                    <div class="text-xs font-semibold flex gap-1">
                        <a href="?lang=fr" class="<?= $lang === 'fr' ? 'text-mango underline font-bold' : 'text-gray-400' ?>">FR</a>
                        <span class="text-gray-600">|</span>
                        <a href="?lang=en" class="<?= $lang === 'en' ? 'text-mango underline font-bold' : 'text-gray-400' ?>">EN</a>
                    </div>

                    <a href="/login" class="text-sm font-medium text-gray-300 hover:text-white transition">Connexion</a>
                    <a href="/annonces/creer" class="bg-mango hover:bg-mango-dark text-white text-sm font-semibold px-4 py-2 rounded-md shadow transition">
                        Publier une annonce
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- SECTION HERO AVEC SLIDER FOND BDD DYNAMIQUE -->
    <section class="relative bg-night text-white py-24 px-4 text-center overflow-hidden min-h-[500px] flex items-center justify-center">
        
        <!-- Diaporama d'images de fond charges depuis la BDD -->
        <div id="hero-bg-slider" class="absolute inset-0 z-0">
            <?php if (!empty($heroSlides)): ?>
                <?php foreach ($heroSlides as $index => $slide): ?>
                    <div class="hero-slide absolute inset-0 bg-cover bg-center transition-opacity duration-1000 <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>" 
                         style="background-image: url('<?= htmlspecialchars($slide['image_url'], ENT_QUOTES, 'UTF-8') ?>');"></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="hero-slide absolute inset-0 bg-cover bg-center opacity-100" style="background-image: url('https://images.unsplash.com/photo-1472851294608-062f824d29cc?auto=format&fit=crop&w=1920&q=80');"></div>
            <?php endif; ?>
        </div>

        <!-- Superposition Bleu Nuit translucide pour lisibilit -->
        <div class="absolute inset-0 bg-night/80 z-10"></div>

        <!-- Contenu Hero -->
        <div class="relative z-20 max-w-4xl mx-auto space-y-6">
            <span class="inline-block bg-mango/20 text-mango text-xs font-bold uppercase tracking-widest px-4 py-1.5 rounded-full border border-mango/40 backdrop-blur-sm">
                <?= htmlspecialchars(__t('hero_badge', [], $lang), ENT_QUOTES, 'UTF-8') ?>
            </span>
            <h1 class="text-4xl md:text-5xl font-black tracking-tight leading-tight drop-shadow-md">
                <?= htmlspecialchars(__t('hero_title', [], $lang), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="text-gray-200 max-w-2xl mx-auto text-sm md:text-base drop-shadow">
                <?= htmlspecialchars(__t('hero_subtitle', [], $lang), ENT_QUOTES, 'UTF-8') ?>
            </p>

<!-- ========================================================================= -->
<!-- NOUVEAU MOTEUR DE RECHERCHE FUTURISTE & AVANCÉ -->
<!-- ========================================================================= -->
<div class="relative max-w-5xl mx-auto pt-4" x-data="advancedSearch()">
    <form action="/man_go/search" method="GET" class="bg-slate-900/95 backdrop-blur-xl p-3 sm:p-4 rounded-3xl shadow-2xl border border-slate-700/60 grid grid-cols-1 md:grid-cols-12 gap-3 items-center text-left">
        
        <!-- CHAMP 1 : RECHERCHE UNIVERSELLE (Mots-clés) -->
        <div class="relative md:col-span-5">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-amber-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input 
                type="text" 
                name="q" 
                x-model="query"
                @input.debounce.300ms="fetchSuggestions()"
                @focus="showSuggestions = true"
                placeholder="Que recherchez-vous aujourd'hui ?" 
                class="w-full pl-12 pr-4 py-4 bg-slate-800/80 text-white placeholder-slate-400 text-sm rounded-2xl border border-slate-700 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all shadow-inner"
                autocomplete="off"
            >
            
            <!-- POPUP DE SUGGESTIONS DYNAMIQUES -->
            <div x-show="showSuggestions && suggestions.length > 0" @click.away="showSuggestions = false" x-transition class="absolute left-0 right-0 mt-2 bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden z-50 divide-y divide-slate-800">
                <template x-for="item in suggestions" :key="item.id">
                    <a :href="item.url" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800/70 transition text-left group">
                        <img :src="item.image || '/man_go/themes/default/assets/img/default.png'" class="w-10 h-10 rounded-xl object-cover border border-slate-700 group-hover:border-amber-500 transition">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate group-hover:text-amber-400 transition" x-text="item.title"></p>
                            <p class="text-xs text-slate-400 capitalize" x-text="item.type + ' • ' + (item.category || '')"></p>
                        </div>
                        <span class="text-xs font-semibold px-2 py-1 bg-amber-500/10 text-amber-400 rounded-lg" x-text="item.price ? item.price + ' FCFA' : ''"></span>
                    </a>
                </template>
            </div>
        </div>

        <!-- CHAMP 2 : LOCALISATION UNIVERSELLE & RAYON -->
        <div class="relative md:col-span-5">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-amber-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            
            <button 
                type="button" 
                @click="openGeoModal = !openGeoModal"
                class="w-full pl-12 pr-10 py-4 bg-slate-800/80 text-left text-sm text-white rounded-2xl border border-slate-700 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all flex items-center justify-between shadow-inner truncate"
            >
                <span x-text="geoLabel || 'Pays, Région, Ville, Quartier...'" :class="geoLabel ? 'text-white' : 'text-slate-400'"></span>
                <svg class="w-4 h-4 text-slate-400 transition-transform" :class="openGeoModal ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>

            <!-- PANNEAU FLOTTANT GÉOGRAPHIQUE -->
            <div x-show="openGeoModal" @click.away="openGeoModal = false" x-transition class="absolute left-0 right-0 mt-2 bg-slate-900 border border-slate-700 p-5 rounded-3xl shadow-2xl z-50 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Localisation & Rayon d'action</h3>
                    <button type="button" @click="openGeoModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Pays</label>
                        <select x-model="selectedCountry" class="w-full bg-slate-800 border border-slate-700 text-white text-xs rounded-xl p-2.5 focus:border-amber-500 focus:outline-none">
                            <option value="">Sélectionner un pays</option>
                            <option value="TG">Togo</option>
                            <option value="FR">France</option>
                            <option value="CI">Côte d'Ivoire</option>
                            <option value="SN">Sénégal</option>
                            <option value="BE">Belgique</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Région / État / Province</label>
                        <input type="text" x-model="selectedRegion" placeholder="Ex: Maritime, Île-de-France..." class="w-full bg-slate-800 border border-slate-700 text-white text-xs rounded-xl p-2.5 focus:border-amber-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Ville / Commune</label>
                        <input type="text" x-model="selectedCity" placeholder="Ex: Lomé, Paris..." class="w-full bg-slate-800 border border-slate-700 text-white text-xs rounded-xl p-2.5 focus:border-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Quartier / District</label>
                        <input type="text" x-model="selectedDistrict" placeholder="Ex: Adidogomé, Cocody..." class="w-full bg-slate-800 border border-slate-700 text-white text-xs rounded-xl p-2.5 focus:border-amber-500 focus:outline-none">
                    </div>
                </div>

                <!-- CURSEUR DE RAYON -->
                <div class="pt-2 border-t border-slate-800">
                    <div class="flex justify-between items-center mb-1">
                        <label class="text-xs font-medium text-slate-300">Rayon de recherche</label>
                        <span class="text-xs font-bold text-amber-400" x-text="radius + ' km'"></span>
                    </div>
                    <input type="range" x-model="radius" min="0" max="500" step="5" class="w-full accent-amber-500 bg-slate-800 cursor-pointer">
                </div>

                <button type="button" @click="applyGeo()" class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs uppercase tracking-wider rounded-xl transition shadow-lg shadow-amber-500/20">
                    Appliquer la localisation
                </button>
            </div>

            <!-- Champs cachés pour le formulaire -->
            <input type="hidden" name="country" x-model="selectedCountry">
            <input type="hidden" name="region" x-model="selectedRegion">
            <input type="hidden" name="city" x-model="selectedCity">
            <input type="hidden" name="district" x-model="selectedDistrict">
            <input type="hidden" name="radius" x-model="radius">
        </div>

        <!-- BOUTON DE RECHERCHE -->
        <div class="md:col-span-2">
            <button type="submit" class="w-full py-4 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-extrabold text-sm rounded-2xl shadow-xl shadow-amber-500/20 transition-all flex items-center justify-center gap-2 transform active:scale-95">
                <span>Rechercher</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </button>
        </div>
    </form>
</div>

    <!-- CATEGORIES COMPLTES (DEPUIS LA BDD) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
        <h2 class="text-xl font-bold text-center text-gray-900 mb-8"><?= htmlspecialchars(__t('cat_title', [], $lang), ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <a href="/man_go/annonces?category=<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col items-center justify-center p-6 bg-white border border-gray-200 rounded-xl hover:border-mango hover:shadow-md transition group">
                        <div class="w-12 h-12 rounded-full bg-mango/10 text-mango flex items-center justify-center mb-3 group-hover:bg-mango group-hover:text-white transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        </div>
                        <span class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars(__t($cat['name_key'], [], $lang), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

<!-- Script Alpine.js pour gérer la logique de recherche -->
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('advancedSearch', () => ({
        query: '',
        suggestions: [],
        showSuggestions: false,
        openGeoModal: false,
        selectedCountry: '',
        selectedRegion: '',
        selectedCity: '',
        selectedDistrict: '',
        radius: 25,
        geoLabel: '',

        fetchSuggestions() {
            if (this.query.length < 2) {
                this.suggestions = [];
                return;
            }
            fetch(`/man_go/api/v1/search/suggestions.php?q=${encodeURIComponent(this.query)}`)
                .then(res => res.json())
                .then(data => {
                    this.suggestions = data.results || [];
                })
                .catch(err => console.error('Erreur suggestions:', err));
        },

        applyGeo() {
            let parts = [];
            if (this.selectedDistrict) parts.push(this.selectedDistrict);
            if (this.selectedCity) parts.push(this.selectedCity);
            if (this.selectedRegion) parts.push(this.selectedRegion);
            if (this.selectedCountry) parts.push(this.selectedCountry);
            
            this.geoLabel = parts.join(', ');
            this.openGeoModal = false;
        }
    }));
});
</script>

    <!-- ANNONCES RCENTES (DEPUIS LA BDD) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="flex justify-between items-end mb-6">
            <div>
                <span class="text-xs font-bold text-mango uppercase tracking-wider">MAN GO</span>
                <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars(__t('recent_ads_title', [], $lang), ENT_QUOTES, 'UTF-8') ?></h2>
            </div>
            <a href="/annonces" class="text-mango hover:text-mango-dark font-semibold text-sm flex items-center gap-1 transition">
                <?= htmlspecialchars(__t('view_all', [], $lang), ENT_QUOTES, 'UTF-8') ?> &rarr;
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if (!empty($recentAds)): ?>
                <?php foreach ($recentAds as $ad): ?>
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition flex flex-col justify-between">
                        <div>
                            <div class="relative h-48 bg-gray-100 overflow-hidden">
                                <img src="<?= htmlspecialchars($ad['main_image'] ?? 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=600&q=80', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($ad['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover">
                                <?php if (!empty($ad['badge'])): ?>
                                    <span class="absolute top-3 left-3 bg-nature text-white text-xs font-bold px-2 py-1 rounded shadow">
                                        <?= htmlspecialchars($ad['badge'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="p-5">
                                <span class="text-xs text-gray-400 font-bold uppercase tracking-wider">
                                    <?= htmlspecialchars(__t($ad['category_name'] ?? 'cat_products', [], $lang), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <h3 class="font-bold text-lg text-gray-900 mt-1">
                                    <?= htmlspecialchars($ad['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                            </div>
                        </div>
                        <div class="px-5 pb-5 pt-2 border-t border-gray-100 flex items-center justify-between mt-auto">
                            <span class="text-mango font-black text-lg">
                                <?= number_format($ad['price'] ?? 0, 0, ',', ' ') ?> <?= htmlspecialchars($ad['currency'] ?? 'XOF', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <a href="/annonces/<?= (int)($ad['id'] ?? 0) ?>" class="bg-mango hover:bg-mango-dark text-white font-bold text-xs px-4 py-2 rounded transition">
                                Voir
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-500 text-sm col-span-2 text-center py-8">Aucune annonce disponible pour le moment.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- BOUTIQUES & STANDS CERTIFIS (DEPUIS LA BDD) -->
    <section class="bg-night text-white py-12 my-8 w-full">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
                <span class="text-xs font-bold text-nature uppercase tracking-widest">Espace Vendeurs</span>
                <h2 class="text-3xl font-bold"><?= htmlspecialchars(__t('certified_stands_title', [], $lang), ENT_QUOTES, 'UTF-8') ?></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if (!empty($featuredStands)): ?>
                    <?php foreach ($featuredStands as $stand): ?>
                        <div class="bg-night-light border border-slate-700 p-5 rounded-xl flex items-center gap-4 hover:border-mango transition">
                            <img src="<?= htmlspecialchars($stand['logo'] ?? 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($stand['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-16 h-16 rounded-full object-cover border-2 border-mango">
                            <div class="flex-1">
                                <h3 class="font-bold text-base text-white"><?= htmlspecialchars($stand['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="text-xs text-gray-400 mb-2"><?= htmlspecialchars($stand['category_description'] ?? 'Boutique Certifie', ENT_QUOTES, 'UTF-8') ?></p>
                                <a href="/boutiques/<?= (int)($stand['id'] ?? 0) ?>" class="text-mango hover:text-mango-light text-xs font-bold inline-flex items-center gap-1 transition">
                                    Visiter le stand &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- CALL TO ACTION (CTA) -->
    <section class="bg-mango text-white py-12 w-full">
        <div class="max-w-4xl mx-auto px-4 text-center space-y-4">
            <h2 class="text-3xl font-black"><?= htmlspecialchars(__t('cta_title', [], $lang), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-white/90 text-sm md:text-base max-w-2xl mx-auto">
                <?= htmlspecialchars(__t('cta_subtitle', [], $lang), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <div class="pt-2">
                <a href="/register?role=seller" class="bg-night hover:bg-black text-white font-bold text-sm px-8 py-3 rounded-md shadow-lg transition inline-block">
                    <?= htmlspecialchars(__t('btn_create_seller', [], $lang), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-night/95 text-gray-400 py-12 mt-auto border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-xs text-gray-500">
            &copy; <?= date('Y') ?> MAN GO. One Market, One Movement. Tous droits rservs.
        </div>
    </footer>

    <!-- SCRIPT DU SLIDER D'IMAGE DE FOND -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.hero-slide');
            let currentSlide = 0;

            if (slides.length > 1) {
                setInterval(() => {
                    slides[currentSlide].classList.remove('opacity-100');
                    slides[currentSlide].classList.add('opacity-0');

                    currentSlide = (currentSlide + 1) % slides.length;

                    slides[currentSlide].classList.remove('opacity-0');
                    slides[currentSlide].classList.add('opacity-100');
                }, 5000);
            }
        });
    </script>

</body>
</html>