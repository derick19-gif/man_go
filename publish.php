<?php
// =========================================================================
// Page de Publication & Édition - MAN GO Marketplace
// =========================================================================

if (!defined('APP_PATH')) {
    define('APP_PATH', __DIR__);
}

// NE SURTOUT PAS SUPPRIMER CETTE LIGNE :
require_once __DIR__ . '/config/config.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// 1. Sécurité : Si l'utilisateur n'est PAS connecté du tout
if (empty($_SESSION['user_id'])) {
    header("Location: $baseUrl/login.php?redirect=publish.php");
    exit();
}

// 2. Sécurité : Si l'utilisateur est connecté mais n'est PAS un vendeur
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['vendor', 'vendeur', '4'])) { // J'ajoute '4' au cas où vous utiliseriez des ID pour les rôles
    // Redirige vers le tableau de bord client avec un message d'erreur
    header("Location: $baseUrl/client/views/dashboard.php?error=not_vendor");
    exit();
}

require_once __DIR__ . '/core/Database.php';
$db = \App\Core\Database::connect();
$successMessage = ""; $errorMessage = "";

// Vérification du statut Premium du vendeur
$stmtUser =$db->prepare("SELECT is_premium FROM users WHERE id = :id");
$stmtUser->execute([':id' => $_SESSION['user_id']]);$userObj = $stmtUser->fetch(PDO::FETCH_ASSOC);$isPremium = !empty($userObj['is_premium']) ? (bool)$userObj['is_premium'] : false;

// Mode Édition
$editMode = false;
$listingId = $_GET['id'] ?? $_POST['listing_id'] ?? null;
$existingData = [];

if ($listingId) {
    $stmtEdit =$db->prepare("SELECT * FROM listings WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmtEdit->execute([':id' => $listingId, ':uid' =>$_SESSION['user_id']]);
    $existingData =$stmtEdit->fetch(PDO::FETCH_ASSOC);
    if ($existingData)$editMode = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmtStand =$db->prepare("SELECT id FROM stands WHERE user_id = :user_id AND status = 'active' LIMIT 1");
        $stmtStand->execute([':user_id' =>$_SESSION['user_id']]);
        $stand =$stmtStand->fetch(PDO::FETCH_ASSOC);
        
        if (!$stand) {$errorMessage = "Opération refusée : Vous devez d'abord créer et activer votre Stand Officiel.";
        } else {
            $standId = $stand['id'];$title = trim($_POST['title'] ?? '');$categoryId = intval($_POST['category_id'] ?? 0);$price = floatval($_POST['price'] ?? 0);$originalPrice = !empty($_POST['original_price']) ? floatval($_POST['original_price']) : null;
            $description = trim($_POST['description'] ?? '');
            $webLink = trim($_POST['web_link'] ?? '');
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-',$title), '-'));

            // LOGIQUE DE PROGRAMMATION PREMIUM
            $scheduledAt = null;
            $status = 'active'; // Par défaut
            if ($isPremium && !empty($_POST['scheduled_at'])) {
                $scheduledAt =$_POST['scheduled_at'];
                if (strtotime($scheduledAt) > time()) {$status = 'scheduled'; // Annonce en attente de sa date
                }
            }

            // GESTION MULTI-IMAGES
            $mainImagePath = $editMode ? $existingData['image_path'] : null;
            $uploadedImages = [];
            
            if (!empty($_FILES['images']['name'][0])) {$uploadDir = __DIR__ . '/public/uploads/listings/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                foreach ($_FILES['images']['name'] as $key =>$name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                            $fileName = 'img_' . time() . '_' . uniqid() . '.' . $ext;
                            if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $uploadDir .$fileName)) {
                                $uploadedImages[] = 'public/uploads/listings/' .$fileName;
                            }
                        }
                    }
                }
            }

            if (!empty($uploadedImages)) $mainImagePath =$uploadedImages[0]; 

            if (empty($title) || empty($categoryId) || empty($description)) {$errorMessage = "Veuillez remplir tous les champs obligatoires.";
            } else {
                if ($editMode) {
                    $stmt =$db->prepare("
                        UPDATE listings 
                        SET category_id=:cat, title=:title, slug=:slug, price=:price, original_price=:orig, description=:desc, web_link=:web, image_path=:img, scheduled_at=:sched
                        WHERE id=:id AND user_id=:uid
                    ");
                    $stmt->execute([
                        ':cat' => $categoryId, ':title' => $title, ':slug' =>$slug, 
                        ':price' => $price, ':orig' => $originalPrice, ':desc' =>$description, 
                        ':web' => $webLink, ':img' => $mainImagePath, ':sched' =>$scheduledAt, ':id' => $listingId, ':uid' =>$_SESSION['user_id']
                    ]);
                    $successMessage = "Votre annonce a été modifiée avec succès !";
                    $existingData = array_merge($existingData,$_POST);
                } else {
                    $stmt =$db->prepare("
                        INSERT INTO listings (user_id, stand_id, category_id, title, slug, price, original_price, description, web_link, image_path, status, scheduled_at, created_at) 
                        VALUES (:user_id, :stand_id, :category_id, :title, :slug, :price, :original_price, :description, :web_link, :image_path, :status, :scheduled_at, NOW())
                    ");
                    $stmt->execute([
                        ':user_id' => $_SESSION['user_id'], ':stand_id' => $standId, ':category_id' =>$categoryId,
                        ':title' => $title, ':slug' =>$slug, ':price' => $price, ':original_price' =>$originalPrice,
                        ':description' => $description, ':web_link' => $webLink, ':image_path' =>$mainImagePath, 
                        ':status' => $status, ':scheduled_at' =>$scheduledAt
                    ]);
                    $listingId = $db->lastInsertId();$successMessage = "Votre annonce a été mise en ligne avec succès !";
                }

                if (count($uploadedImages) > 1) {
                    if ($editMode) $db->prepare("DELETE FROM listing_images WHERE listing_id = ?")->execute([$listingId]);
                    $stmtGallery =$db->prepare("INSERT INTO listing_images (listing_id, image_path) VALUES (?, ?)");
                    for ($i = 1; $i < count($uploadedImages);$i++) {
                        $stmtGallery->execute([$listingId, $uploadedImages[$i]]);
                    }
                }
            }
        }
    } catch (PDOException $e) {$errorMessage = "Erreur SQL : " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $editMode ? 'Modifier' : 'Publier' ?> une annonce - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">
<?php 
$headerPath = __DIR__ . '/app/views/layouts/header.php';
if (file_exists($headerPath)) require_once$headerPath;
?>

<main class="flex-1 max-w-3xl mx-auto px-4 py-12 w-full">
    <div class="mb-8 text-center">
        <span class="bg-amber-100 text-amber-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase">Espace Vendeur</span>
        <h1 class="text-3xl font-black text-slate-900 mt-2"><?= $editMode ? 'Modifier votre annonce' : 'Publier sur MAN GO' ?></h1>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-2xl text-sm font-bold flex items-center shadow-sm">
            <i class="fa-solid fa-circle-check text-xl mr-3"></i> <?= $successMessage ?>
        </div>
    <?php endif; ?>

    <form action="publish.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm space-y-6">
        <?php if($editMode): ?><input type="hidden" name="listing_id" value="<?= $listingId ?>"><?php endif; ?>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Titre de l'annonce *</label>
            <input type="text" name="title" value="<?= htmlspecialchars($existingData['title'] ?? '') ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Catégorie *</label>
                <select name="category_id" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                    <option value="">Sélectionner</option>
                    <option value="1" <?= (($existingData['category_id']??0) == 1) ? 'selected' : '' ?>>Électronique & High-Tech</option>
                    <option value="2" <?= (($existingData['category_id']??0) == 2) ? 'selected' : '' ?>>Services Pro</option>
                    <option value="3" <?= (($existingData['category_id']??0) == 3) ? 'selected' : '' ?>>Immobilier & Foncier</option>
                    <option value="4" <?= (($existingData['category_id']??0) == 4) ? 'selected' : '' ?>>Mode & Style</option>
                    <option value="5" <?= (($existingData['category_id']??0) == 5) ? 'selected' : '' ?>>Véhicules</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Prix de vente *</label>
                <div class="relative">
                    <input type="text" name="price" value="<?= htmlspecialchars($existingData['price'] ?? '') ?>" required oninput="this.value = this.value.replace(/[^0-9]/g, '');" class="w-full px-4 py-3 pr-16 rounded-xl border border-slate-300 text-sm">
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none"><span class="text-gray-400 font-black text-xs"><?= htmlspecialchars($_SESSION['user_currency'] ?? 'FCFA') ?></span></div>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Ancien Prix</label>
                <div class="relative">
                    <input type="text" name="original_price" value="<?= htmlspecialchars($existingData['original_price'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '');" class="w-full px-4 py-3 pr-16 rounded-xl border border-slate-300 text-sm">
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none"><span class="text-gray-400 font-black text-xs"><?= htmlspecialchars($_SESSION['user_currency'] ?? 'FCFA') ?></span></div>
                </div>
            </div>
        </div>

        <!-- MODULE DE PROGRAMMATION (PREMIUM) -->
        <div class="p-5 border <?= $isPremium ? 'border-amber-400 bg-amber-50' : 'border-gray-200 bg-gray-50 opacity-80' ?> rounded-2xl">
            <div class="flex items-center justify-between mb-3">
                <label class="block text-xs font-bold text-slate-900 uppercase">
                    <i class="fa-solid fa-clock text-amber-500 mr-2"></i> Programmation de l'annonce
                </label>
                <?php if (!$isPremium): ?>
                    <span class="bg-slate-900 text-white text-[10px] px-2 py-1 rounded font-bold uppercase"><i class="fa-solid fa-lock text-amber-500 mr-1"></i> Premium</span>
                <?php endif; ?>
            </div>
            <input type="datetime-local" name="scheduled_at" value="<?= htmlspecialchars($existingData['scheduled_at'] ?? '') ?>" <?= $isPremium ? '' : 'disabled' ?> class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
            <?php if (!$isPremium): ?>
                <p class="text-xs text-slate-500 mt-2 font-medium">Abonnez-vous au forfait Premium pour programmer vos annonces à une date ultérieure.</p>
            <?php endif; ?>
        </div>

        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
            <label class="block text-xs font-bold text-slate-700 uppercase mb-3">Photos multiples</label>
            <input type="file" name="images[]" id="imagesInput" multiple accept="image/*" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm bg-white cursor-pointer">
            <div id="image-preview-container" class="flex flex-wrap gap-4 mt-4 empty:mt-0"></div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Description détaillée *</label>
            <textarea name="description" rows="5" required class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm"><?= htmlspecialchars($existingData['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="w-full bg-slate-900 hover:bg-amber-500 text-white hover:text-slate-900 font-black py-4 rounded-xl transition text-sm uppercase">
            <?= $editMode ? 'Enregistrer les modifications' : 'Mettre en ligne mon annonce' ?>
        </button>
    </form>
</main>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
                div.className = 'relative w-24 h-24 rounded-xl overflow-hidden shadow-sm border border-slate-200 group';
                div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">
                                 <button type="button" onclick="removeFile(${i})" class="absolute top-1 right-1 bg-red-600 text-white w-6 h-6 rounded-full opacity-0 group-hover:opacity-100 transition text-xs font-bold"><i class="fa-solid fa-xmark"></i></button>`;
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