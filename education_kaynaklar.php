<?php
// education_kaynaklar.php — YENİ Kaynak Havuzu (kaynak <-> müfredat konusu bağlama)
// Eski "Kaynaklar" (materials) ve görev sisteminden TAMAMEN BAĞIMSIZDIR.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/education_lib.php';

if (!education_can_manage_resources()) {
    header("Location: index.php"); exit;
}

education_ensure_schema($pdo);

$uid     = (int)$_SESSION['user_id'];
$isAdmin = education_is_admin();
$message = '';

$typeLabels = ['kitap' => '📕 Kitap', 'deneme' => '📝 Deneme', 'pdf' => '📄 PDF', 'video' => '🎬 Video', 'diger' => '📦 Diğer'];

/** Kaynağı düzenleme yetkisi: sahibi veya admin. Kilitliyse yalnızca admin. */
function can_edit_resource(array $r, int $uid, bool $isAdmin): bool {
    if ((int)($r['is_locked'] ?? 0) === 1) return $isAdmin;
    return $isAdmin || (int)($r['created_by'] ?? 0) === $uid;
}

/** Kaynağı silme yetkisi: kilitliyse HİÇ KİMSE (admin önce kilidi açmalı). */
function can_delete_resource(array $r, int $uid, bool $isAdmin): bool {
    if ((int)($r['is_locked'] ?? 0) === 1) return false;
    return $isAdmin || (int)($r['created_by'] ?? 0) === $uid;
}

// ── Kaydet / Sil ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_resource'])) {
            $id    = (int)($_POST['resource_id'] ?? 0);
            $title = trim(mb_substr($_POST['title'] ?? '', 0, 255));
            $type  = array_key_exists($_POST['type'] ?? '', $typeLabels) ? $_POST['type'] : 'kitap';
            $desc  = trim($_POST['description'] ?? '');
            $url   = trim(mb_substr($_POST['external_url'] ?? '', 0, 500));
            $topics = $_POST['topics'] ?? [];

            if ($title === '') {
                $message = "❌ Kaynak adı zorunludur.";
            } else {
                if ($id > 0) {
                    $st = $pdo->prepare("SELECT * FROM education_resources WHERE id = ?");
                    $st->execute([$id]); $existing = $st->fetch();
                    if (!$existing || !can_edit_resource($existing, $uid, $isAdmin)) {
                        $message = "❌ Bu kaynağı düzenleme yetkiniz yok.";
                    } else {
                        $pdo->prepare("UPDATE education_resources SET title=?, type=?, description=?, external_url=? WHERE id=?")
                            ->execute([$title, $type, $desc ?: null, $url ?: null, $id]);
                        education_set_resource_topics($pdo, $id, (array)$topics);
                        $message = "✅ Kaynak güncellendi (" . count((array)$topics) . " konu bağlı).";
                    }
                } else {
                    $pdo->prepare("INSERT INTO education_resources (title, type, description, external_url, created_by) VALUES (?,?,?,?,?)")
                        ->execute([$title, $type, $desc ?: null, $url ?: null, $uid]);
                    $newId = (int)$pdo->lastInsertId();
                    education_set_resource_topics($pdo, $newId, (array)$topics);
                    $message = "✅ Kaynak eklendi (" . count((array)$topics) . " konu bağlı).";
                }
            }
        }
        if (isset($_POST['delete_resource'])) {
            $id = (int)$_POST['resource_id'];
            $st = $pdo->prepare("SELECT * FROM education_resources WHERE id = ?");
            $st->execute([$id]); $existing = $st->fetch();
            if ($existing && can_delete_resource($existing, $uid, $isAdmin)) {
                $pdo->prepare("DELETE FROM resource_topics WHERE resource_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM education_resources WHERE id = ?")->execute([$id]);
                $message = "🗑️ Kaynak silindi.";
            } elseif ($existing && (int)$existing['is_locked'] === 1) {
                $message = "🔒 Kilitli (onaylı) kaynak silinemez.";
            }
        }
        if (isset($_POST['toggle_resource_lock']) && $isAdmin) {
            $id = (int)$_POST['resource_id'];
            $pdo->prepare("UPDATE education_resources SET is_locked = 1 - is_locked WHERE id = ?")->execute([$id]);
            $message = "🔐 Kaynak kilit durumu değiştirildi.";
        }
    } catch (Throwable $e) {
        $message = "❌ İşlem sırasında hata oluştu.";
    }
}

// ── Görünüm: liste mi form mu? ──────────────────────────────────────────────
$editId  = (int)($_GET['edit'] ?? 0);
$showForm = isset($_GET['new']) || $editId > 0;

$editRes = null; $editTopicIds = [];
if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM education_resources WHERE id = ?");
    $st->execute([$editId]); $editRes = $st->fetch(PDO::FETCH_ASSOC);
    if (!$editRes || !can_edit_resource($editRes, $uid, $isAdmin)) { $editRes = null; $showForm = false; }
    else {
        $st = $pdo->prepare("SELECT topic_id FROM resource_topics WHERE resource_id = ?");
        $st->execute([$editId]);
        $editTopicIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}

// Havuz kapsamı sekmesi: mine (Benim) / global (Genel-Onaylı) / all (admin: hepsi)
$scope = in_array($_GET['scope'] ?? '', ['mine','global','all'], true) ? $_GET['scope'] : 'mine';
if ($scope === 'all' && !$isAdmin) $scope = 'mine';

// Liste filtreleri
$filters = [
    'q'           => trim($_GET['q'] ?? ''),
    'topic_q'     => trim($_GET['topic_q'] ?? ''),
    'category_id' => (int)($_GET['fcat'] ?? 0),
    'subject_id'  => (int)($_GET['fsubj'] ?? 0),
    'type'        => in_array($_GET['ftype'] ?? '', array_keys($typeLabels)) ? $_GET['ftype'] : '',
    'scope'       => $scope === 'all' ? '' : $scope,
    'viewer_id'   => $uid,
    'is_admin'    => $isAdmin,
];
$resources = $showForm ? [] : education_list_resources($pdo, $filters);

// Form için tam müfredat ağacı (3 sorgu, N+1 yok)
$tree = $showForm ? education_get_full_tree($pdo) : [];

// Liste filtre seçenekleri
$allCats = education_get_categories($pdo, true);
$filterSubjects = $filters['category_id'] ? education_get_subjects($pdo, $filters['category_id'], true) : [];

$pageTitle = "Kaynak Havuzu";
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kaynak Havuzu — DersPROS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Poppins',sans-serif}</style>
</head>
<body class="bg-slate-100 min-h-screen p-4 lg:p-8">
<div class="max-w-6xl mx-auto">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">🗂️ Kaynak Havuzu</h1>
            <p class="text-sm text-slate-500 mt-1">Kaynaklarınızı müfredat konularına bağlayın; görevlerde yalnızca bağlı konular listelenir.</p>
        </div>
        <div class="flex gap-2">
            <?php if ($showForm): ?>
                <a href="education_kaynaklar.php" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-slate-50">← Listeye Dön</a>
            <?php else: ?>
                <a href="?new=1" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-sm">+ Yeni Kaynak</a>
            <?php endif; ?>
            <a href="index.php" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-slate-50">Panel</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="bg-white border border-indigo-100 text-slate-700 p-3 rounded-xl mb-5 text-sm shadow-sm"><?= $message ?></div>
    <?php endif; ?>

<?php if ($showForm): ?>
    <!-- ═══════════ KAYNAK FORMU ═══════════ -->
    <form method="post" class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <input type="hidden" name="resource_id" value="<?= $editRes ? (int)$editRes['id'] : 0 ?>">

        <!-- Sol: kaynak bilgileri -->
        <div class="lg:col-span-4 bg-white rounded-2xl shadow-sm border border-slate-100 p-5 h-fit">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4"><?= $editRes ? 'Kaynağı Düzenle' : 'Yeni Kaynak' ?></h2>
            <label class="block text-xs font-bold text-slate-500 mb-1">Kaynak Adı *</label>
            <input name="title" required maxlength="255" value="<?= htmlspecialchars($editRes['title'] ?? '') ?>"
                   placeholder="örn. 345 TYT Matematik"
                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm mb-4 outline-none focus:ring-2 focus:ring-indigo-300">

            <label class="block text-xs font-bold text-slate-500 mb-1">Tür</label>
            <select name="type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm mb-4 bg-white outline-none focus:ring-2 focus:ring-indigo-300">
                <?php foreach ($typeLabels as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= ($editRes['type'] ?? 'kitap') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>

            <label class="block text-xs font-bold text-slate-500 mb-1">Bağlantı (isteğe bağlı)</label>
            <input name="external_url" maxlength="500" value="<?= htmlspecialchars($editRes['external_url'] ?? '') ?>"
                   placeholder="https://..." class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm mb-4 outline-none focus:ring-2 focus:ring-indigo-300">

            <label class="block text-xs font-bold text-slate-500 mb-1">Açıklama (isteğe bağlı)</label>
            <textarea name="description" rows="3" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm mb-4 outline-none focus:ring-2 focus:ring-indigo-300"><?= htmlspecialchars($editRes['description'] ?? '') ?></textarea>

            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-3 text-xs text-indigo-700 mb-4">
                Seçili konu: <span id="selCount" class="font-black">0</span>
            </div>
            <button name="save_resource" class="w-full bg-indigo-600 text-white rounded-xl py-3 text-sm font-bold hover:bg-indigo-700 shadow-sm">💾 Kaydet</button>
        </div>

        <!-- Sağ: konu seçimi (accordion) -->
        <div class="lg:col-span-8 bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex-1">Müfredat Konuları</h2>
                <input id="topicSearch" placeholder="Konu ara... (örn. Fonksiyonlar)"
                       class="border border-slate-200 rounded-xl px-3 py-2 text-sm w-56 outline-none focus:ring-2 focus:ring-indigo-300">
                <label class="flex items-center gap-1.5 text-xs font-bold text-slate-500 cursor-pointer select-none bg-slate-50 border border-slate-200 rounded-xl px-3 py-2">
                    <input type="checkbox" id="onlySelected" class="rounded text-indigo-600"> Seçilenleri Göster
                </label>
            </div>

            <div class="space-y-2" id="accordion">
                <?php foreach ($tree as $cat): if (!$cat['subjects']) continue; ?>
                <details class="cat-block border border-slate-200 rounded-xl overflow-hidden">
                    <summary class="cursor-pointer select-none bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700 flex items-center justify-between">
                        <span>🎯 <?= htmlspecialchars($cat['name']) ?></span>
                        <span class="cat-sel text-[10px] bg-indigo-100 text-indigo-600 rounded-full px-2 py-0.5"></span>
                    </summary>
                    <div class="p-3 space-y-2">
                        <?php foreach ($cat['subjects'] as $subj): if (!$subj['topics']) continue; ?>
                        <details class="subj-block border border-slate-100 rounded-lg overflow-hidden">
                            <summary class="cursor-pointer select-none bg-white px-3 py-2.5 text-sm font-semibold text-slate-600 flex items-center justify-between border-b border-slate-100">
                                <span>📘 <?= htmlspecialchars($subj['lesson_name']) ?></span>
                                <span class="flex items-center gap-2">
                                    <span class="subj-sel text-[10px] bg-indigo-100 text-indigo-600 rounded-full px-2 py-0.5"></span>
                                    <button type="button" class="btn-all text-[10px] font-bold text-emerald-600 hover:underline">Hepsini Seç</button>
                                    <button type="button" class="btn-none text-[10px] font-bold text-red-400 hover:underline">Temizle</button>
                                </span>
                            </summary>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 p-3">
                                <?php foreach ($subj['topics'] as $t): ?>
                                <label class="topic-row flex items-center gap-2 text-sm text-slate-600 rounded-lg px-2 py-1.5 hover:bg-indigo-50 cursor-pointer"
                                       data-name="<?= htmlspecialchars(mb_strtolower($t['topic_name'], 'UTF-8')) ?>">
                                    <input type="checkbox" name="topics[]" value="<?= (int)$t['id'] ?>"
                                           class="topic-cb rounded text-indigo-600 focus:ring-indigo-400"
                                           <?= in_array((int)$t['id'], $editTopicIds) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($t['topic_name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
    </form>

    <script>
    (function () {
        const cbs = () => Array.from(document.querySelectorAll('.topic-cb'));

        function refresh() {
            let total = 0;
            document.querySelectorAll('.subj-block').forEach(sb => {
                const c = sb.querySelectorAll('.topic-cb:checked').length;
                sb.querySelector('.subj-sel').textContent = c ? c + ' seçili' : '';
                total += c;
            });
            document.querySelectorAll('.cat-block').forEach(cb => {
                const c = cb.querySelectorAll('.topic-cb:checked').length;
                cb.querySelector('.cat-sel').textContent = c ? c + ' seçili' : '';
            });
            document.getElementById('selCount').textContent = total;
        }

        document.getElementById('accordion').addEventListener('change', e => {
            if (e.target.classList.contains('topic-cb')) refresh();
        });

        // Ders bazında Hepsini Seç / Temizle (yalnız görünür satırlar)
        document.querySelectorAll('.subj-block').forEach(sb => {
            sb.querySelector('.btn-all').addEventListener('click', e => {
                e.preventDefault(); e.stopPropagation();
                sb.querySelectorAll('.topic-row:not(.hidden) .topic-cb').forEach(c => c.checked = true);
                refresh();
            });
            sb.querySelector('.btn-none').addEventListener('click', e => {
                e.preventDefault(); e.stopPropagation();
                sb.querySelectorAll('.topic-row .topic-cb').forEach(c => c.checked = false);
                refresh();
            });
        });

        // Arama + "Seçilenleri Göster" filtresi
        const search = document.getElementById('topicSearch');
        const onlySel = document.getElementById('onlySelected');
        function applyFilter() {
            const q = search.value.trim().toLocaleLowerCase('tr');
            const sel = onlySel.checked;
            document.querySelectorAll('.topic-row').forEach(row => {
                const okQ = !q || row.dataset.name.includes(q);
                const okS = !sel || row.querySelector('.topic-cb').checked;
                row.classList.toggle('hidden', !(okQ && okS));
            });
            // Eşleşen konusu olan blokları aç, olmayanları kapat (arama varken)
            document.querySelectorAll('.subj-block').forEach(sb => {
                const visible = sb.querySelectorAll('.topic-row:not(.hidden)').length;
                if (q || sel) { sb.open = visible > 0; sb.classList.toggle('hidden', !visible); }
                else { sb.classList.remove('hidden'); }
            });
            document.querySelectorAll('.cat-block').forEach(cb => {
                const visible = cb.querySelectorAll('.subj-block:not(.hidden)').length;
                if (q || sel) { cb.open = visible > 0; cb.classList.toggle('hidden', !visible); }
                else { cb.classList.remove('hidden'); cb.open = false; }
            });
        }
        search.addEventListener('input', applyFilter);
        onlySel.addEventListener('change', applyFilter);

        refresh();
    })();
    </script>

<?php else: ?>
    <!-- ═══════════ KAYNAK LİSTESİ ═══════════ -->
    <!-- Kapsam sekmeleri: Benim Havuzum ↔ Genel (Onaylı) -->
    <div class="flex gap-2 mb-4">
        <a href="?scope=mine" class="px-4 py-2 rounded-xl text-sm font-bold border <?= $scope==='mine' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">👤 Benim Havuzum</a>
        <a href="?scope=global" class="px-4 py-2 rounded-xl text-sm font-bold border <?= $scope==='global' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">🌍 Genel Havuz (Onaylı)</a>
        <?php if ($isAdmin): ?>
        <a href="?scope=all" class="px-4 py-2 rounded-xl text-sm font-bold border <?= $scope==='all' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">🗂️ Tümü (Admin)</a>
        <?php endif; ?>
    </div>
    <form method="get" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-5 grid grid-cols-2 md:grid-cols-6 gap-2 items-end">
        <input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>">
        <div class="col-span-2 md:col-span-1">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kaynak Adı</label>
            <input name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Ara..."
                   class="w-full border border-slate-200 rounded-lg px-2.5 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-300">
        </div>
        <div class="col-span-2 md:col-span-1">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Konu (Global Arama)</label>
            <input name="topic_q" value="<?= htmlspecialchars($filters['topic_q']) ?>" placeholder="örn. Fonksiyonlar"
                   class="w-full border border-slate-200 rounded-lg px-2.5 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-300">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kategori</label>
            <select name="fcat" onchange="this.form.submit()" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm bg-white">
                <option value="">Tümü</option>
                <?php foreach ($allCats as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filters['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Ders</label>
            <select name="fsubj" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm bg-white">
                <option value="">Tümü</option>
                <?php foreach ($filterSubjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $filters['subject_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['lesson_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tür</label>
            <select name="ftype" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm bg-white">
                <option value="">Tümü</option>
                <?php foreach ($typeLabels as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $filters['type'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button class="flex-1 bg-slate-800 text-white rounded-lg px-3 py-2 text-sm font-bold hover:bg-slate-700">Filtrele</button>
            <a href="education_kaynaklar.php" class="bg-slate-100 text-slate-500 rounded-lg px-3 py-2 text-sm font-bold hover:bg-slate-200">✕</a>
        </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($resources as $r): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex flex-col">
            <div class="flex items-start justify-between gap-2">
                <span class="text-[10px] font-bold bg-indigo-50 text-indigo-600 rounded-full px-2.5 py-1"><?= $typeLabels[$r['type']] ?? $r['type'] ?></span>
                <span class="text-[10px] text-slate-400"><?= date('d.m.Y', strtotime($r['created_at'])) ?></span>
            </div>
            <h3 class="font-bold text-slate-800 mt-2 text-sm leading-snug"><?= htmlspecialchars($r['title']) ?></h3>
            <?php if (!empty($r['description'])): ?>
                <p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= htmlspecialchars($r['description']) ?></p>
            <?php endif; ?>
            <div class="flex items-center justify-between mt-auto pt-4">
                <span class="text-[11px] font-bold text-slate-500 bg-slate-50 border border-slate-100 rounded-full px-2.5 py-1">
                    🔗 <?= (int)$r['topic_count'] ?> konu
                </span>
                <div class="flex items-center gap-2">
                    <?php if (!empty($r['external_url'])): ?>
                        <a href="<?= htmlspecialchars($r['external_url']) ?>" target="_blank" rel="noopener" class="text-[11px] font-bold text-blue-500 hover:underline">Bağlantı ↗</a>
                    <?php endif; ?>
                    <?php if ((int)($r['is_locked'] ?? 0) === 1): ?>
                        <span title="Onaylı/global — silinemez" class="text-[11px]">🔒</span>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                        <form method="post" class="inline">
                            <input type="hidden" name="resource_id" value="<?= $r['id'] ?>">
                            <button name="toggle_resource_lock" title="<?= ($r['is_locked'] ?? 0) ? 'Kilidi Aç' : 'Kilitle/Onayla' ?>" class="text-[11px] font-bold text-indigo-400 hover:underline"><?= ($r['is_locked'] ?? 0) ? 'Kilidi Aç' : 'Onayla' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (can_edit_resource($r, $uid, $isAdmin)): ?>
                        <a href="?edit=<?= $r['id'] ?>" class="text-[11px] font-bold text-indigo-500 hover:underline">Düzenle</a>
                    <?php endif; ?>
                    <?php if (can_delete_resource($r, $uid, $isAdmin)): ?>
                        <form method="post" class="inline" onsubmit="return confirm('Kaynak ve konu bağlantıları silinecek. Emin misiniz?')">
                            <input type="hidden" name="resource_id" value="<?= $r['id'] ?>">
                            <button name="delete_resource" class="text-[11px] font-bold text-red-400 hover:underline">Sil</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <p class="text-[10px] text-slate-300 mt-2">Ekleyen: <?= htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '—') ?></p>
        </div>
        <?php endforeach; ?>

        <?php if (!$resources): ?>
        <div class="col-span-full bg-white rounded-2xl border border-dashed border-slate-200 p-10 text-center text-slate-400 text-sm">
            Kayıt bulunamadı. <a href="?new=1" class="text-indigo-500 font-bold hover:underline">İlk kaynağı ekleyin →</a>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>
</body>
</html>
