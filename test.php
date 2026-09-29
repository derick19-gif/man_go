<?php
// Script de Diagnostic Direct MAN GO
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';

echo "<h1 style='font-family:sans-serif;'>Diagnostic de la Base de Données</h1>";

try {
    $dbInstance = \App\Core\Database::getInstance();
    $db = (method_exists($dbInstance, 'getConnection')) ? $dbInstance->getConnection() :$dbInstance;
    
    // On force l'affichage des erreurs SQL
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // La requête exacte que nous utilisons pour les stands
    $sql = "SELECT s.*, CONCAT(u.firstname, ' ', u.lastname) as vendor_name, u.is_premium 
            FROM stands s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE LOWER(s.status) = 'active'";
            
    $stmt = $db->query($sql);
    $result =$stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) > 0) {
        echo "<p style='color:green; font-weight:bold; font-family:sans-serif;'>✅ SUCCÈS ! " . count($result) . " stand(s) trouvé(s) dans la base.</p>";
        echo "<pre style='background:#111; color:#0f0; padding:15px; border-radius:8px;'>" . print_r($result, true) . "</pre>";
        echo "<p style='font-family:sans-serif;'><b>Conclusion :</b> La requête SQL est parfaite. Le problème vient du fait que le fichier <i>StandController.php</i> n'envoie pas correctement la variable à la vue <i>index.php</i>.</p>";
    } else {
        echo "<p style='color:orange; font-weight:bold; font-family:sans-serif;'>⚠️ La requête a fonctionné, mais elle trouve 0 stand.</p>";
        echo "<p style='font-family:sans-serif;'><b>Conclusion :</b> Vérifiez dans phpMyAdmin que le `user_id` du stand correspond bien à un `id` existant dans la table `users`, et que le statut est bien 'ACTIVE'.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red; font-weight:bold; font-family:sans-serif;'>🚨 ERREUR SQL EXACTE DÉTECTÉE :</p>";
    echo "<pre style='background:#fee; color:red; padding:15px; border:2px solid red; border-radius:8px;'>" . $e->getMessage() . "</pre>";
}
?>