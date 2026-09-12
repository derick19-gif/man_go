<?php
// modules/stand/views/create.php

$pageTitle = 'Ouvrir ma Boutique ou mon Stand Pro - MAN GO';

$countriesPath = dirname(dirname(dirname(__DIR__))) . '/core/Countries.php';
if (file_exists($countriesPath) && !class_exists('Countries')) {
    require_once $countriesPath;
}

$professionsPath = dirname(dirname(dirname(__DIR__))) . '/core/Professions.php';
if (file_exists($professionsPath) && !class_exists('Professions')) {
    require_once $professionsPath;
}

$headerPath = dirname(dirname(dirname(__DIR__))) . '/themes/default/templates/layouts/header.php';
if (!file_exists($headerPath)) {
    $headerPath = dirname(dirname(dirname(__DIR__))) . '/app/views/layouts/header.php';
}
if (file_exists($headerPath)) {
    require_once $headerPath;
}
?>
<div class="bg-slate-950 min-h-screen pb-24 text-slate-100 font-sans">
    <div class="relative overflow-hidden pt-16 pb-20 px-4 text-center border-b border-slate-800">
        <div class="absolute inset-0 bg-gradient-to-r from-amber-500/10 via-transparent to-orange-500/15 pointer-events-none"></div>
        <div class="relative z-10 max-w-3xl mx-auto">
            <span class="bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-black px-4 py-1.5 rounded-full uppercase tracking-widest inline-block mb-3">Plateforme Internationale MAN GO</span>
            <h1 class="text-3xl md:text-5xl font-black mb-3 tracking-tight text-white">Créez votre Stand Professionnel</h1>
            <p class="text-slate-400 text-sm md:text-base">Assistant comptable, médecin, notaire, fermier ou commerçant : ouvrez votre vitrine internationale certifiée en quelques secondes.</p>
        </div>
    </div>

    <main class="max-w-4xl mx-auto px-4 -mt-10 relative z-20">
        <form action="store" method="POST" onsubmit="return confirmCreation(event)" class="bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl p-6 sm:p-12 space-y-8 backdrop-blur-xl">
            
            <div class="bg-slate-950/80 border border-amber-500/30 p-4 rounded-2xl flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-location-crosshairs text-amber-500 text-xl animate-pulse"></i>
                    <div>
                        <h4 class="text-xs font-black uppercase text-amber-400">Remplissage automatique GPS</h4>
                        <p class="text-xs text-slate-400">Cliquez pour détecter votre position et pré-remplir votre ville.</p>
                    </div>
                </div>
                <button type="button" onclick="detectLocation()" class="bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 border border-amber-500/40 text-xs font-bold px-4 py-2 rounded-xl transition">
                    <i class="fa-solid fa-crosshairs me-1"></i> Détecter ma position
                </button>
            </div>

            <div>
                <h3 class="text-amber-400 text-xs font-black uppercase tracking-wider mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-id-card"></i> 1. Votre Identité et Spécialité Professionnelle
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Nom du Stand / Nom & Prénom Pro *</label>
                        <input type="text" name="name" required placeholder="Ex: Cabinet Notarial / Rikos Services" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Métier / Domaine d'activité *</label>
                        <select name="category" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white focus:outline-none focus:border-amber-500 transition">
                            <option value="" class="bg-slate-900 text-slate-400">Sélectionnez votre métier précis</option>
                            <?php 
                            if (class_exists('Professions')) {
                                echo Professions::renderOptions();
                            } else {
                                echo '<option value="Artisan">Artisan</option>';
                                echo '<option value="Commerçant">Commerçant</option>';
                                echo '<option value="Autre profession">Autre profession</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-amber-400 text-xs font-black uppercase tracking-wider mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-earth-africa"></i> 2. Localisation & Pays d'Opération
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Pays d'Implantation *</label>
                        <select name="country_iso" id="country_iso" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white focus:outline-none focus:border-amber-500 transition">
                            <?php 
                            if (class_exists('Countries')) {
                                echo Countries::renderSelectOptions('+228'); 
                            } else {
                                echo '<option value="+228">🇹🇬 Togo (+228)</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Ville / Localité *</label>
                        <input type="text" name="city" id="city_input" required placeholder="Ex: Lomé, Cotonou, Paris..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Zone de Couverture *</label>
                        <select name="coverage_zone" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white focus:outline-none focus:border-amber-500 transition">
                            <option value="Local (Ma ville / Mon quartier)">Local (Ma ville / Mon quartier)</option>
                            <option value="National (Partout dans le pays)">National (Partout dans le pays)</option>
                            <option value="International (En ligne / Export)">International (En ligne / Export)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-amber-400 text-xs font-black uppercase tracking-wider mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-address-book"></i> 3. Contact Direct & Compétences
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Téléphone / WhatsApp Pro *</label>
                        <input type="text" name="phone" required placeholder="Ex: +228 90 00 00 00" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Site Web ou Réseau Social (Optionnel)</label>
                        <input type="text" name="website" placeholder="Ex: https://linkedin.com/in/votre-profil" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Description détaillée de vos services ou produits *</label>
                    <textarea name="description" rows="5" required placeholder="Décrivez votre expérience, vos diplômes, vos tarifs ou vos produits..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition"></textarea>
                </div>
            </div>

            <div class="bg-slate-950/60 border border-amber-500/20 p-4 rounded-2xl flex items-start gap-3">
                <i class="fa-solid fa-shield-halved text-amber-500 text-lg mt-0.5"></i>
                <p class="text-xs text-slate-400 leading-relaxed">
                    <strong class="text-slate-200">Sécurité & Notation Certifiée MAN GO :</strong> L'ancienneté s'affiche automatiquement. Seuls les clients ayant effectué une vraie transaction validée peuvent vous attribuer des notes. Zéro faux avis.
                </p>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black py-4 rounded-xl shadow-lg shadow-amber-500/20 transition-all text-base flex items-center justify-center space-x-2">
                <i class="fa-solid fa-rocket text-lg"></i>
                <span>Créer et Activer mon Stand Officiel</span>
            </button>
        </form>
    </main>
</div>

<script>
function detectLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(async (position) => {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`);
                const data = await res.json();
                if (data && data.address) {
                    const city = data.address.city || data.address.town || data.address.village || data.address.state || '';
                    if (city) {
                        document.getElementById('city_input').value = city;
                    }
                    alert('Position détectée avec succès !');
                }
            } catch (e) {
                alert('Impossible de récupérer la ville automatiquement.');
            }
        }, () => {
            alert('Géolocalisation refusée ou indisponible.');
        });
    } else {
        alert("La géolocalisation n'est pas supportée par votre navigateur.");
    }
}

function confirmCreation(event) {
    const name = document.querySelector('input[name="name"]').value;
    const confirmed = confirm(`Voulez-vous confirmer la création de votre stand "${name}" sur MAN GO ?`);
    if (!confirmed) {
        event.preventDefault();
        return false;
    }
    return true;
}
</script>

<?php
$footerPath = dirname(dirname(dirname(__DIR__))) . '/themes/default/templates/layouts/footer.php';
if (!file_exists($footerPath)) {
    $footerPath = dirname(dirname(dirname(__DIR__))) . '/app/views/layouts/footer.php';
}
if (file_exists($footerPath)) {
    require_once $footerPath;
}
?>