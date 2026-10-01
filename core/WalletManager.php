<?php
namespace App\Core;
use PDO;
use Exception;

class WalletManager {
    
    /**
     * Récupère le solde actuel d'un utilisateur, et lui crée un portefeuille s'il n'en a pas
     */
    public static function getBalance($db, $userId) {
        $stmt = $db->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($wallet) {
            return (int) $wallet['balance'];
        } else {
            // Création automatique du portefeuille à 0 s'il n'existe pas encore
            $stmtInsert = $db->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, 0)");
            $stmtInsert->execute([$userId]);
            return 0;
        }
    }

    /**
     * Ajoute des crédits (Créditer)
     */
    public static function addCredits($db, $userId, $amount, $source, $description) {
        if ($amount <= 0) return false;
        return self::processTransaction($db, $userId, $amount, 'credit', $source, $description);
    }

    /**
     * Retire des crédits (Débiter)
     */
    public static function deductCredits($db, $userId, $amount, $source, $description) {
        if ($amount <= 0) return false;
        
        // Vérifier s'il a assez de crédits
        $currentBalance = self::getBalance($db, $userId);
        if ($currentBalance < $amount) {
            throw new Exception("Fonds insuffisants en crédits MAN GO.");
        }
        
        return self::processTransaction($db, $userId, $amount, 'debit', $source, $description);
    }

    /**
     * Le moteur central ultra-sécurisé qui gère l'argent virtuel
     */
    private static function processTransaction($db, $userId, $amount, $type, $source, $description) {
        try {
            $db->beginTransaction();

            $currentBalance = self::getBalance($db, $userId);
            
            if ($type === 'credit') {
                $newBalance = $currentBalance + $amount;
            } else {
                $newBalance = $currentBalance - $amount;
            }

            // 1. Mise à jour du solde
            $stmtUpdate = $db->prepare("UPDATE user_wallets SET balance = ? WHERE user_id = ?");
            $stmtUpdate->execute([$newBalance, $userId]);

            // 2. Écriture dans l'historique (Traçabilité)
            $stmtHistory = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_after, source, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtHistory->execute([$userId, $type, $amount, $newBalance, $source, $description]);

            $db->commit();
            return true;

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}