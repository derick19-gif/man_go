<?php

class ListingController {

    // Affiche la liste globale des annonces
    public function index() {
        // Correction : on récupère directement la connexion selon la structure standard
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

        // Charge la vue affichant la liste des annonces
        require_once __DIR__ . '/../../listings.php';
    }

    // Affiche le formulaire de publication (avec vérification Connexion & KYC)
    public function create() {
        if (!Session::get('user_id')) {
            header('Location: ' . BASE_URL . '/login.php?redirect=publish');
            exit;
        }

        $db = Database::getInstance();
        if (method_exists($db, 'getConnection')) {
            $db = $db->getConnection();
        }

        $stmtUser = $db->prepare("SELECT kyc_status FROM users WHERE id = ?");
        $stmtUser->execute([Session::get('user_id')]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user || ($user['kyc_status'] ?? '') !== 'approved') {
            $_SESSION['flash_error'] = "Vous devez valider votre vérification KYC pour publier une annonce.";
            header('Location: ' . BASE_URL . '/kyc/verify');
            exit;
        }

        try {
            $stmtCats = $db->query("SELECT id, name FROM categories ORDER BY name ASC");
            $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $categories = [];
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        require_once __DIR__ . '/../views/listings/create.php';
    }

    // Traitement de l'enregistrement en POST de l'annonce
    public function store() {
        if (!Session::get('user_id')) {
            header('Location: ' . BASE_URL . '/login.php');
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

        $title       = trim($_POST['title'] ?? '');
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $price       = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $city        = trim($_POST['city'] ?? 'Lomé');
        $description = trim($_POST['description'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');

        if (empty($title)) $errors[] = "Le titre est obligatoire.";
        if (!$category_id) $errors[] = "Catégorie invalide.";
        if ($price === false || $price < 0) $errors[] = "Prix invalide.";

        if (empty($errors)) {
            try {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))) . '-' . substr(md5(uniqid()), 0, 6);
                
                $sql = "INSERT INTO listings (user_id, category_id, title, slug, description, price, city, phone, status, created_at) 
                        VALUES (:user_id, :category_id, :title, :slug, :description, :price, :city, :phone, 'active', NOW())";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':user_id'     => Session::get('user_id'),
                    ':category_id' => $category_id,
                    ':title'       => $title,
                    ':slug'        => $slug,
                    ':description' => $description,
                    ':price'       => $price,
                    ':city'        => $city,
                    ':phone'       => $phone
                ]);

                $new_id = $db->lastInsertId();
                header("Location: " . BASE_URL . "/listings/" . $new_id);
                exit;

            } catch (Exception $e) {
                $errors[] = "Erreur technique : " . $e->getMessage();
            }
        }

        $_SESSION['form_errors'] = $errors;
        header('Location: ' . BASE_URL . '/publish');
        exit;
    }
}