<?php
// =========================================================================
// PROFIL UTILISATEUR UNIVERSEL - MAN GO (Client & Vendeur)
// =========================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

Session::init();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// Vérification de connexion
if (!Session::isAuthenticated()) {
    header("Location: $baseUrl/login.php");
    exit();
}

$db = \App\Core\Database::connect();
$userId = (int)Session::get('user_id');
$userRole = Session::get('user_role');

$message = '';
$messageType = ''; 

// Lien de retour dynamique selon le rôle
$dashboardLink = ($userRole === 'vendor') ? "$baseUrl/vendor_dir/dashboard.php" : "$baseUrl/client/views/dashboard.php";

// =========================================================================
// TRAITEMENT DU FORMULAIRE DE PROFIL
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Mise à jour des Infos Personnelles
    if ($_POST['action'] === 'update_profile') {
        $fullName = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS));
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');

        if ($fullName && $email) {
            $parts = explode(' ', $fullName, 2);
            $firstname = $parts[0];
            $lastname = $parts[1] ?? '';

            try {
                $stmt = $db->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$firstname, $lastname, $email, $phone, $userId]);
                
                Session::set('user_name', $fullName);
                Session::set('user_email', $email);
                
                $message = "Vos informations personnelles ont été mises à jour avec succès.";
                $messageType = 'success';
            } catch (PDOException $e) {
                // Gestion de l'erreur d'email/tel en doublon
                if ($e->getCode() == 23000) { 
                    $message = "Cet email ou ce numéro est déjà utilisé par un autre compte.";
                } else {
                    $message = "Une erreur est survenue lors de la mise à jour.";
                }
                $messageType = 'error';
            }
        } else {
            $message = "Le format de l'email est invalide ou des champs sont vides.";
            $messageType = 'error';
        }
    }

    // 2. Mise à jour de la Sécurité (Mot de passe)
    elseif ($_POST['action'] === 'update_security') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Vérification de l'ancien mot de passe
        $stmtUser = $db->prepare("SELECT password_hash, password FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userCheck = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $storedHash = $userCheck['password_hash'] ?? $userCheck['password'] ?? '';

        if (!password_verify($current_password, $storedHash)) {
            $message = "L'ancien mot de passe est incorrect.";
            $messageType = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = "Les nouveaux mots de passe ne correspondent pas.";
            $messageType = 'error';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $new_password)) {
            $message = "Le nouveau mot de passe est trop faible. Suivez les critères.";
            $messageType = 'error';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$hashed, $userId])) {
                $message = "Votre mot de passe a été modifié avec succès et est sécurisé.";
                $messageType = 'success';
            }
        }
    }

    // 3. Suppression Logique du Compte (Soft Delete)
    elseif ($_POST['action'] === 'delete_account') {
        $deletedEmail = 'deleted_' . time() . '_' . uniqid();
        $stmt = $db->prepare("UPDATE users SET status = 'inactive', email = ? WHERE id = ?");
        if ($stmt->execute([$deletedEmail, $userId])) {
            Session::destroy();
            header("Location: $baseUrl/?msg=account_deleted");
            exit();
        }
    }
}

// =========================================================================
// RÉCUPÉRATION DES DONNÉES ACTUELLES
// =========================================================================
$stmtUser = $db->prepare("SELECT TRIM(CONCAT(IFNULL(firstname,''), ' ', IFNULL(lastname,''))) AS name, email, phone FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

$pageTitle = "Mon Profil - MAN GO";
// Require header dynamique selon le dossier themes ou views
$headerPath = __DIR__ . '/themes/default/templates/layouts/header.php';
if (!file_exists($headerPath)) { $headerPath = __DIR__ . '/app/views/layouts/header.php'; }
if (file_exists($headerPath)) { require_once $headerPath; }
?>

<div class="bg-slate-50 min-h-screen pt-28 pb-24 font-sans text-slate-800">
    <main class="max-w-4xl mx-auto px-4 sm:px-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Profil & Sécurité</h1>
                <p class="text-slate-500 mt-1">Gérez vos informations personnelles de connexion.</p>
            </div>
            <a href="<?= $dashboardLink ?>" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-900 hover:text-white font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center self-start sm:self-auto">
                <i class="fa-solid fa-arrow-left mr-2"></i> Retour au Dashboard
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="p-4 mb-8 rounded-xl flex items-center font-bold text-sm shadow-sm <?= $messageType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                <i class="<?= $messageType === 'success' ? 'fa-solid fa-circle-check text-emerald-500' : 'fa-solid fa-triangle-exclamation text-rose-500' ?> mr-3 text-lg"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <!-- INFORMATIONS PERSONNELLES -->
            <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm h-fit">
                <div class="flex items-center mb-6">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mr-3 text-lg"><i class="fa-solid fa-id-badge"></i></div>
                    <h2 class="text-xl font-black text-slate-900">Mes Informations</h2>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Nom Complet</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fa-solid fa-user"></i></span>
                                <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-900 pl-11 pr-4 py-3.5 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition text-sm font-semibold">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Adresse Email</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-900 pl-11 pr-4 py-3.5 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition text-sm font-semibold">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Téléphone</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fa-solid fa-phone"></i></span>
                                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 text-slate-900 pl-11 pr-4 py-3.5 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition text-sm font-semibold">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="w-full bg-slate-900 hover:bg-amber-500 text-white hover:text-slate-900 font-black py-4 px-6 rounded-xl transition-all shadow-md text-sm uppercase tracking-wide flex justify-center items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Enregistrer les infos
                        </button>
                    </div>
                </form>
            </div>

            <!-- SÉCURITÉ DU COMPTE -->
            <div class="space-y-8">
                <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mr-3 text-lg"><i class="fa-solid fa-shield-halved"></i></div>
                        <h2 class="text-xl font-black text-slate-900">Mot de passe</h2>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_security">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Ancien mot de passe</label>
                                <div class="relative">
                                    <input type="password" id="cur_pwd" name="current_password" required class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 pr-10 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition text-sm">
                                    <button type="button" onclick="togglePwd('cur_pwd', 'eye_cur')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-500 transition"><i id="eye_cur" class="fa-solid fa-eye"></i></button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Nouveau mot de passe</label>
                                <div class="relative">
                                    <input type="password" id="new_pwd" name="new_password" required placeholder="1 Majuscule, 1 Chiffre, 1 Symbole" class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 pr-10 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition text-sm font-semibold">
                                    <button type="button" onclick="togglePwd('new_pwd', 'eye_new')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-500 transition"><i id="eye_new" class="fa-solid fa-eye"></i></button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Confirmer le nouveau</label>
                                <div class="relative">
                                    <input type="password" id="conf_pwd" name="confirm_password" required class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 pr-10 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition text-sm font-semibold">
                                    <button type="button" onclick="togglePwd('conf_pwd', 'eye_conf')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-500 transition"><i id="eye_conf" class="fa-solid fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" class="w-full bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-500 hover:text-white font-black py-4 px-6 rounded-xl transition-all shadow-sm text-sm uppercase tracking-wide">
                                Changer le mot de passe
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ZONE DE DANGER (Soft Delete) -->
                <div class="bg-white rounded-3xl border border-rose-200 p-8 shadow-sm relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-1 h-full bg-rose-500"></div>
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mr-3 text-lg"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <h2 class="text-xl font-black text-slate-900">Zone de Danger</h2>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-500 mb-5 leading-relaxed">
                        Désactiver votre compte masquera vos informations et vos annonces du marché public. Vos données de transaction seront conservées pour des raisons légales.
                    </p>
                    <form method="POST" action="" onsubmit="return confirm('⚠️ Êtes-vous certain de vouloir désactiver définitivement votre compte ?');">
                        <input type="hidden" name="action" value="delete_account">
                        <button type="submit" class="w-full bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-500 hover:text-white font-bold py-3 px-6 rounded-xl transition-all shadow-sm flex justify-center items-center text-sm">
                            <i class="fa-solid fa-user-xmark mr-2"></i> Désactiver mon compte
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
    function togglePwd(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash", "text-emerald-500");
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye-slash", "text-emerald-500");
            icon.classList.add("fa-eye");
        }
    }
</script>

<?php 
$footerPath = __DIR__ . '/themes/default/templates/layouts/footer.php';
if (!file_exists($footerPath)) { $footerPath = __DIR__ . '/app/views/layouts/footer.php'; }
if (file_exists($footerPath)) { require_once $footerPath; }
?>