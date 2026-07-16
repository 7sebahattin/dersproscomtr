<?php
// online_dersler.php (ATLA PALETİ - Öğretmen Paneli - Full Sürüm)
require_once 'db.php';
include 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo "<script>window.location.href='index.php';</script>"; exit;
}
?>

<style>
    /* Temel Renkler */
    .bg-atla-blue { background-color: #314595; }
    .text-atla-blue { color: #314595; }
    .border-atla-blue { border-color: #314595; }
    
    .bg-atla-orange { background-color: #ec9731; }
    .text-atla-orange { color: #ec9731; }
    .border-atla-orange { border-color: #ec9731; }

    /* Hover Durumları */
    .hover-bg-atla-blue-dark:hover { background-color: #223488; }
    
    /* iOS Safari alt bar ve Scrollbar düzeltmeleri */
    .pb-safe { padding-bottom: env(safe-area-inset-bottom, 20px); }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }

    /* Silme Butonu Animasyonu */
    .message-group .delete-btn { opacity: 0; transition: opacity 0.2s ease-in-out; }
    .message-group:hover .delete-btn { opacity: 1; }
    @media (max-width: 768px) {
        .message-group .delete-btn { opacity: 1; } 
    }
</style>

<div class="bg-slate-50 h-[calc(100vh-80px)] md:h-[calc(100vh-80px)] overflow-hidden font-sans">
    <div class="h-full max-w-[1600px] mx-auto p-0 md:p-4 flex gap-4">
        
        <div id="sidebarArea" class="w-full md:w-96 bg-white md:rounded-3xl shadow-none md:shadow-xl border-r md:border border-slate-200 flex flex-col overflow-hidden shrink-0 h-full">
            <div class="p-5 bg-slate-50 border-b border-slate-100 flex justify-between items-center shrink-0">
                <div>
                    <h2 class="font-black text-slate-800 text-xl">Mesajlar</h2>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Kalan:</span>
                        <span id="creditDisplay" class="bg-orange-50 text-atla-orange px-3 py-1 rounded-full text-xs font-black border border-orange-200">Yükleniyor...</span>
                    </div>
                </div>
            </div>

            <div id="conversationList" class="flex-grow overflow-y-auto custom-scrollbar pb-20 md:pb-0">
                <div class="p-10 text-center text-slate-400 text-sm">Sohbetler yükleniyor...</div>
            </div>
        </div>

        <div id="chatArea" class="hidden md:flex flex-1 bg-white md:rounded-3xl shadow-none md:shadow-xl border-l md:border border-slate-200 flex-col overflow-hidden relative h-full w-full">
            
            <div id="emptyState" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-50 text-slate-400">
                <div class="text-6xl mb-4">💬</div>
                <p class="font-bold text-lg">Bir öğrenci seçin</p>
            </div>

            <div id="chatContent" class="hidden flex-col h-full w-full relative">
                
                <div class="h-16 border-b border-slate-100 flex items-center justify-between px-4 md:px-6 bg-white shrink-0 z-20 shadow-sm md:shadow-none">
                    <div class="flex items-center gap-3">
                        <button onclick="backToList()" class="md:hidden w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                            </svg>
                        </button>
                        
                        <div id="activeUserInitial" class="w-10 h-10 rounded-full bg-blue-100 text-atla-blue flex items-center justify-center font-bold shrink-0">A</div>
                        <div class="min-w-0">
                            <h3 id="activeUserName" class="font-bold text-slate-800 truncate">Öğrenci Adı</h3>
                            <p class="text-xs text-slate-500">Öğrenci</p>
                        </div>
                    </div>

                    <button onclick="clearConversation()" class="group flex items-center gap-2 text-slate-400 hover:text-red-500 hover:bg-red-50 px-3 py-2 rounded-lg transition-colors text-sm font-bold" title="Tüm Sohbeti Temizle">
                        <span class="hidden md:inline">Temizle</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </div>

                <div id="messagesContainer" class="flex-grow overflow-y-auto p-4 md:p-6 space-y-4 bg-slate-50/50 scroll-smooth"></div>

                <div id="lockedOverlay" class="absolute inset-0 z-30 bg-white/80 backdrop-blur-sm flex items-center justify-center hidden p-4">
                    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-2xl border border-slate-200 text-center max-w-sm w-full">
                        <div class="text-4xl mb-4">🔒</div>
                        <h3 class="font-black text-slate-800 text-xl mb-2">Sohbet Kilitli</h3>
                        <p class="text-slate-500 mb-6 text-sm">Mesajı görmek ve cevaplamak için kredi kullanın.</p>
                        <button onclick="unlockStudent()" class="w-full bg-atla-blue hover-bg-atla-blue-dark text-white font-bold py-3 rounded-xl shadow-lg shadow-blue-200 transition transform active:scale-95">
                            🔓 Kilidi Aç (1 Kredi)
                        </button>
                        <p id="unlockError" class="text-red-500 text-xs font-bold mt-3 hidden"></p>
                    </div>
                </div>

                <div id="inputArea" class="p-3 md:p-4 bg-white border-t border-slate-100 shrink-0 w-full z-20 pb-safe">
                    <form onsubmit="sendReply(event)" class="flex gap-2">
                        <input type="text" id="replyInput" class="flex-grow bg-slate-100 border-transparent focus:bg-white focus:border-atla-blue focus:ring-0 rounded-xl px-4 py-3 text-slate-700 placeholder-slate-400 font-medium transition-all text-sm md:text-base" placeholder="Mesaj yazın..." autocomplete="off">
                        <button type="submit" class="bg-atla-blue hover-bg-atla-blue-dark text-white rounded-xl px-4 md:px-6 font-bold transition-colors flex items-center justify-center shadow-md shadow-blue-100">
                            <span class="md:hidden">➤</span>
                            <span class="hidden md:inline">Gönder</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedStudentId = null;
let myCredits = 0;
let refreshInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    loadConversations();
    refreshInterval = setInterval(loadConversations, 5000);
});

// --- MOBİL NAVİGASYON ---
function backToList() {
    const sidebar = document.getElementById('sidebarArea');
    const chatArea = document.getElementById('chatArea');
    sidebar.classList.remove('hidden');
    chatArea.classList.add('hidden');
    chatArea.classList.remove('flex');
}

function switchToChatView() {
    const sidebar = document.getElementById('sidebarArea');
    const chatArea = document.getElementById('chatArea');
    if (window.innerWidth < 768) {
        sidebar.classList.add('hidden');
        chatArea.classList.remove('hidden');
        chatArea.classList.add('flex');
    } else {
        chatArea.classList.remove('hidden');
        chatArea.classList.add('flex');
    }
}

async function loadConversations() {
    try {
        const res = await fetch('chat_api.php?action=get_conversations');
        const data = await res.json();
        
        if(data.ok) {
            myCredits = parseInt(data.credits);
            document.getElementById('creditDisplay').textContent = myCredits + " Kredi";
            renderList(data.users);
            
            if(selectedStudentId) {
                loadMessages(selectedStudentId, false); 
            }
        }
    } catch(e) { console.error(e); }
}

function renderList(users) {
    const list = document.getElementById('conversationList');
    
    if(users.length === 0) {
        list.innerHTML = '<div class="p-10 text-center text-slate-400 text-sm">Henüz mesaj yok.</div>';
        return;
    }

    const html = users.map(u => {
        const isActive = selectedStudentId == u.id;
        const isUnread = parseInt(u.unread_count) > 0;
        const isLocked = parseInt(u.is_unlocked) === 0;
        
        return `
        <div onclick="selectUser(${u.id}, '${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)}', ${isLocked})" 
             class="p-4 border-b border-slate-50 cursor-pointer hover:bg-slate-50 transition ${isActive ? 'bg-blue-50 border-blue-100' : 'bg-white'}">
            <div class="flex items-center gap-3">
                <div class="relative shrink-0">
                    ${u.photo_path
                        ? `<img src="${escapeHtml(u.photo_path)}" class="w-12 h-12 rounded-full object-cover">`
                        : `<div class="w-12 h-12 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center font-bold text-lg">${u.first_name[0]}</div>`
                    }
                    ${isLocked ? '<div class="absolute -bottom-1 -right-1 bg-atla-orange text-white rounded-full p-1 text-[10px] border-2 border-white">🔒</div>' : ''}
                </div>
                <div class="flex-grow min-w-0">
                    <div class="flex justify-between items-baseline mb-1">
                        <h4 class="font-bold text-slate-800 truncate ${isUnread ? 'text-atla-blue' : ''}">${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)}</h4>
                        <span class="text-[10px] text-slate-400 whitespace-nowrap ml-2">${formatDate(u.last_date)}</span>
                    </div>
                    <p class="text-sm truncate ${isUnread ? 'font-bold text-slate-800' : 'text-slate-500'}">
                        ${isLocked ? '🔒 1 Yeni Mesaj (Kilitli)' : escapeHtml(u.last_message)}
                    </p>
                </div>
                ${isUnread ? `<div class="w-2.5 h-2.5 rounded-full bg-atla-orange shrink-0 ring-2 ring-white"></div>` : ''}
            </div>
        </div>
        `;
    }).join('');
    
    if(list.innerHTML !== html) {
        list.innerHTML = html;
    }
}

function selectUser(id, name, isLocked) {
    selectedStudentId = id;
    document.getElementById('emptyState').classList.add('hidden');
    const chatContent = document.getElementById('chatContent');
    chatContent.classList.remove('hidden');
    chatContent.classList.add('flex');
    document.getElementById('activeUserName').textContent = name;
    document.getElementById('activeUserInitial').textContent = name[0];
    switchToChatView();
    document.getElementById('messagesContainer').innerHTML = '<div class="text-center text-slate-400 mt-10">Yükleniyor...</div>';
    loadMessages(id, true);
}

async function loadMessages(userId, scrollToBottom = false) {
    try {
        const res = await fetch(`chat_api.php?action=get_messages&user_id=${userId}`);
        const data = await res.json();
        
        if(data.ok) {
            const container = document.getElementById('messagesContainer');
            const overlay = document.getElementById('lockedOverlay');
            const inputArea = document.getElementById('inputArea');

            if(data.is_locked) {
                overlay.classList.remove('hidden');
                container.innerHTML = '<div class="filter blur-sm select-none opacity-50 space-y-4 pointer-events-none">' + 
                    renderMessagesHTML(data.messages) + 
                '</div>';
                inputArea.classList.add('opacity-50', 'pointer-events-none');
            } else {
                overlay.classList.add('hidden');
                inputArea.classList.remove('opacity-50', 'pointer-events-none');
                
                const html = renderMessagesHTML(data.messages);
                if(container.innerHTML !== html) {
                     container.innerHTML = html;
                     if(scrollToBottom) {
                        setTimeout(() => { container.scrollTop = container.scrollHeight; }, 50);
                    } else {
                         const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 200;
                         if(isAtBottom) container.scrollTop = container.scrollHeight;
                    }
                }
            }
        }
    } catch(e) { console.error(e); }
}

function renderMessagesHTML(messages) {
    if(!messages || messages.length === 0) return '<div class="text-center text-slate-300 text-sm mt-10">Sohbet başlangıcı</div>';
    
    return messages.map(m => {
        const isMe = m.type === 'me';
        const msgId = m.id || 0; 

        return `
            <div id="msg-${msgId}" class="flex ${isMe ? 'justify-end' : 'justify-start'} group message-group">
                <div class="relative max-w-[85%] md:max-w-[70%]">
                    <div class="p-3 rounded-2xl text-sm leading-relaxed break-words shadow-sm ${isMe ? 'bg-atla-blue text-white rounded-tr-none' : 'bg-white border border-slate-200 text-slate-800 rounded-tl-none'}">
                        ${escapeHtml(m.message)}
                        <div class="flex items-center justify-end gap-1 mt-1">
                            ${isMe ? `
                            <button onclick="deleteMessage(${msgId})" class="delete-btn md:opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded hover:bg-black/10" title="Mesajı Sil">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 ${isMe ? 'text-blue-200' : 'text-slate-400'}">
                                  <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 001.5.06l.3-7.5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            ` : ''}
                            <div class="text-[10px] ${isMe ? 'text-blue-200' : 'text-slate-400'}">${formatTime(m.created_at)}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// --- YENİ EKLENEN FONKSİYONLAR ---

// 1. KOMPLE SOHBET TEMİZLEME
async function clearConversation() {
    if(!selectedStudentId) return;
    
    // Güvenlik Onayı
    if(!confirm("Bu öğrenciyle olan TÜM sohbet geçmişiniz silinecek.\nBu işlem geri alınamaz!\n\nDevam etmek istiyor musunuz?")) return;

    const formData = new FormData();
    formData.append('partner_id', selectedStudentId);

    try {
        const res = await fetch('chat_api.php?action=clear_conversation', { method: 'POST', body: formData });
        const data = await res.json();
        
        if(data.ok) {
            // Arayüzü temizle
            document.getElementById('messagesContainer').innerHTML = '<div class="text-center text-slate-300 text-sm mt-10">Sohbet temizlendi.</div>';
            loadConversations(); // Listeyi güncelle (son mesajı boşaltmak için)
        } else {
            alert(data.error || "Temizleme işlemi başarısız.");
        }
    } catch(e) { console.error(e); }
}

// 2. TEK MESAJ SİLME
async function deleteMessage(msgId) {
    if(!confirm("Bu mesajı silmek istediğinize emin misiniz?")) return;

    const formData = new FormData();
    formData.append('message_id', msgId);

    const msgElement = document.getElementById(`msg-${msgId}`);
    if(msgElement) msgElement.style.opacity = '0.5';

    try {
        const res = await fetch('chat_api.php?action=delete_message', { method: 'POST', body: formData });
        const data = await res.json();
        
        if(data.ok) {
            if(msgElement) msgElement.remove();
            loadConversations();
        } else {
            alert(data.error || "Silme işlemi başarısız.");
            if(msgElement) msgElement.style.opacity = '1';
        }
    } catch(e) {
        console.error(e);
        if(msgElement) msgElement.style.opacity = '1';
    }
}

async function unlockStudent() {
    if(!selectedStudentId) return;
    if(myCredits < 1) {
        document.getElementById('unlockError').textContent = "Yetersiz kredi!";
        document.getElementById('unlockError').classList.remove('hidden');
        return;
    }
    if(!confirm("1 Kredi harcansın mı?")) return;

    const formData = new FormData();
    formData.append('student_id', selectedStudentId);

    try {
        const res = await fetch('chat_api.php?action=unlock_student', { method: 'POST', body: formData });
        const data = await res.json();
        if(data.ok) {
            myCredits = data.new_credits;
            document.getElementById('creditDisplay').textContent = myCredits + " Kredi";
            document.getElementById('lockedOverlay').classList.add('hidden');
            loadConversations(); 
            loadMessages(selectedStudentId, true); 
        } else {
            document.getElementById('unlockError').textContent = data.error;
            document.getElementById('unlockError').classList.remove('hidden');
        }
    } catch(e) { console.error(e); }
}

async function sendReply(e) {
    e.preventDefault();
    const input = document.getElementById('replyInput');
    const msg = input.value.trim();
    if(!msg || !selectedStudentId) return;

    const formData = new FormData();
    formData.append('receiver_id', selectedStudentId);
    formData.append('message', msg);

    input.value = '';
    
    try {
        const res = await fetch('chat_api.php?action=send_message', { method: 'POST', body: formData });
        const data = await res.json();
        if(data.ok) {
            loadMessages(selectedStudentId, true);
        } else {
            alert(data.error);
        }
    } catch(e) { console.error(e); }
}

function escapeHtml(text) {
    return (text || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
function formatTime(dateString) {
    return new Date(dateString).toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'});
}
function formatDate(dateString) {
    const d = new Date(dateString);
    const today = new Date();
    if(d.toDateString() === today.toDateString()) return d.toLocaleTimeString('tr-TR', {hour:'2-digit', minute:'2-digit'});
    return d.toLocaleDateString('tr-TR', {day:'numeric', month:'short'});
}
</script>

<?php include 'footer.php'; ?>