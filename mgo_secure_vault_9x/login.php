<?php
// =========================================================================
// SAS DE SÉCURITÉ ADMIN - MAN GO (mgo_secure_vault_9x/login.php)
// Protection anti-bruteforce & Authentification 2FA (Sessions Natives)
// =========================================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

// On démarre la session PHP NATIVE en tout premier lieu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Chemin relatif adapté depuis le dossier secret
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

$db = \App\Core\Database::connect();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$adminUrl = "$baseUrl/mgo_secure_vault_9x";

$errorMsg = '';
$step = 1; // Étape 1 : Email/Mdp, Étape 2 : Code OTP

// --- FONCTIONS DE SÉCURITÉ (ANTI-BRUTE FORCE) ---
function getClientIp() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED'])) $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR'])) $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED'])) $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR'])) $ipaddress = $_SERVER['REMOTE_ADDR'];
    else $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

$userIp = getClientIp();

// Vérifier si l'IP est bannie
$stmtBan = $db->prepare("SELECT banned_until, attempts FROM security_bans WHERE ip_address = ?");
$stmtBan->execute([$userIp]);
$banRecord = $stmtBan->fetch(PDO::FETCH_ASSOC);

if ($banRecord && !empty($banRecord['banned_until'])) {
    if (strtotime($banRecord['banned_until']) > time()) {
        die("<div style='background:#0f172a; color:#f87171; font-family:monospace; padding:50px; text-align:center; height:100vh; display:flex; align-items:center; justify-content:center; flex-direction:column;'><h1 style='font-size:3rem; margin:0;'>ACCÈS BLOQUÉ</h1><p>Mesure de sécurité déclenchée. Votre adresse IP ($userIp) est temporairement bannie suite à de trop nombreuses tentatives échouées.</p></div>");
    } else {
        // Le ban est expiré, on réinitialise
        $db->prepare("UPDATE security_bans SET attempts = 0, banned_until = NULL WHERE ip_address = ?")->execute([$userIp]);
    }
}

function logFailedAttempt($db, $ip) {
    $stmt = $db->prepare("INSERT INTO security_bans (ip_address, attempts) VALUES (?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
    $stmt->execute([$ip]);
    
    $stmtCheck = $db->prepare("SELECT attempts FROM security_bans WHERE ip_address = ?");
    $stmtCheck->execute([$ip]);
    if ($stmtCheck->fetchColumn() >= 3) {
        $db->prepare("UPDATE security_bans SET banned_until = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE ip_address = ?")->execute([$ip]);
    }
}

// =========================================================================
// TRAITEMENT DU FORMULAIRE
// =========================================================================

// On utilise $_SESSION native
if (isset($_SESSION['admin_auth_pending_id'])) {
    $step = 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- ÉTAPE 1 : IDENTIFIANTS ---
    if (isset($_POST['action']) && $_POST['action'] === 'login_step_1') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errorMsg = "Veuillez remplir tous les champs.";
            logFailedAttempt($db, $userIp);
        } else {
            $stmtUser = $db->prepare("SELECT * FROM users WHERE email = ? AND role_id IN (1, 2) LIMIT 1");
            $stmtUser->execute([$email]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $errorMsg = "Accès Refusé : Cette adresse email n'appartient pas à un administrateur.";
                logFailedAttempt($db, $userIp);
            } elseif (!password_verify($password, $user['password_hash'])) {
                $errorMsg = "Accès Refusé : Le mot de passe est incorrect.";
                logFailedAttempt($db, $userIp);
            } else {
                // SUCCÈS ÉTAPE 1
                $db->prepare("UPDATE security_bans SET attempts = 0 WHERE ip_address = ?")->execute([$userIp]);

                $otpCode = sprintf("%06d", mt_rand(1, 999999));
                $db->prepare("UPDATE users SET admin_otp_code = ?, admin_otp_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?")->execute([$otpCode, $user['id']]);
                
                $_SESSION['dev_otp'] = $otpCode; 
                $_SESSION['admin_auth_pending_id'] = $user['id'];
                
                // On force la sauvegarde de la session avant de rediriger
                session_write_close();
                header("Location: login.php");
                exit();
            }
        }
    }

    // --- ÉTAPE 2 : VÉRIFICATION OTP ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $inputOtp = trim($_POST['otp_code'] ?? '');
        $pendingId = $_SESSION['admin_auth_pending_id'] ?? null;

        if (!$pendingId) {
            $errorMsg = "Session expirée. Recommencez.";
            $step = 1;
        } else {
            $stmtVerify = $db->prepare("SELECT * FROM users WHERE id = ? AND admin_otp_code = ? AND admin_otp_expires > NOW()");
            $stmtVerify->execute([$pendingId, $inputOtp]);
            $adminUser = $stmtVerify->fetch(PDO::FETCH_ASSOC);

            if ($adminUser) {
                // AUTHENTIFICATION COMPLÈTE RÉUSSIE
                $db->prepare("UPDATE users SET admin_otp_code = NULL, admin_otp_expires = NULL WHERE id = ?")->execute([$pendingId]);
                
                // ON UTILISE $_SESSION NATIVE
                $_SESSION['is_admin_logged'] = true;
                $_SESSION['admin_user_id'] = $adminUser['id'];
                $_SESSION['admin_user_role'] = $adminUser['role_id'];
                $_SESSION['admin_user_name'] = $adminUser['firstname'] . ' ' . $adminUser['lastname'];

                $db->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, 'Connexion sécurisée réussie', ?)")->execute([$adminUser['id'], $userIp]);

                unset($_SESSION['admin_auth_pending_id']);
                unset($_SESSION['dev_otp']);

                // On force la sauvegarde de la session avant de rediriger
                session_write_close();
                header("Location: dashboard.php"); // Chemin relatif plus sûr
                exit();
            } else {
                $errorMsg = "Code invalide ou expiré.";
                logFailedAttempt($db, $userIp);
            }
        }
    }
}

// Bouton Annuler
if (isset($_GET['cancel'])) {
    unset($_SESSION['admin_auth_pending_id']);
    unset($_SESSION['dev_otp']);
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès Sécurisé - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; background-image: radial-gradient(circle at 50% -20%, #334155, #0f172a); }
        .glitch-effect { animation: glitch 2s linear infinite; }
        @keyframes glitch {
            2%, 64% { transform: translate(2px, 0) skew(0deg); }
            4%, 60% { transform: translate(-2px, 0) skew(0deg); }
            62% { transform: translate(0, 0) skew(5deg); }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 font-mono text-slate-300 relative overflow-hidden">
    
    <div class="absolute inset-0 opacity-5 pointer-events-none" style="background-image: linear-gradient(0deg, transparent 24%, rgba(255, 255, 255, .3) 25%, rgba(255, 255, 255, .3) 26%, transparent 27%, transparent 74%, rgba(255, 255, 255, .3) 75%, rgba(255, 255, 255, .3) 76%, transparent 77%, transparent), linear-gradient(90deg, transparent 24%, rgba(255, 255, 255, .3) 25%, rgba(255, 255, 255, .3) 26%, transparent 27%, transparent 74%, rgba(255, 255, 255, .3) 75%, rgba(255, 255, 255, .3) 76%, transparent 77%, transparent); background-size: 50px 50px;"></div>

    <div class="max-w-md w-full relative z-10">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-slate-900 border-2 border-emerald-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-[0_0_20px_rgba(16,185,129,0.3)]">
                <i class="fa-solid fa-shield-halved text-3xl text-emerald-400"></i>
            </div>
            <h1 class="text-2xl font-black text-white uppercase tracking-widest glitch-effect">MGO <span class="text-emerald-500">SYSTEM</span></h1>
            <p class="text-[10px] text-emerald-500 mt-2 tracking-[0.3em]">Protocol.Security.V2</p>
        </div>

        <div class="bg-slate-900/80 backdrop-blur-md border border-slate-700 p-8 rounded-3xl shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-600 via-emerald-400 to-emerald-600"></div>

            <?php if ($errorMsg): ?>
                <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 px-4 py-3 rounded-lg mb-6 text-xs font-bold flex items-center">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= htmlspecialchars($errorMsg) ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="login_step_1">
                    
                    <div class="mb-5">
                        <label class="block text-[10px] text-slate-400 uppercase tracking-widest mb-2 font-bold">Identification (Email)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="fa-solid fa-user-astronaut text-slate-500"></i></div>
                            <input type="email" name="email" required class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl py-3 pl-10 pr-4 text-sm outline-none focus:border-emerald-500 transition shadow-inner">
                        </div>
                    </div>
                    
                    <div class="mb-8">
                        <label class="block text-[10px] text-slate-400 uppercase tracking-widest mb-2 font-bold">Clé d'accès (Mot de passe)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="fa-solid fa-key text-slate-500"></i></div>
                            <input type="password" name="password" required class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl py-3 pl-10 pr-4 text-sm outline-none focus:border-emerald-500 transition shadow-inner">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-slate-950 font-black uppercase tracking-widest text-xs py-4 rounded-xl transition shadow-[0_0_15px_rgba(16,185,129,0.4)]">
                        Initialiser la connexion
                    </button>
                </form>

            <?php elseif ($step === 2): ?>
                <div class="text-center mb-6">
                    <p class="text-xs text-slate-400 leading-relaxed mb-4">Une demande d'authentification a été détectée.<br>Veuillez entrer le code à 6 chiffres envoyé à votre adresse e-mail.</p>
                    
                    <?php if (isset($_SESSION['dev_otp'])): ?>
                        <div class="bg-amber-500/10 border border-amber-500 text-amber-500 p-3 rounded-lg text-xs mb-4">
                            [MODE DEV] Le code généré est : <strong class="text-white text-lg tracking-widest block mt-1"><?= $_SESSION['dev_otp'] ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="verify_otp">
                    
                    <div class="mb-8 text-center">
                        <label class="block text-[10px] text-slate-400 uppercase tracking-widest mb-3 font-bold">Code OTP (6 chiffres)</label>
                        <input type="text" name="otp_code" required maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" class="bg-slate-950 border-2 border-emerald-500/50 text-white rounded-xl py-4 px-6 text-2xl tracking-[0.5em] font-black outline-none focus:border-emerald-500 transition shadow-[0_0_15px_rgba(16,185,129,0.2)] text-center w-full sm:w-2/3 mx-auto block">
                    </div>

                    <div class="flex gap-3">
                        <a href="login.php?cancel=1" class="w-1/3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold uppercase tracking-widest text-xs py-4 rounded-xl transition text-center flex items-center justify-center">
                            Annuler
                        </a>
                        <button type="submit" class="w-2/3 bg-emerald-600 hover:bg-emerald-500 text-slate-950 font-black uppercase tracking-widest text-xs py-4 rounded-xl transition shadow-[0_0_15px_rgba(16,185,129,0.4)]">
                            Vérifier l'empreinte
                        </button>
                    </div>
                </form>
            <?php endif; ?>
            
        </div>
        
        <p class="text-center text-[10px] text-slate-600 font-bold mt-8 uppercase tracking-widest">
            IP ENREGISTRÉE : <?= $userIp ?>
        </p>
    </div>
</body>
</html>