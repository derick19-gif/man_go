<?php

use App\Core\Database;

// Inclusions de sécurité pour garantir que les classes existent
$countriesPath = __DIR__ . '/../../core/Countries.php';
if (file_exists($countriesPath)) require_once $countriesPath;

$notifPath = __DIR__ . '/../../includes/NotificationManager.php';
if (file_exists($notifPath)) require_once $notifPath;

class ListingController {

    // ---------------------------------------------------------
    // Affiche la liste globale des annonces
    // ---------------------------------------------------------
    public function index() {
        $db = Database::getInstance();
        if (method_exists($db, 'getConnection')) {
            $db = $db->getConnection();
        }
        
        try {
            $stmt = $db->query("SELECT l.*, c.name as category_name FROM listings l LEFT JOIN categories c ON l.category_id = c.id ORDER BY l.created_at DESC");
            $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $listings = [];
        }

        require_once __DIR__ . '/../../listings.php';
    }

    // ---------------------------------------------------------
    // Affiche le formulaire de publication (Vue)
    // ---------------------------------------------------------
    public function create() {
        if (!Session::get('user_id')) {
            header('Location: ' . (defined('APP_URL') ? APP_URL : '') . '/login.php?redirect=publish');
            exit;
        }

        $db = Database::getInstance();
        if (method_exists($db, 'getConnection')) {
            $db = $db->getConnection();
        }

        // Vérification KYC
        $stmtUser = $db->prepare("SELECT kyc_status FROM users WHERE id = ?");
        $stmtUser->execute([Session::get('user_id')]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

       if (!$user || strtoupper($user['kyc_status'] ?? '') !== 'APPROVED') {
            $_SESSION['flash_error'] = "Vous devez valider votre vérification KYC pour publier une annonce.";
            header('Location: ' . (defined('APP_URL') ? APP_URL : '') . '/kyc/verify');
            exit;
        }

        // Récupération des catégories
        try {
            $stmtCats = $db->query("SELECT id, name FROM categories ORDER BY name ASC");
            $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $categories = [];
        }

        // Jeton CSRF
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        require_once __DIR__ . '/../views/listings/create.php';
    }

    // ---------------------------------------------------------
    // Traitement de l'enregistrement en POST de l'annonce
    // ---------------------------------------------------------
    public function store() {
        if (!Session::get('user_id')) {
            header('Location: ' . (defined('APP_URL') ? APP_URL : '') . '/login.php');
            exit;
        }

        $db = Database::getInstance();
        if (method_exists($db, 'getConnection')) {
            $db = $db->getConnection();
        }
        
        $errors = [];

        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $errors[] = "Requête invalide (Session expirée).";
        }

        // 1. Récupération des données de base
        $title          = trim($_POST['title'] ?? '');
        $category_id    = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $price_input    = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $orig_input     = filter_var($_POST['original_price'] ?? null, FILTER_VALIDATE_FLOAT);
        $currency       = trim($_POST['currency'] ?? 'FCFA');
        $description    = trim($_POST['description'] ?? '');
        $dial_code      = trim($_POST['dial_code'] ?? '+228');
        $phone_raw      = trim($_POST['phone'] ?? '');

        // Logique des prix et promo
        $price = $price_input;
        $original_price = ($orig_input !== false && $orig_input !== null && $orig_input > $price_input) ? $orig_input : null;

        // Numéro de téléphone (Robuste)
        $full_phone = $phone_raw;
        if (class_exists('Countries') && !empty($phone_raw)) {
            $full_phone = Countries::formatPhone($dial_code, $phone_raw);
        } elseif(!empty($phone_raw)) {
            $full_phone = $dial_code . ' ' . $phone_raw;
        }

        // 2. Nouvelles données GPS
        $city         = trim($_POST['city'] ?? '');
        $country      = trim($_POST['country'] ?? 'Togo');
        $street       = trim($_POST['street'] ?? '');
        $neighborhood = trim($_POST['neighborhood'] ?? '');
        $latitude     = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude    = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

        // Validations métier
        if (empty($title)) $errors[] = "Le titre est obligatoire.";
        if (!$category_id) $errors[] = "Catégorie invalide.";
        if ($price_input === false || $price_input < 0) $errors[] = "Prix invalide.";
        if (empty($phone_raw)) $errors[] = "Le numéro de téléphone est obligatoire.";

        // 3. Traitement de l'image
        $image_url = 'assets/images/placeholder.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath   = $_FILES['image']['tmp_name'];
            $fileName      = $_FILES['image']['name'];
            $fileSize      = $_FILES['image']['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($fileExtension, $allowedExtensions, true)) {
                if ($fileSize <= 5 * 1024 * 1024) { // Max 5 Mo
                    $uploadDir = __DIR__ . '/../../uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    $destPath    = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $image_url = 'uploads/' . $newFileName;
                    } else {
                        $errors[] = "Erreur lors de l'enregistrement de l'image.";
                    }
                } else {
                    $errors[] = "L'image dépasse 5 Mo.";
                }
            } else {
                $errors[] = "Format d'image non supporté (JPG, PNG, WEBP).";
            }
        }

        // 4. Insertion en Base de Données
        if (empty($errors)) {
            try {
                // Génération propre du slug
                $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
                $slug = $baseSlug . '-' . substr(md5(uniqid()), 0, 6);
                
                $sql = "INSERT INTO listings 
                        (user_id, category_id, title, slug, description, price, original_price, currency, image_url, phone, status, created_at, city, country, street, neighborhood, latitude, longitude) 
                        VALUES 
                        (:user_id, :category_id, :title, :slug, :description, :price, :original_price, :currency, :image_url, :phone, 'active', NOW(), :city, :country, :street, :neighborhood, :lat, :lng)";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':user_id'        => Session::get('user_id'),
                    ':category_id'    => $category_id,
                    ':title'          => $title,
                    ':slug'           => $slug,
                    ':description'    => $description,
                    ':price'          => $price,
                    ':original_price' => $original_price,
                    ':currency'       => $currency,
                    ':image_url'      => $image_url,
                    ':phone'          => $full_phone,
                    ':city'           => $city,
                    ':country'        => $country,
                    ':street'         => $street,
                    ':neighborhood'   => $neighborhood,
                    ':lat'            => $latitude,
                    ':lng'            => $longitude
                ]);

                $new_id = $db->lastInsertId();

                // 5. Notification
                if (class_exists('NotificationManager')) {
                    $notifManager = new NotificationManager($db);
                    $notifManager->createNotification(
                        Session::get('user_id'),
                        "Annonce publiée avec succès !",
                        "Votre annonce « " . htmlspecialchars($title) . " » est en ligne sur MAN GO.",
                        "success",
                        "listing-detail.php?id=" . $new_id
                    );
                }

                header("Location: " . (defined('APP_URL') ? APP_URL : '') . "/listings/" . $new_id);
                exit;

            } catch (Exception $e) {
                $errors[] = "Erreur technique : " . $e->getMessage();
            }
        }

        $_SESSION['form_errors'] = $errors;
        header('Location: ' . (defined('APP_URL') ? APP_URL : '') . '/publish');
        exit;
    }

    // ---------------------------------------------------------
    // NOUVEAU : Affiche les détails d'une annonce spécifique (Vue)
    // ---------------------------------------------------------
    public function show() {
        // 1. Récupérer l'ID de l'annonce depuis l'URL (?id=X)
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        $baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

        if (!$id) {
            header("Location: $baseUrl/"); // Retour à l'accueil si pas d'ID
            exit;
        }

        try {
            $db = Database::getInstance();
            if (method_exists($db, 'getConnection')) {
                $db = $db->getConnection();
            }
            
            // 2. Requête SQL pour récupérer l'annonce + les infos du vendeur + la catégorie
            $stmt = $db->prepare("
                SELECT l.*, 
                       c.name AS category_name,
                       u.firstname, u.lastname, u.avatar, u.phone AS vendor_phone, u.email, u.created_at as vendor_since
                FROM listings l
                LEFT JOIN categories c ON l.category_id = c.id
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.id = :id AND l.status = 'ACTIVE'
            ");
            $stmt->execute([':id' => $id]);
            $listing = $stmt->fetch(PDO::FETCH_ASSOC);

            // Si l'annonce n'existe pas ou n'est plus active
            if (!$listing) {
                header("Location: $baseUrl/?error=not_found");
                exit;
            }

            // 3. Titre de la page pour le SEO
            $pageTitle = $listing['title'] . " - MAN GO";
            
            // 4. Chargement de la vue (Le HTML que vous allez créer ensuite)
            $viewPath = __DIR__ . '/../views/listing_detail.php';
            if (file_exists($viewPath)) {
                require_once $viewPath;
            } else {
                echo "Erreur : La vue listing_detail.php est introuvable.";
            }

        } catch (Exception $e) {
            die("Erreur système : " . $e->getMessage());
        }
    }
}

