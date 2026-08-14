<?php
// modules/stand/models/Stand.php

// Chargement sécurisé de core/Model.php
if (!class_exists('Model')) {
    if (defined('APP_PATH') && file_exists(APP_PATH . '/core/Model.php')) {
        require_once APP_PATH . '/core/Model.php';
    } else {
        $fallbackPath = __DIR__ . '/../../../core/Model.php';
        if (file_exists($fallbackPath)) {
            require_once $fallbackPath;
        } else {
            throw new Exception("Impossible de charger la classe parente Model depuis " . __DIR__);
        }
    }
}

class Stand extends Model {
    protected $table = 'stands';

    public function getActiveStands($search = '', $location = '', $category = '', $limit = 12, $offset = 0) {
        $whereClauses = ["s.status = 'active'"];
        $params = [];

        if (!empty($search)) {
            $whereClauses[] = "(s.name LIKE :search OR s.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if (!empty($location)) {
            $whereClauses[] = "s.city LIKE :location";
            $params[':location'] = '%' . $location . '%';
        }

        if (!empty($category)) {
            $whereClauses[] = "s.category = :category";
            $params[':category'] = $category;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $sql = "
            SELECT s.*, COUNT(a.id) AS total_annonces 
            FROM {$this->table} s
            LEFT JOIN ads a ON a.stand_id = s.id AND a.status = 'active'
            WHERE {$whereSql}
            GROUP BY s.id
            ORDER BY s.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countActiveStands($search = '', $location = '', $category = '') {
        $whereClauses = ["status = 'active'"];
        $params = [];

        if (!empty($search)) {
            $whereClauses[] = "(name LIKE :search OR description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if (!empty($location)) {
            $whereClauses[] = "city LIKE :location";
            $params[':location'] = '%' . $location . '%';
        }

        if (!empty($category)) {
            $whereClauses[] = "category = :category";
            $params[':category'] = $category;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$whereSql}");
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }

    public function getCategories() {
        $stmt = $this->db->query("
            SELECT DISTINCT category 
            FROM {$this->table} 
            WHERE status = 'active' 
              AND category IS NOT NULL 
              AND category != '' 
            ORDER BY category ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}