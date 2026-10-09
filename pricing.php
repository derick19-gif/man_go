<?php
// =========================================================================
// Page des Forfaits et Abonnements (3 Niveaux) - MAN GO
// =========================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php'; // AJOUT CRUCIAL ICI

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$pageTitle = "Forfaits & Abonnements - MAN GO";

require_once __DIR__ . '/themes/default/templates/layouts/header.php';

// Connexion à la base de données
$db = \App\Core\Database::connect();

// ---------------------------------------------------------------------
// CONNEXION DYNAMIQUE AUX CONFIGURATIONS (Adieu les valeurs en dur !)
// ---------------------------------------------------------------------
$stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Crée un tableau [ 'clé' => 'valeur' ]

// On récupère les valeurs depuis la base, avec une valeur par défaut au cas où
$prix_starter_xof = isset($settings['price_starter']) ? (int)$settings['price_starter'] : 2500;
$prix_premium_xof = isset($settings['price_premium']) ? (int)$settings['price_premium'] : 5000;

// Taux de conversion pour affichage dynamique
$taux_usd = 600;
$taux_eur = 655;
?>

<div class="bg-slate-50 min-h-screen pb-24 font-sans">
    <div class="bg-slate-900 pt-20 pb-32 text-center relative overflow-hidden">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-5"></div>
        <div class="relative z-10 px-4 max-w-4xl mx-auto">
            <span class="bg-amber-500/20 text-amber-400 text-xs font-black px-4 py-1.5 rounded-full uppercase tracking-widest mb-4 inline-block">Évoluez à votre rythme</span>
            <h1 class="text-4xl sm:text-5xl font-black text-white mt-4 tracking-tight">Choisissez le plan fait pour <span class="text-amber-500">vous</span></h1>
            <p class="text-slate-400 mt-4 text-base sm:text-lg mx-auto">De la simple découverte à la domination de votre marché local. Sans engagement.</p>
            
            <!-- Sélecteur de devise global pour la page -->
            <div class="mt-8 flex justify-center items-center gap-3">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Afficher les prix en :</span>
                <select id="global-currency-selector" onchange="updatePrices()" class="bg-slate-800 border border-slate-700 text-amber-400 text-sm font-black rounded-xl cursor-pointer focus:ring-amber-500 focus:border-amber-500 py-2 px-4 shadow-sm">
                    <option value="XOF">Francs CFA</option>
                    <option value="USD">Dollars US</option>
                    <option value="EUR">Euros</option>
                </select>
            </div>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 relative z-20 -mt-16">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
            
            <!-- Plan Standard (Gratuit) -->
            <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-8 flex flex-col hover:-translate-y-1 transition-transform duration-300">
                <div class="mb-6">
                    <h2 class="text-xl font-black text-slate-900">Standard</h2>
                    <p class="text-slate-500 text-xs mt-2">Pour la découverte et les petits besoins.</p>
                </div>
                <div class="mb-6 flex items-baseline text-slate-900">
                    <span class="text-4xl font-black tracking-tight">0</span>
                    <span class="currency-symbol text-lg font-bold text-slate-500 ml-1">FCFA</span>
                    <span class="text-xs text-slate-400 ml-1">/ mois</span>
                </div>
                
                <ul class="space-y-3 mb-8 flex-1 text-sm">
                    <li class="flex items-center text-slate-700"><i class="fa-solid fa-check text-green-500 mr-2"></i> Recherche d'annonces</li>
                    <li class="flex items-center text-slate-700"><i class="fa-solid fa-check text-green-500 mr-2"></i> Messagerie basique</li>
                    <li class="flex items-center text-slate-700"><i class="fa-solid fa-check text-green-500 mr-2"></i> 1 Annonce active</li>
                    <li class="flex items-center text-slate-400 opacity-60"><i class="fa-solid fa-xmark mr-2"></i> Pas de Boutique Pro</li>
                    <li class="flex items-center text-slate-400 opacity-60"><i class="fa-solid fa-xmark mr-2"></i> Pas de statistiques</li>
                </ul>

                <a href="<?= $baseUrl ?>/register.php" class="w-full block text-center bg-slate-100 hover:bg-slate-200 text-slate-900 font-bold py-3 rounded-xl transition text-sm">
                    Créer un compte gratuit
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
                    <span id="price-starter" class="text-4xl font-black tracking-tight" data-xof="<?= $prix_starter_xof ?>"><?= number_format($prix_starter_xof, 0, ',', ' ') ?></span>
                    <span class="currency-symbol text-lg font-bold text-blue-500 ml-1">FCFA</span>
                    <span class="text-xs text-slate-400 ml-1">/ mois</span>
                </div>
                
                <ul class="space-y-3 mb-8 flex-1 text-sm">
                    <li class="flex items-center text-slate-700"><i class="fa-solid fa-check-circle text-blue-500 mr-2"></i> Boutique Vendeur Standard</li>
                    <li class="flex items-center text-slate-900 font-bold"><i class="fa-solid fa-check-circle text-blue-500 mr-2"></i> Annonces illimitées</li>
                    <li class="flex items-center text-slate-700"><i class="fa-solid fa-check-circle text-blue-500 mr-2"></i> Statistiques basiques</li>
                    <li class="flex items-center text-slate-400 opacity-60"><i class="fa-solid fa-xmark mr-2"></i> Pas de badge vérifié</li>
                    <li class="flex items-center text-slate-400 opacity-60"><i class="fa-solid fa-xmark mr-2"></i> Pas d'Assistant IA</li>
                </ul>

                <!-- CORRECTION DU LIEN ICI -->
                <a href="<?= $baseUrl ?>/checkout_subscription.php?plan=starter" class="w-full block text-center bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold py-3 rounded-xl transition text-sm border border-blue-200">
                    S'abonner à Starter
                </a>
            </div>

            <!-- Plan Premium VIP (L'offre reine) -->
            <div class="bg-slate-900 rounded-3xl shadow-2xl border border-amber-500/40 p-8 flex flex-col relative transform md:-translate-y-4 hover:-translate-y-5 transition-transform duration-300">
                <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-gradient-to-r from-amber-400 to-amber-600 text-slate-950 font-black text-[10px] uppercase tracking-widest py-1.5 px-6 rounded-full shadow-lg whitespace-nowrap">
                    Recommandé
                </div>

                <div class="mb-6 mt-2">
                    <h2 class="text-xl font-black text-white flex items-center">
                        <i class="fa-solid fa-crown text-amber-500 mr-2"></i> Premium VIP
                    </h2>
                    <p class="text-slate-400 text-xs mt-2">L'arsenal complet pour dominer les ventes.</p>
                </div>
                <div class="mb-6 flex items-baseline text-white">
                    <span id="price-premium" class="text-4xl font-black tracking-tight text-amber-400" data-xof="<?= $prix_premium_xof ?>"><?= number_format($prix_premium_xof, 0, ',', ' ') ?></span>
                    <span class="currency-symbol text-lg font-bold text-amber-500 ml-1">FCFA</span>
                    <span class="text-xs text-slate-400 ml-1">/ mois</span>
                </div>
                
                <ul class="space-y-3 mb-8 flex-1 text-sm">
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-star text-amber-500 mr-2"></i> Boutique VIP Certifiée</li>
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-star text-amber-500 mr-2"></i> <strong>Badge Confiance Or</strong></li>
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-star text-amber-500 mr-2"></i> Mise en avant prioritaire</li>
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-star text-amber-500 mr-2"></i> Statistiques & Audience avancées</li>
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-robot text-amber-500 mr-2"></i> Assistant IA 24/7</li>
                    <li class="flex items-center text-slate-200"><i class="fa-solid fa-rocket text-amber-500 mr-2"></i> Optimisation SEO Google</li>
                </ul>

                <!-- CORRECTION DU LIEN ICI -->
                <a href="<?= $baseUrl ?>/checkout_subscription.php?plan=premium" class="w-full block text-center bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black py-3 rounded-xl shadow-lg shadow-amber-500/20 transition text-sm uppercase tracking-wide">
                    Devenir VIP
                </a>
            </div>

        </div>
        
        <div class="mt-16 text-center">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-6">Paiements Internationaux 100% Sécurisés via</p>
            <div class="flex flex-wrap justify-center items-center gap-6 md:gap-10 opacity-60 grayscale hover:grayscale-0 transition duration-300">
                <div class="font-black text-xl text-slate-800">T-Money <span class="text-yellow-500">Togo</span></div>
                <div class="font-black text-xl text-blue-600">Flooz</div>
                <div class="font-black text-xl text-slate-800"><i class="fa-brands fa-cc-visa text-3xl"></i></div>
                <div class="font-black text-xl text-slate-800"><i class="fa-brands fa-cc-mastercard text-3xl"></i></div>
                <div class="font-black text-xl text-slate-800 flex items-center gap-2"><i class="fa-brands fa-bitcoin text-amber-500 text-3xl"></i> Crypto</div>
            </div>
        </div>
    </main>
</div>

<!-- SCRIPT DE CONVERSION EN TEMPS RÉEL POUR LA PAGE DES PRIX -->
<script>
    const rateUSD = <?= $taux_usd ?>;
    const rateEUR = <?= $taux_eur ?>;

    function updatePrices() {
        const currency = document.getElementById('global-currency-selector').value;
        const priceElements = [
            document.getElementById('price-starter'),
            document.getElementById('price-premium')
        ];
        
        const symbols = document.querySelectorAll('.currency-symbol');

        priceElements.forEach(el => {
            if(!el) return;
            const amountFCFA = parseFloat(el.getAttribute('data-xof'));
            let newAmount = amountFCFA;
            let decimals = 0;

            if (currency === 'USD') {
                newAmount = amountFCFA / rateUSD;
                decimals = 2;
            } else if (currency === 'EUR') {
                newAmount = amountFCFA / rateEUR;
                decimals = 2;
            }

            el.innerText = new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(newAmount);
        });

        // Mise à jour de toutes les devises affichées
        symbols.forEach(sym => {
            if (currency === 'XOF') sym.innerText = 'FCFA';
            else if (currency === 'USD') sym.innerText = '$';
            else if (currency === 'EUR') sym.innerText = '€';
        });
    }
</script>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>