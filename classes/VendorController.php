<?php
namespace App\Controllers;

use PDO;
use Exception;

class VendorController {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getVendorStats($vendorId) {
        // Compte les produits actifs via le stand du vendeur
        $stmtProd = $this->db->prepare("
            SELECT COUNT(p.id) 
            FROM products p
            JOIN stands s ON p.stand_id = s.id
            WHERE s.user_id = :uid AND p.is_active = 1
        ");
        $stmtProd->execute([':uid' => $vendorId]);
        $activeProducts = $stmtProd->fetchColumn();

        // Nombre de ventes et total revenus
        $stmtSales = $this->db->prepare("
            SELECT COUNT(*) as total_sales, COALESCE(SUM(amount), 0) as total_revenue
            FROM orders
            WHERE vendor_id = :uid AND status = 'completed'
        ");
        $stmtSales->execute([':uid' => $vendorId]);
        $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);

        // Nombre de chats actifs distincts
        $stmtChats = $this->db->prepare("
            SELECT COUNT(DISTINCT CASE WHEN sender_id = :uid THEN receiver_id ELSE sender_id END)
            FROM chat_messages
            WHERE sender_id = :uid OR receiver_id = :uid
        ");
        $stmtChats->execute([':uid' => $vendorId]);
        $activeChats = $stmtChats->fetchColumn();

        return [
            'active_products' => (int)$activeProducts,
            'total_sales'     => (int)($salesData['total_sales'] ?? 0),
            'total_revenue'   => (float)($salesData['total_revenue'] ?? 0),
            'active_chats'    => (int)$activeChats
        ];
    }
}