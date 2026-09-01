<?php
namespace Modules\Kyc\Controllers;

use Modules\Kyc\Models\KycModel;
use Exception;

class KycController
{
    private KycModel $kycModel;

    // Dossier cible pour les uploads KYC (hors accès direct public si possible, ou dans public/uploads/kyc)
    private string $uploadDir = __DIR__ . '/../../../public/uploads/kyc/';

    public function __construct()
    {
        $this->kycModel = new KycModel();
        
        // S'assurer que le dossier d'upload existe
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Vérifie si l'utilisateur a l'accès requis (Exemption Admin intégrée)
     */
    public static function checkAccess(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Non connectés -> Refusé
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // 2. EXEMPTION ADMIN : Si le rôle est admin, on autorise immédiatement
        if (!empty($_SESSION['is_admin']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
            return true;
        }

        // 3. Vérification du statut KYC en base pour les utilisateurs normaux
        $model = new KycModel();
        $kyc = $model->getByUserId($_SESSION['user_id']);

        if (!$kyc || $kyc['status'] !== 'approved') {
            return false; 
        }

        return true;
    }

    /**
     * Affiche la vue du formulaire KYC
     */
    public function index()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $kycData = $this->kycModel->getByUserId($userId);
        
        require_once __DIR__ . '/../views/index.php';
    }

    /**
     * Traite la soumission du formulaire KYC avec sécurité et uploads multiples
     */
    public function submit()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $accountType = $_POST['account_type'] ?? 'individual';

        try {
            // 1. Validation des champs obligatoires généraux
            if (empty($_FILES['id_document_path']) || $_FILES['id_document_path']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("La pièce d'identité principale est obligatoire.");
            }

            // Gestion de l'upload de la pièce d'identité principale
            $idDocPath = $this->handleSecureUpload($_FILES['id_document_path']);

            $companyName = null;
            $registrationNumber = null;
            $taxId = null;
            $primaryManagerName = '';
            $primaryManagerIdCard = '';
            $secondaryManagerName = null;
            $secondaryManagerIdCard = null;
            $additionalDocPath = null;

            // 2. Si c'est une entreprise, validation de la double traçabilité et des pièces spécifiques
            if ($accountType === 'company') {
                $companyName = trim($_POST['company_name'] ?? '');
                $registrationNumber = trim($_POST['registration_number'] ?? '');
                $taxId = trim($_POST['tax_id'] ?? '');
                $primaryManagerName = trim($_POST['primary_manager_name'] ?? '');
                $secondaryManagerName = trim($_POST['secondary_manager_name'] ?? '');

                if (empty($companyName) || empty($registrationNumber) || empty($primaryManagerName) || empty($secondaryManagerName)) {
                    throw new Exception("Pour un compte entreprise, tous les champs d'identification et la double traçabilité des co-responsables sont obligatoires.");
                }

                // Upload de la pièce du responsable principal
                if (empty($_FILES['primary_manager_id_card']) || $_FILES['primary_manager_id_card']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("La pièce justificative du responsable principal est requise.");
                }
                $primaryManagerIdCard = $this->handleSecureUpload($_FILES['primary_manager_id_card']);

                // Upload de la pièce du second responsable (Traçabilité obligatoire)
                if (empty($_FILES['secondary_manager_id_card']) || $_FILES['secondary_manager_id_card']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("La pièce justificative du second responsable est obligatoire pour la gouvernance de l'entreprise.");
                }
                $secondaryManagerIdCard = $this->handleSecureUpload($_FILES['secondary_manager_id_card']);

                // Document additionnel (Statuts ou RCCM) optionnel mais recommandé
                if (!empty($_FILES['additional_document_path']) && $_FILES['additional_document_path']['error'] === UPLOAD_ERR_OK) {
                    $additionalDocPath = $this->handleSecureUpload($_FILES['additional_document_path']);
                }
            } else {
                // Pour un particulier, le responsable principal c'est lui-même par défaut
                $primaryManagerName = $_SESSION['user_name'] ?? 'Particulier';
                $primaryManagerIdCard = $idDocPath;
            }

            // 3. Préparation des données pour le modèle
            $formData = [
                'account_type' => $accountType,
                'company_name' => $companyName,
                'registration_number' => $registrationNumber,
                'tax_id' => $taxId,
                'primary_manager_name' => $primaryManagerName,
                'primary_manager_id_card' => $primaryManagerIdCard,
                'secondary_manager_name' => $secondaryManagerName,
                'secondary_manager_id_card' => $secondaryManagerIdCard,
                'id_document_path' => $idDocPath,
                'additional_document_path' => $additionalDocPath
            ];

            // 4. Enregistrement en base de données via le Modèle
            $success = $this->kycModel->submit($userId, $formData);

            if ($success) {
                $_SESSION['success_message'] = "Votre dossier KYC a été soumis avec succès et est en attente de validation.";
                header('Location: ' . BASE_URL . '/kyc');
                exit;
            } else {
                throw new Exception("Erreur lors de l'enregistrement en base de données.");
            }

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: ' . BASE_URL . '/kyc');
            exit;
        }
    }

    /**
     * Méthode privée pour sécuriser l'upload de fichiers (Vérification MIME, extension et nommage)
     */
    private function handleSecureUpload(array $file): string
    {
        $maxFileSize = 5 * 1024 * 1024; // 5 Mo maximum
        if ($file['size'] > $maxFileSize) {
            raise_error:
            throw new Exception("Le fichier dépasse la taille maximale autorisée de 5 Mo.");
        }

        // Extensions autorisées strictes
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Format de fichier non autorisé. Utilisez uniquement JPG, JPEG, PNG ou PDF.");
        }

        // Vérification MIME réelle du fichier
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new Exception("Le type MIME du fichier est invalide ou corrompu.");
        }

        // Génération d'un nom de fichier unique et infalsifiable
        $newFileName = 'kyc_' . uniqid() . '_' . time() . '.' . $fileExtension;
        $destination = $this->uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception("Échec du déplacement du fichier téléchargé sur le serveur.");
        }

        // Retourne le chemin relatif pour stockage en BDD
        return 'public/uploads/kyc/' . $newFileName;
    }
}