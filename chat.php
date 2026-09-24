<?php
// chat.php (Interface de Messagerie MAN GO Shield)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

Session::init();

if (!Session::isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$current_user_id = Session::getUserId();
$baseUrl = defined('APP_URL') ? APP_URL : '/man_go';
$vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;
$userRole = Session::get('user_role');

// Redirection sécurisée
$returnUrl = $baseUrl . '/client/views/dashboard.php';
if (in_array($userRole, ['vendor', 'vendeur'])) { $returnUrl = $baseUrl . '/vendor_dir/dashboard.php'; }
if ($userRole === 'admin') { $returnUrl = $baseUrl . '/admin/dashboard.php'; }

// Récupérer le vrai nom du destinataire
$target_name = "Utilisateur";
if ($vendor_id !== null) {
    try {
        $db_chat = \App\Core\Database::connect();
        $stmtUser = $db_chat->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
        $stmtUser->execute([$vendor_id]);
        $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($uData) {
            $fName = isset($uData['firstname']) ? $uData['firstname'] : '';
            $lName = isset($uData['lastname']) ? $uData['lastname'] : '';
            $target_name = trim($fName . ' ' . $lName);
            if ($target_name === '') { $target_name = 'Utilisateur #' . $vendor_id; }
        }
    } catch(Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAN GO Shield - Messagerie Pro</title>
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
        .message-bubble { max-width: 75%; padding: 12px 16px; border-radius: 16px; font-size: 0.95rem; position: relative; word-wrap: break-word; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .message-sent { align-self: flex-end; background-color: var(--mango-dark); color: #ffffff; border-bottom-right-radius: 4px; }
        .message-received { align-self: flex-start; background-color: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px; }
        .message-time { font-size: 0.7rem; margin-top: 8px; display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
        .message-sent .message-time { color: rgba(255,255,255,0.7); }
        .message-received .message-time { color: #64748b; }
        
        .chat-input-area { padding: 16px 20px; background: #ffffff; border-top: 1px solid #e2e8f0; }
        .shield-notice { background-color: #fffbeb; border: 1px solid #fef3c7; color: #d97706; padding: 8px 14px; font-size: 0.8rem; border-radius: 12px; margin-bottom: 12px; display: flex; align-items: center; }
        
        .action-btn { background: none; border: none; color: #64748b; padding: 0; font-size: 0.9rem; transition: color 0.2s; cursor: pointer; }
        .action-btn:hover { color: var(--mango-orange); }
        .mic-btn { width: 40px; height: 40px; border-radius: 50%; border: none; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .mic-btn.recording { background: #ef4444; color: white; animation: pulse 1.5s infinite; }
        .attach-btn { width: 40px; height: 40px; border-radius: 50%; border: none; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .attach-btn:hover { background: #e2e8f0; }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

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
                
                <!-- SIDEBAR -->
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

                <!-- MAIN CHAT -->
                <div class="col-12 col-md-8 chat-main" id="chatMain">
                    
                    <div class="chat-header" id="chatHeader" style="display: none;">
                        <div class="d-flex align-items-center gap-3">
                            <button class="btn btn-sm btn-light d-md-none rounded-circle" onclick="closeChatMobile()"><i class="fa-solid fa-arrow-left"></i></button>
                            <div class="avatar-circle bg-warning text-dark" id="activeAvatar">U</div>
                            <div>
                                <h5 class="m-0 fw-bold text-dark" id="activeContactName">Utilisateur</h5>
                                <small class="text-muted fw-bold" id="activeContactRole">Membre MAN GO</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <!-- NOUVEAU: Bouton Son -->
                            <button id="notifBtn" class="btn btn-light rounded-circle shadow-sm" onclick="toggleNotif()" title="Désactiver le son">
                                <i class="fa-solid fa-bell text-warning"></i>
                            </button>
                            
                            <div class="dropdown">
                                <button class="btn btn-light rounded-circle" data-bs-toggle="dropdown"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                    <li><a class="dropdown-item text-danger" href="#" onclick="alert('Conversation signalée à l\'admin.')"><i class="fa-solid fa-flag me-2"></i>Signaler l'utilisateur</a></li>
                                </ul>
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
                            <div><strong>Sécurité active :</strong> Traduction dispo. Audios convertis en texte. Numéros filtrés.</div>
                        </div>

                        <!-- Menu des pièces jointes -->
                        <div id="attachmentMenu" class="bg-light p-2 rounded-3 mb-2 d-none flex-wrap gap-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('fileImage').click()"><i class="fa-solid fa-image text-primary me-1"></i> Image (Max 5Mo)</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('fileVideo').click()"><i class="fa-solid fa-video text-danger me-1"></i> Vidéo (Max 30s)</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('fileDoc').click()"><i class="fa-solid fa-file-pdf text-danger me-1"></i> Document</button>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="sendLocation()"><i class="fa-solid fa-location-dot text-info me-1"></i> Ma Position</button> 
                            <input type="file" id="fileImage" accept="image/jpeg, image/png, image/webp" class="d-none" onchange="handleFileSelect(event)">
                            <input type="file" id="fileVideo" accept="video/mp4" class="d-none" onchange="handleFileSelect(event)">
                            <input type="file" id="fileDoc" accept="application/pdf" class="d-none" onchange="handleFileSelect(event)">
                        </div>

                        <form id="sendMessageForm" onsubmit="handleSendMessage(event)">
                            <form id="sendMessageForm" onsubmit="handleSendMessage(event)">
                            
                            <!-- NOUVEAU : Zone de prévisualisation de la citation (cachée par défaut) -->
                            <div id="replyPreviewContainer" class="d-none bg-light p-2 mb-2 rounded border-start border-4 border-warning position-relative">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="small fw-bold" style="color: var(--mango-orange);"><i class="fa-solid fa-reply me-1"></i> Réponse à :</div>
                                    <button type="button" class="btn-close btn-close-sm" onclick="cancelReply()" aria-label="Close"></button>
                                </div>
                                <div id="replyPreviewText" class="small text-truncate text-muted mt-1"></div>
                                <!-- Champ caché pour stocker le texte original cité pour l'envoi -->
                                <input type="hidden" id="quotedMessageInput" value="">
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="attach-btn" onclick="toggleAttachmentMenu()">
                                    <i class="fa-solid fa-plus fa-lg"></i>
                                </button>

                                <div class="dropup">
                                    <button type="button" class="attach-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Émojis">
                                        <i class="fa-regular fa-face-smile fa-lg"></i>
                                    </button>
                                    <div class="dropdown-menu p-2 shadow-lg border-0 mb-2" style="width: 240px; border-radius: 16px;">
                                        <h6 class="dropdown-header text-center fw-bold text-muted" style="font-size: 0.75rem;">Icônes Commerciales</h6>
                                        <div class="d-flex flex-wrap gap-1 justify-content-center">
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('🤝')">🤝</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('📦')">📦</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('🚚')">🚚</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('💳')">💳</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('✅')">✅</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('⚠️')">⚠️</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('💰')">💰</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('🛒')">🛒</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('🏷️')">🏷️</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('📍')">📍</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('🙏')">🙏</button>
                                            <button type="button" class="btn btn-light btn-sm fs-5 p-1 border-0" onclick="insertEmoji('👍')">👍</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="input-group shadow-sm rounded-pill p-1 bg-white border border-secondary border-opacity-25 flex-grow-1">
                                    <input type="text" id="messageInput" class="form-control border-0 bg-transparent shadow-none px-4" placeholder="Écrivez ou dictez un message..." autocomplete="off" spellcheck="true" lang="fr">
                                    <button class="btn text-white rounded-pill px-4 fw-bold transition" style="background-color: var(--mango-orange);" type="submit" id="sendBtn">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </button>
                                </div>

                                <button type="button" class="mic-btn shadow-sm" id="micBtn" onclick="toggleDictation()" title="Parler pour écrire">
                                    <i class="fa-solid fa-microphone fa-lg"></i>
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
    const URL_VENDOR_NAME = <?= json_encode($target_name); ?>;
    let activeReceiverId = null;
    let pollInterval = null;
    let lastMessagesHtml = ''; 
    
    // NOUVEAU : Variables pour le Son et les Messages Importants
    let soundEnabled = true;
    const notifSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3'); // Petit bip professionnel
    let lastMessageId = 0;
    
    const SpeechRecognition = window.SpeechRecognition ? window.SpeechRecognition : (window.webkitSpeechRecognition ? window.webkitSpeechRecognition : null);
    let recognition = null;
    let isRecording = false;

    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.lang = 'fr-FR'; 
        recognition.interimResults = false;
        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            const inputField = document.getElementById('messageInput');
            inputField.value = inputField.value ? inputField.value + ' ' + transcript : transcript;
            inputField.focus();
        };
        recognition.onend = function() {
            isRecording = false;
            document.getElementById('micBtn').classList.remove('recording');
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadInbox();
        setInterval(loadInbox, 3000);
        setInterval(checkOnlineStatus, 10000); 

        if (URL_VENDOR_ID && URL_VENDOR_ID != CURRENT_USER_ID) {
            setTimeout(() => {
                openChat(URL_VENDOR_ID, URL_VENDOR_NAME);
                document.getElementById('chatHeader').style.display = 'flex';
                document.getElementById('chatInputArea').style.display = 'block';
                document.getElementById('chatMain').classList.add('active');
            }, 200);
        }
    });

    async function loadInbox() {
        const inboxList = document.getElementById('inboxList');
        try {
            const response = await fetch('api/chat.php?action=inbox');
            const textResponse = await response.text();
            let conversations;
            
            try {
                conversations = JSON.parse(textResponse);
            } catch (parseError) {
                inboxList.innerHTML = `<div class="text-center p-4 text-danger"><i class="fa-solid fa-triangle-exclamation mb-2"></i><br>Erreur serveur API.</div>`;
                return;
            }
            
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

                // NOUVEAU : Nettoie l'aperçu du message pour cacher la balise [QUOTE]
                let cleanPreview = conv.message ? conv.message.replace(/\[QUOTE\](.*?)\[\/QUOTE\]\n?/g, '↪️ Réponse : ') : '';

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
                                    <p class="m-0 text-muted text-truncate" style="font-size: 0.85rem; ${unreadBadge !== '' ? 'font-weight:bold; color:#0f172a;' : ''}">${escapeHtml(cleanPreview)}</p>
                                    ${unreadBadge}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            inboxList.innerHTML = html;
        } catch (error) { 
            inboxList.innerHTML = `<div class="text-center p-4 text-danger">Impossible de contacter le serveur.</div>`;
        }
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
            const textResponse = await response.text();
            let messages;
            
            try {
                messages = JSON.parse(textResponse);
            } catch (e) { 
                 return;
            }
            
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

            // NOUVEAU : On récupère les messages marqués comme importants dans le stockage du navigateur
            let starredMsgs = JSON.parse(localStorage.getItem('mango_starred')) || [];
            let html = '';

            messages.forEach(msg => {
                const isSent = (msg.sender_id == CURRENT_USER_ID);
                const bubbleClass = isSent ? 'message-sent' : 'message-received';
                const isDeleted = msg.message.indexOf('🚫') !== -1;
                const isStarred = starredMsgs.includes(msg.id); // Vérifie si ce message est important
                
                let readStatus = '';
                if (isSent) {
                    readStatus = msg.is_read == 1 ? '<i class="fa-solid fa-check-double tick-read ms-1" title="Lu"></i>' : '<i class="fa-solid fa-check tick-sent ms-1" title="Envoyé"></i>';
                }

                let actionsMenu = '';
                let safeText = escapeHtml(msg.message).replace(/'/g, "\\'").replace(/"/g, '&quot;');
                
                if (!isDeleted) {
                    let menuItems = '';
                    
                    // Options COMMUNES (Copier, Répondre, Transférer)
                    menuItems += `<li><a class="dropdown-item py-2 text-dark" href="#" onclick="navigator.clipboard.writeText('${safeText}'); alert('Message copié !'); return false;"><i class="fa-regular fa-copy me-2 text-secondary"></i> Copier</a></li>`;
                    menuItems += `<li><a class="dropdown-item py-2 text-dark" href="#" onclick="replyToMessage('${safeText}'); return false;"><i class="fa-solid fa-reply me-2 text-secondary"></i> Répondre</a></li>`;
                    if (navigator.share) {
                        menuItems += `<li><a class="dropdown-item py-2 text-dark" href="#" onclick="navigator.share({title: 'MAN GO Message', text: '${safeText}'}); return false;"><i class="fa-solid fa-share me-2 text-secondary"></i> Transférer</a></li>`;
                    }
                    
                    // NOUVEAU : Option Important (Étoile)
                    let starText = isStarred ? "Retirer des favoris" : "Marquer comme important";
                    let starIcon = isStarred ? "fa-solid fa-star text-secondary" : "fa-regular fa-star text-warning";
                    menuItems += `<li><a class="dropdown-item py-2 text-dark fw-bold" href="#" onclick="toggleImportant(${msg.id}); return false;"><i class="${starIcon} me-2"></i> ${starText}</a></li>`;
                    
                    menuItems += `<li><hr class="dropdown-divider"></li>`;
                    
                    // Options SPÉCIFIQUES
                    if (isSent) {
                        menuItems += `<li><a class="dropdown-item text-danger py-2" href="#" onclick="deleteMessage(${msg.id}); return false;"><i class="fa-regular fa-trash-can me-2"></i> Supprimer</a></li>`;
                    } else {
                        menuItems += `<li><a class="dropdown-item py-2 text-dark" href="#" onclick="translateMessage(${msg.id}, '${safeText}'); return false;"><i class="fa-solid fa-language me-2 text-info"></i> Traduire</a></li>`;
                        menuItems += `<li><a class="dropdown-item text-danger py-2" href="#" onclick="alert('Message signalé à l\\'équipe de modération.'); return false;"><i class="fa-regular fa-flag me-2"></i> Signaler</a></li>`;
                    }

                    let chevronColor = isSent ? 'text-white-50' : 'text-secondary';
                    actionsMenu = `
                        <div class="dropdown" style="position: absolute; top: 5px; right: 8px;">
                            <button class="btn btn-sm p-0 border-0 ${chevronColor}" type="button" data-bs-toggle="dropdown">
                                <i class="fa-solid fa-chevron-down" style="font-size: 0.8rem;"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="font-size: 0.9rem; min-width: 220px; z-index: 1050; border-radius: 8px;">
                                ${menuItems}
                            </ul>
                        </div>
                    `;
                }

                let displayMessage = escapeHtml(msg.message);
                let customBubbleStyle = !isDeleted ? 'padding-right: 25px;' : '';
                
                // Transformation Carte GPS
                const locRegex = /📍 Ma position : https:\/\/www\.google\.com\/maps\?q=([0-9.-]+),([0-9.-]+)/;
                const match = msg.message.match(locRegex);
                
                if (match) {
                    const lat = match[1]; const lng = match[2];
                    displayMessage = `
                        <div class="shadow-sm mt-1" style="width: 240px; overflow: hidden; border-radius: 8px; background: #fff;">
                            <div style="padding: 6px; font-size: 0.75rem; color: #000; text-align: center; font-weight: bold; border-bottom: 1px solid #eee;">
                                <i class="fa-solid fa-location-dot text-danger me-1"></i> Position partagée
                            </div>
                            <iframe width="240" height="130" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q=${lat},${lng}&z=14&output=embed" style="display: block; border: none;"></iframe>
                            <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display: block; padding: 8px; text-align: center; text-decoration: none; font-size: 0.8rem; font-weight: bold; color: var(--mango-orange); background: #fff;">
                                Ouvrir dans Maps <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                            </a>
                        </div>
                    `;
                    customBubbleStyle += ' width: max-content; padding: 6px; padding-right: 28px;';
                } else {
                    displayMessage = `<div style="white-space: pre-wrap; word-break: break-word;">${displayMessage}</div>`;
                }

                // Affichage de l'étoile si le message est important
                let starBadge = isStarred ? '<div style="position:absolute; top:-10px; left:-10px; background:#fff; border-radius:50%; padding:2px; box-shadow:0 2px 4px rgba(0,0,0,0.1);"><i class="fa-solid fa-star text-warning"></i></div>' : '';

                // ==========================================================
                // FORMATAGE DU BLOC "RÉPONSE" (Style WhatsApp)
                // ==========================================================
                let replyBlock = '';
                
                // Détecte le séparateur qu'on a créé à l'envoi
                const quoteRegex = /\[QUOTE\](.*?)\[\/QUOTE\]/;
                const quoteMatch = displayMessage.match(quoteRegex);
                
                if (quoteMatch) {
                    const quotedText = quoteMatch[1];
                    // Retire la balise du message principal
                    displayMessage = displayMessage.replace(quoteRegex, ''); 
                    
                    // Crée le bloc visuel
                    replyBlock = `
                        <div class="mb-2 p-2 rounded" style="background-color: rgba(0,0,0,0.05); border-left: 4px solid var(--mango-orange); font-size: 0.85rem; color: #64748b;">
                            <div class="fw-bold mb-1" style="color: var(--mango-orange);"><i class="fa-solid fa-reply me-1"></i> Réponse :</div>
                            <div class="text-truncate" style="max-height: 40px; overflow: hidden;">${quotedText}</div>
                        </div>
                    `;
                }

                html += `
                    <div class="message-bubble ${bubbleClass} d-flex flex-column shadow-sm" id="msg-${msg.id}" style="${customBubbleStyle}">
                        ${starBadge}
                        ${actionsMenu}
                        
                        <!-- Le bloc de citation WhatsApp s'affiche ici s'il existe -->
                        ${replyBlock}
                        
                        ${displayMessage}
                        <div id="trans-${msg.id}"></div>
                        <div class="message-time mt-1">
                            <span class="ms-auto">${formatTime(msg.created_at)}</span>
                            ${readStatus}
                        </div>
                    </div>
                `;
            });
            
            // NOUVEAU : Logique du BIP SONORE
            if (messages.length > 0) {
                let latestMsg = messages[messages.length - 1];
                if (latestMsg.id > lastMessageId) {
                    if (lastMessageId !== 0 && latestMsg.sender_id != CURRENT_USER_ID && soundEnabled) {
                        notifSound.play().catch(e => console.log("Son bloqué par le navigateur"));
                    }
                    lastMessageId = latestMsg.id;
                }
            }

            if (html !== lastMessagesHtml) {
                messagesContainer.innerHTML = html;
                lastMessagesHtml = html;
                if (isScrolledToBottom) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            }
        } catch (error) {}
    }

    // ==========================================================
    // FONCTION : PRÉPARER UNE RÉPONSE (Style WhatsApp)
    // ==========================================================
    window.replyToMessage = function(text) {
        // Nettoyer le texte
        let cleanText = text.replace(/(\r\n|\n|\r)/gm, " ");
        let shortText = cleanText.length > 60 ? cleanText.substring(0, 60) + "..." : cleanText;
        
        // Afficher la zone de prévisualisation
        const previewContainer = document.getElementById('replyPreviewContainer');
        const previewText = document.getElementById('replyPreviewText');
        const hiddenInput = document.getElementById('quotedMessageInput');
        
        previewText.innerText = shortText;
        hiddenInput.value = shortText; // On stocke le texte pour l'envoi
        previewContainer.classList.remove('d-none');
        
        // Focus sur le champ principal
        document.getElementById('messageInput').focus();
    };

    // Annuler la réponse
    function cancelReply() {
        document.getElementById('replyPreviewContainer').classList.add('d-none');
        document.getElementById('quotedMessageInput').value = '';
    }

    function toggleNotif() {
        soundEnabled = !soundEnabled;
        const btn = document.getElementById('notifBtn');
        btn.innerHTML = soundEnabled ? '<i class="fa-solid fa-bell text-warning"></i>' : '<i class="fa-solid fa-bell-slash text-muted"></i>';
        btn.title = soundEnabled ? 'Désactiver le son' : 'Activer le son';
    }

    function toggleImportant(msgId) {
        let starred = JSON.parse(localStorage.getItem('mango_starred')) || [];
        if (starred.includes(msgId)) {
            starred = starred.filter(id => id !== msgId); // Retire
        } else {
            starred.push(msgId); // Ajoute
        }
        localStorage.setItem('mango_starred', JSON.stringify(starred));
        lastMessagesHtml = ''; // Force le rafraîchissement immédiat
        loadMessages(); 
    }

    function toggleDictation() {
        if (!recognition) {
            alert("Votre navigateur ne supporte pas la dictée vocale.");
            return;
        }
        if (isRecording) {
            recognition.stop();
        } else {
            recognition.start();
            isRecording = true;
            document.getElementById('micBtn').classList.add('recording');
        }
    }

    function toggleAttachmentMenu() {
        const menu = document.getElementById('attachmentMenu');
        menu.classList.toggle('d-none');
        menu.classList.toggle('d-flex');
    }

    function insertEmoji(emoji) {
        const input = document.getElementById('messageInput');
        input.value = input.value + emoji;
        input.focus();
    }

    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (!file) return;
        let fileType = 'Fichier';
        if (file.type.includes('image')) fileType = 'Image';
        if (file.type.includes('video')) fileType = 'Vidéo';
        document.getElementById('messageInput').value = `[📁 ${fileType} jointe : ${file.name}]`;
        toggleAttachmentMenu();
        alert("Envoi simulé : Le système Backend n'est pas encore configuré pour recevoir des fichiers.");
    }

    async function translateMessage(msgId, text) {
        const container = document.getElementById(`trans-${msgId}`);
        container.innerHTML = '<small class="text-warning"><i class="fa-solid fa-spinner fa-spin"></i> Traduction...</small>';
        try {
            const url = `https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=fr&dt=t&q=${encodeURI(text)}`;
            const res = await fetch(url);
            const data = await res.json();
            const translatedText = data[0].map(item => item[0]).join('');
            container.innerHTML = `<hr class="my-1 border-secondary opacity-25"><small class="text-info fw-bold"><i class="fa-solid fa-language me-1"></i> Traduit : ${escapeHtml(translatedText)}</small>`;
        } catch (e) {
            container.innerHTML = '<small class="text-danger">Erreur de traduction.</small>';
        }
    }

    async function handleSendMessage(event) {
        event.preventDefault();
        const input = document.getElementById('messageInput');
        const hiddenQuoteInput = document.getElementById('quotedMessageInput');
        const sendBtn = document.getElementById('sendBtn');
        let message = input.value.trim();
        
        if (!message) return;
        if (!activeReceiverId) return;

        // Si une citation est préparée, on la formate pour le backend
        if (hiddenQuoteInput && hiddenQuoteInput.value) {
            // On utilise un séparateur unique [QUOTE]...[/QUOTE]
            message = `[QUOTE]${hiddenQuoteInput.value}[/QUOTE]\n${message}`;
            cancelReply(); // On cache la petite fenêtre de prévisualisation
        }

        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('receiver_id', activeReceiverId);
        formData.append('message', message);
        
        input.value = '';

        try {
            const response = await fetch('api/chat.php', { method: 'POST', body: formData });
            const textResult = await response.text();
            try {
                const result = JSON.parse(textResult);
                if (result.status === 'success') {
                    await loadMessages();
                    const messagesContainer = document.getElementById('chatMessages');
                    messagesContainer.scrollTop = messagesContainer.scrollHeight; 
                    loadInbox();
                } else {
                    alert(result.message ? result.message : "Erreur lors de l'envoi.");
                }
            } catch (parseError) {
                alert("Erreur serveur : " + textResult);
            }
        } catch (error) { 
            alert("Erreur de connexion au serveur.");
        } finally {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
            input.focus();
        }
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
        } catch (error) {}
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

    // Générer la géolocalisation SANS envoyer
    function sendLocation() {
        if (!navigator.geolocation) {
            alert("La géolocalisation n'est pas supportée par votre appareil/navigateur.");
            return;
        }

        const input = document.getElementById('messageInput');
        const originalText = input.value; // On garde ce que l'utilisateur avait déjà écrit
        input.value = "📍 Calcul de la position en cours...";
        toggleAttachmentMenu(); // Ferme le menu

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const mapLink = `📍 Ma position : https://www.google.com/maps?q=${lat},${lng}`;
                
                // Remplace le texte d'attente par le vrai lien, sans effacer le reste
                input.value = originalText + (originalText ? " " : "") + mapLink;
                input.focus();
            },
            (error) => {
                input.value = originalText; // On remet le texte d'origine
                let msg = "Impossible d'obtenir votre position. ";
                if(error.code === 1) msg += "Vous avez refusé l'accès au GPS.";
                alert(msg);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    async function checkOnlineStatus() {
        if (!activeReceiverId) return;
        try {
            const res = await fetch(`api/chat.php?action=status&id=${activeReceiverId}`);
            const data = await res.json();
            const roleEl = document.getElementById('activeContactRole');
            if (data.online) {
                roleEl.innerHTML = '<span class="text-success fw-bold"><i class="fa-solid fa-circle" style="font-size:0.5rem; margin-right:4px; vertical-align:middle;"></i> En ligne</span>';
            } else {
                roleEl.innerHTML = 'Membre MAN GO';
            }
        } catch(e) {}
    }

</script>
</body>
</html>