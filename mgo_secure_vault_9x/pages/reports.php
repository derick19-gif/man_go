<?php
// pages/reports.php

// Récupération des signalements
$reportsList = [];
try {
    $stmtReports = $db->query("
        SELECT r.*, 
               u1.firstname as reporter_first, u1.lastname as reporter_last,
               u2.firstname as reported_first, u2.lastname as reported_last
        FROM reports r
        LEFT JOIN users u1 ON r.reporter_id = u1.id
        LEFT JOIN users u2 ON r.reported_id = u2.id
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC LIMIT 50
    ");
    if ($stmtReports) {
        $reportsList = $stmtReports->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// Inspecteur de Messages (Recherche entre 2 utilisateurs)
$chatHistory = [];
$userA = filter_input(INPUT_GET, 'user_a', FILTER_VALIDATE_INT);
$userB = filter_input(INPUT_GET, 'user_b', FILTER_VALIDATE_INT);

if ($userA && $userB) {
    try {
        $stmtChat = $db->prepare("
            SELECT sender_id, receiver_id, message, created_at 
            FROM chat_messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC LIMIT 100
        ");
        $stmtChat->execute([$userA, $userB, $userB, $userA]);
        $chatHistory = $stmtChat->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>

<div class="mb-8">
    <h2 class="text-3xl font-black text-white">Centre Anti-Fraude</h2>
    <p class="text-slate-400 text-sm mt-1">Gérez les signalements et inspectez les conversations en cas de litige.</p>
</div>

<!-- L'INSPECTEUR DE MESSAGES -->
<div class="bg-slate-900 border border-indigo-500/30 rounded-3xl p-6 sm:p-8 mb-8 shadow-2xl relative overflow-hidden">
    <div class="absolute -right-10 -top-10 opacity-5"><i class="fa-solid fa-radar text-9xl text-indigo-500"></i></div>
    <div class="relative z-10">
        <h3 class="font-black text-xl text-white flex items-center mb-4">
            <i class="fa-solid fa-user-secret text-indigo-500 mr-3"></i> Le Radar (Inspecteur de conversations)
        </h3>
        <p class="text-xs text-slate-400 mb-6">Entrez l'ID de deux utilisateurs pour lire l'intégralité de leurs échanges sur MAN GO.</p>
        
        <form method="GET" class="flex flex-col sm:flex-row gap-4 items-end mb-6">
            <input type="hidden" name="page" value="reports">
            <div class="w-full sm:w-1/3">
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">ID Utilisateur A</label>
                <input type="number" name="user_a" value="<?= htmlspecialchars($userA ?? '') ?>" placeholder="Ex: 45" required class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl py-3 px-4 outline-none focus:border-indigo-500">
            </div>
            <div class="w-full sm:w-1/3">
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">ID Utilisateur B</label>
                <input type="number" name="user_b" value="<?= htmlspecialchars($userB ?? '') ?>" placeholder="Ex: 89" required class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl py-3 px-4 outline-none focus:border-indigo-500">
            </div>
            <div class="w-full sm:w-1/3">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-black py-3 rounded-xl transition shadow-lg shadow-indigo-500/20">Scanner</button>
            </div>
        </form>

        <?php if ($userA && $userB): ?>
            <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 max-h-96 overflow-y-auto">
                <?php if (empty($chatHistory)): ?>
                    <p class="text-center text-slate-500 py-8">Aucun échange trouvé entre ces deux utilisateurs.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($chatHistory as $msg): 
                            $isUserA = ($msg['sender_id'] == $userA);
                        ?>
                            <div class="flex <?= $isUserA ? 'justify-start' : 'justify-end' ?>">
                                <div class="max-w-[75%] p-3 rounded-xl <?= $isUserA ? 'bg-slate-800 text-slate-200' : 'bg-indigo-900/50 text-indigo-100 border border-indigo-500/30' ?>">
                                    <div class="text-[9px] font-black mb-1 opacity-50 uppercase">Utilisateur #<?= $msg['sender_id'] ?></div>
                                    <p class="text-sm"><?= htmlspecialchars($msg['message']) ?></p>
                                    <div class="text-[9px] text-right mt-2 opacity-50"><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- TABLEAU DES SIGNALEMENTS -->
<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
    <div class="p-6 border-b border-slate-800 flex justify-between items-center">
        <h3 class="font-black text-lg text-white"><i class="fa-solid fa-flag text-rose-500 mr-2"></i> Signalements en attente</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-slate-950/50 text-slate-400 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="py-4 px-6">Plaignant</th>
                    <th class="py-4 px-6">Accusé / Cible</th>
                    <th class="py-4 px-6">Motif</th>
                    <th class="py-4 px-6 text-center">Action Admin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php if (empty($reportsList)): ?>
                    <tr><td colspan="4" class="text-center py-10 text-slate-500">Aucun signalement. La communauté est calme !</td></tr>
                <?php else: ?>
                    <?php foreach ($reportsList as $rep): ?>
                        <tr class="hover:bg-slate-800/50">
                            <td class="py-4 px-6">
                                <p class="font-bold text-white"><?= htmlspecialchars($rep['reporter_first']) ?></p>
                                <p class="text-xs text-slate-500">ID: <?= $rep['reporter_id'] ?></p>
                            </td>
                            <td class="py-4 px-6">
                                <p class="font-bold text-rose-400"><?= htmlspecialchars($rep['reported_first']) ?></p>
                                <p class="text-xs text-rose-500/70">Type: <?= htmlspecialchars($rep['item_type'] ?? 'user') ?> (ID: <?= $rep['reported_id'] ?>)</p>
                            </td>
                            <td class="py-4 px-6">
                                <p class="text-slate-300 font-medium whitespace-normal max-w-xs line-clamp-2"><?= htmlspecialchars($rep['reason']) ?></p>
                                <p class="text-[10px] text-slate-500 mt-1"><?= date('d/m/Y H:i', strtotime($rep['created_at'])) ?></p>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <form method="POST" class="inline mb-2 block" onsubmit="return confirm('Suspendre le compte de l\'accusé ?');">
                                    <input type="hidden" name="action" value="toggle_user_status">
                                    <input type="hidden" name="target_user_id" value="<?= $rep['reported_id'] ?>">
                                    <input type="hidden" name="new_status" value="0">
                                    <button type="submit" class="bg-slate-800 hover:bg-rose-600 text-rose-500 hover:text-white border border-slate-700 px-3 py-1 rounded text-xs font-bold transition w-full">Bannir l'accusé</button>
                                </form>
                                <form method="POST" class="inline block" onsubmit="return confirm('Classer ce signalement sans suite ?');">
                                    <input type="hidden" name="action" value="resolve_report">
                                    <input type="hidden" name="report_id" value="<?= $rep['id'] ?>">
                                    <button type="submit" class="text-slate-500 hover:text-white underline text-xs font-bold w-full text-center">Classer l'affaire</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>