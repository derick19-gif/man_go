<?php
// core/Model.php

class Model {
    protected $db;
    protected $table;

    public function __construct() {
        // Tentative de récupération de l'instance de base de données
        if (class_exists('Database')) {
            $this->db = Database::getInstance()->getConnection();
        } else {
            // Chargement de secours si Database.php n'est pas encore inclus
            $dbPath = __DIR__ . '/Database.php';
            if (!file_exists($dbPath) && defined('APP_PATH')) {
                $dbPath = APP_PATH . '/core/Database.php';
            }
            
            if (file_exists($dbPath)) {
                require_once $dbPath;
                if (class_exists('Database')) {
                    $this->db = Database::getInstance()->getConnection();
                }
            }
        }
    }

    /**
     * Récupère tous les enregistrements de la table
     */
    public function all() {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un enregistrement par son ID
     */
    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime un enregistrement par son ID
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}