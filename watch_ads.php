<?php
// =========================================================================
// Page "Watch to Earn" & Faucet - Gagner des Crédits MAN GO (Version Réelle)
// =========================================================================

if (!defined('APP_PATH')) define('APP_PATH', __DIR__);
require_once __DIR__ . '/config/config.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (empty($currentUserId)) {
    header("Location: " . $baseUrl . "/login.php?redirect=watch_ads.php");
    exit();
}

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/WalletManager.php';

$db = \App\Core\Database::connect();

// =========================================================================
// CHARGEMENT DES DONNÉES DE LA PAGE
// =========================================================================
$wallets = \App\Core\WalletManager::getWallets($db,$currentUserId);
$currentBalance =$wallets['credits_balance'];

// Récupérer l'historique des 5 dernières transactions
$stmtHistory =$db->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmtHistory->execute([$currentUserId]);
$transactions =$stmtHistory->fetchAll(PDO::FETCH_ASSOC);

// Objectif de gamification (ex: 1000 crédits pour un mois Premium)
require_once __DIR__ . '/core/Settings.php';
$goal = (int)\App\Core\Settings::get('premium_price', 5000); // L'objectif s'adapte au prix de l'abonnement
$progressPercentage = min(100, ($currentBalance / $goal) * 100);

// Vérifier si le Faucet est déjà prêt ou s'il faut afficher un timer au chargement
$stmtCheckFaucet =$db->prepare("SELECT last_claim_time FROM hourly_faucet WHERE user_id = ?");
$stmtCheckFaucet->execute([$currentUserId]);$faucetRow = $stmtCheckFaucet->fetch(PDO::FETCH_ASSOC);$initialRemainingSeconds = 0;

if ($faucetRow) {
    $lastClaim = strtotime($faucetRow['last_claim_time']);
    $diffSeconds = time() -$lastClaim;
    if ($diffSeconds < 3600) {
        $initialRemainingSeconds = 3600 -$diffSeconds;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gagnez des Crédits - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <style>
        .pulse-glow { animation: pulseGlow 2s infinite; }
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        .progress-bar-animated { transition: width 1s ease-in-out; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

<?php 
$headerPath = __DIR__ . '/app/views/layouts/header.php';
if (file_exists($headerPath)) require_once$headerPath;
?>

<main class="flex-1 max-w-5xl mx-auto px-4 py-12 w-full">
    
    <!-- EN-TÊTE GAMIFIÉ -->
    <div class="bg-slate-900 rounded-3xl p-8 shadow-2xl relative overflow-hidden mb-8">
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-amber-500 rounded-full blur-3xl opacity-20"></div>
        <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-blue-500 rounded-full blur-3xl opacity-20"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8 text-white">
            <div class="text-center md:text-left">
                <span class="bg-amber-500/20 text-amber-400 border border-amber-500/30 text-xs font-black px-3 py-1 rounded-full uppercase tracking-widest">
                    <i class="fa-solid fa-bolt"></i> Watch & Earn
                </span>
                <h1 class="text-3xl md:text-4xl font-black mt-4 mb-2">Financez votre activité</h1>
                <p class="text-slate-400 max-w-md">Récoltez des Crédits MAN GO via nos partenaires pour débloquer vos abonnements gratuitement.</p>
            </div>
            
            <!-- Le Solde (Wallet) -->
            <div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-2xl text-center min-w-[200px]">
                <p class="text-slate-300 text-sm font-bold uppercase mb-1">Votre Solde Actuel</p>
                <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-coins text-amber-400 text-3xl"></i>
                    <span id="display-balance" class="text-5xl font-black text-white"><?= number_format($currentBalance, 0, ',', ' ') ?></span>
                </div>
                <p class="text-xs text-slate-400 mt-2">Crédits MAN GO</p>
            </div>
        </div>

        <!-- Barre de progression -->
        <div class="relative z-10 mt-8">
            <div class="flex justify-between text-xs font-bold text-slate-300 mb-2">
                <span>Progression vers l'Abonnement Pro</span>
                <span id="display-goal"><?= number_format($currentBalance, 0, ',', ' ') ?> / <?= number_format($goal, 0, ',', ' ') ?></span>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-3">
                <div id="progress-bar" class="bg-gradient-to-r from-amber-500 to-orange-500 h-3 rounded-full progress-bar-animated" style="width: <?= $progressPercentage ?>%"></div>
            </div>
        </div>
    </div>

    <!-- ZONE D'ACTION : FAUCET & VIDÉOS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        
        <!-- ACTION 1 : LE ROBINET HORAIRE (FAUCET) -->
        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-200 text-center relative overflow-hidden flex flex-col justify-between group">
            <div class="absolute -right-10 -top-10 w-32 h-32 bg-emerald-500 rounded-full blur-3xl opacity-10"></div>
            
            <div>
                <div class="w-16 h-16 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto text-3xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-stopwatch text-emerald-500"></i>
                </div>
                <h2 class="text-xl font-black text-slate-900 mb-2">Bonus Horaire</h2>
                <p class="text-slate-500 text-sm mb-4 px-4">Validez la sécurité et réclamez <strong>+2 Crédits</strong>. Disponible toutes les 60 minutes.</p>
                
                <!-- Zone de publicité pour vos futurs revenus -->
                <div class="w-full h-16 bg-slate-100 border border-slate-200 border-dashed rounded-lg mb-4 flex items-center justify-center text-xs text-slate-400 font-bold">
                    Espace Publicitaire (Vos Revenus)
                </div>

                <!-- Simulation du reCAPTCHA -->
                <div class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 flex items-center justify-between mb-6 shadow-inner cursor-pointer hover:bg-slate-100 transition" onclick="document.getElementById('fake-captcha-check').classList.remove('hidden')">
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 border-2 border-slate-300 rounded bg-white flex items-center justify-center">
                            <i id="fake-captcha-check" class="fa-solid fa-check text-green-500 hidden"></i>
                        </div>
                        <span class="text-sm font-bold text-slate-700">Je suis un humain</span>
                    </div>
                    <img src="https://www.gstatic.com/recaptcha/api2/logo_48.png" alt="recaptcha" class="h-6 opacity-80">
                </div>
            </div>
            
            <button id="faucet-btn" onclick="claimFaucet()" class="w-full bg-emerald-100 hover:bg-emerald-500 hover:text-white text-emerald-700 font-black py-4 px-6 rounded-xl transition-colors text-sm uppercase shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-coins"></i> Réclamer
            </button>
            <div id="faucet-timer" class="hidden font-black text-4xl text-emerald-600 mt-2 tracking-widest bg-emerald-50 py-3 rounded-xl border border-emerald-100"></div>
        </div>

        <!-- ACTION 2 : VIDÉOS SPONSORISÉES -->
        <div class="bg-slate-900 rounded-3xl p-8 shadow-sm border border-slate-800 text-center relative overflow-hidden group">
            <div class="absolute -left-10 -bottom-10 w-32 h-32 bg-amber-500 rounded-full blur-3xl opacity-10 group-hover:opacity-20 transition"></div>
            <div class="w-16 h-16 bg-slate-800 text-amber-500 rounded-full flex items-center justify-center mx-auto text-3xl mb-4 group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-play"></i>
            </div>
            <h2 class="text-xl font-black text-white mb-2">Vidéos Sponsorisées</h2>
            <p class="text-slate-400 text-sm mb-6 px-4">Regardez une courte vidéo offerte par nos partenaires et gagnez des crédits en illimité.</p>
            
            <button onclick="showRealAd()" class="pulse-glow w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-900 font-black py-4 px-6 rounded-xl transition-colors text-sm uppercase shadow-lg shadow-amber-500/20 flex items-center justify-center gap-2">
                <i class="fa-solid fa-video"></i> Lancer une vidéo
            </button>
        </div>

    </div>

    <!-- HISTORIQUE DES GAINS -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <h3 class="font-black text-slate-800 uppercase text-sm tracking-wider"><i class="fa-solid fa-clock-rotate-left text-amber-500 mr-2"></i> Historique récent</h3>
        </div>
        <div class="p-0">
            <ul id="transactions-list" class="divide-y divide-slate-100">
                <?php if(empty($transactions)): ?>
                    <li class="p-6 text-center text-slate-400 text-sm italic" id="empty-history">Aucun gain pour le moment. Lancez votre première vidéo !</li>
                <?php else: ?>
                    <?php foreach($transactions as$tx): ?>
                        <li class="p-4 flex items-center justify-between hover:bg-slate-50 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $tx['type'] === 'credit' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600' ?>">
                                    <i class="fa-solid <?= $tx['type'] === 'credit' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($tx['description']) ?></p>
                                    <p class="text-xs text-slate-400"><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></p>
                                </div>
                            </div>
                            <div class="font-black <?= $tx['type'] === 'credit' ? 'text-emerald-500' : 'text-red-500' ?>">
                                <?= $tx['type'] === 'credit' ? '+' : '-' ?><?= number_format($tx['amount'], 0, ',', ' ') ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

</main>

<script>
    const currentUserId = <?= json_encode($currentUserId) ?>;
    const goal = 1000;

    // --- LOGIQUE DE LA RÉGIE VIDÉO ---
    function showRealAd() {
        if (typeof showRewardedAd === 'function') {
            showRewardedAd({
                userId: currentUserId,
                onComplete: function() {
                    alert("Félicitations ! La vidéo est validée. Vos crédits ont été ajoutés.");
                    location.reload(); 
                },
                onError: function(err) {
                    alert("Erreur lors du chargement de la vidéo. Veuillez réessayer plus tard.");
                }
            });
        } else {
            alert("Mode Réel activé : Le script de la régie publicitaire n'est pas encore intégré. Créez votre compte régie avec support@mango-app.com pour obtenir le code de diffusion.");
        }
    }

    // --- LOGIQUE DU ROBINET HORAIRE (FAUCET) ---
    let countdownInterval;

    function startTimer(secondsLeft) {
        const btn = document.getElementById('faucet-btn');
        const timerDisplay = document.getElementById('faucet-timer');
        
        btn.classList.add('hidden');
        timerDisplay.classList.remove('hidden');

        clearInterval(countdownInterval);
        
        countdownInterval = setInterval(() => {
            if (secondsLeft <= 0) {
                clearInterval(countdownInterval);
                btn.classList.remove('hidden');
                timerDisplay.classList.add('hidden');
                btn.innerHTML = '<i class="fa-solid fa-coins"></i> Réclamer';
                btn.disabled = false;
                document.getElementById('fake-captcha-check').classList.add('hidden');
                return;
            }
            
            let m = Math.floor(secondsLeft / 60);
            let s = secondsLeft % 60;
            timerDisplay.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
            secondsLeft--;
        }, 1000);
    }

    // Initialisation au chargement de la page si l'utilisateur est déjà en délai d'attente
    const initSeconds = <?= $initialRemainingSeconds ?>;
    if(initSeconds > 0) {
        startTimer(initSeconds);
    }

    function claimFaucet() {
        // Vérification de notre faux captcha
        if(document.getElementById('fake-captcha-check').classList.contains('hidden')) {
            alert("Veuillez valider le Captcha humain d'abord.");
            return;
        }

        const btn = document.getElementById('faucet-btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Traitement...';
        btn.disabled = true;

        fetch('claim_faucet.php', { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
                
                // Mise à jour des soldes affichés sans recharger la page
                document.getElementById('display-balance').innerText = data.new_balance.toLocaleString('fr-FR');
                let newPercent = Math.min(100, (data.new_balance / goal) * 100);
                document.getElementById('progress-bar').style.width = `${newPercent}%`;
                
                alert(data.message);
                startTimer(3600); // Lance le chrono de 60 min (3600 sec)
            } else {
                if(data.remaining_seconds) {
                    startTimer(data.remaining_seconds);
                } else {
                    alert(data.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            }
        })
        .catch(error => {
            alert("Erreur de communication avec le serveur.");
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
</script>
</body>
</html>