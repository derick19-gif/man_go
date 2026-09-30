<?php
// =========================================================================
// Page de Publication de Services - MAN GO Marketplace
// =========================================================================

if (!defined('APP_PATH')) {
    define('APP_PATH', __DIR__);
}

require_once __DIR__ . '/config/config.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// 1. Sécurité : Vérification de connexion
if (empty($_SESSION['user_id'])) {
    header("Location: $baseUrl/login.php?redirect=publish_service.php");
    exit();
}

// 2. Sécurité : Seuls les vendeurs/prestataires peuvent publier
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['vendor', 'vendeur', '4'])) {
    header("Location: $baseUrl/client/views/dashboard.php?error=not_vendor");
    exit();
}

require_once __DIR__ . '/core/Database.php';
$db = \App\Core\Database::connect();
$successMessage = ""; $errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmtStand = $db->prepare("SELECT id FROM stands WHERE user_id = :user_id AND status = 'active' LIMIT 1");
        $stmtStand->execute([':user_id' => $_SESSION['user_id']]);
        $stand = $stmtStand->fetch(PDO::FETCH_ASSOC);
        
        if (!$stand) {
            $errorMessage = "Vous devez d'abord créer et activer votre Stand Professionnel pour proposer des services.";
        } else {
            $standId = $stand['id'];
            $title = trim($_POST['title'] ?? '');
            $categoryId = intval($_POST['category_id'] ?? 0);
            
            $isQuote = isset($_POST['is_quote']) && $_POST['is_quote'] === '1';
            $price = $isQuote ? null : floatval($_POST['price'] ?? 0);
            
            $description = trim($_POST['description'] ?? '');
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

            $imageName = null;
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/public/uploads/services/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $imageName = 'srv_' . time() . '_' . uniqid() . '.' . $ext;
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName)) {
                        $errorMessage = "Erreur lors du téléchargement de l'image.";
                    }
                } else {
                    $errorMessage = "Format d'image non autorisé. Utilisez JPG, PNG ou WEBP.";
                }
            }

            if (empty($errorMessage)) {
                if (empty($title) || empty($categoryId) || empty($description)) {
                    $errorMessage = "Veuillez remplir tous les champs obligatoires.";
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO services (user_id, stand_id, category_id, title, slug, description, price, image, status, created_at) 
                        VALUES (:user_id, :stand_id, :category_id, :title, :slug, :description, :price, :image, 'active', NOW())
                    ");
                    $stmt->execute([
                        ':user_id' => $_SESSION['user_id'],
                        ':stand_id' => $standId,
                        ':category_id' => $categoryId,
                        ':title' => $title,
                        ':slug' => $slug,
                        ':description' => $description,
                        ':price' => $price,
                        ':image' => $imageName
                    ]);
                    $successMessage = "Votre service a été publié avec succès !";
                }
            }
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur système : " . $e->getMessage(); 
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Proposer un Service - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bibliothèques pour le menu déroulant avec recherche -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    
    <!-- Adaptation du design de Choices.js pour ressembler à Tailwind -->
    <style>
        .choices__inner {
            background-color: white;
            border-radius: 0.75rem !important; /* Arrondi Tailwind */
            border: 1px solid #cbd5e1 !important; /* Bordure Tailwind */
            padding: 0.35rem 1rem !important;
            font-size: 0.875rem !important;
        }
        .choices[data-type*="select-one"] .choices__input { background-color: white; }
        .choices__list--dropdown { border-radius: 0.75rem; border: 1px solid #cbd5e1; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); z-index: 50; }
        .choices__list--dropdown .choices__item--selectable.is-highlighted { background-color: #eff6ff; color: #1d4ed8; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

<?php 
$headerPath = __DIR__ . '/app/views/layouts/header.php';
if (file_exists($headerPath)) require_once $headerPath;
?>

<main class="flex-1 max-w-3xl mx-auto px-4 py-12 w-full">
    <div class="mb-8 text-center">
        <span class="bg-blue-100 text-blue-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase"><i class="fa-solid fa-handshake"></i> Espace Prestataire</span>
        <h1 class="text-3xl font-black text-slate-900 mt-3">Proposer un Service</h1>
        <p class="text-slate-500 mt-2">Mettez votre expertise à disposition de milliers de clients.</p>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
            <i class="fa-solid fa-circle-check text-xl mr-3"></i> <?= $successMessage ?>
        </div>
        <div class="text-center mb-6">
            <a href="<?= $baseUrl ?>/services" class="text-blue-600 font-bold hover:underline"><i class="fa-solid fa-arrow-left"></i> Retour au catalogue</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-xl mr-3"></i> <?= $errorMessage ?>
        </div>
    <?php endif; ?>

    <form action="publish_service.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm space-y-6">
        
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Titre de la prestation *</label>
            <input type="text" name="title" placeholder="Ex: Création de site web complet, Installation plomberie..." required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Domaine d'expertise *</label>
                <!-- Le select avec l'ID "category_id" sera transformé par JavaScript -->
                <select name="category_id" id="category_id" required>
                    <option value="">Sélectionnez ou tapez un domaine</option>
                    <option value="101">Aide à la personne & Garde d'enfants</option>
                    <option value="102">Bâtiment & Travaux (Plomberie, Maçonnerie, Électricité)</option>
                    <option value="103">Beauté, Soins & Esthétique à domicile</option>
                    <option value="104">Coaching, Cours particuliers & Formations</option>
                    <option value="105">Consulting, Comptabilité & Services aux Entreprises</option>
                    <option value="106">Dépannage & Réparation (Électroménager, Auto)</option>
                    <option value="107">Design, Graphisme, Photo & Vidéo</option>
                    <option value="108">Événementiel, Animation, DJ & Traiteur</option>
                    <option value="109">Informatique, Développement Web & Tech</option>
                    <option value="110">Logistique, Déménagement & Transport</option>
                    <option value="111">Marketing, Communication & Rédaction</option>
                    <option value="112">Ménage, Nettoyage & Entretien</option>
                    <option value="113">Santé, Médecine douce & Thérapies</option>
                    <option value="114">Services Animaliers (Garde, Toilettage)</option>
                    <option value="115">Tourisme, Guides & Loisirs</option>
                    <option value="999">Autres prestations / Divers</option>
                </select>
            </div>

            <div class="bg-blue-50 p-3 rounded-xl border border-blue-100">
                <label class="block text-xs font-bold text-blue-900 uppercase mb-2">Tarification</label>
                <div class="flex items-center mb-2">
                    <input type="checkbox" id="is_quote" name="is_quote" value="1" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500" onchange="togglePriceField()">
                    <label for="is_quote" class="ml-2 text-sm font-bold text-blue-800">C'est un service "Sur Devis"</label>
                </div>
                <div id="price_container" class="relative mt-2">
                    <input type="text" id="price_input" name="price" placeholder="Prix de base (Optionnel)" oninput="this.value = this.value.replace(/[^0-9]/g, '');" class="w-full px-4 py-2 pr-16 rounded-lg border border-blue-200 text-sm">
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                        <span class="text-slate-400 font-black text-xs"><?= htmlspecialchars($_SESSION['user_currency'] ?? 'FCFA') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-3">Image d'illustration du service</label>
            <div class="flex items-center justify-center w-full">
                <label for="dropzone-file" class="flex flex-col items-center justify-center w-full h-40 border-2 border-slate-300 border-dashed rounded-2xl cursor-pointer bg-slate-50 hover:bg-slate-100 transition">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400 mb-2"></i>
                        <p class="mb-2 text-sm text-slate-500 font-semibold"><span class="text-blue-600">Cliquez pour ajouter</span> ou glissez une image</p>
                    </div>
                    <input id="dropzone-file" type="file" name="image" accept="image/*" class="hidden" onchange="previewImage(event)"/>
                </label>
            </div>
            <div id="image-preview" class="hidden mt-4 h-48 w-full rounded-2xl overflow-hidden border border-slate-200">
                <img id="preview-img" src="" class="w-full h-full object-cover">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Description détaillée de votre offre *</label>
            <textarea name="description" rows="6" required class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Décrivez votre méthodologie, ce qui est inclus..."></textarea>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition text-sm uppercase shadow-lg shadow-blue-500/30">
            Publier mon service
        </button>
    </form>
</main>

<script>
    // Initialisation du menu déroulant avec recherche
    document.addEventListener('DOMContentLoaded', function() {
        const categoryElement = document.getElementById('category_id');
        if(categoryElement) {
            new Choices(categoryElement, {
                searchEnabled: true,
                searchPlaceholderValue: 'Tapez pour rechercher...',
                itemSelectText: '', // Enlève le texte moche par défaut
                noResultsText: 'Aucun domaine trouvé, choisissez "Autres"',
                shouldSort: false // On garde notre tri alphabétique HTML
            });
        }
    });

    // Gestion du prix
    function togglePriceField() {
        const isQuote = document.getElementById('is_quote').checked;
        const priceInput = document.getElementById('price_input');
        if (isQuote) {
            priceInput.value = ''; priceInput.disabled = true;
            priceInput.classList.add('bg-slate-100', 'cursor-not-allowed', 'opacity-50');
        } else {
            priceInput.disabled = false;
            priceInput.classList.remove('bg-slate-100', 'cursor-not-allowed', 'opacity-50');
        }
    }

    // Prévisualisation de l'image
    function previewImage(event) {
        const previewDiv = document.getElementById('image-preview');
        const previewImg = document.getElementById('preview-img');
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) { previewImg.src = e.target.result; previewDiv.classList.remove('hidden'); }
            reader.readAsDataURL(file);
        } else { previewDiv.classList.add('hidden'); }
    }
</script>
</body>
</html>