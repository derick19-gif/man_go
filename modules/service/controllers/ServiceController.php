<?php
use App\Core\Controller;

$coreControllerPath = dirname(dirname(dirname(__DIR__))) . '/core/Controller.php';
if (file_exists($coreControllerPath) && !class_exists('App\Core\Controller')) { require_once $coreControllerPath; }
$databasePath = dirname(dirname(dirname(__DIR__))) . '/core/Database.php';
if (file_exists($databasePath) && !class_exists('App\Core\Database')) { require_once $databasePath; }

class ServiceController extends Controller {

    public function index() {
        try { $db = \App\Core\Database::connect(); } catch (Exception $e) { die("Erreur BD"); }

        $searchQuery = trim($_GET['q'] ?? '');
        $searchLocation = trim($_GET['location'] ?? '');
        $searchCategory = (int)($_GET['category'] ?? 0);

        // 1. CHARGEMENT DE L'ARBRE DES CATÉGORIES (Parents + Enfants)
        $categoriesTree = [];
        try {
            $stmtCats = $db->query("SELECT id, name_key AS name, parent_id FROM categories ORDER BY name_key ASC");
            $allCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
            
            // On isole les Grandes Familles (Parents)
            foreach($allCats as $cat) {
                if (empty($cat['parent_id'])) {
                    $categoriesTree[$cat['id']] = ['id' => $cat['id'], 'name' => $cat['name'], 'subcategories' => []];
                }
            }
            // On rattache les Spécialités (Enfants)
            foreach($allCats as $cat) {
                if (!empty($cat['parent_id']) && isset($categoriesTree[$cat['parent_id']])) {
                    $categoriesTree[$cat['parent_id']]['subcategories'][] = $cat;
                }
            }
            // On trie l'arbre principal alphabétiquement de A à Z
            usort($categoriesTree, function($a, $b) { return strcmp($a['name'], $b['name']); });
        } catch (PDOException $e) { }

        // 2. MOTEUR DE RECHERCHE INTELLIGENT
        $sql = "SELECT s.*, st.name as stand_name, c.name_key as category_name 
                FROM services s 
                LEFT JOIN stands st ON s.stand_id = st.id 
                LEFT JOIN categories c ON s.category_id = c.id 
                WHERE s.status = 'active'";
        $params = [];

        if (!empty($searchQuery)) {
            $sql .= " AND (s.title LIKE ? OR s.description LIKE ?)";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
        }
        if (!empty($searchLocation)) {
            $sql .= " AND (s.location LIKE ? OR s.city LIKE ?)";
            $params[] = "%$searchLocation%";
            $params[] = "%$searchLocation%";
        }
        if ($searchCategory > 0) {
            // L'ASTUCE AMAZON : Si on clique sur une Grande Famille (Parent), on inclut automatiquement toutes ses sous-catégories (Enfants) !
            $sql .= " AND (s.category_id = ? OR s.category_id IN (SELECT id FROM categories WHERE parent_id = ?))";
            $params[] = $searchCategory;
            $params[] = $searchCategory;
        }

        $sql .= " ORDER BY s.created_at DESC";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $services = []; }

        return $this->render('services/index', [
            'title' => 'Services & Prestations Professionnelles - MAN GO',
            'services' => $services,
            'categoriesTree' => $categoriesTree,
            'searchQuery' => $searchQuery,
            'searchLocation' => $searchLocation,
            'searchCategory' => $searchCategory
        ]);
    }

    public function show($id) {
        try { $db = \App\Core\Database::connect(); } catch (Exception $e) { die("Erreur de connexion."); }
        $stmt = $db->prepare("SELECT s.*, st.name as stand_name, st.phone as stand_phone, st.email as stand_email FROM services s LEFT JOIN stands st ON s.stand_id = st.id WHERE s.id = ? AND s.status = 'active'");
        $stmt->execute([$id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$service) { http_response_code(404); echo "<h1>Service non trouvé</h1>"; return; }
        return $this->render('services/detail', ['title' => htmlspecialchars($service['title']) . ' - MAN GO', 'service' => $service]);
    }
}