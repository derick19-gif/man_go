<?php
namespace App\Modules\Dashboard\Controllers;

use Session;
use NotificationManager;
use PDO;

require_once __DIR__ . '/../../includes/NotificationManager.php';

class DashboardController {
    public function index() {
        Session::init();

        // Sécurité : Redirection vers la connexion si non authentifié
        if (!Session::get('user_id')) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }

        $userId = (int)Session::get('user_id');
        $role = Session::get('user_role') ?? 'user';

        // 2. Redirection selon les rôles (Niveaux 2 et 3)[cite: 1]
        if ($role === 'admin') {
            header('Location: ' . APP_URL . '/admin/dashboard.php');
            exit;
        } elseif ($role === 'vendor' || $role === 'vendeur') {
            header('Location: ' . APP_URL . '/vendor_dir/dashboard.php');
            exit;
        }

        // 3. Connexion à la BDD et exécution de la logique métier[cite: 1]
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $limit = 5;
        $page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT) ? (int)$_GET['page'] : 1;
        if ($page < 1) { 
            $page = 1; 
        }

        try {
            // Informations utilisateur
            $stmtUser = $pdo->prepare("SELECT id, name, email, avatar FROM users WHERE id = :id LIMIT 1");
            $stmtUser->execute([':id' => $userId]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                session_destroy();
                header('Location: ' . APP_URL . '/login.php');
                exit;
            }

            // Statistiques globales
            $stmtStats = $pdo->prepare("
                SELECT 
                    COUNT(id) AS total_listings,
                    COALESCE(SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END), 0) AS active_listings,
                    COALESCE(SUM(views_count), 0) AS total_views
                FROM listings 
                WHERE user_id = :id
            ");
            $stmtStats->execute([':id' => $userId]);
            $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

            // Pagination
            $totalListings = (int)($stats['total_listings'] ?? 0);
            $totalPages = max(1, (int)ceil($totalListings / $limit));
            if ($page > $totalPages) { $page = $totalPages; }
            $offset = ($page - 1) * $limit;

            // Annonces paginées
           $stmtListings = $pdo->prepare("
                SELECT id, title, category_id, price, currency, status, created_at, views_count, image 
                FROM listings 
                WHERE user_id = :id 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset
            ");
            $stmtListings->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmtListings->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtListings->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtListings->execute();
            $recentListings = $stmtListings->fetchAll(PDO::FETCH_ASSOC);

            // Boutiques en avant
            $stmtstands = $pdo->prepare("
                SELECT id, name, logo, banner, status 
                FROM stands 
                WHERE status = 'ACTIVE' 
                ORDER BY RAND() 
                LIMIT 8
            ");
            $stmtstands->execute();
            $featuredstands = $stmtstands->fetchAll(PDO::FETCH_ASSOC);

            // Notifications
            $notifManager = new NotificationManager($pdo);
            $notifications = $notifManager->getUserNotifications($userId, 5);
            $unreadNotifsCount = (int)$notifManager->getUnreadCount($userId);

        } catch (PDOException $e) {
            error_log("Erreur Dashboard PDO: " . $e->getMessage());
            $user = ['name' => 'Utilisateur', 'email' => '', 'avatar' => null];
            $stats = ['total_listings' => 0, 'active_listings' => 0, 'total_views' => 0];
            $recentListings = [];
            $featuredstands = [];
            $notifications = [];
            $unreadNotifsCount = 0;
            $totalPages = 1;
        }

        $pageTitle = "Tableau de bord dynamique - MAN GO";

        // Appel de la vue dédiée
        require_once __DIR__ . '/../../client/views/dashboard.php';
    }
}

