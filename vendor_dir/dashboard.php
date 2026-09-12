<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Autoloader.php';

// Initialisation de la session
Session::init();

// Vérifier si l'utilisateur est connecté
if (!Session::isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// Aiguillage par rôle : si ce n'est pas un vendeur, on le renvoie vers l'espace client
if (Session::get('user_role') !== 'vendor') {
    header('Location: ../client/views/dashboard.php');
    exit;
}

$current_user_id = Session::getUserId();
$currency = $_SESSION['user_currency'] ?? 'FCFA';
$userName = Session::get('user_name') ?? 'Vendeur';
$userId = Session::get('user_id') ?? $current_user_id;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Vendeur & Prestataire - MAN GO</title>
    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --mango-orange: #f59e0b; /* Amber 500 */
            --mango-orange-dark: #d97706; /* Amber 600 */
            --mango-dark: #0f172a; /* Slate 900 */
            --mango-bg: #f8fafc;
        }
        body { 
            background-color: var(--mango-bg); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #334155;
        }
        
        /* Sidebar Styling */
        .sidebar { 
            background: #ffffff; 
            border-right: 1px solid #e2e8f0; 
            min-height: 100vh; 
            position: sticky;
            top: 0;
        }
        .nav-pills .nav-link {
            color: #64748b;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .nav-pills .nav-link:hover {
            background-color: #f1f5f9;
            color: var(--mango-dark);
            transform: translateX(5px);
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--mango-orange), var(--mango-orange-dark));
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
            transform: translateX(5px);
        }

        /* Cards & UI Elements */
        .stat-card { 
            background: #ffffff; 
            border-radius: 16px; 
            border: 1px solid #e2e8f0; 
            padding: 24px; 
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        .stat-icon { 
            width: 54px; 
            height: 54px; 
            border-radius: 14px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.5rem; 
        }
        .btn-mango {
            background: linear-gradient(135deg, var(--mango-orange), var(--mango-orange-dark));
            color: white;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-mango:hover {
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
            color: white;
            transform: translateY(-2px);
        }
        .card-custom {
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navbar -->
        <div class="col-md-3 col-lg-2 sidebar p-4">
            <div class="d-flex align-items-center mb-5">
                <div class="bg-warning text-dark fw-black rounded-3 d-flex align-items-center justify-content-center me-2 shadow-sm" style="width: 40px; height: 40px; font-weight: 900; font-size: 1.2rem;">M</div>
                <h5 class="fw-bold text-dark m-0">Espace <span class="text-warning">Pro</span></h5>
            </div>
            
            <div class="nav flex-column nav-pills gap-2" id="v-pills-tab" role="tablist">
                <button class="nav-link active text-start" id="tab-stats-btn" data-bs-toggle="pill" data-bs-target="#tab-stats"><i class="fa-solid fa-chart-line me-2 w-20px"></i> Statistiques</button>
                
                <!-- NOUVEAUX ONGLETS -->
                <button class="nav-link text-start" id="tab-listings-btn" data-bs-toggle="pill" data-bs-target="#tab-listings"><i class="fa-solid fa-box-open me-2 w-20px"></i> Mes Annonces</button>
                <button class="nav-link text-start" id="tab-publish-btn" data-bs-toggle="pill" data-bs-target="#tab-publish"><i class="fa-solid fa-plus-circle me-2 w-20px"></i> Publier</button>
                
                <hr class="my-2 text-muted">
                
                <button class="nav-link text-start" id="tab-settings-btn" data-bs-toggle="pill" data-bs-target="#tab-settings"><i class="fa-solid fa-robot me-2 w-20px"></i> Rép. Auto & Absence</button>
                <button class="nav-link text-start" id="tab-quick-btn" data-bs-toggle="pill" data-bs-target="#tab-quick"><i class="fa-solid fa-bolt me-2 w-20px"></i> Réponses Rapides</button>
                
                <a href="../index.php" class="btn btn-outline-dark mt-5 fw-bold rounded-pill"><i class="fa-solid fa-arrow-left me-2"></i> Retour au site</a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="col-md-9 col-lg-10 p-4 p-lg-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-extrabold m-0 text-dark">Bonjour, <?= htmlspecialchars($userName) ?> 👋</h3>
                <span class="badge bg-dark text-warning px-3 py-2 rounded-pill fs-6"><i class="fa-solid fa-store me-1"></i> Compte Vendeur</span>
            </div>

            <div class="tab-content" id="v-pills-tabContent">
                
                <!-- TAB 1: STATISTIQUES -->
                <div class="tab-pane fade show active" id="tab-stats">
                    <h5 class="fw-bold mb-4 text-muted">Aperçu des performances</h5>
                    <div class="row g-4" id="statsContainer">
                        <div class="text-center py-5">
                            <div class="spinner-border text-warning" role="status"></div>
                            <p class="mt-3 text-muted">Chargement de vos données...</p>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: MES ANNONCES (NOUVEAU) -->
                <div class="tab-pane fade" id="tab-listings">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold m-0">Gestion de mes annonces</h4>
                        <button class="btn btn-mango fw-bold rounded-pill px-4" onclick="document.getElementById('tab-publish-btn').click()">
                            <i class="fa-solid fa-plus me-1"></i> Créer
                        </button>
                    </div>

                    <div class="card card-custom overflow-hidden shadow-sm">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3 px-4">Produit / Service</th>
                                        <th>Catégorie</th>
                                        <th>Prix</th>
                                        <th>Statut</th>
                                        <th>Vues</th>
                                        <th class="text-end px-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="vendorListingsTable">
                                    <!-- Placeholder en attendant l'API -->
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                                <i class="fa-solid fa-box-open fs-1 text-secondary"></i>
                                            </div>
                                            <h5 class="fw-bold text-dark">Votre vitrine est vide</h5>
                                            <p>Commencez à vendre en publiant votre première annonce.</p>
                                            <button class="btn btn-outline-dark rounded-pill mt-2" onclick="document.getElementById('tab-publish-btn').click()">Publier maintenant</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: PUBLIER UNE ANNONCE (NOUVEAU) -->
                <div class="tab-pane fade" id="tab-publish">
                    <div class="card card-custom p-5 text-center shadow-sm border-0 bg-white relative overflow-hidden">
                        <!-- Effet de fond subtil -->
                        <div class="position-absolute top-0 start-0 w-100 h-100" style="background: radial-gradient(circle at top right, rgba(245, 158, 11, 0.05), transparent 40%); pointer-events: none;"></div>
                        
                        <div class="position-relative z-1">
                            <div class="mb-4">
                                <i class="fa-solid fa-rocket text-warning" style="font-size: 4rem; filter: drop-shadow(0 0 15px rgba(245,158,11,0.4));"></i>
                            </div>
                            <h2 class="fw-extrabold text-dark mb-3">Prêt à conquérir le marché ?</h2>
                            <p class="text-muted fs-5 mb-4 max-w-2xl mx-auto">
                                <strong class="text-dark">One Market, One Movement.</strong><br>
                                Que vous soyez artisan, entreprise, ou prestataire de services, MAN GO connecte vos offres au monde entier.
                            </p>
                            
                            <a href="../publish.php" class="btn btn-mango btn-lg rounded-pill px-5 fw-bold shadow-sm">
                                <i class="fa-solid fa-pen-nib me-2"></i> Accéder à l'éditeur complet
                            </a>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: PARAMÈTRES BUSINESS (ACCUEIL & ABSENCE) -->
                <div class="tab-pane fade" id="tab-settings">
                    <h4 class="fw-bold mb-4">Chat & Réponses Automatiques</h4>
                    <div class="card card-custom p-4 shadow-sm">
                        <form id="businessSettingsForm" onsubmit="saveBusinessSettings(event)">
                            
                            <div class="p-3 bg-light rounded-3 mb-4 border border-light-subtle">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input fs-5" type="checkbox" id="isAway">
                                    <label class="form-check-label fw-bold ms-2 mt-1" for="isAway">Activer le Mode Absence</label>
                                </div>
                                <small class="text-muted d-block ms-5 mb-3">Répond automatiquement à tous les messages reçus quand vous n'êtes pas disponible.</small>
                                
                                <div class="ms-5">
                                    <label class="form-label fw-semibold text-dark">Message d'absence</label>
                                    <textarea id="autoReplyMessage" class="form-control" rows="2" placeholder="Bonjour, je suis actuellement indisponible. Je vous réponds dès mon retour."></textarea>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-3 mb-4 border border-light-subtle">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input fs-5" type="checkbox" id="autoReplyEnabled">
                                    <label class="form-check-label fw-bold ms-2 mt-1" for="autoReplyEnabled">Message d'Accueil Automatique</label>
                                </div>
                                <small class="text-muted d-block ms-5 mb-3">Envoyé automatiquement lors de la première prise de contact d'un client.</small>
                                
                                <div class="ms-5">
                                    <label class="form-label fw-semibold text-dark">Message de bienvenue</label>
                                    <textarea id="welcomeMessage" class="form-control" rows="2" placeholder="Bienvenue sur ma boutique ! En quoi puis-je vous aider aujourd'hui ?"></textarea>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-mango fw-bold rounded-pill px-5"><i class="fa-solid fa-save me-2"></i> Enregistrer les paramètres</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- TAB 5: RÉPONSES RAPIDES -->
                <div class="tab-pane fade" id="tab-quick">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold m-0">Gestion des Raccourcis</h4>
                        <button class="btn btn-mango btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="openQuickModal()">
                            <i class="fa-solid fa-plus me-1"></i> Ajouter un raccourci
                        </button>
                    </div>

                    <div class="card card-custom p-0 overflow-hidden shadow-sm">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3 px-4">Raccourci</th>
                                        <th>Message prédéfini</th>
                                        <th class="text-end px-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="quickRepliesTable">
                                    <tr><td colspan="3" class="text-center text-muted py-4">Chargement...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- MODAL AJOUT RÉPONSE RAPIDE -->
<div class="modal fade" id="quickReplyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-extrabold text-dark">Nouveau Raccourci</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickReplyForm" onsubmit="saveQuickReply(event)">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted">Intitulé / Raccourci</label>
                        <input type="text" id="quickShortcut" class="form-control form-control-lg" placeholder="ex: /prix, /livraison, Merci" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-muted">Message complet</label>
                        <textarea id="quickMessage" class="form-control" rows="4" placeholder="Texte qui sera inséré dans la discussion..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-mango w-100 fw-bold rounded-pill py-2">Enregistrer le raccourci</button>
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
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card d-flex align-items-center gap-3">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-box"></i></div>
                        <div>
                            <small class="text-muted fw-semibold d-block">Annonces Actives</small>
                            <h4 class="fw-extrabold m-0 text-dark">${data.active_products || 0}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fa-solid fa-cart-check"></i></div>
                        <div>
                            <small class="text-muted fw-semibold d-block">Ventes Réalisées</small>
                            <h4 class="fw-extrabold m-0 text-dark">${data.total_sales || 0}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fa-solid fa-wallet"></i></div>
                        <div>
                            <small class="text-muted fw-semibold d-block">Revenus Total</small>
                            <h4 class="fw-extrabold m-0 text-dark">${Number(data.total_revenue || 0).toLocaleString()} ${CURRENCY}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card d-flex align-items-center gap-3">
                        <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fa-solid fa-comments"></i></div>
                        <div>
                            <small class="text-muted fw-semibold d-block">Discussions</small>
                            <h4 class="fw-extrabold m-0 text-dark">${data.active_chats || 0}</h4>
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
                alert('Paramètres sauvegardés avec succès !');
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
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-5">Aucun raccourci enregistré.</td></tr>';
                return;
            }

            let html = '';
            replies.forEach(r => {
                html += `
                    <tr>
                        <td class="px-4"><span class="badge bg-dark fw-normal px-2 py-1">${escapeHtml(r.shortcut)}</span></td>
                        <td class="text-secondary">${escapeHtml(r.message)}</td>
                        <td class="text-end px-4">
                            <button onclick="deleteQuickReply(${r.id})" class="btn btn-sm btn-outline-danger rounded-circle" style="width: 32px; height: 32px;"><i class="fa-solid fa-trash"></i></button>
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
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce raccourci ?')) return;
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