<?php 
// =========================================================================
// VUE DE LA PAGE D'ACCUEIL - home.php
// =========================================================================

// Chargement du header futuriste (On s'assure d'appeler le bon fichier centralisé)
$headerPath = __DIR__ . '/layouts/header.php';
if (!file_exists($headerPath)) $headerPath = __DIR__ . '/../../themes/default/templates/layouts/header.php';
if (file_exists($headerPath)) require_once $headerPath;
?>

<!-- Arrière-plan : Slider Dynamique de 4 secteurs d'activités -->
<div class="absolute inset-0 z-0 overflow-hidden" id="hero-slider">
    <!-- Image 1 : Tech & Business (Active par défaut) -->
    <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1920&q=80" 
         class="bg-slide absolute w-full h-full object-cover transition-opacity duration-1000 opacity-60 mix-blend-overlay" />
    
    <!-- Image 2 : Logistique & Transport Mondiale -->
    <img src="https://images.unsplash.com/photo-1586528116311-ad8ed7c663b0?auto=format&fit=crop&w=1920&q=80" 
         class="bg-slide absolute w-full h-full object-cover transition-opacity duration-1000 opacity-0 mix-blend-overlay" />
    
    <!-- Image 3 : BTP & Immobilier -->
    <img src="https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1920&q=80" 
         class="bg-slide absolute w-full h-full object-cover transition-opacity duration-1000 opacity-0 mix-blend-overlay" />
    
    <!-- Image 4 : Artisanat & Commerce local -->
    <img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1920&q=80" 
         class="bg-slide absolute w-full h-full object-cover transition-opacity duration-1000 opacity-0 mix-blend-overlay" />
    
    <!-- Dégradé ajusté : Moins sombre pour rendre les images beaucoup plus claires -->
    <div class="absolute inset-0 bg-gradient-to-b from-slate-950/30 via-slate-900/50 to-slate-950"></div>
</div>

<!-- Script d'animation du Slider -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const slides = document.querySelectorAll('.bg-slide');
        let currentSlide = 0;
        
        if(slides.length > 0) {
            setInterval(() => {
                slides[currentSlide].classList.remove('opacity-60');
                slides[currentSlide].classList.add('opacity-0');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.remove('opacity-0');
                slides[currentSlide].classList.add('opacity-60');
            }, 5000); 
        }
    });
</script>

<!-- Contenu du Héros -->
<div class="max-w-5xl mx-auto relative z-10 pt-20">
    
    <!-- LE SLOGAN STARTUP -->
    <div class="inline-flex items-center space-x-2 mb-6 px-5 py-2 rounded-full border border-amber-500/40 bg-amber-500/10 backdrop-blur-md shadow-glow animate-pulse">
        <span class="relative flex h-3 w-3">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
        </span>
        <span class="text-amber-400 font-extrabold tracking-widest text-sm uppercase">
            One Market, One Movement.
        </span>
    </div>

    <h1 class="text-4xl sm:text-6xl md:text-7xl font-black tracking-tight leading-tight mb-6 text-white">
        L'écosystème mondial de <br class="hidden sm:block">
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-amber-600 drop-shadow-lg">MAN GO</span>
    </h1>
    
    <p class="text-gray-300 text-base sm:text-xl mt-4 max-w-3xl mx-auto font-medium leading-relaxed mb-10">
        Artisans, startups, multinationales, avocats, restaurants ou particuliers. 
        Quel que soit votre domaine, connectez-vous au marché universel sans limite.
    </p>

    <!-- Formulaire de recherche intelligent -->
    <form action="<?= $baseUrl ?>/listings.php" method="GET" class="mt-8 bg-white/10 backdrop-blur-xl p-3 rounded-2xl sm:rounded-full border border-white/20 shadow-2xl flex flex-col sm:flex-row gap-2 max-w-4xl mx-auto mb-20">
        <?php if(!empty($lang)): ?>
            <input type="hidden" name="lang" value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>

        <div class="flex-1 flex items-center bg-white rounded-xl sm:rounded-full px-5 py-3.5 text-gray-800 shadow-inner">
            <i class="fa-solid fa-magnifying-glass text-amber-500 text-lg mr-3"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search_query ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Un bien, un service, une entreprise..." class="w-full text-base bg-transparent border-none focus:outline-none placeholder-gray-400 font-medium">
        </div>
        
        <div class="sm:w-1/3 flex items-center bg-white rounded-xl sm:rounded-full px-5 py-3.5 text-gray-800 shadow-inner">
            <i class="fa-solid fa-location-dot text-amber-500 text-lg mr-3"></i>
            <input type="text" name="city" value="<?= htmlspecialchars($search_city ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ville ou Pays" class="w-full text-base bg-transparent border-none focus:outline-none placeholder-gray-400 font-medium">
        </div>
        
        <button type="submit" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black px-10 py-3.5 rounded-xl sm:rounded-full text-base transition transform hover:-translate-y-1 shadow-futuristic flex items-center justify-center space-x-2">
            <span>Explorer</span>
        </button>
    </form>
</div>

<!-- Section Exploration par Catégories (Animée et Tactile) -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full relative z-20">
    <div class="flex items-center space-x-3 mb-8 bg-slate-950 inline-flex px-6 py-2 rounded-full border border-slate-800 shadow-lg">
        <i class="fa-solid fa-globe text-amber-500 text-xl"></i>
        <h2 class="text-xl font-bold text-white">Un monde d'opportunités</h2>
    </div>

    <!-- Le bloc défilant (Désormais compatible avec le Swipe/Drag via le script dans le Footer) -->
    <div class="relative w-full py-4">
        <div id="category-scroller" class="flex space-x-5 overflow-x-auto hide-scrollbar flex-nowrap pb-6 pt-2 px-2">
            <?php if (!empty($categories)): ?>
                <!-- Boucle de vos catégories (dupliquée pour l'effet de longueur infini) -->
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <?php foreach ($categories as $cat): ?>
                        <?php $icon = (isset($getCategoryIcon) && is_callable($getCategoryIcon)) ? $getCategoryIcon($cat['name'] ?? '', $iconMap ?? []) : 'fa-box'; ?>
                        <a href="<?= $baseUrl ?>/listings.php?category=<?= (int)($cat['id'] ?? 0) ?>" class="flex-shrink-0 w-48 bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:border-amber-400 transition-all duration-300 transform hover:-translate-y-2 flex flex-col items-center text-center select-none">
                            <div class="w-14 h-14 bg-slate-50 text-slate-400 rounded-2xl flex items-center justify-center mb-4 hover:bg-amber-500 hover:text-slate-950 transition-colors duration-300 shadow-sm hover:shadow-glow">
                                <i class="fa-solid <?= $icon ?> text-2xl"></i>
                            </div>
                            <span class="text-sm font-extrabold text-slate-800 hover:text-amber-600 transition leading-snug whitespace-normal">
                                <?= htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php endfor; ?>
            <?php else: ?>
                <!-- Vos Fallbacks -->
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <a href="<?= $baseUrl ?>/listings.php" class="flex-shrink-0 w-48 bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center select-none">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 hover:bg-amber-500 hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-building text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 hover:text-amber-600">Immobilier & Foncier</span>
                    </a>
                     <a href="<?= $baseUrl ?>/listings.php" class="flex-shrink-0 w-48 bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center select-none">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 hover:bg-amber-500 hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-hotel text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 hover:text-amber-600">Hôtellerie & Restauration</span>
                    </a>
                     <a href="<?= $baseUrl ?>/listings.php" class="flex-shrink-0 w-48 bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center select-none">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 hover:bg-amber-500 hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-scale-balanced text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 hover:text-amber-600">Avocats & Juristes</span>
                    </a>
                    <a href="<?= $baseUrl ?>/listings.php" class="flex-shrink-0 w-48 bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center select-none">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 hover:bg-amber-500 hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-truck-fast text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 hover:text-amber-600">Logistique & Livraison</span>
                    </a>
                    <a href="<?= $baseUrl ?>/listings.php" class="flex-shrink-0 w-48 bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center select-none">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 hover:bg-amber-500 hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-laptop text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 hover:text-amber-600">Tech & Digital</span>
                    </a>
                    <a href="<?= $baseUrl ?>/listings.php" class="flex-shrink-0 w-48 bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition flex flex-col items-center text-center select-none">
                        <div class="w-14 h-14 bg-slate-50 text-slate-400 hover:bg-amber-500 hover:text-slate-950 rounded-2xl flex items-center justify-center mb-4 transition"><i class="fa-solid fa-hammer text-2xl"></i></div>
                        <span class="text-sm font-extrabold text-slate-800 hover:text-amber-600">Artisans & Bâtiment</span>
                    </a>
                <?php endfor; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- SCRIPT POUR LE DÉFILEMENT AUTOMATIQUE DOUX -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const scroller = document.getElementById('category-scroller');
    if (!scroller) return;

    let isHoveredOrDragged = false;

    // Met en pause si la souris est dessus ou si on touche l'écran
    scroller.addEventListener('mouseenter', () => isHoveredOrDragged = true);
    scroller.addEventListener('mouseleave', () => isHoveredOrDragged = false);
    scroller.addEventListener('touchstart', () => isHoveredOrDragged = true, {passive: true});
    scroller.addEventListener('touchend', () => {
        setTimeout(() => isHoveredOrDragged = false, 1500); // Reprend après 1.5s
    });
    scroller.addEventListener('mousedown', () => isHoveredOrDragged = true);
    scroller.addEventListener('mouseup', () => isHoveredOrDragged = false);

    // Moteur de défilement très doux (1 pixel toutes les 30 millisecondes)
    setInterval(() => {
        if (!isHoveredOrDragged) {
            scroller.scrollLeft += 1;
            
            // Si on arrive presque à la fin, on ramène le scroll au début discrètement
            if (scroller.scrollLeft >= (scroller.scrollWidth - scroller.clientWidth - 5)) {
                scroller.scrollLeft = 0;
            }
        }
    }, 30);
});
</script>

<!-- Section Dernières Annonces -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mb-20 w-full flex-1">
    <div class="flex items-center justify-between mb-10">
        <div class="flex items-center space-x-3">
            <i class="fa-solid fa-bolt text-amber-500 text-2xl"></i>
            <h2 class="text-2xl sm:text-3xl font-black text-white">
                <?= (!empty($search_query) || !empty($search_city)) ? 'Résultats de votre recherche' : 'En direct du marché' ?>
            </h2>
            <span class="text-sm font-bold bg-slate-800 text-amber-400 px-3 py-1 rounded-full border border-slate-700">
                <?= $totalListings ?? 0 ?> offres
            </span>
        </div>
        <a href="<?= $baseUrl ?>/listings.php" class="hidden sm:flex items-center space-x-2 text-slate-950 bg-amber-500 hover:bg-amber-400 px-5 py-2.5 rounded-full text-sm font-bold transition shadow-sm">
            <span>Tout explorer</span>
            <i class="fa-solid fa-arrow-right-long"></i>
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <?php if (!empty($listings)): ?>
            <?php foreach ($listings as $item): ?>
                <?php 
                    $imgPath = trim($item['image_path'] ?? $item['image_url'] ?? ''); 
                    if (empty($imgPath)) {
                        $imageSrc = 'https://via.placeholder.com/600x400/1e293b/f59e0b?text=MAN+GO';
                    } elseif (str_starts_with($imgPath, 'http://') || str_starts_with($imgPath, 'https://')) {
                        $imageSrc = $imgPath;
                    } else {
                        $imageSrc = $baseUrl . '/' . ltrim($imgPath, '/');
                    }
                ?>
                <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= (int)($item['id'] ?? 0) ?>" class="bg-white rounded-3xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group transform hover:-translate-y-2">
                    <div class="relative h-48 bg-slate-100 overflow-hidden">
                        <img src="<?= htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                        <?php if (!empty($item['category_name'])): ?>
                            <span class="absolute top-4 left-4 bg-slate-950/80 backdrop-blur-md text-white text-xs font-black uppercase tracking-wider px-4 py-1.5 rounded-full shadow-lg">
                                <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="p-5 flex flex-col flex-1 justify-between">
                        <div>
                            <h3 class="font-black text-slate-900 text-lg line-clamp-2 group-hover:text-amber-500 transition leading-tight mb-2">
                                <?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            
                            <!-- AJOUT DE LA DESCRIPTION (Coupée proprement sur 2 lignes avec line-clamp) -->
                            <?php if(!empty($item['description'])): ?>
                                <p class="text-sm text-slate-500 line-clamp-2 mb-3 leading-relaxed">
                                    <?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                            <div>
                                <span class="text-xl font-black text-slate-900">
                                    <?= number_format((float)($item['price'] ?? 0), 0, ',', ' ') ?> 
                                    <span class="text-amber-500 text-base"><?= htmlspecialchars($item['currency'] ?? 'FCFA', ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            </div>
                            <div class="bg-slate-100 text-slate-900 group-hover:bg-amber-500 group-hover:text-white w-8 h-8 rounded-full flex items-center justify-center transition">
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full bg-slate-50 p-16 rounded-3xl border border-dashed border-gray-300 text-center">
                <div class="w-20 h-20 bg-white shadow-sm rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-globe text-4xl text-amber-500"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Le marché vous attend</h3>
                <p class="text-slate-500 font-medium mb-6 max-w-md mx-auto">Aucune annonce ne correspond à votre recherche pour le moment.</p>
                <a href="<?= $baseUrl ?>/publish.php" class="inline-block bg-slate-900 hover:bg-amber-500 text-white hover:text-slate-950 font-bold px-8 py-3.5 rounded-full transition shadow-lg">
                    Soyez le premier à publier !
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination dynamique -->
    <?php if (isset($totalPages) && $totalPages > 1): ?>
        <div class="mt-16 flex justify-center items-center space-x-3">
            <?php if (isset($currentPage) && $currentPage > 1): ?>
                <a href="<?= isset($buildUrl) ? $buildUrl(['page' => $currentPage - 1]) : '#' ?>" class="px-5 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-amber-500 hover:text-white transition shadow-sm">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Précédent
                </a>
            <?php else: ?>
                <span class="px-5 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold text-gray-300 cursor-not-allowed">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Précédent
                </span>
            <?php endif; ?>

            <div class="hidden sm:flex items-center space-x-2">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if (isset($currentPage) && $p == $currentPage): ?>
                        <span class="w-10 h-10 flex items-center justify-center bg-slate-900 text-white rounded-xl text-sm font-black shadow-md">
                            <?= $p ?>
                        </span>
                    <?php else: ?>
                        <a href="<?= isset($buildUrl) ? $buildUrl(['page' => $p]) : '#' ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-amber-100 hover:text-amber-600 transition">
                            <?= $p ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>

            <?php if (isset($currentPage) && $currentPage < $totalPages): ?>
                <a href="<?= isset($buildUrl) ? $buildUrl(['page' => $currentPage + 1]) : '#' ?>" class="px-5 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-amber-500 hover:text-white transition shadow-sm">
                    Suivant <i class="fa-solid fa-arrow-right ml-2"></i>
                </a>
            <?php else: ?>
                <span class="px-5 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold text-gray-300 cursor-not-allowed">
                    Suivant <i class="fa-solid fa-arrow-right ml-2"></i>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php
// Chargement du footer
$footerPath = __DIR__ . '/layouts/footer.php';
if (!file_exists($footerPath)) $footerPath = __DIR__ . '/../../themes/default/templates/layouts/footer.php';
if (file_exists($footerPath)) require_once $footerPath;
?>