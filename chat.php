<?php
// chat.php (Interface de Messagerie)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

// ON UTILISE VOTRE CLASSE SESSION SECURISEE
Session::init();

if (Session::isAuthenticated() === false) {
    header('Location: login.php');
    exit;
}

$current_user_id = Session::getUserId();$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;
$userRole = Session::get('user_role');

// Définition de l'URL de retour selon le rôle
$returnUrl =$baseUrl . '/client/views/dashboard.php';
if ($userRole === 'vendor') {
    $returnUrl =$baseUrl . '/vendor_dir/dashboard.php';
}
if ($userRole === 'vendeur') {
    $returnUrl =$baseUrl . '/vendor_dir/dashboard.php';
}
if ($userRole === 'admin') {
    $returnUrl =$baseUrl . '/admin/dashboard.php';
}
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
        :root { --mango-orange: #f59e0b; --mango-dark: #0f172a; --chat-bg: #f8fafc; }
        body { background-color: #f1f5f9; height: 100vh; overflow: hidden; font-family: 'Segoe UI', sans-serif; }
        .chat-container { height: calc(100vh - 40px); margin-top: 20px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e2e8f0; }
        .inbox-sidebar { border-right: 1px solid #e2e8f0; height: 100%; display: flex; flex-direction: column; background: #fff; }
        .inbox-header { padding: 20px; background-color: #fff; border-bottom: 1px solid #f1f5f9; }
        .inbox-list { flex: 1; overflow-y: auto; }
        .conversation-item { padding: 16px 20px; border-bottom: 1px solid #f8fafc; cursor: pointer; transition: background 0.2s; }
        .conversation-item:hover { background-color: #f8fafc; }
        .conversation-item.active { background-color: #fffbeb; border-left: 4px solid var(--mango-orange); }
        .avatar-circle { width: 48px; height: 48px; border-radius: 50%; background-color: var(--mango-dark); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.2rem; }
        .chat-main { height: 100%; display: flex; flex-direction: column; background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); background-color: var(--chat-bg); }
        .chat-header { padding: 16px 24px; background: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.02); z-index: 10; }
        .chat-messages { flex: 1; padding: 24px; overflow-y: auto; display: flex; flex-direction: column; gap: 16px; scroll-behavior: smooth; }
        .message-bubble { max-width: 70%; padding: 12px 16px; border-radius: 16px; font-size: 0.95rem; position: relative; word-wrap: break-word; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .message-sent { align-self: flex-end; background-color: var(--mango-dark); color: #ffffff; border-bottom-right-radius: 4px; }
        .message-received { align-self: flex-start; background-color: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px; }
        .message-time { font-size: 0.7rem; margin-top: 6px; display: flex; align-items: center; justify-content: flex-end; gap: 4px; }
        .message-sent .message-time { color: rgba(255,255,255,0.7); }
        .message-received .message-time { color: #94a3b8; }
        .chat-input-area { padding: 20px; background: #ffffff; border-top: 1px solid #e2e8f0; }
        .shield-notice { background-color: #fffbeb; border: 1px solid #fef3c7; color: #d97706; padding: 10px 14px; font-size: 0.85rem; border-radius: 12px; margin-bottom: 12px; display: flex; align-items: center; }
        .tick-sent { color: #94a3b8; } 
        .tick-read { color: #3b82f6; } 
        
        @media (max-width: 768px) {
            .inbox-sidebar { display: block; }
            .chat-main { display: none; }
            .chat-main.active { display: flex; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 100; }
        }
    </style>
</head>
<body>

<a href="<?= $returnUrl ?>" class="btn btn-dark position-absolute shadow" style="top: 20px; left: 20px; z-index: 1000; border-radius: 50px;">
    <i class="fa-solid fa-arrow-left me-2"></i> Tableau de bord
</a>

<div class="container-fluid h-100">
    <div class="row h-100 justify-content-center align-items-center">
        <div class="col-12 col-xl-10 h-100">
            <div class="row chat-container g-0">
                
                <div class="col-12 col-md-4 inbox-sidebar" id="inboxSidebar">
                    <div class="inbox-header text-center pt-5">
                        <div class="d-inline-flex align-items-center justify-content-center w-12 h-12 rounded-circle bg-warning bg-opacity-10 text-warning mb-2 p-3">
                            <i class="fa-solid fa-shield-halved fa-2x"></i>
                        </div>
                        <h5 class="m-0 fw-black">MAN GO Shield</h5>
                        <small class="text-muted fw-bold">Messagerie 100% Sécurisée</small>
                    </div>
                    <div class="inbox-list" id="inboxList">
                        <div class="text-center p-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-warning me-2" role="status"></div>
                            Chargement...
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-8 chat-main" id="chatMain">
                    
                    <div class="chat-header" id="chatHeader" style="display: none;">
                        <div class="d-flex align-items-center gap-3">
                            <button class="btn btn-sm btn-light d-md-none rounded-circle" onclick="closeChatMobile()"><i class="fa-solid fa-arrow-left"></i></button>
                            <div class="avatar-circle bg-warning text-dark" id="activeAvatar">U</div>
                            <div>
                                <h5 class="m-0 fw-bold text-dark" id="activeContactName">Utilisateur</h5>
                                <small class="text-success fw-bold"><i class="fa-solid fa-circle text-success" style="font-size: 0.5rem; margin-right:4px;"></i> En ligne</small>
                            </div>
                        </div>
                    </div>

                    <div class="chat-messages" id="chatMessages">
                        <div class="text-center my-auto text-muted">
                            <div class="bg-white p-4 rounded-circle shadow-sm d-inline-block mb-3">
                                <i class="fa-solid fa-comments fa-3x text-warning"></i>
                            </div>
                            <h5 class="fw-bold text-dark">Vos messages s'affichent ici</h5>
                            <p class="small">Sélectionnez une conversation dans le menu de gauche.</p>
                        </div>
                    </div>

                    <div class="chat-input-area" id="chatInputArea" style="display: none;">
                        <div class="shield-notice shadow-sm">
                            <i class="fa-solid fa-lock text-warning me-2 fa-lg"></i> 
                            <div><strong>Sécurité active :</strong> Les numéros, liens externes et mots inappropriés sont filtrés automatiquement.</div>
                        </div>

                        <form id="sendMessageForm" onsubmit="handleSendMessage(event)">
                            <div class="input-group shadow-sm rounded-pill p-1 bg-white border border-secondary border-opacity-25">
                                <input type="text" id="messageInput" class="form-control border-0 bg-transparent shadow-none px-4" placeholder="Écrivez votre message en toute sécurité..." autocomplete="off" required>
                                <button class="btn text-white rounded-pill px-4 fw-bold transition" style="background-color: var(--mango-orange);" type="submit" id="sendBtn">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Envoyer
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
    const URL_VENDOR_ID = <?= json_encode($vendor_id); ?>;
    let activeReceiverId = null;
    let pollInterval = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadInbox();
        setInterval(loadInbox, 3000); 

        if (URL_VENDOR_ID) {
            if (URL_VENDOR_ID !== CURRENT_USER_ID) {
                setTimeout(() => {
                    openChat(URL_VENDOR_ID, "Nouvelle Discussion");
                    document.getElementById('chatHeader').style.display = 'flex';
                    document.getElementById('chatInputArea').style.display = 'block';
                    document.getElementById('chatMain').classList.add('active');
                }, 200);
            }
        }
    });

    async function loadInbox() {
        try {
            const response = await fetch('api/chat.php?action=inbox');
            const conversations = await response.json();
            const inboxList = document.getElementById('inboxList');
            
            let isEmpty = false;
            if (conversations == null) { isEmpty = true; }
            if (conversations && conversations.length === 0) { isEmpty = true; }
            
            if (isEmpty) {
                inboxList.innerHTML = `<div class="text-center p-5 text-muted"><i class="fa-solid fa-inbox fa-2x mb-3 text-light"></i><br>Aucune conversation.</div>`;
                return;
            }

            let html = '';
            conversations.forEach(conv => {
                const isActive = (conv.contact_id == activeReceiverId) ? 'active' : '';
                
                let fName = conv.firstname ? conv.firstname : '';
                let lName = conv.lastname ? conv.lastname : '';
                let cName = (fName + ' ' + lName).trim();
                
                const contactName = cName !== '' ? cName : 'Utilisateur #' + conv.contact_id;
                const initial = contactName.charAt(0).toUpperCase();
                
                let unreadBadge = '';
                if (conv.is_read == 0) {
                    if (conv.receiver_id == CURRENT_USER_ID) {
                        unreadBadge = '<span class="badge bg-danger rounded-pill shadow-sm">Nouveau</span>';
                    }
                }

                html += `
                    <div class="conversation-item ${isActive}" onclick="openChat(${conv.contact_id}, '${escapeHtml(contactName)}')">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-circle shadow-sm">${initial}</div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="m-0 fw-bold text-dark text-truncate">${escapeHtml(contactName)}</h6>
                                    <small class="text-muted" style="font-size:0.7rem;">${formatTime(conv.created_at)}</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <p class="m-0 text-muted text-truncate" style="font-size: 0.85rem; ${unreadBadge !== '' ? 'font-weight:bold; color:#0f172a;' : ''}">${escapeHtml(conv.message)}</p>
                                    ${unreadBadge}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            inboxList.innerHTML = html;
        } catch (error) { console.error('Erreur chargement inbox:', error); }
    }

    function openChat(receiverId, contactName) {
        activeReceiverId = receiverId;
        
        document.getElementById('chatHeader').style.display = 'flex';
        document.getElementById('chatInputArea').style.display = 'block';
        document.getElementById('chatMain').classList.add('active');
        document.getElementById('activeContactName').innerText = contactName;
        document.getElementById('activeAvatar').innerText = contactName.charAt(0).toUpperCase();
        
        loadMessages();
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(loadMessages, 2000); 
        loadInbox();
    }

    async function loadMessages() {
        if (!activeReceiverId) return;
        try {
            const response = await fetch(`api/chat.php?action=get&receiver_id=${activeReceiverId}`);
            const messages = await response.json();
            const messagesContainer = document.getElementById('chatMessages');
            
            const isScrolledToBottom = messagesContainer.scrollHeight - messagesContainer.clientHeight <= messagesContainer.scrollTop + 50;
            
            let isMsgEmpty = false;
            if (messages == null) { isMsgEmpty = true; }
            if (messages && messages.length === 0) { isMsgEmpty = true; }

            if (isMsgEmpty) {
                if(messagesContainer.innerHTML.indexOf('fa-handshake') === -1) {
                    messagesContainer.innerHTML = `
                        <div class="text-center my-auto text-muted pt-5">
                            <div class="bg-white p-4 rounded-circle shadow-sm d-inline-block mb-3">
                                <i class="fa-solid fa-handshake fa-3x text-warning"></i>
                            </div>
                            <h5 class="fw-bold text-dark">Nouvelle discussion</h5>
                            <p class="small">Envoyez un premier message pour démarrer l'échange.</p>
                        </div>`;
                }
                return;
            }

            let html = '';

            messages.forEach(msg => {
                const isSent = (msg.sender_id == CURRENT_USER_ID);
                const bubbleClass = isSent ? 'message-sent' : 'message-received';
                
                let readStatus = '';
                if (isSent) {
                    if (msg.is_read == 1) {
                        readStatus = '<i class="fa-solid fa-check-double tick-read ms-2" title="Lu"></i>';
                    } else {
                        readStatus = '<i class="fa-solid fa-check tick-sent ms-2" title="Envoyé"></i>';
                    }
                }

                let deleteBtn = '';
                if (isSent) {
                    if (msg.message.indexOf('🚫') === -1) {
                        deleteBtn = `<i class="fa-solid fa-trash ms-3 cursor-pointer opacity-50 hover:opacity-100 transition" style="cursor:pointer;" onclick="deleteMessage(${msg.id})" title="Supprimer"></i>`;
                    }
                }

                html += `
                    <div class="message-bubble ${bubbleClass} d-flex flex-column shadow-sm" id="msg-${msg.id}">
                        <div>${msg.message}</div>
                        <div class="message-time">
                            <span>${formatTime(msg.created_at)}</span>
                            ${readStatus}
                            ${deleteBtn}
                        </div>
                    </div>
                `;
            });
            messagesContainer.innerHTML = html;
            
            if (isScrolledToBottom) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        } catch (error) { console.error("Erreur messages:", error); }
    }

    async function handleSendMessage(event) {
        event.preventDefault();
        const input = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const message = input.value.trim();
        
        if (!message) return;
        if (!activeReceiverId) return;

        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('receiver_id', activeReceiverId);
        formData.append('message', message);
        
        input.value = '';

        try {
            const response = await fetch('api/chat.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.status === 'success') {
                await loadMessages();
                const messagesContainer = document.getElementById('chatMessages');
                messagesContainer.scrollTop = messagesContainer.scrollHeight; 
                loadInbox();
            } else {
                alert(result.message ? result.message : "Erreur lors de l'envoi.");
            }
        } catch (error) { console.error(error); }
        
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Envoyer';
        input.focus();
    }

    async function deleteMessage(msgId) {
        if (!confirm("Voulez-vous vraiment supprimer ce message pour tout le monde ?")) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', msgId);

            const response = await fetch('api/chat.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.status === 'success') {
                loadMessages(); 
            } else {
                alert(result.message);
            }
        } catch (error) { console.error("Erreur suppression:", error); }
    }

    function closeChatMobile() {
        document.getElementById('chatMain').classList.remove('active');
        if (pollInterval) clearInterval(pollInterval);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    function formatTime(dateTimeStr) {
        if (!dateTimeStr) return '';
        const date = new Date(dateTimeStr);
        return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }
</script>
</body>
</html>