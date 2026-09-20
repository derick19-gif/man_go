<?php
// modules/stand/controllers/StandController.php

use App\Core\Controller;

require_once __DIR__ . '/../models/Stand.php';

class StandController extends Controller {

    public function index() {
        $standModel = new Stand();
        $search   = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $location = trim(filter_input(INPUT_GET, 'location', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $category = trim(filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

        $stands = method_exists($standModel, 'getActiveStands') ? $standModel->getActiveStands($search, $location, $category) : [];

        echo $this->render('index', [
            'stands'      => $stands,
            'search'      => $search,
            'location'    => $location,
            'category'    => $category
        ]);
    }

    public function detail() {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $standModel = new Stand();
        $stand = $standModel->find($id);

        if (!$stand) {
            http_response_code(404);
            echo "Boutique introuvable.";
            return;
        }

        $listings = $standModel->getStandListings($id);

        echo $this->render('detail', [
            'stand'    => $stand,
            'listings' => $listings
        ]);
    }

    public function create() {
        if (class_exists('Session') && !\Session::isAuthenticated()) {
            header('Location: ../login');
            exit;
        }

        $userId = class_exists('Session') ? \Session::getUserId() : ($_SESSION['user_id'] ?? 1);

        $dbInstance = \App\Core\Database::getInstance();
        $db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;
        
        $stmt = $db->prepare("SELECT * FROM stands WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $existingStand = $stmt->fetch(\PDO::FETCH_ASSOC);

        echo $this->render('create', ['existingStand' => $existingStand]);
    }

    public function store() {
        if (class_exists('Session') && !\Session::isAuthenticated()) {
            header('Location: ../login');
            exit;
        }

        $userId = class_exists('Session') ? \Session::getUserId() : ($_SESSION['user_id'] ?? 1);
        
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $city        = trim($_POST['city'] ?? '');
        $address     = trim($_POST['address'] ?? ''); 
        $phone       = trim($_POST['phone'] ?? '');
        $website     = trim($_POST['website'] ?? '');

        if (empty($address) && isset($_POST['coverage_zone'])) {
             $address = trim($_POST['coverage_zone']);
        }

        // Création du SLUG obligatoire (ex: "Cabinet Notarial" devient "cabinet-notarial-12")
        // On ajoute l'ID utilisateur à la fin pour garantir que le slug est UNIQUE
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)) . '-' . $userId;
        $slug = trim($slug, '-');

        // Connexion directe
        $dbInstance = \App\Core\Database::getInstance();
        $db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;
        
        // Mode strict pour voir les erreurs s'il en reste !
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $stmtCheck = $db->prepare("SELECT id, logo FROM stands WHERE user_id = ? LIMIT 1");
            $stmtCheck->execute([$userId]);
            $existingStand = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            // --- VERIFICATION ET UPLOAD DU LOGO ---
            $logoFileName = null;
            $hasUploadedLogo = isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK;

            if (!$existingStand && !$hasUploadedLogo) {
                header('Location: create?error=logo_required');
                exit;
            }

            if ($hasUploadedLogo) {
                $tmpName = $_FILES['logo']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $allowedExtensions)) {
                    header('Location: create?error=invalid_format');
                    exit;
                }

                $logoFileName = 'logo_' . $userId . '_' . time() . '.' . $ext; 
                
                $uploadDir = dirname(dirname(dirname(__DIR__))) . '/uploads/stands/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                move_uploaded_file($tmpName, $uploadDir . $logoFileName);
            }

            if ($existingStand) {
                // --- MISE À JOUR ---
                $existingId = $existingStand['id'];
                $finalLogo = $logoFileName ? $logoFileName : ($existingStand['logo'] ?? 'default-shop.png');
                
                $sql = "UPDATE stands SET description = ?, city = ?, address = ?, phone = ?, website = ?, logo = ?, updated_at = NOW() WHERE id = ?";
                $stmtUpdate = $db->prepare($sql);
                $stmtUpdate->execute([$description, $city, $address, $phone, $website, $finalLogo, $existingId]);
                
                header('Location: create?msg=updated');
                exit;
            } else {
                // --- CRÉATION ---
                $finalLogo = $logoFileName; 
                
                // On inclut bien le "slug", "category", "city", etc.
                $sql = "INSERT INTO stands (user_id, name, slug, description, category, city, address, phone, website, logo, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', NOW())";
                $stmtInsert = $db->prepare($sql);
                $stmtInsert->execute([$userId, $name, $slug, $description, $category, $city, $address, $phone, $website, $finalLogo]);

                header('Location: create?msg=created');
                exit;
            }

        } catch (\PDOException $e) {
            die("<div style='background:#111; color:white; padding:30px; font-family:sans-serif;'>
                    <h2 style='color:#EF4444;'>🚨 ERREUR SQL 🚨</h2>
                    <pre style='color:#10B981; font-size:16px;'>".$e->getMessage()."</pre>
                 </div>");
        }
    }
}