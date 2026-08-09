<?php
// Chargement de l'environnement
define('APP_PATH', __DIR__);
require_once APP_PATH . '/config/config.php';
require_once APP_PATH . '/core/Database.php';

try {
    $db = Database::connect();

    // Informations du compte Admin
    $email = 'admin@mango.com';
    $passwordClair = '123456';
    $passwordHashed = password_hash($passwordClair, PASSWORD_BCRYPT);
    $firstName = 'Admin';
    $lastName = 'SOSSOU';

    // 1. Vérifier si l'administrateur existe déjà dans la table users
    $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmtCheck->execute(['email' => $email]);
    $existingUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        $userId = $existingUser['id'];
        // Mise à jour du mot de passe et activation
        $stmtUpdate = $db->prepare("
            UPDATE users 
            SET password = :password, first_name = :first_name, last_name = :last_name, is_active = 1, is_verified = 1, updated_at = NOW()
            WHERE id = :id
        ");
        $stmtUpdate->execute([
            'password'   => $passwordHashed,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'id'         => $userId
        ]);
    } else {
        // Insertion conforme aux colonnes de ta table users
        $stmtInsert = $db->prepare("
            INSERT INTO users (
                uuid, first_name, last_name, email, password, is_active, is_verified, created_at, updated_at
            ) VALUES (
                UUID(), :first_name, :last_name, :email, :password, 1, 1, NOW(), NOW()
            )
        ");
        $stmtInsert->execute([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'password'   => $passwordHashed
        ]);
        $userId = $db->lastInsertId();
    }

    // 2. Gestion des Rôles (Attribution du rôle 'admin')
    try {
        // Si une table 'roles' existe, on récupère l'ID du rôle admin
        $stmtRoleId = $db->prepare("SELECT id FROM roles WHERE slug = 'admin' OR name = 'admin' LIMIT 1");
        $stmtRoleId->execute();
        $role = $stmtRoleId->fetch(PDO::FETCH_ASSOC);

        if ($role && isset($role['id'])) {
            $roleId = $role['id'];
            $stmtRoleLnk = $db->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
            $stmtRoleLnk->execute(['user_id' => $userId, 'role_id' => $roleId]);
        }
    } catch (Exception $e) {
        // Sécurité si les tables de rôles sont organisées différemment
    }

    echo "<div style='font-family: sans-serif; padding: 20px; border: 2px solid #22c55e; background: #f0fdf4; border-radius: 8px; max-width: 600px; margin: 40px auto;'>";
    echo "<h2 style='color: #15803d; margin-top: 0;'>o. Compte Administrateur prêt !</h2>";
    echo "<p><strong>Email :</strong> admin@mango.com</p>";
    echo "<p><strong>Mot de passe :</strong> 123456</p>";
    echo "<p style='margin-top: 20px;'><a href='http://localhost/man_go/login' style='display: inline-block; padding: 12px 24px; background: #4f46e5; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;'>Aller à la page de connexion (/login)</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='font-family: sans-serif; padding: 20px; border: 2px solid #ef4444; background: #fef2f2; border-radius: 8px; max-width: 600px; margin: 40px auto;'>";
    echo "<h2 style='color: #b91c1c; margin-top: 0;'>O Erreur lors de l'exécution :</h2>";
    echo "<pre style='background: #fff; padding: 10px; border-radius: 4px; overflow-x: auto;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}