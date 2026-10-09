<?php
// =========================================================================
// PAGE CHECKOUT - Achat d'un produit
// =========================================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';

if (defined('SESSION_NAME')) session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$db = \App\Core\Database::connect();

// 1. Vérifier si connecté
if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: $baseUrl/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$listing_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$listing_id) die("Annonce invalide.");

// 2. Récupérer l'annonce
$stmt = $db->prepare("SELECT l.*, CONCAT(u.firstname, ' ', u.lastname) AS seller_name FROM listings l LEFT JOIN users u ON l.user_id = u.id WHERE l.id = ? AND l.status = 'active'");
$stmt->execute([$listing_id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) die("L'annonce n'est plus disponible.");
if ($listing['user_id'] == $buyer_id) die("Vous ne pouvez pas acheter votre propre article.");

$articlePrice = (float)$listing['price'];
$totalPrice = $articlePrice; // Les frais de livraison sont désormais à la charge du vendeur ou à négocier

// 3. Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';

    // Gestion du préfixe téléphonique
    if (!empty($phone) && !str_starts_with($phone, '+')) {
        $phone = '+228 ' . $phone; // Ajout de l'indicatif par défaut
    }

    if (empty($city) || empty($address) || empty($phone) || empty($paymentMethod)) {
        $error = "Veuillez remplir toutes vos informations de livraison et choisir un moyen de paiement.";
    } else {
        $orderNumber = 'ORD-' . date('ym') . '-' . strtoupper(substr(uniqid(), -6));

        try {
            $db->beginTransaction();
            $stmtOrder = $db->prepare("INSERT INTO orders (order_number, buyer_id, vendor_id, listing_id, amount, payment_method, shipping_city, shipping_address, shipping_phone, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmtOrder->execute([$orderNumber, $buyer_id, $listing['user_id'], $listing_id, $totalPrice, $paymentMethod, $city, $address, $phone]);
            $orderId = $db->lastInsertId();
            $db->commit();

            if ($paymentMethod === 'crypto') {
                header("Location: $baseUrl/process_payment.php?order_id=$orderId");
                exit();
            } else {
                // Autres paiements plus tard
                header("Location: $baseUrl/purchases.php?msg=pending");
                exit();
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erreur lors de la création de la commande : " . $e->getMessage();
        }
    }
}

$pageTitle = "Finaliser la commande - MAN GO";
require_once __DIR__ . '/themes/default/templates/layouts/header.php';
?>

<div class="bg-slate-50 min-h-screen pt-12 pb-24 font-sans text-slate-800">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
        
        <div class="text-center mb-10">
            <h1 class="text-3xl font-black text-slate-900 flex items-center justify-center gap-3"><i class="fa-solid fa-lock text-emerald-500"></i> Finaliser la commande</h1>
            <p class="text-slate-500 mt-2">Vérifiez vos informations avant de procéder au paiement sécurisé.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-600 px-6 py-4 rounded-xl mb-8 font-bold text-sm">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <div class="lg:col-span-7 space-y-8">
                <!-- LIVRAISON -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200">
                    <h2 class="text-xl font-black text-slate-900 mb-6 flex items-center"><i class="fa-solid fa-truck text-amber-500 mr-3"></i> Informations de Livraison</h2>
                    <div class="space-y-5">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Ville de livraison *</label>
                            <input type="text" name="city" required placeholder="Ex: Lomé, Paris, Abidjan..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-sm font-bold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Adresse complète / Quartier *</label>
                            <input type="text" name="address" required placeholder="Ex: Près de la pharmacie principale..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-sm font-bold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Téléphone (AVEC INDICATIF) *</label>
                            <input type="text" name="phone" required placeholder="Ex: +228 90 00 00 00" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-sm font-bold font-mono">
                        </div>
                    </div>
                </div>

                <!-- PAIEMENT -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200">
                    <h2 class="text-xl font-black text-slate-900 mb-6 flex items-center"><i class="fa-solid fa-credit-card text-emerald-500 mr-3"></i> Méthode de Paiement</h2>
                    <div class="space-y-4">
                        <label class="flex items-center p-4 border border-slate-200 rounded-2xl cursor-pointer hover:bg-slate-50 transition">
                            <input type="radio" name="payment_method" value="wallet" class="w-5 h-5 text-amber-500 border-slate-300 focus:ring-amber-500" disabled>
                            <div class="ml-4 flex-1">
                                <span class="block font-black text-slate-900">Portefeuille MAN GO</span>
                                <span class="block text-xs text-slate-500">Bientôt disponible</span>
                            </div>
                            <i class="fa-solid fa-wallet text-slate-300 text-xl"></i>
                        </label>
                        <label class="flex items-center p-4 border border-slate-200 rounded-2xl cursor-pointer hover:bg-slate-50 transition">
                            <input type="radio" name="payment_method" value="crypto" checked class="w-5 h-5 text-amber-500 border-slate-300 focus:ring-amber-500">
                            <div class="ml-4 flex-1">
                                <span class="block font-black text-slate-900">Crypto-monnaie (USDT)</span>
                                <span class="block text-xs text-emerald-600 font-bold">Recommandé • Sécurisé & Sans frontière</span>
                            </div>
                            <i class="fa-brands fa-bitcoin text-amber-500 text-2xl"></i>
                        </label>
                        <label class="flex items-center p-4 border border-slate-200 rounded-2xl cursor-pointer hover:bg-slate-50 transition">
                            <input type="radio" name="payment_method" value="cod" class="w-5 h-5 text-amber-500 border-slate-300 focus:ring-amber-500">
                            <div class="ml-4 flex-1">
                                <span class="block font-black text-slate-900">Paiement à la livraison</span>
                                <span class="block text-xs text-slate-500">Payez en espèces à la réception</span>
                            </div>
                            <i class="fa-solid fa-hand-holding-dollar text-slate-400 text-xl"></i>
                        </label>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-5">
                <div class="bg-white p-8 rounded-3xl shadow-xl border border-slate-100 sticky top-24">
                    <h3 class="text-xl font-black text-slate-900 mb-6">Récapitulatif</h3>
                    
                    <div class="flex items-start gap-4 pb-6 border-b border-slate-100 mb-6">
                        <img src="<?= htmlspecialchars(!empty($listing['image_path']) ? $baseUrl.'/'.$listing['image_path'] : $baseUrl.'/assets/images/placeholder.jpg') ?>" class="w-16 h-16 rounded-xl object-cover border border-slate-200">
                        <div>
                            <h4 class="font-bold text-slate-900 leading-tight mb-1 text-sm"><?= htmlspecialchars($listing['title']) ?></h4>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Vendeur : <?= htmlspecialchars($listing['seller_name']) ?></span>
                        </div>
                    </div>

                    <div class="space-y-3 text-sm font-bold text-slate-600 mb-6">
                        <div class="flex justify-between">
                            <span>Prix de l'article</span>
                            <span><?= number_format($articlePrice, 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Frais de livraison</span>
                            <span>À définir avec le vendeur</span>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-100 mb-8 flex justify-between items-end">
                        <span class="text-xs font-black uppercase tracking-widest text-slate-400">Total à payer</span>
                        <div class="text-right">
                            <span class="text-3xl font-black text-amber-500"><?= number_format($totalPrice, 0, ',', ' ') ?> <span class="text-lg">FCFA</span></span>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-4 rounded-xl shadow-lg transition-all text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                        Continuer vers le paiement <i class="fa-solid fa-arrow-right"></i>
                    </button>
                    
                    <p class="text-[10px] text-center text-slate-400 font-bold uppercase tracking-widest mt-4 flex items-center justify-center gap-1">
                        <i class="fa-solid fa-shield-halved text-emerald-500"></i> Vos données sont sécurisées
                    </p>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/themes/default/templates/layouts/footer.php'; ?>