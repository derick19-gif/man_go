<?php
// =========================================================================
// Page FAQ - MAN GO Marketplace (Version MVC)
// =========================================================================

// 1. On charge la configuration EN PREMIER (Crucial pour le nom de la session)
require_once __DIR__ . '/config/config.php';

// 2. Démarrage de la session AVEC LE BON NOM
if (defined('SESSION_NAME')) {
    session_name(SESSION_NAME);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// Définition du titre de la page pour le header
$pageTitle = 'Foire aux Questions (FAQ) - MAN GO';

// 3. Inclusion dynamique du Header (En-tête global)
$headerPath = __DIR__ . '/themes/default/templates/layouts/header.php';
if (!file_exists($headerPath)) {
    $headerPath = __DIR__ . '/app/views/layouts/header.php';
}
if (file_exists($headerPath)) {
    require_once $headerPath;
}
?>

<!-- 2. Contenu Principal de la Vue -->
<div class="bg-slate-50 min-h-screen pb-20">
    <main class="max-w-4xl mx-auto px-4 py-16 w-full relative z-20">
        <div class="text-center mb-12">
            <span class="bg-amber-100 text-amber-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Centre d'aide</span>
            <h1 class="text-3xl sm:text-4xl font-black text-slate-900 mt-3">Foire Aux Questions</h1>
            <p class="text-gray-600 text-sm sm:text-base mt-2">Retrouvez les réponses aux questions les plus fréquentes concernant l'utilisation de la marketplace MAN GO.</p>
        </div>

        <div class="space-y-4">
            <!-- Question 1 -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:border-amber-500/30 transition">
                <h3 class="text-base sm:text-lg font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-circle-question text-amber-500 mr-3"></i> Comment publier une annonce sur MAN GO ?
                </h3>
                <p class="text-gray-600 text-sm mt-3 pl-7 leading-relaxed">
                    Cliquez simplement sur le bouton <strong class="text-slate-800">"Publier"</strong> en haut à droite, connectez-vous à votre compte, puis remplissez le formulaire avec les détails de votre article ou service.
                </p>
            </div>

            <!-- Question 2 -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:border-amber-500/30 transition">
                <h3 class="text-base sm:text-lg font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-circle-question text-amber-500 mr-3"></i> Qu'est-ce qu'un Stand ou une Boutique certifiée ?
                </h3>
                <p class="text-gray-600 text-sm mt-3 pl-7 leading-relaxed">
                    Un stand certifié est un espace vendeur vérifié qui permet aux professionnels de regrouper l'ensemble de leurs produits et services sous une même vitrine de confiance, augmentant ainsi leur visibilité.
                </p>
            </div>

            <!-- Question 3 -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:border-amber-500/30 transition">
                <h3 class="text-base sm:text-lg font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-circle-question text-amber-500 mr-3"></i> La publication d'annonces est-elle gratuite ?
                </h3>
                <p class="text-gray-600 text-sm mt-3 pl-7 leading-relaxed">
                    Oui, la publication d'annonces standard est totalement gratuite pour tous les utilisateurs de la plateforme. Des options de mise en avant (Premium) sont également disponibles.
                </p>
            </div>
        </div>

        <!-- Appel à l'action -->
        <div class="mt-12 bg-slate-900 rounded-3xl p-8 text-center text-white shadow-xl">
            <h3 class="text-xl font-black">Vous avez d'autres questions ?</h3>
            <p class="text-gray-400 text-sm mt-2">Notre équipe de support technique est à votre écoute pour vous accompagner 24h/24.</p>
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/" class="inline-block mt-6 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black px-8 py-3 rounded-xl text-sm transition-all shadow-lg hover:-translate-y-0.5">
                Retour à l'accueil
            </a>
        </div>
    </main>
</div>

<?php
// 3. Inclusion dynamique du Footer (Pied de page global)
$footerPath = __DIR__ . '/themes/default/templates/layouts/footer.php';
if (!file_exists($footerPath)) {
    $footerPath = __DIR__ . '/app/views/layouts/footer.php';
}
if (file_exists($footerPath)) {
    require_once $footerPath;
}
?>