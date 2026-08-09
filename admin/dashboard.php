<?php
// Vrification de scurit minimale
if (!Session::isAuthenticated()) {
    header('Location: ' . APP_URL . '/login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAN GO - Administration & Modration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-user-shield text-danger me-2"></i>Panneau d'Administration MAN GO</h2>
        <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-house me-1"></i>Retour au site</a>
    </div>

    <!-- Mtriques Globales -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Utilisateurs</h6>
                        <h3 id="stat-users" class="mb-0">--</h3>
                    </div>
                    <i class="fa-solid fa-users fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Annonces Actives</h6>
                        <h3 id="stat-active" class="mb-0">--</h3>
                    </div>
                    <i class="fa-solid fa-box-open fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">En Attente</h6>
                        <h3 id="stat-pending" class="mb-0">--</h3>
                    </div>
                    <i class="fa-solid fa-clock fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Ventes Valides</h6>
                        <h3 id="stat-orders" class="mb-0">--</h3>
                    </div>
                    <i class="fa-solid fa-cart-check fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- File de Modration -->
    <div class="card shadow-sm">
        <div class="card-header bg-white font-weight-bold">
            <i class="fa-solid fa-list-check me-2"></i>Annonces en attente de modration
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Titre</th>
                            <th>Vendeur</th>
                            <th>Prix</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pending-list">
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Chargement des donnes...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
async function loadAdminData() {
    try {
        // Charger les statistiques
        const resStats = await fetch('../api/admin.php?action=getStats');
        const stats = await resStats.json();
        
        document.getElementById('stat-users').innerText = stats.total_users || 0;
        document.getElementById('stat-active').innerText = stats.active_products || 0;
        document.getElementById('stat-pending').innerText = stats.pending_products || 0;
        document.getElementById('stat-orders').innerText = stats.completed_orders || 0;

        // Charger la liste des annonces en attente
        const resProducts = await fetch('../api/admin.php?action=getPendingProducts');
        const products = await resProducts.json();

        const listContainer = document.getElementById('pending-list');
        listContainer.innerHTML = '';

        if (!Array.isArray(products) || products.length === 0) {
            listContainer.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Aucune annonce en attente de modration.</td></tr>';
            return;
        }

        products.forEach(item => {
            const tr = document.createElement('tr');
            tr.id = `product-row-${item.id}`;
            tr.innerHTML = `
                <td><strong>${item.title}</strong></td>
                <td>${item.vendor_name} <br><small class="text-muted">${item.vendor_email}</small></td>
                <td>${parseFloat(item.price).toLocaleString()} XOF</td>
                <td><small>${new Date(item.created_at).toLocaleDateString()}</small></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-success me-1" onclick="moderate(${item.id}, 'active')">
                        <i class="fa-solid fa-check me-1"></i>Approuver
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="moderate(${item.id}, 'rejected')">
                        <i class="fa-solid fa-xmark me-1"></i>Rejeter
                    </button>
                </td>
            `;
            listContainer.appendChild(tr);
        });

    } catch (e) {
        console.error('Erreur chargement admin:', e);
    }
}

async function moderate(productId, status) {
    if (!confirm(`Confirmer cette action (${status}) ?`)) return;

    const formData = new FormData();
    formData.append('action', 'moderateProduct');
    formData.append('product_id', productId);
    formData.append('status', status);

    const res = await fetch('../api/admin.php', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
        const row = document.getElementById(`product-row-${productId}`);
        if (row) row.remove();
        loadAdminData();
    } else {
        alert('Erreur lors de l\'opration');
    }
}

document.addEventListener('DOMContentLoaded', loadAdminData);
</script>
</body>
</html>