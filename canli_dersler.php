<?php
// canli_dersler.php (ATLA PALETİ: MAVİ & TURUNCU KONSEPT)
require_once 'db.php';
include 'header.php';

$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] == 'student';
$user_id = $_SESSION['user_id'] ?? 0;

// SQL Optimizasyonu
$sql = "
    SELECT u.*, 
    (
        SELECT COUNT(*) 
        FROM live_chat_messages m 
        WHERE m.sender_id = u.id 
          AND m.receiver_id = :student_id 
          AND m.is_read = 0
    ) as unread_count
    FROM users u 
    WHERE u.role = 'teacher' 
    AND u.is_public_instructor = 1  
    ORDER BY u.branch ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['student_id' => $user_id]);
$teachers = $stmt->fetchAll();

$branches = array_unique(array_column($teachers, 'branch'));
sort($branches);
?>

<style>
    /* ATLA Palette Colors */
    .bg-atla-blue-light { background-color: #314595; }
    .bg-atla-blue-dark { background-color: #223488; }
    .bg-atla-orange { background-color: #ec9731; }
    .text-atla-blue-dark { color: #223488; }
    .text-atla-orange { color: #ec9731; }
    
    .border-atla-blue { border-color: #314595; }
    
    /* Hover States */
    .hover-bg-atla-blue-dark:hover { background-color: #223488; }
    .hover-bg-atla-orange-dark:hover { background-color: #d68625; }
</style>

<div class="bg-slate-50 min-h-screen font-sans pb-20">
    <div class="max-w-7xl mx-auto px-4 py-16">
        
        <div class="text-center mb-14">
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">
                <span class="text-red-600">Özel Ders</span> 
                <span class="text-slate-900">Eğitmenleri</span>
            </h1>
            <p class="text-slate-500 text-lg max-w-2xl mx-auto">
                Hedeflerinize ulaşmak için uzman eğitmenlerimizle hemen iletişime geçin.
            </p>
        </div>

        <div class="flex flex-wrap justify-center gap-3 mb-16">
            <button onclick="filterTeachers('all')" class="filter-btn active transition-all duration-300 bg-atla-blue-dark text-white px-8 py-2.5 rounded-full font-bold shadow-lg shadow-slate-200 hover:shadow-xl hover:-translate-y-0.5">Tümü</button>
            <?php foreach($branches as $branch): if(empty($branch)) continue; ?>
            <button onclick="filterTeachers('<?= htmlspecialchars($branch); ?>')" class="filter-btn transition-all duration-300 bg-white text-slate-600 border border-slate-200 px-6 py-2.5 rounded-full font-semibold hover:bg-indigo-50 hover:text-atla-blue-dark hover:border-atla-blue"><?= htmlspecialchars($branch); ?></button>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php foreach ($teachers as $t): 
                $b_raw = $t['branch'] ? $t['branch'] : 'Genel';
                $initial = strtoupper(substr($t['first_name'], 0, 1));
                $unread = (int)$t['unread_count'];
            ?>
            <div class="teacher-card group relative bg-white rounded-[2rem] p-6 border border-slate-100 shadow-[0_10px_40px_-10px_rgba(0,0,0,0.05)] hover:shadow-[0_20px_40px_-10px_rgba(49,69,149,0.15)] transition-all duration-300 flex flex-col items-center text-center hover:-translate-y-2" data-category="<?= htmlspecialchars($b_raw); ?>">
                
                <div class="relative mb-3 group-hover:scale-105 transition-transform duration-300">
                    <div class="w-32 h-32 rounded-full p-1 bg-gradient-to-tr from-[#223488] via-[#314595] to-blue-300 shadow-lg">
                        <div class="w-full h-full rounded-full bg-white p-1 overflow-hidden relative">
                            <?php if(!empty($t['photo_path'])): ?>
                                <img src="<?= htmlspecialchars($t['photo_path']); ?>" class="w-full h-full rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full rounded-full bg-slate-50 flex items-center justify-center text-4xl font-black text-slate-300"><?= $initial; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <span class="inline-block bg-indigo-50 text-atla-blue-dark text-xs font-bold px-4 py-1.5 rounded-full uppercase tracking-wider border border-indigo-100"><?= htmlspecialchars($b_raw); ?></span>
                </div>

                <h3 class="text-xl font-bold text-slate-900 mb-2 leading-tight"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></h3>
                
                <p class="text-slate-500 text-sm mb-8 line-clamp-3 min-h-[60px] leading-relaxed px-2">
                    <?= !empty($t['bio']) ? htmlspecialchars($t['bio']) : 'Öğrenci başarısı odaklı, kişiye özel eğitim programları ve özel dersler ile hedeflerinize ulaşın.'; ?>
                </p>

                <div class="mt-auto w-full">
                    <?php if($is_logged_in): ?>
                        <button onclick="openChatModal(<?= $t['id']; ?>, '<?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>')" 
                           class="relative flex items-center justify-center w-full gap-2 bg-atla-blue-light hover-bg-atla-blue-dark text-white font-bold py-3.5 px-6 rounded-2xl shadow-lg shadow-indigo-200 transition-all duration-300 active:scale-95">
                            <span>💬 Mesaj Gönder</span>
                            
                            <?php if($unread > 0): ?>
                                <span class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-atla-orange text-xs text-white font-bold ring-4 ring-white shadow-sm animate-pulse">
                                    <?= $unread ?>
                                </span>
                            <?php endif; ?>
                        </button>
                    <?php else: ?>
                        <button onclick="alert('Mesaj göndermek için lütfen giriş yapınız.')" 
                                class="w-full bg-slate-100 text-slate-400 font-bold py-3.5 px-6 rounded-2xl border border-slate-200 cursor-not-allowed hover:bg-slate-200 transition-colors">
                            🔒 Giriş Yapmalısın
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div id="chatModal" class="fixed inset-0 z-[100] hidden items-end md:items-center justify-center bg-slate-900/60 backdrop-blur-sm sm:p-4 transition-all duration-300">
    <div class="bg-white w-full md:max-w-lg rounded-t-3xl md:rounded-3xl shadow-2xl overflow-hidden flex flex-col h-[85vh] md:h-[600px] animate-in slide-in-from-bottom-10 fade-in duration-300">
        
        <div class="bg-gradient-to-r from-[#223488] to-[#314595] p-4 flex justify-between items-center text-white shrink-0 shadow-md z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-white/10 backdrop-blur-md flex items-center justify-center font-bold text-lg shadow-inner border border-white/10">
                    👨‍🏫
                </div>
                <div>
                    <h3 id="chatTeacherName" class="font-bold text-lg leading-tight tracking-wide">Öğretmen Adı</h3>
                    <div class="flex items-center gap-1.5 opacity-90">
                        <span class="w-2 h-2 bg-atla-orange rounded-full animate-pulse"></span>
                        <p class="text-blue-100 text-xs font-medium">Çevrimiçi</p>
                    </div>
                </div>
            </div>
            <button onclick="closeChatModal()" class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition text-white/90 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div id="chatMessages" class="flex-grow overflow-y-auto p-5 bg-slate-50 space-y-4 scroll-smooth">
            <div class="text-center text-slate-400 text-sm mt-10">Sohbet yükleniyor...</div>
        </div>

        <div class="p-3 md:p-4 bg-white border-t border-slate-100 shrink-0 pb-safe">
            <form id="chatForm" onsubmit="sendMessage(event)" class="flex gap-2 items-center">
                <input type="hidden" id="chatTeacherId" value="">
                
                <input type="text" id="chatInput" class="flex-grow bg-slate-100 border-transparent focus:bg-white focus:border-[#314595] focus:ring-0 rounded-2xl px-5 py-3.5 text-slate-700 placeholder-slate-400 font-medium transition-all shadow-inner" placeholder="Mesajınızı yazın..." required autocomplete="off">
                
                <button type="submit" class="w-12 h-12 flex items-center justify-center bg-atla-orange hover-bg-atla-orange-dark text-white rounded-full font-bold transition-all shadow-lg shadow-orange-200 active:scale-90 active:shadow-none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 ml-0.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
    .pb-safe { padding-bottom: env(safe-area-inset-bottom, 1rem); }
    #chatMessages::-webkit-scrollbar { width: 5px; }
    #chatMessages::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    #chatMessages::-webkit-scrollbar-track { background: transparent; }
</style>

<script>
let currentTeacherId = null;
let chatInterval = null;

function filterTeachers(category) {
    const cards = document.querySelectorAll('.teacher-card');
    cards.forEach(card => {
        if (category === 'all' || card.getAttribute('data-category') === category) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function openChatModal(teacherId, teacherName) {
    currentTeacherId = teacherId;
    document.getElementById('chatTeacherId').value = teacherId;
    document.getElementById('chatTeacherName').textContent = teacherName;
    
    const modal = document.getElementById('chatModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    loadMessages();
    chatInterval = setInterval(loadMessages, 3000); 
}

function closeChatModal() {
    const modal = document.getElementById('chatModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    clearInterval(chatInterval);
    currentTeacherId = null;
}

async function loadMessages() {
    if(!currentTeacherId) return;
    try {
        const res = await fetch(`chat_api.php?action=get_messages&user_id=${currentTeacherId}`);
        const data = await res.json();
        
        if(data.ok) {
            const container = document.getElementById('chatMessages');
            
            if(data.messages.length === 0) {
                container.innerHTML = `<div class="flex flex-col items-center justify-center h-full text-slate-400 space-y-2">
                    <span class="text-4xl">👋</span>
                    <p class="text-sm">Henüz mesaj yok.<br>İlk mesajı sen gönder!</p>
                </div>`;
                return;
            }

            const html = data.messages.map(m => {
                const isMe = m.type === 'me';
                
                // GRAFİK TASARIM DOKUNUŞU:
                // Ben (Öğrenci): Turuncu (Paletteki #ec9731) - Enerjik ve dikkat çekici
                // Karşı (Öğretmen): Mavi (Paletteki #314595) - Kurumsal ve sakin
                
                return `
                    <div class="flex ${isMe ? 'justify-end' : 'justify-start'} mb-2">
                        <div class="max-w-[75%] px-4 py-3 text-[15px] leading-relaxed shadow-sm
                            ${isMe 
                                ? 'bg-[#ec9731] text-white rounded-[20px] rounded-tr-sm'  // ATLA TURUNCU
                                : 'bg-[#314595] text-white rounded-[20px] rounded-tl-sm'  // ATLA MAVİ
                            }">
                            ${escapeHtml(m.message)}
                            <div class="text-[10px] ${isMe ? 'text-orange-100' : 'text-indigo-200'} mt-1 text-right font-medium opacity-80">
                                ${formatTime(m.created_at)}
                                ${isMe ? '<span>✓</span>' : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            if(container.innerHTML !== html) {
                const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
                container.innerHTML = html;
                if(isAtBottom) container.scrollTop = container.scrollHeight;
            }
        }
    } catch(e) { console.error(e); }
}

async function sendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if(!msg) return;

    const formData = new FormData();
    formData.append('receiver_id', currentTeacherId);
    formData.append('message', msg);

    input.value = '';
    
    try {
        const res = await fetch('chat_api.php?action=send_message', { method: 'POST', body: formData });
        const data = await res.json();
        if(data.ok) {
            loadMessages(); 
        } else {
            alert(data.error || 'Hata oluştu');
        }
    } catch(e) { console.error(e); }
}

function escapeHtml(text) {
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
function formatTime(dateString) {
    const d = new Date(dateString);
    return d.toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'});
}
</script>

<?php include 'footer.php'; ?>