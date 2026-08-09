<?php
require_once 'config/config.php';
require_once 'core/Session.php';

use App\Core\Session;

if (!Session::isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$current_user_id = Session::getUserId();
$currency = $_SESSION['user_currency'] ?? 'FCFA';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Commandes & Transaction - MAN GO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .order-card { background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 16px; margin-bottom: 12px; }
        .status-badge { font-size: 0.8rem; padding: 5px 10px; border-radius: 20px; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold m-0"><i class="fa-solid fa-receipt text-warning me-2"></i>Mes Commandes & Offres</h4>
        <a href="index.php" class="btn btn-sm btn-outline-secondary">Accueil</a>
    </div>

    <div id="ordersList">
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Chargement de votre historique...</p>
        </div>
    </div>
</div>

<script>
    const CURRENT_USER_ID = <?=$current_user_id?>;
    const CURRENCY = <?=json_encode($currency)?>;

    document.addEventListener('DOMContentLoaded', loadOrders);

    async function loadOrders() {
        try {
            const res = await fetch('api/orders.php?action=getUserOrders');
            const orders = await res.json();

            const container = document.getElementById('ordersList');
            if (!orders || orders.length === 0) {
                container.innerHTML = '<div class="alert alert-info">Aucune commande enregistre pour le moment.</div>';
                return;
            }

            let html = '';
            orders.forEach(o => {
                const isBuyer = o.buyer_id == CURRENT_USER_ID;
                const roleTag = isBuyer ? '<span class="badge bg-primary">Achat</span>' : '<span class="badge bg-success">Vente</span>';
                
                let statusBadge = '<span class="badge bg-warning text-dark status-badge">En attente</span>';
                if (o.status === 'completed') statusBadge = '<span class="badge bg-success status-badge">Livr / Pay</span>';
                if (o.status === 'cancelled') statusBadge = '<span class="badge bg-danger status-badge">Annul</span>';

                html += `
                    <div class="order-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>Code : ${o.order_code}</strong> ${roleTag}
                            </div>
                            <div>${statusBadge}</div>
                        </div>
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h6 class="m-0 fw-semibold">${escapeHtml(o.product_title || 'Article sans titre')}</h6>
                                <small class="text-muted">
                                    ${isBuyer ? 'Vendeur: ' + escapeHtml(o.vendor_name) : 'Acheteur: ' + escapeHtml(o.buyer_name)}
                                </small>
                            </div>
                            <div class="col-4 text-end">
                                <span class="fw-bold text-dark">${Number(o.amount).toLocaleString()} ${CURRENCY}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        } catch (e) {
            console.error(e);
            document.getElementById('ordersList').innerHTML = '<div class="alert alert-danger">Erreur de chargement.</div>';
        }
    }

    function escapeHtml(str) {
        if(!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }
</script>
</body>
</html>