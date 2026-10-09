<?php
use App\Core\Database;

class HomeController {
    
    public function index() {
        $baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
        $dbInstance = Database::getInstance();
        $db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;
        
        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'fr';
        if (!in_array($lang, ['fr', 'en'], true)) { 
            $lang = 'fr'; 
        }
        $_SESSION['lang'] = $lang;

        // 1. Catégories
        try {
            $stmtCats = $db->query("
                SELECT c.id, c.name_key, c.icon_class, COUNT(l.id) as total_listings
                FROM categories c
                LEFT JOIN listings l ON c.id = l.category_id AND LOWER(l.status) IN ('active', 'published', 'actif')
                GROUP BY c.id
                ORDER BY total_listings DESC LIMIT 8
            ");
            $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { 
            $categories = []; 
        }

        // 2. Annonces Actives (Attribuées à $listings pour la vue)
        try {
            $stmtListings = $db->query("
                SELECT l.*, c.name_key AS category_name, u.firstname, u.lastname, s.city
                FROM listings l
                LEFT JOIN categories c ON l.category_id = c.id
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN stands s ON u.id = s.user_id
                WHERE LOWER(l.status) IN ('active', 'published', 'actif')
                ORDER BY l.created_at DESC
                LIMIT 12
            ");
            $listings = $stmtListings->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { 
            $listings = []; 
        }

        // Aliases de sécurité pour couvrir toutes les variantes du template
        $recentListings = $listings;
        $recentAds = $listings;
        $totalListings = count($listings);

        // 3. Annonces VIP (Premium)
        try {
            $stmtVip = $db->query("
                SELECT l.*, c.name_key AS category_name, u.firstname, u.lastname, s.city
                FROM listings l
                LEFT JOIN categories c ON l.category_id = c.id
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN stands s ON u.id = s.user_id
                JOIN user_subscriptions us ON l.user_id = us.user_id
                WHERE LOWER(l.status) IN ('active', 'published', 'actif') 
                  AND us.plan_id = 2 
                  AND us.status = 'active' 
                  AND us.end_date > NOW()
                ORDER BY RAND() LIMIT 4
            ");
            $vipListings = $stmtVip->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { 
            $vipListings = []; 
        }

        // 4. Chargement de la vue home.php
        $viewPath = __DIR__ . '/../views/home.php';
        if (!file_exists($viewPath)) {
            $viewPath = __DIR__ . '/../../themes/default/templates/home.php';
        }
        
        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {
            echo "Erreur : La vue home.php est introuvable.";
        }
    }
}