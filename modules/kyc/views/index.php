<?php 
// =========================================================================
// Vue : Formulaire KYC (Avec gestion des dates d'expiration)
// =========================================================================
$pageTitle = "Vérification d'Identité & KYC - MAN GO";

$headerPath = __DIR__ . '/../../../themes/default/templates/layouts/header.php';
if (!file_exists($headerPath)) $headerPath = __DIR__ . '/../../../app/views/layouts/header.php';
if (file_exists($headerPath)) require_once $headerPath;

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
?>

<!-- Importation de Alpine.js pour la logique interactive du formulaire -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div class="bg-gray-50 min-h-screen pb-20">
    <div class="max-w-4xl mx-auto px-4 py-12">
        
        <div class="text-center mb-10">
            <span class="bg-amber-100 text-amber-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Sécurité</span>
            <h1 class="text-3xl sm:text-4xl font-black text-slate-900 mt-3">Certification KYC</h1>
            <p class="text-gray-600 text-sm mt-2">Vérification d'identité requise pour sécuriser les transactions sur MAN GO.</p>
        </div>
        
        <!-- Gestion des messages d'erreur (Expiration, etc.) -->
        <?php if (!empty($_SESSION['kyc_error'])): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-xl shadow-sm">
                <p class="text-red-700 font-bold text-sm"><i class="fa-solid fa-triangle-exclamation mr-2"></i><?= htmlspecialchars($_SESSION['kyc_error']) ?></p>
            </div>
            <?php unset($_SESSION['kyc_error']); ?>
        <?php endif; ?>

        <?php if (!empty($kycData['status']) && $kycData['status'] === 'pending'): ?>
            <div class="bg-amber-50 border-l-4 border-amber-500 p-6 mb-6 rounded-r-xl shadow-sm">
                <h3 class="text-amber-800 font-bold text-lg mb-1"><i class="fa-solid fa-hourglass-half mr-2"></i>Dossier en cours d'examen</h3>
                <p class="text-amber-700 text-sm">Votre dossier KYC a bien été reçu. Notre équipe de sécurité l'examine actuellement. Vous pourrez publier des annonces dès qu'il sera validé.</p>
            </div>
        <?php elseif (!empty($kycData['status']) && $kycData['status'] === 'approved'): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-6 mb-6 rounded-r-xl shadow-sm">
                <h3 class="text-emerald-800 font-bold text-lg mb-1"><i class="fa-solid fa-shield-check mr-2"></i>Compte Certifié</h3>
                <p class="text-emerald-700 text-sm">Votre identité est validée. Vous pouvez désormais vendre et publier librement sur la plateforme.</p>
                <div class="mt-4">
                    <a href="<?= $baseUrl ?>/publish.php" class="inline-block bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-6 rounded-full text-sm transition">Aller publier</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Formulaire KYC (Caché si déjà en attente ou approuvé, sauf s'il faut renouveler) -->
        <?php if (empty($kycData) || $kycData['status'] === 'rejected' || (!empty($_SESSION['kyc_error']))): ?>
            
            <form action="<?= $baseUrl ?>/verification.php" method="POST" enctype="multipart/form-data" class="bg-white shadow-md rounded-2xl border border-gray-200 p-8 space-y-8" x-data="{ accountType: 'individual' }">
                
                <!-- Sélection du type de compte -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Type de Compte *</label>
                    <select name="account_type" x-model="accountType" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none transition font-medium">
                        <option value="individual">Vendeur Particulier / Auto-entrepreneur</option>
                        <option value="company">Entreprise Immatriculée (SARL, SA, etc.)</option>
                    </select>
                </div>

                <!-- Section Commune : Pièce d'identité et Dates d'expiration (CRITIQUE) -->
                <div class="border-t border-gray-100 pt-6 space-y-5 bg-slate-50 p-6 rounded-xl">
                    <h3 class="text-lg font-black text-slate-900"><i class="fa-solid fa-id-card text-amber-500 mr-2"></i>Pièce d'Identité Officielle</h3>
                    <p class="text-xs text-gray-500 mb-4">La pièce (CNI ou Passeport) doit être en cours de validité. Le format PDF ou image (haute qualité) est exigé.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Date de délivrance *</label>
                            <input type="date" name="id_issue_date" required class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Date d'expiration *</label>
                            <input type="date" name="id_expiration_date" required class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none text-sm">
                            <p class="text-[10px] text-amber-600 font-bold mt-1">Sert à garantir la conformité continue de votre compte.</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Fichier de la pièce (Recto/Verso) *</label>
                        <input type="file" name="id_document_path" required accept=".pdf,.jpg,.jpeg,.png" class="w-full border border-gray-300 rounded-xl p-2 bg-white text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-slate-800 transition cursor-pointer">
                    </div>
                </div>

                <!-- Section Spécifique Entreprise -->
                <div x-show="accountType === 'company'" style="display: none;" class="space-y-6 border-t border-gray-100 pt-6">
                    <h3 class="text-lg font-black text-slate-900"><i class="fa-solid fa-building text-amber-500 mr-2"></i>Informations Légales de l'Entreprise</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nom de l'Entreprise *</label>
                            <input type="text" name="company_name" :required="accountType === 'company'" placeholder="Raison sociale" class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">N° RCCM ou Équivalent *</label>
                            <input type="text" name="registration_number" :required="accountType === 'company'" placeholder="Numéro d'immatriculation" class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none text-sm">
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 p-5 rounded-xl">
                        <h4 class="text-sm font-bold text-amber-900 mb-4"><i class="fa-solid fa-user-shield mr-2"></i>Traçabilité des Responsables (Obligatoire)</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nom Gérant Principal *</label>
                                <input type="text" name="primary_manager_name" :required="accountType === 'company'" class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">ID Gérant Principal *</label>
                                <input type="text" name="primary_manager_id_card" :required="accountType === 'company'" placeholder="Numéro de pièce" class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nom Second Responsable *</label>
                                <input type="text" name="secondary_manager_name" :required="accountType === 'company'" placeholder="Co-gérant, directeur..." class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Pièce du Second Responsable *</label>
                                <input type="file" name="secondary_manager_id_card" :required="accountType === 'company'" accept=".pdf,.jpg,.jpeg,.png" class="w-full border border-gray-300 bg-white rounded-xl p-2 text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5 text-sm uppercase tracking-wider">
                        Soumettre mon dossier KYC de manière sécurisée
                    </button>
                    <p class="text-center text-[10px] text-gray-400 font-semibold mt-3"><i class="fa-solid fa-lock mr-1"></i> Vos données sont chiffrées de bout en bout et traitées conformément à notre politique de confidentialité.</p>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php 
$footerPath = __DIR__ . '/../../../themes/default/templates/layouts/footer.php';
if (!file_exists($footerPath)) $footerPath = __DIR__ . '/../../../app/views/layouts/footer.php';
if (file_exists($footerPath)) require_once $footerPath;
?>