<?php
// =========================================================================
// pages/listings.php - Modération du Catalogue d'annonces
// =========================================================================

$localSuccess = '';
$localError = '';

// --- TRAITEMENT DES ACTIONS MANUELLES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $localAction = $_POST['action'] ?? '';
    
    // SUSPENDRE / RÉACTIVER UNE ANNONCE
    if ($localAction === 'toggle_listing_status') {
        $listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
        $newStatus = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);
        
        if ($listingId && in_array($newStatus, ['active', 'suspended'])) {
            try {
                $db->prepare("UPDATE listings SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $listingId]);
                $localSuccess = $newStatus === 'active' ? "L'annonce a été réactivée et est visible." : "L'annonce a été suspendue. Elle n'est plus visible sur le marché.";
            } catch(Exception $e) {
                $localError = "Erreur lors de la modification : " . $e->getMessage();
            }
        }
    }
    
    // SUPPRIMER DÉFINITIVEMENT UNE ANNONCE
    if ($localAction === 'delete_listing') {
        $listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
        if ($listingId) {
            try {
                $db->prepare("DELETE FROM listings WHERE id = ?")->execute([$listingId]);
                $localSuccess = "L'annonce a été définitivement supprimée du système.";
            } catch(Exception $e) {
                $localError = "Erreur lors de la suppression : " . $e->getMessage();
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
    $whereSql = "WHERE l.title LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR l.id = ?";
    $params = ["%$search%", "%$search%", "%$search%", $search];
}

// Compter le total
$stmtCount = $db->prepare("SELECT COUNT(l.id) FROM listings l JOIN users u ON l.user_id = u.id $whereSql");
$stmtCount->execute($params);
$totalListingsFound = $stmtCount->fetchColumn();
$totalPages = ceil($totalListingsFound / $pageLimit);

// Récupérer les annonces
$stmtListings = $db->prepare("
    SELECT l.id, l.title, l.price, l.status, l.created_at, l.image_path, 
           u.id as vendor_id, u.firstname, u.lastname, u.is_premium 
    FROM listings l 
    JOIN users u ON l.user_id = u.id 
    $whereSql 
    ORDER BY l.created_at DESC 
    LIMIT $pageLimit OFFSET $offset
");
$stmtListings->execute($params);
$listingsData = $stmtListings->fetchAll(PDO::FETCH_ASSOC);
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
        <h2 class="text-3xl font-black text-white">Modération du Catalogue</h2>
        <p class="text-slate-400 text-sm mt-1">Surveillez, suspendez ou supprimez les annonces publiées sur le marché.</p>
    </div>
    
    <form method="GET" class="flex w-full md:w-auto shadow-lg">
        <input type="hidden" name="page" value="listings">
        <div class="relative flex-1 md:w-64">
            <i class="fa-solid fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-500"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Titre, ID, Vendeur..." class="w-full bg-slate-900 border border-slate-700 text-white rounded-l-xl py-3 pl-12 pr-4 outline-none focus:border-amber-500 text-sm font-bold">
        </div>
        <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-slate-950 px-6 py-3 rounded-r-xl text-sm font-black transition">Chercher</button>
    </form>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl pb-8">
    <div class="p-6 border-b border-slate-800 flex justify-between items-center">
        <h3 class="font-black text-lg text-white">Catalogue (<?= $totalListingsFound ?> annonces)</h3>
    </div>
    
    <div class="overflow-x-auto min-h-[400px]">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-slate-950/50 text-slate-400 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="py-4 px-6">Produit / Service</th>
                    <th class="py-4 px-6">Vendeur</th>
                    <th class="py-4 px-6">Prix</th>
                    <th class="py-4 px-6">Statut</th>
                    <th class="py-4 px-6 text-right">Actions Admin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php if(empty($listingsData)): ?>
                    <tr><td colspan="5" class="text-center py-10 text-slate-500">Aucune annonce trouvée.</td></tr>
                <?php else: ?>
                    <?php foreach ($listingsData as $item): ?>
                        <tr class="hover:bg-slate-800/50 <?= ($item['status'] === 'suspended') ? 'opacity-50 grayscale' : '' ?>">
                            <td class="py-4 px-6 flex items-center">
                                <img src="<?= htmlspecialchars(!empty($item['image_path']) ? $baseUrl.'/'.$item['image_path'] : $baseUrl.'/assets/images/placeholder.jpg') ?>" class="w-12 h-12 rounded-lg object-cover mr-4 border border-slate-700">
                                <div>
                                    <p class="font-black text-white truncate max-w-[200px]" title="<?= htmlspecialchars($item['title']) ?>">
                                        <a href="<?= $baseUrl ?>/listing-detail.php?id=<?= $item['id'] ?>" target="_blank" class="hover:text-amber-500 transition">
                                            <?= htmlspecialchars($item['title']) ?> <i class="fa-solid fa-arrow-up-right-from-square text-[10px] ml-1 opacity-50"></i>
                                        </a>
                                    </p>
                                    <p class="text-[10px] text-slate-500 mt-1">Publié le <?= date('d/m/Y', strtotime($item['created_at'])) ?> (ID: <?= $item['id'] ?>)</p>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <p class="font-bold text-slate-300"><?= htmlspecialchars($item['firstname'] . ' ' . $item['lastname']) ?></p>
                                <?php if ($item['is_premium'] == 1): ?>
                                    <span class="text-[9px] text-amber-500 border border-amber-500/30 px-2 py-0.5 rounded uppercase font-black"><i class="fa-solid fa-crown mr-1"></i> Pro</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 font-black text-emerald-400">
                                <?= number_format($item['price'], 0, ',', ' ') ?> FCFA
                            </td>
                            <td class="py-4 px-6">
                                <?php if ($item['status'] === 'active'): ?>
                                    <span class="bg-emerald-500/10 text-emerald-500 px-3 py-1 rounded-full text-[10px] font-black uppercase border border-emerald-500/20">Actif</span>
                                <?php elseif ($item['status'] === 'suspended'): ?>
                                    <span class="bg-rose-500/10 text-rose-500 px-3 py-1 rounded-full text-[10px] font-black uppercase border border-rose-500/20">Suspendu</span>
                                <?php else: ?>
                                    <span class="bg-slate-800 text-slate-400 px-3 py-1 rounded-full text-[10px] font-black uppercase border border-slate-700"><?= htmlspecialchars($item['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    
                                    <!-- Suspendre / Réactiver -->
                                    <form method="POST" onsubmit="return confirm('Voulez-vous vraiment changer le statut de cette annonce ?');" class="inline">
                                        <input type="hidden" name="action" value="toggle_listing_status">
                                        <input type="hidden" name="listing_id" value="<?= $item['id'] ?>">
                                        <?php if ($item['status'] === 'active'): ?>
                                            <input type="hidden" name="new_status" value="suspended">
                                            <button type="submit" class="bg-rose-500/10 text-rose-500 hover:bg-rose-500 hover:text-white px-3 py-2 rounded border border-rose-500/30 text-xs font-bold transition" title="Suspendre l'annonce">
                                                <i class="fa-solid fa-eye-slash"></i> Cacher
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="active">
                                            <button type="submit" class="bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500 hover:text-white px-3 py-2 rounded border border-emerald-500/30 text-xs font-bold transition" title="Réactiver l'annonce">
                                                <i class="fa-solid fa-eye"></i> Afficher
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- Supprimer -->
                                    <form method="POST" onsubmit="return confirm('ATTENTION : Voulez-vous DÉFINITIVEMENT supprimer cette annonce de la base de données ?');" class="inline">
                                        <input type="hidden" name="action" value="delete_listing">
                                        <input type="hidden" name="listing_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="text-slate-500 hover:text-rose-500 px-2 py-2 text-sm transition" title="Supprimer définitivement">
                                            <i class="fa-solid fa-trash"></i>
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-slate-800 bg-slate-900/50 flex justify-between items-center mt-4 mx-4 rounded-xl">
            <span class="text-xs font-bold text-slate-500">Page <?= $pageNum ?> sur <?= $totalPages ?></span>
            <div class="flex gap-2">
                <?php if ($pageNum > 1): ?>
                    <a href="?page=listings&search=<?= urlencode($search) ?>&p=<?= $pageNum - 1 ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition">Précédent</a>
                <?php endif; ?>
                <?php if ($pageNum < $totalPages): ?>
                    <a href="?page=listings&search=<?= urlencode($search) ?>&p=<?= $pageNum + 1 ?>" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-slate-950 text-xs font-black rounded-lg transition">Suivant</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>