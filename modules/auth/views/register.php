<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'MAN GO'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-full flex flex-col justify-between font-sans">

    <!-- En-tête (Bannière) -->
    <header class="bg-[#0B132B] text-white py-4 px-8 flex justify-between items-center shadow-md">
        <a href="<?php echo defined('APP_URL') ? APP_URL : '/man_go'; ?>/index.php" class="flex items-center space-x-2">
            <span class="bg-[#F59E0B] text-[#0B132B] font-black px-3 py-1 rounded-lg text-xl tracking-wider">M</span>
            <span class="font-extrabold text-2xl tracking-tight text-white">MAN <span class="text-[#F59E0B]">GO</span></span>
        </a>
        <div>
            <span class="text-slate-400 text-sm mr-2">Déjà un compte ?</span>
            <a href="<?php echo defined('APP_URL') ? APP_URL : '/man_go'; ?>/login.php" class="text-[#F59E0B] hover:underline font-semibold text-sm transition-colors">Connexion</a>
        </div>
    </header>

    <!-- Conteneur Formulaire -->
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="w-full max-w-md p-8 bg-white rounded-3xl shadow-xl border border-gray-100">
            <div class="text-center mb-8">
                <div class="bg-amber-500 text-slate-900 font-black text-3xl w-16 h-16 rounded-full flex items-center justify-center shadow-lg mx-auto mb-4">M</div>
                <h2 class="text-2xl font-bold text-gray-900">Inscription</h2>
                <p class="text-gray-500 text-sm mt-2">Rejoignez <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'MAN GO'); ?></p>
            </div>

            <form method="POST" action="<?php echo defined('APP_URL') ? APP_URL : '/man_go'; ?>/register.php" class="space-y-4">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                <!-- Nom -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nom complet</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i class="fa-solid fa-user"></i></div>
                        <input type="text" name="name" placeholder="Ex: Komlan Mensah" required class="block w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i class="fa-solid fa-envelope"></i></div>
                        <input type="email" name="email" placeholder="Ex: exemple@mail.com" required class="block w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                    </div>
                </div>

                <!-- Téléphone -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Numéro de téléphone</label>
                    <div class="flex gap-2">
                        <select name="country_code" class="w-2/5 px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500">
                            <?php echo $country_options ?? Countries::renderSelectOptions('+228'); ?>
                        </select>
                        <input type="tel" name="phone" required placeholder="90000000" class="block w-3/5 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>

                <!-- Mot de passe -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Mot de passe</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i class="fa-solid fa-lock"></i></div>
                        <input type="password" name="password" placeholder="Votre mot de passe" required class="block w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                    </div>
                </div>

                <button type="submit" class="w-full bg-[#F59E0B] hover:bg-amber-600 text-slate-950 font-extrabold py-3.5 rounded-xl transition shadow-lg hover:shadow-xl mt-6">
                    Créer mon compte
                </button>
            </form>

            <p class="text-center text-sm text-gray-500 mt-6">
                Déjà inscrit ? <a href="<?php echo defined('APP_URL') ? APP_URL : '/man_go'; ?>/login.php" class="text-amber-600 font-bold hover:underline">Connexion</a>
            </p>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="py-4 text-center text-xs text-slate-400">
        &copy; <?php echo date('Y'); ?> MAN GO Marketplace. Tous droits réservés.
    </footer>

</body>
</html>