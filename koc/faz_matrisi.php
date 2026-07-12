<?php
// koc/faz_matrisi.php — Koç geneli FAZ MATRİSİ
// Tüm öğrenciler × dersler tablosu: her hücrede faz + kapsama yüzdesi.
// Hangi öğrencinin hangi derste geride olduğu tek bakışta görünür.
// Hesap, analiz.php'deki müfredat kapsama/faz mantığıyla birebir aynıdır:
//   faz N = müfredattaki HER konuya en az N kez görev verilmesi;
//   Faz 1 %100 olunca otomatik Faz 2 sayılır… 5. faza kadar.

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';
include __DIR__ . '/../header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo "<script>window.location.href='../index.php';</script>";
    exit;
}
$teacher_id = (int)$_SESSION['user_id'];

// ── Öğrenciler ────────────────────────────────────────────────────────────────
$students = [];
try {
    $st = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.school_level, u.grade, u.track
        FROM users u
        JOIN coaching_relationships cr ON cr.student_id = u.id
        WHERE cr.teacher_id = ?
        ORDER BY u.first_name, u.last_name");
    $st->execute([$teacher_id]);
    $students = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $students = []; }

$liseStudents = array_values(array_filter($students, fn($s) => ($s['school_level'] ?? 'Lise') !== 'Ortaokul'));
$ortaStudents = array_values(array_filter($students, fn($s) => ($s['school_level'] ?? 'Lise') === 'Ortaokul'));

// ── Konu başına görev sayıları (tüm öğrenciler, 2 sorgu) ─────────────────────
// $cntEdu[student_id][edu_topic_id] = adet;  $cntOld[student_id][topic_id] = adet
$cntEdu = []; $cntOld = [];
if ($students) {
    $ids = array_map(fn($s) => (int)$s['id'], $students);
    $in  = implode(',', $ids);
    try {
        foreach ($pdo->query("SELECT student_id, edu_topic_id k, COUNT(*) c FROM schedule_items
                              WHERE edu_topic_id IS NOT NULL AND student_id IN ($in)
                              GROUP BY student_id, edu_topic_id") as $r) {
            $cntEdu[(int)$r['student_id']][(int)$r['k']] = (int)$r['c'];
        }
        foreach ($pdo->query("SELECT student_id, topic_id k, COUNT(*) c FROM schedule_items
                              WHERE topic_id IS NOT NULL AND student_id IN ($in)
                              GROUP BY student_id, topic_id") as $r) {
            $cntOld[(int)$r['student_id']][(int)$r['k']] = (int)$r['c'];
        }
    } catch (Throwable $e) {}
}

// ── Müfredat sütunları ────────────────────────────────────────────────────────
// Her sütun: ['key','name','category','src'=>'edu'|'old','topics'=>[id,...]]
$colsLise = []; $colsOrta = [];
try {
    // 1) YENİ müfredat (TYT/AYT) — Lise/Mezun matrisi ana sütunları
    $eduRows = $pdo->query("
        SELECT et.id topic_id, es.id subject_id, es.lesson_name, ec.name category
        FROM education_topics et
        JOIN education_subjects es ON et.subject_id = es.id
        JOIN education_categories ec ON es.category_id = ec.id
        WHERE ec.name IN ('TYT','AYT')
        ORDER BY ec.name, es.display_order, es.lesson_name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($eduRows as $r) {
        $key = 'edu_' . $r['subject_id'];
        if (!isset($colsLise[$key])) $colsLise[$key] = ['name' => $r['lesson_name'], 'category' => $r['category'], 'src' => 'edu', 'topics' => []];
        $colsLise[$key]['topics'][] = (int)$r['topic_id'];
    }

    // 2) ESKİ koçluk müfredatı — yalnızca hâlâ kullanılan dersler (görev varsa)
    $oldRows = $pdo->query("
        SELECT t.id topic_id, s.id subject_id, s.name lesson_name, s.category
        FROM coaching_topics t
        JOIN coaching_subjects s ON t.subject_id = s.id
        ORDER BY s.category, s.name")->fetchAll(PDO::FETCH_ASSOC);
    $oldCols = [];
    foreach ($oldRows as $r) {
        $key = 'old_' . $r['subject_id'];
        if (!isset($oldCols[$key])) $oldCols[$key] = ['name' => $r['lesson_name'], 'category' => $r['category'], 'src' => 'old', 'topics' => []];
        $oldCols[$key]['topics'][] = (int)$r['topic_id'];
    }
    $usedOld = function (array $col, array $group) use ($cntOld): bool {
        foreach ($group as $s) {
            $c = $cntOld[(int)$s['id']] ?? [];
            foreach ($col['topics'] as $tid) if (!empty($c[$tid])) return true;
        }
        return false;
    };
    foreach ($oldCols as $key => $col) {
        $cat = mb_strtoupper(trim($col['category'] ?? ''), 'UTF-8');
        if ($cat === 'LGS') {
            // LGS: Ortaokul matrisinin ana müfredatı — her zaman göster
            $colsOrta[$key] = $col;
        } elseif ($usedOld($col, $liseStudents)) {
            // TYT/AYT eski dersler: yalnızca görev verilmiş olanlar (geçmiş veri)
            $colsLise[$key] = $col;
        }
    }
} catch (Throwable $e) {}

// ── Alan (track) uygunluğu — analiz.php ile aynı kural ───────────────────────
$TRACK_AYT = [
    'Sayısal'      => ['matematik','geometri','fizik','kimya','biyoloji'],
    'Eşit Ağırlık' => ['matematik','geometri','edebiyat','tarih','coğrafya'],
    'Sözel'        => ['edebiyat','tarih','coğrafya','felsefe','din','psikoloji','sosyoloji','mantık'],
];
$trLower = fn($s) => mb_strtolower(str_replace(['İ','I'], ['i','ı'], (string)$s), 'UTF-8');
$fmRelevant = function (array $col, array $stu) use ($TRACK_AYT, $trLower): bool {
    if (mb_strtoupper(trim($col['category'] ?? ''), 'UTF-8') !== 'AYT') return true;
    $grade = trim((string)($stu['grade'] ?? ''));
    $track = trim((string)($stu['track'] ?? ''));
    if (in_array($grade, ['9','10'], true)) return false;       // 9-10: önce TYT
    if ($track === '' || !isset($TRACK_AYT[$track])) return true; // alan seçilmemiş → hepsi
    $n = $trLower($col['name']);
    foreach ($TRACK_AYT[$track] as $kw) if (mb_strpos($n, $kw) !== false) return true;
    return false;
};

// ── Hücre hesabı: faz + kapsama ──────────────────────────────────────────────
$fmCell = function (array $topicIds, array $cnts): array {
    $tot = count($topicIds);
    if ($tot === 0) return ['faz' => 0, 'pct' => 0, 'done' => 0, 'total' => 0];
    $asg = 0; $min = PHP_INT_MAX;
    foreach ($topicIds as $tid) {
        $c = (int)($cnts[$tid] ?? 0);
        if ($c > 0) $asg++;
        $min = min($min, $c);
    }
    if ($asg === 0) return ['faz' => 0, 'pct' => 0, 'done' => 0, 'total' => $tot];
    $faz  = min(5, $min + 1);
    $done = 0;
    foreach ($topicIds as $tid) if ((int)($cnts[$tid] ?? 0) >= $faz) $done++;
    return ['faz' => $faz, 'pct' => (int)round(100 * $done / $tot), 'done' => $done, 'total' => $tot];
};

// Matris satırlarını önceden hesapla (render sade kalsın)
$buildMatrix = function (array $group, array $cols) use ($fmCell, $fmRelevant, $cntEdu, $cntOld): array {
    $rows = [];
    foreach ($group as $stu) {
        $sidX = (int)$stu['id'];
        $cells = [];
        foreach ($cols as $key => $col) {
            $cnts = $col['src'] === 'edu' ? ($cntEdu[$sidX] ?? []) : ($cntOld[$sidX] ?? []);
            $cell = $fmCell($col['topics'], $cnts);
            $cell['relevant'] = $fmRelevant($col, $stu);
            $cells[$key] = $cell;
        }
        $rows[] = ['stu' => $stu, 'cells' => $cells];
    }
    return $rows;
};
$matLise = $buildMatrix($liseStudents, $colsLise);
$matOrta = $buildMatrix($ortaStudents, $colsOrta);

// Hücre görünümü (renk sınıfları)
function fm_cell_html(array $c): string {
    if (!$c['relevant']) {
        return '<td class="fm-td"><span class="fm-chip fm-off" title="Alan dışı — bu öğrencinin hedefi için sayılmaz">—</span></td>';
    }
    if ($c['total'] === 0 || $c['faz'] === 0) {
        return '<td class="fm-td"><span class="fm-chip fm-none" title="Bu derste henüz hiç görev verilmedi">·</span></td>';
    }
    $cls = $c['pct'] >= 67 ? 'fm-g' : ($c['pct'] >= 34 ? 'fm-a' : 'fm-r');
    $fazTag = $c['faz'] > 1 ? '<b class="fm-faz">F' . $c['faz'] . '</b>' : '';
    $tip = 'Faz ' . $c['faz'] . ' · ' . $c['done'] . '/' . $c['total'] . ' konu';
    return '<td class="fm-td"><span class="fm-chip ' . $cls . '" title="' . $tip . '">' . $fazTag . '%' . $c['pct'] . '</span></td>';
}
?>

<style>
    .fm-table { border-collapse: separate; border-spacing: 0; font-size: 12px; }
    .fm-table th, .fm-table td { border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9; padding: 6px 8px; white-space: nowrap; }
    .fm-table thead th { background: #223488; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; position: sticky; top: 0; z-index: 2; }
    .fm-table thead th .fm-cat { display: block; font-size: 8px; color: #ec9731; font-weight: 900; }
    .fm-sticky { position: sticky; left: 0; background: #fff; z-index: 1; box-shadow: 2px 0 0 #e2e8f0; }
    .fm-table thead .fm-sticky { background: #223488; z-index: 3; }
    .fm-td { text-align: center; }
    .fm-chip { display: inline-flex; align-items: center; gap: 3px; font-weight: 800; font-size: 11px; border-radius: 8px; padding: 2px 7px; }
    .fm-g    { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .fm-a    { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .fm-r    { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .fm-none { background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; min-width: 22px; justify-content: center; }
    .fm-off  { background: transparent; color: #cbd5e1; }
    .fm-faz  { font-size: 8px; background: #223488; color: #fff; border-radius: 4px; padding: 1px 3px; }
    .fm-scroll { overflow-x: auto; }
    .fm-scroll::-webkit-scrollbar { height: 8px; }
    .fm-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<div class="max-w-[98%] mx-auto py-6">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-xl font-black text-[#223488]">🗺️ Faz Matrisi</h1>
            <p class="text-xs text-slate-500 font-medium mt-1">Tüm öğrenciler × dersler — her hücrede müfredat fazı ve kapsama yüzdesi. Kırmızı hücreler geride kalınan dersleri gösterir.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-[10px] font-bold">
            <span class="fm-chip fm-g">%67+</span>
            <span class="fm-chip fm-a">%34-66</span>
            <span class="fm-chip fm-r">%0-33</span>
            <span class="fm-chip fm-none" title="Hiç görev verilmemiş">· görev yok</span>
            <span class="text-slate-400">— alan dışı</span>
            <span class="fm-chip" style="background:#eef2ff;color:#223488;border:1px solid #c7d2fe"><b class="fm-faz">F2</b> tekrar turu</span>
        </div>
    </div>

    <?php if (empty($students)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400 font-bold">Henüz öğrenciniz yok.</div>
    <?php endif; ?>

    <?php
    // İki grup: Lise/Mezun (TYT+AYT) ve Ortaokul (LGS)
    $groups = [];
    if ($liseStudents) $groups[] = ['title' => '🎓 Lise / Mezun', 'cols' => $colsLise, 'rows' => $matLise];
    if ($ortaStudents) $groups[] = ['title' => '🎒 Ortaokul (LGS)', 'cols' => $colsOrta, 'rows' => $matOrta];
    foreach ($groups as $g): if (empty($g['cols'])) continue; ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
            <h2 class="font-bold text-[#223488] text-sm uppercase tracking-wide"><?php echo $g['title']; ?></h2>
            <span class="text-[10px] font-bold text-slate-400"><?php echo count($g['rows']); ?> öğrenci · <?php echo count($g['cols']); ?> ders</span>
        </div>
        <div class="fm-scroll">
            <table class="fm-table w-full">
                <thead>
                    <tr>
                        <th class="fm-sticky text-left">Öğrenci</th>
                        <?php foreach ($g['cols'] as $col): ?>
                        <th title="<?php echo htmlspecialchars($col['name'] . ' (' . $col['category'] . ') · ' . count($col['topics']) . ' konu'); ?>">
                            <?php echo htmlspecialchars(mb_strimwidth($col['name'], 0, 14, '…', 'UTF-8')); ?>
                            <span class="fm-cat"><?php echo htmlspecialchars($col['category']); ?><?php echo $col['src'] === 'old' ? ' · ESKİ' : ''; ?></span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($g['rows'] as $row): $stu = $row['stu']; ?>
                    <tr class="hover:bg-slate-50/70">
                        <td class="fm-sticky">
                            <a href="../koc_paneli.php?student_id=<?php echo (int)$stu['id']; ?>" class="font-bold text-slate-700 hover:text-[#223488]" title="Öğrencinin paneline git">
                                <?php echo htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']); ?>
                            </a>
                            <span class="block text-[9px] font-bold text-slate-400">
                                <?php echo htmlspecialchars(trim(($stu['grade'] ? $stu['grade'] . '. sınıf' : '') . ($stu['track'] ? ' · ' . $stu['track'] : '')) ?: ($stu['school_level'] ?? '')); ?>
                            </span>
                        </td>
                        <?php foreach ($g['cols'] as $key => $col) echo fm_cell_html($row['cells'][$key]); ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <p class="text-[10px] text-slate-400 font-medium">
        Faz N = müfredattaki her konuya en az N kez görev verilmesi; Faz 1 %100 olunca otomatik Faz 2 başlar (5. faza kadar).
        AYT sütunları öğrencinin alanına/sınıfına göre değerlendirilir; alan dışı dersler "—" ile soluk gösterilir.
        "ESKİ" etiketli sütunlar eski koçluk müfredatından olup yalnızca görev verilmişse listelenir.
    </p>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
