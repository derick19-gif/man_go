<?php
// =========================================================================
// PAGE DE CONNEXION - MAN GO
// =========================================================================
require_once __DIR__ . '/config/config.php';
$autoloaderPath = __DIR__ . '/core/Autoloader.php';
if (file_exists($autoloaderPath)) { require_once $autoloaderPath; }
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

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

// Message de succès si on vient de s'inscrire
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Votre compte a été créé avec succès ! Connectez-vous ci-dessous.";
}

$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim(filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        try {
            $db = \App\Core\Database::connect();
            
            // On nettoie l'identifiant pour la recherche du téléphone (on garde le + et les chiffres)
            $cleanPhone = preg_replace('/[^\d+]/', '', $identifier);

            // CORRECTION ICI : Chaque paramètre a un nom unique (:email_id, :phone_id, :cleanPhone)
            $stmt = $db->prepare("
                SELECT * FROM users 
                WHERE email = :email_id 
                   OR phone = :phone_id 
                   OR phone = :cleanPhone 
                LIMIT 1
            ");
            
            // On fournit exactement 3 valeurs pour les 3 paramètres
            $stmt->execute([
                ':email_id'   => $identifier,
                ':phone_id'   => $identifier,
                ':cleanPhone' => $cleanPhone
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérification du mot de passe
            $storedHash = $user['password_hash'] ?? $user['password'] ?? '';

            if ($user && password_verify($password, $storedHash)) {
                if (isset($user['status']) && strtolower($user['status']) !== 'active') {
                    $error = "Votre compte est suspendu ou inactif. Veuillez contacter le support.";
                } else {
                    // Trouver le bon nom à afficher
                    $userName = $user['firstname'] ?? $user['name'] ?? $user['full_name'] ?? explode('@', $user['email'])[0];

                    // Définition de l'URL de redirection selon le rôle
                    $roleId = (int) ($user['role_id'] ?? 5); // 5 = Acheteur par défaut
                    
                    if ($roleId === 4) {
                        $roleStr = 'vendor';
                        $redirectUrl = 'vendor_dir/dashboard.php';
                    } elseif ($roleId === 1 || $roleId === 2) {
                        $roleStr = 'admin';
                        $redirectUrl = 'admin/dashboard.php';
                    } else {
                        $roleStr = 'buyer';
                        $redirectUrl = 'client/views/dashboard.php';
                    }

                    // Création de la Session sécurisée
                    Session::create([
                        'user_id'    => (int) $user['id'],
                        'user_name'  => ucfirst($userName),
                        'user_email' => $user['email'] ?? '',
                        'user_role'  => $roleStr,
                        'user' => [
                            'id'    => (int) $user['id'],
                            'name'  => ucfirst($userName),
                            'email' => $user['email'] ?? '',
                            'role'  => $roleStr
                        ]
                    ]);

                    header('Location: ' . $redirectUrl);
                    exit;
                }
            } else {
                $error = "Identifiant ou mot de passe incorrect.";
            }
        } catch (Exception $e) {
            error_log('Erreur de connexion MAN GO : ' . $e->getMessage());
            $error = "Une erreur est survenue lors de la connexion. Veuillez réessayer.";
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
    <title>Connexion - <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'MAN GO') ?></title>
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
            <span class="text-slate-400 text-sm mr-2 hidden sm:inline">Pas encore de compte ?</span>
            <a href="register.php" class="text-amber-500 hover:text-amber-400 hover:underline font-bold text-sm transition-colors flex items-center gap-1 inline-flex">
                <i class="fa-solid fa-user-plus sm:hidden"></i> <span class="hidden sm:inline">S'inscrire gratuitement</span>
            </a>
        </div>
    </header>

    <!-- Formulaire de connexion -->
    <main class="flex-grow flex items-center justify-center p-4 my-6">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 w-full max-w-md p-8">
            
            <div class="text-center mb-8">
                <div class="bg-gradient-to-tr from-amber-400 to-amber-600 text-slate-900 font-black text-3xl w-16 h-16 rounded-full flex items-center justify-center shadow-lg shadow-amber-500/30 mx-auto mb-4 border border-amber-300">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h1 class="text-2xl font-black text-slate-900">Connexion</h1>
                <p class="text-xs font-semibold text-slate-500 mt-1 uppercase tracking-widest">Accédez à votre espace</p>
            </div>

            <!-- Messages d'Alerte -->
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-700 text-xs font-bold p-4 rounded-xl flex items-center space-x-3 mb-6 shadow-sm border border-red-100">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-emerald-50 text-emerald-700 text-xs font-bold p-4 rounded-xl flex items-center space-x-3 mb-6 shadow-sm border border-emerald-100">
                    <i class="fa-solid fa-circle-check text-xl"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($expired = Session::getFlash('expired')): ?>
                <div class="bg-amber-50 text-amber-700 text-xs font-bold p-4 rounded-xl flex items-center space-x-3 mb-6 shadow-sm border border-amber-100">
                    <i class="fa-solid fa-clock text-xl"></i>
                    <span>Votre session a expiré. Veuillez vous reconnecter.</span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-5">
                
                <!-- Email ou Téléphone avec Infobulle -->
                <div>
                    <label class="flex items-center text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">
                        E-mail ou Téléphone *
                        <div class="group relative inline-block ml-2">
                            <i class="fa-solid fa-circle-info text-slate-400 hover:text-amber-500 cursor-help transition"></i>
                            <div class="opacity-0 w-64 bg-slate-800 text-white text-[10px] font-normal normal-case tracking-normal rounded-lg py-2 px-3 absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-2 pointer-events-none group-hover:opacity-100 transition-opacity duration-300 shadow-xl text-center">
                                Vous pouvez vous connecter avec votre adresse E-mail ou votre Numéro.<br><br>
                                <strong class="text-amber-400">Important :</strong> Si vous utilisez votre numéro, n'oubliez pas l'indicatif de votre pays (ex: <strong>+228</strong> 90 00 00 00).
                            </div>
                        </div>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <input type="text" name="identifier" required 
                               value="<?= htmlspecialchars($identifier) ?>"
                               placeholder="exemple@mail.com ou +228..." 
                               class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition">
                    </div>
                </div>

                <!-- Mot de passe avec Œil de visibilité -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide">Mot de passe *</label>
                        <a href="forgot-password.php" class="text-[11px] text-amber-600 font-bold hover:text-amber-700 hover:underline transition">Oublié ?</a>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" required 
                               placeholder="••••••••" 
                               class="w-full pl-11 pr-12 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-amber-500 transition focus:outline-none">
                            <i id="toggleIcon" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Bouton Se Connecter -->
                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black py-4 rounded-xl shadow-lg shadow-amber-500/20 transition-all transform hover:-translate-y-0.5 text-sm uppercase tracking-wide flex items-center justify-center space-x-2 mt-2">
                    <span>Ouvrir ma session</span>
                    <i class="fa-solid fa-right-to-bracket"></i>
                </button>

            </form>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-slate-900 text-slate-400 py-6 text-center text-xs font-semibold">
        &copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.
    </footer>

    <!-- Script pour le bouton Œil -->
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash', 'text-amber-500');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash', 'text-amber-500');
                icon.classList.add('fa-eye');
            }
        }
    </script>

</body>
</html>