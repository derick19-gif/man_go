<?php
// classes/Upload.php

class Upload {
    private static array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    private static array $allowedMimeTypes  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private static int $maxSizeBytes        = 5 * 1024 * 1024; // 5 Mo

    /**
     * Traite et tlverse une image vers un sous-dossier spcifi
     *
     * @param array $file Le tableau $_FILES['input_name']
     * @param string $targetSubfolder Nom du sous-dossier dans uploads/ (ex: 'products', 'stands', 'users')
     * @return array [success => bool, filePath => string|null, error => string|null]
     */
    public static function processImage(array $file, string $targetSubfolder = 'products'): array {
        // Vrifier si un fichier a bien t soumis sans erreur
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'filePath' => null, 'error' => 'Paramtres de fichier invalides.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'filePath' => null, 'error' => self::getUploadErrorMessage($file['error'])];
        }

        // 1. Vrification de la taille
        if ($file['size'] > self::$maxSizeBytes) {
            return ['success' => false, 'filePath' => null, 'error' => 'Le fichier dpasse la taille maximale autorise (5 Mo).'];
        }

        // 2. Vrification de l'extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, self::$allowedExtensions, true)) {
            return ['success' => false, 'filePath' => null, 'error' => 'Format de fichier non autoris (Seuls JPG, PNG, WEBP, GIF sont accepts).'];
        }

        // 3. Vrification du type MIME rel du fichier (Scurit)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, self::$allowedMimeTypes, true)) {
            return ['success' => false, 'filePath' => null, 'error' => 'Type de fichier non valide.'];
        }

        // 4. Prparation du chemin de destination
        $uploadDir = __DIR__ . '/../uploads/' . trim($targetSubfolder, '/') . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 5. Gnration d'un nom unique scuris
        $newFileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
        $destinationPath = $uploadDir . $newFileName;

        // 6. Dplacement du fichier temporaire
        if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
            return ['success' => false, 'filePath' => null, 'error' => 'chec de l\'enregistrement du fichier sur le serveur.'];
        }

        // Retourne le chemin relatif  enregistrer en Base de Donnes
        $relativePath = 'uploads/' . trim($targetSubfolder, '/') . '/' . $newFileName;

        return [
            'success'  => true,
            'filePath' => $relativePath,
            'error'    => null
        ];
    }

    /**
     * Supprime un fichier existant du serveur
     */
    public static function deleteFile(string $relativePath): bool {
        $fullPath = __DIR__ . '/../' . ltrim($relativePath, '/');
        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    private static function getUploadErrorMessage(int $errorCode): string {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier tlvers est trop volumineux.',
            UPLOAD_ERR_PARTIAL                         => 'Le fichier n\'a t que partiellement tlvers.',
            UPLOAD_ERR_NO_FILE                        => 'Aucun fichier n\'a t tlvers.',
            UPLOAD_ERR_NO_TMP_DIR                     => 'Dossier temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE                     => 'chec de l\'criture du fichier sur le disque.',
            default                                   => 'Erreur inconnue lors du tlversement.'
        };
    }
}