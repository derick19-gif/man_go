<?php
// =========================================================================
// Page des Forfaits et Abonnements (3 Niveaux) - MAN GO
// =========================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$pageTitle = "Forfaits & Abonnements - MAN GO";

require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-50 min-h-screen pb-24">
    <div class="bg-slate-900 pt-20 pb-32 text-center relative overflow-hidden">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-5"></div>
        <div class="relative z-10 px-4">
            <span class="bg-amber-500/20 text-amber-400 text-xs font-black px-4 py-1.5 rounded-full uppercase tracking-widest mb-4 inline-block">Évoluez à votre rythme</span>
            <h1 class="text-4xl sm:text-5xl font-black text-white mt-4 tracking-tight">Choisissez le plan fait pour <span class="text-amber-500">vous</span></h1>
            <p class="text-slate-400 mt-4 text-base sm:text-lg max-w-2xl mx-auto">De la simple découverte à la domination de votre marché local. Sans engagement.</p>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 relative z-20 -mt-20">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
            
            <!-- Plan Standard (Gratuit) -->
            <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-8 flex flex-col hover:-translate-y-1 transition-transform duration-300">
                <div class="mb-6">
                    <h2 class="text-xl font-black text-slate-900">Standard</h2>
                    <p class="text-slate-500 text-xs mt-2">Pour la découverte et les petits besoins.</p>
                </div>
                <div class="mb-6 flex items-baseline text-slate-900">
                    <span class="text-4xl font-black tracking-tight">0</span>
                    <span class="text-lg font-bold text-slate-500 ml-1">FCFA</span>
                    <span class="text-xs text-slate-400 ml-1">/ mois</span>
                </div>
                
                <ul class="space-y-3 mb-8 flex-1 text-sm">
                    <li class="flex items-center text-slate-700"><i class="fa-solid fa-check text-green-500 mr-2"></i> Recherche d'annonces</li>
                    <li class="flex items-center text-slate-700"><i class="fa-solid fa-check text-green-500 mr-2"></i> Messagerie basique</li>
                    <li class="flex items-center text-slate-700"><i class="fa-solid fa-check text-green-500 mr-2"></i> 1 Annonce active</li>
                    <li class="flex items-center text-slate-400 opacity-60"><i class="fa-solid fa-xmark mr-2"></i> Pas de Boutique</li>
                    <li class="flex items-center text-slate-400 opacity-60"><i class="fa-solid fa-xmark mr-2"></i> Pas de statistiques</li>
                </ul>

                <a href="<?= $baseUrl ?>/register.php" class="w-full block text-center bg-slate-100 hover:bg-slate-200 text-slate-900 font-bold py-3 rounded-xl transition text-sm">
                    Compte gratuit
                </a>
            </div>

            <!-- Plan Starter (Le juste milieu) -->
            <div class="bg-white rounded-3xl shadow-xl border-2 border-blue-500/20 p-8 flex flex-col hover:-translate-y-1 transition-transform duration-300 relative overflow-hidden">
                <div class="absolute top-0 right-0 bg-blue-50 text-blue-600 text-[10px] font-black px-3 py-1 rounded-bl-xl uppercase tracking-wider">
                    Idéal pour débuter
                </div>
                <div class="mb-6">
                    <h2 class="text-xl font-black text-slate-900 flex items-center">
                        <i class="fa-solid fa-store text-blue-500 mr-2"></i> Starter Pro
                    </h2>
                    <p class="text-slate-500 text-xs mt-2">Pour les petits vendeurs réguliers.</p>
                </div>
                <div class="mb-6 flex items-baseline text-slate-900">
                    <span class="text-4xl font-black tracking-tight">2 500</span>
                    <span class="text-lg font-bold text-blue-500 ml-1">FCFA</span>
                    <span class="text-xs text-slate-400 ml-1">/ mois</span>
                </div>
                
                <ul class="space-y-3 mb-8 flex-1 text-sm">
                    <li class="flex items-center text-slate-700"><i class="fa-solid fa-check-circle text-blue-500 mr-2"></i> Boutique Vendeur Standard</li>
                    <li class="flex items-center text-slate-900 font-bold"><i class="fa-solid fa-check-circle text-blue-500 mr-2"></i> Annonces illimitées</li>
                    <li class="flex items-center text-slate-700"><i class="fa-solid fa-check-circle text-blue-500 mr-2"></i> Statistiques basiques</li>
                    <li class="flex items-center text-slate-400 opacity-60"><i class="fa-solid fa-xmark mr-2"></i> Pas de badge vérifié</li>
                    <li class="flex items-center text-slate-400 opacity-60"><i class="fa-solid fa-xmark mr-2"></i> Pas de géolocalisation Pro</li>
                </ul>

                <a href="<?= $baseUrl ?>/checkout.php?plan=starter" class="w-full block text-center bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold py-3 rounded-xl transition text-sm border border-blue-200">
                    S'abonner à Starter
                </a>
            </div>

            <!-- Plan Premium VIP (L'offre reine) -->
            <div class="bg-slate-900 rounded-3xl shadow-2xl border border-amber-500/40 p-8 flex flex-col relative transform md:-translate-y-4 hover:-translate-y-5 transition-transform duration-300">
                <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-gradient-to-r from-amber-400 to-amber-600 text-slate-950 font-black text-xs uppercase tracking-widest py-1.5 px-6 rounded-full shadow-lg whitespace-nowrap">
                    Recommandé
                </div>

                <div class="mb-6">
                    <h2 class="text-xl font-black text-white flex items-center">
                        <i class="fa-solid fa-crown text-amber-500 mr-2"></i> Premium VIP
                    </h2>
                    <p class="text-slate-400 text-xs mt-2">L'arsenal complet pour dominer les ventes.</p>
                </div>
                <div class="mb-6 flex items-baseline text-white">
                    <span class="text-4xl font-black tracking-tight">5 000</span>
                    <span class="text-lg font-bold text-amber-500 ml-1">FCFA</span>
                    <span class="text-xs text-slate-400 ml-1">/ mois</span>
                </div>
                
                <ul class="space-y-3 mb-8 flex-1 text-sm">
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-star text-amber-500 mr-2"></i> Boutique VIP Certifiée</li>
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-star text-amber-500 mr-2"></i> <strong>Badge Confiance Or</strong></li>
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-star text-amber-500 mr-2"></i> Mise en avant prioritaire</li>
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-star text-amber-500 mr-2"></i> Statistiques & Audience avancées</li>
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-star text-amber-500 mr-2"></i> Géolocalisation Pro (À venir)</li>
                </ul>

                <a href="<?= $baseUrl ?>/checkout.php?plan=premium" class="w-full block text-center bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black py-3 rounded-xl shadow-lg shadow-amber-500/20 transition text-sm">
                    Devenir VIP
                </a>
            </div>

        </div>
        
        <div class="mt-16 text-center">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-6">Paiements 100% sécurisés via</p>
            <div class="flex justify-center items-center gap-6 opacity-70 grayscale hover:grayscale-0 transition duration-300">
                <div class="font-black text-xl text-slate-800">Mixx <span class="text-yellow-500">by Yas</span></div>
                <div class="font-black text-xl text-blue-600">Flooz</div>
                <div class="font-black text-xl text-slate-800">VISA <span class="font-light">/</span> Mastercard</div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>