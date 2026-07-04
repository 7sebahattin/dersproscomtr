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
    'A_12'     => ['hour'=>12, 'title'=>'📚 Günaydın! Bugünkü programın hazır',         'body'=>'Merhaba {ad}! Bugün için {toplam} görevin seni bekliyor. Hadi başlayalım!'],
    'A_16'     => ['hour'=>16, 'title'=>'⏰ Programın Seni Bekliyor',                    'body'=>'Bugün henüz sisteme girmedin. {toplam} görevin tamamlanmayı bekliyor. Zaman geçiyor!'],
    'A_20'     => ['hour'=>20, 'title'=>'⚠️ Önemli: Bugünkü Programın Tamamlanmadı',   'body'=>'Bugün sisteme hiç girmedin. Hedefine ulaşmak için hâlâ zamanın var, şimdi başla!'],
    'A_22'     => ['hour'=>22, 'title'=>'🚨 Bugün Kaçan Bir Gün Daha',                  'body'=>'Başarı tesadüf değildir. Bugün programına hiç girmedin. Yarın mutlaka devam et.'],
    'B_17'     => ['hour'=>17, 'title'=>'📝 Günlük Görevlerini Tamamla',               'body'=>'Çalıştıklarını sisteme işlemeyi unutma! Bugün hâlâ hiç görev işaretlemedin.'],
    'B_20'     => ['hour'=>20, 'title'=>'📊 Hedefin Gerisinde Kalma!',                  'body'=>'Bugünkü görevlerin %50\'den azı tamamlandı. Yaptıklarını sisteme işlemeyi unutma!'],
    'B_22_no'  => ['hour'=>22, 'title'=>'🌙 Bugün Çalışmayı Unutma',                   'body'=>'Sisteme girdin ama henüz hiç görev işaretlemedin. Başarı için her gün düzenli çalışmak şart!'],
    'B_22_done'=> ['hour'=>22, 'title'=>'🎉 Harika Bir Gün!',                           'body'=>'Tebrikler! Bugünkü tüm görevlerini tamamladın. Yarının programına da göz atmayı unutma!'],
    'C'        => ['hour'=>null,'title'=>null,                                           'body'=>null],
];

// ── İnsani etiketler (teknik kod → anlaşılır ad + açıklama) ───────────────────
$labels = [
    'A_12'     => ['icon'=>'🌅', 'name'=>'Sabah hatırlatması',   'desc'=>'Öğrenci güne başlasın diye erken bir dürtü.'],
    'A_16'     => ['icon'=>'☀️', 'name'=>'Öğleden sonra',        'desc'=>'Hâlâ giriş yoksa ikinci hatırlatma.'],
    'A_20'     => ['icon'=>'🌆', 'name'=>'Akşam uyarısı',         'desc'=>'Gün bitmeden son fırsat hatırlatması.'],
    'A_22'     => ['icon'=>'🌙', 'name'=>'Gece kapanışı',         'desc'=>'Gün kaçtıysa yarına motive eden mesaj.'],
    'B_17'     => ['icon'=>'📝', 'name'=>'Görev başlatma',        'desc'=>'Giriş yaptı ama hiç görev işaretlemediyse.'],
    'B_20'     => ['icon'=>'📊', 'name'=>'Yarı yol uyarısı',      'desc'=>'Görevlerin yarısından azı tamamlandıysa.'],
    'B_22_no'  => ['icon'=>'🌙', 'name'=>'Gece — görev yok',      'desc'=>'Girdi ama gün sonunda hiç görev işaretlemediyse.'],
    'B_22_done'=> ['icon'=>'🎉', 'name'=>'Tebrik mesajı',         'desc'=>'Tüm görevleri tamamladıysa kutlama.'],
    'C'        => ['icon'=>'⏱️', 'name'=>'Göreve özel saat',      'desc'=>'Bir göreve saat atandığında tam o saatte hatırlatır.'],
];

// ── Hazır şablonlar: hangi senaryolar açık olsun ─────────────────────────────
$presets = [
    'nazik'    => ['A_20','B_22_done','C'],
    'standart' => ['A_12','A_20','A_22','B_17','B_22_no','B_22_done','C'],
    'siki'     => ['A_12','A_16','A_20','A_22','B_17','B_20','B_22_no','B_22_done','C'],
];

$groupA = ['A_12','A_16','A_20','A_22'];
$groupB = ['B_17','B_20','B_22_no','B_22_done'];
$allScenarios = array_merge($groupA, $groupB, ['C']);

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

// Ana anahtar durumu: en az bir senaryo aktifse "açık" say
$masterOn = false;
foreach ($allScenarios as $sc) { if (!empty($settings[$sc]['is_active'])) { $masterOn = true; break; } }

// Tek bir senaryo kartını (insani başlıkla) basan yardımcı
function render_notif_row(string $sc, array $s, array $labels, bool $hasHour): void {
    $L = $labels[$sc];
    ?>
    <div class="notif-card border border-slate-100 rounded-2xl bg-white overflow-hidden" data-scenario="<?php echo $sc; ?>">
        <div class="flex items-center gap-3 p-4">
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" class="sr-only peer toggle-active" <?php echo $s['is_active'] ? 'checked' : ''; ?>>
                <div class="w-11 h-6 bg-slate-200 peer-checked:bg-green-500 rounded-full peer transition-colors"></div>
                <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
            </label>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-slate-800 leading-tight"><?php echo $L['icon'].' '.htmlspecialchars($L['name']); ?></p>
                <p class="text-[11px] text-slate-400 leading-snug mt-0.5"><?php echo htmlspecialchars($L['desc']); ?></p>
            </div>
            <?php if ($hasHour): ?>
            <div class="flex items-center gap-1.5 shrink-0">
                <span class="text-[10px] text-slate-400 font-bold hidden sm:inline">SAAT</span>
                <select class="hour-select text-sm border border-slate-200 rounded-lg px-2 py-1.5 bg-slate-50 font-mono font-bold text-slate-700">
                    <?php for ($h=6; $h<=23; $h++): ?>
                    <option value="<?php echo $h; ?>" <?php echo $s['hour']==$h ? 'selected' : ''; ?>><?php echo str_pad($h,2,'0',STR_PAD_LEFT); ?>:00</option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($hasHour): ?>
            <button type="button" onclick="toggleEdit('<?php echo $sc; ?>')" class="edit-toggle text-slate-300 hover:text-slate-600 transition shrink-0 p-1" title="Mesajı düzenle">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            <?php endif; ?>
        </div>
        <?php if ($hasHour): ?>
        <div class="edit-panel hidden px-4 pb-4 space-y-2 border-t border-slate-50 pt-3 bg-slate-50/50">
            <input type="text" class="notif-title w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-200"
                   placeholder="Bildirim başlığı" value="<?php echo htmlspecialchars($s['title']); ?>">
            <textarea class="notif-body w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-200 resize-none"
                      rows="2" placeholder="Bildirim metni"><?php echo htmlspecialchars($s['body']); ?></textarea>
            <div class="flex justify-between items-center">
                <button type="button" onclick="resetScenario('<?php echo $sc; ?>')" class="text-[11px] text-slate-400 hover:text-red-500 font-medium">↺ Varsayılan metne dön</button>
                <span class="text-[10px] text-slate-400">{ad} ve {toplam} otomatik doldurulur</span>
            </div>
        </div>
        <?php else: ?>
        <input type="hidden" class="notif-title" value="">
        <input type="hidden" class="notif-body" value="">
        <?php endif; ?>
    </div>
    <?php
}
?>

<div class="max-w-3xl mx-auto px-4 py-8">

    <!-- Başlık -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-800">🔔 Bildirim Ayarları</h1>
            <p class="text-sm text-slate-500 mt-1">Öğrencilerine hangi hatırlatmaların gideceğini seç</p>
        </div>
        <a href="<?php echo $B; ?>/koc/ogrencilerim.php" class="text-sm text-slate-500 hover:text-slate-700 transition">← Öğrencilerime Dön</a>
    </div>

    <!-- 1) Öğrenci Seçici + Test -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-5 shadow-sm">
        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 block">1. Kime uygulanacak?</label>
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
        <p class="text-xs text-slate-400 mt-3">ℹ️ "Tüm Öğrenciler" ayarı, kendine özel ayarı olmayan herkese uygulanır.</p>
        <?php endif; ?>

        <!-- Test bildirimi -->
        <div class="mt-4 pt-4 border-t border-slate-100">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-slate-700">🧪 Test Bildirimi Gönder</p>
                    <p class="text-xs text-slate-400 mt-0.5">Seçili öğrenciye anında bir test yollar; ayarlardan bağımsız pipeline'ı doğrular.</p>
                </div>
                <?php if ($filter_sid): ?>
                <button id="testPushBtn" onclick="sendTestPush(<?php echo $filter_sid; ?>)"
                        class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition whitespace-nowrap">
                    🔔 Şimdi Test Et
                </button>
                <?php else: ?>
                <span class="text-xs text-slate-400 font-medium bg-slate-100 px-3 py-2 rounded-lg">Önce bir öğrenci seçin</span>
                <?php endif; ?>
            </div>
            <div id="testPushResult" class="hidden mt-3 text-xs rounded-xl border p-3"></div>
        </div>
    </div>

    <!-- 2) Ana Anahtar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-5 shadow-sm">
        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 block">2. Bildirimler</label>
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🔔</span>
                <div>
                    <p class="text-sm font-bold text-slate-800">Bu öğrenciye bildirim gönder</p>
                    <p class="text-xs text-slate-400" id="masterHint"><?php echo $masterOn ? 'Açık — aşağıdaki seçili hatırlatmalar gönderilir.' : 'Kapalı — hiçbir hatırlatma gönderilmez.'; ?></p>
                </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" id="masterToggle" class="sr-only peer" <?php echo $masterOn ? 'checked' : ''; ?>>
                <div class="w-14 h-7 bg-slate-200 peer-checked:bg-green-500 rounded-full peer transition-colors"></div>
                <div class="absolute left-1 top-1 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-7"></div>
            </label>
        </div>
    </div>

    <!-- 3) Hazır Şablonlar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-5 shadow-sm" id="presetCard">
        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1 block">3. Hazır şablon (tek tıkla ayarla)</label>
        <p class="text-xs text-slate-400 mb-3">Bir şablon seç; hatırlatmalar otomatik ayarlanır. İstersen aşağıdan tek tek değiştirebilirsin.</p>
        <div class="grid grid-cols-3 gap-2">
            <button type="button" onclick="applyPreset('nazik')" class="preset-btn flex flex-col items-center gap-1 py-3 rounded-xl border-2 border-slate-100 hover:border-green-300 hover:bg-green-50 transition">
                <span class="text-xl">🌱</span><span class="text-xs font-bold text-slate-700">Nazik</span><span class="text-[10px] text-slate-400">Az hatırlatma</span>
            </button>
            <button type="button" onclick="applyPreset('standart')" class="preset-btn flex flex-col items-center gap-1 py-3 rounded-xl border-2 border-slate-100 hover:border-blue-300 hover:bg-blue-50 transition">
                <span class="text-xl">⚖️</span><span class="text-xs font-bold text-slate-700">Standart</span><span class="text-[10px] text-slate-400">Dengeli</span>
            </button>
            <button type="button" onclick="applyPreset('siki')" class="preset-btn flex flex-col items-center gap-1 py-3 rounded-xl border-2 border-slate-100 hover:border-red-300 hover:bg-red-50 transition">
                <span class="text-xl">🔥</span><span class="text-xs font-bold text-slate-700">Sıkı</span><span class="text-[10px] text-slate-400">Tüm hatırlatmalar</span>
            </button>
        </div>
    </div>

    <!-- 4) Hatırlatma Listesi -->
    <div id="scenarioWrap" class="space-y-5 mb-5 <?php echo $masterOn ? '' : 'opacity-40 pointer-events-none'; ?>">

        <!-- Grup: Sisteme hiç girmediğinde -->
        <div>
            <div class="flex items-center gap-2 mb-2 px-1">
                <span class="text-base">🚫</span>
                <h2 class="text-sm font-black text-slate-700">Öğrenci sisteme hiç girmediğinde</h2>
            </div>
            <div class="space-y-2">
                <?php foreach ($groupA as $sc) render_notif_row($sc, $settings[$sc], $labels, true); ?>
            </div>
        </div>

        <!-- Grup: Girdi ama görev eksik -->
        <div>
            <div class="flex items-center gap-2 mb-2 px-1">
                <span class="text-base">⚠️</span>
                <h2 class="text-sm font-black text-slate-700">Girdi ama görevleri eksik kaldığında</h2>
            </div>
            <div class="space-y-2">
                <?php foreach ($groupB as $sc) render_notif_row($sc, $settings[$sc], $labels, true); ?>
            </div>
        </div>

        <!-- Grup: Göreve özel saat -->
        <div>
            <div class="flex items-center gap-2 mb-2 px-1">
                <span class="text-base">⏱️</span>
                <h2 class="text-sm font-black text-slate-700">Göreve saat atandığında</h2>
            </div>
            <div class="space-y-2">
                <?php render_notif_row('C', $settings['C'], $labels, false); ?>
            </div>
        </div>
    </div>

    <!-- Tümüne uygula (öğrenci seçiliyken) -->
    <?php if ($filter_sid): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-24 text-center">
        <p class="text-sm text-blue-700 font-medium mb-2">Bu öğrencinin ayarlarını tüm öğrencilere uygulamak ister misin?</p>
        <button onclick="applyToAll(<?php echo $filter_sid; ?>)" class="px-6 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition">
            👥 Tüm Öğrencilere Uygula
        </button>
    </div>
    <?php else: ?>
    <div class="mb-24"></div>
    <?php endif; ?>
</div>

<!-- Sabit alt kaydet çubuğu -->
<div class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur border-t border-slate-200 p-3 shadow-[0_-4px_12px_rgba(0,0,0,0.05)]">
    <div class="max-w-3xl mx-auto flex items-center justify-between gap-3 px-1">
        <span class="text-xs text-slate-400 hidden sm:block">Değişiklikler kaydedilene kadar uygulanmaz.</span>
        <button onclick="saveAll()" id="saveAllBtn" class="flex-1 sm:flex-none px-8 py-3 bg-slate-800 text-white rounded-xl text-sm font-black hover:bg-slate-700 transition shadow-lg">
            💾 Tümünü Kaydet
        </button>
    </div>
</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-20 right-6 z-50 hidden">
    <div class="bg-slate-800 text-white px-5 py-3 rounded-xl shadow-xl text-sm font-medium flex items-center gap-2">
        <span id="toastIcon">✅</span>
        <span id="toastMsg">Kaydedildi</span>
    </div>
</div>

<script>
const DEFAULTS = <?php echo json_encode($defaults, JSON_UNESCAPED_UNICODE); ?>;
const PRESETS  = <?php echo json_encode($presets, JSON_UNESCAPED_UNICODE); ?>;
const ALL_SC   = <?php echo json_encode($allScenarios, JSON_UNESCAPED_UNICODE); ?>;
const FILTER_SID = <?php echo $filter_sid ?: 'null'; ?>;

function showToast(msg, ok=true) {
    document.getElementById('toastIcon').textContent = ok ? '✅' : '❌';
    document.getElementById('toastMsg').textContent = msg;
    const t = document.getElementById('toast');
    t.classList.remove('hidden');
    clearTimeout(window._toastT);
    window._toastT = setTimeout(() => t.classList.add('hidden'), 3000);
}

function cardOf(sc){ return document.querySelector(`.notif-card[data-scenario="${sc}"]`); }

function getCardData(sc) {
    const card = cardOf(sc);
    if (!card) return null;
    return {
        is_active: card.querySelector('.toggle-active').checked ? 1 : 0,
        hour:      card.querySelector('.hour-select')  ? parseInt(card.querySelector('.hour-select').value) : null,
        title:     card.querySelector('.notif-title')  ? card.querySelector('.notif-title').value : null,
        body:      card.querySelector('.notif-body')   ? card.querySelector('.notif-body').value  : null,
    };
}

// Mesaj düzenleme panelini aç/kapa
function toggleEdit(sc) {
    const card = cardOf(sc);
    const panel = card ? card.querySelector('.edit-panel') : null;
    if (panel) panel.classList.toggle('hidden');
}

// Varsayılan metne dön
function resetScenario(sc) {
    const def = DEFAULTS[sc];
    const card = cardOf(sc);
    if (!def || !card) return;
    if (def.hour !== null && def.hour !== undefined) {
        const sel = card.querySelector('.hour-select'); if (sel) sel.value = def.hour;
    }
    if (card.querySelector('.notif-title') && def.title) card.querySelector('.notif-title').value = def.title;
    if (card.querySelector('.notif-body')  && def.body)  card.querySelector('.notif-body').value  = def.body;
    showToast('Varsayılan metin yüklendi. "Tümünü Kaydet" ile uygula.', true);
}

// Ana anahtar: tüm hatırlatmaları aç/kapat (sadece görsel; kaydet ile uygulanır)
const masterToggle = document.getElementById('masterToggle');
const scenarioWrap = document.getElementById('scenarioWrap');
function reflectMaster() {
    const on = masterToggle.checked;
    scenarioWrap.classList.toggle('opacity-40', !on);
    scenarioWrap.classList.toggle('pointer-events-none', !on);
    document.getElementById('masterHint').textContent = on
        ? 'Açık — aşağıdaki seçili hatırlatmalar gönderilir.'
        : 'Kapalı — hiçbir hatırlatma gönderilmez.';
}
masterToggle.addEventListener('change', reflectMaster);

// Hazır şablon uygula: ilgili senaryoları aç, kalanı kapat (görsel)
function applyPreset(name) {
    const active = PRESETS[name] || [];
    if (!masterToggle.checked) { masterToggle.checked = true; reflectMaster(); }
    ALL_SC.forEach(sc => {
        const card = cardOf(sc);
        if (!card) return;
        card.querySelector('.toggle-active').checked = active.includes(sc);
    });
    document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('ring-2','ring-blue-400'));
    showToast('Şablon uygulandı. "Tümünü Kaydet" ile onayla.', true);
}

// Tümünü kaydet: ana anahtar kapalıysa hepsini pasif kaydet
async function saveAll() {
    const btn = document.getElementById('saveAllBtn');
    btn.disabled = true; const old = btn.textContent; btn.textContent = '⏳ Kaydediliyor...';
    const masterOn = masterToggle.checked;
    let ok = 0, fail = 0;

    for (const sc of ALL_SC) {
        const data = getCardData(sc);
        if (!data) continue;
        if (!masterOn) data.is_active = 0; // ana anahtar kapalı → hepsi pasif
        try {
            const res = await fetch('<?php echo $B; ?>/ajax/save_notif_settings.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ scenario: sc, student_id: FILTER_SID, ...data })
            });
            const j = await res.json();
            if (j.success) ok++; else fail++;
        } catch(e) { fail++; }
    }
    btn.disabled = false; btn.textContent = old;
    if (fail === 0) showToast('Tüm ayarlar kaydedildi ✅', true);
    else showToast(`${ok} kaydedildi, ${fail} başarısız`, false);
}

// Tüm öğrencilere uygula
function applyToAll(fromStudentId) {
    if (!confirm('Bu öğrencinin ayarları tüm öğrencilerine uygulanacak. Önce "Tümünü Kaydet" yaptığından emin ol. Devam edilsin mi?')) return;
    fetch('<?php echo $B; ?>/ajax/save_notif_settings.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'copy_to_all', from_student_id: fromStudentId })
    })
    .then(r => r.json())
    .then(res => showToast(res.message, res.success))
    .catch(() => showToast('Bağlantı hatası', false));
}

// ── Anlık test bildirimi (TEŞHİS) ──
function sendTestPush(studentId) {
    const btn = document.getElementById('testPushBtn');
    const box = document.getElementById('testPushResult');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Gönderiliyor...'; }
    box.className = 'mt-3 text-xs rounded-xl border p-3 bg-slate-50 border-slate-200 text-slate-500';
    box.classList.remove('hidden');
    box.textContent = 'Test bildirimi gönderiliyor...';

    fetch('<?php echo $B; ?>/ajax/push_test.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ student_id: studentId })
    })
    .then(async r => {
        const t = await r.text();
        try { return JSON.parse(t); }
        catch(e) { throw new Error('Sunucu JSON döndürmedi (HTTP ' + r.status + '). Yanıt: ' + t.slice(0, 400)); }
    })
    .then(res => {
        let html = '';
        const okCls   = 'bg-green-50 border-green-200 text-green-700';
        const failCls = 'bg-red-50 border-red-200 text-red-700';
        box.className = 'mt-3 text-xs rounded-xl border p-3 break-words ' + (res.ok ? okCls : failCls);
        html += '<p class="font-bold mb-1">' + (res.ok ? '✅ ' : '⚠️ ') + (res.msg || '') + '</p>';
        if (typeof res.device_count !== 'undefined') {
            html += '<p class="text-slate-500">Kayıtlı cihaz: <b>' + res.device_count + '</b>'
                 + ' · Tercih: <b>' + (res.pref_enabled ? 'Açık' : 'Kapalı') + '</b></p>';
        }
        if (res.diagnostics && res.diagnostics.length) {
            html += '<ul class="mt-1 list-disc list-inside text-amber-700">';
            res.diagnostics.forEach(d => html += '<li>' + d + '</li>');
            html += '</ul>';
        }
        if (res.results && res.results.length) {
            html += '<ul class="mt-1 space-y-0.5">';
            res.results.forEach(r => {
                html += '<li>' + (r.ok ? '✅' : '❌') + ' <b>' + r.device + '</b>'
                     + (r.http ? ' (HTTP ' + r.http + ')' : '') + ' — ' + r.detail + '</li>';
            });
            html += '</ul>';
        }
        box.innerHTML = html;
    })
    .catch(err => {
        box.className = 'mt-3 text-xs rounded-xl border p-3 bg-red-50 border-red-200 text-red-700 break-words';
        box.textContent = 'Hata: ' + (err && err.message ? err.message : 'test gönderilemedi.');
    })
    .finally(() => {
        if (btn) { btn.disabled = false; btn.textContent = '🔔 Şimdi Test Et'; }
    });
}
</script>

<?php include '../footer.php'; ?>
