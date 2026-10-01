<?php
// =========================================================================
// Page de Publication & Édition de Services - MAN GO Marketplace
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
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (empty($currentUserId)) {
    header("Location: " . $baseUrl . "/login.php?redirect=publish_service.php");
    exit();
}

// 2. Sécurité : Seuls les vendeurs/prestataires peuvent publier
$userRole = $_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? '';
if (!in_array($userRole, ['vendor', 'vendeur', '4'])) {
    header("Location: " . $baseUrl . "/client/views/dashboard.php?error=not_vendor");
    exit();
}

require_once __DIR__ . '/core/Database.php';
$db = \App\Core\Database::connect();
$successMessage = ""; $errorMessage = "";

// ==========================================
// GESTION DU MODE ÉDITION & SUPPRESSION
// ==========================================
$editMode = false;
$serviceId = $_GET['id'] ?? $_POST['service_id'] ?? null;
$existingData = [];

if ($serviceId) {
    $stmtEdit =$db->prepare("SELECT * FROM services WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmtEdit->execute([':id' => $serviceId, ':uid' =>$currentUserId]);
    $existingData =$stmtEdit->fetch(PDO::FETCH_ASSOC);
    if ($existingData) {$editMode = true;
    } else {
        $serviceId = null; 
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ACTION : SUPPRESSION
        $actionPost =$_POST['action'] ?? '';
        if ($actionPost === 'delete' &&$editMode) {
            $db->prepare("DELETE FROM services WHERE id = :id")->execute([':id' => $serviceId]);
            header("Location: " . $baseUrl . "/client/views/dashboard.php?msg=service_deleted");
            exit;
        }

        // ACTION : PUBLICATION / MODIFICATION
        $stmtStand =$db->prepare("SELECT id FROM stands WHERE user_id = :user_id AND status = 'active' LIMIT 1");
        $stmtStand->execute([':user_id' =>$currentUserId]);
        $stand =$stmtStand->fetch(PDO::FETCH_ASSOC);
        
        if (!$stand) {$errorMessage = "Vous devez d'abord créer et activer votre Stand Professionnel pour proposer des services.";
        } else {
            $standId =$stand['id'];
            $title = trim($_POST['title'] ?? '');
            $categoryId = intval($_POST['category_id'] ?? 0);
            
            $isQuote = isset($_POST['is_quote']) && $_POST['is_quote'] === '1';$price = $isQuote ? null : floatval($_POST['price'] ?? 0);
            
            $description = trim($_POST['description'] ?? '');
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-',$title), '-'));

            // VÉRIFICATION DES CHAMPS VIDES
            $hasEmptyFields = false;
            if (empty($title)) {$hasEmptyFields = true; }
            if (empty($categoryId)) {$hasEmptyFields = true; }
            if (empty($description)) {$hasEmptyFields = true; }

            // GESTION MULTI-IMAGES
            $mainImagePath = $editMode ? $existingData['image'] : null;
            $uploadedImages = [];$uploadDir = __DIR__ . '/public/uploads/services/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            if (!empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['name'] as $key =>$name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));$allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                        if (in_array($ext,$allowedExts)) {
                            $fileName = 'srv_' . time() . '_' . uniqid() . '.' . $ext;
                            if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $uploadDir .$fileName)) {
                                $uploadedImages[] =$fileName;
                            }
                        }
                    }
                }
            }

            if (!empty($uploadedImages)) {
                $mainImagePath =$uploadedImages[0];
            }

            if ($hasEmptyFields) {$errorMessage = "Veuillez remplir tous les champs obligatoires.";
            } else {
                if ($editMode) {
                    $stmt =$db->prepare("
                        UPDATE services 
                        SET title=:title, slug=:slug, description=:description, price=:price, image=:image, category_id=:category_id
                        WHERE id=:id AND user_id=:uid
                    ");
                    $stmt->execute([
                        ':title' => $title, ':slug' => $slug, ':description' =>$description, 
                        ':price' => $price, ':image' => $mainImagePath, ':category_id' =>$categoryId,
                        ':id' => $serviceId, ':uid' =>$currentUserId
                    ]);
                    $successMessage = "Votre service a été modifié avec succès.";
                    $existingData = array_merge($existingData,$_POST);
                } else {
                    $stmt =$db->prepare("
                        INSERT INTO services (user_id, stand_id, category_id, title, slug, description, price, image, status, created_at) 
                        VALUES (:user_id, :stand_id, :category_id, :title, :slug, :description, :price, :image, 'active', NOW())
                    ");
                    $stmt->execute([
                        ':user_id' => $currentUserId, ':stand_id' => $standId, ':category_id' =>$categoryId,
                        ':title' => $title, ':slug' => $slug, ':description' =>$description, 
                        ':price' => $price, ':image' =>$mainImagePath
                    ]);
                    $serviceId = $db->lastInsertId();$successMessage = "Votre service a été publié avec succès !";
                    $editMode = true; // On bascule en édition
                }

                // Insertion des images supplémentaires dans la galerie (service_images)
                if (count($uploadedImages) > 1) {
                    if ($editMode) {
                        $db->prepare("DELETE FROM service_images WHERE service_id = ?")->execute([$serviceId]);
                    }
                    $stmtGallery =$db->prepare("INSERT INTO service_images (service_id, image_path) VALUES (?, ?)");
                    for ($i = 1; $i < count($uploadedImages);$i++) {
                        $stmtGallery->execute([$serviceId, $uploadedImages[$i]]);
                    }
                }
            }
        }
    } catch (PDOException $e) {$errorMessage = "Erreur système : " . $e->getMessage(); 
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $editMode ? 'Modifier' : 'Proposer' ?> un Service - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bibliothèques pour le menu déroulant avec recherche -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    
    <style>
        .choices__inner { background-color: white; border-radius: 0.75rem !important; border: 1px solid #cbd5e1 !important; padding: 0.35rem 1rem !important; font-size: 0.875rem !important; }
        .choices[data-type*="select-one"] .choices__input { background-color: white; }
        .choices__list--dropdown { border-radius: 0.75rem; border: 1px solid #cbd5e1; z-index: 50; }
        .choices__list--dropdown .choices__item--selectable.is-highlighted { background-color: #eff6ff; color: #1d4ed8; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

<?php 
$headerPath = __DIR__ . '/app/views/layouts/header.php';
if (file_exists($headerPath)) require_once$headerPath;
?>

<main class="flex-1 max-w-3xl mx-auto px-4 py-12 w-full">
    <div class="mb-8 text-center">
        <span class="bg-blue-100 text-blue-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase"><i class="fa-solid fa-handshake"></i> Espace Prestataire</span>
        <h1 class="text-3xl font-black text-slate-900 mt-3"><?= $editMode ? 'Modifier votre Service' : 'Proposer un Service' ?></h1>
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
        
        <?php if($editMode): ?>
            <input type="hidden" name="service_id" value="<?= $serviceId ?>">
        <?php endif; ?>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Titre de la prestation *</label>
            <input type="text" name="title" value="<?= htmlspecialchars($existingData['title'] ?? '') ?>" placeholder="Ex: Création de site web complet, Installation plomberie..." required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Domaine d'expertise *</label>
                <select name="category_id" id="category_id" required>
                    <option value="">Sélectionnez ou tapez un domaine</option>
                    <option value="101" <?= (($existingData['category_id']??0) == 101) ? 'selected' : '' ?>>Aide à la personne & Garde d'enfants</option>
                    <option value="102" <?= (($existingData['category_id']??0) == 102) ? 'selected' : '' ?>>Bâtiment & Travaux (Plomberie, Maçonnerie)</option>
                    <option value="103" <?= (($existingData['category_id']??0) == 103) ? 'selected' : '' ?>>Beauté, Soins & Esthétique à domicile</option>
                    <option value="104" <?= (($existingData['category_id']??0) == 104) ? 'selected' : '' ?>>Coaching, Cours particuliers & Formations</option>
                    <option value="105" <?= (($existingData['category_id']??0) == 105) ? 'selected' : '' ?>>Consulting, Comptabilité & Services Pro</option>
                    <option value="106" <?= (($existingData['category_id']??0) == 106) ? 'selected' : '' ?>>Dépannage & Réparation (Électroménager, Auto)</option>
                    <option value="107" <?= (($existingData['category_id']??0) == 107) ? 'selected' : '' ?>>Design, Graphisme, Photo & Vidéo</option>
                    <option value="108" <?= (($existingData['category_id']??0) == 108) ? 'selected' : '' ?>>Événementiel, Animation, DJ & Traiteur</option>
                    <option value="109" <?= (($existingData['category_id']??0) == 109) ? 'selected' : '' ?>>Informatique, Développement Web & Tech</option>
                    <option value="110" <?= (($existingData['category_id']??0) == 110) ? 'selected' : '' ?>>Logistique, Déménagement & Transport</option>
                    <option value="111" <?= (($existingData['category_id']??0) == 111) ? 'selected' : '' ?>>Marketing, Communication & Rédaction</option>
                    <option value="112" <?= (($existingData['category_id']??0) == 112) ? 'selected' : '' ?>>Ménage, Nettoyage & Entretien</option>
                    <option value="113" <?= (($existingData['category_id']??0) == 113) ? 'selected' : '' ?>>Santé, Médecine douce & Thérapies</option>
                    <option value="114" <?= (($existingData['category_id']??0) == 114) ? 'selected' : '' ?>>Services Animaliers (Garde, Toilettage)</option>
                    <option value="115" <?= (($existingData['category_id']??0) == 115) ? 'selected' : '' ?>>Tourisme, Guides & Loisirs</option>
                    <option value="999" <?= (($existingData['category_id']??0) == 999) ? 'selected' : '' ?>>Autres prestations / Divers</option>
                </select>
            </div>

            <div class="bg-blue-50 p-3 rounded-xl border border-blue-100">
                <label class="block text-xs font-bold text-blue-900 uppercase mb-2">Tarification</label>
                <div class="flex items-center mb-2">
                    <?php $isQuoteChecked = ($editMode &&$existingData['price'] === null) ? 'checked' : ''; ?>
                    <input type="checkbox" id="is_quote" name="is_quote" value="1" <?= $isQuoteChecked ?> class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500" onchange="togglePriceField()">
                    <label for="is_quote" class="ml-2 text-sm font-bold text-blue-800">C'est un service "Sur Devis"</label>
                </div>
                <div id="price_container" class="relative mt-2">
                    <input type="text" id="price_input" name="price" value="<?= htmlspecialchars($existingData['price'] ?? '') ?>" placeholder="Prix de base (Optionnel)" oninput="this.value = this.value.replace(/[^0-9]/g, '');" class="w-full px-4 py-2 pr-16 rounded-lg border border-blue-200 text-sm">
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                        <span class="text-slate-400 font-black text-xs"><?= htmlspecialchars($_SESSION['user_currency'] ?? 'FCFA') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-3">Images d'illustration (Vos réalisations)</label>
            <div class="flex items-center justify-center w-full">
                <label for="imagesInput" class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-300 border-dashed rounded-2xl cursor-pointer bg-slate-50 hover:bg-slate-100 transition">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <i class="fa-solid fa-images text-3xl text-slate-400 mb-2"></i>
                        <p class="mb-2 text-sm text-slate-500 font-semibold"><span class="text-blue-600">Sélectionnez plusieurs images</span></p>
                    </div>
                    <input id="imagesInput" type="file" name="images[]" multiple accept="image/*" class="hidden"/>
                </label>
            </div>
            
            <div id="image-preview-container" class="flex flex-wrap gap-4 mt-4 empty:mt-0"></div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Description détaillée de votre offre *</label>
            <textarea name="description" rows="6" required class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Décrivez votre méthodologie, ce qui est inclus..."><?= htmlspecialchars($existingData['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition text-sm uppercase shadow-lg shadow-blue-500/30 flex justify-center items-center gap-2">
            <i class="fa-solid <?= $editMode ? 'fa-floppy-disk' : 'fa-paper-plane' ?>"></i> <?= $editMode ? 'Enregistrer les modifications' : 'Publier mon service' ?>
        </button>
    </form>

    <?php if($editMode): ?>
    <form action="publish_service.php" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement ce service ?');" class="mt-4">
        <input type="hidden" name="service_id" value="<?= $serviceId ?>">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="w-full bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 font-bold py-3 rounded-xl transition text-sm flex justify-center items-center gap-2">
            <i class="fa-solid fa-trash"></i> Supprimer ce service
        </button>
    </form>
    <?php endif; ?>

</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const categoryElement = document.getElementById('category_id');
        if(categoryElement) {
            new Choices(categoryElement, {
                searchEnabled: true,
                searchPlaceholderValue: 'Tapez pour rechercher...',
                itemSelectText: '',
                noResultsText: 'Aucune catégorie trouvée, choisissez "Autres"',
                shouldSort: false
            });
        }
        
        // Initialiser l'état du prix au chargement
        togglePriceField();

        // Système de prévisualisation multi-images avec object-contain
        const fileInput = document.getElementById('imagesInput');
        const previewContainer = document.getElementById('image-preview-container');
        let selectedFiles = new DataTransfer(); 

        fileInput.addEventListener('change', function(e) {
            selectedFiles = new DataTransfer(); 
            for (let i = 0; i < this.files.length; i++) selectedFiles.items.add(this.files[i]);
            renderPreviews();
        });

        function renderPreviews() {
            previewContainer.innerHTML = '';
            const files = selectedFiles.files;
            for (let i = 0; i < files.length; i++) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    // Ajout de object-contain et bg-slate-100 pour un cadrage propre sans coupure
                    div.className = 'relative w-28 h-28 bg-slate-100 rounded-xl overflow-hidden shadow-sm border border-slate-200 group flex items-center justify-center p-1';
                    div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-contain">
                                     <button type="button" onclick="removeFile(${i})" class="absolute top-1 right-1 bg-red-600 text-white w-6 h-6 rounded-full opacity-0 group-hover:opacity-100 transition text-xs font-bold shadow-md"><i class="fa-solid fa-xmark"></i></button>`;
                    previewContainer.appendChild(div);
                }
                reader.readAsDataURL(files[i]);
            }
        }
        
        window.removeFile = function(index) {
            const dt = new DataTransfer();
            const files = selectedFiles.files;
            for (let i = 0; i < files.length; i++) if (i !== index) dt.items.add(files[i]);
            selectedFiles = dt;
            fileInput.files = selectedFiles.files;
            renderPreviews();
        }
    });

    // Gestion du prix "Sur devis"
    function togglePriceField() {
        const isQuote = document.getElementById('is_quote');
        const priceInput = document.getElementById('price_input');
        if (isQuote && priceInput) {
            if (isQuote.checked) {
                priceInput.value = ''; priceInput.disabled = true;
                priceInput.classList.add('bg-slate-100', 'cursor-not-allowed', 'opacity-50');
            } else {
                priceInput.disabled = false;
                priceInput.classList.remove('bg-slate-100', 'cursor-not-allowed', 'opacity-50');
            }
        }
    }
</script>
</body>
</html>