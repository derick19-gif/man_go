<?php
namespace App\Core;
use PDO;
use Exception;

class WalletManager {
    
    /**
     * Récupère le portefeuille complet et génère une adresse crypto si elle manque
     */
    public static function getWallets($db, $userId) {
        $stmt = $db->prepare("SELECT credits_balance, commission_balance, wallet_id FROM user_wallets WHERE user_id = ?");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($wallet) {
            // S'il n'a pas encore d'adresse MAN GO Pay (anciens comptes), on lui en crée une
            if (empty($wallet['wallet_id'])) {
                $walletId = self::generateWalletId();
                $db->prepare("UPDATE user_wallets SET wallet_id = ? WHERE user_id = ?")->execute([$walletId, $userId]);
                $wallet['wallet_id'] = $walletId;
            }
            return $wallet;
        } else {
            // Création automatique
            $walletId = self::generateWalletId();
            $stmtInsert = $db->prepare("INSERT INTO user_wallets (user_id, wallet_id, credits_balance, commission_balance) VALUES (?, ?, 0, 0)");
            $stmtInsert->execute([$userId, $walletId]);
            return ['credits_balance' => 0, 'commission_balance' => 0, 'wallet_id' => $walletId];
        }
    }

    /**
     * Génère une adresse de portefeuille unique type Crypto (Ex: MGO-A1B2C3D4)
     */
    private static function generateWalletId() {
        return 'MGO-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * MOTEUR SÉCURISÉ : Ajoute des fonds
     */
    public static function addFunds($db, $userId, $amount, $currency = 'credit', $source, $description) {
        if ($amount <= 0) return false;
        if (!in_array($currency, ['credit', 'commission'])) throw new Exception("Devise inconnue.");
        return self::processTransaction($db, $userId, $amount, 'credit', $currency, $source, $description);
    }

    /**
     * MOTEUR SÉCURISÉ : Retire des fonds
     */
    public static function deductFunds($db, $userId, $amount, $currency = 'credit', $source, $description) {
        if ($amount <= 0) return false;
        $wallets = self::getWallets($db, $userId);
        $currentBalance = ($currency === 'credit') ? $wallets['credits_balance'] : $wallets['commission_balance'];
        
        if ($currentBalance < $amount) throw new Exception("Fonds insuffisants.");
        return self::processTransaction($db, $userId, $amount, 'debit', $currency, $source, $description);
    }

    /**
     * TRANSFERT P2P VIA ADRESSE (Avec prélèvement de commission réseau de 1%)
     */
    public static function transferCommissionsByWalletId($db, $senderId, $receiverWalletId, $amount) {
        if ($amount < 100) throw new Exception("Le montant minimum de transfert est de 100 FCFA.");

        try {
            $db->beginTransaction();

            // 1. Trouver l'ID du destinataire grâce à son adresse "MGO-..."
            $stmt = $db->prepare("SELECT user_id FROM user_wallets WHERE wallet_id = ?");
            $stmt->execute([strtoupper(trim($receiverWalletId))]);
            $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$receiver) throw new Exception("Adresse de portefeuille MAN GO Pay invalide ou introuvable.");
            $receiverId = $receiver['user_id'];

            if ($senderId == $receiverId) throw new Exception("Vous ne pouvez pas envoyer de l'argent à vous-même.");

            // 2. Calcul des frais (Ex: 1% de frais réseau, minimum 5 FCFA)
            $feePercentage = 1; 
            $feeAmount = max(5, ceil(($amount * $feePercentage) / 100)); // Minimum 5 FCFA de frais
            $totalToDeduct = $amount + $feeAmount; // Ce que l'expéditeur va réellement payer

            // 3. Vérifier le solde de l'expéditeur avant de déduire
            $wallets = self::getWallets($db, $senderId);
            if ($wallets['commission_balance'] < $totalToDeduct) {
                throw new Exception("Solde insuffisant. Il vous faut " . $totalToDeduct . " FCFA (dont " . $feeAmount . " FCFA de frais réseau).");
            }

            // 4. Débiter l'expéditeur (Montant + Frais)
            self::deductFunds($db, $senderId, $totalToDeduct, 'commission', 'p2p_transfer_out', "Transfert vers $receiverWalletId (Inclut $feeAmount FCFA de frais)");

            // 5. Créditer le destinataire (Montant net sans les frais)
            self::addFunds($db, $receiverId, $amount, 'commission', 'p2p_transfer_in', "Réception d'un transfert via MAN GO Pay");

            $db->commit();
            return ['success' => true, 'fee' => $feeAmount, 'net' => $amount];
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * TRANSACTION SQL BLINDÉE
     */
    private static function processTransaction($db, $userId, $amount, $type, $currency, $source, $description) {
        try {
            if (!$db->inTransaction()) { $db->beginTransaction(); $hasTransaction = true; } 
            else { $hasTransaction = false; }

            $wallets = self::getWallets($db, $userId);
            $currentBalance = ($currency === 'credit') ? $wallets['credits_balance'] : $wallets['commission_balance'];
            $newBalance = ($type === 'credit') ? ($currentBalance + $amount) : ($currentBalance - $amount);
            
            $column = ($currency === 'credit') ? 'credits_balance' : 'commission_balance';
            $stmtUpdate = $db->prepare("UPDATE user_wallets SET $column = ? WHERE user_id = ?");
            $stmtUpdate->execute([$newBalance, $userId]);

            $stmtHistory = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, currency, amount, balance_after, source, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtHistory->execute([$userId, $type, $currency, $amount, $newBalance, $source, $description]);

            if ($hasTransaction) $db->commit();
            return true;

        } catch (Exception $e) {
            if (isset($hasTransaction) && $hasTransaction) $db->rollBack();
            throw $e;
        }
    }
}