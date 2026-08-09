<?php
// forgot-password.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Session.php';

Session::init();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim(filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    if (empty($identifier)) {
        $error = "Veuillez entrer votre e-mail ou votre numéro de téléphone.";
    } else {
        $db = getDBConnection();
        
        // Vérifier si l'utilisateur existe
        $stmt = $db->prepare("SELECT id, email, phone, full_name FROM users WHERE email = :id OR phone = :id LIMIT 1");
        $stmt->execute([':id' => $identifier]);
        $user = $stmt->fetch();

        if ($user) {
            // Token aléatoire sécurisé (64 caractères)
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Enregistrer ou mettre à jour le token
            $updateStmt = $db->prepare("UPDATE users SET reset_token = :token, reset_token_expires = :expires WHERE id = :id");
            $updateStmt->execute([
                ':token' => $token,
                ':expires' => $expiresAt,
                ':id' => $user['id']
            ]);

            // Lien direct pour l'environnement de développement local
            $resetLink = "reset-password.php?token=" . $token;
            $success = "Un lien a été généré ! <a href='{$resetLink}' class='underline font-bold hover:text-emerald-800'>Cliquez ici pour réinitialiser le mot de passe</a>";
        } else {
            // Réponse neutre pour éviter le 'user enumeration'
            $success = "Si un compte existe avec cet identifiant, un lien de réinitialisation a été généré.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 font-sans min-h-screen flex flex-col justify-between">

    <!-- En-tête -->
    <header class="bg-[#0B132B] text-white py-4 px-8 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-2">
            <span class="bg-[#F59E0B] text-[#0B132B] font-black px-3 py-1 rounded-lg text-xl tracking-wider">M</span>
            <span class="font-extrabold text-2xl tracking-tight text-white">MAN GO</span>
        </div>
        <div>
            <a href="login.php" class="text-slate-400 hover:text-white text-sm font-medium transition-colors">
                <i class="fa-solid fa-arrow-left mr-1"></i> Retour à la connexion
            </a>
        </div>
    </header>

    <!-- Conteneur Formulaire -->
    <main class="flex-grow flex items-center justify-center p-4 my-8">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 border border-slate-100">
            
            <div class="text-center mb-8">
                <div class="bg-amber-100 text-[#F59E0B] w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fa-solid fa-key"></i>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-800">Mot de passe oublié ?</h1>
                <p class="text-slate-500 text-sm mt-2">Entrez votre e-mail ou téléphone pour recevoir les instructions de réinitialisation.</p>
            </div>

            <!-- Messages d'Alerte -->
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 border border-red-100">
                    <i class="fa-solid fa-circle-exclamation text-lg"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 border border-emerald-100">
                    <i class="fa-solid fa-circle-check text-lg flex-shrink-0"></i>
                    <div><?= $success ?></div>
                </div>
            <?php endif; ?>

            <form action="forgot-password.php" method="POST" class="space-y-5">
                
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Adresse E-mail ou Téléphone</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <i class="fa-solid fa-envelope-open"></i>
                        </span>
                        <input type="text" name="identifier" required placeholder="exemple@mail.com ou +228..." 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#F59E0B] focus:bg-white text-sm transition-all">
                    </div>
                </div>

                <button type="submit" class="w-full bg-[#F59E0B] hover:bg-amber-600 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-amber-500/20 transition-all flex items-center justify-center gap-2 text-sm mt-6">
                    Réinitialiser le mot de passe <i class="fa-solid fa-paper-plane text-xs"></i>
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