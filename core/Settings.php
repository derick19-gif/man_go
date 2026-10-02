<?php
namespace App\Core;

use PDO;
use Exception;

class Settings {
    private static $settings = null;

    /**
     * Charge tous les paramètres depuis la base de données (une seule fois par page)
     */
    public static function loadAll() {
        if (self::$settings === null) {
            try {
                $db = Database::connect();
                $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
                self::$settings = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    self::$settings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Exception $e) {
                // Valeurs de secours en cas d'erreur de base de données
                self::$settings = [
                    'premium_price' => '5000',
                    'starter_price' => '2500',
                    'faucet_reward_amount' => '2',
                    'faucet_timer_minutes' => '60',
                    'referral_commission_percent' => '20'
                ];
            }
        }
    }

    /**
     * Récupère la valeur d'un paramètre spécifique
     */
    public static function get($key, $default = null) {
        self::loadAll();
        return isset(self::$settings[$key]) ? self::$settings[$key] : $default;
    }
}