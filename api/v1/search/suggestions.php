<?php
// Test d'accès
file_put_contents('test_log.txt', 'Le script a été appelé !');
/**
 * API Backend de Suggestions - MAN GO Marketplace
 * Endpoint: /man_go/api/v1/search/suggestions.php?q=keyword
 */

// En-têtes pour forcer le retour en JSON et autoriser le CORS si nécessaire
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

// 1. Charger la configuration de la base de données 
$configPath = __DIR__ . '/../../../config/database.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__, 3) . '/config/database.php';
}

if (file_exists($configPath)) {
    $config = require_once $configPath;
    
    // Création dynamique de l'objet PDO à partir de ton tableau de config
    try {
        $dsn = "{$config['driver']}:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], $config['options']);
    } catch (PDOException $e) {
        // Gestion de l'erreur de connexion si besoin
    }
}

// 2. Récupérer et sécuriser le paramètre de recherche 'q'
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Si la recherche est trop courte, on retourne un tableau vide immédiatement
if (mb_strlen($query) < 2) {
    echo json_encode([
        'success' => true,
        'results' => []
    ]);
    exit;
}

try {
    // Connexion PDO (On s'attend à ce que $pdo soit défini dans config/database.php)
    if (!isset($pdo)) {
        throw new Exception("Erreur critique : Connexion à la base de données non établie.");
    }

    // 3. Requête préparée pour chercher dans les annonces / produits
    // On recherche par correspondance partielle sur le titre ou la description
    $sql = "SELECT id, title, slug, price, main_image AS image, category_id, 'produit' as type 
            FROM products 
            WHERE (title LIKE :searchTerm OR description LIKE :searchTerm) 
            AND status = 'ACTIVE'
            LIMIT 6";

    $stmt = $pdo->prepare($sql);
    $searchTerm = '%' . $query . '%';
    $stmt->execute(['searchTerm' => $searchTerm]);
    $rawProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($rawProducts as $product) {
        // Construction sécurisée de l'URL de redirection de l'annonce
        $productUrl = '/man_go/annonce/' . htmlspecialchars($product['slug'], ENT_QUOTES, 'UTF-8');
        
        // Traitement de l'image (si vide, utiliser une image par défaut)
        $imageUrl = !empty($product['image']) 
            ? '/man_go/uploads/products/' . htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') 
            : '/man_go/themes/default/assets/img/default.png';

        $results[] = [
            'id' => (int)$product['id'],
            'title' => html_entity_decode($product['title'], ENT_QUOTES, 'UTF-8'),
            'type' => 'Annonce',
            'category' => 'Général', // Tu pourras faire une jointure SQL avec la table categories si besoin
            'price' => number_format($product['price'], 0, ',', ' '),
            'image' => $imageUrl,
            'url' => $productUrl
        ];
    }

    // 4. Retourner la réponse au format JSON structuré
    echo json_encode([
        'success' => true,
        'count' => count($results),
        'results' => $results
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // En cas d'erreur serveur, on renvoie un JSON d'erreur propre pour ne pas casser Alpine.js
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur lors de la récupération des suggestions.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
