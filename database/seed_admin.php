<?php
// ============================================================================
// MAN GO - Seed Admin Account
// Initialisation / Rinitialisation du compte Super Admin
// ============================================================================

define('APP_PATH', dirname(__DIR__));

$configFile = APP_PATH . '/config.php';
if (!file_exists($configFile)) {
    die("Fichier config.php introuvable dans " . APP_PATH);
}
require_once $configFile;

try {
    $pdo = getDBConnection();

    // Informations officielles issues du cahier des charges / profil
    $phone = '96110013';
    $pin = '190719';
    $email = 'admin@mango-app.com';
    $firstName = 'Komlan Drick Richard';
    $lastName = 'SOSSOU';
    $passwordHash = password_hash($pin, PASSWORD_BCRYPT);

    $pdo->beginTransaction();

    // S'assurer que le rle Super Admin existe
    $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE id = 1 LIMIT 1");
    $stmtRole->execute();
    if (!$stmtRole->fetch()) {
        $pdo->exec("INSERT INTO roles (id, name, description) VALUES (1, 'Super Admin', 'Administrateur systme complet')");
    }

    // Vrifier si l'utilisateur existe dj
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? OR email = ? LIMIT 1");
    $stmt->execute([$phone, $email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmtUpdate = $pdo->prepare("
            UPDATE users 
            SET role_id = 1, 
                firstname = ?, 
                lastname = ?, 
                password_hash = ?, 
                is_active = 1, 
                is_verified = 1, 
                kyc_status = 'APPROVED',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$firstName, $lastName, $passwordHash, $existing['id']]);
        echo "Compte administrateur (ID: {$existing['id']}) rinitialis avec succs.\n";
    } else {
        $stmtInsert = $pdo->prepare("
            INSERT INTO users (role_id, firstname, lastname, email, phone, password_hash, is_active, is_verified, kyc_status, created_at)
            VALUES (1, ?, ?, ?, ?, ?, 1, 1, 'APPROVED', NOW())
        ");
        $stmtInsert->execute([$firstName, $lastName, $email, $phone, $passwordHash]);
        $newId = $pdo->lastInsertId();
        echo "Compte administrateur cr•••••••• avec succs (ID: {$newId}).\n";
    }

    $pdo->commit();

    echo "--------------------------------------------------\n";
    echo "Identifiants de connexion :\n";
    echo "Nom : " . $lastName . " " . $firstName . "\n";
    echo "Tlphone : " . $phone . "\n";
    echo "PIN / Password : " . $pin . "\n";
    echo "--------------------------------------------------\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erreur d'initialisation de l'administrateur : " . $e->getMessage() . "\n");
}