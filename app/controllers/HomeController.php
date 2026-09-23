<?php

use App\Core\Database;

class HomeController {

    public function index() {
        // Gestion de la configuration de l'URL
        $baseUrl = defined('APP_URL') ? APP_URL : (defined('APP_URL') ? APP_URL : '/man_go');

        // Connexion sécurisée à la base de données
        $dbInstance = Database::getInstance();
        $db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() : $dbInstance;

        // Gestion de la langue
        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'fr';
        if (!in_array($lang, ['fr', 'en'], true)) {
            $lang = 'fr';
        }
        $_SESSION['lang'] = $lang;

        // Paramètres de recherche et de pagination
        $search_query = trim($_GET['q'] ?? '');
        $search_city  = trim($_GET['city'] ?? '');
        $itemsPerPage = 8; // Changé à 8 pour faire 2 belles lignes de 4 annonces sur PC
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        // Récupération des catégories (élargies pour la vision internationale)
        try {
            $stmtCats = $db->query("SELECT * FROM categories ORDER BY name ASC LIMIT 12");
            $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $categories = [];
        }

        // Mapping des icônes FontAwesome étendu pour les multiples domaines d'activités
        $iconMap = [
            'electronique' => 'fa-laptop',
            'high-tech'    => 'fa-mobile-screen-button',
            'emploi'       => 'fa-user-tie',
            'services'     => 'fa-handshake',
            'immobilier'   => 'fa-building',
            'maison'       => 'fa-couch',
            'jardin'       => 'fa-leaf',
            'mode'         => 'fa-shirt',
            'vehicule'     => 'fa-car',
            'auto'         => 'fa-car',
            'hotel'        => 'fa-hotel',
            'restauration' => 'fa-utensils',
            'artisan'      => 'fa-hammer',
            'ong'          => 'fa-globe',
            'droit'        => 'fa-scale-balanced',
            'comptable'    => 'fa-calculator'
        ];

        // Récupération et pagination des annonces
        $listings = [];
        $totalListings = 0;
        $totalPages = 1;

        try {
            // CORRECTION CRUCIALE : On cherche les annonces dont le statut est 'active', 'ACTIVE' ou 'PUBLISHED'
            $whereConditions = ["LOWER(l.status) IN ('active', 'published')"];
            $params = [];

            // Recherche textuelle globale
            if (!empty($search_query)) {
                $whereConditions[] = "(l.title LIKE :q OR l.description LIKE :q)";
                $params[':q'] = '%' . $search_query . '%';
            }

            // Recherche par ville/location (uniquement si la colonne existe dans votre base)
            if (!empty($search_city)) {
                // Pour éviter un crash PDO si la colonne n'existe pas, on cherche aussi dans la description
                $whereConditions[] = "(l.description LIKE :city OR l.title LIKE :city)";
                $params[':city'] = '%' . $search_city . '%';
            }

            $whereSql = " WHERE " . implode(" AND ", $whereConditions);

            // Total des éléments pour pagination
            $sqlCount = "SELECT COUNT(*) FROM listings l" . $whereSql;
            $stmtCount = $db->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalListings = (int) $stmtCount->fetchColumn();

            $totalPages = max(1, ceil($totalListings / $itemsPerPage));
            if ($currentPage > $totalPages) {
                $currentPage = $totalPages;
            }

            $offset = ($currentPage - 1) * $itemsPerPage;

            // Requête des annonces limitées (On joint les catégories)
            $sqlListings = "SELECT l.*, c.name_key AS category_name 
                            FROM listings l 
                            LEFT JOIN categories c ON l.category_id = c.id 
                            " . $whereSql . " 
                            ORDER BY l.created_at DESC 
                            LIMIT :limit OFFSET :offset";

            $stmtListings = $db->prepare($sqlListings);
            foreach ($params as $key => $val) {
                $stmtListings->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmtListings->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
            $stmtListings->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmtListings->execute();
            $listings = $stmtListings->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Erreur dans HomeController: " . $e->getMessage());
            $listings = [];
            $totalListings = 0;
            $totalPages = 1;
        }

        // Helper de génération d'URL pour la pagination
        $buildUrl = function(array $newParams = []): string {
            $queryParams = $_GET;
            foreach ($newParams as $key => $value) {
                if ($value === null) {
                    unset($queryParams[$key]);
                } else {
                    $queryParams[$key] = $value;
                }
            }
            return '?' . http_build_query($queryParams);
        };

        $getCategoryIcon = function(?string $name, array $map): string {
            if (empty($name)) {
                return 'fa-layer-group';
            }
            $lower = mb_strtolower($name, 'UTF-8');
            foreach ($map as $key => $icon) {
                if (str_contains($lower, $key)) {
                    return $icon;
                }
            }
            return 'fa-layer-group';
        };

        // 4. Chargement de la VUE (qui contient tout le HTML)
        $viewPath = __DIR__ . '/../views/home.php';
        if (file_exists($viewPath)) {
            // Ces variables seront disponibles dans la vue
            require_once $viewPath;
        } else {
            echo "Erreur : La vue home.php est introuvable à l'emplacement " . $viewPath;
        }
    }
}