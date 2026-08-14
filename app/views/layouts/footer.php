<!-- Pied de page / Footer -->
    <footer class="bg-slate-950 text-gray-400 text-sm border-t border-slate-800 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="space-y-4">
                <a href="<?= $baseUrl ?>/" class="flex items-center space-x-2 text-white">
                    <span class="bg-amber-500 text-slate-950 font-black text-xl w-8 h-8 rounded-full flex items-center justify-center">M</span>
                    <span class="font-extrabold text-xl">MAN <span class="text-amber-500">GO</span></span>
                </a>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Plateforme universelle d'annonces, de boutiques virtuelles et de services.
                </p>
            </div>
            <div>
                <h4 class="text-white font-bold mb-4 text-xs tracking-wider uppercase">Navigation</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="<?= $baseUrl ?>/listings" class="hover:text-amber-500 transition">Toutes les annonces</a></li>
                    <li><a href="<?= $baseUrl ?>/stands" class="hover:text-amber-500 transition">Boutiques certifiées</a></li>
                    <li><a href="<?= $baseUrl ?>/services" class="hover:text-amber-500 transition">Prestataires de services</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-bold mb-4 text-xs tracking-wider uppercase">Support</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="<?= $baseUrl ?>/faq" class="hover:text-amber-500 transition">Foire aux questions</a></li>
                    <li><a href="<?= $baseUrl ?>/terms" class="hover:text-amber-500 transition">Conditions d'utilisation</a></li>
                    <li><a href="<?= $baseUrl ?>/contact" class="hover:text-amber-500 transition">Nous contacter</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-bold mb-4 text-xs tracking-wider uppercase">Compte</h4>
                <ul class="space-y-2 text-xs">
                    <?php if (!empty($isLoggedIn)): ?>
                        <li><a href="<?= $baseUrl ?>/dashboard.php" class="hover:text-amber-500 transition">Tableau de bord</a></li>
                        <li><a href="<?= $baseUrl ?>/logout.php" class="hover:text-amber-500 transition">Déconnexion</a></li>
                    <?php else: ?>
                        <li><a href="<?= $baseUrl ?>/login.php" class="hover:text-amber-500 transition">Connexion</a></li>
                        <li><a href="<?= $baseUrl ?>/register.php" class="hover:text-amber-500 transition">Inscription</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-900 py-6 text-center text-xs text-gray-500">
            &copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.
        </div>
    </footer>
</body>
</html>