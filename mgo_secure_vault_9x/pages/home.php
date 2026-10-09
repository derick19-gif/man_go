<?php
// pages/home.php
$totalUsers = $db->query("SELECT COUNT(id) FROM users")->fetchColumn();
$totalSalesVol = $db->query("SELECT SUM(amount) FROM orders WHERE status IN ('paid', 'shipped', 'delivered')")->fetchColumn() ?: 0;
$platformRevenue = $totalSalesVol * 0.03;
$stmtPending = $db->query("SELECT t.*, u.firstname, u.lastname, u.phone FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.payment_method = 'withdrawal_request' AND t.status = 'pending' ORDER BY t.created_at ASC");
$pendingWithdrawals = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="mb-8"><h2 class="text-3xl font-black text-white">Vue d'ensemble</h2><p class="text-slate-400 text-sm mt-1">Activité financière de MAN GO.</p></div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
    <div class="bg-slate-900 border border-amber-500/30 rounded-3xl p-6"><p class="text-xs font-black text-amber-500 uppercase tracking-widest mb-1">Revenus MAN GO (3%)</p><h2 class="text-3xl font-black text-white"><?= number_format($platformRevenue, 0, ',', ' ') ?> <span class="text-sm text-amber-500">FCFA</span></h2></div>
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6"><p class="text-xs font-black text-blue-400 uppercase tracking-widest mb-1">Volume Marché</p><h2 class="text-3xl font-black text-white"><?= number_format($totalSalesVol, 0, ',', ' ') ?> <span class="text-sm text-blue-500">FCFA</span></h2></div>
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6"><p class="text-xs font-black text-emerald-400 uppercase tracking-widest mb-1">Membres</p><h2 class="text-3xl font-black text-white"><?= number_format($totalUsers, 0, ',', ' ') ?> <span class="text-sm text-emerald-500">Inscrits</span></h2></div>
</div>
<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
    <div class="p-6 border-b border-slate-800"><h3 class="font-black text-lg text-white"><i class="fa-solid fa-money-bill-transfer text-emerald-500 mr-2"></i> Retraits en attente</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-slate-950/50 text-slate-400 uppercase tracking-widest text-[10px]"><tr><th class="py-4 px-6">Date</th><th class="py-4 px-6">Vendeur</th><th class="py-4 px-6 text-right">Montant</th><th class="py-4 px-6 text-center">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php if (empty($pendingWithdrawals)): ?><tr><td colspan="4" class="text-center py-10 text-slate-500">Aucun retrait en attente.</td></tr><?php else: ?>
                    <?php foreach ($pendingWithdrawals as $req): $amountToPay = abs($req['amount']); ?>
                        <tr class="hover:bg-slate-800/50"><td class="py-4 px-6 text-slate-400"><?= date('d/m/Y H:i', strtotime($req['created_at'])) ?></td><td class="py-4 px-6 font-bold text-white"><?= htmlspecialchars($req['firstname'] . ' ' . $req['lastname']) ?></td><td class="py-4 px-6 text-right font-black text-emerald-400"><?= number_format($amountToPay, 0, ',', ' ') ?> FCFA</td><td class="py-4 px-6 text-center"><form method="POST" class="inline" onsubmit="return confirm('Fonds envoyés ?');"><input type="hidden" name="action" value="approve_withdraw"><input type="hidden" name="tx_id" value="<?= $req['id'] ?>"><button class="bg-emerald-500/20 text-emerald-400 px-3 py-1 rounded hover:bg-emerald-500 hover:text-white text-xs font-bold">Payer</button></form></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>