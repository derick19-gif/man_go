<?php
/**
 * View: Modules/Services/Views/index.php
 * Description: Interface utilisateur pour l'affichage des services
 */
$title = $title ?? 'Services Professionnels - MAN GO';
$services = $services ?? [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .mango-gradient {
            background: linear-gradient(135deg, #FF7A00 0%, #FF9F0A 100%);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">

    <!-- En-tête / Navigation -->
    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="<?= BASE_URL ?>/" class="text-2xl font-black tracking-wider flex items-center space-x-2">
                    <span class="text-orange-500">MAN</span><span>GO</span>
                </a>
            </div>
            <nav class="hidden md:flex items-center space-x-6 text-sm font-medium">
                <a href="<?= BASE_URL ?>/" class="hover:text-orange-400 transition">Accueil</a>
                <a href="<?= BASE_URL ?>/listings" class="hover:text-orange-400 transition">Annonces</a>
                <a href="<?= BASE_URL ?>/stands" class="hover:text-orange-400 transition">Boutiques & Stands</a>
                <a href="<?= BASE_URL ?>/services" class="text-orange-400 font-semibold">Services</a>
            </nav>
            <div>
                <a href="<?= BASE_URL ?>/login.php" class="mango-gradient text-white px-5 py-2.5 rounded-full font-medium shadow-lg hover:opacity-90 transition">
                    Espace Membre
                </a>
            </div>
        </div>
    </header>

    <!-- Contenu Principal -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight sm:text-5xl">
                Services & Prestations <span class="text-orange-500">Professionnelles</span>
            </h1>
            <p class="mt-4 text-lg text-slate-600 max-w-2xl mx-auto">
                Découvrez des experts qualifiés, des prestataires de confiance et des services sur mesure adaptés à tous vos besoins.
            </p>
        </div>

        <!-- Grille des Services -->
        <?php if (!empty($services)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($services as $service): ?>
                    <div class="glass-card rounded-2xl shadow-sm hover:shadow-xl transition duration-300 overflow-hidden flex flex-col justify-between">
                        <div>
                            <div class="h-48 bg-slate-200 relative overflow-hidden">
                                <?php if (!empty($service['image'])): ?>
                                    <img src="<?= BASE_URL ?>/uploads/services/<?= htmlspecialchars($service['image']) ?>" alt="<?= htmlspecialchars($service['title']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-slate-400 bg-slate-100">
                                        <i class="fas fa-concierge-bell fa-3x"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="absolute top-3 right-3 bg-slate-900/80 text-white text-xs font-semibold px-3 py-1 rounded-full backdrop-blur-sm">
                                    <?= htmlspecialchars($service['category_name'] ?? 'Général') ?>
                                </span>
                            </div>
                            <div class="p-6">
                                <h3 class="text-xl font-bold text-slate-900 mb-2">
                                    <?= htmlspecialchars($service['title']) ?>
                                </h3>
                                <p class="text-slate-600 text-sm line-clamp-3 mb-4">
                                    <?= htmlspecialchars($service['description']) ?>
                                </p>
                            </div>
                        </div>
                        <div class="px-6 pb-6 pt-0 flex items-center justify-between border-t border-slate-100 mt-4 pt-4">
                            <span class="text-orange-600 font-bold text-lg">
                                <?= !empty($service['price']) ? number_format($service['price'], 0, ',', ' ') . ' XOF' : 'Sur devis' ?>
                            </span>
                            <a href="<?= BASE_URL ?>/services/detail?id=<?= $service['id'] ?>" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-slate-800 transition">
                                Voir détails
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-20 bg-white rounded-3xl shadow-sm border border-slate-100">
                <div class="text-slate-400 mb-4">
                    <i class="fas fa-folder-open fa-4x"></i>
                </div>
                <h3 class="text-xl font-semibold text-slate-700">Aucun service disponible pour le moment</h3>
                <p class="text-slate-500 text-sm mt-2">Revenez un peu plus tard ou publiez votre propre service depuis votre stand.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Pied de page -->
    <footer class="bg-slate-900 text-slate-400 py-12 mt-20 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm">
            <p>&copy; <?= date('Y') ?> MAN GO — One Market, One Movement. Tous droits réservés.</p>
        </div>
    </footer>

</body>
</html>