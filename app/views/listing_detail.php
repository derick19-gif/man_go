<?php 
// Inclusion du Header
$headerPath = __DIR__ . '/layouts/header.php';
if (!file_exists($headerPath)) $headerPath = __DIR__ . '/../../themes/default/templates/layouts/header.php';
if (file_exists($headerPath)) require_once $headerPath;

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// Nettoyage et formatage des données
$imgPath = trim($listing['image_path'] ?? '');
$imageSrc = empty($imgPath) ? 'https://via.placeholder.com/800x600/1e293b/f59e0b?text=Image+Non+Disponible' : $baseUrl . '/' . ltrim($imgPath, '/');
$vendorName = trim(($listing['firstname'] ?? '') . ' ' . ($listing['lastname'] ?? ''));
if (empty($vendorName)) $vendorName = 'Vendeur Anonyme';
$vendorInitial = strtoupper(substr($listing['firstname'] ?? 'V', 0, 1));
$datePublished = date('d/m/Y', strtotime($listing['created_at']));
?>

<div class="bg-slate-50 min-h-screen py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Fil d'ariane (Breadcrumb) -->
        <nav class="text-sm font-medium text-slate-500 mb-6 flex items-center space-x-2">
            <a href="<?= $baseUrl ?>/" class="hover:text-amber-500 transition"><i class="fa-solid fa-house mr-1"></i> Accueil</a>
            <i class="fa-solid fa-chevron-right text-[10px]"></i>
            <a href="<?= $baseUrl ?>/listings?category=<?= $listing['category_id'] ?>" class="hover:text-amber-500 transition"><?= htmlspecialchars($listing['category_name'] ?? 'Général') ?></a>
            <i class="fa-solid fa-chevron-right text-[10px]"></i>
            <span class="text-slate-800 truncate max-w-xs"><?= htmlspecialchars($listing['title']) ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- COLONNE GAUCHE (Image + Description) -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Image Principale -->
                <div class="bg-white rounded-3xl border border-slate-200 p-2 shadow-sm">
                    <div class="w-full h-80 sm:h-[500px] bg-slate-100 rounded-2xl overflow-hidden relative">
                        <img src="<?= htmlspecialchars($imageSrc) ?>" alt="<?= htmlspecialchars($listing['title']) ?>" class="w-full h-full object-cover">
                    </div>
                </div>

                <!-- Description détaillée -->
                <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm">
                    <h2 class="text-2xl font-black text-slate-900 mb-6 border-b border-slate-100 pb-4">Description complète</h2>
                    <div class="prose prose-slate max-w-none whitespace-pre-line text-slate-600 leading-relaxed">
                        <?= htmlspecialchars($listing['description']) ?>
                    </div>
                    
                    <?php if (!empty($listing['web_link'])): ?>
                    <div class="mt-8 pt-6 border-t border-slate-100">
                        <a href="<?= htmlspecialchars($listing['web_link']) ?>" target="_blank" class="inline-flex items-center text-amber-600 font-bold hover:text-amber-500 transition">
                            <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i> Visiter le site web du vendeur
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- COLONNE DROITE (Prix + Vendeur + Contact) -->
            <div class="space-y-6">
                <!-- Carte Prix -->
                <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm text-center">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 mb-4 leading-tight">
                        <?= htmlspecialchars($listing['title']) ?>
                    </h1>
                    <p class="text-4xl font-black text-amber-500 mb-2">
                        <?= number_format($listing['price'], 0, ',', ' ') ?> <span class="text-xl">FCFA</span>
                    </p>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-6">Publié le <?= $datePublished ?></p>
                    
                    <!-- Bouton Contacter -->
                    <a href="<?= $baseUrl ?>/chat?vendor_id=<?= $listing['user_id'] ?>&listing_id=<?= $listing['id'] ?>" class="w-full block bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 px-6 rounded-2xl transition transform hover:-translate-y-1 shadow-lg flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-message"></i> <span>Contacter le vendeur</span>
                    </a>
                </div>

                <!-- Carte Vendeur -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                    <h3 class="font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">À propos du vendeur</h3>
                    
                    <div class="flex items-center mb-6">
                        <?php if (!empty($listing['avatar'])): ?>
                            <img src="<?= $baseUrl ?>/<?= htmlspecialchars($listing['avatar']) ?>" class="w-16 h-16 rounded-full object-cover mr-4 border-2 border-amber-100">
                        <?php else: ?>
                            <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-600 font-black text-2xl flex items-center justify-center mr-4 border-2 border-amber-200">
                                <?= $vendorInitial ?>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <h4 class="font-black text-slate-900 text-lg"><?= htmlspecialchars($vendorName) ?></h4>
                            <p class="text-xs text-slate-500 font-medium">Membre depuis <?= date('Y', strtotime($listing['vendor_since'] ?? 'now')) ?></p>
                            <span class="inline-flex items-center mt-1 bg-emerald-50 text-emerald-600 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                <i class="fa-solid fa-shield-check mr-1"></i> Vendeur Vérifié (KYC)
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($listing['phone'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $listing['phone']) ?>" target="_blank" class="w-full flex items-center justify-center space-x-2 border-2 border-[#25D366] text-[#25D366] hover:bg-[#25D366] hover:text-white font-bold py-3 rounded-xl transition">
                        <i class="fa-brands fa-whatsapp text-xl"></i> <span>WhatsApp Direct</span>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Sécurité -->
                <div class="bg-amber-50 rounded-2xl p-5 border border-amber-100 flex items-start space-x-3 text-amber-800 text-sm">
                    <i class="fa-solid fa-lock text-lg mt-0.5"></i>
                    <p class="font-medium">Pour votre sécurité, privilégiez toujours les paiements en personne lors de la remise de l'article.</p>
                </div>
            </div>

        </div>
    </div>
</div>

<?php 
// Inclusion du Footer
$footerPath = __DIR__ . '/layouts/footer.php';
if (!file_exists($footerPath)) $footerPath = __DIR__ . '/../../themes/default/templates/layouts/footer.php';
if (file_exists($footerPath)) require_once $footerPath;
?>