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