<?php
// client/views/dashboard.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Autoloader.php';
require_once __DIR__ . '/../../core/Database.php';

Session::init();

if (!Session::isAuthenticated()) {
    header('Location: ../../login.php');
    exit;
}

$role = Session::get('user_role') ?? 'buyer';

if ($role === 'admin' || $role === 'super_admin') {
    header('Location: ../../admin/dashboard.php');
    exit;
} elseif ($role === 'vendor' || $role === 'vendeur') {
    header('Location: ../../vendor_dir/dashboard.php');
    exit;
}

$userId = (int)Session::get('user_id');
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

try {
    $db = \App\Core\Database::connect();

    $stmtUser = $db->prepare("SELECT id, firstname, lastname, email, avatar, created_at FROM users WHERE id = :id LIMIT 1");
    $stmtUser->execute([':id' => $userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user['name'] = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
        if (empty($user['name'])) $user['name'] = 'Client MAN GO';
    } else {
        session_destroy();
        header('Location: ../../login.php');
        exit;
    }

    $stmtFav = $db->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = :id");
    $stmtFav->execute([':id' => $userId]);
    $totalFavorites = (int)$stmtFav->fetchColumn();

    // ==========================================
    // COMPTEUR DE MESSAGES ET NON-LUS DYNAMIQUE
    // ==========================================
    $totalMessages = 0; 
    $unreadMessages = 0; 
    try {
        $stmtMsg = $db->prepare("SELECT COUNT(DISTINCT listing_id) FROM messages WHERE sender_id = :id OR receiver_id = :id");
        $stmtMsg->execute([':id' => $userId]);
        $totalMessages = (int)$stmtMsg->fetchColumn();

        $stmtUnread = $db->prepare("SELECT COUNT(id) FROM messages WHERE receiver_id = :id AND is_read = 0");
        $stmtUnread->execute([':id' => $userId]);
        $unreadMessages = (int)$stmtUnread->fetchColumn();
    } catch(Exception $e) {}

    $recentFavorites = [];
    try {
        $stmtRecentFav = $db->prepare("
            SELECT l.id, l.title, l.price, l.image_path, c.name_key AS category
            FROM favorites f
            JOIN listings l ON f.listing_id = l.id
            LEFT JOIN categories c ON l.category_id = c.id
            WHERE f.user_id = :id AND l.status = 'active'
            ORDER BY f.created_at DESC
            LIMIT 3
        ");
        $stmtRecentFav->execute([':id' => $userId]);
        $recentFavorites = $stmtRecentFav->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}

} catch (PDOException $e) {
    $user = ['name' => 'Utilisateur', 'email' => '', 'avatar' => null];
    $totalFavorites = 0;
    $totalMessages = 0;
    $unreadMessages = 0;
    $recentFavorites = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace Client - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 500: '#f59e0b', 600: '#d97706', 950: '#090d16' } },
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 flex h-screen overflow-hidden relative">

    <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity opacity-0"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 h-full flex flex-col p-6 transform -translate-x-full lg:translate-x-0 lg:static transition-transform duration-300 ease-in-out shadow-2xl lg:shadow-none">
        
        <div class="flex items-center justify-between mb-10">
            <div class="flex items-center">
                <div class="bg-slate-900 text-white font-black rounded-lg flex items-center justify-center mr-3 shadow-sm" style="width: 40px; height: 40px; font-size: 1.2rem;">M</div>
                <h2 class="font-extrabold text-slate-900 text-xl m-0">Espace <span class="text-amber-500">Client</span></h2>
            </div>
            <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-red-500 text-2xl transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <nav class="flex flex-col gap-2 flex-1">
            <a href="#" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold transition-all bg-slate-900 text-white shadow-md">
                <i class="fa-solid fa-house-user w-6 text-center mr-2 text-amber-500"></i> Vue d'ensemble
            </a>
            <a href="<?= $baseUrl ?>/favorites.php" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all">
                <i class="fa-solid fa-heart w-6 text-center mr-2 text-rose-400"></i> Mes Favoris
            </a>

            <!-- ========================================== -->
            <!-- BOUTON MESSAGERIE AVEC BADGE DYNAMIQUE -->
            <!-- ========================================== -->
            <a href="<?= $baseUrl ?>/chat.php" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all mt-2">
                <i class="fa-solid fa-message w-6 text-center mr-2 text-indigo-400"></i> Ma Messagerie
                <?php if (isset($unreadMessages) && $unreadMessages > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full shadow-sm">
                        <?= $unreadMessages ?> Nouveau<?= $unreadMessages > 1 ? 'x' : '' ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="#" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all opacity-50 cursor-not-allowed" title="Bientôt disponible">
                <i class="fa-solid fa-bag-shopping w-6 text-center mr-2 text-emerald-400"></i> Mes Achats
            </a>
            
            <hr class="border-slate-200 my-4">

            <a href="<?= $baseUrl ?>/profile-settings.php" class="nav-link w-full flex items-center px-4 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 transition-all mt-auto">
                <i class="fa-solid fa-user-gear w-6 text-center mr-2"></i> Paramètres
            </a>
        </nav>

        <div class="mt-4 pt-4 border-t border-slate-200">
            <a href="<?= $baseUrl ?>/" class="w-full flex items-center justify-center px-4 py-3 border-2 border-slate-900 text-slate-900 rounded-full font-bold hover:bg-slate-900 hover:text-white transition-all mb-3">
                <i class="fa-solid fa-arrow-left mr-2"></i> Retour au site
            </a>
            <a href="<?= $baseUrl ?>/logout.php" class="w-full flex items-center justify-center px-4 py-3 border-2 border-red-100 text-red-500 rounded-full font-bold hover:bg-red-50 transition-all">
                <i class="fa-solid fa-arrow-right-from-bracket mr-2"></i> Déconnexion
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-full overflow-hidden">
        
        <header class="lg:hidden bg-white border-b border-slate-200 h-16 flex items-center justify-between px-4 shrink-0 shadow-sm">
            <div class="flex items-center">
                <div class="bg-slate-900 text-white font-black rounded w-8 h-8 flex items-center justify-center mr-2">M</div>
                <span class="font-extrabold text-slate-900">MAN <span class="text-amber-500">GO</span></span>
            </div>
            <button onclick="toggleSidebar()" class="text-slate-600 hover:text-amber-500 text-2xl p-2 focus:outline-none transition">
                <i class="fa-solid fa-bars"></i>
            </button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-8 lg:p-10">
            
            <header class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-10 gap-4">
                <div class="flex items-center gap-4">
                    <img src="<?= htmlspecialchars($user['avatar'] ?? $baseUrl.'/assets/images/default-avatar.png', ENT_QUOTES, 'UTF-8') ?>" 
                         alt="Avatar" class="w-16 h-16 rounded-full border-4 border-white shadow-md object-cover">
                    <div>
                        <h3 class="font-extrabold text-2xl text-slate-900 m-0">Bonjour, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> 👋</h3>
                        <p class="text-sm text-slate-500 mt-1">Prêt à dénicher de bonnes affaires aujourd'hui ?</p>
                    </div>
                </div>
                <a href="<?= $baseUrl ?>/listings.php" class="bg-amber-100 text-amber-700 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm self-start sm:self-auto hover:bg-amber-200 transition">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i> Explorer le marché
                </a>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                <div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm flex justify-between items-center transition hover:shadow-md hover:border-rose-200 group">
                    <div>
                        <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-2">Mes Favoris</p>
                        <h3 class="text-4xl font-black text-slate-900"><?= $totalFavorites ?></h3>
                        <a href="<?= $baseUrl ?>/favorites.php" class="text-xs font-bold text-rose-500 mt-4 inline-block group-hover:underline">Voir ma liste &rarr;</a>
                    </div>
                    <div class="w-16 h-16 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm flex justify-between items-center transition hover:shadow-md hover:border-indigo-200 group">
                    <div>
                        <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-2">Discussions actives</p>
                        <h3 class="text-4xl font-black text-slate-900"><?= $totalMessages ?></h3>
                        <a href="<?= $baseUrl ?>/chat.php" class="text-xs font-bold text-indigo-500 mt-4 inline-block group-hover:underline">Ouvrir la messagerie &rarr;</a>
                    </div>
                    <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-message"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2">
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="font-bold text-xl text-slate-900">Vos derniers coups de ❤️</h4>
                    </div>

                    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm p-4">
                        <?php if (empty($recentFavorites)): ?>
                            <div class="text-center py-12">
                                <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                                    <i class="fa-regular fa-heart"></i>
                                </div>
                                <h5 class="font-bold text-slate-900 mb-1">Aucun favori pour l'instant</h5>
                                <p class="text-slate-500 text-sm mb-6">Sauvegardez les annonces qui vous intéressent pour les retrouver ici.</p>
                                <a href="<?= $baseUrl ?>/listings.php" class="bg-slate-900 text-white font-bold py-2.5 px-6 rounded-full hover:bg-amber-500 transition">Parcourir les offres</a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recentFavorites as $fav): ?>
                                    <div class="flex items-center p-3 hover:bg-slate-50 rounded-2xl transition border border-transparent hover:border-slate-100">
                                        <img src="<?= htmlspecialchars(!empty($fav['image_path']) ? $baseUrl.'/'.$fav['image_path'] : $baseUrl.'/assets/images/placeholder.jpg') ?>" 
                                             class="w-20 h-20 rounded-xl object-cover mr-4 shadow-sm border border-slate-200">
                                        <div class="flex-1 min-w-0">
                                            <span class="text-[10px] font-black text-amber-500 uppercase tracking-wider"><?= htmlspecialchars($fav['category'] ?? 'Général') ?></span>
                                            <h5 class="font-bold text-slate-900 truncate my-1">
                                                <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $fav['id'] ?>" class="hover:text-amber-600 transition"><?= htmlspecialchars($fav['title']) ?></a>
                                            </h5>
                                            <p class="font-black text-slate-700"><?= number_format($fav['price'], 0, ',', ' ') ?> FCFA</p>
                                        </div>
                                        <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $fav['id'] ?>" class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-amber-500 hover:text-white transition">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4 pt-4 border-t border-slate-100 text-center">
                                <a href="<?= $baseUrl ?>/favorites.php" class="text-sm font-bold text-slate-500 hover:text-slate-900">Voir toute ma liste</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-gradient-to-br from-slate-900 to-[#0f172a] rounded-3xl p-8 text-center shadow-xl relative overflow-hidden h-full flex flex-col justify-center border border-slate-800">
                        <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 rounded-full bg-amber-500 opacity-20 blur-2xl"></div>
                        <div class="absolute bottom-0 left-0 -ml-8 -mb-8 w-32 h-32 rounded-full bg-blue-500 opacity-20 blur-2xl"></div>
                        
                        <div class="relative z-10">
                            <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-6 border border-white/20">
                                <i class="fa-solid fa-store text-amber-400 text-3xl"></i>
                            </div>
                            <h3 class="text-2xl font-black text-white mb-3 leading-tight">Gagnez de l'argent avec MAN GO</h3>
                            <p class="text-slate-300 text-sm mb-8 leading-relaxed">
                                Transformez votre passion en profit. Ouvrez votre boutique professionnelle et touchez des milliers de clients aujourd'hui.
                            </p>
                            <a href="<?= $baseUrl ?>/become-vendor.php" class="block w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-900 font-black py-4 rounded-xl transition transform hover:-translate-y-1 shadow-[0_0_20px_rgba(245,158,11,0.3)]">
                                Devenir Vendeur PRO
                            </a>
                            <p class="text-slate-400 text-xs mt-4"><i class="fa-solid fa-check text-amber-500 mr-1"></i> Inscription gratuite et rapide</p>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('-translate-x-full'); 
            
            if(overlay.classList.contains('hidden')) {
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            } else {
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }
    </script>
</body>
</html>