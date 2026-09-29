<?php
// modules/stand/controllers/StandController.php

use App\Core\Controller;

require_once __DIR__ . '/../models/Stand.php';

class StandController extends Controller {

    public function index() {
        $search   = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $location = trim(filter_input(INPUT_GET, 'location', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $category = trim(filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

        $dbInstance = \App\Core\Database::getInstance();
        $db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;

        // CORRECTION : Ajout de la sous-requête pour compter les annonces (listings) actives du vendeur
        $sql = "SELECT s.*, 
                       CONCAT(u.firstname, ' ', u.lastname) as vendor_name, 
                       u.is_premium,
                       (SELECT COUNT(*) FROM listings l WHERE l.user_id = s.user_id AND LOWER(l.status) = 'active') as total_annonces
                FROM stands s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE LOWER(s.status) = 'active'";
        
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (s.name LIKE :search OR s.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        if (!empty($location)) {
            $sql .= " AND (s.city LIKE :location OR s.address LIKE :location)";
            $params[':location'] = '%' . $location . '%';
        }
        if (!empty($category)) {
            $sql .= " AND s.category = :category";
            $params[':category'] = $category;
        }

        $sql .= " ORDER BY u.is_premium DESC, s.created_at DESC"; 

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $stands = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $stands = [];
        }

        $totalStands = count($stands);

        echo $this->render('index', [
            'stands'      => $stands,
            'totalStands' => $totalStands,
            'search'      => $search,
            'location'    => $location,
            'category'    => $category
        ]);
    }

    public function detail() {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        
        if (!$id) {
            http_response_code(404);
            echo "Lien de la boutique invalide.";
            return;
        }

        // Connexion directe et forcée à la base de données
        $dbInstance = \App\Core\Database::getInstance();
        $db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            // 1. Récupération du Stand (avec la bonne concaténation du nom)
            $stmt = $db->prepare("SELECT s.*, CONCAT(u.firstname, ' ', u.lastname) as vendor_name, u.phone as vendor_phone, u.is_premium 
                                  FROM stands s 
                                  LEFT JOIN users u ON s.user_id = u.id 
                                  WHERE s.id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $stand = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$stand) {
                http_response_code(404);
                echo "<div style='padding:50px; text-align:center; font-family:sans-serif;'>
                        <h2 style='color:#0f172a;'>Cette boutique est introuvable ou a été désactivée.</h2>
                        <br><a href='../stands' style='padding:10px 20px; background:#f59e0b; color:white; text-decoration:none; border-radius:8px;'>Retour aux boutiques</a>
                      </div>";
                return;
            }

            // 2. Récupération des annonces du vendeur (liées par le user_id)
            $stmtListings = $db->prepare("SELECT * FROM listings WHERE user_id = :user_id AND LOWER(status) = 'active' ORDER BY created_at DESC");
            $stmtListings->execute([':user_id' => $stand['user_id']]);
            $listings = $stmtListings->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Envoi à la vue
            echo $this->render('detail', [
                'stand'    => $stand,
                'listings' => $listings
            ]);

        } catch (\PDOException $e) {
            // Si une colonne manque, l'erreur s'affichera clairement !
            die("<div style='background:#111; color:white; padding:20px; border-left:8px solid red; font-family:sans-serif;'>
                    <h3 style='color:red;'>🚨 ERREUR SQL DANS DETAIL()</h3>
                    <p>" . $e->getMessage() . "</p>
                 </div>");
        }
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

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)) . '-' . $userId;
        $slug = trim($slug, '-');

        $dbInstance = \App\Core\Database::getInstance();
        $db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $stmtCheck = $db->prepare("SELECT id, logo FROM stands WHERE user_id = ? LIMIT 1");
            $stmtCheck->execute([$userId]);
            $existingStand = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

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
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                move_uploaded_file($tmpName, $uploadDir . $logoFileName);
            }

            if ($existingStand) {
                $existingId = $existingStand['id'];
                $finalLogo = $logoFileName ? $logoFileName : ($existingStand['logo'] ?? 'default-shop.png');
                
                $sql = "UPDATE stands SET description = ?, city = ?, address = ?, phone = ?, website = ?, logo = ?, updated_at = NOW() WHERE id = ?";
                $stmtUpdate = $db->prepare($sql);
                $stmtUpdate->execute([$description, $city, $address, $phone, $website, $finalLogo, $existingId]);
                
                header('Location: create?msg=updated');
                exit;
            } else {
                $finalLogo = $logoFileName; 
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
?>