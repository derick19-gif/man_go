<?php
// login.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Countries.php';

Session::init();

// Rediriger si l'utilisateur est déjà connecté
if (Session::isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

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
        $db = function_exists('getDBConnection') ? getDBConnection() : Database::getInstance();

        try {
            if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $cleanPhone = preg_replace('/[^0-9+]/', '', $identifier);
                
                if (strpos($cleanPhone, '0') === 0) {
                    $cleanPhone = substr($cleanPhone, 1);
                }

                $stmt = $db->prepare("
                    SELECT * FROM users 
                    WHERE email = :id 
                       OR phone = :id 
                       OR phone = :cleanPhone 
                       OR phone = CONCAT('+228', :cleanPhone) 
                    LIMIT 1
                ");
                $stmt->execute([
                    ':id'         => $identifier,
                    ':cleanPhone' => $cleanPhone
                ]);
            } else {
                $stmt = $db->prepare("SELECT * FROM users WHERE email = :id LIMIT 1");
                $stmt->execute([':id' => $identifier]);
            }

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérification du mot de passe avec gestion des deux formats de colonnes possibles
            $storedHash = $user['password_hash'] ?? $user['password'] ?? '';

            if ($user && password_verify($password, $storedHash)) {
                if (isset($user['status']) && $user['status'] !== 'active') {
                    $error = "Votre compte est suspendu ou inactif.";
                } else {
                    Session::create([
                        'user_id'    => (int) $user['id'],
                        'user_name'  => $user['full_name'] ?? $user['name'] ?? '',
                        'user_email' => $user['email'] ?? '',
                        'user_role'  => $user['role'] ?? 'user'
                    ]);

                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = "Identifiant (email/téléphone) ou mot de passe incorrect.";
            }
        } catch (Exception $e) {
            error_log('Erreur de connexion MAN GO : ' . $e->getMessage());
            $error = "Une erreur est survenue lors de la connexion. Veuillez réessayer.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-full flex flex-col justify-between font-sans">

    <!-- En-tête (Bannière) -->
    <header class="bg-[#0B132B] text-white py-4 px-8 flex justify-between items-center shadow-md">
        <a href="/man_go/" class="flex items-center space-x-2">
            <span class="bg-[#F59E0B] text-[#0B132B] font-black px-3 py-1 rounded-lg text-xl tracking-wider">M</span>
            <span class="font-extrabold text-2xl tracking-tight text-white">MAN <span class="text-[#F59E0B]">GO</span></span>
        </a>
        <div>
            <span class="text-slate-400 text-sm mr-2">Pas encore de compte ?</span>
            <a href="register.php" class="text-[#F59E0B] hover:underline font-semibold text-sm transition-colors">S'inscrire</a>
        </div>
    </header>

    <!-- Conteneur Formulaire -->
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-xl w-full max-w-md p-8 border border-slate-100">
            
            <div class="text-center mb-8">
                <div class="bg-[#F59E0B] text-[#0B132B] font-black text-3xl w-16 h-16 rounded-full flex items-center justify-center shadow-lg mx-auto mb-4">M</div>
                <h1 class="text-2xl font-bold text-gray-900">Connexion</h1>
                <p class="text-gray-500 text-sm mt-2">Accédez à votre espace MAN GO</p>
            </div>

            <!-- Messages d'Alerte -->
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 border border-red-100">
                    <i class="fa-solid fa-circle-exclamation text-lg flex-shrink-0"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-emerald-50 text-emerald-600 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 border border-emerald-100">
                    <i class="fa-solid fa-circle-check text-lg flex-shrink-0"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($expired = Session::getFlash('expired')): ?>
                <div class="bg-amber-50 text-amber-700 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 border border-amber-100">
                    <i class="fa-solid fa-clock text-lg flex-shrink-0"></i>
                    <span>Votre session a expiré. Veuillez vous reconnecter.</span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-4">
                
                <!-- Email ou Téléphone -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Adresse E-mail ou Téléphone</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <input type="text" name="identifier" required 
                               value="<?= htmlspecialchars($identifier) ?>"
                               placeholder="exemple@mail.com ou +228..." 
                               class="block w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#F59E0B] focus:border-[#F59E0B] text-sm transition">
                    </div>
                </div>

                <!-- Mot de passe -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-sm font-semibold text-gray-700">Mot de passe</label>
                        <a href="forgot-password.php" class="text-xs text-[#F59E0B] font-bold hover:underline">Oublié ?</a>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" required 
                               placeholder="••••••••" 
                               class="block w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#F59E0B] focus:border-[#F59E0B] text-sm transition">
                    </div>
                </div>

                <!-- Bouton Se Connecter -->
                <button type="submit" class="w-full bg-[#F59E0B] hover:bg-amber-600 text-slate-950 font-extrabold py-3.5 rounded-xl transition shadow-lg hover:shadow-xl mt-6 flex items-center justify-center space-x-2">
                    <span>Se connecter</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </form>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="py-4 text-center text-xs text-slate-400">
        &copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.
    </footer>

</body>
</html>