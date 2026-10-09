<?php
// =========================================================================
// DÉCONNEXION SÉCURISÉE - MGO SYSTEM
// =========================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// En-têtes Anti-Cache pour empêcher le retour en arrière
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Retour au sas de sécurité
header("Location: login.php");
exit();