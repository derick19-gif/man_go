<?php
// app/views/layouts/footer.php
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// Gestion intelligente du namespace pour le Footer
$sessionClassFooter = class_exists('App\Core\Session') ? 'App\Core\Session' : (class_exists('Session') ? 'Session' : null);

if ($sessionClassFooter) {
    $isLoggedInFooter = $sessionClassFooter::isAuthenticated();
    $userRoleFooter = $sessionClassFooter::get('user_role') ?: '';
} else {
    $isLoggedInFooter = !empty($_SESSION['user_id']) || !empty($_SESSION['user']);
    $userRoleFooter = $_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? '';
}

$dashboardLinkFooter = ($userRoleFooter === 'vendor') ? $baseUrl . '/vendor_dir/dashboard.php' : $baseUrl . '/client/views/dashboard.php';
?>

<footer class="bg-slate-950 border-t border-slate-900 pt-16 pb-8 text-slate-400 relative z-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
            
            <!-- Colonne 1 : Marque -->
            <div class="col-span-1 md:col-span-1">
                <a href="<?= $baseUrl ?>/" class="flex items-center space-x-3 text-white mb-6">
                    <div class="bg-amber-500 text-slate-950 font-black text-lg w-9 h-9 rounded-xl flex items-center justify-center">M</div>
                    <span class="font-extrabold text-xl tracking-tight">MAN <span class="text-amber-500">GO</span></span>
                </a>
                <p class="text-sm leading-relaxed mb-6">
                    La marketplace universelle sans limite. <br>
                    <strong class="text-amber-500/80">One Market, One Movement.</strong>
                </p>
            </div>

            <!-- Colonne 2 : Navigation -->
            <div>
                <h4 class="text-white font-bold mb-6 tracking-wider text-sm uppercase">Navigation</h4>
                <ul class="space-y-4 text-sm font-medium">
                    <li><a href="<?= $baseUrl ?>/listings" class="hover:text-amber-500 transition">Toutes les annonces</a></li>
                    <li><a href="<?= $baseUrl ?>/stands" class="hover:text-amber-500 transition">Boutiques certifiées</a></li>
                    <li><a href="<?= $baseUrl ?>/services" class="hover:text-amber-500 transition">Prestataires de services</a></li>
                </ul>
            </div>

            <!-- Colonne 3 : Support -->
            <div>
                <h4 class="text-white font-bold mb-6 tracking-wider text-sm uppercase">Support</h4>
                <ul class="space-y-4 text-sm font-medium">
                    <li><a href="<?= $baseUrl ?>/faq.php" class="hover:text-amber-500 transition">Foire aux questions</a></li>
                    <li><a href="<?= $baseUrl ?>/terms.php" class="hover:text-amber-500 transition">Conditions d'utilisation</a></li>
                    <li><a href="#" class="hover:text-amber-500 transition">Nous contacter</a></li>
                </ul>
            </div>

            <!-- Colonne 4 : Compte (DYNAMIQUE) -->
            <div>
                <h4 class="text-white font-bold mb-6 tracking-wider text-sm uppercase">Compte</h4>
                <ul class="space-y-4 text-sm font-medium">
                    <?php if ($isLoggedInFooter): ?>
                        <li><a href="<?= $dashboardLinkFooter ?>" class="text-amber-500 hover:text-amber-400 transition flex items-center space-x-2"><i class="fa-solid fa-gauge-high"></i> <span>Mon Tableau de bord</span></a></li>
                        <li><a href="<?= $dashboardLinkFooter ?>#tab-settings" class="hover:text-amber-500 transition">Paramètres</a></li>
                        <li><a href="<?= $baseUrl ?>/logout.php" class="text-red-400 hover:text-red-300 transition">Déconnexion</a></li>
                    <?php else: ?>
                        <li><a href="<?= $baseUrl ?>/login.php" class="hover:text-amber-500 transition">Connexion</a></li>
                        <li><a href="<?= $baseUrl ?>/register.php" class="hover:text-amber-500 transition">Inscription</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
        </div>

        <div class="border-t border-slate-900/50 pt-8 flex flex-col md:flex-row justify-between items-center text-xs font-medium">
            <p>&copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.</p>
        </div>
    </div>
</footer>

<!-- ========================================== -->
<!-- BANDEAU COOKIES MAN GO (PREMIUM & LÉGAL)   -->
<!-- ========================================== -->
<div id="cookie-consent-banner" class="fixed bottom-4 left-4 right-4 md:left-auto md:right-8 md:w-[480px] bg-slate-900 border border-slate-800 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] z-[9999] transform translate-y-[150%] opacity-0 transition-all duration-700 ease-[cubic-bezier(0.34,1.56,0.64,1)] hidden font-sans">
    <div class="p-5">
        <div class="flex items-start gap-4">
            
            <!-- Icône Bouclier (Plus pro qu'un cookie) -->
            <div class="bg-amber-500/10 p-3 rounded-full flex items-center justify-center border border-amber-500/20 shrink-0 mt-1">
                <i class="fa-solid fa-shield-halved text-amber-500 text-xl"></i>
            </div>
            
            <div>
                <h4 class="text-white font-black text-base mb-1.5 tracking-wide">Respect de votre vie privée</h4>
                <p class="text-slate-400 text-xs sm:text-sm leading-relaxed mb-4">
                    MAN GO utilise des traceurs pour assurer la sécurité de vos transactions (KYC) et améliorer votre expérience sur la marketplace. En continuant, vous acceptez notre politique.
                </p>
                
                <div class="flex items-center gap-3 w-full justify-end">
                    <a href="<?= $baseUrl ?>/terms.php" class="text-slate-400 hover:text-white text-xs font-semibold px-2 py-2 transition whitespace-nowrap">
                        En savoir plus
                    </a>
                    <button onclick="acceptMangoCookies()" class="bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black px-5 py-2.5 rounded-xl shadow-lg shadow-amber-500/20 transition-all text-sm flex items-center justify-center gap-2 hover:scale-105 active:scale-95">
                        <span>J'accepte</span>
                        <i class="fa-solid fa-check"></i>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const cookieBanner = document.getElementById('cookie-consent-banner');
    
    // Vérification du consentement
    if (!localStorage.getItem('mango_cookies_accepted')) {
        // Enlève la classe hidden pour rendre l'élément présent dans le DOM
        cookieBanner.classList.remove('hidden');
        
        // Délai pour laisser le DOM se mettre à jour avant de lancer l'animation "rebond"
        setTimeout(() => {
            cookieBanner.classList.remove('translate-y-[150%]', 'opacity-0');
        }, 300);
    }
});

function acceptMangoCookies() {
    const cookieBanner = document.getElementById('cookie-consent-banner');
    
    // Sauvegarde du choix
    localStorage.setItem('mango_cookies_accepted', 'true');
    
    // Animation de sortie vers le bas
    cookieBanner.classList.add('translate-y-[150%]', 'opacity-0');
    
    // Nettoyage final du DOM
    setTimeout(() => {
        cookieBanner.classList.add('hidden');
    }, 700);
}
</script>
<!-- ========================================== -->