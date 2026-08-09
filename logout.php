<?php
// logout.php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Session.php';

// 1. Initialisation de la session
Session::init();

// 2. Destruction complète de la session (vide $_SESSION, invalide le cookie et détruit le fichier/stockage)
Session::destroy();

// 3. En-têtes Anti-Cache (Empêche l'affichage du Dashboard via le bouton "Retour" du navigateur)
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache");                                  // HTTP 1.0
header("Expires: 0");                                       // Proxies

// 4. Redirection vers la page de connexion
header('Location: login.php');
exit;