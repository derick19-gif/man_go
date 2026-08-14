<?php
// register.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Countries.php';

Session::init();

if (Session::get('user_id')) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email     = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password  = $_POST['password'] ?? '';
    $dial_code = trim($_POST['dial_code'] ?? '+228');
    $raw_phone = trim($_POST['phone'] ?? '');

    // Assemblage au format international (+228XXXXXXXX)
    $phone = Countries::formatPhone($dial_code, $raw_phone);

    if (!$full_name || !$email || !$password || empty($raw_phone)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        $db = getDBConnection();

        try {
            // Vérification email ou téléphone existant
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email OR phone = :phone LIMIT 1");
            $stmt->execute([':email' => $email, ':phone' => $phone]);

            if ($stmt->fetch()) {
                $error = "Un compte existe déjà avec cet e-mail ou ce numéro de téléphone.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmtInsert = $db->prepare("
                    INSERT INTO users (full_name, email, phone, password, role, created_at)
                    VALUES (:full_name, :email, :phone, :password, 'user', NOW())
                ");

                $stmtInsert->execute([
                    ':full_name' => $full_name,
                    ':email'     => $email,
                    ':phone'     => $phone,
                    ':password'  => $password_hash
                ]);

                $userId = $db->lastInsertId();
                Session::set('user_id', $userId);
                Session::set('user_name', $full_name);

                header('Location: dashboard.php?registered=1');
                exit;
            }
        } catch (Exception $e) {
            $error = "Erreur lors de l'inscription : " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-gray-800 font-sans min-h-screen flex flex-col justify-between">

    <!-- Nav Simplifiée -->
    <header class="bg-slate-900 text-white py-4 shadow">
        <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
            <a href="/man_go/" class="flex items-center space-x-2">
                <span class="bg-amber-500 text-slate-900 font-black text-xl w-8 h-8 rounded-full flex items-center justify-center">M</span>
                <span class="font-extrabold text-xl tracking-wide">MAN <span class="text-amber-500">GO</span></span>
            </a>
            <a href="login.php" class="text-sm text-gray-300 hover:text-amber-500 transition">Déjà un compte ? Se connecter</a>
        </div>
    </header>

    <!-- Formulaire d'inscription -->
    <main class="flex-grow flex items-center justify-center p-4 my-8">
        <div class="bg-white p-8 rounded-2xl shadow-md border border-gray-200 w-full max-w-md space-y-6">
            
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
                
                <!-- Nom complet -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nom complet</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" 
                               placeholder="Ex: Komlan Mensah" 
                               class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:outline-none focus:border-amber-500 focus:bg-white transition">
                    </div>
                </div>

                <!-- Adresse E-mail -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Adresse E-mail</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               placeholder="Ex: exemple@mail.com" 
                               class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:outline-none focus:border-amber-500 focus:bg-white transition">
                    </div>
                </div>

                <!-- Téléphone International -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Numéro de téléphone (WhatsApp)</label>
                    <div class="flex gap-2">
                        <select name="dial_code" class="w-2/5 bg-gray-50 border border-gray-300 rounded-xl px-2 py-2.5 text-xs focus:outline-none focus:border-amber-500 focus:bg-white transition">
                            <?= Countries::renderSelectOptions($_POST['dial_code'] ?? '+228') ?>
                        </select>
                        <input type="tel" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                               placeholder="90123456" 
                               class="w-3/5 px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:outline-none focus:border-amber-500 focus:bg-white transition">
                    </div>
                </div>

                <!-- Mot de passe -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Mot de passe</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" required placeholder="Mot de passe" 
                               class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:outline-none focus:border-amber-500 focus:bg-white transition">
                    </div>
                </div>

                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-slate-950 font-extrabold py-3 rounded-xl shadow transition text-sm flex items-center justify-center space-x-2">
                    <span>Créer mon compte</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="text-center text-xs text-gray-500 pt-2 border-t border-gray-100">
                En vous inscrivant, vous acceptez nos <a href="#" class="text-amber-600 underline">Conditions d'utilisation</a>.
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-gray-400 py-4 text-center text-xs border-t border-slate-800">
        &copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.
    </footer>

</body>
</html>