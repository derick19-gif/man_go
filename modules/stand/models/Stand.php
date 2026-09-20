<?php
// modules/stand/models/Stand.php

$modelPath = dirname(dirname(dirname(__DIR__))) . '/core/Model.php';
if (file_exists($modelPath) && !class_exists('Model')) {
    require_once $modelPath;
}

class Stand extends Model {
    protected $table = 'stands';

    public function __construct() {
        $dbInstance = class_exists('Database') ? Database::getInstance() : null;
        if ($dbInstance && method_exists($dbInstance, 'getConnection')) {
            $this->db = $dbInstance->getConnection();
        } elseif ($dbInstance instanceof PDO) {
            $this->db = $dbInstance;
        } else {
            parent::__construct();
            if (isset($this->db) && method_exists($this->db, 'getConnection')) {
                $this->db = $this->db->getConnection();
            }
        }
    }

    public function find($id) {
        if (!$this->db) return null;
        $stmt = $this->db->prepare("SELECT s.*, u.name as vendor_name, u.phone as vendor_phone FROM {$this->table} s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStandListings($stand_id) {
        if (!$this->db) return [];
        $stmt = $this->db->prepare("SELECT * FROM listings WHERE stand_id = :stand_id AND status = 'active' ORDER BY created_at DESC");
        $stmt->execute([':stand_id' => $stand_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // 1. LE MOTEUR DE RECHERCHE MAN GO (Pour afficher les stands sur la page publique)
    // =========================================================================
    public function getActiveStands($search = '', $location = '', $category = '') {
        if (!$this->db) return [];

        // ATTENTION : On s'assure que le statut est bien 'ACTIVE' en majuscules
        // car votre base de données utilise un ENUM('PENDING', 'ACTIVE', 'SUSPENDED')
        $sql = "SELECT s.*, u.name as vendor_name FROM {$this->table} s LEFT JOIN users u ON s.user_id = u.id WHERE s.status = 'active'";
        $params = [];

        // Filtre par mot-clé (Nom ou Description)
        if (!empty($search)) {
            $sql .= " AND (s.name LIKE :search OR s.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        // Filtre par localisation (Ville ou Quartier)
        if (!empty($location)) {
            $sql .= " AND (s.city LIKE :location OR s.address LIKE :location)";
            $params[':location'] = '%' . $location . '%';
        }

        // Filtre par Catégorie/Métier
        if (!empty($category)) {
            $sql .= " AND s.category = :category";
            $params[':category'] = $category;
        }

        // L'ASTUCE BUSINESS : On trie d'abord par Compte Premium (is_premium DESC)
        $sql .= " ORDER BY s.is_premium DESC, s.created_at DESC"; 

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // 2. LA CRÉATION DE STAND (Avec détection d'erreurs)
    // =========================================================================
    public function createStand($data) {
        if (!$this->db) return false;
        
        try {
            // Mode STRICT pour afficher les erreurs si la base de données bloque
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "INSERT INTO {$this->table} 
                    (user_id, name, description, category, city, address, phone, website, logo, status, created_at) 
                    VALUES 
                    (:user_id, :name, :description, :category, :city, :address, :phone, :website, :logo, 'ACTIVE', NOW())";
            
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                ':user_id'     => $data['user_id'] ?? null,
                ':name'        => $data['name'] ?? null,
                ':description' => $data['description'] ?? null,
                ':category'    => $data['category'] ?? null,
                ':city'        => $data['city'] ?? null,
                ':address'     => $data['address'] ?? null,
                ':phone'       => $data['phone'] ?? null,
                ':website'     => $data['website'] ?? null,
                ':logo'        => $data['logo'] ?? 'default-shop.png'
            ]);

        } catch (\PDOException $e) {
            die("<div style='background:#111; color:white; padding:20px; font-family:sans-serif; border-left: 8px solid #F59E0B;'>
                    <h2 style='color:#EF4444;'>🚨 ERREUR BASE DE DONNÉES 🚨</h2>
                    <p>Le formulaire est bon, mais la base de données bloque l'enregistrement pour cette raison exacte :</p>
                    <pre style='background:#000; color:#10B981; padding:20px; font-size:16px; overflow:auto;'>".$e->getMessage()."</pre>
                 </div>");
        }
    }
}

