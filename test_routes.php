<?php
// test_routes.php

require_once __DIR__ . '/config/config.php';

// Force le chargement direct des éléments fondamentaux du Framework
if (file_exists(__DIR__ . '/core/Model.php')) {
    require_once __DIR__ . '/core/Model.php';
}

if (file_exists(__DIR__ . '/core/Controller.php')) {
    require_once __DIR__ . '/core/Controller.php';
}

if (file_exists(__DIR__ . '/core/Autoloader.php')) {
    require_once __DIR__ . '/core/Autoloader.php';
}

if (file_exists(__DIR__ . '/core/Request.php')) {
    require_once __DIR__ . '/core/Request.php';
}

echo "<h2>1. Vérification des Constantes</h2>";
echo "BASE_URL_PATH : " . (defined('BASE_URL_PATH') ? BASE_URL_PATH : '<span style="color:red">NON DÉFINI</span>') . "<br>";
echo "APP_PATH : " . (defined('APP_PATH') ? APP_PATH : '<span style="color:red">NON DÉFINI</span>') . "<br>";

echo "<h2>2. Test d'Existence des Contrôleurs</h2>";
$controllersToTest = [
    'StandController' => '/modules/stands/controllers/StandController.php',
    'HomeController'  => '/controllers/HomeController.php'
];

foreach ($controllersToTest as $class => $path) {
    $fullPath = __DIR__ . $path;
    if (file_exists($fullPath)) {
        echo "✅ Fichier trouvé : <code>$path</code><br>";
        require_once $fullPath;
        if (class_exists($class)) {
            echo " &nbsp;&nbsp;&nbsp;&nbsp; -> Classe <b>$class</b> chargée avec succès !<br>";
        } else {
            echo " &nbsp;&nbsp;&nbsp;&nbsp; ❌ <b style='color:red'>Erreur :</b> La classe $class n'est pas définie dans le fichier !<br>";
        }
    } else {
        echo "ℹ️ Fichier non trouvé à : <code>$path</code> (Normal si l'architecture diffère)<br>";
    }
}

echo "<h2>3. Test de Simulation de Route</h2>";
if (class_exists('Request')) {
    $request = new Request();
    echo "URL demandée actuellement : <b>" . $request->getPath() . "</b><br>";
    echo "Méthode HTTP : <b>" . $request->getMethod() . "</b><br>";
} else {
    echo "<b style='color:red'>❌ Erreur :</b> La classe Request est introuvable.<br>";
}