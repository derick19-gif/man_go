<?php
// shops.php (à la racine)

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/modules/stands/controllers/StandController.php';

Session::init();

$controller = new StandController();
$controller->index();