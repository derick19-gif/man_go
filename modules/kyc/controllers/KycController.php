<?php
namespace Modules\Kyc\Controllers;

require_once __DIR__ . '/../Models/KycModel.php';

use Modules\Kyc\Models\KycModel;
use Exception;
use DateTime;

class KycController
{
    private KycModel $kycModel;
    private string $uploadDir = __DIR__ . '/../../../public/uploads/kyc/';

    public function __construct()
    {
        $this->kycModel = new KycModel();
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Vérifie si l'utilisateur peut publier (Gère l'expiration !)
     */
    public static function checkAccess(): bool
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) return false;

        // Exemption Admin
        if (!empty($_SESSION['is_admin']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) return true;

        $model = new KycModel();
        $kyc = $model->getByUserId($_SESSION['user_id']);

        // 1. Pas de KYC ou non approuvé -> Refusé
        if (!$kyc || $kyc['status'] !== 'approved') return false;

        // 2. Vérification de l'expiration
        if (!empty($kyc['id_expiration_date'])) {
            $expiryDate = new DateTime($kyc['id_expiration_date']);
            $now = new DateTime();

            // Si expiré -> Refusé
            if ($expiryDate < $now) {
                $_SESSION['kyc_error'] = "Votre pièce d'identité est expirée. Veuillez soumettre une nouvelle pièce valide.";
                return false;
            }

            // Avertissement si expiration dans moins de 30 jours
            $interval = $now->diff($expiryDate);
            if ($interval->days <= 30) {
                $_SESSION['kyc_warning'] = "Attention : Votre pièce d'identité expire dans {$interval->days} jours. Pensez à la mettre à jour pour ne pas perdre vos droits de publication.";
            }
        }

        return true;
    }

    public function index()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $kycData = $this->kycModel->getByUserId($userId);
        
        // Inclure la vue
        require_once __DIR__ . '/../views/index.php';
    }

    public function submit()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        try {
            if (empty($_POST['id_issue_date']) || empty($_POST['id_expiration_date'])) {
                throw new Exception("Les dates de délivrance et d'expiration de la pièce sont obligatoires.");
            }

            // Vérification logique : expiration doit être > délivrance
            if (strtotime($_POST['id_expiration_date']) <= strtotime($_POST['id_issue_date'])) {
                throw new Exception("La date d'expiration doit être ultérieure à la date de délivrance.");
            }
            if (strtotime($_POST['id_expiration_date']) < time()) {
                throw new Exception("Cette pièce d'identité est déjà expirée.");
            }

            if (empty($_FILES['id_document_path']) || $_FILES['id_document_path']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("La pièce d'identité principale est obligatoire.");
            }

            $idDocPath = $this->handleSecureUpload($_FILES['id_document_path']);
            $accountType = $_POST['account_type'] ?? 'individual';
            
            // ... (Ici le reste de votre logique d'entreprise reste identique)
            $companyName = $_POST['company_name'] ?? null;
            $registrationNumber = $_POST['registration_number'] ?? null;
            $taxId = $_POST['tax_id'] ?? null;
            $secondaryManagerName = $_POST['secondary_manager_name'] ?? null;
            $secondaryManagerIdCard = null;
            $additionalDocPath = null;

            if (!empty($_FILES['secondary_manager_id_card']) && $_FILES['secondary_manager_id_card']['error'] === UPLOAD_ERR_OK) {
                $secondaryManagerIdCard = $this->handleSecureUpload($_FILES['secondary_manager_id_card']);
            }
            if (!empty($_FILES['additional_document_path']) && $_FILES['additional_document_path']['error'] === UPLOAD_ERR_OK) {
                $additionalDocPath = $this->handleSecureUpload($_FILES['additional_document_path']);
            }

            $primaryManagerName = ($accountType === 'company') ? ($_POST['primary_manager_name'] ?? '') : ($_SESSION['user_name'] ?? 'Particulier');
            $primaryManagerIdCardNum = $_POST['primary_manager_id_card'] ?? '';

            $formData = [
                'account_type' => $accountType,
                'company_name' => $companyName,
                'registration_number' => $registrationNumber,
                'tax_id' => $taxId,
                'primary_manager_name' => $primaryManagerName,
                'primary_manager_id_card' => $primaryManagerIdCardNum,
                'id_issue_date' => $_POST['id_issue_date'],
                'id_expiration_date' => $_POST['id_expiration_date'],
                'secondary_manager_name' => $secondaryManagerName,
                'secondary_manager_id_card' => $secondaryManagerIdCard,
                'id_document_path' => $idDocPath,
                'additional_document_path' => $additionalDocPath
            ];

            $this->kycModel->submit($_SESSION['user_id'], $formData);
            
            $_SESSION['success_message'] = "Votre dossier KYC a été soumis avec succès.";
            header('Location: ' . BASE_URL . '/verification');
            exit;

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: ' . BASE_URL . '/verification');
            exit;
        }
    }

    private function handleSecureUpload(array $file): string
    {
        // ... (Gardez votre fonction handleSecureUpload actuelle)
        $newFileName = 'kyc_' . uniqid() . '_' . time() . '.pdf'; // Simplifié pour l'exemple
        $destination = $this->uploadDir . $newFileName;
        move_uploaded_file($file['tmp_name'], $destination);
        return 'public/uploads/kyc/' . $newFileName;
    }
}