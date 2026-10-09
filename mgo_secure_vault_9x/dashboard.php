<?php
// =========================================================================
// ROUTEUR CENTRAL SUPER ADMIN - MAN GO
// mgo_secure_vault_9x/dashboard.php
// =========================================================================

ini_set('display_errors', 1); error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['is_admin_logged']) || $_SESSION['is_admin_logged'] !== true) {
    header("Location: login.php"); exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

$db = \App\Core\Database::connect();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$adminUrl = "$baseUrl/mgo_secure_vault_9x";

$successMsg = ''; $errorMsg = '';
$currentPage = $_GET['page'] ?? 'home'; 

// --- TRAITEMENT GLOBAL DES ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        $db->beginTransaction();

        // RETRAITS
        if ($action === 'approve_withdraw' || $action === 'reject_withdraw') {
            $txId = filter_input(INPUT_POST, 'tx_id', FILTER_VALIDATE_INT);
            $stmtTx = $db->prepare("SELECT user_id, amount FROM transactions WHERE id = ? AND payment_method = 'withdrawal_request' AND status = 'pending'");
            $stmtTx->execute([$txId]);
            $tx = $stmtTx->fetch(PDO::FETCH_ASSOC);
            if ($tx) {
                if ($action === 'approve_withdraw') {
                    $db->prepare("UPDATE transactions SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$txId]);
                    $successMsg = "Le retrait a été validé avec succès.";
                } elseif ($action === 'reject_withdraw') {
                    $db->prepare("UPDATE transactions SET status = 'cancelled', updated_at = NOW() WHERE id = ?")->execute([$txId]);
                    $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([abs($tx['amount']), $tx['user_id']]);
                    $successMsg = "Retrait rejeté. Fonds recrédités.";
                }
            }
        }
        // KYC
        elseif ($action === 'approve_kyc' || $action === 'reject_kyc') {
            $kycId = filter_input(INPUT_POST, 'kyc_id', FILTER_VALIDATE_INT);
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if ($action === 'approve_kyc') {
                $db->prepare("UPDATE user_kyc SET status = 'approved', updated_at = NOW() WHERE id = ?")->execute([$kycId]);
                $db->prepare("UPDATE users SET kyc_status = 'APPROVED', is_verified = 1 WHERE id = ?")->execute([$userId]);
                $successMsg = "Le dossier KYC a été approuvé.";
            } elseif ($action === 'reject_kyc') {
                $db->prepare("UPDATE user_kyc SET status = 'rejected', updated_at = NOW() WHERE id = ?")->execute([$kycId]);
                $db->prepare("UPDATE users SET kyc_status = 'REJECTED' WHERE id = ?")->execute([$userId]);
                $successMsg = "Le dossier KYC a été rejeté.";
            }
        }
        // UTILISATEURS
        elseif ($action === 'toggle_user_status') {
            $targetUserId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);
            $newStatus = filter_input(INPUT_POST, 'new_status', FILTER_VALIDATE_INT);
            if ($targetUserId && $targetUserId != $_SESSION['admin_user_id']) { 
                $db->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newStatus, $targetUserId]);
                $successMsg = "Le statut du compte a été modifié.";
            }
        }
        // CONFIGURATIONS
        elseif ($action === 'update_settings') {
            if (isset($_POST['settings']) && is_array($_POST['settings'])) {
                $stmtUpdate = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                foreach ($_POST['settings'] as $key => $value) { $stmtUpdate->execute([$value, $key]); }
                $successMsg = "Les paramètres ont été mis à jour.";
            }
        }
        // SIGNALEMENTS
        elseif ($action === 'resolve_report') {
            $reportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
            $db->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?")->execute([$reportId]);
            $successMsg = "Le signalement a été marqué comme résolu.";
        }
        $db->commit();
    } catch (Exception $e) { $db->rollBack(); $errorMsg = "Erreur : " . $e->getMessage(); }
}

// --- BADGES NOTIFICATIONS SIDEBAR ---
$countPendingWithdrawals = $db->query("SELECT COUNT(*) FROM transactions WHERE payment_method = 'withdrawal_request' AND status = 'pending'")->fetchColumn();
$countPendingKyc = $db->query("SELECT COUNT(*) FROM user_kyc WHERE status = 'pending'")->fetchColumn();
try { $countReports = $db->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn(); } catch (Exception $e) { $countReports = 0; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MGO SYSTEM - Tour de Contrôle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-950 text-slate-300 font-sans h-screen flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-72 bg-slate-900 border-r border-slate-800 flex flex-col justify-between h-full shadow-2xl relative z-20">
        <div>
            <div class="h-20 flex items-center px-6 border-b border-slate-800 bg-slate-950/50">
                <div class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center shadow-[0_0_15px_rgba(16,185,129,0.3)] mr-3"><i class="fa-solid fa-shield-halved text-slate-950 text-xl"></i></div>
                <div><h1 class="text-white font-black tracking-widest text-lg uppercase">MGO SYSTEM</h1><p class="text-[9px] text-emerald-500 font-bold tracking-[0.2em] uppercase">Superviseur v2</p></div>
            </div>
            <nav class="p-4 space-y-2 mt-4">
                <p class="px-3 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Tableaux de bord</p>
                <a href="?page=home" class="flex items-center px-4 py-3 rounded-xl transition <?= $currentPage === 'home' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>"><i class="fa-solid fa-chart-line w-6 text-center"></i> <span class="font-bold text-sm">Vue d'ensemble</span></a>
                <a href="?page=kyc" class="flex items-center justify-between px-4 py-3 rounded-xl transition <?= $currentPage === 'kyc' ? 'bg-amber-500/10 text-amber-500 border border-amber-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                    <div class="flex items-center font-bold text-sm"><i class="fa-solid fa-id-card-clip w-6 text-center"></i> Validation KYC</div>
                    <?php if ($countPendingKyc > 0): ?><span class="bg-rose-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full animate-pulse"><?= $countPendingKyc ?></span><?php endif; ?>
                </a>
                <p class="px-3 text-[10px] font-black text-slate-500 uppercase tracking-widest mt-6 mb-2">Communauté & Finance</p>
                <a href="?page=users" class="flex items-center px-4 py-3 rounded-xl transition <?= $currentPage === 'users' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>"><i class="fa-solid fa-users w-6 text-center"></i> <span class="font-bold text-sm">Utilisateurs</span></a>
                <a href="?page=reports" class="flex items-center justify-between px-4 py-3 rounded-xl transition <?= $currentPage === 'reports' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                    <div class="flex items-center font-bold text-sm"><i class="fa-solid fa-triangle-exclamation w-6 text-center"></i> Signalements</div>
                    <?php if ($countReports > 0): ?><span class="bg-rose-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full animate-pulse"><?= $countReports ?></span><?php endif; ?>
                </a>
                <p class="px-3 text-[10px] font-black text-slate-500 uppercase tracking-widest mt-6 mb-2">Système</p>
                <a href="?page=settings" class="flex items-center px-4 py-3 rounded-xl transition <?= $currentPage === 'settings' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>"><i class="fa-solid fa-gears w-6 text-center"></i> <span class="font-bold text-sm">Configurations</span></a>
            </nav>
        </div>
        <div class="p-4 border-t border-slate-800 bg-slate-900">
            <a href="logout.php" class="block w-full bg-slate-950 hover:bg-rose-500 text-slate-400 hover:text-white border border-slate-800 font-bold py-2.5 rounded-xl transition text-center text-xs flex items-center justify-center gap-2"><i class="fa-solid fa-power-off"></i> Fermer le sas</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 h-full overflow-y-auto bg-[#0a0f18] p-6 lg:p-10 relative">
        <?php if ($successMsg): ?><div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-6 py-4 rounded-xl mb-8 font-black text-sm flex items-center shadow-sm"><i class="fa-solid fa-circle-check mr-3 text-xl"></i> <?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
        <?php if ($errorMsg): ?><div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 px-6 py-4 rounded-xl mb-8 font-black text-sm flex items-center shadow-sm"><i class="fa-solid fa-triangle-exclamation mr-3 text-xl"></i> <?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

        <?php
        // LE ROUTEUR : Charge dynamiquement le bon fichier depuis le dossier "pages"
        $allowedPages = ['home', 'kyc', 'users', 'settings', 'reports'];
        $pageFile = __DIR__ . "/pages/{$currentPage}.php";
        
        if (in_array($currentPage, $allowedPages) && file_exists($pageFile)) {
            require_once $pageFile;
        } else {
            echo "<div class='text-center py-20'><i class='fa-solid fa-triangle-exclamation text-4xl text-rose-500 mb-4'></i><h2 class='text-2xl font-black text-white'>Module introuvable</h2><p class='text-slate-500'>Veuillez créer le fichier pages/{$currentPage}.php</p></div>";
        }
        ?>
    </main>
</body>
</html>