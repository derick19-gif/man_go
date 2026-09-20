<?php
$pageTitle = htmlspecialchars($stand['name']) . ' - MAN GO';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>
<div class="bg-slate-50 min-h-screen pb-20">
    <div class="bg-slate-900 text-white pt-12 pb-20 px-4">
        <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center gap-6">
            <img src="<?= htmlspecialchars($stand['logo_url'] ?? 'assets/images/placeholder.jpg') ?>" class="w-28 h-28 object-cover rounded-2xl border-4 border-amber-500 shadow-xl" alt="Logo">
            <div class="text-center md:text-left">
                <span class="bg-amber-500 text-slate-950 text-xs font-black px-3 py-1 rounded-full uppercase tracking-widest"><?= htmlspecialchars($stand['category'] ?? 'Boutique Officielle') ?></span>
                <h1 class="text-3xl md:text-4xl font-black mt-2"><?= htmlspecialchars($stand['name']) ?></h1>
                <p class="text-gray-400 text-sm mt-1"><i class="fa-solid fa-location-dot text-amber-500 mr-1"></i> <?= htmlspecialchars($stand['city']) ?> - <?= htmlspecialchars($stand['address'] ?? '') ?></p>
            </div>
        </div>
    </div>
    <main class="max-w-6xl mx-auto px-4 mt-10">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-10">
            <h3 class="text-lg font-black text-slate-900 mb-3">À propos de l'établissement</h3>
            <p class="text-gray-600 text-sm leading-relaxed"><?= nl2br(htmlspecialchars($stand['description'])) ?></p>
            <div class="mt-4 flex gap-4">
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $stand['phone'] ?? '') ?>" target="_blank" class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2.5 rounded-xl text-sm flex items-center gap-2">
                    <i class="fa-brands fa-whatsapp text-lg"></i> Contacter sur WhatsApp
                </a>
            </div>
        </div>
        <h2 class="text-2xl font-black text-slate-900 mb-6">Catalogue des offres et produits</h2>
        <?php if (!empty($listings)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                <?php foreach ($listings as $item): ?>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col">
                        <img src="<?= htmlspecialchars($item['image_url'] ?? 'assets/images/placeholder.jpg') ?>" class="h-48 w-full object-cover" alt="Produit">
                        <div class="p-5 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="font-bold text-slate-900"><?= htmlspecialchars($item['title']) ?></h4>
                                <p class="text-amber-600 font-black text-lg mt-2"><?= number_format($item['price'], 0, ',', ' ') ?> <?= htmlspecialchars($item['currency'] ?? 'FCFA') ?></p>
                            </div>
                            <a href="<?= (defined('APP_URL') ? APP_URL : '/man_go') ?>/listing-detail.php?id=<?= $item['id'] ?>" class="mt-4 w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl text-center text-sm block">Voir l'offre</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white p-10 rounded-2xl text-center border border-gray-100">
                <p class="text-gray-500 font-medium">Ce stand n'a publié aucune offre pour le moment.</p>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
