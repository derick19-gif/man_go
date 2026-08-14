<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'MAN GO'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-full flex items-center justify-center p-4">
    <div class="w-full max-w-md p-8 bg-white rounded-3xl shadow-xl border border-gray-100">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="bg-amber-500 text-slate-900 font-black text-3xl w-16 h-16 rounded-full flex items-center justify-center shadow-lg mx-auto mb-4">M</div>
            <h2 class="text-2xl font-bold text-gray-900">Connexion</h2>
            <p class="text-gray-500 text-sm mt-2">Connectez-vous à votre espace</p>
        </div>

        <!-- Alerts -->
        <?php if (isset($error) && $error): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm mb-6 border border-red-100 flex items-center">
                <i class="fa-solid fa-circle-exclamation mr-2"></i> <?php echo htmlspecialchars($error['message'] ?? 'Erreur de connexion'); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($message) && $message): ?>
            <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm mb-6 border border-green-100 flex items-center">
                <i class="fa-solid fa-circle-check mr-2"></i> <?php echo htmlspecialchars($message['message'] ?? 'Succès'); ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="<?php echo defined('APP_URL') ? APP_URL : ''; ?>/authenticate" class="space-y-5">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
            
            <div class="relative">
                <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">Email ou Téléphone</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <input type="text" id="email" name="email" required placeholder="exemple@email.com ou 90000000"
                           class="block w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                </div>
            </div>

            <div class="relative">
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">Mot de passe</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <input type="password" id="password" name="password" required placeholder="••••••••"
                           class="block w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                </div>
            </div>

            <button type="submit" 
                    class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-3.5 rounded-xl transition shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                Se connecter
            </button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-sm text-gray-500">
                Pas encore de compte ? 
                <a href="<?php echo defined('APP_URL') ? APP_URL : ''; ?>/register" class="text-amber-600 font-bold hover:underline">S'inscrire gratuitement</a>
            </p>
        </div>
    </div>
</body>
</html>
