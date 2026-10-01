<?php
// =========================================================================
// Page de Publication & Édition - Annuaire Web Vérifié - MAN GO
// =========================================================================

if (!defined('APP_PATH')) define('APP_PATH', __DIR__);
require_once __DIR__ . '/config/config.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (empty($currentUserId)) {
    header("Location: " . $baseUrl . "/login.php?redirect=publish_directory.php");
    exit();
}

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/DomainValidator.php'; 

$db = \App\Core\Database::connect();
$successMessage = ""; $errorMessage = "";

// ==========================================
// GESTION DU MODE ÉDITION & SUPPRESSION
// ==========================================
$editMode = false;
$directoryId = $_GET['id'] ?? $_POST['directory_id'] ?? null;
$existingData = [];

if ($directoryId) {
    $stmtEdit =$db->prepare("SELECT * FROM web_directory WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmtEdit->execute([':id' => $directoryId, ':uid' =>$currentUserId]);
    $existingData =$stmtEdit->fetch(PDO::FETCH_ASSOC);
    if ($existingData) {$editMode = true;
    } else {
        $directoryId = null; 
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ACTION : SUPPRESSION
        $actionPost =$_POST['action'] ?? '';
        if ($actionPost === 'delete' &&$editMode) {
            $db->prepare("DELETE FROM web_directory WHERE id = :id")->execute([':id' => $directoryId]);
            header("Location: " . $baseUrl . "/client/views/dashboard.php?msg=directory_deleted");
            exit;
        }

        // ACTION : PUBLICATION / MODIFICATION
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $categoryId = intval($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        // VÉRIFICATION DES CHAMPS VIDES (réécrit pour éviter le bug "\vert")
        $hasEmptyFields = false;
        if (empty($title)) {$hasEmptyFields = true; }
        if (empty($url)) {$hasEmptyFields = true; }
        if (empty($categoryId)) {$hasEmptyFields = true; }
        if (empty($description)) {$hasEmptyFields = true; }

        if ($hasEmptyFields) {$errorMessage = "Veuillez remplir tous les champs obligatoires.";
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {$errorMessage = "L'URL fournie n'est pas valide. N'oubliez pas le https://";
        } else {
            
            // On ne vérifie le domaine que si c'est une création, ou si l'URL a été modifiée
            $urlChanged = false;
            if (!$editMode) {$urlChanged = true;
            } elseif (isset($existingData['url']) &&$existingData['url'] !== $url) {$urlChanged = true;
            }
            
            $validation = ['success' => true, 'domain' =>$existingData['domain'] ?? ''];

            if ($urlChanged) {
                $validation = \App\Core\DomainValidator::checkDomainAge($url, 365);
            }

            if (!$validation['success']) {
                $errorMessage =$validation['message'];
            } else {
                $domain =$validation['domain'];
                
                // MULTI-IMAGES
                $mainImagePath = $editMode ? $existingData['image'] : null;
                $uploadedImages = [];$uploadDir = __DIR__ . '/public/uploads/directory/';
                
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                if (!empty($_FILES['images']['name'][0])) {
                    foreach ($_FILES['images']['name'] as $key =>$name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));$allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                            if (in_array($ext,$allowedExts)) {
                                $fileName = 'web_' . time() . '_' . uniqid() . '.' . $ext;
                                if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $uploadDir .$fileName)) {
                                    $uploadedImages[] =$fileName;
                                }
                            }
                        }
                    }
                }

                if (!empty($uploadedImages)) {
                    $mainImagePath =$uploadedImages[0]; // La première image devient l'image principale
                }

                if ($editMode) {
                    $stmt =$db->prepare("
                        UPDATE web_directory 
                        SET title=:title, url=:url, domain=:domain, description=:description, image=:image, category_id=:category_id
                        WHERE id=:id AND user_id=:uid
                    ");
                    $stmt->execute([
                        ':title' => $title, ':url' => $url, ':domain' =>$domain, 
                        ':description' => $description, ':image' => $mainImagePath, ':category_id' =>$categoryId,
                        ':id' => $directoryId, ':uid' =>$currentUserId
                    ]);
                    $successMessage = "Vos modifications ont été enregistrées avec succès.";
                    $existingData = array_merge($existingData,$_POST);
                } else {
                    $stmt =$db->prepare("
                        INSERT INTO web_directory (user_id, title, url, domain, description, image, category_id, status, published_at) 
                        VALUES (:user_id, :title, :url, :domain, :description, :image, :category_id, 'active', NOW())
                    ");
                    $stmt->execute([
                        ':user_id' => $currentUserId, ':title' =>$title, ':url' => $url, ':domain' =>$domain,
                        ':description' => $description, ':image' => $mainImagePath, ':category_id' =>$categoryId
                    ]);
                    $directoryId = $db->lastInsertId();$successMessage = "Félicitations ! Votre site a passé l'audit et est maintenant référencé.";
                    $editMode = true; // On bascule en édition après création
                }

                // Insertion des images supplémentaires dans la galerie
                if (count($uploadedImages) > 1) {
                    if ($editMode) {
                        $db->prepare("DELETE FROM directory_images WHERE directory_id = ?")->execute([$directoryId]);
                    }
                    $stmtGallery =$db->prepare("INSERT INTO directory_images (directory_id, image_path) VALUES (?, ?)");
                    for ($i = 1; $i < count($uploadedImages);$i++) {
                        $stmtGallery->execute([$directoryId, $uploadedImages[$i]]);
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
    <title><?= $editMode ? 'Modifier' : 'Référencer' ?> un Site Web - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    
    <style>
        .choices__inner { background-color: white; border-radius: 0.75rem !important; border: 1px solid #cbd5e1 !important; padding: 0.35rem 1rem !important; font-size: 0.875rem !important; }
        .choices[data-type*="select-one"] .choices__input { background-color: white; }
        .choices__list--dropdown { border-radius: 0.75rem; border: 1px solid #cbd5e1; z-index: 50; }
        .choices__list--dropdown .choices__item--selectable.is-highlighted { background-color: #eef2ff; color: #4f46e5; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

<?php 
$headerPath = __DIR__ . '/app/views/layouts/header.php';
if (file_exists($headerPath)) require_once$headerPath;
?>

<main class="flex-1 max-w-3xl mx-auto px-4 py-12 w-full">
    
    <div class="mb-8 text-center">
        <span class="bg-indigo-100 text-indigo-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">
            <i class="fa-solid fa-globe"></i> Annuaire Web Vérifié
        </span>
        <h1 class="text-3xl font-black text-slate-900 mt-4"><?= $editMode ? 'Modifier votre Référencement' : 'Référencer votre Site ou Application' ?></h1>
        <p class="text-slate-500 mt-2">Faites découvrir votre univers. Quel que soit votre domaine, partagez votre plateforme en toute sécurité avec notre communauté.</p>
    </div>

    <div class="bg-slate-900 text-white p-4 rounded-2xl mb-8 flex items-start gap-4 shadow-lg">
        <div class="bg-emerald-500 text-slate-900 w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 text-xl">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div>
            <h4 class="font-bold text-emerald-400">Politique de Sécurité Anti-Fraude</h4>
            <p class="text-sm text-slate-300 mt-1 leading-relaxed">Pour protéger nos utilisateurs, notre robot analyse l'âge des noms de domaine. <strong class="text-white">Seuls les sites existant depuis plus de 12 mois sont acceptés.</strong></p>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
            <i class="fa-solid fa-circle-check text-xl mr-3"></i> <?= $successMessage ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-xl mr-3 flex-shrink-0"></i> 
            <span><?= $errorMessage ?></span>
        </div>
    <?php endif; ?>

    <form action="publish_directory.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm space-y-6">
        
        <?php if($editMode): ?>
            <input type="hidden" name="directory_id" value="<?= $directoryId ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Titre ou Nom de l'entité *</label>
                <input type="text" name="title" value="<?= htmlspecialchars($existingData['title'] ?? '') ?>" placeholder="Ex: MAN GO, Mon Projet..." required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Lien URL du site *</label>
                <input type="url" name="url" value="<?= htmlspecialchars($existingData['url'] ?? '') ?>" placeholder="https://www.votre-site.com" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Catégorie du site *</label>
            <select name="category_id" id="category_id" required>
                <option value="">Sélectionnez ou tapez votre domaine</option>
                <option value="201" <?= (($existingData['category_id']??0) == 201) ? 'selected' : '' ?>>Administration & Services Publics</option>
                <option value="202" <?= (($existingData['category_id']??0) == 202) ? 'selected' : '' ?>>Application Web & Outils SaaS</option>
                <option value="203" <?= (($existingData['category_id']??0) == 203) ? 'selected' : '' ?>>Blog & Médias d'Information</option>
                <option value="204" <?= (($existingData['category_id']??0) == 204) ? 'selected' : '' ?>>Boutique en Ligne & E-commerce</option>
                <option value="205" <?= (($existingData['category_id']??0) == 205) ? 'selected' : '' ?>>Éducation, Écoles & Universités</option>
                <option value="206" <?= (($existingData['category_id']??0) == 206) ? 'selected' : '' ?>>Entreprise, Corporate & B2B</option>
                <option value="207" <?= (($existingData['category_id']??0) == 207) ? 'selected' : '' ?>>ONG, Associations & Fondations</option>
                <option value="208" <?= (($existingData['category_id']??0) == 208) ? 'selected' : '' ?>>Portfolio & Créateur de contenu</option>
                <option value="209" <?= (($existingData['category_id']??0) == 209) ? 'selected' : '' ?>>Santé & Centres Médicaux</option>
                <option value="999" <?= (($existingData['category_id']??0) == 999) ? 'selected' : '' ?>>Autre / Projet indépendant</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-3">Logo et Captures d'écran du site (Plusieurs possibles)</label>
            <div class="flex items-center justify-center w-full">
                <label for="imagesInput" class="flex flex-col items-center justify-center w-full h-32 border-2 border-indigo-200 border-dashed rounded-2xl cursor-pointer bg-indigo-50 hover:bg-indigo-100 transition">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <i class="fa-solid fa-images text-3xl text-indigo-400 mb-2"></i>
                        <p class="mb-2 text-sm text-slate-500 font-semibold"><span class="text-indigo-600">Sélectionnez vos images</span></p>
                    </div>
                    <input id="imagesInput" type="file" name="images[]" multiple accept="image/*" class="hidden"/>
                </label>
            </div>
            
            <div id="image-preview-container" class="flex flex-wrap gap-4 mt-4 empty:mt-0"></div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Description pour attirer vos visiteurs *</label>
            <textarea name="description" rows="5" required class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Présentez brièvement ce que les utilisateurs trouveront sur votre site..."><?= htmlspecialchars($existingData['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 rounded-xl transition text-sm uppercase shadow-lg shadow-indigo-500/30 flex justify-center items-center gap-2">
            <i class="fa-solid <?= $editMode ? 'fa-floppy-disk' : 'fa-robot' ?>"></i> <?= $editMode ? 'Enregistrer les modifications' : "Lancer l'audit et Publier" ?>
        </button>
    </form>

    <?php if($editMode): ?>
    <form action="publish_directory.php" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement ce site de l\'annuaire ?');" class="mt-4">
        <input type="hidden" name="directory_id" value="<?= $directoryId ?>">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="w-full bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 font-bold py-3 rounded-xl transition text-sm flex justify-center items-center gap-2">
            <i class="fa-solid fa-trash"></i> Supprimer ce référencement
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
                noResultsText: 'Aucune catégorie trouvée, choisissez "Autre"',
                shouldSort: false 
            });
        }

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
</script>
</body>
</html>