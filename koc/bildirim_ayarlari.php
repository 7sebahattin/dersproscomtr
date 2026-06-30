<?php
require_once '../db.php';
require_once '../header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: " . BASE_URL . "/index.php"); exit;
}
$teacher_id = (int)$_SESSION['user_id'];
$B = BASE_URL;

// ── DB tablosunu otomatik oluştur ─────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_settings (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id  INT NOT NULL,
        student_id  INT DEFAULT NULL,
        scenario    VARCHAR(20) NOT NULL,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        hour        TINYINT UNSIGNED DEFAULT NULL,
        title       VARCHAR(200) DEFAULT NULL,
        body        TEXT DEFAULT NULL,
        updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_ns (teacher_id, student_id, scenario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
} catch (Throwable $e) {}

// ── Öğrencileri çek ──────────────────────────────────────────────────────────
$students = $pdo->prepare("SELECT u.id, u.first_name, u.last_name FROM users u
    JOIN coaching_relationships cr ON cr.student_id = u.id
    WHERE cr.teacher_id = ? ORDER BY u.first_name");
$students->execute([$teacher_id]);
$students = $students->fetchAll();

// ── Varsayılan ayarlar ────────────────────────────────────────────────────────
$defaults = [
    'A_12'     => ['hour'=>12, 'title'=>'📚 Günaydın! Bugünkü programın hazır',         'body'=>'Merhaba {ad}! Bugün için {toplam} görevin seni bekliyor. Hadi başlayalım!',                          'condition'=>null],
    'A_16'     => ['hour'=>16, 'title'=>'⏰ Programın Seni Bekliyor',                    'body'=>'Bugün henüz sisteme girmedin. {toplam} görevin tamamlanmayı bekliyor. Zaman geçiyor!',               'condition'=>null],
    'A_20'     => ['hour'=>20, 'title'=>'⚠️ Önemli: Bugünkü Programın Tamamlanmadı',   'body'=>'Bugün sisteme hiç girmedin. Hedefine ulaşmak için hâlâ zamanın var, şimdi başla!',                  'condition'=>null],
    'A_22'     => ['hour'=>22, 'title'=>'🚨 Bugün Kaçan Bir Gün Daha',                  'body'=>'Başarı tesadüf değildir. Bugün programına hiç girmedin. Yarın mutlaka devam et.',                    'condition'=>null],
    'B_17'     => ['hour'=>17, 'title'=>'📝 Günlük Görevlerini Tamamla',               'body'=>'Çalıştıklarını sisteme işlemeyi unutma! Bugün hâlâ hiç görev işaretlemedin.',                        'condition'=>'Koşul: Hiç görev işaretlenmemiş'],
    'B_20'     => ['hour'=>20, 'title'=>'📊 Hedefin Gerisinde Kalma!',                  'body'=>'Bugünkü görevlerin %50\'den azı tamamlandı. Yaptıklarını sisteme işlemeyi unutma!',                 'condition'=>'Koşul: %50\'den az görev tamamlandı'],
    'B_22_no'  => ['hour'=>22, 'title'=>'🌙 Bugün Çalışmayı Unutma',                   'body'=>'Sisteme girdin ama henüz hiç görev işaretlemedin. Başarı için her gün düzenli çalışmak şart!',       'condition'=>'Koşul: Hiç görev işaretlenmemiş'],
    'B_22_done'=> ['hour'=>22, 'title'=>'🎉 Harika Bir Gün!',                           'body'=>'Tebrikler! Bugünkü tüm görevlerini tamamladın. Yarının programına da göz atmayı unutma!',           'condition'=>'Koşul: Tüm görevler tamamlandı ✅'],
    'C'        => ['hour'=>null,'title'=>null,                                           'body'=>null,                                                                                                   'condition'=>null],
];

// ── Seçili öğrenci için ayarları çek ─────────────────────────────────────────
function get_settings(PDO $pdo, int $teacher_id, ?int $student_id, array $defaults): array {
    $rows = $pdo->prepare("SELECT scenario, is_active, hour, title, body FROM notification_settings
        WHERE teacher_id = ? AND student_id " . ($student_id ? "= $student_id" : "IS NULL"));
    $rows->execute([$teacher_id]);
    $saved = [];
    foreach ($rows->fetchAll() as $r) $saved[$r['scenario']] = $r;

    $result = [];
    foreach ($defaults as $sc => $def) {
        $result[$sc] = [
            'is_active' => isset($saved[$sc]) ? (int)$saved[$sc]['is_active'] : 1,
            'hour'      => isset($saved[$sc]) && $saved[$sc]['hour'] !== null ? (int)$saved[$sc]['hour'] : $def['hour'],
            'title'     => isset($saved[$sc]) && $saved[$sc]['title'] ? $saved[$sc]['title'] : $def['title'],
            'body'      => isset($saved[$sc]) && $saved[$sc]['body']  ? $saved[$sc]['body']  : $def['body'],
        ];
    }
    return $result;
}

$filter_sid = isset($_GET['student_id']) && (int)$_GET['student_id'] > 0 ? (int)$_GET['student_id'] : null;
$settings = get_settings($pdo, $teacher_id, $filter_sid, $defaults);
?>

<div class="max-w-4xl mx-auto px-4 py-8">

    <!-- Başlık -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-800">🔔 Öğrenci Bildirim Ayarları</h1>
            <p class="text-sm text-slate-500 mt-1">Bildirim içeriklerini ve saatlerini özelleştirin</p>
        </div>
        <a href="<?php echo $B; ?>/koc/ogrencilerim.php" class="text-sm text-slate-500 hover:text-slate-700 transition">← Öğrencilerime Dön</a>
    </div>

    <!-- Öğrenci Seçici -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-6 shadow-sm">
        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 block">Kime Uygulanacak?</label>
        <div class="flex flex-wrap gap-2">
            <a href="?student_id=0" class="px-4 py-2 rounded-xl text-sm font-semibold transition <?php echo !$filter_sid ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                👥 Tüm Öğrenciler
            </a>
            <?php foreach ($students as $st): ?>
            <a href="?student_id=<?php echo $st['id']; ?>" class="px-4 py-2 rounded-xl text-sm font-semibold transition <?php echo $filter_sid === (int)$st['id'] ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                <?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if ($filter_sid): ?>
        <p class="text-xs text-blue-600 mt-3 font-medium">ℹ️ Bu öğrenciye özel ayar yapılıyor. Genel ayarları değiştirmek için "Tüm Öğrenciler" seçin.</p>
        <?php else: ?>
        <p class="text-xs text-slate-400 mt-3">ℹ️ Tüm Öğrenciler seçiliyken yapılan değişiklikler, özel ayarı olmayan tüm öğrencilere uygulanır.</p>
        <?php endif; ?>
    </div>

    <!-- DURUM A -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm mb-6 overflow-hidden">
        <div class="bg-red-50 border-b border-red-100 px-5 py-4 flex items-center gap-3">
            <span class="text-2xl">🚫</span>
            <div>
                <h2 class="font-black text-slate-800">Durum A — Öğrenci Sisteme Girmedi</h2>
                <p class="text-xs text-slate-500 mt-0.5">Öğrenci o gün hiç giriş yapmadığında sabit saatlerde gönderilir</p>
            </div>
        </div>
        <div class="p-5 space-y-5">
            <?php foreach (['A_12','A_16','A_20','A_22'] as $sc):
                $s = $settings[$sc]; ?>
            <div class="border border-slate-100 rounded-xl p-4 bg-slate-50 notif-card" data-scenario="<?php echo $sc; ?>">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <!-- Aktif/Pasif toggle -->
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer toggle-active" <?php echo $s['is_active'] ? 'checked' : ''; ?>>
                            <div class="w-9 h-5 bg-slate-200 peer-checked:bg-green-500 rounded-full peer transition-colors"></div>
                            <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                        </label>
                        <span class="text-sm font-bold text-slate-700"><?php echo $sc; ?> Bildirimi</span>
                    </div>
                    <!-- Saat seçici -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-slate-500">Saat:</label>
                        <select class="hour-select text-sm border border-slate-200 rounded-lg px-2 py-1 bg-white font-mono font-bold text-slate-700">
                            <?php for ($h=6; $h<=23; $h++): ?>
                            <option value="<?php echo $h; ?>" <?php echo $s['hour']==$h ? 'selected' : ''; ?>><?php echo str_pad($h,2,'0',STR_PAD_LEFT); ?>:00</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="space-y-2">
                    <input type="text" class="notif-title w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-300"
                           placeholder="Bildirim başlığı" value="<?php echo htmlspecialchars($s['title']); ?>">
                    <textarea class="notif-body w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 resize-none"
                              rows="2" placeholder="Bildirim metni"><?php echo htmlspecialchars($s['body']); ?></textarea>
                </div>
                <div class="flex items-center justify-between mt-3">
                    <button onclick="resetScenario('<?php echo $sc; ?>')" class="text-xs text-slate-400 hover:text-red-500 transition font-medium">↺ Varsayılana sıfırla</button>
                    <button onclick="saveScenario('<?php echo $sc; ?>', <?php echo $filter_sid ?: 'null'; ?>)" class="px-4 py-1.5 bg-slate-800 text-white rounded-lg text-xs font-bold hover:bg-slate-700 transition save-btn">Kaydet</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- DURUM B -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm mb-6 overflow-hidden">
        <div class="bg-amber-50 border-b border-amber-100 px-5 py-4 flex items-center gap-3">
            <span class="text-2xl">⚠️</span>
            <div>
                <h2 class="font-black text-slate-800">Durum B — Öğrenci Giriş Yaptı Ama Görev Eksik</h2>
                <p class="text-xs text-slate-500 mt-0.5">Giriş yapan öğrencinin görev durumuna göre gönderilir</p>
            </div>
        </div>
        <div class="p-5 space-y-5">
            <?php foreach (['B_17'=>'B_17','B_20'=>'B_20','B_22_no'=>'B_22_no','B_22_done'=>'B_22_done'] as $sc):
                $s = $settings[$sc]; ?>
            <div class="border border-slate-100 rounded-xl p-4 bg-slate-50 notif-card" data-scenario="<?php echo $sc; ?>">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer toggle-active" <?php echo $s['is_active'] ? 'checked' : ''; ?>>
                            <div class="w-9 h-5 bg-slate-200 peer-checked:bg-green-500 rounded-full peer transition-colors"></div>
                            <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                        </label>
                        <span class="text-sm font-bold text-slate-700"><?php echo $sc; ?> Bildirimi</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-slate-500">Saat:</label>
                        <select class="hour-select text-sm border border-slate-200 rounded-lg px-2 py-1 bg-white font-mono font-bold text-slate-700">
                            <?php for ($h=6; $h<=23; $h++): ?>
                            <option value="<?php echo $h; ?>" <?php echo $s['hour']==$h ? 'selected' : ''; ?>><?php echo str_pad($h,2,'0',STR_PAD_LEFT); ?>:00</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <!-- Koşul etiketi -->
                <div class="inline-flex items-center gap-1.5 bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1 rounded-full mb-3">
                    🎯 <?php echo $defaults[$sc]['condition']; ?>
                </div>
                <div class="space-y-2">
                    <input type="text" class="notif-title w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-300"
                           placeholder="Bildirim başlığı" value="<?php echo htmlspecialchars($s['title']); ?>">
                    <textarea class="notif-body w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 resize-none"
                              rows="2" placeholder="Bildirim metni"><?php echo htmlspecialchars($s['body']); ?></textarea>
                </div>
                <div class="flex items-center justify-between mt-3">
                    <button onclick="resetScenario('<?php echo $sc; ?>')" class="text-xs text-slate-400 hover:text-red-500 transition font-medium">↺ Varsayılana sıfırla</button>
                    <button onclick="saveScenario('<?php echo $sc; ?>', <?php echo $filter_sid ?: 'null'; ?>)" class="px-4 py-1.5 bg-slate-800 text-white rounded-lg text-xs font-bold hover:bg-slate-700 transition save-btn">Kaydet</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- DURUM C -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm mb-6 overflow-hidden">
        <div class="bg-blue-50 border-b border-blue-100 px-5 py-4 flex items-center gap-3">
            <span class="text-2xl">⏱️</span>
            <div>
                <h2 class="font-black text-slate-800">Durum C — Göreve Özel Saat Bildirimi</h2>
                <p class="text-xs text-slate-500 mt-0.5">Koç panelinde göreve saat girildiğinde o saatte gönderilir</p>
            </div>
        </div>
        <div class="p-5">
            <div class="border border-slate-100 rounded-xl p-4 bg-slate-50 notif-card" data-scenario="C">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-slate-700 mb-1">Görev Saati Bildirimleri</p>
                        <p class="text-xs text-slate-500">Koç panelinde bir göreve saat atandığında, o saat gelince öğrenciye "Şimdi: [Ders] [Kategori]" bildirimi gönderilir.</p>
                    </div>
                    <div class="flex flex-col items-center gap-2 ml-4">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer toggle-active" <?php echo $settings['C']['is_active'] ? 'checked' : ''; ?>>
                            <div class="w-12 h-6 bg-slate-200 peer-checked:bg-green-500 rounded-full peer transition-colors"></div>
                            <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-6"></div>
                        </label>
                        <span class="text-xs text-slate-500 toggle-label"><?php echo $settings['C']['is_active'] ? 'Aktif' : 'Pasif'; ?></span>
                    </div>
                </div>
                <div class="flex justify-end mt-4">
                    <button onclick="saveScenario('C', <?php echo $filter_sid ?: 'null'; ?>)" class="px-4 py-1.5 bg-slate-800 text-white rounded-lg text-xs font-bold hover:bg-slate-700 transition">Kaydet</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tümüne Uygula Butonu -->
    <?php if ($filter_sid): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 text-center">
        <p class="text-sm text-blue-700 font-medium mb-3">Bu öğrencinin ayarlarını tüm öğrencilere uygulamak ister misiniz?</p>
        <button onclick="applyToAll(<?php echo $filter_sid; ?>)" class="px-6 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition">
            👥 Tüm Öğrencilere Uygula
        </button>
    </div>
    <?php endif; ?>

</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-6 right-6 z-50 hidden">
    <div class="bg-slate-800 text-white px-5 py-3 rounded-xl shadow-xl text-sm font-medium flex items-center gap-2">
        <span id="toastIcon">✅</span>
        <span id="toastMsg">Kaydedildi</span>
    </div>
</div>

<script>
const DEFAULTS = <?php echo json_encode($defaults, JSON_UNESCAPED_UNICODE); ?>;

function showToast(msg, ok=true) {
    document.getElementById('toastIcon').textContent = ok ? '✅' : '❌';
    document.getElementById('toastMsg').textContent = msg;
    const t = document.getElementById('toast');
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 3000);
}

function getCardData(scenario) {
    const card = document.querySelector(`.notif-card[data-scenario="${scenario}"]`);
    if (!card) return null;
    return {
        is_active: card.querySelector('.toggle-active').checked ? 1 : 0,
        hour:      card.querySelector('.hour-select')  ? parseInt(card.querySelector('.hour-select').value) : null,
        title:     card.querySelector('.notif-title')  ? card.querySelector('.notif-title').value : null,
        body:      card.querySelector('.notif-body')   ? card.querySelector('.notif-body').value  : null,
    };
}

function saveScenario(scenario, studentId) {
    const data = getCardData(scenario);
    if (!data) return;
    const btn = document.querySelector(`.notif-card[data-scenario="${scenario}"] .save-btn`);
    if (btn) { btn.textContent = '...'; btn.disabled = true; }

    fetch('<?php echo $B; ?>/ajax/save_notif_settings.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ scenario, student_id: studentId, ...data })
    })
    .then(r => r.json())
    .then(res => {
        showToast(res.message, res.success);
        if (btn) { btn.textContent = 'Kaydet'; btn.disabled = false; }
    })
    .catch(() => { showToast('Bağlantı hatası', false); if(btn){btn.textContent='Kaydet';btn.disabled=false;} });
}

function resetScenario(scenario) {
    const def = DEFAULTS[scenario];
    if (!def) return;
    const card = document.querySelector(`.notif-card[data-scenario="${scenario}"]`);
    if (!card) return;
    if (def.hour !== null && def.hour !== undefined) {
        const sel = card.querySelector('.hour-select');
        if (sel) sel.value = def.hour;
    }
    if (def.title) card.querySelector('.notif-title').value = def.title;
    if (def.body)  card.querySelector('.notif-body').value  = def.body;
    card.querySelector('.toggle-active').checked = true;
    showToast('Varsayılan değerler yüklendi. Kaydet\'e basarak uygulayın.', true);
}

function applyToAll(fromStudentId) {
    if (!confirm('Bu öğrencinin tüm ayarları diğer tüm öğrencilerinize uygulanacak. Emin misiniz?')) return;
    fetch('<?php echo $B; ?>/ajax/save_notif_settings.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'copy_to_all', from_student_id: fromStudentId })
    })
    .then(r => r.json())
    .then(res => showToast(res.message, res.success));
}

// Toggle label güncelle
document.querySelectorAll('.toggle-active').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const label = this.closest('.notif-card').querySelector('.toggle-label');
        if (label) label.textContent = this.checked ? 'Aktif' : 'Pasif';
    });
});
</script>

<?php include '../footer.php'; ?>
