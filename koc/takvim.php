<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) { die("Giriş yapmalısınız."); }

// Öğrencileri çek
$students = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'student' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4f46e5">
    <link rel="manifest" href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/manifest.json">
    <title>Koçluk Asistanı</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; -webkit-tap-highlight-color: transparent; }
        
        /* FullCalendar Mobil Ayarları */
        .fc-toolbar-title { font-size: 1.1rem !important; font-weight: 700; color: #1e293b; }
        .fc-header-toolbar { margin-bottom: 10px !important; padding: 0 10px; }
        .fc-button { background: transparent !important; border: none !important; color: #64748b !important; box-shadow: none !important; }
        .fc-button-active { color: #4f46e5 !important; font-weight: bold; }
        .fc-day-today { background: #eff6ff !important; }
        .fc-event { border-radius: 6px; padding: 2px 4px; border: none; font-size: 0.75rem; margin-bottom: 2px; }
        
        /* Bottom Sheet Animasyonları */
        .bottom-sheet { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); transform: translateY(100%); }
        .bottom-sheet.open { transform: translateY(0); }
        .overlay { transition: opacity 0.3s; opacity: 0; pointer-events: none; }
        .overlay.open { opacity: 1; pointer-events: auto; }

        /* Floating Action Button */
        .fab { box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4); }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden text-slate-800">

    <header class="bg-white border-b border-slate-100 h-14 flex items-center justify-between px-4 shrink-0 z-10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                <?php echo substr($_SESSION['username'] ?? 'K', 0, 1); ?>
            </div>
            <h1 class="font-bold text-lg text-slate-800 tracking-tight">Koçluk</h1>
        </div>
        <div class="flex gap-2">
            <button onclick="changeView('dayGridMonth')" class="p-2 text-slate-400 hover:text-indigo-600"><i class="fa-solid fa-calendar"></i></button>
            <button onclick="changeView('listWeek')" class="p-2 text-slate-400 hover:text-indigo-600"><i class="fa-solid fa-list-ul"></i></button>
        </div>
    </header>

    <div class="bg-white py-3 px-4 shadow-sm shrink-0 z-10 overflow-x-auto whitespace-nowrap custom-scrollbar">
        <button onclick="filterStudent('all', this)" class="student-filter active inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-bold bg-slate-900 text-white mr-2 transition-all">
            <i class="fa-solid fa-users"></i> Tümü
        </button>
        <?php foreach($students as $s): ?>
        <button onclick="filterStudent(<?php echo $s['id']; ?>, this)" class="student-filter inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-500 mr-2 border border-slate-200 transition-all">
            <?php echo $s['first_name']; ?>
        </button>
        <?php endforeach; ?>
    </div>

    <main class="flex-grow relative overflow-y-auto bg-white">
        <div id='calendar' class="h-full pb-20"></div>
    </main>

    <button onclick="openSheet()" class="fab fixed bottom-6 right-6 w-14 h-14 bg-indigo-600 text-white rounded-full flex items-center justify-center text-2xl z-20 active:scale-95 transition-transform">
        <i class="fa-solid fa-plus"></i>
    </button>

    <div id="overlay" onclick="closeSheet()" class="overlay fixed inset-0 bg-black/60 z-30 backdrop-blur-sm"></div>

    <div id="bottomSheet" class="bottom-sheet fixed bottom-0 left-0 right-0 bg-white z-40 rounded-t-3xl shadow-[0_-5px_20px_rgba(0,0,0,0.1)] h-[85vh] flex flex-col md:max-w-md md:mx-auto md:h-auto md:bottom-4 md:rounded-3xl">
        
        <div class="w-full h-8 flex items-center justify-center shrink-0 cursor-pointer" onclick="closeSheet()">
            <div class="w-12 h-1.5 bg-slate-200 rounded-full"></div>
        </div>

        <div class="p-6 pt-0 flex-grow overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-slate-800" id="sheetTitle">Yeni Randevu</h3>
                <button onclick="closeSheet()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form id="eventForm" class="space-y-5">
                <input type="hidden" id="eventId">
                
                <div class="relative">
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 block">Öğrenci</label>
                    <div class="relative">
                        <select id="modalStudent" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block p-3.5 appearance-none font-medium">
                            <?php foreach($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo $s['first_name'] . ' ' . $s['last_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 pointer-events-none"></i>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 block">Başlangıç</label>
                        <input type="datetime-local" id="modalStart" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm font-medium">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 block">Bitiş</label>
                        <input type="datetime-local" id="modalEnd" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm font-medium">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 block">Özel Notlar</label>
                    <textarea id="modalNote" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm font-medium placeholder-slate-400" placeholder="Bu derste ne işlendi?"></textarea>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 block">Durum</label>
                    <div class="flex gap-2">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="status" value="active" class="peer hidden" checked>
                            <div class="text-center py-2 rounded-lg bg-slate-50 border border-slate-200 text-slate-600 peer-checked:bg-indigo-50 peer-checked:border-indigo-500 peer-checked:text-indigo-600 text-sm font-bold transition">Aktif</div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="status" value="completed" class="peer hidden">
                            <div class="text-center py-2 rounded-lg bg-slate-50 border border-slate-200 text-slate-600 peer-checked:bg-green-50 peer-checked:border-green-500 peer-checked:text-green-600 text-sm font-bold transition">Tamamlandı</div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="status" value="cancelled" class="peer hidden">
                            <div class="text-center py-2 rounded-lg bg-slate-50 border border-slate-200 text-slate-600 peer-checked:bg-red-50 peer-checked:border-red-500 peer-checked:text-red-600 text-sm font-bold transition">İptal</div>
                        </label>
                    </div>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" id="btnDelete" class="hidden w-12 h-12 rounded-xl bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-100 transition"><i class="fa-solid fa-trash"></i></button>
                    <button type="button" id="btnSave" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-indigo-200 transition active:scale-95">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    // Mobil uyumlu FullCalendar
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth', // Mobilde Liste, PC'de Takvim başla
        headerToolbar: { left: 'prev,next', center: 'title', right: '' },
        locale: 'tr',
        firstDay: 1, // Pazartesi
        height: '100%',
        selectable: true,
        editable: true,
        longPressDelay: 500, // Mobilde sürükleme için uzun basma
        events: { url: '../ajax/calendar_api.php', method: 'POST', extraParams: { action: 'fetch' } },
        
        // Tarihe tıklayınca
        dateClick: function(info) {
            openSheet();
            var d = info.dateStr;
            if(d.length <= 10) d += 'T09:00';
            setFormDates(d);
        },

        // Etkinliğe tıklayınca
        eventClick: function(info) {
            openSheet(info.event);
        },
        
        // Boş alana seçim yapınca
        select: function(info) {
             openSheet();
             setFormDates(info.startStr);
        }
    });

    calendar.render();
    window.calendar = calendar;

    // --- SHEET / MODAL YÖNETİMİ ---
    window.openSheet = function(event = null) {
        document.getElementById('overlay').classList.add('open');
        document.getElementById('bottomSheet').classList.add('open');
        
        if(event) {
            document.getElementById('sheetTitle').innerText = "Randevuyu Düzenle";
            document.getElementById('eventId').value = event.id;
            document.getElementById('modalStudent').value = event.extendedProps.student_id;
            document.getElementById('modalNote').value = event.extendedProps.coach_note;
            
            // Radio Button Seçimi
            let status = event.extendedProps.status;
            document.querySelector(`input[name="status"][value="${status}"]`).checked = true;
            
            document.getElementById('modalStart').value = toLocalISO(event.start);
            document.getElementById('modalEnd').value = toLocalISO(event.end);
            
            document.getElementById('btnDelete').classList.remove('hidden');
        } else {
            document.getElementById('sheetTitle').innerText = "Yeni Randevu";
            document.getElementById('eventId').value = '';
            document.getElementById('eventForm').reset();
            document.getElementById('btnDelete').classList.add('hidden');
            // Default active
            document.querySelector('input[name="status"][value="active"]').checked = true;
        }
    };

    window.closeSheet = function() {
        document.getElementById('overlay').classList.remove('open');
        document.getElementById('bottomSheet').classList.remove('open');
        // Klavye açıksa kapansın
        document.activeElement.blur(); 
    };

    // --- API İŞLEMLERİ ---
    document.getElementById('btnSave').onclick = function() {
        var id = document.getElementById('eventId').value;
        var status = document.querySelector('input[name="status"]:checked').value;
        
        var formData = new FormData();
        formData.append('action', id ? 'update' : 'add');
        formData.append('id', id);
        formData.append('student_id', document.getElementById('modalStudent').value);
        formData.append('start', document.getElementById('modalStart').value);
        formData.append('end', document.getElementById('modalEnd').value);
        formData.append('coach_note', document.getElementById('modalNote').value);
        formData.append('status', status);

        fetch('../ajax/calendar_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            calendar.refetchEvents();
            closeSheet();
        });
    };

    document.getElementById('btnDelete').onclick = function() {
        if(!confirm('Silinsin mi?')) return;
        var formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', document.getElementById('eventId').value);
        fetch('../ajax/calendar_api.php', { method: 'POST', body: formData })
        .then(() => {
            calendar.refetchEvents();
            closeSheet();
        });
    };

    // --- YARDIMCILAR ---
    window.changeView = function(viewName) {
        calendar.changeView(viewName);
    };

    window.filterStudent = function(id, btn) {
        document.querySelectorAll('.student-filter').forEach(b => {
            b.classList.remove('bg-slate-900', 'text-white');
            b.classList.add('bg-slate-100', 'text-slate-500');
        });
        btn.classList.remove('bg-slate-100', 'text-slate-500');
        btn.classList.add('bg-slate-900', 'text-white');

        var allEvents = calendar.getEvents();
        allEvents.forEach(evt => {
            if(id === 'all' || evt.extendedProps.student_id == id) {
                evt.setProp('display', 'auto');
            } else {
                evt.setProp('display', 'none');
            }
        });
    };

    function setFormDates(startStr) {
        if(startStr.length <= 10) startStr += 'T09:00';
        document.getElementById('modalStart').value = startStr.slice(0,16);
        
        let d = new Date(startStr);
        d.setHours(d.getHours() + 1);
        let endStr = new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0,16);
        document.getElementById('modalEnd').value = endStr;
    }

    function toLocalISO(date) {
        if(!date) return '';
        const offset = date.getTimezoneOffset();
        date = new Date(date.getTime() - (offset*60*1000));
        return date.toISOString().slice(0, 16);
    }
});
</script>
</body>
</html>