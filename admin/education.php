<?php
// education.php — YENİ Müfredat Yönetimi (Kategoriler > Dersler > Konular)
// Eski müfredat sisteminden (mufredat.php / coaching_*) TAMAMEN BAĞIMSIZDIR.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once '../education_lib.php';

if (!education_is_admin()) {
    header("Location: ../index.php"); exit;
}

education_ensure_schema($pdo);

$message = '';

// ── AJAX: sürükle-bırak sıralama kaydet ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_reorder'])) {
    header('Content-Type: application/json; charset=utf-8');
    $type = $_POST['type'] ?? '';
    $ids  = json_decode($_POST['ids'] ?? '[]', true);
    $table = ['category' => 'education_categories', 'subject' => 'education_subjects', 'topic' => 'education_topics'][$type] ?? null;
    if (!$table || !is_array($ids)) { echo json_encode(['ok' => false]); exit; }
    $st = $pdo->prepare("UPDATE $table SET display_order = ? WHERE id = ?");
    foreach (array_values($ids) as $i => $id) { $st->execute([$i + 1, (int)$id]); }
    echo json_encode(['ok' => true]); exit;
}

// ── Form işlemleri ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_category'])) {
            $name = trim(mb_substr($_POST['name'] ?? '', 0, 100));
            if ($name !== '') {
                $max = (int)$pdo->query("SELECT COALESCE(MAX(display_order),0) FROM education_categories")->fetchColumn();
                $pdo->prepare("INSERT INTO education_categories (name, display_order) VALUES (?, ?)")->execute([$name, $max + 1]);
                $message = "✅ Kategori eklendi.";
            }
        }
        if (isset($_POST['add_subject'])) {
            $catId = (int)$_POST['category_id'];
            $name  = trim(mb_substr($_POST['name'] ?? '', 0, 100));
            if ($catId > 0 && $name !== '') {
                $st = $pdo->prepare("SELECT COALESCE(MAX(display_order),0) FROM education_subjects WHERE category_id = ?");
                $st->execute([$catId]); $max = (int)$st->fetchColumn();
                $pdo->prepare("INSERT INTO education_subjects (category_id, lesson_name, display_order) VALUES (?, ?, ?)")
                    ->execute([$catId, $name, $max + 1]);
                $message = "✅ Ders eklendi.";
            }
        }
        if (isset($_POST['add_topic'])) {
            $subjId = (int)$_POST['subject_id'];
            $names  = trim($_POST['names'] ?? '');
            if ($subjId > 0 && $names !== '') {
                $st = $pdo->prepare("SELECT COALESCE(MAX(display_order),0) FROM education_topics WHERE subject_id = ?");
                $st->execute([$subjId]); $order = (int)$st->fetchColumn();
                $ins = $pdo->prepare("INSERT INTO education_topics (subject_id, topic_name, display_order) VALUES (?, ?, ?)");
                $added = 0;
                foreach (preg_split('/\r\n|\r|\n/', $names) as $line) {
                    $line = trim(mb_substr($line, 0, 255));
                    if ($line !== '') { $ins->execute([$subjId, $line, ++$order]); $added++; }
                }
                $message = "✅ $added konu eklendi.";
            }
        }
        if (isset($_POST['rename_item'])) {
            $type = $_POST['type']; $id = (int)$_POST['id'];
            $name = trim(mb_substr($_POST['name'] ?? '', 0, 255));
            $map = ['category' => ['education_categories','name'], 'subject' => ['education_subjects','lesson_name'], 'topic' => ['education_topics','topic_name']];
            if (isset($map[$type]) && $id > 0 && $name !== '') {
                [$t, $col] = $map[$type];
                $pdo->prepare("UPDATE $t SET $col = ? WHERE id = ?")->execute([$name, $id]);
                $message = "✏️ Güncellendi.";
            }
        }
        if (isset($_POST['toggle_item'])) {
            $type = $_POST['type']; $id = (int)$_POST['id'];
            $map = ['category' => ['education_categories','is_active'], 'subject' => ['education_subjects','is_active'], 'topic' => ['education_topics','status']];
            if (isset($map[$type]) && $id > 0) {
                [$t, $col] = $map[$type];
                $pdo->prepare("UPDATE $t SET $col = 1 - $col WHERE id = ?")->execute([$id]);
                $message = "👁️ Durum değiştirildi.";
            }
        }
        if (isset($_POST['toggle_lock'])) {
            // Kilitle/Onayla: içerik global olur ve silinemez. Admin kilidi geri açabilir.
            $type = $_POST['type']; $id = (int)$_POST['id'];
            $map = ['category' => 'education_categories', 'subject' => 'education_subjects', 'topic' => 'education_topics'];
            if (isset($map[$type]) && $id > 0) {
                $pdo->prepare("UPDATE {$map[$type]} SET is_locked = 1 - is_locked WHERE id = ?")->execute([$id]);
                $message = "🔐 Kilit durumu değiştirildi.";
            }
        }
        if (isset($_POST['delete_item'])) {
            $type = $_POST['type']; $id = (int)$_POST['id'];
            $map = ['category' => 'education_categories', 'subject' => 'education_subjects', 'topic' => 'education_topics'];
            if (isset($map[$type]) && $id > 0) {
                $chk = $pdo->prepare("SELECT is_locked FROM {$map[$type]} WHERE id = ?");
                $chk->execute([$id]);
                if ((int)$chk->fetchColumn() === 1) {
                    $message = "🔒 Kilitli içerik silinemez. Önce kilidi açın.";
                } else {
                    $pdo->prepare("DELETE FROM {$map[$type]} WHERE id = ?")->execute([$id]);
                    $message = "🗑️ Silindi (bağlı alt kayıtlar da temizlendi).";
                }
            }
        }
    } catch (Throwable $e) {
        $message = "❌ İşlem hatası: kayıt zaten mevcut olabilir.";
    }
}

// ── Görünüm verisi ──────────────────────────────────────────────────────────
$ownerFilter = isset($_GET['owner']) && $_GET['owner'] == '1'; // sadece öğretmen ekledikleri
$categories = education_get_categories($pdo, false);
$curCat  = (int)($_GET['cat'] ?? ($categories[0]['id'] ?? 0));
$subjects = $curCat ? education_get_subjects($pdo, $curCat, false) : [];
$curSubj = (int)($_GET['subj'] ?? 0);
if ($curSubj && !in_array($curSubj, array_column($subjects, 'id'))) $curSubj = 0;
$search  = trim($_GET['q'] ?? '');
$topics  = $curSubj ? education_get_topics($pdo, $curSubj, false, $search, 1000, 0) : [];

if ($ownerFilter) {
    $subjects = array_values(array_filter($subjects, fn($s) => !empty($s['created_by'])));
    $topics   = array_values(array_filter($topics,   fn($t) => !empty($t['created_by'])));
}

// Sahip (öğretmen) adları — tek sorguda (N+1 yok)
$ownerIds = array_filter(array_unique(array_merge(
    array_column($categories, 'created_by'),
    array_column($subjects, 'created_by'),
    array_column($topics, 'created_by')
)));
$ownerNames = [];
if ($ownerIds) {
    $in = implode(',', array_map('intval', $ownerIds));
    foreach ($pdo->query("SELECT id, CONCAT(first_name,' ',last_name) n FROM users WHERE id IN ($in)") as $r) {
        $ownerNames[$r['id']] = $r['n'];
    }
}
// Öğretmen katkısı özeti
$teacherCounts = $pdo->query("SELECT
    (SELECT COUNT(*) FROM education_subjects WHERE created_by IS NOT NULL) subj,
    (SELECT COUNT(*) FROM education_topics WHERE created_by IS NOT NULL) top")->fetch(PDO::FETCH_ASSOC);

// Sayılar (rozetler için, tek sorguda — N+1 yok)
$subjCounts = $pdo->query("SELECT category_id, COUNT(*) c FROM education_subjects GROUP BY category_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$topicCounts = $pdo->query("SELECT s.id, COUNT(t.id) c FROM education_subjects s LEFT JOIN education_topics t ON t.subject_id = s.id GROUP BY s.id")->fetchAll(PDO::FETCH_KEY_PAIR);

$curCatName  = '';
foreach ($categories as $c) if ($c['id'] == $curCat) $curCatName = $c['name'];
$curSubjName = '';
foreach ($subjects as $s) if ($s['id'] == $curSubj) $curSubjName = $s['lesson_name'];

function url_self(int $cat = 0, int $subj = 0, string $q = ''): string {
    global $ownerFilter;
    $p = [];
    if ($cat)  $p['cat'] = $cat;
    if ($subj) $p['subj'] = $subj;
    if ($q !== '') $p['q'] = $q;
    if (!empty($ownerFilter)) $p['owner'] = 1;
    return 'education.php' . ($p ? ('?' . http_build_query($p)) : '');
}

/** Satır rozetleri: 🔒 kilitli / ★ öğretmen içeriği (sahip adı) */
function owner_badge(array $row, array $ownerNames): string {
    $html = '';
    if ((int)($row['is_locked'] ?? 0) === 1) {
        $html .= '<span title="Kilitli — silinemez" class="text-[10px]">🔒</span>';
    }
    $cb = (int)($row['created_by'] ?? 0);
    if ($cb) {
        $nm = htmlspecialchars($ownerNames[$cb] ?? ('#' . $cb));
        $html .= '<span title="Öğretmen içeriği: ' . $nm . '" class="text-[9px] font-bold bg-amber-100 text-amber-700 rounded-full px-1.5 py-0.5">★ ' . $nm . '</span>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müfredat Yönetimi — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .drag-handle { cursor: grab; }
        .sortable-ghost { opacity: .4; background: #e0e7ff !important; }
    </style>
</head>
<body class="bg-slate-100 flex min-h-screen">

<?php include 'sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8 overflow-x-hidden">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">📚 Müfredat Yönetimi</h1>
                <p class="text-sm text-slate-500 mt-1">Kategoriler, dersler ve konular. Sürükleyerek sıralayın. 🔒 = kilitli (global, silinemez), ★ = öğretmen içeriği.</p>
            </div>
            <?php
                $tp = [];
                if ($curCat)  $tp['cat'] = $curCat;
                if ($curSubj) $tp['subj'] = $curSubj;
                if ($search !== '') $tp['q'] = $search;
                if (!$ownerFilter) $tp['owner'] = 1; // kapalıysa aç; açıksa parametresiz (kapat)
                $toggleUrl = 'education.php' . ($tp ? ('?' . http_build_query($tp)) : '');
            ?>
            <a href="<?= $toggleUrl ?>"
               class="text-xs font-bold px-4 py-2.5 rounded-xl border <?= $ownerFilter ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-amber-600 border-amber-200 hover:bg-amber-50' ?>">
                ★ Öğretmen Ekledikleri (<?= (int)$teacherCounts['subj'] ?> ders / <?= (int)$teacherCounts['top'] ?> konu) <?= $ownerFilter ? '✕' : '' ?>
            </a>
        </div>

        <?php if ($message): ?>
            <div class="bg-white border border-indigo-100 text-slate-700 p-3 rounded-xl mb-5 text-sm shadow-sm"><?= $message ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

            <!-- KATEGORİLER -->
            <section class="lg:col-span-3 bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Kategoriler</h2>
                <ul id="cat-list" class="space-y-1.5">
                    <?php foreach ($categories as $c): ?>
                    <li data-id="<?= $c['id'] ?>" class="group flex items-center gap-2 rounded-xl px-2 py-2 border <?= $c['id'] == $curCat ? 'bg-indigo-50 border-indigo-200' : 'border-transparent hover:bg-slate-50' ?>">
                        <span class="drag-handle text-slate-300 group-hover:text-slate-400 select-none">⠿</span>
                        <a href="<?= url_self((int)$c['id']) ?>" class="flex-1 text-sm font-semibold <?= $c['is_active'] ? 'text-slate-700' : 'text-slate-400 line-through' ?>">
                            <?= htmlspecialchars($c['name']) ?>
                        </a>
                        <?= owner_badge($c, $ownerNames) ?>
                        <span class="text-[10px] bg-slate-100 text-slate-500 rounded-full px-2 py-0.5"><?= (int)($subjCounts[$c['id']] ?? 0) ?></span>
                        <form method="post" class="hidden group-hover:flex items-center gap-1">
                            <input type="hidden" name="type" value="category"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button name="toggle_lock" title="<?= $c['is_locked'] ? 'Kilidi Aç' : 'Kilitle/Onayla (global + silinemez)' ?>" class="text-slate-400 hover:text-indigo-500 text-xs"><?= $c['is_locked'] ? '🔓' : '🔒' ?></button>
                            <button name="toggle_item" title="Aktif/Pasif" class="text-slate-400 hover:text-amber-500 text-xs">👁️</button>
                            <?php if (!$c['is_locked']): ?>
                            <button name="delete_item" onclick="return confirm('Kategori ve TÜM alt ders/konuları silinecek. Emin misiniz?')" title="Sil" class="text-slate-400 hover:text-red-500 text-xs">🗑️</button>
                            <?php endif; ?>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" class="mt-4 flex gap-2">
                    <input name="name" required maxlength="100" placeholder="Yeni kategori" class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-300">
                    <button name="add_category" class="bg-indigo-600 text-white rounded-lg px-3 py-2 text-sm font-bold hover:bg-indigo-700">+</button>
                </form>
            </section>

            <!-- DERSLER -->
            <section class="lg:col-span-4 bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                    Dersler <?= $curCatName ? '— <span class="text-indigo-500">' . htmlspecialchars($curCatName) . '</span>' : '' ?>
                </h2>
                <?php if (!$curCat): ?>
                    <p class="text-sm text-slate-400">Önce soldan bir kategori seçin.</p>
                <?php else: ?>
                <ul id="subj-list" class="space-y-1.5 max-h-[60vh] overflow-y-auto pr-1">
                    <?php foreach ($subjects as $s): ?>
                    <li data-id="<?= $s['id'] ?>" class="group flex items-center gap-2 rounded-xl px-2 py-2 border <?= $s['id'] == $curSubj ? 'bg-indigo-50 border-indigo-200' : 'border-transparent hover:bg-slate-50' ?>">
                        <span class="drag-handle text-slate-300 group-hover:text-slate-400 select-none">⠿</span>
                        <a href="<?= url_self($curCat, (int)$s['id']) ?>" class="flex-1 text-sm font-medium <?= $s['is_active'] ? 'text-slate-700' : 'text-slate-400 line-through' ?>">
                            <?= htmlspecialchars($s['lesson_name']) ?>
                        </a>
                        <?= owner_badge($s, $ownerNames) ?>
                        <span class="text-[10px] bg-slate-100 text-slate-500 rounded-full px-2 py-0.5"><?= (int)($topicCounts[$s['id']] ?? 0) ?> konu</span>
                        <form method="post" class="hidden group-hover:flex items-center gap-1">
                            <input type="hidden" name="type" value="subject"><input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button name="toggle_lock" title="<?= $s['is_locked'] ? 'Kilidi Aç' : 'Kilitle/Onayla (global + silinemez)' ?>" class="text-slate-400 hover:text-indigo-500 text-xs"><?= $s['is_locked'] ? '🔓' : '🔒' ?></button>
                            <button name="toggle_item" title="Aktif/Pasif" class="text-slate-400 hover:text-amber-500 text-xs">👁️</button>
                            <?php if (!$s['is_locked']): ?>
                            <button name="delete_item" onclick="return confirm('Ders ve TÜM konuları silinecek. Emin misiniz?')" title="Sil" class="text-slate-400 hover:text-red-500 text-xs">🗑️</button>
                            <?php endif; ?>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" class="mt-4 flex gap-2">
                    <input type="hidden" name="category_id" value="<?= $curCat ?>">
                    <input name="name" required maxlength="100" placeholder="Yeni ders (örn. Matematik)" class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-300">
                    <button name="add_subject" class="bg-indigo-600 text-white rounded-lg px-3 py-2 text-sm font-bold hover:bg-indigo-700">+</button>
                </form>
                <?php endif; ?>
            </section>

            <!-- KONULAR -->
            <section class="lg:col-span-5 bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                <div class="flex items-center justify-between mb-3 gap-3">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Konular <?= $curSubjName ? '— <span class="text-indigo-500">' . htmlspecialchars($curSubjName) . '</span>' : '' ?>
                    </h2>
                    <?php if ($curSubj): ?>
                    <form method="get" class="flex gap-1">
                        <input type="hidden" name="cat" value="<?= $curCat ?>"><input type="hidden" name="subj" value="<?= $curSubj ?>">
                        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Konu ara..." class="border border-slate-200 rounded-lg px-3 py-1.5 text-xs w-36 outline-none focus:ring-2 focus:ring-indigo-300">
                        <button class="bg-slate-800 text-white rounded-lg px-2.5 py-1.5 text-xs">Ara</button>
                        <?php if ($search !== ''): ?><a href="<?= url_self($curCat, $curSubj) ?>" class="text-xs text-slate-400 self-center px-1">✕</a><?php endif; ?>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (!$curSubj): ?>
                    <p class="text-sm text-slate-400">Konuları görmek için ortadan bir ders seçin.</p>
                <?php else: ?>
                <ul id="topic-list" class="space-y-1 max-h-[55vh] overflow-y-auto pr-1">
                    <?php foreach ($topics as $t): ?>
                    <li data-id="<?= $t['id'] ?>" class="group flex items-center gap-2 rounded-lg px-2 py-1.5 border border-transparent hover:bg-slate-50 hover:border-slate-100">
                        <?php if ($search === ''): ?><span class="drag-handle text-slate-300 group-hover:text-slate-400 select-none text-sm">⠿</span><?php endif; ?>
                        <span class="flex-1 text-sm <?= $t['status'] ? 'text-slate-700' : 'text-slate-400 line-through' ?>"><?= htmlspecialchars($t['topic_name']) ?></span>
                        <?= owner_badge($t, $ownerNames) ?>
                        <form method="post" class="hidden group-hover:flex items-center gap-1">
                            <input type="hidden" name="type" value="topic"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button name="toggle_lock" title="<?= $t['is_locked'] ? 'Kilidi Aç' : 'Kilitle/Onayla (global + silinemez)' ?>" class="text-slate-400 hover:text-indigo-500 text-xs"><?= $t['is_locked'] ? '🔓' : '🔒' ?></button>
                            <button name="toggle_item" title="Aktif/Pasif" class="text-slate-400 hover:text-amber-500 text-xs">👁️</button>
                            <?php if (!$t['is_locked']): ?>
                            <button name="delete_item" onclick="return confirm('Konu silinecek. Emin misiniz?')" title="Sil" class="text-slate-400 hover:text-red-500 text-xs">🗑️</button>
                            <?php endif; ?>
                        </form>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$topics): ?><li class="text-sm text-slate-400 px-2 py-1">Kayıt bulunamadı.</li><?php endif; ?>
                </ul>
                <form method="post" class="mt-4">
                    <input type="hidden" name="subject_id" value="<?= $curSubj ?>">
                    <textarea name="names" required rows="3" placeholder="Yeni konu(lar) — her satıra bir konu yazın, toplu ekleyebilirsiniz" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-300"></textarea>
                    <button name="add_topic" class="mt-2 bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm font-bold hover:bg-indigo-700">+ Konu Ekle</button>
                </form>
                <?php endif; ?>
            </section>
        </div>

        <p class="text-[11px] text-slate-400 mt-6">
            ℹ️ Bu sistem eski "Müfredat &amp; Konu" (koçluk) yapısından bağımsızdır; oradaki verilere dokunmaz.
            Pasif yapılan öğeler öğretmen seçim ekranlarında görünmez, mevcut bağlantılar korunur.
        </p>
    </div>
</main>

<script>
function initSort(listId, type) {
    const el = document.getElementById(listId);
    if (!el || !window.Sortable) return;
    new Sortable(el, {
        handle: '.drag-handle', animation: 150,
        onEnd: function () {
            const ids = Array.from(el.querySelectorAll('li[data-id]')).map(li => li.dataset.id);
            const fd = new FormData();
            fd.append('ajax_reorder', '1'); fd.append('type', type); fd.append('ids', JSON.stringify(ids));
            fetch('education.php', { method: 'POST', body: fd });
        }
    });
}
initSort('cat-list', 'category');
initSort('subj-list', 'subject');
<?php if ($search === ''): ?>initSort('topic-list', 'topic');<?php endif; ?>
</script>
</body>
</html>
