<?php
require_once 'config/config.php';
require_once 'core/Session.php';

use App\Core\Session;

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_user_id = Session::isAuthenticated() ? Session::getUserId() : 0;
$currency = $_SESSION['user_currency'] ?? 'FCFA';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail de l'offre - MAN GO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --mango-orange: #ff6600;
            --mango-dark: #1e293b;
        }
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .product-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 24px; }
        .main-img { width: 100%; height: 380px; object-fit: cover; border-radius: 8px; }
        .price-tag { font-size: 1.8rem; font-weight: bold; color: var(--mango-orange); }
        .vendor-box { background: #f1f5f9; border-radius: 8px; padding: 16px; margin-top: 20px; }
        .badge-vip { background: linear-gradient(45deg, #f59e0b, #d97706); color: #fff; font-size: 0.75rem; }
    </style>
</head>
<body>

<div class="container py-5">
    <a href="index.php" class="btn btn-outline-secondary mb-4 btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Retour aux offres</a>
    
    <div id="productContainer">
        <div class="text-center py-5">
            <div class="spinner-border text-warning" role="status"></div>
            <p class="mt-2 text-muted">Chargement de l'annonce...</p>
        </div>
    </div>
</div>

<!-- MODAL PROPOSITION DE PRIX -->
<div class="modal fade" id="offerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-handshake text-warning me-2"></i>Faire une offre de prix</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="offerForm" onsubmit="submitOffer(event)">
                    <input type="hidden" id="offerVendorId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Prix actuel de l'annonce</label>
                        <input type="text" id="originalPriceDisplay" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Votre proposition (<?=$currency?>)</label>
                        <input type="number" id="offeredPrice" class="form-control" placeholder="Entrez votre prix" required min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message d'accompagnement (Optionnel)</label>
                        <textarea id="offerMessage" class="form-control" rows="2" placeholder="Bonjour, je propose ce prix pour achat immédiat..."></textarea>
                    </div>
                    <button type="submit" class="btn w-100 fw-bold text-white" style="background-color: var(--mango-orange);">
                        Envoyer l'offre dans le Chat
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const PRODUCT_ID = <?=$product_id?>;
    const CURRENT_USER_ID = <?=$current_user_id?>;
    const CURRENCY = <?=json_encode($currency)?>;
    let productData = null;

    document.addEventListener('DOMContentLoaded', () => {
        if (!PRODUCT_ID) {
            document.getElementById('productContainer').innerHTML = '<div class="alert alert-danger">Annonce introuvable.</div>';
            return;
        }
        loadProduct();
    });

    async function loadProduct() {
        try {
            const res = await fetch(`api/orders.php?action=getProductDetail&id=${PRODUCT_ID}`);
            const data = await res.json();

            if (data.status === 'error' || !data.product) {
                document.getElementById('productContainer').innerHTML = `<div class="alert alert-warning">${data.message || 'Produit indisponible'}</div>`;
                return;
            }

            productData = data.product;
            renderProduct(productData);
        } catch (e) {
            console.error(e);
            document.getElementById('productContainer').innerHTML = '<div class="alert alert-danger">Erreur lors de la récupération des informations.</div>';
        }
    }

    function renderProduct(p) {
        const isOwner = p.vendor_id == CURRENT_USER_ID;
        const mainImage = p.image || 'https://via.placeholder.com/600x400?text=MAN+GO';
        const vipBadge = p.is_vip ? '<span class="badge badge-vip ms-2"><i class="fa-solid fa-crown me-1"></i>Vendeur VIP</span>' : '';

        const html = `
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <img src="${mainImage}" class="main-img" alt="${escapeHtml(p.title)}">
                </div>
                <div class="col-12 col-md-6">
                    <div class="product-card">
                        <span class="badge bg-secondary mb-2">${escapeHtml(p.category_name || 'Général')}</span>
                        <h2 class="fw-bold text-dark">${escapeHtml(p.title)}</h2>
                        <div class="price-tag my-2">${Number(p.price).toLocaleString()} ${CURRENCY}</div>
                        
                        <p class="text-muted mt-3">${escapeHtml(p.description)}</p>
                        
                        <div class="vendor-box">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold mb-0">${escapeHtml(p.vendor_name || 'Vendeur MAN GO')} ${vipBadge}</h6>
                                    <small class="text-muted">Membre certifié</small>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-grid gap-2">
                            ${!isOwner ? `
                                <a href="chat.php?user_id=${p.vendor_id}&product_id=${p.id}" class="btn btn-outline-dark fw-semibold">
                                    <i class="fa-solid fa-comments me-2"></i> Discuter avec le vendeur
                                </a>
                                <button onclick="openOfferModal()" class="btn text-white fw-bold" style="background-color: var(--mango-orange);">
                                    <i class="fa-solid fa-handshake me-2"></i> Faire une offre de prix
                                </button>
                                <button onclick="directOrder()" class="btn btn-success fw-bold">
                                    <i class="fa-solid fa-cart-shopping me-2"></i> Commander directement
                                </button>
                            ` : `
                                <div class="alert alert-info text-center m-0">Vous êtes l'auteur de cette annonce.</div>
                            `}
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('productContainer').innerHTML = html;
    }

    function openOfferModal() {
        if (!CURRENT_USER_ID) {
            window.location.href = 'login.php';
            return;
        }
        document.getElementById('offerVendorId').value = productData.vendor_id;
        document.getElementById('originalPriceDisplay').value = `${Number(productData.price).toLocaleString()} ${CURRENCY}`;
        const modal = new bootstrap.Modal(document.getElementById('offerModal'));
        modal.show();
    }

    async function submitOffer(e) {
        e.preventDefault();
        const offeredPrice = document.getElementById('offeredPrice').value;
        const offerMessage = document.getElementById('offerMessage').value;

        const bodyData = new URLSearchParams({
            action: 'proposeOffer',
            product_id: PRODUCT_ID,
            receiver_id: productData.vendor_id,
            price: offeredPrice,
            message: offerMessage
        });

        try {
            const res = await fetch('api/orders.php', { method: 'POST', body: bodyData });
            const result = await res.json();
            if (result.status === 'success') {
                window.location.href = `chat.php?user_id=${productData.vendor_id}&product_id=${PRODUCT_ID}`;
            } else {
                alert(result.message || 'Erreur lors de la soumission de l\'offre.');
            }
        } catch (err) {
            console.error(err);
            alert('Erreur réseau.');
        }
    }

    async function directOrder() {
        if (!CURRENT_USER_ID) {
            window.location.href = 'login.php';
            return;
        }
        if (!confirm(`Confirmer la commande au prix de ${Number(productData.price).toLocaleString()} ${CURRENCY} ?`)) return;

        const bodyData = new URLSearchParams({
            action: 'createOrder',
            product_id: PRODUCT_ID,
            vendor_id: productData.vendor_id,
            amount: productData.price
        });

        try {
            const res = await fetch('api/orders.php', { method: 'POST', body: bodyData });
            const result = await res.json();
            if (result.status === 'success') {
                alert('Commande créée avec succès ! Code de commande : ' + result.order_code);
                window.location.href = 'orders.php';
            } else {
                alert(result.message || 'Impossible de valider la commande.');
            }
        } catch (err) {
            console.error(err);
            alert('Erreur réseau lors de la commande.');
        }
    }

    function escapeHtml(str) {
        if(!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
</script>
</body>
</html>