<?php
// =========================================================================
// PAGE D'INSCRIPTION - MAN GO
// =========================================================================
require_once __DIR__ . '/config/config.php';
$autoloaderPath = __DIR__ . '/core/Autoloader.php';
if (file_exists($autoloaderPath)) { require_once $autoloaderPath; }
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

Session::init();

// Rediriger si l'utilisateur est déjà connecté
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

    // Formatage du téléphone
    $phone = class_exists('Countries') && method_exists('Countries', 'formatPhone') 
             ? Countries::formatPhone($dial_code, $raw_phone) 
             : $dial_code . preg_replace('/[^0-9]/', '', $raw_phone);

    if (!$full_name || !$email || !$password || empty($raw_phone)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($password !== $confirm_password) {
        $error = "Les deux mots de passe ne correspondent pas.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $error = "Le mot de passe ne respecte pas les critères de sécurité.";
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
                $referred_by_id = null;
                if (!empty($referral_code_post)) {
                    $stmtParrain = $db->prepare("SELECT id FROM users WHERE referral_code = :ref LIMIT 1");
                    $stmtParrain->execute([':ref' => $referral_code_post]);
                    $parrain = $stmtParrain->fetch(PDO::FETCH_ASSOC);
                    if ($parrain) {
                        $referred_by_id = $parrain['id'];
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

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'MAN GO') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen flex flex-col justify-between font-sans text-slate-800">

    <!-- En-tête -->
    <header class="bg-[#0B132B] text-white py-4 px-4 sm:px-8 flex justify-between items-center shadow-md">
        <a href="<?= $baseUrl ?>/index.php" class="flex items-center space-x-2">
            <span class="bg-amber-500 text-slate-900 font-black px-3 py-1 rounded-lg text-xl tracking-wider shadow-sm border border-amber-400">M</span>
            <span class="font-extrabold text-2xl tracking-tight text-white hidden sm:block">MAN <span class="text-amber-500">GO</span></span>
        </a>
        <div>
            <span class="text-slate-400 text-sm mr-2 hidden sm:inline">Déjà un compte ?</span>
            <a href="login.php" class="text-amber-500 hover:text-amber-400 hover:underline font-bold text-sm transition-colors flex items-center gap-1 inline-flex">
                <i class="fa-solid fa-right-to-bracket sm:hidden"></i> <span class="hidden sm:inline">Connexion</span>
            </a>
        </div>
    </header>

    <!-- Formulaire d'inscription -->
    <main class="flex-grow flex items-center justify-center p-4 my-6">
        <div class="bg-white p-8 rounded-3xl shadow-xl border border-slate-100 w-full max-w-lg space-y-6">
            
            <div class="text-center mb-2">
                <h1 class="text-2xl font-black text-slate-900">Créer un compte</h1>
                <p class="text-xs font-semibold text-slate-500 mt-1 uppercase tracking-widest">Rejoignez l'écosystème MAN GO</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-xs font-bold p-4 rounded-xl flex items-center space-x-3 shadow-sm">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="space-y-5">
                
                <!-- Code parrain -->
                <input type="hidden" name="referral_code" value="<?= htmlspecialchars($_GET['ref'] ?? '') ?>">

                <!-- Type de compte avec Infobulle -->
                <div>
                    <label class="flex items-center text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">
                        Type de compte *
                        <div class="group relative inline-block ml-2">
                            <i class="fa-solid fa-circle-info text-slate-400 hover:text-amber-500 cursor-help transition"></i>
                            <div class="opacity-0 w-64 bg-slate-800 text-white text-[10px] font-normal normal-case tracking-normal rounded-lg py-2 px-3 absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-2 pointer-events-none group-hover:opacity-100 transition-opacity duration-300 shadow-xl text-center">
                                <strong class="text-amber-400">Acheteur:</strong> Idéal pour contacter les vendeurs et acheter.<br><br>
                                <strong class="text-emerald-400">Vendeur:</strong> Nécessaire si vous souhaitez ouvrir une boutique et publier des annonces.
                            </div>
                        </div>
                    </label>
                    <select name="role" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3.5 text-sm font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition cursor-pointer">
                        <option value="buyer" <?= (($_POST['role'] ?? '') === 'buyer') ? 'selected' : '' ?>>👤 Acheteur / Particulier</option>
                        <option value="vendor" <?= (($_POST['role'] ?? '') === 'vendor') ? 'selected' : '' ?>>🏪 Vendeur / Professionnel</option>
                    </select>
                </div>

                <!-- Nom complet -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Nom complet *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" 
                               placeholder="Ex: Komlan Mensah" 
                               class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition">
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Adresse E-mail *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               placeholder="Ex: exemple@mail.com" 
                               class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition">
                    </div>
                </div>

                <!-- Téléphone (Sécurisé Numérique) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Numéro WhatsApp / Mobile *</label>
                    <div class="flex gap-2">
                        <select name="dial_code" class="w-1/3 bg-slate-50 border border-slate-200 rounded-xl px-2 py-3.5 text-xs font-bold text-slate-700 focus:outline-none focus:border-amber-500 transition cursor-pointer">
                            <?= class_exists('Countries') && method_exists('Countries', 'renderSelectOptions') 
                                ? Countries::renderSelectOptions($_POST['dial_code'] ?? '+228') 
                                : '<option value="+228">🇹🇬 +228</option><option value="+229">🇧🇯 +229</option><option value="+225">🇨🇮 +225</option>' ?>
                        </select>
                        <input type="tel" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                               placeholder="90123456" 
                               oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                               class="w-2/3 px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 tracking-wider focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition">
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- MODULE MOT DE PASSE INTERACTIF AVEC CHECKLIST -->
                <!-- ========================================== -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Mot de passe *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" id="password" required placeholder="Saisissez votre mot de passe" 
                               class="w-full pl-11 pr-12 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition">
                        <button type="button" onclick="togglePassword('password', 'eye1')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-amber-500 transition">
                            <i id="eye1" class="fa-solid fa-eye"></i>
                        </button>
                    </div>

                    <!-- Barre de progression -->
                    <div class="mt-3 flex items-center gap-2">
                        <div class="flex-grow h-1.5 bg-slate-200 rounded-full overflow-hidden">
                            <div id="pwd-progress-bar" class="h-full w-0 transition-all duration-300 bg-red-500"></div>
                        </div>
                        <span id="pwd-status-text" class="text-[10px] font-black uppercase text-slate-400 w-16 text-right">Faible</span>
                    </div>

                    <!-- Checklist des critères -->
                    <div class="mt-3 grid grid-cols-2 gap-2 p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <div id="req-length" class="text-[11px] font-semibold text-slate-400 flex items-center gap-1.5 transition-colors">
                            <i class="fa-solid fa-xmark text-red-400 w-3 text-center"></i> Minimum 8 caractères
                        </div>
                        <div id="req-upper" class="text-[11px] font-semibold text-slate-400 flex items-center gap-1.5 transition-colors">
                            <i class="fa-solid fa-xmark text-red-400 w-3 text-center"></i> 1 Majuscule
                        </div>
                        <div id="req-lower" class="text-[11px] font-semibold text-slate-400 flex items-center gap-1.5 transition-colors">
                            <i class="fa-solid fa-xmark text-red-400 w-3 text-center"></i> 1 Minuscule
                        </div>
                        <div id="req-number" class="text-[11px] font-semibold text-slate-400 flex items-center gap-1.5 transition-colors">
                            <i class="fa-solid fa-xmark text-red-400 w-3 text-center"></i> 1 Chiffre
                        </div>
                        <div id="req-special" class="col-span-2 text-[11px] font-semibold text-slate-400 flex items-center gap-1.5 transition-colors">
                            <i class="fa-solid fa-xmark text-red-400 w-3 text-center"></i> 1 Caractère spécial (ex: @, #, !, ?)
                        </div>
                    </div>
                </div>

                <!-- Confirmer le mot de passe -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Confirmer le mot de passe *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Répétez le mot de passe" 
                               class="w-full pl-11 pr-12 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition">
                        <button type="button" onclick="togglePassword('confirm_password', 'eye2')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-amber-500 transition">
                            <i id="eye2" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <p id="pwd-match-error" class="hidden text-xs text-red-500 font-bold mt-1.5 flex items-center gap-1"><i class="fa-solid fa-circle-exclamation"></i> Les mots de passe ne correspondent pas.</p>
                </div>

                <!-- Conditions -->
                <div class="flex items-start space-x-3 pt-3">
                    <input type="checkbox" name="terms" id="terms" required class="mt-1 w-4 h-4 rounded border-slate-300 text-amber-500 focus:ring-amber-500 cursor-pointer">
                    <label for="terms" class="text-[11px] text-slate-600 cursor-pointer font-medium leading-tight">
                        En m'inscrivant, j'accepte les <a href="terms.php" class="text-amber-600 hover:text-amber-700 underline font-bold">Conditions générales</a> et la <a href="privacy.php" class="text-amber-600 hover:text-amber-700 underline font-bold">Politique de confidentialité</a> de MAN GO.
                    </label>
                </div>

                <button type="submit" id="submitBtn" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black py-4 rounded-xl shadow-lg shadow-amber-500/20 transition-all transform hover:-translate-y-0.5 text-sm uppercase tracking-wide flex items-center justify-center space-x-2 mt-4">
                    <span>Créer mon compte sécurisé</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-6 text-center text-xs font-semibold">
        &copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.
    </footer>

    <!-- Script JS -->
    <script>
        // Afficher/Masquer le mot de passe
        function togglePassword(fieldId, iconId) {
            const passwordField = document.getElementById(fieldId);
            const eyeIcon = document.getElementById(iconId);
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash', 'text-amber-500');
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash', 'text-amber-500');
                eyeIcon.classList.add('fa-eye');
            }
        }

        // Checklist de validation du mot de passe
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const matchError = document.getElementById('pwd-match-error');
        const progressBar = document.getElementById('pwd-progress-bar');
        const statusText = document.getElementById('pwd-status-text');

        // Regex
        const reqLength = /.{8,}/;
        const reqUpper = /[A-Z]/;
        const reqLower = /[a-z]/;
        const reqNumber = /[0-9]/;
        const reqSpecial = /[\W_]/;

        // Met à jour un élément de la liste (croix rouge vers coche verte)
        function updateReqState(elementId, isValid) {
            const el = document.getElementById(elementId);
            const icon = el.querySelector('i');
            
            if (isValid) {
                el.classList.remove('text-slate-400');
                el.classList.add('text-emerald-600');
                icon.classList.remove('fa-xmark', 'text-red-400');
                icon.classList.add('fa-check', 'text-emerald-500');
            } else {
                el.classList.add('text-slate-400');
                el.classList.remove('text-emerald-600');
                icon.classList.add('fa-xmark', 'text-red-400');
                icon.classList.remove('fa-check', 'text-emerald-500');
            }
        }

        passwordInput.addEventListener('input', function() {
            const val = passwordInput.value;
            let score = 0;

            // Test chaque critère
            const isLength = reqLength.test(val);
            const isUpper = reqUpper.test(val);
            const isLower = reqLower.test(val);
            const isNumber = reqNumber.test(val);
            const isSpecial = reqSpecial.test(val);

            if(isLength) score++;
            if(isUpper) score++;
            if(isLower) score++;
            if(isNumber) score++;
            if(isSpecial) score++;

            // MAJ Visuelle Liste
            updateReqState('req-length', isLength);
            updateReqState('req-upper', isUpper);
            updateReqState('req-lower', isLower);
            updateReqState('req-number', isNumber);
            updateReqState('req-special', isSpecial);

            // MAJ Barre de progression
            progressBar.style.width = (score * 20) + "%";

            // Couleurs
            progressBar.classList.remove('bg-red-500', 'bg-orange-500', 'bg-amber-400', 'bg-emerald-500');
            statusText.classList.remove('text-red-500', 'text-orange-500', 'text-amber-500', 'text-emerald-500', 'text-slate-400');

            if (val.length === 0) {
                statusText.innerText = "Faible";
                statusText.classList.add('text-slate-400');
            } else if (score <= 2) {
                progressBar.classList.add('bg-red-500');
                statusText.innerText = "Faible";
                statusText.classList.add('text-red-500');
            } else if (score === 3) {
                progressBar.classList.add('bg-orange-500');
                statusText.innerText = "Moyen";
                statusText.classList.add('text-orange-500');
            } else if (score === 4) {
                progressBar.classList.add('bg-amber-400');
                statusText.innerText = "Bon";
                statusText.classList.add('text-amber-500');
            } else if (score === 5) {
                progressBar.classList.add('bg-emerald-500');
                statusText.innerText = "Parfait";
                statusText.classList.add('text-emerald-500');
            }
            
            checkMatch();
        });

        // Vérification en direct des mots de passe qui correspondent
        function checkMatch() {
            if (confirmInput.value.length > 0 && passwordInput.value !== confirmInput.value) {
                matchError.classList.remove('hidden');
                confirmInput.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500/20');
                confirmInput.classList.remove('focus:border-amber-500', 'focus:ring-amber-500/20');
            } else {
                matchError.classList.add('hidden');
                confirmInput.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500/20');
                confirmInput.classList.add('focus:border-amber-500', 'focus:ring-amber-500/20');
            }
        }

        confirmInput.addEventListener('input', checkMatch);
    </script>
</body>
</html>