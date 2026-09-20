<?php
// =========================================================================
// Page de Publication - MAN GO Marketplace (Annonces, Boutiques & Services)
// =========================================================================

if (!defined('APP_PATH')) {
    define('APP_PATH', __DIR__);
}

// 1. INCLUSION CAPITALE AVANT LA SESSION (C'est la clé du mystère !)
require_once __DIR__ . '/config/config.php';

// 2. DÉMARRAGE DE LA SESSION AVEC VOTRE NOM PERSONNALISÉ
if (defined('SESSION_NAME')) {
    session_name(SESSION_NAME);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// 3. Redirection vers la connexion si l'utilisateur n'est pas connecté
if (empty($_SESSION['user_id'])) {
    header("Location: $baseUrl/login.php?redirect=publish.php");
    exit();
}

// 4. Inclusions obligatoires
require_once __DIR__ . '/modules/kyc/Models/KycModel.php';
require_once __DIR__ . '/modules/kyc/Controllers/KycController.php';
require_once __DIR__ . '/core/Database.php';

// 5. Le Vigile KYC (Endormi temporairement)
/* 
// TODO: SÉCURITÉ KYC DÉSACTIVÉE TEMPORAIREMENT - À RÉACTIVER AVANT LA PRODUCTION
if (!\Modules\Kyc\Controllers\KycController::checkAccess()) {
    header("Location: $baseUrl/verification.php");
    exit();
}
*/

$successMessage = "";
$errorMessage = "";


// 4. TRAITEMENT DU FORMULAIRE ET ENREGISTREMENT EN BASE DE DONNÉES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        $db = \App\Core\Database::connect();
        
        // SÉCURITÉ ABSOLUE : On vérifie si le vendeur possède un Stand ACTIF
        $stmtStand = $db->prepare("SELECT id FROM stands WHERE user_id = :user_id AND status = 'active' LIMIT 1");
        $stmtStand->execute([':user_id' => $_SESSION['user_id']]);
        $stand = $stmtStand->fetch(PDO::FETCH_ASSOC);
        
        if (!$stand) {
            // Blocage immédiat : Pas de stand = Pas de publication
            $errorMessage = "Opération refusée : Vous devez d'abord créer et activer votre Stand Officiel avant de pouvoir publier des annonces.";
        } else {
            // Le stand existe, on peut procéder à l'enregistrement de l'annonce
            $standId = $stand['id'];
            
            $title = trim($_POST['title'] ?? '');
            $categoryId = intval($_POST['category_id'] ?? 0);
            $price = floatval($_POST['price'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $webLink = trim($_POST['web_link'] ?? '');
            
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

            $imagePath = null;
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/public/uploads/listings/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($ext, $allowedExts)) {
                    $fileName = 'listing_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                        $imagePath = 'public/uploads/listings/' . $fileName;
                    }
                } else {
                    $errorMessage = "Format d'image non supporté. Utilisez JPG, PNG ou WEBP.";
                }
            }

            if (empty($title) || empty($categoryId) || empty($description)) {
                if (empty($errorMessage)) $errorMessage = "Veuillez remplir tous les champs obligatoires.";
            } elseif (empty($errorMessage)) { // On vérifie qu'il n'y a pas eu d'erreur d'image
                
                $stmt = $db->prepare("
                    INSERT INTO listings (user_id, stand_id, category_id, title, slug, price, description, web_link, image_path, status, created_at) 
                    VALUES (:user_id, :stand_id, :category_id, :title, :slug, :price, :description, :web_link, :image_path, 'active', NOW())
                ");
                
                $stmt->execute([
                    ':user_id' => $_SESSION['user_id'],
                    ':stand_id' => $standId,
                    ':category_id' => $categoryId,
                    ':title' => $title,
                    ':slug' => $slug,
                    ':price' => $price,
                    ':description' => $description,
                    ':web_link' => $webLink,
                    ':image_path' => $imagePath
                ]);
                
                $successMessage = "Votre annonce a été mise en ligne avec succès !";
            }
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur de base de données : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publier une annonce - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 500: '#f59e0b', 600: '#d97706', 950: '#090d16' } },
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen font-sans">

<?php 
// Inclusion dynamique du Header global
$headerPath = __DIR__ . '/themes/default/templates/layouts/header.php';
if (!file_exists($headerPath)) $headerPath = __DIR__ . '/app/views/layouts/header.php';
if (file_exists($headerPath)) require_once $headerPath;
?>

<!-- Contenu Principal -->
<main class="flex-1 max-w-3xl mx-auto px-4 py-12 w-full">
    <div class="mb-8 text-center">
        <span class="bg-amber-100 text-amber-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Espace Vendeur & Prestataire</span>
        <h1 class="text-3xl font-black text-slate-900 mt-2">Publier sur MAN GO</h1>
        <p class="text-slate-500 text-sm mt-1">Ajoutez un produit, un service professionnel ou partagez le lien de votre site internet.</p>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
            <i class="fa-solid fa-circle-check text-xl mr-3"></i> <?= $successMessage ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-xl mr-3"></i> <?= $errorMessage ?>
        </div>
    <?php endif; ?>

    <form action="publish.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm space-y-6">
        
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Titre de l'annonce ou du service *</label>
            <input type="text" name="title" required placeholder="Ex: iPhone 14 Pro Max ou Agence Web" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 transition text-sm font-medium">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
           <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Catégorie *</label>
                <select name="category_id" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 transition text-sm bg-white font-medium">
                    <option value="">Sélectionner une catégorie</option>
                    <option value="1">Électronique & High-Tech</option>
                    <option value="2">Services Professionnels & Prestations</option>
                    <option value="3">Immobilier & Foncier</option>
                    <option value="4">Mode & Style</option>
                    <option value="5">Véhicules & Transports</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Prix (FCFA) ou Budget</label>
                <input type="number" inputmode="numeric" pattern="[0-9]*" min="0" step="1" name="price" placeholder="Ex: 150000" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 transition text-sm font-medium">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Photo principale de l'annonce</label>
            <input type="file" name="image" accept="image/*" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm bg-slate-50 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-slate-800 transition cursor-pointer">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">
                <i class="fa-solid fa-globe text-amber-500 mr-1"></i> Lien de votre site internet / Portfolio (Optionnel)
            </label>
            <input type="url" name="web_link" placeholder="https://votre-site-web.com" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 transition text-sm font-medium">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Description détaillée *</label>
            <textarea name="description" rows="5" required placeholder="Décrivez votre offre en détail..." class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 transition text-sm font-medium"></textarea>
        </div>

        <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black py-4 rounded-xl transition transform hover:-translate-y-0.5 shadow-lg text-sm uppercase tracking-wider">
            Mettre en ligne mon annonce
        </button>
    </form>
</main>

<?php 
// Inclusion dynamique du Footer global
$footerPath = __DIR__ . '/themes/default/templates/layouts/footer.php';
if (!file_exists($footerPath)) $footerPath = __DIR__ . '/app/views/layouts/footer.php';
if (file_exists($footerPath)) require_once $footerPath;
?>

