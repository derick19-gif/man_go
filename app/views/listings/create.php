<?php
// app/views/listings/create.php
$pageTitle = 'Publier une annonce - MAN GO';
$baseUrl = defined('BASE_URL') ? BASE_URL : '/man_go';
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="bg-slate-50 min-h-screen pb-20">
    <!-- En-tête de page -->
    <div class="bg-slate-900 text-white pt-12 pb-24 px-4 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-amber-500 via-transparent to-transparent"></div>
        <div class="max-w-4xl mx-auto relative z-10 text-center">
            <span class="text-amber-500 font-bold uppercase tracking-widest text-xs mb-2 block">One Market, One Movement.</span>
            <h1 class="text-3xl md:text-5xl font-black mb-4">Créez votre annonce</h1>
            <p class="text-gray-400 font-medium">Touchez des milliers de clients grâce à la géolocalisation précise de MAN GO.</p>
        </div>
    </div>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-12 relative z-20">
        
        <?php if (!empty($_SESSION['form_errors'])): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-xl shadow-sm">
                <?php foreach ($_SESSION['form_errors'] as $err): ?>
                    <p class="text-red-700 font-bold text-sm"><i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= htmlspecialchars($err) ?></p>
                <?php endforeach; unset($_SESSION['form_errors']); ?>
            </div>
        <?php endif; ?>

        <form action="<?= $baseUrl ?>/publish" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl shadow-xl border border-gray-100 p-6 sm:p-10 space-y-10">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <!-- Section 1: Informations de base -->
            <div>
                <h3 class="text-lg font-black text-slate-900 mb-6 flex items-center border-b border-gray-100 pb-3">
                    <span class="bg-amber-100 text-amber-600 w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">1</span>
                    Détails de l'offre
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Titre de l'annonce <span class="text-red-500">*</span></label>
                        <input type="text" name="title" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:bg-white focus:border-amber-500 transition text-sm font-semibold">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Catégorie <span class="text-red-500">*</span></label>
                        <select name="category_id" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:bg-white focus:border-amber-500 transition text-sm font-semibold cursor-pointer">
                            <option value="">Sélectionnez un domaine</option>
                            <?php if(!empty($categories)): foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Numéro WhatsApp / Téléphone <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <select name="dial_code" class="w-1/3 px-3 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-amber-500 transition text-sm font-semibold">
                                <?php if(class_exists('Countries')) echo Countries::renderSelectOptions('+228'); else echo '<option value="+228">+228</option>'; ?>
                            </select>
                            <input type="text" name="phone" required class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:bg-white focus:border-amber-500 transition text-sm font-semibold" placeholder="Ex: 90 00 00 00">
                        </div>
                    </div>

                    <!-- Bloc Prix -->
                    <div class="md:col-span-2 bg-amber-50/50 p-5 rounded-xl border border-amber-200/80 space-y-4 mt-2">
                        <div class="flex items-center space-x-2 text-amber-800 font-bold text-sm">
                            <i class="fa-solid fa-tags text-amber-500"></i>
                            <span>Tarification et Promotion</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Prix de vente <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" min="0" name="price" required class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:border-amber-500 text-sm bg-white" placeholder="Ex: 15000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Prix barré <span class="text-gray-400 font-normal">(Optionnel)</span></label>
                                <input type="number" step="0.01" min="0" name="original_price" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:border-amber-500 text-sm bg-white" placeholder="Ex: 18000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Devise</label>
                                <select name="currency" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:border-amber-500 text-sm bg-white">
                                    <option value="FCFA">FCFA</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="USD">USD ($)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Téléversement d'Image -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Photo de l'annonce</label>
                        <input type="file" id="imageInput" name="image" accept="image/png, image/jpeg, image/webp" class="w-full border border-gray-300 rounded-xl p-2 text-sm bg-white focus:outline-none focus:border-amber-500">
                        <div id="imagePreviewContainer" class="mt-3 hidden">
                            <img id="imagePreview" src="#" alt="Aperçu" class="h-32 w-32 object-cover rounded-xl border border-gray-200 shadow-sm">
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Description <span class="text-red-500">*</span></label>
                        <textarea name="description" required rows="4" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:bg-white focus:border-amber-500 transition text-sm" placeholder="Décrivez votre offre en détail..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: GÉOLOCALISATION INTELLIGENTE -->
            <div>
                <h3 class="text-lg font-black text-slate-900 mb-6 flex items-center border-b border-gray-100 pb-3">
                    <span class="bg-amber-100 text-amber-600 w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">2</span>
                    Localisation (Mondiale)
                </h3>
                
                <div class="bg-slate-900 p-6 rounded-2xl relative mb-6 shadow-glow border border-slate-800">
                    <label class="block text-xs font-black text-amber-500 uppercase tracking-widest mb-3">
                        <i class="fa-solid fa-satellite-dish animate-pulse mr-2"></i> Rechercher l'adresse exacte
                    </label>
                    <div class="relative">
                        <input type="text" id="addressSearch" class="w-full bg-white border-0 rounded-xl px-5 py-4 focus:ring-4 focus:ring-amber-500/50 transition text-sm font-bold text-slate-900 shadow-inner" placeholder="Tapez une rue, un quartier, une ville ou un pays..." autocomplete="off">
                        <i class="fa-solid fa-location-crosshairs absolute right-5 top-4 text-slate-400 text-lg"></i>
                    </div>

                    <!-- Boîte de suggestions déroulante -->
                    <div id="suggestionsBox" class="hidden absolute left-6 right-6 mt-2 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden z-50 max-h-60 overflow-y-auto"></div>
                </div>

                <!-- Champs cachés GPS -->
                <input type="hidden" name="latitude" id="lat">
                <input type="hidden" name="longitude" id="lon">

                <!-- Champs d'adresse classiques -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-5 rounded-2xl border border-gray-200">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Pays</label>
                        <input type="text" name="country" id="country" class="w-full bg-transparent border-b border-gray-300 py-2 text-sm font-semibold text-gray-900 focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Ville</label>
                        <input type="text" name="city" id="city" class="w-full bg-transparent border-b border-gray-300 py-2 text-sm font-semibold text-gray-900 focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Quartier / Arrondissement</label>
                        <input type="text" name="neighborhood" id="neighborhood" class="w-full bg-transparent border-b border-gray-300 py-2 text-sm font-semibold text-gray-900 focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Rue</label>
                        <input type="text" name="street" id="street" class="w-full bg-transparent border-b border-gray-300 py-2 text-sm font-semibold text-gray-900 focus:outline-none focus:border-amber-500">
                    </div>
                </div>
            </div>

            <!-- Bouton de soumission -->
            <div class="pt-6 border-t border-gray-100 flex justify-end">
                <button type="submit" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black px-10 py-4 rounded-full text-sm transition-all duration-300 shadow-futuristic hover:shadow-glow flex items-center space-x-2 transform hover:-translate-y-1">
                    <span>Publier mon offre mondiale</span>
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        </form>
    </main>
</div>

<!-- SCRIPT MAGIQUE DE GÉOLOCALISATION ET IMAGES -->
<script>
    // --- 1. Gestion de l'aperçu de l'image ---
    document.getElementById('imageInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('imagePreview').src = event.target.result;
                document.getElementById('imagePreviewContainer').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    // --- 2. Gestion de l'API de Géolocalisation ---
    const searchInput = document.getElementById('addressSearch');
    const suggestionsBox = document.getElementById('suggestionsBox');
    let timeoutId;

    searchInput.addEventListener('input', function() {
        clearTimeout(timeoutId);
        const query = this.value;
        
        if (query.length < 3) {
            suggestionsBox.classList.add('hidden');
            return;
        }

        suggestionsBox.classList.remove('hidden');
        suggestionsBox.innerHTML = '<div class="p-4 text-center text-sm text-gray-500"><i class="fa-solid fa-spinner fa-spin text-amber-500 mr-2"></i>Recherche satellite...</div>';

        timeoutId = setTimeout(() => {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=5&q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                suggestionsBox.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(place => {
                        const div = document.createElement('div');
                        div.className = 'p-4 hover:bg-amber-50 cursor-pointer border-b border-gray-100 flex items-start space-x-3 transition group';
                        const icon = place.class === 'amenity' || place.class === 'shop' ? 'fa-store' : 'fa-location-dot';
                        
                        div.innerHTML = `
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-amber-500 group-hover:bg-amber-500 group-hover:text-white transition flex-shrink-0">
                                <i class="fa-solid ${icon} text-xs"></i>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-slate-800">${place.name || place.display_name.split(',')[0]}</p>
                                <p class="text-[11px] text-gray-500 truncate max-w-md">${place.display_name}</p>
                            </div>
                        `;
                        div.onclick = () => selectPlace(place);
                        suggestionsBox.appendChild(div);
                    });
                } else {
                    suggestionsBox.innerHTML = '<div class="p-4 text-center text-sm text-gray-500">Aucune adresse trouvée.</div>';
                }
            })
            .catch(err => {
                suggestionsBox.innerHTML = '<div class="p-4 text-center text-sm text-red-500">Erreur réseau.</div>';
            });
        }, 600);
    });

    function selectPlace(place) {
        searchInput.value = place.display_name;
        document.getElementById('lat').value = place.lat;
        document.getElementById('lon').value = place.lon;
        
        const addr = place.address;
        document.getElementById('city').value = addr.city || addr.town || addr.village || addr.county || '';
        document.getElementById('country').value = addr.country || '';
        document.getElementById('neighborhood').value = addr.suburb || addr.neighbourhood || addr.city_district || '';
        document.getElementById('street').value = addr.road || '';
        
        suggestionsBox.classList.add('hidden');
        searchInput.classList.add('ring-2', 'ring-green-400', 'bg-green-50');
        setTimeout(() => searchInput.classList.remove('ring-2', 'ring-green-400', 'bg-green-50'), 1500);
    }

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
            suggestionsBox.classList.add('hidden');
        }
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>