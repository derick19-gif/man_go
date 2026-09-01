<?php
define('APP_PATH', __DIR__);

require_once __DIR__ . '/core/Database.php';

$db = Database::connect();

$db->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$executed = $db->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/database/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $filename = basename($file);
    if (!in_array($filename, $executed)) {
        $sql = file_get_contents($file);
        $db->exec($sql);
        
        $stmt = $db->prepare("INSERT INTO migrations (migration) VALUES (:migration)");
        $stmt->execute([':migration' => $filename]);
        
        echo "Migration réussie : {$filename}\n";
    }
}
echo "Base de données à jour.";
