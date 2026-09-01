<?php require_once __DIR__ . '/../../../app/views/layouts/header.php'; ?>

<div class="max-w-4xl mx-auto px-4 py-10">
    <h1 class="text-3xl font-extrabold text-slate-900 mb-6">Vérification d'Identité & KYC</h1>
    
    <?php if (!empty($kycData['status']) && $kycData['status'] === 'pending'): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
            <p class="text-yellow-700">Votre dossier KYC est actuellement en cours d'examen par notre équipe de sécurité.</p>
        </div>
    <?php elseif (!empty($kycData['status']) && $kycData['status'] === 'approved'): ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
            <p class="text-green-700">Votre compte est officiellement vérifié et certifié !</p>
        </div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>/kyc/submit" method="POST" enctype="multipart/form-data" class="bg-white shadow-sm rounded-2xl border border-gray-200 p-6 space-y-6" x-data="{ accountType: 'individual' }">
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Type de Compte</label>
            <select name="account_type" x-model="accountType" class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-indigo-500">
                <option value="individual">Particulier</option>
                <option value="company">Entreprise / Entité Morale</option>
            </select>
        </div>

        <!-- Section Spécifique Entreprise -->
        <div x-show="accountType === 'company'" class="space-y-4 border-t pt-4">
            <h3 class="text-lg font-bold text-gray-800">Informations de l'Entreprise</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nom de l'Entreprise</label>
                    <input type="text" name="company_name" class="w-full border border-gray-300 rounded-xl p-3">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Numéro d'Immatriculation (RCCM)</label>
                    <input type="text" name="registration_number" class="w-full border border-gray-300 rounded-xl p-3">
                </div>
            </div>

            <h4 class="text-md font-semibold text-gray-800 mt-4">Traçabilité des Responsables (Double Validation Obligatoire)</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nom du Responsable Principal (CEO/Gérant)</label>
                    <input type="text" name="primary_manager_name" class="w-full border border-gray-300 rounded-xl p-3">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ID / Pièce du Responsable Principal</label>
                    <input type="file" name="primary_manager_id_card" class="w-full border border-gray-300 rounded-xl p-3">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nom du Second Responsable (Traçabilité)</label>
                    <input type="text" name="secondary_manager_name" class="w-full border border-gray-300 rounded-xl p-3">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ID / Pièce du Second Responsable</label>
                    <input type="file" name="secondary_manager_id_card" class="w-full border border-gray-300 rounded-xl p-3">
                </div>
            </div>
        </div>

        <!-- Documents Généraux -->
        <div class="border-t pt-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Pièce d'Identité Officielle (Recto/Verso)</label>
            <input type="file" name="id_document_path" required class="w-full border border-gray-300 rounded-xl p-3">
        </div>

        <button type="submit" class="w-full bg-slate-900 text-white font-bold py-3 rounded-xl hover:bg-slate-800 transition">
            Soumettre mon dossier KYC
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../../../app/views/layouts/footer.php'; ?>