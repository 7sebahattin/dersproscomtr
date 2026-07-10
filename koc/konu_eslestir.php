<?php
// koc/konu_eslestir.php — Eşleşmeyen (manuel girilmiş) görevleri müfredat konularına bağlar.
//
// NEDEN: custom_subject/custom_topic ile girilen görevler edu_topic_id taşımadığı
// için Analiz'de "Diğer" olarak kalır; kapsama/faz ve konu istatistikleri onları görmez.
// BU SAYFA: öğretmenin öğrencilerindeki eşleşmemiş görevleri (ders adı, konu adı,
// kayıt sayısı) gruplar, benzerliğe göre MUHTEMEL müfredat eşleşmesi önerir;
// tek tıkla ya da elle seçerek toplu eşleştirir (schedule_items.edu_topic_id güncellenir).
// Eski görev adları (custom_*) silinmez — görüntüde müfredat adı öncelik kazanır.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../education_lib.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['teacher','admin','superuser'], true)) {
    header("Location: ../index.php"); exit;
}
education_ensure_schema($pdo);

$uid     = (int)$_SESSION['user_id'];
$isAdmin = education_is_admin();
$message = ''; $error = '';

// TR duyarlı normalize (karşılaştırma için)
function ke_norm(string $s): string {
    $s = str_replace(['İ','I'], ['i','ı'], trim($s));
    return mb_strtolower(preg_replace('/\s+/u', ' ', $s), 'UTF-8');
}

// Eşleştirmeyi uygula: öğretmenin öğrencilerindeki, müfredata bağlı olmayan ve
// aynı (custom_subject, custom_topic) çiftini taşıyan TÜM görevleri günceller.
function ke_apply(PDO $pdo, int $uid, string $cs, string $ct, int $etid): int {
    $st = $pdo->prepare("
        UPDATE schedule_items si
        JOIN coaching_relationships cr ON cr.student_id = si.student_id AND cr.teacher_id = :tid
        SET si.edu_topic_id = :etid
        WHERE si.edu_topic_id IS NULL AND si.topic_id IS NULL
          AND COALESCE(si.custom_subject,'') = :cs
          AND COALESCE(si.custom_topic,'')  = :ct
    ");
    $st->execute([':tid' => $uid, ':etid' => $etid, ':cs' => $cs, ':ct' => $ct]);
    return $st->rowCount();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cs = (string)($_POST['cs'] ?? '');
        $ct = (string)($_POST['ct'] ?? '');

        if (isset($_POST['map_existing'])) {
            // Var olan müfredat konusuna bağla
            $etid = (int)($_POST['edu_topic_id'] ?? 0);
            $chk = $pdo->prepare("SELECT topic_name FROM education_topics WHERE id = ?");
            $chk->execute([$etid]);
            $tname = $chk->fetchColumn();
            if ($etid <= 0 || !$tname) throw new Exception('Geçerli bir müfredat konusu seçin.');
            $n = ke_apply($pdo, $uid, $cs, $ct, $etid);
            $message = "✅ $n görev \"" . htmlspecialchars($tname) . "\" konusuna bağlandı. Analiz/kapsama anında güncellendi.";
        }

        if (isset($_POST['map_new'])) {
            // Önce konuyu müfredata (seçilen derse, öğretmene ait ★) ekle, sonra bağla
            $subjId = (int)($_POST['subject_id'] ?? 0);
            $tname  = trim(mb_substr($_POST['new_topic_name'] ?? '', 0, 255));
            if ($subjId <= 0 || $tname === '') throw new Exception('Ders seçin ve konu adı girin.');
            $st = $pdo->prepare("SELECT COALESCE(MAX(display_order),0) FROM education_topics WHERE subject_id = ?");
            $st->execute([$subjId]);
            $order = (int)$st->fetchColumn() + 1;
            $pdo->prepare("INSERT INTO education_topics (subject_id, topic_name, display_order, status, created_by, is_locked) VALUES (?,?,?,1,?,0)")
                ->execute([$subjId, $tname, $order, $uid]);
            $etid = (int)$pdo->lastInsertId();
            $n = ke_apply($pdo, $uid, $cs, $ct, $etid);
            $message = "✅ \"" . htmlspecialchars($tname) . "\" müfredatınıza eklendi (★) ve $n görev bu konuya bağlandı.";
        }
    } catch (Throwable $e) {
        $error = "❌ " . $e->getMessage();
    }
}

// ── Eşleşmemiş görev grupları (bu öğretmenin öğrencileri) ─────────────────────
$groups = [];
try {
    $st = $pdo->prepare("
        SELECT COALESCE(si.custom_subject,'') AS cs,
               COALESCE(si.custom_topic,'')  AS ct,
               COUNT(*) AS n,
               MIN(si.date) AS d1, MAX(si.date) AS d2
        FROM schedule_items si
        JOIN coaching_relationships cr ON cr.student_id = si.student_id AND cr.teacher_id = ?
        WHERE si.edu_topic_id IS NULL AND si.topic_id IS NULL
          AND (COALESCE(si.custom_subject,'') <> '' OR COALESCE(si.custom_topic,'') <> '')
        GROUP BY COALESCE(si.custom_subject,''), COALESCE(si.custom_topic,'')
        ORDER BY n DESC, cs, ct
    ");
    $st->execute([$uid]);
    $groups = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// ── Müfredat düz listesi (öneri hesabı + JS seçiciler) ────────────────────────
$tree = education_get_full_tree($pdo, $uid, $isAdmin);
$flatTopics = []; // her konu: id, t (konu adı), s (ders), c (kategori), sid (ders id)
foreach ($tree as $c) {
    foreach ($c['subjects'] as $s) {
        foreach ($s['topics'] as $t) {
            $flatTopics[] = ['id' => (int)$t['id'], 't' => $t['topic_name'], 's' => $s['lesson_name'], 'c' => $c['name'], 'sid' => (int)$s['id']];
        }
    }
}

// Her grup için en iyi öneriyi hesapla (konu adı benzerliği + ders adı bonusu)
function ke_suggest(array $flatTopics, string $cs, string $ct): ?array {
    $nct = ke_norm($ct !== '' ? $ct : $cs);   // konu boşsa ders adıyla dene
    $ncs = ke_norm($cs);
    if ($nct === '') return null;
    $best = null; $bestScore = 0;
    foreach ($flatTopics as $ft) {
        $nt = ke_norm($ft['t']);
        if ($nt === $nct) { $score = 100; }
        elseif (mb_strpos($nt, $nct) !== false || mb_strpos($nct, $nt) !== false) { $score = 70; }
        else {
            similar_text($nct, $nt, $pct);
            $score = (int)$pct * 0.6; // saf benzerlik daha az güvenilir
        }
        if ($ncs !== '' && mb_strpos(ke_norm($ft['s']), $ncs) !== false) $score += 15;
        if ($score > $bestScore) { $bestScore = $score; $best = $ft; }
    }
    return ($best && $bestScore >= 55) ? array_merge($best, ['score' => min(100, (int)$bestScore)]) : null;
}

$B = defined('BASE_URL') ? BASE_URL : '';
require_once __DIR__ . '/../header.php';
?>
<div class="max-w-5xl mx-auto p-4 lg:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">🔗 Konuları Eşleştir</h1>
            <p class="text-sm text-slate-500 mt-1">Manuel girilmiş görevleri müfredat konularına bağla — Analiz, kapsama ve faz istatistikleri tam doğru hesaplansın.</p>
        </div>
        <a href="<?= $B ?>/koc/mufredat_v2.php" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-slate-50">← Müfredatım</a>
    </div>

    <?php if ($message): ?><div class="mb-5 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-bold"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="mb-5 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-bold"><?= $error ?></div><?php endif; ?>

    <?php if (empty($groups)): ?>
        <div class="bg-white rounded-2xl border border-dashed border-slate-200 p-12 text-center">
            <div class="text-4xl mb-3">🎉</div>
            <p class="font-bold text-slate-700">Eşleşmemiş görev yok!</p>
            <p class="text-sm text-slate-400 mt-1">Tüm görevler müfredat konularına bağlı — istatistikler eksiksiz.</p>
        </div>
    <?php else: ?>
        <p class="text-xs text-slate-400 mb-3 font-medium"><?= count($groups) ?> eşleşmemiş ders/konu grubu bulundu. Eşleştirme, aynı adla girilmiş <b>tüm</b> görevlere birden uygulanır.</p>
        <div class="space-y-3">
        <?php foreach ($groups as $i => $g):
            $sug = ke_suggest($flatTopics, $g['cs'], $g['ct']);
        ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[10px] font-black uppercase bg-slate-700 text-white rounded px-2 py-0.5"><?= htmlspecialchars($g['cs'] ?: '(ders yok)') ?></span>
                            <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($g['ct'] ?: '(konu yok)') ?></span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1"><b><?= (int)$g['n'] ?> görev</b> · <?= htmlspecialchars(date('d.m.Y', strtotime($g['d1']))) ?> – <?= htmlspecialchars(date('d.m.Y', strtotime($g['d2']))) ?></p>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <?php if ($sug): ?>
                        <form method="POST" class="flex items-center gap-2">
                            <input type="hidden" name="cs" value="<?= htmlspecialchars($g['cs'], ENT_QUOTES) ?>">
                            <input type="hidden" name="ct" value="<?= htmlspecialchars($g['ct'], ENT_QUOTES) ?>">
                            <input type="hidden" name="edu_topic_id" value="<?= (int)$sug['id'] ?>">
                            <span class="text-[11px] text-slate-500 max-w-[260px]">
                                Öneri: <b class="text-[#223488]"><?= htmlspecialchars($sug['c']) ?> › <?= htmlspecialchars($sug['s']) ?> › <?= htmlspecialchars($sug['t']) ?></b>
                                <span class="text-[9px] font-black <?= $sug['score'] >= 85 ? 'text-emerald-600' : 'text-amber-600' ?>">%<?= $sug['score'] ?></span>
                            </span>
                            <button type="submit" name="map_existing" value="1" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-3 py-2 rounded-xl transition whitespace-nowrap">✓ Eşleştir</button>
                        </form>
                        <?php else: ?>
                        <span class="text-[11px] text-slate-400 italic">Otomatik öneri bulunamadı</span>
                        <?php endif; ?>
                        <button type="button" onclick="keToggle(<?= $i ?>)" class="bg-white border border-slate-200 text-slate-600 text-xs font-bold px-3 py-2 rounded-xl hover:bg-slate-50 transition whitespace-nowrap">✎ Elle seç</button>
                    </div>
                </div>

                <!-- Elle seçim: kategori → ders → konu; ya da yeni konu olarak ekle -->
                <div id="ke-manual-<?= $i ?>" class="hidden mt-3 pt-3 border-t border-slate-100">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                        <select class="ke-cat w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5 outline-none focus:border-[#223488]" onchange="keCatChange(<?= $i ?>)"><option value="">Kategori...</option></select>
                        <select class="ke-subj w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5 outline-none focus:border-[#223488]" onchange="keSubjChange(<?= $i ?>)" disabled><option value="">Ders...</option></select>
                        <select class="ke-topic w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5 outline-none focus:border-[#223488]" disabled><option value="">Konu...</option></select>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <form method="POST" class="inline" onsubmit="return keFillTopic(this, <?= $i ?>)">
                            <input type="hidden" name="cs" value="<?= htmlspecialchars($g['cs'], ENT_QUOTES) ?>">
                            <input type="hidden" name="ct" value="<?= htmlspecialchars($g['ct'], ENT_QUOTES) ?>">
                            <input type="hidden" name="edu_topic_id" value="">
                            <button type="submit" name="map_existing" value="1" class="bg-[#223488] hover:bg-[#314595] text-white text-xs font-bold px-4 py-2 rounded-xl transition">Seçilen Konuya Eşleştir</button>
                        </form>
                        <span class="text-[11px] text-slate-300 font-bold">— veya —</span>
                        <form method="POST" class="inline flex items-center gap-2" onsubmit="return keFillSubj(this, <?= $i ?>)">
                            <input type="hidden" name="cs" value="<?= htmlspecialchars($g['cs'], ENT_QUOTES) ?>">
                            <input type="hidden" name="ct" value="<?= htmlspecialchars($g['ct'], ENT_QUOTES) ?>">
                            <input type="hidden" name="subject_id" value="">
                            <input type="text" name="new_topic_name" value="<?= htmlspecialchars($g['ct'] ?: $g['cs'], ENT_QUOTES) ?>" class="bg-white border border-amber-300 rounded-xl text-xs p-2 outline-none focus:border-amber-500 w-48" placeholder="Yeni konu adı">
                            <button type="submit" name="map_new" value="1" class="bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold px-3 py-2 rounded-xl transition whitespace-nowrap">➕ Seçilen derse yeni konu olarak ekle</button>
                        </form>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1.5">"Yeni konu": seçtiğin dersin altına ★ (size ait) konu olarak eklenir ve görevler ona bağlanır.</p>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Müfredat ağacı (PHP'den) — elle seçim kaskadları için
const KE_TREE = <?= json_encode(array_map(function($c){
    return ['id'=>(int)$c['id'], 'name'=>$c['name'], 'subjects'=>array_map(function($s){
        return ['id'=>(int)$s['id'], 'name'=>$s['lesson_name'], 'topics'=>array_map(function($t){
            return ['id'=>(int)$t['id'], 'name'=>$t['topic_name']];
        }, $s['topics'])];
    }, $c['subjects'])];
}, $tree), JSON_UNESCAPED_UNICODE) ?>;

function keToggle(i) {
    const box = document.getElementById('ke-manual-' + i);
    box.classList.toggle('hidden');
    const cat = box.querySelector('.ke-cat');
    if (!box.classList.contains('hidden') && cat.options.length <= 1) {
        cat.innerHTML = '<option value="">Kategori...</option>' + KE_TREE.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    }
}
function keCatChange(i) {
    const box = document.getElementById('ke-manual-' + i);
    const c = KE_TREE.find(x => x.id == box.querySelector('.ke-cat').value);
    const subj = box.querySelector('.ke-subj'), top = box.querySelector('.ke-topic');
    subj.innerHTML = '<option value="">Ders...</option>' + (c ? c.subjects.map(s => `<option value="${s.id}">${s.name}</option>`).join('') : '');
    subj.disabled = !c;
    top.innerHTML = '<option value="">Konu...</option>'; top.disabled = true;
}
function keSubjChange(i) {
    const box = document.getElementById('ke-manual-' + i);
    const c = KE_TREE.find(x => x.id == box.querySelector('.ke-cat').value);
    const s = c ? c.subjects.find(x => x.id == box.querySelector('.ke-subj').value) : null;
    const top = box.querySelector('.ke-topic');
    top.innerHTML = '<option value="">Konu...</option>' + (s ? s.topics.map(t => `<option value="${t.id}">${t.name}</option>`).join('') : '');
    top.disabled = !s;
}
function keFillTopic(form, i) {
    const v = document.getElementById('ke-manual-' + i).querySelector('.ke-topic').value;
    if (!v) { alert('Önce kategori → ders → konu seçin.'); return false; }
    form.querySelector('[name="edu_topic_id"]').value = v;
    return true;
}
function keFillSubj(form, i) {
    const v = document.getElementById('ke-manual-' + i).querySelector('.ke-subj').value;
    if (!v) { alert('Önce kategori → ders seçin.'); return false; }
    if (!form.querySelector('[name="new_topic_name"]').value.trim()) { alert('Yeni konu adı boş olamaz.'); return false; }
    form.querySelector('[name="subject_id"]').value = v;
    return true;
}
</script>

<?php include __DIR__ . '/../footer.php'; ?>
