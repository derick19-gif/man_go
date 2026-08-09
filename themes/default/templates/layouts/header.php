<!DOCTYPE html>
<html lang="<?= isset($lang) ? htmlspecialchars($lang) : 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'MAN GO - Marketplace Universelle'; ?></title>
    <link rel="stylesheet" href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/themes/default/assets/css/mango.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="mango-header">
    <div class="container mango-nav-container">
        <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/" class="mango-logo">
            MAN <span>GO</span>
        </a>
        <nav>
            <ul class="mango-nav-links">
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/">Accueil</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/product">Annonces</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/stands">Boutiques & Stands</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/services">Services</a></li>
                <?php if (isset($_SESSION['user'])): ?>
                    <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/dashboard">Mon Compte</a></li>
                    <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/logout">Dconnexion</a></li>
                <?php else: ?>
                    <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/login">Connexion</a></li>
                    <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/register" class="btn-mango" style="padding: 8px 16px;">Publier une annonce</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>