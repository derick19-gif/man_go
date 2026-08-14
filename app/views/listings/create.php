<?php
// publish.php

// Chargement sécurisé de la configuration globale
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/core/config.php')) {
    require_once __DIR__ . '/core/config.php';
}

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Countries.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/includes/NotificationManager.php';

Session::init();

// 1. Restriction d'accès (Utilisateur connecté uniquement)
if (!Session::get('user_id')) {
    header('Location: login.php?redirect=publish.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$errors = [];

// 2. Génération / Vérification du Jeton CSRF pour la sécurité
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Récupération des catégories
try {
    $stmtCats = $db->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Générateur de Slug pour URL propre
function generateSlug(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'annonce-' . time() : $text;
}

// 4. Traitement du formulaire POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verification CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Ressource expirée ou requête invalide. Veuillez réessayer.";
    }

    $title       = trim($_POST['title'] ?? '');
    $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
    $price_input = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $orig_input  = filter_var($_POST['original_price'] ?? null, FILTER_VALIDATE_FLOAT);
    $currency    = trim($_POST['currency'] ?? 'FCFA');
    $city        = trim($_POST['city'] ?? 'Lomé');
    $dial_code   = trim($_POST['dial_code'] ?? '+228');
    $phone_raw   = trim($_POST['phone'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validations métier
    if (empty($title)) {
        $errors[] = "Le titre de l'annonce est obligatoire.";
    }
    if (!$category_id) {
        $errors[] = "Veuillez sélectionner une catégorie valide.";
    }
    if ($price_input === false || $price_input < 0) {
        $errors[] = "Veuillez saisir un prix valide.";
    }
    if (empty($phone_raw)) {
        $errors[] = "Le numéro de téléphone est obligatoire.";
    }

    $full_phone = Countries::formatPhone($dial_code, $phone_raw);
    $price = $price_input;
    $original_price = ($orig_input !== false && $orig_input !== null && $orig_input > $price_input) ? $orig_input : null;

    // Traitement de l'image
    $image_url = 'assets/images/placeholder.jpg';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['image']['tmp_name'];
        $fileName      = $_FILES['image']['name'];
        $fileSize      = $_FILES['image']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($fileExtension, $allowedExtensions, true)) {
            if ($fileSize <= 5 * 1024 * 1024) { // Max 5 Mo
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $destPath    = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $image_url = 'uploads/' . $newFileName;
                } else {
                    $errors[] = "Erreur lors de l'enregistrement de l'image sur le serveur.";
                }
            } else {
                $errors[] = "L'image dépasse la taille maximale autorisée (5 Mo).";
            }
        } else {
            $errors[] = "Format d'image non supporté (Uniquement JPG, PNG, WEBP).";
        }
    }

    // Sauvegarde en Base de Données
    if (empty($errors)) {
        try {
            $baseSlug = generateSlug($title);
            $slug     = $baseSlug . '-' . substr(md5(uniqid((string)rand(), true)), 0, 6);
            $user_id  = Session::get('user_id');

            $sql = "INSERT INTO listings 
                    (user_id, category_id, title, slug, description, price, original_price, currency, city, phone, image_url, status, created_at) 
                    VALUES 
                    (:user_id, :category_id, :title, :slug, :description, :price, :original_price, :currency, :city, :phone, :image_url, 'active', NOW())";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':user_id'        => $user_id,
                ':category_id'    => $category_id,
                ':title'          => $title,
                ':slug'           => $slug,
                ':description'    => $description,
                ':price'          => $price,
                ':original_price' => $original_price,
                ':currency'       => $currency,
                ':city'           => $city,
                ':phone'          => $full_phone,
                ':image_url'      => $image_url
            ]);

            $new_id = $db->lastInsertId();

            // Création de la notification utilisateur
            $notifManager = new NotificationManager($db);
            $notifManager->createNotification(
                $user_id,
                "Annonce publiée avec succès !",
                "Votre annonce « " . htmlspecialchars($title) . " » est en ligne sur MAN GO.",
                "success",
                "listing-detail.php?id=" . $new_id
            );

            // Redirection vers l'annonce
            header("Location: listing-detail.php?id=" . $new_id);
            exit;

        } catch (PDOException $e) {
            $errors[] = "Erreur technique lors de la publication : " . $e->getMessage();
        }
    }
}

// Inclusion de l'en-tête
if (file_exists(__DIR__ . '/includes/header.php')) {
    include __DIR__ . '/includes/header.php';
} else {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publier une annonce - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">
    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-20 flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-2">
                <span class="bg-amber-500 text-slate-900 font-black text-2xl w-10 h-10 rounded-full flex items-center justify-center">M</span>
                <span class="font-extrabold text-2xl">MAN <span class="text-amber-500">GO</span></span>
            </a>
            <a href="dashboard.php" class="text-sm font-medium hover:text-amber-500">Mon Compte</a>
        </div>
    </header>
<?php } ?>

<main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full flex-1">
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-gray-900">Publier une nouvelle annonce</h1>
        <p class="text-gray-500 text-sm mt-1">Saisissez les informations relatives à votre produit ou service.</p>
    </div>

    <!-- Affichage des Erreurs -->
    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
            <div class="font-bold mb-1 flex items-center">
                <i class="fa-solid fa-circle-exclamation mr-2"></i> Veuillez corriger les erreurs suivantes :
            </div>
            <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>/publish" method="POST" enctype="multipart/form-data" class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-200 shadow-sm space-y-6">
        
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <!-- Titre -->
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Titre de l'annonce <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="ex: iPhone 13 Pro Max 256Go comme neuf" required class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
        </div>

        <!-- Catégorie et Ville -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Catégorie <span class="text-red-500">*</span></label>
                <select name="category_id" required class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm bg-white">
                    <option value="">-- Sélectionner une catégorie --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Ville</label>
                <input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? 'Lomé') ?>" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
            </div>
        </div>

        <!-- Téléphone avec Indicatif -->
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Téléphone de contact <span class="text-red-500">*</span></label>
            <div class="flex gap-2">
                <select name="dial_code" class="w-1/3 sm:w-1/4 px-3 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm bg-white">
                    <?= Countries::renderSelectOptions($_POST['dial_code'] ?? '+228') ?>
                </select>
                <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="90 00 00 00" required class="flex-1 px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
            </div>
        </div>

        <!-- Bloc Prix & Promotion -->
        <div class="bg-amber-50/50 p-5 rounded-xl border border-amber-200/80 space-y-4">
            <div class="flex items-center space-x-2 text-amber-800 font-bold text-sm">
                <i class="fa-solid fa-tags text-amber-500"></i>
                <span>Tarification et Promotion</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Prix de vente <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="price" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" placeholder="150000" required class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Prix barré <span class="text-gray-400 font-normal">(Optionnel)</span></label>
                    <input type="number" step="0.01" min="0" name="original_price" value="<?= htmlspecialchars($_POST['original_price'] ?? '') ?>" placeholder="180000" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Devise</label>
                    <select name="currency" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm bg-white">
                        <option value="FCFA" <?= (($_POST['currency'] ?? 'FCFA') === 'FCFA') ? 'selected' : '' ?>>FCFA</option>
                        <option value="EUR" <?= (($_POST['currency'] ?? '') === 'EUR') ? 'selected' : '' ?>>EUR (€)</option>
                        <option value="USD" <?= (($_POST['currency'] ?? '') === 'USD') ? 'selected' : '' ?>>USD ($)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Téléversement d'Image avec aperçu -->
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Photo principale de l'annonce</label>
            <input type="file" id="imageInput" name="image" accept="image/png, image/jpeg, image/webp" class="w-full border border-gray-300 rounded-xl p-2 text-sm bg-white">
            <div id="imagePreviewContainer" class="mt-3 hidden">
                <p class="text-xs text-gray-500 mb-1">Aperçu :</p>
                <img id="imagePreview" src="#" alt="Aperçu" class="h-32 w-32 object-cover rounded-xl border border-gray-200 shadow-sm">
            </div>
        </div>

        <!-- Description -->
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Description détaillée <span class="text-red-500">*</span></label>
            <textarea name="description" rows="5" placeholder="Décrivez votre article en détail (état, caractéristiques, accessoires inclus...)" required class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <!-- Bouton Soumettre -->
        <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold py-3.5 px-6 rounded-xl shadow-md transition transform hover:-translate-y-0.5 text-base flex items-center justify-center space-x-2 cursor-pointer">
            <i class="fa-solid fa-paper-plane"></i>
            <span>Publier mon annonce maintenant</span>
        </button>
    </form>
</main>

<script>
document.getElementById('imageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('imagePreview').src = event.target.result;
            document.getElementById('imagePreviewContainer').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php
if (file_exists(__DIR__ . '/includes/footer.php')) {
    include __DIR__ . '/includes/footer.php';
} else {
?>
    <footer class="bg-slate-900 text-gray-400 py-6 mt-12 text-center text-xs border-t border-slate-800">
        &copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.
    </footer>
</body>
</html>
<?php } ?>