<?php
/**
 * Controller: ServiceController
 * Description: Gestion complète des services professionnels sur MAN GO
 */

class ServiceController extends Controller {

    /**
     * Affiche la liste de tous les services disponibles avec filtrage dynamique
     */
    public function index() {
        $db = Database::getInstance()->getConnection();
        
        // Récupération des services depuis la base de données de manière sécurisée
        try {
            $stmt = $db->query("SELECT s.*, st.name as stand_name, c.name as category_name 
                                FROM services s 
                                LEFT JOIN stands st ON s.stand_id = st.id 
                                LEFT JOIN categories c ON s.category_id = c.id 
                                WHERE s.status = 'active' 
                                ORDER BY s.created_at DESC");
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $services = [];
        }

        // Chargement de la vue dédiée aux services
        $this->render('services/index', [
            'title' => 'Services Professionnels & Prestations - MAN GO',
            'services' => $services
        ]);
    }

    /**
     * Affiche le détail d'un service spécifique
     */
    public function show($id) {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT s.*, st.name as stand_name, st.phone as stand_phone, st.email as stand_email 
                              FROM services s 
                              LEFT JOIN stands st ON s.stand_id = st.id 
                              WHERE s.id = ? AND s.status = 'active'");
        $stmt->execute([$id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$service) {
            http_response_code(404);
            echo "<h1>Service non trouvé</h1>";
            return;
        }

        $this->render('services/detail', [
            'title' => htmlspecialchars($service['title']) . ' - MAN GO',
            'service' => $service
        ]);
    }
}