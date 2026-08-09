<?php
// dashboard.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentification
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2. Connexion à la BDD et chargement des dépendances
require_once 'includes/db.php';
require_once 'includes/NotificationManager.php';

$userId = (int)$_SESSION['user_id'];

// Désactiver l'émulation PDO pour garantir le bon type des entiers dans LIMIT / OFFSET
if (isset($pdo)) {
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
}

// 3. Configuration de la Pagination
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
        header('Location: login.php');
        exit;
    }

    // Statistiques globales
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(id) AS total_listings,
            COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_listings,
            COALESCE(SUM(views_count), 0) AS total_views
        FROM listings 
        WHERE user_id = :id
    ");
    $stmtStats->execute([':id' => $userId]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Calcul propre de la pagination
    $totalListings = (int)($stats['total_listings'] ?? 0);
    $totalPages = max(1, (int)ceil($totalListings / $limit));
    
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    
    // Calcul de l'OFFSET final
    $offset = ($page - 1) * $limit;

    // Requête des annonces paginées
    $stmtListings = $pdo->prepare("
        SELECT id, title, category, price, currency, status, created_at, views_count, image 
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

    // Récupération des stands / boutiques mis en avant
    $stmtShops = $pdo->prepare("
        SELECT id, name, logo, banner, status 
        FROM shops 
        WHERE status = 'active' 
        ORDER BY RAND() 
        LIMIT 8
    ");
    $stmtShops->execute();
    $featuredShops = $stmtShops->fetchAll(PDO::FETCH_ASSOC);

    // Notifications
    $notifManager = new NotificationManager($pdo);
    $notifications = $notifManager->getUserNotifications($userId, 5);
    $unreadNotifsCount = (int)$notifManager->getUnreadCount($userId);

} catch (PDOException $e) {
    error_log("Erreur Dashboard PDO: " . $e->getMessage());
    $user = ['name' => 'Utilisateur', 'email' => '', 'avatar' => null];
    $stats = ['total_listings' => 0, 'active_listings' => 0, 'total_views' => 0];
    $recentListings = [];
    $featuredShops = [];
    $notifications = [];
    $unreadNotifsCount = 0;
    $totalPages = 1;
}

$pageTitle = "Tableau de bord dynamique - MAN GO";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: calc(100vh - 56px); background-color: #ffffff; }
        .nav-link.active { background-color: #0d6efd; color: #fff !important; border-radius: 8px; font-weight: 500; }
        .nav-link { border-radius: 8px; transition: all 0.2s ease-in-out; }
        .nav-link:hover:not(.active) { background-color: #f1f3f5; }
        
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .swiper-container { width: 100%; padding: 10px 0 30px 0; }
        .shop-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eef0f3;
            transition: transform 0.3s ease;
        }
        .shop-card:hover { transform: scale(1.02); }
        .shop-banner { height: 80px; background-size: cover; background-position: center; }
        .shop-logo { width: 50px; height: 50px; margin-top: -25px; border: 3px solid #fff; border-radius: 50%; object-fit: cover; }

        .badge-active { background-color: #198754; color: #fff; }
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-sold { background-color: #6c757d; color: #fff; }
        .notif-dropdown { min-width: 320px; max-height: 380px; overflow-y: auto; }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            
            <!-- Sidebar Navigation -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3 border-end">
                <div class="d-flex align-items-center mb-4 ps-2">
                    <img src="<?= htmlspecialchars($user['avatar'] ?? 'assets/images/default-avatar.png', ENT_QUOTES, 'UTF-8') ?>" 
                         alt="Avatar" class="rounded-circle me-2 border" width="45" height="45" style="object-fit: cover;">
                    <div class="text-truncate">
                        <strong class="d-block text-truncate"><?= htmlspecialchars($user['name'] ?? 'Utilisateur', ENT_QUOTES, 'UTF-8') ?></strong>
                        <small class="text-muted d-block text-truncate"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                </div>

                <ul class="nav nav-pills flex-column mb-auto gap-1">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            <i class="bi bi-speedometer2 me-2"></i> Vue d'ensemble
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="my-listings.php" class="nav-link text-dark">
                            <i class="bi bi-card-list me-2"></i> Mes annonces
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="my-shop.php" class="nav-link text-dark">
                            <i class="bi bi-shop me-2"></i> Ma boutique
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="messages.php" class="nav-link text-dark">
                            <i class="bi bi-chat-dots me-2"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="favorites.php" class="nav-link text-dark">
                            <i class="bi bi-heart me-2"></i> Favoris
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile-settings.php" class="nav-link text-dark">
                            <i class="bi bi-gear me-2"></i> Paramètres
                        </a>
                    </li>
                </ul>
                <hr class="my-3">
                <div>
                    <a href="logout.php" class="nav-link text-danger fw-semibold">
                        <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
                    </a>
                </div>
            </nav>

            <!-- Main Content Area -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                
                <!-- Titre & Menu de Notification -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-4 border-bottom">
                    <h1 class="h2 fw-bold text-dark">Tableau de bord</h1>
                    
                    <div class="btn-toolbar mb-2 mb-md-0 gap-2 align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary position-relative dropdown-toggle" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell fs-5"></i>
                                <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= $unreadNotifsCount === 0 ? 'd-none' : '' ?>">
                                    <?= $unreadNotifsCount ?>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end notif-dropdown p-2 shadow border-0" aria-labelledby="notifDropdown">
                                <li>
                                    <div class="dropdown-header d-flex justify-content-between align-items-center px-1">
                                        <span class="fw-bold">Notifications</span>
                                        <button onclick="refreshNotifications()" class="btn btn-sm btn-link p-0 text-decoration-none"><i class="bi bi-arrow-clockwise"></i></button>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <div id="notifList">
                                    <?php if (!empty($notifications)): ?>
                                        <?php foreach ($notifications as $notif): ?>
                                            <li class="mb-1">
                                                <a class="dropdown-item p-2 rounded text-wrap <?= !($notif['is_read'] ?? false) ? 'bg-light fw-bold' : '' ?>" href="<?= htmlspecialchars($notif['link'] ?? '#', ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="small"><?= htmlspecialchars($notif['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div class="text-muted fst-italic mt-1" style="font-size: 0.75rem;">
                                                        <i class="bi bi-clock me-1"></i><?= isset($notif['created_at']) ? date('d/m/Y H:i', strtotime($notif['created_at'])) : '' ?>
                                                    </div>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="text-center py-3 text-muted small">Aucune notification.</li>
                                    <?php endif; ?>
                                </div>
                            </ul>
                        </div>

                        <a href="publish.php" class="btn btn-primary shadow-sm fw-medium">
                            <i class="bi bi-plus-lg me-1"></i> Publier une annonce
                        </a>
                    </div>
                </div>

                <!-- Section Dynamique : Stands / Boutiques -->
                <?php if (!empty($featuredShops)): ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-shop me-2 text-primary"></i>Stands & Boutiques à la une</h5>
                            <span class="badge bg-primary bg-opacity-10 text-primary">Défilement direct</span>
                        </div>
                        <div class="swiper swiper-container">
                            <div class="swiper-wrapper">
                                <?php foreach ($featuredShops as $shop): ?>
                                    <div class="swiper-slide">
                                        <div class="shop-card shadow-sm text-center pb-3">
                                            <div class="shop-banner" style="background-image: url('<?= htmlspecialchars($shop['banner'] ?? 'assets/images/default-banner.jpg', ENT_QUOTES, 'UTF-8') ?>');"></div>
                                            <img src="<?= htmlspecialchars($shop['logo'] ?? 'assets/images/default-shop.png', ENT_QUOTES, 'UTF-8') ?>" class="shop-logo" alt="Logo">
                                            <h6 class="fw-bold mt-2 mb-1 text-truncate px-2"><?= htmlspecialchars($shop['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h6>
                                            <a href="shop-detail.php?id=<?= (int)$shop['id'] ?>" class="btn btn-xs btn-outline-primary mt-1 py-1 px-3 fs-7 rounded-pill">Visiter</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Cartes de Statistiques -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card stat-card p-3 bg-white">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-medium">Total des annonces</span>
                                    <h3 class="mb-0 mt-1 fw-bold counter" data-target="<?= (int)($stats['total_listings'] ?? 0) ?>">
                                        <?= (int)($stats['total_listings'] ?? 0) ?>
                                    </h3>
                                </div>
                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                                    <i class="bi bi-box-seam fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card stat-card p-3 bg-white">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-medium">Annonces actives</span>
                                    <h3 class="mb-0 mt-1 fw-bold counter" data-target="<?= (int)($stats['active_listings'] ?? 0) ?>">
                                        <?= (int)($stats['active_listings'] ?? 0) ?>
                                    </h3>
                                </div>
                                <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                                    <i class="bi bi-check-circle fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card stat-card p-3 bg-white">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-medium">Vues totales</span>
                                    <h3 class="mb-0 mt-1 fw-bold counter" data-target="<?= (int)($stats['total_views'] ?? 0) ?>">
                                        <?= (int)($stats['total_views'] ?? 0) ?>
                                    </h3>
                                </div>
                                <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                                    <i class="bi bi-eye fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section : Tableau de mes annonces avec pagination -->
                <div class="card border-0 shadow-sm rounded-3 bg-white">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                        <h5 class="mb-0 fw-bold text-dark">Mes annonces</h5>
                        <a href="my-listings.php" class="btn btn-sm btn-outline-primary fw-medium">Gérer tout</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" class="ps-3">Annonce</th>
                                        <th scope="col">Prix</th>
                                        <th scope="col">Statut</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Vues</th>
                                        <th scope="col" class="text-end pe-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentListings)): ?>
                                        <?php foreach ($recentListings as $listing): ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= htmlspecialchars($listing['image'] ?? 'assets/images/placeholder.jpg', ENT_QUOTES, 'UTF-8') ?>" 
                                                             class="rounded me-3 border" width="48" height="48" style="object-fit: cover;" alt="Aperçu">
                                                        <div>
                                                            <strong class="d-block text-truncate" style="max-width: 220px;">
                                                                <?= htmlspecialchars($listing['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                            </strong>
                                                            <small class="text-muted"><?= htmlspecialchars($listing['category'] ?? 'Général', ENT_QUOTES, 'UTF-8') ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fw-bold">
                                                    <?= htmlspecialchars(number_format((float)($listing['price'] ?? 0), 0, ',', ' '), ENT_QUOTES, 'UTF-8') ?> 
                                                    <?= htmlspecialchars($listing['currency'] ?? 'FCFA', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = match($listing['status'] ?? '') {
                                                        'active'  => 'badge-active',
                                                        'pending' => 'badge-pending',
                                                        'sold'    => 'badge-sold',
                                                        default   => 'bg-secondary text-white'
                                                    };
                                                    ?>
                                                    <span class="badge px-2 py-1 <?= $statusClass ?>">
                                                        <?= htmlspecialchars(ucfirst($listing['status'] ?? 'Inconnu'), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td class="text-muted small">
                                                    <?= isset($listing['created_at']) ? htmlspecialchars(date('d/m/Y', strtotime($listing['created_at'])), ENT_QUOTES, 'UTF-8') : '-' ?>
                                                </td>
                                                <td class="fw-medium">
                                                    <?= htmlspecialchars((string)((int)($listing['views_count'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <a href="edit-listing.php?id=<?= (int)$listing['id'] ?>" class="btn btn-sm btn-light border me-1" title="Éditer">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="listing-detail.php?id=<?= (int)$listing['id'] ?>" class="btn btn-sm btn-light border" title="Voir">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                                Aucune annonce trouvée sur cette page.<br>
                                                <a href="publish.php" class="btn btn-sm btn-primary mt-2">Créer une annonce</a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pied de carte avec Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="card-footer bg-white py-3 border-top">
                            <nav aria-label="Navigation des annonces">
                                <ul class="pagination pagination-sm justify-content-end mb-0">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Précédent">&laquo;</a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Suivant">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>

                </div>

            </main>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Scripts Javascript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Initialisation de Swiper pour le carrousel des boutiques
        document.addEventListener('DOMContentLoaded', function () {
            if (document.querySelector('.swiper-container')) {
                new Swiper('.swiper-container', {
                    slidesPerView: 1,
                    spaceBetween: 15,
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true,
                    },
                    breakpoints: {
                        576: { slidesPerView: 2, spaceBetween: 15 },
                        768: { slidesPerView: 3, spaceBetween: 20 },
                        1200: { slidesPerView: 4, spaceBetween: 20 }
                    }
                });
            }
        });

        // Fonction pour rafraîchir les notifications via AJAX
        function refreshNotifications() {
            fetch('api/get-notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const notifList = document.getElementById('notifList');
                        const notifBadge = document.getElementById('notifBadge');
                        
                        if (data.unread_count > 0) {
                            notifBadge.textContent = data.unread_count;
                            notifBadge.classList.remove('d-none');
                        } else {
                            notifBadge.classList.add('d-none');
                        }

                        if (data.notifications.length > 0) {
                            let html = '';
                            data.notifications.forEach(notif => {
                                html += `<li class="mb-1">
                                    <a class="dropdown-item p-2 rounded text-wrap ${!notif.is_read ? 'bg-light fw-bold' : ''}" href="${notif.link || '#'}">
                                        <div class="small">${notif.message}</div>
                                        <div class="text-muted fst-italic mt-1" style="font-size: 0.75rem;">
                                            <i class="bi bi-clock me-1"></i>${notif.created_at}
                                        </div>
                                    </a>
                                </li>`;
                            });
                            notifList.innerHTML = html;
                        } else {
                            notifList.innerHTML = '<li class="text-center py-3 text-muted small">Aucune notification.</li>';
                        }
                    }
                })
                .catch(err => console.error('Erreur lors de la mise à jour des notifications:', err));
        }
    </script>
</body>
</html>