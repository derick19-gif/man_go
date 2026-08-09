<?php
// includes/NotificationManager.php

class NotificationManager {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Crer une nouvelle notification
    public function createNotification(int $userId, string $title, string $message, string $type = 'info', ?string $link = null): bool {
        try {
            $sql = "INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) 
                    VALUES (:user_id, :type, :title, :message, :link, 0, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':user_id' => $userId,
                ':type'    => $type,
                ':title'   => $title,
                ':message' => $message,
                ':link'    => $link
            ]);
        } catch (PDOException $e) {
            error_log("Erreur NotificationManager : " . $e->getMessage());
            return false;
        }
    }

    // Rcuprer les notifications rcentes d'un utilisateur
    public function getUserNotifications(int $userId, int $limit = 10): array {
        try {
            $sql = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur NotificationManager (getUserNotifications) : " . $e->getMessage());
            return [];
        }
    }

    // Compter les notifications non lues
    public function getUnreadCount(int $userId): int {
        try {
            $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Erreur NotificationManager (getUnreadCount) : " . $e->getMessage());
            return 0;
        }
    }

    // Marquer une notification comme lue
    public function markAsRead(int $notificationId, int $userId): bool {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Erreur NotificationManager (markAsRead) : " . $e->getMessage());
            return false;
        }
    }
}