<?php
// =========================================================================
// pages/deposits.php - Validation manuelle des dépôts Crypto / TXID
// =========================================================================

$localSuccess = '';
$localError = '';

// --- TRAITEMENT DES VALIDATIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $localAction = $_POST['action'] ?? '';
    
    if ($localAction === 'approve_crypto' || $localAction === 'reject_crypto') {
        $txId = filter_input(INPUT_POST, 'tx_id', FILTER_VALIDATE_INT);
        
        try {
            $db->beginTransaction();
            
            $stmtTx = $db->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending' AND payment_method LIKE 'crypto_%' FOR UPDATE");
            $stmtTx->execute([$txId]);
            $tx = $stmtTx->fetch(PDO::FETCH_ASSOC);
            
            if ($tx) {
                if ($localAction === 'approve_crypto') {
                    // On passe la transaction en 'completed'
                    $db->prepare("UPDATE transactions SET status = 'completed' WHERE id = ?")->execute([$txId]);
                    
                    if ($tx['payment_method'] === 'crypto_deposit') {
                        $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['user_id']]);
                        $localSuccess = "Le dépôt a été validé ! Le portefeuille du client a été crédité de " . number_format($tx['amount'], 0, ',', ' ') . " FCFA.";
                        
                    } elseif ($tx['payment_method'] === 'crypto_subscription_pending') {
                        $db->prepare("UPDATE users SET is_premium = 1, role_id = 4 WHERE id = ?")->execute([$tx['user_id']]);
                        $localSuccess = "Le paiement a été validé ! Le compte du client est maintenant Premium VIP.";
                    } else {
                        $localSuccess = "La transaction a été validée avec succès.";
                    }
                    
                } elseif ($localAction === 'reject_crypto') {
                    // On annule la transaction
                    $db->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ?")->execute([$txId]);
                    $localSuccess = "La transaction a été rejetée et annulée.";
                }
            } else {
                $localError = "Transaction introuvable ou déjà traitée.";
            }
            
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $localError = "Erreur lors du traitement : " . $e->getMessage();
        }
    }
}

// --- RÉCUPÉRATION DES DEMANDES EN ATTENTE ---
$stmtPending = $db->query("
    SELECT t.*, u.firstname, u.lastname, u.email, u.phone 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.status = 'pending' AND t.payment_method LIKE 'crypto_%'
    ORDER BY t.created_at ASC
");
$pendingDeposits = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

// --- RÉCUPÉRATION DE L'HISTORIQUE RÉCENT ---
$stmtHistory = $db->query("
    SELECT t.*, u.firstname, u.lastname 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.payment_method LIKE 'crypto_%' AND t.status != 'pending'
    ORDER BY t.created_at DESC LIMIT 20
");
$historyDeposits = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
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

<div class="mb-8">
    <h2 class="text-3xl font-black text-white">Validations Crypto (USDT)</h2>
    <p class="text-slate-400 text-sm mt-1">Vérifiez les TXID sur la blockchain avant d'approuver les dépôts de vos clients.</p>
</div>

<!-- SECTION : EN ATTENTE DE VALIDATION -->
<div class="bg-slate-900 border border-amber-500/30 rounded-3xl overflow-hidden shadow-2xl mb-10 relative">
    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-amber-400 to-orange-500"></div>
    <div class="p-6 border-b border-slate-800 flex justify-between items-center bg-amber-500/5">
        <h3 class="font-black text-lg text-amber-500 flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left"></i> Dépôts en attente (<?= count($pendingDeposits) ?>)
        </h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-slate-950/50 text-slate-400 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="py-4 px-6">Date & Client</th>
                    <th class="py-4 px-6">Type</th>
                    <th class="py-4 px-6 text-right">Montant (FCFA)</th>
                    <th class="py-4 px-6">Hash (TXID) à vérifier</th>
                    <th class="py-4 px-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php if(empty($pendingDeposits)): ?>
                    <tr><td colspan="5" class="text-center py-12 text-slate-500 font-bold">Aucune transaction crypto en attente.</td></tr>
                <?php else: ?>
                    <?php foreach ($pendingDeposits as $dep): ?>
                        <tr class="hover:bg-slate-800/50">
                            <td class="py-4 px-6">
                                <p class="font-black text-white"><?= htmlspecialchars($dep['firstname'] . ' ' . $dep['lastname']) ?></p>
                                <p class="text-[10px] text-slate-500 mt-1"><?= date('d/m/Y H:i', strtotime($dep['created_at'])) ?></p>
                            </td>
                            <td class="py-4 px-6">
                                <span class="bg-slate-800 text-slate-300 px-2 py-1 rounded text-[10px] font-bold uppercase">
                                    <?= str_contains($dep['payment_method'], 'deposit') ? 'Recharge Solde' : 'Abonnement' ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right font-black text-amber-400 text-lg">
                                <?= number_format($dep['amount'], 0, ',', ' ') ?>
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-2">
                                    <input type="text" readonly value="<?= htmlspecialchars(str_replace(['Dépôt USDT (TXID: ', 'Abonnement (TXID: ', ')'], '', $dep['transaction_ref'])) ?>" class="bg-slate-950 border border-slate-700 text-slate-300 px-3 py-2 rounded text-xs font-mono w-48 focus:outline-none">
                                    <a href="https://tronscan.org/#/transaction/<?= htmlspecialchars(str_replace(['Dépôt USDT (TXID: ', 'Abonnement (TXID: ', ')'], '', $dep['transaction_ref'])) ?>" target="_blank" class="bg-blue-500/10 text-blue-400 hover:bg-blue-500 hover:text-white px-3 py-2 rounded transition text-xs font-bold" title="Vérifier sur TronScan">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </a>
                                </div>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex justify-center gap-2">
                                    <form method="POST" onsubmit="return confirm('Avez-vous bien reçu cet argent sur votre Trust Wallet / Binance ?');">
                                        <input type="hidden" name="action" value="approve_crypto">
                                        <input type="hidden" name="tx_id" value="<?= $dep['id'] ?>">
                                        <button type="submit" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 px-4 py-2 rounded font-black text-xs transition shadow-lg shadow-emerald-500/20">
                                            Valider
                                        </button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Rejeter ce TXID ? (Faux hash ou paiement non reçu)');">
                                        <input type="hidden" name="action" value="reject_crypto">
                                        <input type="hidden" name="tx_id" value="<?= $dep['id'] ?>">
                                        <button type="submit" class="bg-rose-500/10 text-rose-500 hover:bg-rose-500 hover:text-white px-3 py-2 rounded font-bold text-xs transition border border-rose-500/30">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SECTION : HISTORIQUE RÉCENT -->
<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
    <div class="p-6 border-b border-slate-800">
        <h3 class="font-black text-lg text-white">Historique des vérifications</h3>
    </div>
    <div class="overflow-x-auto max-h-96">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-slate-950/50 text-slate-500 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="py-4 px-6">Client</th>
                    <th class="py-4 px-6">Montant</th>
                    <th class="py-4 px-6">TXID vérifié</th>
                    <th class="py-4 px-6 text-right">Statut Final</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php foreach ($historyDeposits as $hist): ?>
                    <tr class="hover:bg-slate-800/50">
                        <td class="py-4 px-6 text-slate-300 font-bold"><?= htmlspecialchars($hist['firstname'] . ' ' . $hist['lastname']) ?></td>
                        <td class="py-4 px-6 font-mono text-slate-400"><?= number_format($hist['amount'], 0, ',', ' ') ?> FCFA</td>
                        <td class="py-4 px-6 text-[10px] text-slate-500 truncate max-w-[150px]"><?= htmlspecialchars($hist['transaction_ref']) ?></td>
                        <td class="py-4 px-6 text-right">
                            <?php if ($hist['status'] === 'completed'): ?>
                                <span class="text-emerald-400 text-[10px] font-black uppercase"><i class="fa-solid fa-check mr-1"></i> Approuvé</span>
                            <?php else: ?>
                                <span class="text-rose-400 text-[10px] font-black uppercase"><i class="fa-solid fa-xmark mr-1"></i> Rejeté</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>