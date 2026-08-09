<?php
// reset-password.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Session.php';

Session::init();

$error = '';
$success = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

if (empty($token)) {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Vérifier si le token est valide et non expiré
$stmt = $db->prepare("SELECT id FROM users WHERE reset_token = :token AND reset_token_expires > NOW() LIMIT 1");
$stmt->execute([':token' => $token]);
$user = $stmt->fetch();

if (!$user) {
    $error = "Le lien de réinitialisation est invalide ou a expiré. Veuillez refaire une demande.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($password) || empty($password_confirm)) {
        $error = "Veuillez remplir tous les champs.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Hachage du mot de passe et annulation du token
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE users SET password = :pass, reset_token = NULL, reset_token_expires = NULL WHERE id = :id");
        $updateStmt->execute([
            ':pass' => $passwordHash,
            ':id' => $user['id']
        ]);

        header('Location: login.php?reset=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 font-sans min-h-screen flex flex-col justify-between">

    <header class="bg-[#0B132B] text-white py-4 px-8 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-2">
            <span class="bg-[#F59E0B] text-[#0B132B] font-black px-3 py-1 rounded-lg text-xl tracking-wider">M</span>
            <span class="font-extrabold text-2xl tracking-tight text-white">MAN GO</span>
        </div>
        <div>
            <a href="login.php" class="text-slate-400 hover:text-white text-sm font-medium transition-colors">Se connecter</a>
        </div>
    </header>

    <main class="flex-grow flex items-center justify-center p-4 my-8">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 border border-slate-100">
            
            <div class="text-center mb-8">
                <h1 class="text-2xl font-extrabold text-slate-800">Nouveau mot de passe</h1>
                <p class="text-slate-500 text-sm mt-2">Définissez votre nouveau mot de passe sécurisé.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 border border-red-100">
                    <i class="fa-solid fa-circle-exclamation text-lg flex-shrink-0"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($user): ?>
            <form action="reset-password.php" method="POST" class="space-y-5">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nouveau mot de passe</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" required placeholder="?••••••••?••••••••?••••••••?••••••••?••••••••?••••••••?••••••••?" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#F59E0B] focus:bg-white text-sm transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Confirmer le mot de passe</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password_confirm" required placeholder="?••••••••?••••••••?••••••••?••••••••?••••••••?••••••••?••••••••?" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#F59E0B] focus:bg-white text-sm transition-all">
                    </div>
                </div>

                <button type="submit" class="w-full bg-[#F59E0B] hover:bg-amber-600 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-amber-500/20 transition-all flex items-center justify-center gap-2 text-sm mt-6">
                    Enregistrer le mot de passe <i class="fa-solid fa-check text-xs"></i>
                </button>
            </form>
            <?php else: ?>
                <a href="forgot-password.php" class="block text-center w-full bg-[#0B132B] text-white font-bold py-3 rounded-xl hover:bg-slate-800 transition-all text-sm">
                    Refaire une demande
                </a>
            <?php endif; ?>
        </div>
    </main>

    <footer class="py-4 text-center text-xs text-slate-400">
        &copy; <?= date('Y') ?> MAN GO Marketplace. Tous droits réservés.
    </footer>

</body>
</html>