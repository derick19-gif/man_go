<?php
namespace App\Controllers;

use PDO;
use Exception;

class OrderController {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $this->db = $db;
    }

    // Rcuprer un produit avec infos du vendeur
    public function getProductDetail($productId) {
        $stmt = $this->db->prepare("
            SELECT p.*, u.username as vendor_name, u.is_vip, c.name as category_name
            FROM products p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = :id AND p.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':id' => $productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Soumettre une offre de ngociation via le chat
    public function proposeOffer($buyerId, $receiverId, $productId, $price, $customMessage = '') {
        if ($price <= 0 || !$receiverId) {
            return ['status' => 'error', 'message' => 'Donnes invalides pour la proposition.'];
        }

        $msgText = "?? PROPOSITION DE PRIX : " . number_format($price, 0, ',', ' ') . " FCFA";
        if (!empty($customMessage)) {
            $msgText .= "\nNote : " . trim($customMessage);
        }

        // Insrer le message dans la table chat
        $stmt = $this->db->prepare("
            INSERT INTO chat_messages (sender_id, receiver_id, product_id, message, label, created_at)
            VALUES (:sender, :receiver, :product, :msg, 'Ngociation', NOW())
        ");
        
        $success = $stmt->execute([
            ':sender'   => $buyerId,
            ':receiver' => $receiverId,
            ':product'  => $productId,
            ':msg'      => $msgText
        ]);

        if ($success) {
            return ['status' => 'success', 'message' => 'Offre transmise dans la discussion.'];
        }

        return ['status' => 'error', 'message' => 'chec de l\'envoi de la proposition.'];
    }

    // Cration directe d'une commande
    public function createOrder($buyerId, $vendorId, $productId, $amount) {
        if ($buyerId == $vendorId) {
            return ['status' => 'error', 'message' => 'Vous ne pouvez pas acheter votre propre produit.'];
        }

        $orderCode = 'MGO-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

        try {
            $stmt = $this->db->prepare("
                INSERT INTO orders (order_code, buyer_id, vendor_id, product_id, amount, status, created_at)
                VALUES (:code, :buyer, :vendor, :product, :amount, 'pending', NOW())
            ");
            
            $stmt->execute([
                ':code'    => $orderCode,
                ':buyer'   => $buyerId,
                ':vendor'  => $vendorId,
                ':product' => $productId,
                ':amount'  => $amount
            ]);

            return [
                'status'     => 'success',
                'order_code' => $orderCode,
                'order_id'   => $this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Erreur enregistrement commande : ' . $e->getMessage()];
        }
    }

    // Rcuprer la liste des commandes d'un utilisateur (Achats & Ventes)
    public function getUserOrders($userId) {
        $stmt = $this->db->prepare("
            SELECT o.*, p.title as product_title, p.image as product_image,
                   u_buyer.username as buyer_name, u_vendor.username as vendor_name
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN users u_buyer ON o.buyer_id = u_buyer.id
            LEFT JOIN users u_vendor ON o.vendor_id = u_vendor.id
            WHERE o.buyer_id = :uid OR o.vendor_id = :uid
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}