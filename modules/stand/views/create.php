<?php
// modules/stand/views/create.php

$isEditMode = !empty($existingStand);
$pageTitle = $isEditMode ? 'Gérer ma Boutique / Stand - MAN GO' : 'Ouvrir ma Boutique ou mon Stand Pro - MAN GO';

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

$currentLogo = $existingStand['logo'] ?? '';
$hasValidLogo = !empty($currentLogo) && $currentLogo !== 'default-shop.png' && $currentLogo !== 'assets/images/placeholder.jpg';
$logoDisplayUrl = $hasValidLogo 
    ? (defined('APP_URL') ? APP_URL : '/man_go') . '/uploads/stands/' . htmlspecialchars($currentLogo) 
    : 'https://ui-avatars.com/api/?name='.urlencode($existingStand['name'] ?? 'Stand').'&background=0B132B&color=F59E0B';
    
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
?>

<div class="bg-slate-950 min-h-screen pb-24 text-slate-100 font-sans relative">
    <div class="relative overflow-hidden pt-16 pb-20 px-4 text-center border-b border-slate-800">
        <div class="absolute inset-0 bg-gradient-to-r from-amber-500/10 via-transparent to-orange-500/15 pointer-events-none"></div>
        <div class="relative z-10 max-w-3xl mx-auto">
            <span class="bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-black px-4 py-1.5 rounded-full uppercase tracking-widest inline-block mb-3">Plateforme Internationale MAN GO</span>
            
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
                <h1 class="text-3xl md:text-5xl font-black mb-3 tracking-tight text-white">Création Réussie !</h1>
                <p class="text-emerald-400 font-semibold text-sm md:text-base">Votre stand est maintenant en ligne et prêt à accueillir des clients.</p>
            <?php elseif ($isEditMode): ?>
                <h1 class="text-3xl md:text-5xl font-black mb-3 tracking-tight text-white">Gérer votre Stand Officiel</h1>
                <p class="text-slate-400 text-sm md:text-base">Mettez à jour vos informations et votre logo. Votre identité professionnelle est verrouillée par sécurité (KYC).</p>
            <?php else: ?>
                <h1 class="text-3xl md:text-5xl font-black mb-3 tracking-tight text-white">Créez votre Stand Professionnel</h1>
                <p class="text-slate-400 text-sm md:text-base">Assistant comptable, médecin, notaire, fermier ou commerçant : ouvrez votre vitrine internationale en quelques secondes.</p>
            <?php endif; ?>
        </div>
    </div>

    <main class="max-w-4xl mx-auto px-4 -mt-10 relative z-20">
        
        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-500/10 border border-red-500/50 p-4 rounded-3xl mb-6 text-center shadow-lg backdrop-blur-sm">
                <i class="fa-solid fa-triangle-exclamation text-red-400 text-3xl mb-2"></i>
                <h3 class="text-red-400 font-bold text-lg">Erreur</h3>
                <?php if ($_GET['error'] === 'logo_required'): ?>
                    <p class="text-red-300/80 text-sm">Le logo ou la photo professionnelle est obligatoire pour créer un stand.</p>
                <?php else: ?>
                    <p class="text-red-300/80 text-sm">Une erreur est survenue lors de l'enregistrement. Veuillez réessayer.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="bg-blue-500/10 border border-blue-500/50 p-4 rounded-3xl mb-6 text-center shadow-lg backdrop-blur-sm">
                <i class="fa-solid fa-check-double text-blue-400 text-2xl mb-2"></i>
                <p class="text-blue-300 font-bold">Mise à jour réussie avec succès.</p>
            </div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- ÉCRAN DE SUCCÈS APRÈS CRÉATION             -->
        <!-- ========================================== -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
            
            <div class="bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl p-8 sm:p-16 text-center backdrop-blur-xl relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-64 h-64 bg-emerald-500/20 blur-3xl rounded-full pointer-events-none"></div>
                <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-amber-500/10 blur-3xl rounded-full pointer-events-none"></div>
                
                <div class="relative z-10">
                    <div class="w-24 h-24 bg-gradient-to-tr from-emerald-400 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-emerald-500/30 shadow-[inset_0_-4px_10px_rgba(0,0,0,0.2)]">
                        <i class="fa-solid fa-check text-4xl text-white"></i>
                    </div>
                    
                    <h2 class="text-2xl font-black text-white mb-2">Félicitations, vous êtes en ligne !</h2>
                    <p class="text-slate-400 mb-8 max-w-lg mx-auto">Votre stand professionnel MAN GO a été créé et vérifié avec succès. Vous rejoignez aujourd'hui un écosystème mondial de professionnels certifiés.</p>
                    
                    <div class="bg-slate-950 border border-slate-800 rounded-2xl p-6 mb-8 text-left max-w-lg mx-auto">
                        <h4 class="text-amber-400 text-xs font-black uppercase tracking-wider mb-4 border-b border-slate-800 pb-2">Résumé de votre Stand</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-slate-500 text-sm">Statut :</span>
                                <span class="text-emerald-400 font-bold text-sm flex items-center gap-1"><i class="fa-solid fa-circle text-[8px]"></i> Actif</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 text-sm">Identité KYC :</span>
                                <span class="text-slate-200 font-bold text-sm">Vérifiée par MAN GO</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 text-sm">Visibilité :</span>
                                <span class="text-slate-200 font-bold text-sm">Mondiale</span>
                            </div>
                        </div>
                    </div>

                    <a href="<?= $baseUrl ?>/vendor_dir/dashboard.php" class="inline-flex items-center justify-center gap-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black px-8 py-4 rounded-xl shadow-lg shadow-amber-500/20 transition-all text-lg hover:scale-105 active:scale-95">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>Accéder à mon Tableau de bord</span>
                    </a>
                </div>
            </div>

        <?php else: ?>
        <!-- ========================================== -->
        <!-- LE FORMULAIRE DE CRÉATION / GESTION        -->
        <!-- ========================================== -->
            <form id="standForm" action="store" method="POST" enctype="multipart/form-data" class="bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl p-6 sm:p-12 space-y-8 backdrop-blur-xl">
                
                <div class="bg-slate-950/80 border border-amber-500/30 p-4 rounded-2xl flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-location-crosshairs text-amber-500 text-xl animate-pulse"></i>
                        <div>
                            <h4 class="text-xs font-black uppercase text-amber-400">Remplissage automatique GPS</h4>
                            <p class="text-xs text-slate-400 hidden sm:block">Cliquez pour détecter votre position et pré-remplir votre ville.</p>
                        </div>
                    </div>
                    <button type="button" onclick="detectLocation()" class="bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 border border-amber-500/40 text-xs font-bold px-4 py-2 rounded-xl transition">
                        <i class="fa-solid fa-crosshairs sm:me-1"></i> <span class="hidden sm:inline">Détecter ma position</span>
                    </button>
                </div>

                <div>
                    <h3 class="text-amber-400 text-xs font-black uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-image"></i> Logo ou Photo Professionnelle <span class="text-red-500 ml-1">*</span>
                    </h3>
                    <div class="bg-slate-950 border border-slate-700 rounded-2xl p-4 flex items-center gap-6">
                        <img id="logoPreview" src="<?= $logoDisplayUrl ?>" class="w-20 h-20 rounded-xl object-cover border-2 border-slate-800 shadow-lg">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-slate-400 mb-2">Photo claire de vous ou logo officiel (JPG, PNG)</label>
                            <input type="file" name="logo" id="logoInput" accept="image/*" <?= !$hasValidLogo ? 'required' : '' ?> onchange="previewImage(event)" class="w-full text-sm font-medium text-slate-400 file:cursor-pointer file:mr-4 file:py-2.5 file:px-5 file:rounded-full file:border-0 file:text-xs file:font-black file:bg-amber-500 file:text-slate-900 hover:file:bg-amber-400 transition outline-none">
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-amber-400 text-xs font-black uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-id-card"></i> 1. Votre Identité et Spécialité
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Nom du Stand / Nom Pro *</label>
                            <input type="text" name="name" id="f_name" required placeholder="Ex: Cabinet Notarial / Rikos Services" 
                                value="<?= htmlspecialchars($existingStand['name'] ?? '') ?>" 
                                <?= $isEditMode ? 'readonly class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 cursor-not-allowed"' : 'class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition"' ?>>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Métier / Domaine *</label>
                            <select name="category" id="f_category" required 
                                <?= $isEditMode ? 'disabled class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 cursor-not-allowed appearance-none"' : 'class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white focus:outline-none focus:border-amber-500 transition"' ?>>
                                <?php if ($isEditMode): ?>
                                    <option value="<?= htmlspecialchars($existingStand['category']) ?>" selected><?= htmlspecialchars($existingStand['category']) ?></option>
                                <?php else: ?>
                                    <option value="" class="bg-slate-900 text-slate-400">Sélectionnez votre métier</option>
                                    <?php 
                                    if (class_exists('Professions')) echo Professions::renderOptions();
                                    else echo '<option value="Artisan">Artisan</option><option value="Commerçant">Commerçant</option>';
                                    ?>
                                <?php endif; ?>
                            </select>
                            <?php if ($isEditMode) echo '<input type="hidden" name="category" value="'.htmlspecialchars($existingStand['category']).'">'; ?>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-amber-400 text-xs font-black uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-earth-africa"></i> 2. Localisation
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Pays *</label>
                            <select name="country_iso" id="f_country" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white focus:outline-none focus:border-amber-500 transition">
                                <?php if (class_exists('Countries')) echo Countries::renderSelectOptions('+228'); else echo '<option value="+228">🇹🇬 Togo (+228)</option>'; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Ville *</label>
                            <input type="text" name="city" id="f_city" required placeholder="Ex: Lomé..." 
                                value="<?= htmlspecialchars($existingStand['city'] ?? '') ?>"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Zone *</label>
                            <select name="coverage_zone" id="f_zone" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white focus:outline-none focus:border-amber-500 transition">
                                <?php $zone = $existingStand['address'] ?? ''; ?>
                                <option value="Local" <?= $zone == 'Local' ? 'selected' : '' ?>>Local</option>
                                <option value="National" <?= $zone == 'National' ? 'selected' : '' ?>>National</option>
                                <option value="International" <?= $zone == 'International' ? 'selected' : '' ?>>International</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-amber-400 text-xs font-black uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-address-book"></i> 3. Contact & Détails
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Téléphone Pro *</label>
                            <input type="tel" name="phone" id="f_phone" inputmode="tel" required placeholder="Ex: +228 90 00 00 00" maxlength="20"
                                oninput="this.value = this.value.replace(/[^0-9+\-\s]/g, '')"
                                value="<?= htmlspecialchars($existingStand['phone'] ?? '') ?>"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Site Web (Optionnel)</label>
                            <input type="text" name="website" placeholder="https://..." 
                                value="<?= htmlspecialchars($existingStand['website'] ?? '') ?>"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Description *</label>
                        <textarea name="description" rows="4" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3.5 text-sm font-medium text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition"><?= htmlspecialchars($existingStand['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <button type="button" onclick="handleFormSubmit()" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black py-4 rounded-xl shadow-lg shadow-amber-500/20 transition-all text-base flex items-center justify-center space-x-2">
                    <?php if ($isEditMode): ?>
                        <i class="fa-solid fa-save text-lg"></i>
                        <span>Mettre à jour mon Stand</span>
                    <?php else: ?>
                        <i class="fa-solid fa-rocket text-lg"></i>
                        <span>Vérifier et Créer mon Stand</span>
                    <?php endif; ?>
                </button>
            </form>
        <?php endif; ?>
    </main>
</div>

<!-- ========================================== -->
<!-- MODAL DE CONFIRMATION (REVIEW & CONFIRM)   -->
<!-- ========================================== -->
<div id="summaryModal" class="fixed inset-0 z-[99999] hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl shadow-2xl w-full max-w-lg transform scale-95 transition-transform duration-300 overflow-hidden" id="summaryModalContent">
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 p-6 text-center">
            <h3 class="text-slate-950 font-black text-xl">Vérification des informations</h3>
            <p class="text-slate-900 font-medium text-sm mt-1">Veuillez confirmer que ces données sont exactes.</p>
        </div>
        
        <div class="p-6 space-y-4">
            <div class="flex justify-between border-b border-slate-800 pb-3">
                <span class="text-slate-400 text-sm">Nom du Stand</span>
                <span class="text-white font-bold text-sm text-right" id="sum_name">...</span>
            </div>
            <div class="flex justify-between border-b border-slate-800 pb-3">
                <span class="text-slate-400 text-sm">Métier</span>
                <span class="text-white font-bold text-sm text-right" id="sum_category">...</span>
            </div>
            <div class="flex justify-between border-b border-slate-800 pb-3">
                <span class="text-slate-400 text-sm">Ville & Zone</span>
                <span class="text-white font-bold text-sm text-right"><span id="sum_city"></span> (<span id="sum_zone"></span>)</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-400 text-sm">Téléphone</span>
                <span class="text-white font-bold text-sm text-right" id="sum_phone">...</span>
            </div>
            
            <div class="bg-amber-500/10 border border-amber-500/30 p-3 rounded-xl mt-4 flex gap-3 items-start">
                <i class="fa-solid fa-circle-info text-amber-500 mt-0.5"></i>
                <p class="text-xs text-amber-200/80 leading-relaxed">Une fois validée, l'identité de votre stand sera liée à votre profil vendeur et soumise aux règles de la plateforme.</p>
            </div>
        </div>

        <div class="p-6 bg-slate-950/50 flex gap-4">
            <button type="button" onclick="closeModal()" class="w-1/3 py-3 rounded-xl font-bold text-slate-300 hover:text-white hover:bg-slate-800 transition">Annuler</button>
            <button type="button" onclick="submitFinalForm()" class="w-2/3 py-3 rounded-xl font-black bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition flex justify-center items-center gap-2">
                <span>Je confirme</span>
                <i class="fa-solid fa-check-circle"></i>
            </button>
        </div>
    </div>
</div>

<script>
const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;

function handleFormSubmit() {
    const form = document.getElementById('standForm');
    
    // Vérification HTML5 basique
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    if (isEditMode) {
        // En mode édition, on soumet direct
        form.submit();
    } else {
        // En mode création, on affiche le Modal de résumé
        document.getElementById('sum_name').innerText = document.getElementById('f_name').value;
        const catSelect = document.getElementById('f_category');
        document.getElementById('sum_category').innerText = catSelect.options[catSelect.selectedIndex].text;
        document.getElementById('sum_city').innerText = document.getElementById('f_city').value;
        const zoneSelect = document.getElementById('f_zone');
        document.getElementById('sum_zone').innerText = zoneSelect.options[zoneSelect.selectedIndex].text;
        document.getElementById('sum_phone').innerText = document.getElementById('f_phone').value;
        
        const modal = document.getElementById('summaryModal');
        const modalContent = document.getElementById('summaryModalContent');
        
        modal.classList.remove('hidden');
        // Petit délai pour l'animation CSS
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modalContent.classList.remove('scale-95');
        }, 10);
    }
}

function closeModal() {
    const modal = document.getElementById('summaryModal');
    const modalContent = document.getElementById('summaryModalContent');
    
    modal.classList.add('opacity-0');
    modalContent.classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function submitFinalForm() {
    // Désactiver le bouton pour éviter les doubles clics
    event.currentTarget.disabled = true;
    event.currentTarget.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Création...';
    document.getElementById('standForm').submit();
}

function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function() {
        const output = document.getElementById('logoPreview');
        output.src = reader.result;
    };
    if (event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    }
}

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
                    if (city) document.getElementById('f_city').value = city;
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