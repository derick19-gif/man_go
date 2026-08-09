<?php
require_once 'config/config.php';
require_once 'core/Session.php';

use App\Core\Session;

if (!Session::isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$current_user_id = Session::getUserId();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie MAN GO Shield</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --mango-orange: #ff6600;
            --mango-dark: #1e293b;
            --chat-bg: #f8fafc;
        }

        body {
            background-color: #f1f5f9;
            height: 100vh;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .chat-container {
            height: calc(100vh - 40px);
            margin-top: 20px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* Sidebar Inbox */
        .inbox-sidebar {
            border-right: 1px solid #e2e8f0;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .inbox-header {
            padding: 16px;
            background-color: #fff;
            border-bottom: 1px solid #e2e8f0;
        }

        .inbox-list {
            flex: 1;
            overflow-y: auto;
        }

        .conversation-item {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: background 0.2s;
        }

        .conversation-item:hover, .conversation-item.active {
            background-color: #f8fafc;
        }

        .conversation-item.active {
            border-left: 4px solid var(--mango-orange);
        }

        .avatar-circle {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: var(--mango-orange);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Message Area */
        .chat-main {
            height: 100%;
            display: flex;
            flex-direction: column;
            background-color: var(--chat-bg);
        }

        .chat-header {
            padding: 16px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .message-bubble {
            max-width: 65%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 0.95rem;
            position: relative;
            word-wrap: break-word;
        }

        .message-sent {
            align-self: flex-end;
            background-color: var(--mango-orange);
            color: #ffffff;
            border-bottom-right-radius: 2px;
        }

        .message-received {
            align-self: flex-start;
            background-color: #ffffff;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 2px;
        }

        .message-time {
            font-size: 0.7rem;
            margin-top: 4px;
            opacity: 0.75;
            text-align: right;
        }

        .chat-input-area {
            padding: 16px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
        }

        .badge-label {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 10px;
        }

        /* Shield Warning */
        .shield-notice {
            background-color: #fff7ed;
            border: 1px solid #ffedd5;
            color: #c2410c;
            padding: 8px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .inbox-sidebar {
                display: block;
            }
            .chat-main {
                display: none;
            }
            .chat-main.active {
                display: flex;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 100;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid h-100">
    <div class="row h-100 justify-content: center align-items-center">
        <div class="col-12 col-xl-10 h-100">
            <div class="row chat-container g-0">
                
                <!-- SIDEBAR INBOX -->
                <div class="col-12 col-md-4 inbox-sidebar" id="inboxSidebar">
                    <div class="inbox-header">
                        <h5 class="m-0 fw-bold"><i class="fa-solid fa-shield-halved text-warning me-2"></i>MAN GO Shield</h5>
                        <small class="text-muted">Messagerie sécurisée</small>
                    </div>
                    <div class="inbox-list" id="inboxList">
                        <div class="text-center p-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Chargement des discussions...
                        </div>
                    </div>
                </div>

                <!-- MAIN CHAT AREA -->
                <div class="col-12 col-md-8 chat-main" id="chatMain">
                    
                    <!-- Chat Header -->
                    <div class="chat-header" id="chatHeader" style="display: none;">
                        <div class="d-flex align-items-center gap-3">
                            <button class="btn btn-sm btn-light d-md-none" onclick="closeChatMobile()"><i class="fa-solid fa-arrow-left"></i></button>
                            <div class="avatar-circle" id="activeAvatar">U</div>
                            <div>
                                <h6 class="m-0 fw-bold" id="activeContactName">Utilisateur</h6>
                                <span class="badge bg-secondary badge-label" id="activeLabel">Aucun label</span>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fa-solid fa-tag me-1"></i> ?tiquette
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="setChatLabel('Prospect')">YY Prospect</a></li>
                                <li><a class="dropdown-item" href="#" onclick="setChatLabel('En négociation')">Y" En négociation</a></li>
                                <li><a class="dropdown-item" href="#" onclick="setChatLabel('Payé / Vendu')">YY Payé / Vendu</a></li>
                                <li><a class="dropdown-item" href="#" onclick="setChatLabel('Urgent')">Y" Urgent</a></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Messages Container -->
                    <div class="chat-messages" id="chatMessages">
                        <div class="text-center my-auto text-muted">
                            <i class="fa-solid fa-comments fa-3x mb-3 text-secondary"></i>
                            <h6>Sélectionnez une conversation pour démarrer</h6>
                        </div>
                    </div>

                    <!-- Input Form -->
                    <div class="chat-input-area" id="chatInputArea" style="display: none;">
                        <div class="shield-notice">
                            <i class="fa-solid fa-lock me-1"></i> <strong>Protection MAN GO :</strong> Les numéros de téléphone et liens externes sont filtrés pour votre sécurité.
                        </div>

                        <!-- Bar d'outils réponses rapides -->
                        <div class="d-flex align-items-center mb-2 gap-2">
                            <button class="btn btn-sm btn-light border" onclick="toggleQuickReplies()">
                                <i class="fa-solid fa-bolt text-warning me-1"></i> Réponses rapides
                            </button>
                        </div>

                        <!-- Tiroir Réponses Rapides -->
                        <div id="quickRepliesBox" class="p-2 mb-2 bg-light border rounded" style="display: none;">
                            <small class="text-muted d-block mb-1">Cliquer pour insérer :</small>
                            <div id="quickRepliesList" class="d-flex flex-wrap gap-1">
                                <!-- Injecté dynamiquement par JS -->
                            </div>
                        </div>

                        <form id="sendMessageForm" onsubmit="handleSendMessage(event)">
                            <div class="input-group">
                                <input type="text" id="messageInput" class="form-control" placeholder="?crivez votre message..." autocomplete="off" required>
                                <button class="btn btn-primary" style="background-color: var(--mango-orange); border:none;" type="submit">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const CURRENT_USER_ID = <?= json_encode($current_user_id); ?>;
    let activeReceiverId = null;
    let pollInterval = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadInbox();
        loadQuickReplies();
        // Rafraîchir l'inbox toutes les 5 secondes
        setInterval(loadInbox, 5000);
    });

    // 1. Charger la liste des conversations (Inbox)
    async function loadInbox() {
        try {
            const response = await fetch('api/chat.php?action=inbox');
            const conversations = await response.json();
            
            const inboxList = document.getElementById('inboxList');
            if (conversations.length === 0) {
                inboxList.innerHTML = `<div class="text-center p-4 text-muted">Aucune conversation.</div>`;
                return;
            }

            let html = '';
            conversations.forEach(conv => {
                const isActive = conv.contact_id == activeReceiverId ? 'active' : '';
                const unreadBadge = (conv.is_read == 0 && conv.receiver_id == CURRENT_USER_ID) ? '<span class="badge bg-danger rounded-pill">Nouveau</span>' : '';
                const labelHtml = conv.label ? `<span class="badge bg-info badge-label">${escapeHtml(conv.label)}</span>` : '';

                html += `
                    <div class="conversation-item ${isActive}" onclick="openChat(${conv.contact_id}, '${escapeHtml(conv.label || '')}')">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-circle">U${conv.contact_id}</div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 fw-bold text-truncate">Utilisateur #${conv.contact_id}</h6>
                                    <small class="text-muted" style="font-size:0.7rem;">${formatTime(conv.created_at)}</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <p class="m-0 text-muted text-truncate" style="font-size: 0.85rem;">${escapeHtml(conv.message)}</p>
                                    ${unreadBadge}
                                    ${labelHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            inboxList.innerHTML = html;
        } catch (error) {
            console.error('Erreur chargement inbox:', error);
        }
    }

    // 2. Ouvrir une discussion
    function openChat(receiverId, label = '') {
        activeReceiverId = receiverId;
        
        // UI Adjustments
        document.getElementById('chatHeader').style.display = 'flex';
        document.getElementById('chatInputArea').style.display = 'block';
        document.getElementById('chatMain').classList.add('active');
        document.getElementById('activeContactName').innerText = `Utilisateur #${receiverId}`;
        document.getElementById('activeAvatar').innerText = `U${receiverId}`;
        
        updateLabelBadge(label);
        
        // Charger les messages et démarrer le polling
        loadMessages();
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(loadMessages, 3000);

        loadInbox(); // Met à jour le surlignage actif
    }

    // 3. Charger les messages de la conversation active
    async function loadMessages() {
        if (!activeReceiverId) return;

        try {
            const response = await fetch(`api/chat.php?action=get&receiver_id=${activeReceiverId}`);
            const messages = await response.json();
            
            const messagesContainer = document.getElementById('chatMessages');
            let html = '';

            messages.forEach(msg => {
                const isSent = msg.sender_id == CURRENT_USER_ID;
                const bubbleClass = isSent ? 'message-sent' : 'message-received';
                
                html += `
                    <div class="message-bubble ${bubbleClass}">
                        <div>${escapeHtml(msg.message)}</div>
                        <div class="message-time">${formatTime(msg.created_at)}</div>
                    </div>
                `;
            });

            messagesContainer.innerHTML = html;
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        } catch (error) {
            console.error('Erreur chargement messages:', error);
        }
    }

    // 4. Envoyer un message
    async function handleSendMessage(event) {
        event.preventDefault();
        const input = document.getElementById('messageInput');
        const message = input.value.trim();

        if (!message || !activeReceiverId) return;

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('receiver_id', activeReceiverId);
        formData.append('message', message);

        input.value = '';

        try {
            const response = await fetch('api/chat.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                loadMessages();
                loadInbox();
            } else {
                alert(result.message || 'Erreur lors de l?Tenvoi');
            }
        } catch (error) {
            console.error('Erreur envoi message:', error);
        }
    }

    // 5. Appliquer un Label / ?tiquette
    async function setChatLabel(label) {
        if (!activeReceiverId) return;

        const formData = new FormData();
        formData.append('action', 'setLabel');
        formData.append('chat_id', activeReceiverId);
        formData.append('label', label);

        try {
            const response = await fetch('api/chat.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                updateLabelBadge(label);
                loadInbox();
            }
        } catch (error) {
            console.error('Erreur étiquetage:', error);
        }
    }

    // 6. Charger & afficher les réponses rapides
    async function loadQuickReplies() {
        try {
            const response = await fetch('api/chat.php?action=getQuickReplies');
            const replies = await response.json();
            
            const listContainer = document.getElementById('quickRepliesList');
            if (!replies || replies.length === 0) {
                listContainer.innerHTML = `<span class="text-muted style="font-size:0.8rem;">Aucun raccourci configuré.</span>`;
                return;
            }

            let html = '';
            replies.forEach(r => {
                html += `
                    <button type="button" class="btn btn-xs btn-outline-secondary btn-sm" onclick="insertQuickReply('${escapeHtml(r.message)}')">
                        <strong>${escapeHtml(r.shortcut)}</strong>
                    </button>
                `;
            });
            listContainer.innerHTML = html;
        } catch (error) {
            console.error('Erreur raccourcis:', error);
        }
    }

    function toggleQuickReplies() {
        const box = document.getElementById('quickRepliesBox');
        box.style.display = box.style.display === 'none' ? 'block' : 'none';
    }

    function insertQuickReply(text) {
        const input = document.getElementById('messageInput');
        input.value = text;
        input.focus();
        toggleQuickReplies();
    }

    // Helpers UI
    function updateLabelBadge(label) {
        const badge = document.getElementById('activeLabel');
        if (label) {
            badge.innerText = label;
            badge.className = 'badge bg-primary badge-label';
        } else {
            badge.innerText = 'Aucun label';
            badge.className = 'badge bg-secondary badge-label';
        }
    }

    function closeChatMobile() {
        document.getElementById('chatMain').classList.remove('active');
        if (pollInterval) clearInterval(pollInterval);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function formatTime(dateTimeStr) {
        if (!dateTimeStr) return '';
        const date = new Date(dateTimeStr);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
</script>
</body>
</html>