<?php
// =========================================================================
// Page "Watch to Earn" - Gagner des Crédits MAN GO
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
require_once __DIR__ . '/core/WalletManager.php'; // On inclut notre nouveau banquier !

$db = \App\Core\Database::connect();

// =========================================================================
// GESTION DE LA RÉCOMPENSE (Appel AJAX en arrière-plan)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) &&$_POST['ajax_action'] === 'claim_ad_reward') {
    header('Content-Type: application/json');
    try {
        // On donne 5 crédits pour chaque pub regardée
        $rewardAmount = 5;          \App\Core\WalletManager::addCredits($db, $currentUserId,$rewardAmount, 'watch_ad', 'Récompense publicitaire');
        
        $newBalance = \App\Core\WalletManager::getBalance($db,$currentUserId);
        echo json_encode(['success' => true, 'new_balance' => $newBalance, 'reward' =>$rewardAmount]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// =========================================================================
// CHARGEMENT DE LA PAGE NORMALE
// =========================================================================
$currentBalance = \App\Core\WalletManager::getBalance($db,$currentUserId);

// Récupérer l'historique des 5 dernières transactions
$stmtHistory =$db->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmtHistory->execute([$currentUserId]);
$transactions =$stmtHistory->fetchAll(PDO::FETCH_ASSOC);

// Objectif de gamification (ex: 1000 crédits pour un mois Premium)
$goal = 1000;
$progressPercentage = min(100, ($currentBalance / $goal) * 100);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gagnez des Crédits - MAN GO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Librairie de Confettis pour la récompense -->
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
        <!-- Décoration d'arrière plan -->
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-amber-500 rounded-full blur-3xl opacity-20"></div>
        <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-blue-500 rounded-full blur-3xl opacity-20"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8 text-white">
            <div class="text-center md:text-left">
                <span class="bg-amber-500/20 text-amber-400 border border-amber-500/30 text-xs font-black px-3 py-1 rounded-full uppercase tracking-widest">
                    <i class="fa-solid fa-bolt"></i> Watch & Earn
                </span>
                <h1 class="text-3xl md:text-4xl font-black mt-4 mb-2">Financez votre activité</h1>
                <p class="text-slate-400 max-w-md">Visionnez des publicités sponsorisées pour accumuler des Crédits MAN GO et débloquez vos abonnements ou boostez vos annonces gratuitement.</p>
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

    <!-- ZONE D'ACTION : LE BOUTON POUR REGARDER LES PUBS -->
    <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-200 text-center mb-8">
        <div class="w-20 h-20 bg-amber-100 text-amber-500 rounded-full flex items-center justify-center mx-auto text-4xl mb-4">
            <i class="fa-solid fa-play"></i>
        </div>
        <h2 class="text-2xl font-black text-slate-900 mb-2">Publicité Sponsorisée Disponible</h2>
        <p class="text-slate-500 mb-6 max-w-lg mx-auto">Regardez une courte vidéo de 10 secondes offerte par nos partenaires et gagnez instantanément <strong>+5 Crédits</strong>.</p>
        
        <button onclick="showRealAd()" class="pulse-glow bg-slate-900 hover:bg-amber-500 text-white font-black py-4 px-10 rounded-full transition-colors text-lg uppercase shadow-xl flex items-center justify-center gap-3 mx-auto">
            <i class="fa-solid fa-video"></i> Lancer la vidéo
        </button>
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

<!-- ======================================================== -->
<!-- MODAL DE LA PUBLICITÉ (Le Simulateur)                    -->
<!-- ======================================================== -->
<div id="ad-modal" class="fixed inset-0 bg-slate-950/90 backdrop-blur-sm z-[100] hidden flex-col items-center justify-center">
    <div class="max-w-2xl w-full mx-4 bg-black rounded-2xl overflow-hidden shadow-2xl border border-slate-800 relative">
        
        <!-- Haut du lecteur -->
        <div class="absolute top-0 inset-x-0 p-4 flex justify-between items-center z-10 bg-gradient-to-b from-black/80 to-transparent">
            <span class="bg-amber-500 text-slate-900 text-xs font-black px-2 py-1 rounded">Sponsorisé</span>
            <span id="ad-timer" class="text-white font-bold bg-black/50 px-3 py-1 rounded-full border border-white/20">La vidéo commence...</span>
        </div>

        <!-- Zone Vidéo (Simulation) -->
        <div class="w-full aspect-video bg-slate-900 flex flex-col items-center justify-center relative overflow-hidden">
            <!-- Animation de fond (Simule une vidéo en cours) -->
            <div class="absolute inset-0 bg-gradient-to-tr from-indigo-900 to-purple-900 animate-pulse opacity-50"></div>
            
            <i class="fa-solid fa-play text-white/20 text-7xl mb-4 animate-bounce"></i>
            <h3 class="text-white text-xl font-bold z-10 text-center px-4">Découvrez les offres exclusives de nos partenaires !</h3>
            <p class="text-slate-400 text-sm z-10 mt-2">Veuillez patienter jusqu'à la fin du décompte...</p>
        </div>

        <!-- Bas du lecteur (Le Bouton de Récompense) -->
        <div class="p-6 bg-slate-900 border-t border-slate-800 flex justify-center">
            <button id="claim-btn" onclick="claimReward()" disabled class="bg-slate-700 text-slate-400 font-black py-3 px-8 rounded-full cursor-not-allowed transition-all duration-300 w-full md:w-auto">
                <i class="fa-solid fa-lock mr-2"></i> Patientez pour réclamer
            </button>
        </div>
    </div>
</div>

<script>
    let currentBalance = <?= $currentBalance ?>;
    const goal = <?= $goal ?>;
    let countdownInterval;

    // 1. Ouvre la modale et lance le faux timer de pub
    function openAdModal() {
        document.getElementById('ad-modal').classList.remove('hidden');
        document.getElementById('ad-modal').classList.add('flex');
        
        const claimBtn = document.getElementById('claim-btn');
        const timerText = document.getElementById('ad-timer');
        
        // Reset du bouton
        claimBtn.disabled = true;
        claimBtn.className = "bg-slate-700 text-slate-400 font-black py-3 px-8 rounded-full cursor-not-allowed transition-all duration-300 w-full md:w-auto";
        claimBtn.innerHTML = '<i class="fa-solid fa-lock mr-2"></i> Patientez pour réclamer';

        let timeLeft = 10; // 10 secondes de publicité
        
        countdownInterval = setInterval(() => {
            timerText.innerHTML = `Publicité - ${timeLeft}s restantes`;
            timeLeft--;

            if (timeLeft < 0) {
                clearInterval(countdownInterval);
                timerText.innerHTML = "Terminé !";
                
                // Activer le bouton de récompense
                claimBtn.disabled = false;
                claimBtn.className = "pulse-glow bg-amber-500 hover:bg-amber-400 text-slate-900 font-black py-3 px-8 rounded-full transition-all duration-300 w-full md:w-auto shadow-lg";
                claimBtn.innerHTML = '<i class="fa-solid fa-gift mr-2"></i> Réclamer mes Crédits';
            }
        }, 1000);
    }

    // 2. Réclame la récompense via AJAX
    function claimReward() {
        const claimBtn = document.getElementById('claim-btn');
        claimBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Créditement en cours...';
        claimBtn.disabled = true;

        // Appel AJAX au serveur PHP
        const formData = new FormData();
        formData.append('ajax_action', 'claim_ad_reward');

        fetch('watch_ads.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fermer la modale
                document.getElementById('ad-modal').classList.add('hidden');
                document.getElementById('ad-modal').classList.remove('flex');
                
                // Feu d'artifice !
                confetti({
                    particleCount: 150,
                    spread: 70,
                    origin: { y: 0.6 },
                    colors: ['#f59e0b', '#ef4444', '#3b82f6']
                });

                // Mettre à jour les chiffres à l'écran
                currentBalance = data.new_balance;
                document.getElementById('display-balance').innerText = currentBalance.toLocaleString('fr-FR');
                document.getElementById('display-goal').innerText = `${currentBalance.toLocaleString('fr-FR')} / ${goal.toLocaleString('fr-FR')}`;
                
                // Mettre à jour la barre de progression
                let newPercent = Math.min(100, (currentBalance / goal) * 100);
                document.getElementById('progress-bar').style.width = `${newPercent}%`;

                // Ajouter la ligne d'historique en haut de la liste
                const historyList = document.getElementById('transactions-list');
                const emptyMsg = document.getElementById('empty-history');
                if (emptyMsg) emptyMsg.remove();

                const now = new Date();
                const timeString = `${now.getDate().toString().padStart(2,'0')}/${(now.getMonth()+1).toString().padStart(2,'0')}/${now.getFullYear()} ${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}`;

                const newLi = document.createElement('li');
                newLi.className = 'p-4 flex items-center justify-between bg-emerald-50/50 transition';
                newLi.innerHTML = `
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center bg-emerald-100 text-emerald-600">
                            <i class="fa-solid fa-arrow-down"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-800 text-sm">Récompense publicitaire</p>
                            <p class="text-xs text-slate-400">${timeString}</p>
                        </div>
                    </div>
                    <div class="font-black text-emerald-500">
                        +${data.reward}
                    </div>
                `;
                
                historyList.insertBefore(newLi, historyList.firstChild);

                // Enlever le surlignage vert après 2 secondes
                setTimeout(() => { newLi.classList.remove('bg-emerald-50/50', 'hover:bg-slate-50'); }, 2000);

            } else {
                alert("Erreur lors de l'attribution : " + data.message);
                document.getElementById('ad-modal').classList.add('hidden');
            }
        })
        .catch(error => {
            alert("Erreur de connexion au serveur.");
            document.getElementById('ad-modal').classList.add('hidden');
        });
    }
</script>
</body>
</html>