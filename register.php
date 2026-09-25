<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';

Session::init();

// Rediriger intelligemment si l'utilisateur est déjà connecté
if (Session::isAuthenticated() || Session::get('user_id')) {
    $role = Session::get('user_role');
    
    if ($role === 'vendor') {
        header('Location: vendor_dir/dashboard.php');
    } elseif ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: client/views/dashboard.php');
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role       = $_POST['role'] ?? 'buyer';
    $full_name  = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email      = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password   = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $dial_code  = trim($_POST['dial_code'] ?? '+228');
    $raw_phone  = trim($_POST['phone'] ?? '');
    $terms      = isset($_POST['terms']) ? true : false;
    
    // Récupération du code de parrainage caché
    $referral_code_post = trim($_POST['referral_code'] ?? '');

    // Validation du rôle autorisé
    if (!in_array($role, ['buyer', 'vendor'])) {
        $role = 'buyer';
    }

    // Formatage du téléphone (On suppose que Countries::formatPhone existe dans votre architecture)
    $phone = class_exists('Countries') && method_exists('Countries', 'formatPhone') 
             ? Countries::formatPhone($dial_code, $raw_phone) 
             : $dial_code . preg_replace('/[^0-9]/', '', $raw_phone);

    if (!$full_name || !$email || !$password || empty($raw_phone)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($password !== $confirm_password) {
        $error = "Les deux mots de passe ne correspondent pas.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $error = "Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.";
    } elseif (!$terms) {
        $error = "Vous devez accepter les conditions d'utilisation.";
    } else {
        $db = \App\Core\Database::connect();

        try {
            // Vérification si l'utilisateur existe déjà
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email OR phone = :phone LIMIT 1");
            $stmt->execute([':email' => $email, ':phone' => $phone]);

            if ($stmt->fetch()) {
                $error = "Un compte existe déjà avec cet e-mail ou ce numéro de téléphone.";
            } else {
                
                // --- LOGIQUE DE PARRAINAGE ---
                $referred_by_id = null; // Par défaut, aucun parrain
                
                if (!empty($referral_code_post)) {
                    $stmtParrain = $db->prepare("SELECT id FROM users WHERE referral_code = :ref LIMIT 1");
                    $stmtParrain->execute([':ref' => $referral_code_post]);
                    $parrain = $stmtParrain->fetch(PDO::FETCH_ASSOC);
                    
                    if ($parrain) {
                        $referred_by_id = $parrain['id']; // Le parrain est identifié
                    }
                }
                // -----------------------------

                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $name_parts = explode(' ', $full_name, 2);                
                $firstname = $name_parts[0] ?? $full_name;                
                $lastname = $name_parts[1] ?? '';

                // Définition de l'ID du rôle
                $roleId = ($role === 'vendor') ? 4 : 5; 

                // Insertion avec la colonne referred_by
                $stmtInsert = $db->prepare("
                    INSERT INTO users (firstname, lastname, email, phone, password_hash, role_id, referred_by, created_at)
                    VALUES (:firstname, :lastname, :email, :phone, :password_hash, :role_id, :referred_by, NOW())
                ");

                $stmtInsert->execute([
                    ':firstname'     => $firstname,
                    ':lastname'      => $lastname,
                    ':email'         => $email,
                    ':phone'         => $phone,
                    ':password_hash' => $password_hash,
                    ':role_id'       => $roleId,
                    ':referred_by'   => $referred_by_id
                ]);

                // Redirection après succès
                header('Location: login.php?registered=1');
                exit;
            }
        } catch (Exception $e) {
            $error = "Erreur technique lors de l'inscription : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - MAN GO Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen flex flex-col justify-between font-sans text-gray-800">

    <!-- En-tête simplifié -->
    <header class="bg-slate-900 text-white py-4 shadow">
        <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
            <a href="/man_go/" class="flex items-center space-x-2">
                <span class="bg-amber-500 text-slate-900 font-black text-xl w-8 h-8 rounded-full flex items-center justify-center">M</span>
                <span class="font-extrabold text-xl tracking-wide">MAN <span class="text-amber-500">GO</span></span>
            </a>
            <a href="login.php" class="text-sm text-gray-300 hover:text-amber-500 transition">Déjà un compte ? Se connecter</a>
        </div>
    </header>

    <!-- Corps de page / Formulaire -->
    <main class="flex-grow flex items-center justify-center p-4 my-8">
        <div class="bg-white p-8 rounded-2xl shadow-md border border-gray-200 w-full max-w-lg space-y-6">
            
            <div class="text-center">
                <h1 class="text-2xl font-black text-slate-900">Créer un compte</h1>
                <p class="text-xs text-gray-500 mt-1">Rejoignez la communauté internationale MAN GO</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-xs p-3 rounded-lg flex items-center space-x-2">
                    <i class="fa-solid fa-circle-exclamation text-base"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="space-y-4">
                
                <!-- CHAMP CACHÉ POUR CAPTURER LE CODE PARRAIN -->
                <input type="hidden" name="referral_code" value="<?= htmlspecialchars($_GET['ref'] ?? '') ?>">

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Type de compte</label>
                    <select name="role" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-amber-500 transition">
                        <option value="buyer">Acheteur</option>
                        <option value="vendor">Vendeur / Propriétaire de stand</option>
                    </select>
                </div>

                <!-- Nom complet -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nom complet</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" 
                               placeholder="Ex: Komlan Mensah" 
                               class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:outline-none focus:border-amber-500 focus:bg-white transition">
                    </div>
                </div>

                <!-- Adresse E-mail -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Adresse E-mail</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               placeholder="Ex: exemple@mail.com" 
                               class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:outline-none focus:border-amber-500 focus:bg-white transition">
                    </div>
                </div>

                <!-- Téléphone International -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Numéro de téléphone (WhatsApp)</label>
                    <div class="flex gap-2">
                        <select name="dial_code" class="w-2/5 bg-gray-50 border border-gray-300 rounded-xl px-2 py-2.5 text-xs focus:outline-none focus:border-amber-500 transition">
                            <?= class_exists('Countries') && method_exists('Countries', 'renderSelectOptions') 
                                ? Countries::renderSelectOptions($_POST['dial_code'] ?? '+228') 
                                : '<option value="+228">+228 (Togo)</option><option value="+229">+229 (Bénin)</option><option value="+225">+225 (CI)</option>' ?>
                        </select>
                        <input type="tel" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                               placeholder="90123456" 
                               class="w-3/5 px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition">
                    </div>
                </div>

                <!-- Mot de passe avec visibilité (Œil) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Mot de passe</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" id="password" required placeholder="Min. 8 car. (Maj, Min, Chiffre, Symbole)" 
                               class="w-full pl-10 pr-10 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition">
                        <button type="button" onclick="togglePassword('password', 'eye1')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-amber-500 transition">
                            <i id="eye1" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirmer le mot de passe avec visibilité (Œil) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Confirmer le mot de passe</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Répétez le mot de passe" 
                               class="w-full pl-10 pr-10 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:outline-none focus:border-amber-500 transition">
                        <button type="button" onclick="togglePassword('confirm_password', 'eye2')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-amber-500 transition">
                            <i id="eye2" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Conditions d'utilisation -->
                <div class="flex items-start space-x-2 pt-2">
                    <input type="checkbox" name="terms" id="terms" required class="mt-1 rounded border-gray-300 text-amber-500 focus:ring-amber-500 cursor-pointer">
                    <label for="terms" class="text-xs text-gray-600 cursor-pointer">
                        J'accepte les <a href="terms.php" class="text-amber-600 hover:text-amber-700 underline font-semibold">Conditions d'utilisation</a> et la <a href="privacy.php" class="text-amber-600 hover:text-amber-700 underline font-semibold">Politique de confidentialité</a>.
                    </label>
                </div>

                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-slate-950 font-extrabold py-3 rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5 text-sm flex items-center justify-center space-x-2 mt-4">
                    <span>Créer mon compte</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-gray-400 py-4 text-center text-xs border-t border-slate-800">
        &copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.
    </footer>

    <!-- Script JavaScript pour l'œil -->
    <script>
        function togglePassword(fieldId, iconId) {
            const passwordField = document.getElementById(fieldId);
            const eyeIcon = document.getElementById(iconId);
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>