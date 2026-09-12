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

    public function createStand($data) {
        if (!$this->db) return false;
        $sql = "INSERT INTO {$this->table} (user_id, name, description, category, city, address, phone, logo_url, banner_url, status, created_at) 
                VALUES (:user_id, :name, :description, :category, :city, :address, :phone, :logo_url, :banner_url, 'active', NOW())";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user_id'     => $data['user_id'],
            ':name'        => $data['name'],
            ':description' => $data['description'],
            ':category'    => $data['category'],
            ':city'        => $data['city'],
            ':address'     => $data['address'],
            ':phone'       => $data['phone'],
            ':logo_url'    => $data['logo_url'] ?? null,
            ':banner_url'  => $data['banner_url'] ?? null
        ]);
    }
}