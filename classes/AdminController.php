<?php
namespace App\Controllers;

use App\Core\Database;
use PDO;

class AdminController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    /**
     * Statistiques globales de la plateforme
     */
    public function getGlobalStats(): array {
        try {
            $users = (int)$this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $products = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
            $pendingProducts = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE status = 'pending'")->fetchColumn();
            $orders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();

            return [
                'total_users'      => $users,
                'active_products'  => $products,
                'pending_products' => $pendingProducts,
                'completed_orders' => $orders
            ];
        } catch (\PDOException $e) {
            error_log("Erreur AdminController::getGlobalStats: " . $e->getMessage());
            return ['total_users' => 0, 'active_products' => 0, 'pending_products' => 0, 'completed_orders' => 0];
        }
    }

    /**
     * Liste des annonces en attente de modration
     */
    public function getPendingProducts(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, u.name as vendor_name, u.email as vendor_email 
                FROM products p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.status = 'pending' 
                ORDER BY p.created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log("Erreur AdminController::getPendingProducts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Changer le statut d'un produit (active, rejected, suspended)
     */
    public function updateProductStatus(int $productId, string $status): bool {
        try {
            $stmt = $this->db->prepare("UPDATE products SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $productId]);
        } catch (\PDOException $e) {
            error_log("Erreur AdminController::updateProductStatus: " . $e->getMessage());
            return false;
        }
    }
}