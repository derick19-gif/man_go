<?php
namespace Modules\Kyc\Models;

// AJOUTEZ CE BLOC POUR LIER LA BASE DE DONNÉES :
$dbPath = dirname(dirname(dirname(__DIR__))) . '/core/Database.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
}

use App\Core\Database;
use PDO;

class KycModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance(); // Ou Database::connect() selon votre configuration
    }

    public function getByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM user_kyc WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function submit(int $userId, array $data): bool
    {
        $existing = $this->getByUserId($userId);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE user_kyc SET 
                    account_type = :account_type,
                    company_name = :company_name,
                    registration_number = :registration_number,
                    tax_id = :tax_id,
                    primary_manager_name = :primary_manager_name,
                    primary_manager_id_card = :primary_manager_id_card,
                    id_issue_date = :id_issue_date,
                    id_expiration_date = :id_expiration_date,
                    secondary_manager_name = :secondary_manager_name,
                    secondary_manager_id_card = :secondary_manager_id_card,
                    id_document_path = :id_document_path,
                    additional_document_path = :additional_document_path,
                    status = 'pending',
                    updated_at = NOW()
                WHERE user_id = :user_id
            ");
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO user_kyc (
                    user_id, account_type, company_name, registration_number, tax_id,
                    primary_manager_name, primary_manager_id_card, id_issue_date, id_expiration_date, 
                    secondary_manager_name, secondary_manager_id_card, id_document_path, additional_document_path, status, created_at
                ) VALUES (
                    :user_id, :account_type, :company_name, :registration_number, :tax_id,
                    :primary_manager_name, :primary_manager_id_card, :id_issue_date, :id_expiration_date, 
                    :secondary_manager_name, :secondary_manager_id_card, :id_document_path, :additional_document_path, 'pending', NOW()
                )
            ");
        }

        return $stmt->execute([
            ':user_id' => $userId,
            ':account_type' => $data['account_type'],
            ':company_name' => $data['company_name'] ?? null,
            ':registration_number' => $data['registration_number'] ?? null,
            ':tax_id' => $data['tax_id'] ?? null,
            ':primary_manager_name' => $data['primary_manager_name'],
            ':primary_manager_id_card' => $data['primary_manager_id_card'],
            ':id_issue_date' => $data['id_issue_date'],
            ':id_expiration_date' => $data['id_expiration_date'],
            ':secondary_manager_name' => $data['secondary_manager_name'] ?? null,
            ':secondary_manager_id_card' => $data['secondary_manager_id_card'] ?? null,
            ':id_document_path' => $data['id_document_path'],
            ':additional_document_path' => $data['additional_document_path'] ?? null
        ]);
    }
}