<?php
// =========================================================================
// Page de Vérification KYC - MAN GO
// =========================================================================

// 1. DÉFINITION DE LA CONSTANTE MANQUANTE (Solution pour l'erreur Database)
if (!defined('APP_PATH')) {
    define('APP_PATH', __DIR__);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Inclusions absolues pour éviter l'erreur "Class not found"
require_once __DIR__ . '/modules/kyc/Models/KycModel.php';
require_once __DIR__ . '/modules/kyc/Controllers/KycController.php';

$controller = new \Modules\Kyc\Controllers\KycController();

// 3. Routage simple
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->submit();
} else {
    $controller->index();
}