<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Session.php';
Session::init();

echo "<pre>";
print_r($_SESSION);
echo "</pre>";