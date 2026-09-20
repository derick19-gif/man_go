<?php
// dashboard.php

// 1. Inclusion de la configuration (contient getDBConnection()) et de la classe Session
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Session.php';

// Inclusion optionnelle du NotificationManager s'il existe
if (file_exists(__DIR__ . '/../../includes/NotificationManager.php')) {
    require_once __DIR__ . '/../../includes/NotificationManager.php';
}

Session::init();

// 2. Authentification via la classe Session (On remonte à la racine pour le login)
if (!Session::get('user_id')) {
    header('Location: ../../login.php');
    exit;
}

$userId = (int)Session::get('user_id');

// 3. Connexion à la BDD via la fonction globale et sécurisation PDO
$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// 4. Configuration de la Pagination
$limit = 5;
$page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT) ? (int)$_GET['page'] : 1;
if ($page < 1) { 
    $page = 1; 
}

try {
    // Informations utilisateur
    $stmtUser = $pdo->prepare("SELECT id, firstname, lastname, email, avatar FROM users WHERE id = :id LIMIT 1");
    $stmtUser->execute([':id' => $userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // On fusionne le prénom et le nom pour recréer la clé 'name' attendue par le design
        $user['name'] = trim($user['firstname'] . ' ' . $user['lastname']);
    } else {
        session_destroy();
        header('Location: ../../login.php');
        exit;
    }

    $role = Session::get('user_role') ?? 'buyer';

    // Redirection si l'utilisateur n'est pas un simple acheteur (client)
    if ($role === 'admin' || $role === 'super_admin') {
        header('Location: ../../admin/dashboard.php');
        exit;
    } elseif ($role === 'vendor' || $role === 'vendeur') {
        header('Location: ../../vendor_dir/dashboard.php');
        exit;
    }

    // Statistiques globales basées sur la table 'ads' (annonces)
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(id) AS total_listings,
            COALESCE(SUM(CASE WHEN status = 'PUBLISHED' THEN 1 ELSE 0 END), 0) AS active_listings,
            0 AS total_views 
        FROM ads 
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

    // Requête des annonces paginées (Adaptée au schema.sql avec la table 'ads')
    $stmtListings = $pdo->prepare("
        SELECT id, title, category_id AS category, price, currency, status, created_at, 0 AS views_count, main_image AS image 
        FROM ads 
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
    $stmtstands = $pdo->prepare("
        SELECT id, name, logo, banner, status 
        FROM stands 
        WHERE status = 'active' 
        ORDER BY RAND() 
        LIMIT 8
    ");
    $stmtstands->execute();
    $featuredstands = $stmtstands->fetchAll(PDO::FETCH_ASSOC);

    // Notifications
    if (class_exists('NotificationManager')) {
        $notifManager = new NotificationManager($pdo);
        $notifications = $notifManager->getUserNotifications($userId, 5);
        $unreadNotifsCount = (int)$notifManager->getUnreadCount($userId);
    } else {
        $notifications = [];
        $unreadNotifsCount = 0;
    }

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
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
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
    <!-- Google Fonts Inter / Poppins pour le style futuriste -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            --futuristic-bg: #f8fafc;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            --card-hover-shadow: 0 20px 40px rgba(13, 110, 253, 0.08);
        }

        body { 
            background-color: var(--futuristic-bg); 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
        }

        .sidebar { 
            min-height: calc(100vh - 56px); 
            background-color: #ffffff; 
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.01);
            z-index: 100;
        }

        .nav-link.active { 
            background: var(--primary-gradient); 
            color: #fff !important; 
            border-radius: 10px; 
            font-weight: 600; 
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }

        .nav-link { 
            border-radius: 10px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            color: #64748b;
            font-weight: 500;
        }

        .nav-link:hover:not(.active) { 
            background-color: #f1f5f9; 
            color: #0d6efd;
            transform: translateX(4px);
        }
        
        /* Cartes de statistiques futuristes */
        .stat-card {
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 16px;
            background: #ffffff;
            box-shadow: var(--card-shadow);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--card-hover-shadow);
            border-color: rgba(13, 110, 253, 0.2);
        }

        .stat-card:hover::after {
            opacity: 1;
        }

        /* Swiper & Boutiques */
        .swiper-container { width: 100%; padding: 10px 5px 35px 5px; }
        .shop-card {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }
        .shop-card:hover { 
            transform: translateY(-6px) scale(1.01); 
            box-shadow: 0 12px 30px rgba(0,0,0,0.08);
            border-color: #cbd5e1;
        }
        .shop-banner { height: 90px; background-size: cover; background-position: center; position: relative; }
        .shop-banner::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0) 50%, rgba(0,0,0,0.2) 100%);
        }
        .shop-logo { width: 54px; height: 54px; margin-top: -27px; border: 3px solid #fff; border-radius: 50%; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        /* Badges de statut futuristes */
        .badge-active { background-color: #d1fae5; color: #065f46; font-weight: 600; }
        .badge-pending { background-color: #fef3c7; color: #92400e; font-weight: 600; }
        .badge-sold { background-color: #f1f5f9; color: #475569; font-weight: 600; }
        
        .notif-dropdown { min-width: 340px; max-height: 400px; overflow-y: auto; border-radius: 16px !important; }
        
        /* Bouton futuriste */
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.35);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 110, 253, 0.5);
            background: linear-gradient(135deg, #0b5ed7 0%, #084298 100%);
        }

        /* Personnalisation de la table */
        .table tr { transition: background-color 0.2s ease; }
        .table tbody tr:hover { background-color: #f8fafc; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../../themes/default/templates/layouts/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            
            <!-- Sidebar Navigation -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-white sidebar p-3 border-end">
                <div class="d-flex align-items-center mb-4 ps-2 p-2 rounded-3 bg-light border border-opacity-50">
                    <img src="<?= htmlspecialchars(($user ?? [])['avatar'] ?? 'assets/images/default-avatar.png', ENT_QUOTES, 'UTF-8') ?>" 
                         alt="Avatar" class="rounded-circle me-2 border border-2 border-white shadow-sm" width="45" height="45" style="object-fit: cover;">
                    <div class="text-truncate">
                        <strong class="d-block text-truncate text-dark"><?= htmlspecialchars(($user ?? [])['name'] ?? 'Utilisateur', ENT_QUOTES, 'UTF-8') ?></strong>
                        <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;"><?= htmlspecialchars(($user ?? [])['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                </div>

                <ul class="nav nav-pills flex-column mb-auto gap-2">
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/" class="nav-link active">
                            <i class="bi bi-speedometer2 me-2"></i> Vue d'ensemble
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/listings" class="nav-link">
                            <i class="bi bi-card-list me-2"></i> Mes annonces
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/stands" class="nav-link">
                            <i class="bi bi-shop me-2"></i> Ma boutique
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/services" class="nav-link">
                            <i class="bi bi-chat-dots me-2"></i> Services
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/favorites" class="nav-link">
                            <i class="bi bi-heart me-2"></i> Favoris
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/profile-settings" class="nav-link">
                            <i class="bi bi-gear me-2"></i> Paramètres
                        </a>
                    </li>
                </ul>
                <hr class="my-3 text-muted opacity-25">
                <div>
                    <a href="<?= $baseUrl ?>/logout.php" class="nav-link text-danger fw-semibold hover-danger">
                        <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
                    </a>
                </div>
            </nav>

            <!-- Main Content Area -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                
                <!-- Titre & Menu de Notification -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
                    <div>
                        <h1 class="h3 fw-bold text-dark mb-1">Tableau de bord</h1>
                        <p class="text-muted small mb-0">Bienvenue dans votre espace de pilotage MAN GO.</p>
                    </div>
                    
                    <div class="btn-toolbar mb-2 mb-md-0 gap-2 align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-light border position-relative dropdown-toggle rounded-pill px-3 shadow-sm" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell text-secondary fs-5 align-middle"></i>
                                <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= $unreadNotifsCount === 0 ? 'd-none' : '' ?>">
                                    <?= $unreadNotifsCount ?>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end notif-dropdown p-3 shadow-lg border-0 mt-2" aria-labelledby="notifDropdown">
                                <li>
                                    <div class="dropdown-header d-flex justify-content-between align-items-center px-0 pt-0 pb-2">
                                        <span class="fw-bold text-dark fs-6">Notifications</span>
                                        <button onclick="refreshNotifications()" class="btn btn-sm btn-light rounded-circle p-1 text-primary shadow-sm" title="Actualiser"><i class="bi bi-arrow-clockwise"></i></button>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider opacity-75"></li>
                                <div id="notifList" class="py-1">
                                    <?php if (!empty($notifications)): ?>
                                        <?php foreach ($notifications as $notif): ?>
                                            <li class="mb-2">
                                                <a class="dropdown-item p-2 rounded-3 text-wrap <?= !($notif['is_read'] ?? false) ? 'bg-light border-start border-3 border-primary fw-semibold' : '' ?>" href="<?= htmlspecialchars($notif['link'] ?? '#', ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="small text-dark"><?= htmlspecialchars($notif['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div class="text-muted fst-italic mt-1" style="font-size: 0.72rem;">
                                                        <i class="bi bi-clock me-1"></i><?= isset($notif['created_at']) ? date('d/m/Y H:i', strtotime($notif['created_at'])) : '' ?>
                                                    </div>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="text-center py-4 text-muted small">Aucune notification pour le moment.</li>
                                    <?php endif; ?>
                                </div>
                            </ul>
                        </div>

                        <a href="../../publish.php" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold d-flex align-items-center">
                            <i class="bi bi-plus-lg me-2"></i> Publier une annonce
                        </a>
                    </div>
                </div>

                <!-- Section Dynamique : Stands / Boutiques -->
                <?php if (!empty($featuredstands)): ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                                <i class="bi bi-shop me-2 text-primary"></i>Stands & Boutiques à la une
                            </h5>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-medium">Défilement interactif</span>
                        </div>
                        <div class="swiper swiper-container">
                            <div class="swiper-wrapper">
                                <?php foreach ($featuredstands as $shop): ?>
                                    <div class="swiper-slide">
                                        <div class="shop-card text-center pb-3">
                                            <div class="shop-banner" style="background-image: url('<?= htmlspecialchars($shop['banner'] ?? 'assets/images/default-banner.jpg', ENT_QUOTES, 'UTF-8') ?>');"></div>
                                            <img src="<?= htmlspecialchars($shop['logo'] ?? 'assets/images/default-shop.png', ENT_QUOTES, 'UTF-8') ?>" class="shop-logo" alt="Logo">
                                            <h6 class="fw-bold mt-2 mb-1 text-truncate px-2 text-dark"><?= htmlspecialchars($shop['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h6>
                                            <a href="../../shop-detail.php?id=<?= (int)$shop['id'] ?>" class="btn btn-sm btn-outline-primary mt-2 py-1 px-4 rounded-pill fw-medium fs-7">Visiter</a>
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
                        <div class="card stat-card p-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Total des annonces</span>
                                    <h2 class="mb-0 mt-2 fw-extrabold text-dark counter" data-target="<?= (int)($stats['total_listings'] ?? 0) ?>">
                                        <?= (int)($stats['total_listings'] ?? 0) ?>
                                    </h2>
                                </div>
                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                    <i class="bi bi-box-seam fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card stat-card p-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Annonces actives</span>
                                    <h2 class="mb-0 mt-2 fw-extrabold text-success counter" data-target="<?= (int)($stats['active_listings'] ?? 0) ?>">
                                        <?= (int)($stats['active_listings'] ?? 0) ?>
                                    </h2>
                                </div>
                                <div class="bg-success bg-opacity-10 text-success p-3 rounded-4 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                    <i class="bi bi-check-circle fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card stat-card p-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Vues totales</span>
                                    <h2 class="mb-0 mt-2 fw-extrabold text-info counter" data-target="<?= (int)($stats['total_views'] ?? 0) ?>">
                                        <?= (int)($stats['total_views'] ?? 0) ?>
                                    </h2>
                                </div>
                                <div class="bg-info bg-opacity-10 text-info p-3 rounded-4 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                    <i class="bi bi-eye fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section : Tableau de mes annonces avec pagination -->
                <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-0 mt-2">
                        <h5 class="mb-0 fw-bold text-dark">Mes annonces récentes</h5>
                        <a href="my-listings.php" class="btn btn-sm btn-light border rounded-pill px-3 fw-medium text-primary">Gérer tout</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-uppercase text-muted fs-7">
                                    <tr>
                                        <th scope="col" class="ps-4 py-3">Annonce</th>
                                        <th scope="col" class="py-3">Prix</th>
                                        <th scope="col" class="py-3">Statut</th>
                                        <th scope="col" class="py-3">Date</th>
                                        <th scope="col" class="py-3">Vues</th>
                                        <th scope="col" class="text-end pe-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentListings)): ?>
                                        <?php foreach ($recentListings as $listing): ?>
                                            <tr>
                                                <td class="ps-4 py-3">
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= htmlspecialchars($listing['image'] ?? 'assets/images/placeholder.jpg', ENT_QUOTES, 'UTF-8') ?>" 
                                                             class="rounded-3 me-3 border shadow-sm" width="50" height="50" style="object-fit: cover;" alt="Aperçu">
                                                        <div>
                                                            <strong class="d-block text-truncate text-dark" style="max-width: 220px;">
                                                                <?= htmlspecialchars($listing['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                            </strong>
                                                            <small class="text-muted"><?= htmlspecialchars($listing['category'] ?? 'Général', ENT_QUOTES, 'UTF-8') ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fw-bold text-dark">
                                                    <?= number_format((float)($listing['price'] ?? 0), 2, ',', ' ') ?> 
                                                    <span class="text-muted fw-normal fs-7"><?= htmlspecialchars($listing['currency'] ?? 'FCFA', ENT_QUOTES, 'UTF-8') ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = match(strtoupper($listing['status'] ?? '')) {
                                                        'PUBLISHED', 'ACTIVE' => 'badge-active',
                                                        'DRAFT', 'PENDING'    => 'badge-pending',
                                                        'ARCHIVED', 'SOLD'    => 'badge-sold',
                                                        default               => 'bg-secondary text-white'
                                                    };
                                                    ?>
                                                    <span class="badge px-3 py-2 rounded-pill <?= $statusClass ?>">
                                                        <?= htmlspecialchars(ucfirst(strtolower($listing['status'] ?? 'Inconnu')), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td class="text-muted small">
                                                    <?= isset($listing['created_at']) ? htmlspecialchars(date('d/m/Y', strtotime($listing['created_at'])), ENT_QUOTES, 'UTF-8') : '-' ?>
                                                </td>
                                                <td class="fw-semibold text-dark">
                                                    <i class="bi bi-eye text-muted me-1"></i> <?= htmlspecialchars((string)((int)($listing['views_count'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <a href="../../edit-listing.php?id=<?= (int)$listing['id'] ?>" class="btn btn-sm btn-light border rounded-circle p-2 me-1 text-primary shadow-sm" title="Éditer">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="../../listing-detail.php?id=<?= (int)$listing['id'] ?>" class="btn btn-sm btn-light border rounded-circle p-2 text-secondary shadow-sm" title="Voir">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                                Aucune annonce trouvée sur cette page.<br>
                                                <a href="../../publish.php" class="btn btn-sm btn-primary rounded-pill px-4 mt-3">Créer une annonce</a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pied de carte avec Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="card-footer bg-white py-3 px-4 border-0">
                            <nav aria-label="Navigation des annonces">
                                <ul class="pagination pagination-sm justify-content-end mb-0 gap-1">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link rounded-circle border-0 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" href="?page=<?= $page - 1 ?>" aria-label="Précédent">&laquo;</a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                                            <a class="page-link rounded-circle border-0 d-flex align-items-center justify-content-center fw-semibold <?= ($page === $i) ? 'bg-primary text-white shadow-sm' : 'text-dark' ?>" style="width: 32px; height: 32px;" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link rounded-circle border-0 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" href="?page=<?= $page + 1 ?>" aria-label="Suivant">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>

                </div>

            </main>
        </div>
    </div>

    <!-- Footer (Correction de la balise PHP non fermée) -->
    <?php include __DIR__ . '/../../themes/default/templates/layouts/footer.php'; ?>

    <!-- Scripts Javascript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Initialisation de Swiper pour le carrousel des boutiques
        document.addEventListener('DOMContentLoaded', function () {
            if (document.querySelector('.swiper-container')) {
                new Swiper('.swiper-container', {
                    slidesPerView: 1,
                    spaceBetween: 20,
                    autoplay: {
                        delay: 4000,
                        disableOnInteraction: false,
                    },
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true,
                    },
                    breakpoints: {
                        576: { slidesPerView: 2, spaceBetween: 20 },
                        768: { slidesPerView: 3, spaceBetween: 20 },
                        1200: { slidesPerView: 4, spaceBetween: 20 }
                    }
                });
            }
        });

        // Fonction pour rafraîchir les notifications via AJAX
        function refreshNotifications() {
            fetch('../../api/get-notifications.php')
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
                                html += `<li class="mb-2">
                                    <a class="dropdown-item p-2 rounded-3 text-wrap ${!notif.is_read ? 'bg-light border-start border-3 border-primary fw-semibold' : ''}" href="${notif.link || '#'}">
                                        <div class="small text-dark">${notif.message}</div>
                                        <div class="text-muted fst-italic mt-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-clock me-1"></i>${notif.created_at}
                                        </div>
                                    </a>
                                </li>`;
                            });
                            notifList.innerHTML = html;
                        } else {
                            notifList.innerHTML = '<li class="text-center py-4 text-muted small">Aucune notification pour le moment.</li>';
                        }
                    }
                })
                .catch(err => console.error('Erreur lors de la mise à jour des notifications:', err));
        }
    </script>
</body>
</html>

