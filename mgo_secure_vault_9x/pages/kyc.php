<?php
// pages/kyc.php
$stmtKyc = $db->query("SELECT k.*, u.email, u.phone FROM user_kyc k JOIN users u ON k.user_id = u.id WHERE k.status = 'pending' ORDER BY k.created_at ASC");
$pendingKycList = $stmtKyc->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="mb-8"><h2 class="text-3xl font-black text-white">Validation KYC</h2><p class="text-slate-400 text-sm mt-1">Examinez les documents d'identité pour certifier les vendeurs.</p></div>
<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
    <div class="p-6 border-b border-slate-800 flex justify-between items-center"><h3 class="font-black text-lg text-white">Dossiers en attente</h3><span class="bg-rose-500 text-white font-black px-3 py-1 rounded-full text-xs shadow-lg"><?= count($pendingKycList) ?> dossier(s)</span></div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-slate-950/50 text-slate-400 uppercase tracking-widest text-[10px]"><tr><th class="py-4 px-6">Informations</th><th class="py-4 px-6">Documents</th><th class="py-4 px-6 text-center">Décision</th></tr></thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php if (empty($pendingKycList)): ?><tr><td colspan="3" class="text-center py-10">Tous les dossiers sont traités !</td></tr><?php else: ?>
                    <?php foreach ($pendingKycList as $kyc): ?>
                        <tr class="hover:bg-slate-800/50"><td class="py-4 px-6"><p class="font-black text-white"><?= htmlspecialchars($kyc['primary_manager_name']) ?></p></td><td class="py-4 px-6"><a href="<?= $baseUrl ?>/<?= htmlspecialchars($kyc['id_document_path']) ?>" target="_blank" class="text-xs text-blue-400">Ouvrir</a></td><td class="py-4 px-6 text-center"><form method="POST" class="inline"><input type="hidden" name="action" value="approve_kyc"><input type="hidden" name="kyc_id" value="<?= $kyc['id'] ?>"><input type="hidden" name="user_id" value="<?= $kyc['user_id'] ?>"><button class="bg-emerald-500/20 text-emerald-400 px-3 py-1 rounded mr-2"><i class="fa-solid fa-check"></i></button></form></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>