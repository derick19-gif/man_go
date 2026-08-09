<?php
require_once '../config/config.php';
require_once '../core/Session.php';

use App\Core\Session;

if (!Session::isAuthenticated()) {
    header('Location: ../login.php');
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
    <title>Espace Vendeur & Prestataire - MAN GO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --mango-orange: #ff6600;
            --mango-dark: #1e293b;
        }
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: #ffffff; border-right: 1px solid #e2e8f0; min-height: 100vh; }
        .stat-card { background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navbar -->
        <div class="col-md-3 col-lg-2 sidebar p-3">
            <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-store text-warning me-2"></i>Espace Vendeur</h5>
            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                <button class="nav-link active text-start mb-2" id="tab-stats-btn" data-bs-toggle="pill" data-bs-target="#tab-stats"><i class="fa-solid fa-chart-line me-2"></i> Statistiques</button>
                <button class="nav-link text-start mb-2" id="tab-settings-btn" data-bs-toggle="pill" data-bs-target="#tab-settings"><i class="fa-solid fa-sliders me-2"></i> Rponses Auto & Absence</button>
                <button class="nav-link text-start mb-2" id="tab-quick-btn" data-bs-toggle="pill" data-bs-target="#tab-quick"><i class="fa-solid fa-bolt me-2"></i> Rponses Rapides</button>
                <a href="../index.php" class="btn btn-outline-secondary mt-4"><i class="fa-solid fa-house me-2"></i> Retour au site</a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="col-md-9 col-lg-10 p-4">
            <div class="tab-content" id="v-pills-tabContent">
                
                <!-- TAB 1: STATISTIQUES -->
                <div class="tab-pane fade show active" id="tab-stats">
                    <h4 class="fw-bold mb-4">Aperu des performances</h4>
                    <div class="row g-3" id="statsContainer">
                        <div class="text-center py-4">
                            <div class="spinner-border text-warning" role="status"></div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: PARAMTRES BUSINESS (ACCUEIL & ABSENCE) -->
                <div class="tab-pane fade" id="tab-settings">
                    <h4 class="fw-bold mb-4">Configuration du Chat & Rponses Automatiques</h4>
                    <div class="card p-4 border-0 shadow-sm">
                        <form id="businessSettingsForm" onsubmit="saveBusinessSettings(event)">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="isAway">
                                <label class="form-check-label fw-bold" for="isAway">Activer le Mode Absence</label>
                                <small class="d-block text-muted">Rpond automatiquement  tous les messages reus quand vous n'tes pas disponible.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Message d'absence</label>
                                <textarea id="autoReplyMessage" class="form-control" rows="2" placeholder="Bonjour, je suis actuellement indisponible. Je vous rponds ds mon retour."></textarea>
                            </div>

                            <hr class="my-4">

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="autoReplyEnabled">
                                <label class="form-check-label fw-bold" for="autoReplyEnabled">Activer le Message d'Accueil Automatique</label>
                                <small class="d-block text-muted">Envoy automatiquement lors de la premire prise de contact d'un client.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Message de bienvenue</label>
                                <textarea id="welcomeMessage" class="form-control" rows="2" placeholder="Bienvenue sur ma boutique ! En quoi puis-je vous aider aujourd'hui ?"></textarea>
                            </div>

                            <button type="submit" class="btn text-white fw-bold" style="background-color: var(--mango-orange);">Enregistrer les paramtres</button>
                        </form>
                    </div>
                </div>

                <!-- TAB 3: RPONSES RAPIDES -->
                <div class="tab-pane fade" id="tab-quick">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold m-0">Gestion des Rponses Rapides (Shortcuts)</h4>
                        <button class="btn btn-sm text-white fw-bold" style="background-color: var(--mango-orange);" onclick="openQuickModal()">
                            <i class="fa-solid fa-plus me-1"></i> Ajouter un raccourci
                        </button>
                    </div>

                    <div class="card p-3 border-0 shadow-sm">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Raccourci</th>
                                        <th>Message prdfini</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="quickRepliesTable">
                                    <tr><td colspan="3" class="text-center text-muted">Chargement...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- MODAL AJOUT RPONSE RAPIDE -->
<div class="modal fade" id="quickReplyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Nouveau Raccourci</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickReplyForm" onsubmit="saveQuickReply(event)">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Intitul / Raccourci</label>
                        <input type="text" id="quickShortcut" class="form-control" placeholder="ex: /prix, /livraison, Merci" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message complet</label>
                        <textarea id="quickMessage" class="form-control" rows="3" placeholder="Texte qui sera insr dans la discussion..." required></textarea>
                    </div>
                    <button type="submit" class="btn text-white w-100 fw-bold" style="background-color: var(--mango-orange);">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const CURRENCY = <?=json_encode($currency)?>;

    document.addEventListener('DOMContentLoaded', () => {
        loadVendorStats();
        loadBusinessSettings();
        loadQuickReplies();
    });

    async function loadVendorStats() {
        try {
            const res = await fetch('../api/vendor.php?action=getStats');
            const data = await res.json();

            const container = document.getElementById('statsContainer');
            container.innerHTML = `
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="stat-card d-flex align-items-center gap-3">
                        <div class="stat-icon bg-warning text-white"><i class="fa-solid fa-box"></i></div>
                        <div>
                            <small class="text-muted d-block">Annonces Actives</small>
                            <h5 class="fw-bold m-0">${data.active_products || 0}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="stat-card d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success text-white"><i class="fa-solid fa-cart-check"></i></div>
                        <div>
                            <small class="text-muted d-block">Ventes Ralises</small>
                            <h5 class="fw-bold m-0">${data.total_sales || 0}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="stat-card d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary text-white"><i class="fa-solid fa-wallet"></i></div>
                        <div>
                            <small class="text-muted d-block">Revenus Total</small>
                            <h5 class="fw-bold m-0">${Number(data.total_revenue || 0).toLocaleString()} ${CURRENCY}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="stat-card d-flex align-items-center gap-3">
                        <div class="stat-icon bg-info text-white"><i class="fa-solid fa-comments"></i></div>
                        <div>
                            <small class="text-muted d-block">Discussions en cours</small>
                            <h5 class="fw-bold m-0">${data.active_chats || 0}</h5>
                        </div>
                    </div>
                </div>
            `;
        } catch (e) {
            console.error(e);
        }
    }

    async function loadBusinessSettings() {
        try {
            const res = await fetch('../api/chat.php?action=getBusinessSettings');
            const data = await res.json();

            document.getElementById('isAway').checked = data.is_away == 1;
            document.getElementById('autoReplyMessage').value = data.auto_reply_message || '';
            document.getElementById('autoReplyEnabled').checked = data.auto_reply_enabled == 1;
            document.getElementById('welcomeMessage').value = data.welcome_message || '';
        } catch (e) {
            console.error(e);
        }
    }

    async function saveBusinessSettings(e) {
        e.preventDefault();
        const formData = new URLSearchParams({
            action: 'saveBusinessSettings',
            is_away: document.getElementById('isAway').checked ? 1 : 0,
            auto_reply_message: document.getElementById('autoReplyMessage').value,
            auto_reply_enabled: document.getElementById('autoReplyEnabled').checked ? 1 : 0,
            welcome_message: document.getElementById('welcomeMessage').value
        });

        try {
            const res = await fetch('../api/chat.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.status === 'success') {
                alert('Paramtres du chat sauvegards avec succs !');
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadQuickReplies() {
        try {
            const res = await fetch('../api/chat.php?action=getQuickReplies');
            const replies = await res.json();

            const tbody = document.getElementById('quickRepliesTable');
            if (!replies || replies.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Aucun raccourci enregistr.</td></tr>';
                return;
            }

            let html = '';
            replies.forEach(r => {
                html += `
                    <tr>
                        <td><span class="badge bg-secondary">${escapeHtml(r.shortcut)}</span></td>
                        <td>${escapeHtml(r.message)}</td>
                        <td class="text-end">
                            <button onclick="deleteQuickReply(${r.id})" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } catch (e) {
            console.error(e);
        }
    }

    function openQuickModal() {
        document.getElementById('quickReplyForm').reset();
        new bootstrap.Modal(document.getElementById('quickReplyModal')).show();
    }

    async function saveQuickReply(e) {
        e.preventDefault();
        const formData = new URLSearchParams({
            action: 'addQuickReply',
            shortcut: document.getElementById('quickShortcut').value,
            message: document.getElementById('quickMessage').value
        });

        try {
            const res = await fetch('../api/chat.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('quickReplyModal')).hide();
                loadQuickReplies();
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function deleteQuickReply(id) {
        if (!confirm('Supprimer ce raccourci ?')) return;
        try {
            const res = await fetch(`../api/chat.php?action=deleteQuickReply&id=${id}`, { method: 'POST' });
            const result = await res.json();
            if (result.status === 'success') loadQuickReplies();
        } catch (e) {
            console.error(e);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }
</script>
</body>
</html>