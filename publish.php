<?php
// =========================================================================
// Page de Publication - MAN GO Marketplace (Annonces, Boutiques & Services Web)
// =========================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['kyc_status'] = 'verified';

$baseUrl = "http://localhost/man_go";

// Règle 1 : Redirection vers la connexion si l'utilisateur n'est pas connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $baseUrl . "/login.php?redirect=publish.php");
    exit();
}

// Règle 2 : Redirection vers le KYC si le vendeur n'est pas certifié
$userKycVerified = isset($_SESSION['kyc_status']) && $_SESSION['kyc_status'] === 'verified';
if (!$userKycVerified) {
    header("Location: " . $baseUrl . "/verification.php");
    exit();
}

// Traitement du formulaire à la soumission
$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $webLink = trim($_POST['web_link'] ?? ''); // Lien de site internet pour les prestataires

    if (empty($title) || empty($category) || empty($description)) {
        $errorMessage = "Veuillez remplir tous les champs obligatoires.";
    } else {
        // Logique d'enregistrement en base de données à insérer ici
        $successMessage = "Votre publication a été mise en ligne avec succès !";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publier une annonce ou un service - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

<!-- Header -->
<header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        <a href="<?= $baseUrl ?>/" class="flex items-center space-x-2 text-white">
            <span class="bg-amber-500 text-slate-950 font-black text-xl w-10 h-10 rounded-full flex items-center justify-center">M</span>
            <span class="font-extrabold text-2xl tracking-tight">MAN <span class="text-amber-500">GO</span></span>
        </a>
        <nav class="hidden md:flex items-center space-x-8 text-sm font-semibold text-gray-300">
            <a href="<?= $baseUrl ?>/" class="hover:text-amber-500 transition">Accueil</a>
            <a href="<?= $baseUrl ?>/listings" class="hover:text-amber-500 transition">Annonces</a>
            <a href="<?= $baseUrl ?>/stands" class="hover:text-amber-500 transition">Boutiques & Stands</a>
            <a href="<?= $baseUrl ?>/services" class="hover:text-amber-500 transition">Services</a>
        </nav>
        <div>
            <a href="<?= $baseUrl ?>/logout.php" class="text-xs font-bold text-red-400 hover:text-red-300 transition">Déconnexion</a>
        </div>
    </div>
</header>

<!-- Contenu Principal -->
<main class="flex-1 max-w-3xl mx-auto px-4 py-12 w-full">
    <div class="mb-8 text-center">
        <span class="bg-amber-100 text-amber-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Espace Vendeur & Prestataire</span>
        <h1 class="text-3xl font-black text-slate-900 mt-2">Publier sur MAN GO</h1>
        <p class="text-gray-600 text-sm mt-1">Ajoutez un produit, un service professionnel ou partagez le lien de votre site internet.</p>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm font-medium">
            <i class="fa-solid fa-circle-check mr-2"></i> <?= $successMessage ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium">
            <i class="fa-solid fa-circle-exclamation mr-2"></i> <?= $errorMessage ?>
        </div>
    <?php endif; ?>

    <form action="publish.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm space-y-6">
        
        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Titre de l'annonce ou du service *</label>
            <input type="text" name="title" required placeholder="Ex: iPhone 14 Pro Max ou Agence de Développement Web" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Catégorie *</label>
                <select name="category" required class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm bg-white">
                    <option value="">Sélectionner une catégorie</option>
                    <option value="high-tech">Électronique & High-Tech</option>
                    <option value="services">Services Professionnels & Prestations</option>
                    <option value="immobilier">Immobilier</option>
                    <option value="mode">Mode & Style</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Prix (FCFA) ou Budget</label>
                <input type="number" name="price" placeholder="Ex: 150000 ou Sur devis" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
            </div>
        </div>

        <!-- Champ spécifique pour les prestataires : Lien du site internet -->
        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">
                <i class="fa-solid fa-globe text-amber-500 mr-1"></i> Lien de votre site internet / Portfolio (Optionnel pour prestataires)
            </label>
            <input type="url" name="web_link" placeholder="https://votre-site-web.com" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
            <p class="text-xs text-gray-500 mt-1">Idéal pour les agences, développeurs et prestataires souhaitant rediriger vers leur propre plateforme.</p>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Description détaillée *</label>
            <textarea name="description" rows="5" required placeholder="Décrivez votre offre, vos compétences ou votre article en détail..." class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm"></textarea>
        </div>

        <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold py-3.5 rounded-xl transition shadow-md text-sm uppercase tracking-wider">
            Publier maintenant
        </button>
    </form>
</main>

<!-- Footer -->
<footer class="bg-slate-950 text-gray-400 text-sm border-t border-slate-800 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-xs text-gray-500">
        &copy; 2026 MAN GO Marketplace. Tous droits réservés.
    </div>
</footer>
</body>
</html>