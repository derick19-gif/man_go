<?php
// =========================================================================
// Conditions Générales d'Utilisation (CGU) - MAN GO Marketplace (MVC)
// =========================================================================

$pageTitle = "Conditions d'Utilisation - MAN GO";

// 1. Inclusion dynamique du Header (qui gère intelligemment la connexion)
$headerPath = __DIR__ . '/themes/default/templates/layouts/header.php';
if (!file_exists($headerPath)) {
    $headerPath = __DIR__ . '/app/views/layouts/header.php';
}
if (file_exists($headerPath)) {
    require_once $headerPath;
}
?>

<!-- 2. Contenu Principal de la Vue -->
<div class="bg-gray-50 min-h-screen pb-20">
    <main class="max-w-4xl mx-auto px-4 py-16 w-full relative z-20">
        <div class="text-center mb-12">
            <span class="bg-amber-100 text-amber-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Légal</span>
            <h1 class="text-3xl sm:text-4xl font-black text-slate-900 mt-3">Conditions Générales d'Utilisation</h1>
            <p class="text-gray-600 text-sm sm:text-base mt-2">Dernière mise à jour : Août 2026</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm space-y-8 text-sm sm:text-base leading-relaxed text-gray-700">
            
            <section>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-scale-balanced text-amber-500 mr-3"></i> 1. Objet et Acceptation
                </h2>
                <p>
                    Les présentes Conditions Générales d'Utilisation régissent l'accès et l'utilisation de la plateforme <strong>MAN GO</strong>, marketplace universelle d'annonces, de boutiques virtuelles et de services. En accédant ou en utilisant la plateforme, vous acceptez sans réserve d'être lié par ces conditions.
                </p>
            </section>

            <section>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-user-shield text-amber-500 mr-3"></i> 2. Inscription, Compte et Vérification (KYC)
                </h2>
                <p>
                    Pour publier des annonces ou gérer un stand, l'utilisateur doit créer un compte. La création d'un stand professionnel est soumise à une procédure de vérification d'identité (KYC). Vous vous engagez à fournir des informations exactes et à jour. MAN GO se réserve le droit de suspendre, restreindre ou supprimer tout compte en cas de non-respect de ces règles ou d'activité frauduleuse.
                </p>
            </section>

            <section>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-bullhorn text-amber-500 mr-3"></i> 3. Publication d'Annonces et Modération
                </h2>
                <p>
                    Les utilisateurs peuvent publier des annonces ou mettre en avant leurs prestations via des stands. Toute publication de contenu illégal, contrefait, trompeur ou contraire aux bonnes mœurs est strictement interdite. <strong>L'équipe de modération de MAN GO</strong> se réserve le droit de retirer toute annonce jugée non conforme sans préavis.
                </p>
            </section>

            <section>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-shield-halved text-amber-500 mr-3"></i> 4. Responsabilité et Sécurité
                </h2>
                <p>
                    MAN GO agit exclusivement en tant qu'intermédiaire technologique facilitant la mise en relation entre acheteurs, vendeurs et prestataires locaux. La plateforme ne saurait être tenue responsable de l'aboutissement des transactions directes, des litiges, ou de la qualité des biens et services échangés entre les utilisateurs.
                </p>
            </section>

            <section>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-copyright text-amber-500 mr-3"></i> 5. Propriété Intellectuelle
                </h2>
                <p>
                    L'ensemble des éléments techniques et graphiques constituant la plateforme MAN GO (code, logos, design) sont protégés par le droit de la propriété intellectuelle. En publiant du contenu (photos d'articles, descriptions), l'utilisateur accorde à MAN GO une licence non exclusive pour afficher ce contenu sur la plateforme.
                </p>
            </section>

            <section>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-lock text-amber-500 mr-3"></i> 6. Données Personnelles
                </h2>
                <p>
                    Les informations recueillies lors de votre inscription et de la procédure KYC sont nécessaires à la gestion de la plateforme. Elles sont conservées de manière sécurisée et ne sont en aucun cas revendues à des tiers à des fins commerciales sans votre consentement.
                </p>
            </section>

            <section>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-envelope text-amber-500 mr-3"></i> 7. Contact
                </h2>
                <p>
                    Pour toute question relative aux présentes conditions ou pour signaler un abus, vous pouvez joindre l'administration de MAN GO via notre centre de support ou nos canaux officiels.
                </p>
            </section>

        </div>

        <div class="mt-12 text-center">
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/" class="inline-block bg-slate-900 hover:bg-slate-800 text-white font-bold px-8 py-3 rounded-full text-xs transition shadow-md">
                Retour à l'accueil
            </a>
        </div>
    </main>
</div>

<?php
// 3. Inclusion dynamique du Footer global
$footerPath = __DIR__ . '/themes/default/templates/layouts/footer.php';
if (!file_exists($footerPath)) {
    $footerPath = __DIR__ . '/app/views/layouts/footer.php';
}
if (file_exists($footerPath)) {
    require_once $footerPath;
}
?>