<?php
/**
 * Controller: ServiceController
 * Description: Gestion complète des services professionnels sur MAN GO
 */

// 1. IMPORT OBLIGATOIRE (C'est ce qui manquait pour que PHP comprenne le mot "Controller")
use App\Core\Controller;

// 2. Inclusion de sécurité du fichier physique
$coreControllerPath = dirname(dirname(dirname(__DIR__))) . '/core/Controller.php';
if (file_exists($coreControllerPath) && !class_exists('App\Core\Controller')) {
    require_once $coreControllerPath;
}

// 3. La classe est maintenant comprise par PHP !
class ServiceController extends Controller {

    public function index() {
        $db = class_exists('Database') ? Database::getInstance() : null;

        $services = [];
        if ($db) {
            try {
                $stmt = $db->query("SELECT s.*, st.name as stand_name, c.name as category_name 
                                    FROM services s 
                                    LEFT JOIN stands st ON s.stand_id = st.id 
                                    LEFT JOIN categories c ON s.category_id = c.id 
                                    WHERE s.status = 'active' 
                                    ORDER BY s.created_at DESC");
                if ($stmt) {
                    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                $services = [];
            }
        }

        return $this->render('services/index', [
            'title' => 'Services Professionnels & Prestations - MAN GO',
            'services' => $services
        ]);
    }

    public function show($id) {
        $db = class_exists('Database') ? Database::getInstance() : null;
        $service = null;

        if ($db) {
            $stmt = $db->prepare("SELECT s.*, st.name as stand_name, st.phone as stand_phone, st.email as stand_email 
                                  FROM services s 
                                  LEFT JOIN stands st ON s.stand_id = st.id 
                                  WHERE s.id = ? AND s.status = 'active'");
            $stmt->execute([$id]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$service) {
            http_response_code(404);
            echo "<h1>Service non trouvé</h1>";
            return;
        }

        return $this->render('services/detail', [
            'title' => htmlspecialchars($service['title']) . ' - MAN GO',
            'service' => $service
        ]);
    }
}