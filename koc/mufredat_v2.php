<?php
// koc/mufredat_v2.php — Öğretmen için YENİ Müfredat sayfası (eski teacher_mufredat.php yerine)
// Öğretmen: kendi kategori/ders/konusunu ekler-düzenler-siler; kilitli/global içeriğe DOKUNAMAZ.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../education_lib.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['teacher','admin','superuser'], true)) {
    header("Location: ../index.php"); exit;
}
education_ensure_schema($pdo);

$uid     = (int)$_SESSION['user_id'];
$isAdmin = education_is_admin();
$message = '';

/** Yetki denetimiyle satır getir */
function edu_row(PDO $pdo, string $table, int $id): ?array {
    $st = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_category'])) {
            $name = trim(mb_substr($_POST['name'] ?? '', 0, 100));
            if ($name !== '') {
                $max = (int)$pdo->query("SELECT COALESCE(MAX(display_order),0) FROM education_categories")->fetchColumn();
                $pdo->prepare("INSERT INTO education_categories (name, display_order, created_by, is_locked) VALUES (?,?,?,0)")
                    ->execute([$name, $max + 1, $uid]);
                $message = "✅ Kategori eklendi (size ait).";
            }
        }
        if (isset($_POST['add_subject'])) {
            $catId = (int)$_POST['category_id'];
            $name  = trim(mb_substr($_POST['name'] ?? '', 0, 100));
            if ($catId > 0 && $name !== '') {
                $st = $pdo->prepare("SELECT COALESCE(MAX(display_order),0) FROM education_subjects WHERE category_id = ?");
                $st->execute([$catId]); $max = (int)$st->fetchColumn();
                $pdo->prepare("INSERT INTO education_subjects (category_id, lesson_name, display_order, created_by, is_locked) VALUES (?,?,?,?,0)")
                    ->execute([$catId, $name, $max + 1, $uid]);
                $message = "✅ Ders eklendi (size ait).";
            }
        }
        if (isset($_POST['add_topic'])) {
            $subjId = (int)$_POST['subject_id'];
            $names  = trim($_POST['names'] ?? '');
            if ($subjId > 0 && $names !== '') {
                $st = $pdo->prepare("SELECT COALESCE(MAX(display_order),0) FROM education_topics WHERE subject_id = ?");
                $st->execute([$subjId]); $order = (int)$st->fetchColumn();
                $ins = $pdo->prepare("INSERT INTO education_topics (subject_id, topic_name, display_order, status, created_by, is_locked) VALUES (?,?,?,1,?,0)");
                $added = 0;
                foreach (preg_split('/\r\n|\r|\n/', $names) as $line) {
                    $line = trim(mb_substr($line, 0, 255));
                    if ($line !== '') { $ins->execute([$subjId, $line, ++$order, $uid]); $added++; }
                }
                $message = "✅ $added konu eklendi (size ait).";
            }
        }
        if (isset($_POST['delete_item'])) {
            $type = $_POST['type']; $id = (int)$_POST['id'];
            $tbl = ['category'=>'education_categories','subject'=>'education_subjects','topic'=>'education_topics'][$type] ?? null;
            if ($tbl && ($row = edu_row($pdo, $tbl, $id))) {
                if (education_can_delete($row, $uid, $isAdmin)) {
                    $pdo->prepare("DELETE FROM $tbl WHERE id = ?")->execute([$id]);
                    $message = "🗑️ Silindi.";
                } else {
                    $message = "🔒 Bu öğe kilitli/başkasına ait — silemezsiniz.";
                }
            }
        }
        if (isset($_POST['rename_item'])) {
            $type = $_POST['type']; $id = (int)$_POST['id'];
            $name = trim(mb_substr($_POST['name'] ?? '', 0, 255));
            $map = ['category'=>['education_categories','name'],'subject'=>['education_subjects','lesson_name'],'topic'=>['education_topics','topic_name']];
            if (isset($map[$type]) && $name !== '' && ($row = edu_row($pdo, $map[$type][0], $id))) {
                if (education_can_edit_item($row, $uid, $isAdmin)) {
                    $pdo->prepare("UPDATE {$map[$type][0]} SET {$map[$type][1]} = ? WHERE id = ?")->execute([$name, $id]);
                    $message = "✏️ Güncellendi.";
                } else {
                    $message = "🔒 Bu öğeyi düzenleyemezsiniz.";
                }
            }
        }
    } catch (Throwable $e) {
        $message = "❌ İşlem hatası (kayıt zaten mevcut olabilir).";
    }
}

// Görünüm (öğretmen kapsamı: kendi + kilitli/global + admin)
$categories = education_get_categories($pdo, false, $uid, $isAdmin);
$curCat  = (int)($_GET['cat'] ?? ($categories[0]['id'] ?? 0));
$subjects = $curCat ? education_get_subjects($pdo, $curCat, false, $uid, $isAdmin) : [];
$curSubj = (int)($_GET['subj'] ?? 0);
if ($curSubj && !in_array($curSubj, array_column($subjects, 'id'))) $curSubj = 0;
$topics  = $curSubj ? education_get_topics($pdo, $curSubj, false, '', 1000, 0, $uid, $isAdmin) : [];

function mine_badge(array $row, int $uid): string {
    if ((int)($row['is_locked'] ?? 0) === 1) return '<span title="Genel/onaylı — düzenlenemez" class="text-[10px]">🔒</span>';
    if ((int)($row['created_by'] ?? 0) === $uid) return '<span title="Size ait" class="text-[9px] font-bold bg-amber-100 text-amber-700 rounded-full px-1.5 py-0.5">★ benim</span>';
    return '';
}
function u2(int $cat=0,int $subj=0): string { $p=[]; if($cat)$p['cat']=$cat; if($subj)$p['subj']=$subj; return 'mufredat_v2.php'.($p?('?'.http_build_query($p)):''); }
$B = defined('BASE_URL') ? BASE_URL : '';
?>
<?php require_once __DIR__ . '/../header.php'; ?>
<div class="max-w-7xl mx-auto p-4 lg:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">📚 Müfredatım</h1>
            <p class="text-sm text-slate-500 mt-1">Kendi kategori, ders ve konularınızı ekleyin. 🔒 genel (onaylı) içeriktir, değiştirilemez; ★ size aittir.</p>
        </div>
        <div class="flex gap-2">
            <?php
            // Eşleşmemiş (manuel girilmiş) görev grubu sayısı — rozet
            $unmatchedCnt = 0;
            try {
                $ust = $pdo->prepare("SELECT COUNT(DISTINCT CONCAT(COALESCE(si.custom_subject,''),'|',COALESCE(si.custom_topic,'')))
                    FROM schedule_items si
                    JOIN coaching_relationships cr ON cr.student_id = si.student_id AND cr.teacher_id = ?
                    WHERE si.edu_topic_id IS NULL AND si.topic_id IS NULL
                      AND (COALESCE(si.custom_subject,'') <> '' OR COALESCE(si.custom_topic,'') <> '')");
                $ust->execute([$uid]);
                $unmatchedCnt = (int)$ust->fetchColumn();
            } catch (Throwable $e) {}
            ?>
            <a href="<?= $B ?>/koc/konu_eslestir.php" class="relative bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-emerald-700 shadow-sm" title="Manuel girilmiş görevleri müfredat konularına bağla">
                🧩 Konuları Eşleştir
                <?php if ($unmatchedCnt > 0): ?>
                <span class="absolute -top-2 -right-2 min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-black leading-5 text-center"><?= $unmatchedCnt ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $B ?>/education_kaynaklar.php" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-sm">🔗 Kaynak Havuzu</a>
            <a href="<?= $B ?>/index.php" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-slate-50">Panel</a>
        </div>
    </div>

    <?php if ($message): ?><div class="bg-white border border-indigo-100 text-slate-700 p-3 rounded-xl mb-5 text-sm shadow-sm"><?= $message ?></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- KATEGORİLER -->
        <section class="lg:col-span-3 bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Kategoriler</h2>
            <ul class="space-y-1.5">
                <?php foreach ($categories as $c): ?>
                <li class="group flex items-center gap-2 rounded-xl px-2 py-2 border <?= $c['id']==$curCat?'bg-indigo-50 border-indigo-200':'border-transparent hover:bg-slate-50' ?>">
                    <a href="<?= u2((int)$c['id']) ?>" class="flex-1 text-sm font-semibold text-slate-700"><?= htmlspecialchars($c['name']) ?></a>
                    <?= mine_badge($c, $uid) ?>
                    <?php if (education_can_delete($c, $uid, $isAdmin)): ?>
                    <form method="post" onsubmit="return confirm('Kategori ve alt öğeleri silinecek?')">
                        <input type="hidden" name="type" value="category"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button name="delete_item" class="hidden group-hover:block text-slate-300 hover:text-red-500 text-xs">🗑️</button>
                    </form>
                    <?php endif; ?>
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
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Dersler</h2>
            <?php if (!$curCat): ?><p class="text-sm text-slate-400">Kategori seçin.</p><?php else: ?>
            <ul class="space-y-1.5 max-h-[60vh] overflow-y-auto pr-1">
                <?php foreach ($subjects as $s): ?>
                <li class="group flex items-center gap-2 rounded-xl px-2 py-2 border <?= $s['id']==$curSubj?'bg-indigo-50 border-indigo-200':'border-transparent hover:bg-slate-50' ?>">
                    <a href="<?= u2($curCat,(int)$s['id']) ?>" class="flex-1 text-sm font-medium text-slate-700"><?= htmlspecialchars($s['lesson_name']) ?></a>
                    <?= mine_badge($s, $uid) ?>
                    <?php if (education_can_delete($s, $uid, $isAdmin)): ?>
                    <form method="post" onsubmit="return confirm('Ders ve konuları silinecek?')">
                        <input type="hidden" name="type" value="subject"><input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <button name="delete_item" class="hidden group-hover:block text-slate-300 hover:text-red-500 text-xs">🗑️</button>
                    </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <form method="post" class="mt-4 flex gap-2">
                <input type="hidden" name="category_id" value="<?= $curCat ?>">
                <input name="name" required maxlength="100" placeholder="Yeni ders" class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-300">
                <button name="add_subject" class="bg-indigo-600 text-white rounded-lg px-3 py-2 text-sm font-bold hover:bg-indigo-700">+</button>
            </form>
            <?php endif; ?>
        </section>

        <!-- KONULAR -->
        <section class="lg:col-span-5 bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Konular</h2>
            <?php if (!$curSubj): ?><p class="text-sm text-slate-400">Ders seçin.</p><?php else: ?>
            <ul class="space-y-1 max-h-[55vh] overflow-y-auto pr-1">
                <?php foreach ($topics as $t): ?>
                <li class="group flex items-center gap-2 rounded-lg px-2 py-1.5 border border-transparent hover:bg-slate-50">
                    <span class="flex-1 text-sm text-slate-700"><?= htmlspecialchars($t['topic_name']) ?></span>
                    <?= mine_badge($t, $uid) ?>
                    <?php if (education_can_delete($t, $uid, $isAdmin)): ?>
                    <form method="post">
                        <input type="hidden" name="type" value="topic"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button name="delete_item" class="hidden group-hover:block text-slate-300 hover:text-red-500 text-xs">🗑️</button>
                    </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
                <?php if (!$topics): ?><li class="text-sm text-slate-400 px-2 py-1">Konu yok.</li><?php endif; ?>
            </ul>
            <form method="post" class="mt-4">
                <input type="hidden" name="subject_id" value="<?= $curSubj ?>">
                <textarea name="names" required rows="3" placeholder="Yeni konu(lar) — her satıra bir konu" class="js-upper w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-300"></textarea>
                <button name="add_topic" class="mt-2 bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm font-bold hover:bg-indigo-700">+ Konu Ekle</button>
            </form>
            <?php endif; ?>
        </section>
    </div>
    <p class="text-[11px] text-slate-400 mt-6">🔒 Genel (admin onaylı) içerik herkese açıktır ve yalnızca admin değiştirebilir. ★ ile işaretli öğeler size aittir; dilediğiniz gibi düzenleyip silebilirsiniz.</p>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
