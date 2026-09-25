<?php
// =========================================================================
// Tableau de bord Affiliation (Parrainage) - MAN GO
// =========================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';

if (empty($_SESSION['user_id'])) {
    header("Location: $baseUrl/login.php?redirect=referral.php");
    exit();
}

$userId = $_SESSION['user_id'];
$db = \App\Core\Database::connect();

// Récupération ou Création du code de parrainage
$stmt = $db->prepare("SELECT referral_code FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$referralCode = $user['referral_code'];

if (empty($referralCode)) {
    $referralCode = 'MG-' . strtoupper(substr(md5($userId . time() . bin2hex(random_bytes(5))), 0, 6));
    $updateStmt = $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
    $updateStmt->execute([$referralCode, $userId]);
}

$referralLink = (strpos($baseUrl, 'http') === 0 ? $baseUrl : "http://localhost" . $baseUrl) . "/register.php?ref=" . $referralCode;
// Récupération des statistiques
$stmtStats = $db->prepare("SELECT COUNT(id) as total_inscrits FROM users WHERE referred_by = :user_id");
$stmtStats->execute([':user_id' => $userId]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
$totalInscrits = $stats['total_inscrits'] ?? 0;

$stmtFilleuls = $db->prepare("SELECT firstname, lastname, created_at, role_id FROM users WHERE referred_by = :user_id ORDER BY created_at DESC LIMIT 5");
$stmtFilleuls->execute([':user_id' => $userId]);
$filleuls = $stmtFilleuls->fetchAll(PDO::FETCH_ASSOC);

$clicsLien = 0; 
$commissionsValidees = 0; 
$commissionsEnAttente = 0;
$commissionPercent = defined('REFERRAL_COMMISSION_PERCENT') ? REFERRAL_COMMISSION_PERCENT : 20;

$pageTitle = "Programme Ambassadeur & Affiliation - MAN GO";

require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-950 min-h-screen pb-20 text-slate-100">
    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-12 w-full relative z-20">
        
        <!-- Hero Section Ultra-DYNAMIQUE avec Effets Lumineux -->
        <div class="relative bg-gradient-to-br from-slate-900 via-slate-900 to-amber-950/40 rounded-3xl p-8 sm:p-14 shadow-2xl border border-amber-500/20 overflow-hidden mb-12">
            <div class="absolute -right-20 -top-20 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-20 -bottom-20 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 max-w-3xl">
                <div class="inline-flex items-center space-x-2 bg-amber-500/10 border border-amber-500/30 px-4 py-1.5 rounded-full text-amber-400 text-xs font-black uppercase tracking-wider mb-6 animate-pulse">
                    <i class="fa-solid fa-bolt"></i> <span>Monétisez votre audience dès aujourd'hui</span>
                </div>
                <h1 class="text-4xl sm:text-6xl font-black tracking-tight mb-6 leading-tight">
                    Devenez <span class="bg-gradient-to-r from-amber-400 to-amber-600 bg-clip-text text-transparent">Ambassadeur MAN GO</span>
                </h1>
                <p class="text-slate-300 text-base sm:text-lg mb-8 leading-relaxed">
                    Partagez votre lien exclusif sur TikTok, Facebook, YouTube ou WhatsApp. Touchez <strong class="text-amber-400 font-extrabold"><?= $commissionPercent ?>% de commission cash</strong> sur chaque abonnement Premium souscrit par votre communauté !
                </p>

                <!-- Box du Lien de Parrainage avec Effet Glow -->
                <div class="bg-slate-950/80 backdrop-blur-md p-3 sm:p-4 rounded-2xl border border-slate-800 shadow-inner flex flex-col sm:flex-row gap-3">
                    <input type="text" id="refLink" value="<?= htmlspecialchars($referralLink) ?>" readonly 
                           class="w-full bg-slate-900 border border-slate-700 text-amber-300 font-mono text-sm px-4 py-3 rounded-xl focus:outline-none">
                    <button onclick="copyLink()" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black px-8 py-3 rounded-xl transition-all shadow-lg hover:shadow-amber-500/20 flex items-center justify-center whitespace-nowrap transform hover:-translate-y-0.5">
                        <i class="fa-regular fa-copy mr-2"></i> Copier le lien
                    </button>
                </div>
            </div>
        </div>

        <!-- Section Statistiques en Cartes Fluides -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <!-- Carte 1 -->
            <div class="bg-slate-900/80 backdrop-blur border border-slate-800 rounded-3xl p-6 shadow-xl relative overflow-hidden group hover:border-amber-500/40 transition">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-slate-400 text-sm font-bold uppercase tracking-wider">Clics sur le lien</span>
                    <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-xl"><i class="fa-solid fa-mouse-pointer"></i></div>
                </div>
                <div class="text-3xl font-black text-white"><?= $clicsLien ?></div>
                <p class="text-xs text-slate-500 mt-2">Visiteurs uniques redirigés</p>
            </div>

            <!-- Carte 2 -->
            <div class="bg-slate-900/80 backdrop-blur border border-slate-800 rounded-3xl p-6 shadow-xl relative overflow-hidden group hover:border-amber-500/40 transition">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-slate-400 text-sm font-bold uppercase tracking-wider">Inscriptions Filleuls</span>
                    <div class="w-12 h-12 rounded-2xl bg-green-500/10 text-green-400 flex items-center justify-center text-xl"><i class="fa-solid fa-users"></i></div>
                </div>
                <div class="text-3xl font-black text-white"><?= $totalInscrits ?></div>
                <p class="text-xs text-slate-500 mt-2">Membres rattachés à votre réseau</p>
            </div>

            <!-- Carte 3 -->
            <div class="bg-gradient-to-br from-amber-600 to-amber-700 rounded-3xl p-6 shadow-xl relative overflow-hidden text-slate-950">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-slate-950/80 text-sm font-black uppercase tracking-wider">Solde Disponible</span>
                    <div class="w-12 h-12 rounded-2xl bg-white/20 text-slate-950 flex items-center justify-center text-xl"><i class="fa-solid fa-wallet"></i></div>
                </div>
                <div class="text-3xl font-black"><?= number_format($commissionsValidees, 0, ',', ' ') ?> <span class="text-lg">FCFA</span></div>
                <button class="mt-4 w-full bg-slate-950 text-white font-bold py-2.5 rounded-xl text-xs hover:bg-slate-900 transition shadow cursor-not-allowed opacity-60" disabled>
                    Demander un retrait (Min. 5 000 FCFA)
                </button>
            </div>
        </div>

        <!-- Grille Principale : Explications & Liste des Filleuls -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Colonne Gauche : Comment ça marche (Dynamique & Visuel) -->
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-slate-900/80 backdrop-blur border border-slate-800 rounded-3xl p-8 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                        <i class="fa-solid fa-circle-info text-amber-500 mr-3"></i> Comment booster vos gains ?
                    </h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div class="bg-slate-950/50 p-6 rounded-2xl border border-slate-800/80 text-center">
                            <div class="w-10 h-10 bg-amber-500/10 text-amber-400 font-black rounded-xl flex items-center justify-center mx-auto mb-4">1</div>
                            <h3 class="font-bold text-sm text-white mb-2">Partagez</h3>
                            <p class="text-xs text-slate-400 leading-relaxed">Diffusez votre lien sur vos réseaux (TikTok, Bio Instagram, Groupes Facebook).</p>
                        </div>
                        <div class="bg-slate-950/50 p-6 rounded-2xl border border-slate-800/80 text-center">
                            <div class="w-10 h-10 bg-amber-500/10 text-amber-400 font-black rounded-xl flex items-center justify-center mx-auto mb-4">2</div>
                            <h3 class="font-bold text-sm text-white mb-2">Invitez</h3>
                            <p class="text-xs text-slate-400 leading-relaxed">Vos abonnés créent leur compte vendeur ou acheteur en toute simplicité.</p>
                        </div>
                        <div class="bg-slate-950/50 p-6 rounded-2xl border border-slate-800/80 text-center">
                            <div class="w-10 h-10 bg-amber-500/10 text-amber-400 font-black rounded-xl flex items-center justify-center mx-auto mb-4">3</div>
                            <h3 class="font-bold text-sm text-white mb-2">Encaissez</h3>
                            <p class="text-xs text-slate-400 leading-relaxed">Touchez vos commissions cash dès qu'ils prennent un abonnement Pro.</p>
                        </div>
                    </div>
                </div>

                <!-- Tableau des Filleuls -->
                <div class="bg-slate-900/80 backdrop-blur border border-slate-800 rounded-3xl p-8 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                        <i class="fa-solid fa-address-book text-amber-500 mr-3"></i> Historique de vos Filleuls
                    </h2>
                    
                    <?php if (empty($filleuls)): ?>
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-slate-950 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-800">
                                <i class="fa-solid fa-user-astronaut text-slate-600 text-3xl"></i>
                            </div>
                            <p class="text-slate-300 font-bold">Aucun filleul enregistré pour l'instant.</p>
                            <p class="text-xs text-slate-500 mt-1">Votre réseau s'affichera ici dès vos premiers partages !</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($filleuls as $f): ?>
                                <div class="flex items-center justify-between p-4 bg-slate-950 rounded-2xl border border-slate-800/60 hover:border-amber-500/30 transition">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 bg-amber-500/10 text-amber-400 rounded-xl flex items-center justify-center font-bold text-sm shadow-inner">
                                            <?= strtoupper(substr($f['firstname'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-white text-sm"><?= htmlspecialchars($f['firstname'] . ' ' . $f['lastname']) ?></p>
                                            <p class="text-xs text-slate-500">Inscrit le <?= date('d/m/Y', strtotime($f['created_at'])) ?></p>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="bg-slate-800 text-amber-400 text-[10px] px-3 py-1 rounded-full font-extrabold uppercase tracking-wider">
                                            <?= ($f['role_id'] == 4) ? 'Vendeur Pro' : 'Acheteur' ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Colonne Droite : Astuces Réseaux Sociaux -->
            <div class="space-y-6">
                <div class="bg-gradient-to-br from-indigo-950/60 to-slate-900 border border-indigo-500/20 rounded-3xl p-6 shadow-xl">
                    <h3 class="font-bold text-white mb-4 flex items-center">
                        <i class="fa-brands fa-tiktok text-amber-400 text-xl mr-2"></i> Kit Creator TikTok & FB
                    </h3>
                    <p class="text-xs text-slate-300 leading-relaxed mb-4">
                        Conseil d'pro : Créez une courte vidéo montrant comment publier une annonce en 1 clic sur MAN GO et mettez votre lien en description. C'est le moyen numéro un pour générer des centaines de filleuls sans effort !
                    </p>
                    <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-[11px] text-indigo-300 font-mono">
                        💡 Astuce : Utilisez un réducteur de lien ou mettez le lien direct dans votre bio.
                    </div>
                </div>

                <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 shadow-xl text-center">
                    <h3 class="font-bold text-white mb-2">Besoin d'aide ?</h3>
                    <p class="text-xs text-slate-400 mb-4">Notre équipe support accompagne les ambassadeurs VIP 24h/24.</p>
                    <a href="mailto:support@mango.tg" class="inline-block bg-slate-800 hover:bg-slate-700 text-amber-400 text-xs font-bold px-6 py-2.5 rounded-xl transition">
                        Contacter le Support
                    </a>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
function copyLink() {
    var copyText = document.getElementById("refLink");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value).then(() => {
        alert("✨ Lien copié avec succès ! Prêt à être partagé.");
    });
}
</script>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>