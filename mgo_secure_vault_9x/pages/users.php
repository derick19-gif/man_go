<?php
// =========================================================================
// pages/users.php - Gestion de la Communauté & Actions Manuelles (Boost/Crédit)
// =========================================================================

$localSuccess = '';
$localError = '';

// --- TRAITEMENT DES ACTIONS MANUELLES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $localAction = $_POST['action'] ?? '';
    
    // 1. CRÉDITER MANUELLEMENT LE PORTEFEUILLE
    if ($localAction === 'credit_wallet') {
        $targetUserId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        
        if ($targetUserId && $amount > 0) {
            try {
                $db->beginTransaction();
                $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $targetUserId]);
                $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, ?, 'manual_deposit', 'Recharge manuelle par Admin (Flooz/T-Money/Espèces)', 'completed', NOW())")->execute([$targetUserId, $amount]);
                $db->commit();
                $localSuccess = "Le compte de l'utilisateur #$targetUserId a été crédité de " . number_format($amount, 0, ',', ' ') . " FCFA.";
            } catch(Exception $e) {
                $db->rollBack();
                $localError = "Erreur lors du crédit : " . $e->getMessage();
            }
        }
    }
    
    // 2. FORCER L'ABONNEMENT (BOOST VIP) SANS COMMISSION
    if ($localAction === 'force_subscription') {
        $targetUserId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);
        $planId = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT); // 2 = Premium, 3 = Starter
        
        if ($targetUserId && $planId) {
            try {
                $db->beginTransaction();
                $planName = ($planId == 2) ? "Premium VIP" : "Starter Pro";
                
                // On passe l'utilisateur en Premium (is_premium = 1) et Vendeur Pro (role_id = 4)
                $db->prepare("UPDATE users SET is_premium = 1, role_id = 4 WHERE id = ?")->execute([$targetUserId]);
                
                // On trace l'opération à 0 FCFA (Puisque payé hors ligne ou offert)
                $db->prepare("INSERT INTO transactions (user_id, amount, payment_method, transaction_ref, status, created_at) VALUES (?, 0, 'manual_subscription', 'Abonnement $planName forcé par Admin (Cadeau / Hors ligne)', 'completed', NOW())")->execute([$targetUserId]);
                
                // INFO : Aucune logique de parrainage (referral_commissions) n'est exécutée ici.
                
                $db->commit();
                $localSuccess = "Le boost '$planName' a été activé avec succès pour l'utilisateur #$targetUserId.";
            } catch(Exception $e) {
                $db->rollBack();
                $localError = "Erreur lors de l'activation : " . $e->getMessage();
            }
        }
    }
}

// --- RECHERCHE ET PAGINATION ---
$search = $_GET['search'] ?? '';
$pageLimit = 20; 
$pageNum = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($pageNum - 1) * $pageLimit;

$whereSql = "";
$params = [];
if (!empty($search)) {
    $whereSql = "WHERE id = ? OR firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ?";
    $params = [$search, "%$search%", "%$search%", "%$search%", "%$search%"];
}

$stmtCount = $db->prepare("SELECT COUNT(id) FROM users $whereSql");
$stmtCount->execute($params);
$totalUsersFound = $stmtCount->fetchColumn();
$totalPages = ceil($totalUsersFound / $pageLimit);

$stmtUsers = $db->prepare("SELECT id, firstname, lastname, email, phone, role_id, balance, is_active, is_verified, kyc_status, is_premium, created_at FROM users $whereSql ORDER BY created_at DESC LIMIT $pageLimit OFFSET $offset");
$stmtUsers->execute($params);
$usersList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if ($localSuccess): ?>
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-6 py-4 rounded-xl mb-8 font-black text-sm flex items-center shadow-sm">
        <i class="fa-solid fa-circle-check mr-3 text-xl"></i> <?= htmlspecialchars($localSuccess) ?>
    </div>
<?php endif; ?>
<?php if ($localError): ?>
    <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 px-6 py-4 rounded-xl mb-8 font-black text-sm flex items-center shadow-sm">
        <i class="fa-solid fa-triangle-exclamation mr-3 text-xl"></i> <?= htmlspecialchars($localError) ?>
    </div>
<?php endif; ?>

<div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
    <div>
        <h2 class="text-3xl font-black text-white">Gestion de la Communauté</h2>
        <p class="text-slate-400 text-sm mt-1">Supervisez les comptes, créditez les soldes et offrez des abonnements.</p>
    </div>
    
    <form method="GET" class="flex w-full md:w-auto shadow-lg">
        <input type="hidden" name="page" value="users">
        <div class="relative flex-1 md:w-64">
            <i class="fa-solid fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-500"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ID, Nom, Email..." class="w-full bg-slate-900 border border-slate-700 text-white rounded-l-xl py-3 pl-12 pr-4 outline-none focus:border-blue-500 text-sm font-bold">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-r-xl text-sm font-black transition">Chercher</button>
    </form>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl pb-32">
    <div class="p-6 border-b border-slate-800 flex justify-between items-center">
        <h3 class="font-black text-lg text-white">Base de données (<?= $totalUsersFound ?> résultats)</h3>
    </div>
    
    <div class="overflow-x-auto min-h-[400px]">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-slate-950/50 text-slate-400 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="py-4 px-6">ID / Utilisateur</th>
                    <th class="py-4 px-6">Contact & Solde</th>
                    <th class="py-4 px-6">Abonnement</th>
                    <th class="py-4 px-6 text-center">Actions Administrateur</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php if(empty($usersList)): ?>
                    <tr><td colspan="4" class="text-center py-10 text-slate-500">Aucun utilisateur trouvé.</td></tr>
                <?php else: ?>
                    <?php foreach ($usersList as $user): ?>
                        <tr class="hover:bg-slate-800/50 <?= (isset($user['is_active']) && $user['is_active'] == 0) ? 'opacity-50 grayscale' : '' ?>">
                            <td class="py-4 px-6">
                                <p class="font-black text-white">#<?= $user['id'] ?> - <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></p>
                            </td>
                            <td class="py-4 px-6 text-slate-400">
                                <p class="text-xs"><?= htmlspecialchars($user['phone'] ?? $user['email']) ?></p>
                                <p class="text-emerald-500 font-black mt-1 text-xs"><i class="fa-solid fa-wallet"></i> <?= number_format($user['balance'] ?? 0, 0, ',', ' ') ?> FCFA</p>
                            </td>
                            <td class="py-4 px-6">
                                <?php if (isset($user['is_premium']) && $user['is_premium'] == 1): ?>
                                    <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-[10px] font-black border border-blue-500/30">PREMIUM VIP</span>
                                <?php else: ?>
                                    <span class="bg-slate-800 text-slate-400 px-3 py-1 rounded-full text-[10px] font-black">STANDARD</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <?php if ($user['id'] == $_SESSION['admin_user_id']): ?>
                                    <span class="text-emerald-600 font-black text-[10px] uppercase">Vous-même</span>
                                <?php else: ?>
                                    <div class="flex items-center justify-center gap-2">
                                        
                                        <!-- Bouton Créditer (Ouvre la modale) -->
                                        <div class="relative group cursor-help">
                                            <button onclick="openCreditModal(<?= $user['id'] ?>, '<?= addslashes(htmlspecialchars($user['firstname'] . ' ' . $user['lastname'])) ?>')" class="bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500 hover:text-white px-3 py-2 rounded border border-emerald-500/30 text-xs font-bold transition">
                                                <i class="fa-solid fa-coins"></i> Créditer
                                            </button>
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-48 bg-slate-800 text-white text-[10px] p-2.5 rounded-lg shadow-xl z-50 normal-case font-medium text-left">
                                                Ajouter de l'argent sur le solde MAN GO de ce client suite à un transfert T-Money/Flooz.
                                            </div>
                                        </div>

                                        <!-- Bouton Forcer Abonnement (Ouvre la modale) -->
                                        <div class="relative group cursor-help">
                                            <button onclick="openBoostModal(<?= $user['id'] ?>, '<?= addslashes(htmlspecialchars($user['firstname'] . ' ' . $user['lastname'])) ?>')" class="bg-amber-500/10 text-amber-500 hover:bg-amber-500 hover:text-white px-3 py-2 rounded border border-amber-500/30 text-xs font-bold transition">
                                                <i class="fa-solid fa-bolt"></i> Boost VIP
                                            </button>
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-48 bg-slate-800 text-white text-[10px] p-2.5 rounded-lg shadow-xl z-50 normal-case font-medium text-left">
                                                Offrir un abonnement (Cadeau/Concours). <strong>Aucune commission ne sera versée au parrain.</strong>
                                            </div>
                                        </div>

                                        <!-- Suspendre / Réactiver (Existant) -->
                                        <?php $isActive = $user['is_active'] ?? 1; ?>
                                        <form method="POST" onsubmit="return confirm('Modifier le statut de ce compte ?');" class="inline">
                                            <input type="hidden" name="action" value="toggle_user_status">
                                            <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= $isActive == 1 ? 0 : 1 ?>">
                                            <button type="submit" class="<?= $isActive == 1 ? 'bg-rose-500/10 text-rose-500 hover:bg-rose-500' : 'bg-slate-700 text-slate-300 hover:bg-emerald-500' ?> hover:text-white px-3 py-2 rounded border <?= $isActive == 1 ? 'border-rose-500/30' : 'border-slate-600' ?> text-xs font-bold transition">
                                                <i class="fa-solid <?= $isActive == 1 ? 'fa-ban' : 'fa-unlock' ?>"></i>
                                            </button>
                                        </form>

                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-slate-800 bg-slate-900/50 flex justify-between items-center">
            <span class="text-xs font-bold text-slate-500">Page <?= $pageNum ?> sur <?= $totalPages ?></span>
            <div class="flex gap-2">
                <?php if ($pageNum > 1): ?>
                    <a href="?page=users&search=<?= urlencode($search) ?>&p=<?= $pageNum - 1 ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition">Précédent</a>
                <?php endif; ?>
                <?php if ($pageNum < $totalPages): ?>
                    <a href="?page=users&search=<?= urlencode($search) ?>&p=<?= $pageNum + 1 ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition">Suivant</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ================= MODALES D'ACTION ================= -->

<!-- MODALE CRÉDITER SOLDE -->
<div id="creditModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 rounded-3xl overflow-hidden max-w-sm w-full shadow-2xl border border-slate-700">
        <div class="p-5 border-b border-slate-800 flex justify-between items-center">
            <h4 class="font-black text-white flex items-center gap-2"><i class="fa-solid fa-coins text-emerald-500"></i> Créditer le Solde</h4>
            <button onclick="document.getElementById('creditModal').classList.add('hidden')" class="text-slate-400 hover:text-rose-500 text-xl">&times;</button>
        </div>
        <div class="p-6">
            <div class="bg-emerald-500/10 p-3 rounded-lg border border-emerald-500/20 mb-4">
                <p class="text-[10px] text-emerald-400 font-bold leading-relaxed">Cet argent sera ajouté au portefeuille MAN GO du client. Le client pourra ensuite l'utiliser pour payer son abonnement ou faire des transferts.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="credit_wallet">
                <input type="hidden" name="target_user_id" id="credit_user_id" value="">
                
                <p class="text-xs font-bold text-slate-300 mb-4">Client : <span id="credit_user_name" class="text-emerald-400 font-black"></span></p>

                <div class="mb-6">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Montant à ajouter (FCFA) *</label>
                    <input type="number" name="amount" required placeholder="Ex: 5000" class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl py-3 px-4 outline-none focus:border-emerald-500 font-black">
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-3 rounded-xl transition shadow-lg shadow-emerald-500/20 uppercase text-xs">
                    Valider le Dépôt
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODALE FORCER ABONNEMENT (BOOST) -->
<div id="boostModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 rounded-3xl overflow-hidden max-w-sm w-full shadow-2xl border border-slate-700">
        <div class="p-5 border-b border-slate-800 flex justify-between items-center">
            <h4 class="font-black text-white flex items-center gap-2"><i class="fa-solid fa-bolt text-amber-500"></i> Forcer l'Abonnement</h4>
            <button onclick="document.getElementById('boostModal').classList.add('hidden')" class="text-slate-400 hover:text-rose-500 text-xl">&times;</button>
        </div>
        <div class="p-6">
            <div class="bg-amber-500/10 p-3 rounded-lg border border-amber-500/20 mb-4">
                <p class="text-[10px] text-amber-400 font-bold leading-relaxed">Active l'abonnement instantanément (Cadeau/Concours/Paiement manuel). <strong class="text-rose-400">Aucune commission ne sera versée au parrain du client.</strong></p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="force_subscription">
                <input type="hidden" name="target_user_id" id="boost_user_id" value="">
                
                <p class="text-xs font-bold text-slate-300 mb-4">Client : <span id="boost_user_name" class="text-amber-400 font-black"></span></p>

                <div class="mb-6">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Choisir le plan *</label>
                    <select name="plan_id" class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl py-3 px-4 outline-none focus:border-amber-500 font-bold">
                        <option value="3">Starter Pro</option>
                        <option value="2">Premium VIP</option>
                    </select>
                </div>
                <button type="submit" class="w-full bg-amber-600 hover:bg-amber-500 text-slate-950 font-black py-3 rounded-xl transition shadow-lg shadow-amber-500/20 uppercase text-xs">
                    Activer le Plan
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openCreditModal(id, name) {
    document.getElementById('credit_user_id').value = id;
    document.getElementById('credit_user_name').innerText = name;
    document.getElementById('creditModal').classList.remove('hidden');
}
function openBoostModal(id, name) {
    document.getElementById('boost_user_id').value = id;
    document.getElementById('boost_user_name').innerText = name;
    document.getElementById('boostModal').classList.remove('hidden');
}
</script>