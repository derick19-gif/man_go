<?php
// =========================================================================
// Page des Paramètres Globaux du Compte - MAN GO (International & VIP)
// =========================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

// Vérification de connexion
if (empty($_SESSION['user_id'])) {
    header("Location: $baseUrl/login.php");
    exit();
}

$db = \App\Core\Database::connect();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'client';
$message = '';
$messageType = ''; 

$dashboardLink = ($userRole === 'vendor') ? "$baseUrl/vendor_dir/dashboard.php" : "$baseUrl/client/views/dashboard.php";

// =========================================================================
// TRAITEMENT DES FORMULAIRES
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Mise à jour du Profil & Préférences d'affichage
    if ($_POST['action'] === 'update_profile') {
        $fullName = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $currency = $_POST['user_currency'] ?? 'FCFA';
        $rt_conversion = isset($_POST['real_time_conversion']) ? 1 : 0;

        if (!empty($fullName) && !empty($email)) {
            $parts = explode(' ', $fullName, 2);
            $firstname = $parts[0];
            $lastname = $parts[1] ?? '';

            $stmt = $db->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ?, phone = ?, user_currency = ?, real_time_conversion = ? WHERE id = ?");
            if ($stmt->execute([$firstname, $lastname, $email, $phone, $currency, $rt_conversion, $userId])) {
                $_SESSION['user_name'] = $fullName; 
                $_SESSION['user_currency'] = $currency; 
                $message = "Vos informations et préférences ont été mises à jour.";
                $messageType = 'success';
            }
        }
    }

    // 2. Mise à jour de la Sécurité
    elseif ($_POST['action'] === 'update_security') {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($new_password !== $confirm_password) {
            $message = "Les mots de passe ne correspondent pas.";
            $messageType = 'error';
        } elseif (strlen($new_password) >= 6) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$hashed, $userId])) {
                $message = "Votre mot de passe a été modifié avec succès.";
                $messageType = 'success';
            }
        } else {
            $message = "Le mot de passe doit contenir au moins 6 caractères.";
            $messageType = 'error';
        }
    }

    // 3. Mise à jour des Paramètres Business & VIP
    elseif ($_POST['action'] === 'update_business' && $userRole === 'vendor') {
        $welcome = $_POST['welcome_message'] ?? '';
        $auto_reply = $_POST['auto_reply_message'] ?? '';
        $is_away = isset($_POST['is_away']) ? 1 : 0;
        $auto_reply_enabled = isset($_POST['auto_reply_enabled']) ? 1 : 0;
        
        $open_time = $_POST['open_time'] ?? '08:00';
        $close_time = $_POST['close_time'] ?? '18:00';
        
        // Options VIP
        $ai_enabled = isset($_POST['ai_assistant_enabled']) ? 1 : 0;
        $seo_desc = trim($_POST['seo_description'] ?? '');

        // Utilisation de ON DUPLICATE KEY UPDATE pour ne pas écraser l'ID
        $stmt = $db->prepare("
            INSERT INTO business_settings (user_id, auto_reply_enabled, auto_reply_message, welcome_message, is_away, open_time, close_time, ai_assistant_enabled, seo_description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            auto_reply_enabled=VALUES(auto_reply_enabled), auto_reply_message=VALUES(auto_reply_message), 
            welcome_message=VALUES(welcome_message), is_away=VALUES(is_away), 
            open_time=VALUES(open_time), close_time=VALUES(close_time),
            ai_assistant_enabled=VALUES(ai_assistant_enabled), seo_description=VALUES(seo_description)
        ");
        if ($stmt->execute([$userId, $auto_reply_enabled, $auto_reply, $welcome, $is_away, $open_time, $close_time, $ai_enabled, $seo_desc])) {
            $message = "Vos paramètres Business & VIP ont été enregistrés.";
            $messageType = 'success';
        }
    }

    // 4. Suppression Logique du Compte (Soft Delete)
    elseif ($_POST['action'] === 'delete_account') {
        $deletedEmail = 'deleted_' . time() . '_' . uniqid();
        $stmt = $db->prepare("UPDATE users SET is_active = 0, email = ? WHERE id = ?");
        if ($stmt->execute([$deletedEmail, $userId])) {
            session_destroy();
            header("Location: $baseUrl/?msg=account_deleted");
            exit();
        }
    }
}

// =========================================================================
// RÉCUPÉRATION DES DONNÉES
// =========================================================================
$stmtUser = $db->prepare("SELECT TRIM(CONCAT(firstname, ' ', lastname)) AS name, email, phone, user_currency, real_time_conversion FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

$biz = [
    'welcome_message' => '', 'auto_reply_message' => '', 'is_away' => 0, 'auto_reply_enabled' => 0,
    'open_time' => '08:00', 'close_time' => '18:00', 'ai_assistant_enabled' => 0, 'seo_description' => ''
];
$activeSub = null;
$daysRemaining = 0;
$isVip = false;

if ($userRole === 'vendor') {
    $stmtBiz = $db->prepare("SELECT * FROM business_settings WHERE user_id = ?");
    $stmtBiz->execute([$userId]);
    $bizFetched = $stmtBiz->fetch(PDO::FETCH_ASSOC);
    if ($bizFetched) $biz = $bizFetched;

    $stmtSub = $db->prepare("
        SELECT s.end_date, p.name as plan_name, p.id as plan_id
        FROM user_subscriptions s JOIN subscription_plans p ON s.plan_id = p.id
        WHERE s.user_id = ? AND s.status = 'active' AND s.end_date > NOW() ORDER BY s.id DESC LIMIT 1
    ");
    $stmtSub->execute([$userId]);
    $activeSub = $stmtSub->fetch(PDO::FETCH_ASSOC);
    if ($activeSub) {
        $daysRemaining = (new DateTime())->diff(new DateTime($activeSub['end_date']))->days;
        if ($activeSub['plan_id'] == 2) $isVip = true; 
    }
}

$pageTitle = "Paramètres du Compte - MAN GO";
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<!-- ATTENTION : pt-28 ajouté ici pour éviter que le menu du haut ne cache le contenu -->
<div class="bg-slate-50 min-h-screen pt-28 pb-24">
    <main class="max-w-5xl mx-auto px-4 sm:px-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Paramètres</h1>
                <p class="text-slate-500 mt-1">Gérez vos informations, votre sécurité et vos préférences.</p>
            </div>
            <a href="<?= $dashboardLink ?>" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-amber-600 font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center self-start sm:self-auto">
                <i class="fa-solid fa-arrow-left mr-2"></i> Retour <span class="hidden sm:inline ml-1">au Dashboard</span>
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="p-4 mb-8 rounded-xl flex items-center font-bold text-sm shadow-sm <?= $messageType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                <i class="<?= $messageType === 'success' ? 'fa-solid fa-circle-check text-emerald-500' : 'fa-solid fa-triangle-exclamation text-rose-500' ?> mr-3 text-lg"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- COLONNE GAUCHE (Profil & Business) -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- PROFIL & AFFICHAGE -->
                <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mr-3 text-lg"><i class="fa-solid fa-user"></i></div>
                        <h2 class="text-xl font-black text-slate-900">Informations & Affichage</h2>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Nom Complet <?= $userRole === 'vendor' ? '/ Raison Sociale' : '' ?></label>
                                <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 transition mb-4">
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-1">Adresse Email</label>
                                        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-1">Téléphone</label>
                                        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
                                    </div>
                                </div>
                            </div>

                            <hr class="border-slate-100">

                            <div>
                                <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center"><i class="fa-solid fa-earth-africa text-amber-500 mr-2"></i> Préférences Internationales</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Devise d'affichage</label>
                                        <select name="user_currency" class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 font-bold">
                                            <?php
                                            $currencies = ['FCFA' => 'Franc CFA (XOF/XAF)', 'GHS' => 'Cedi Ghanéen (GHS)', 'NGN' => 'Naira Nigérian (NGN)', 'USD' => 'Dollar US (USD)', 'EUR' => 'Euro (EUR)', 'ZAR' => 'Rand Sud-Africain (ZAR)'];
                                            foreach($currencies as $code => $label) {
                                                $selected = ($user['user_currency'] === $code) ? 'selected' : '';
                                                echo "<option value=\"$code\" $selected>$label</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="flex items-center cursor-pointer group bg-slate-50 border border-slate-200 p-3 rounded-xl hover:bg-amber-50 transition">
                                            <div class="relative">
                                                <input type="checkbox" name="real_time_conversion" class="sr-only peer" <?= $user['real_time_conversion'] ? 'checked' : '' ?>>
                                                <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                            </div>
                                            <span class="ml-3 text-sm font-bold text-slate-900">Conversion en temps réel</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 px-6 rounded-xl transition shadow-lg hover:shadow-xl">Mettre à jour les préférences</button>
                        </div>
                    </form>
                </div>

                <!-- BUSINESS & VIP (VENDEURS UNIQUEMENT) -->
                <?php if ($userRole === 'vendor'): ?>
                <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center mr-3 text-lg"><i class="fa-solid fa-store"></i></div>
                        <h2 class="text-xl font-black text-slate-900">Configuration Boutique & Messages</h2>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_business">
                        
                        <!-- Horaires d'ouverture -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Heure d'ouverture</label>
                                <input type="time" name="open_time" value="<?= htmlspecialchars(substr($biz['open_time'], 0, 5)) ?>" class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-2 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Heure de fermeture</label>
                                <input type="time" name="close_time" value="<?= htmlspecialchars(substr($biz['close_time'], 0, 5)) ?>" class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-2 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>
                        </div>

                        <!-- Chat classique et Mode Absence Manuel -->
                        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 mb-8 space-y-5">
                            
                            <!-- Toggle Mode Absence Manuel -->
                            <label class="flex items-center cursor-pointer group">
                                <div class="relative">
                                    <input type="checkbox" name="is_away" class="sr-only peer" <?= $biz['is_away'] ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500"></div>
                                </div>
                                <div class="ml-3">
                                    <span class="text-sm font-bold text-slate-900 block group-hover:text-rose-600 transition">Forcer le Mode Absence (Manuel)</span>
                                    <span class="text-xs text-slate-500">Le message d'absence sera envoyé même pendant les heures d'ouverture.</span>
                                </div>
                            </label>

                            <hr class="border-slate-200">

                            <!-- Toggle Réponse Auto -->
                            <label class="flex items-center cursor-pointer group">
                                <div class="relative">
                                    <input type="checkbox" name="auto_reply_enabled" class="sr-only peer" <?= $biz['auto_reply_enabled'] ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                </div>
                                <span class="ml-3 text-sm font-bold text-slate-900 group-hover:text-amber-600 transition">Activer les Réponses Automatiques (Standard)</span>
                            </label>

                            <div class="space-y-4 pt-2">
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">Message d'accueil</label>
                                    <textarea name="welcome_message" rows="2" class="w-full bg-white border border-slate-200 text-slate-900 px-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500"><?= htmlspecialchars($biz['welcome_message']) ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">Message d'absence <span class="text-xs text-slate-400 font-normal">(Auto en dehors des horaires ou si forcé)</span></label>
                                    <textarea name="auto_reply_message" rows="2" class="w-full bg-white border border-slate-200 text-slate-900 px-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500"><?= htmlspecialchars($biz['auto_reply_message']) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- OUTILS VIP -->
                        <div class="border-2 <?= $isVip ? 'border-amber-400 bg-amber-50/30' : 'border-slate-100 bg-slate-50' ?> rounded-2xl p-6 relative overflow-hidden">
                            <?php if (!$isVip): ?>
                                <!-- Voile de blocage VIP -->
                                <div class="absolute inset-0 z-10 bg-slate-50/80 backdrop-blur-[2px] flex flex-col items-center justify-center text-center p-4">
                                    <i class="fa-solid fa-lock text-3xl text-slate-400 mb-2"></i>
                                    <h4 class="font-black text-slate-800">Outils Réservés aux VIP</h4>
                                    <p class="text-xs text-slate-500 mb-3">Assistant IA 24/7 et Optimisation Google.</p>
                                    <a href="<?= $baseUrl ?>/pricing.php" class="bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold py-2 px-5 rounded-full text-xs shadow-lg">Devenir VIP</a>
                                </div>
                            <?php endif; ?>

                            <h3 class="font-black text-slate-900 mb-4 flex items-center">
                                <i class="fa-solid fa-crown text-amber-500 mr-2"></i> Arsenal VIP
                            </h3>
                            
                            <label class="flex items-center cursor-pointer group mb-6 bg-white p-4 rounded-xl shadow-sm border border-amber-100">
                                <div class="relative">
                                    <input type="checkbox" name="ai_assistant_enabled" class="sr-only peer" <?= ($biz['ai_assistant_enabled'] && $isVip) ? 'checked' : '' ?> <?= !$isVip ? 'disabled' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                </div>
                                <div class="ml-3">
                                    <span class="text-sm font-black text-slate-900 block">Déléguer à l'Assistant IA Premium</span>
                                    <span class="text-xs text-slate-500">L'IA répondra et négociera intelligemment avec vos clients 24h/24.</span>
                                </div>
                            </label>

                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Description SEO Google <span class="text-xs font-normal text-slate-400">(Max 160 car.)</span></label>
                                <textarea name="seo_description" rows="2" maxlength="160" placeholder="Décrivez votre boutique avec des mots-clés forts pour apparaître premier sur Google..." class="w-full bg-white border border-slate-200 text-slate-900 px-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500" <?= !$isVip ? 'disabled' : '' ?>><?= htmlspecialchars($biz['seo_description']) ?></textarea>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-white font-bold py-3 px-8 rounded-xl transition shadow-lg hover:shadow-xl">Enregistrer ma configuration</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

            </div>

            <!-- COLONNE DROITE (Abonnement, Sécurité, Danger) -->
            <div class="space-y-8">

                <!-- Abonnement (Vendeurs) -->
                <?php if ($userRole === 'vendor'): ?>
                <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center mr-3 text-lg"><i class="fa-solid fa-crown"></i></div>
                        <h2 class="text-xl font-black text-slate-900">Abonnement</h2>
                    </div>
                    <?php if ($activeSub): ?>
                        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 mb-6 text-center">
                            <p class="text-sm text-slate-500 mb-1">Forfait actuel</p>
                            <h3 class="text-lg font-black text-slate-900 mb-2"><?= htmlspecialchars($activeSub['plan_name']) ?></h3>
                            <div class="inline-block bg-blue-100 text-blue-700 font-bold px-3 py-1 rounded-full text-xs">
                                <?= $daysRemaining ?> jours restants
                            </div>
                        </div>
                        <a href="<?= $baseUrl ?>/pricing.php" class="block w-full text-center bg-blue-50 text-blue-600 hover:bg-blue-500 hover:text-white border border-blue-200 font-bold py-3 px-6 rounded-xl transition">Renouveler / Upgrader</a>
                    <?php else: ?>
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-3 text-2xl"><i class="fa-solid fa-lock"></i></div>
                            <p class="text-sm text-slate-500">Aucun abonnement PRO actif.</p>
                        </div>
                        <a href="<?= $baseUrl ?>/pricing.php" class="block w-full text-center bg-amber-500 hover:bg-amber-400 text-slate-900 font-bold py-3 px-6 rounded-xl transition shadow-lg">Découvrir les forfaits</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Sécurité -->
                <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mr-3 text-lg"><i class="fa-solid fa-shield-halved"></i></div>
                        <h2 class="text-xl font-black text-slate-900">Sécurité</h2>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_security">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Nouveau mot de passe</label>
                                <div class="relative">
                                    <input type="password" id="new_pwd" name="new_password" required minlength="6" placeholder="Min. 6 caractères" class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 pr-10 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <button type="button" onclick="togglePwd('new_pwd', 'eye_new')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"><i id="eye_new" class="fa-solid fa-eye"></i></button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Confirmer mot de passe</label>
                                <div class="relative">
                                    <input type="password" id="conf_pwd" name="confirm_password" required minlength="6" placeholder="Répétez le mot de passe" class="w-full bg-slate-50 border border-slate-200 text-slate-900 px-4 py-3 pr-10 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <button type="button" onclick="togglePwd('conf_pwd', 'eye_conf')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"><i id="eye_conf" class="fa-solid fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" class="w-full bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-500 hover:text-white font-bold py-3 px-6 rounded-xl transition">Changer mot de passe</button>
                        </div>
                    </form>
                </div>

                <!-- Danger (Soft Delete) -->
                <div class="bg-white rounded-3xl border border-rose-200 p-8 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-rose-500"></div>
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mr-3 text-lg"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <h2 class="text-xl font-black text-slate-900">Zone de Danger</h2>
                    </div>
                    <p class="text-sm text-slate-500 mb-6 leading-relaxed">
                        Désactiver votre compte masquera vos informations et vos annonces du public.
                    </p>
                    <form method="POST" action="" onsubmit="return confirm('⚠️ Êtes-vous sûr de vouloir désactiver votre compte ?');">
                        <input type="hidden" name="action" value="delete_account">
                        <button type="submit" class="w-full bg-rose-500 hover:bg-rose-600 text-white font-bold py-3 px-6 rounded-xl transition shadow-lg shadow-rose-500/30 flex justify-center items-center">
                            <i class="fa-solid fa-user-xmark mr-2"></i> Désactiver mon compte
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
    function togglePwd(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            input.type = "password";
            icon.classList.replace("fa-eye-slash", "fa-eye");
        }
    }
</script>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>