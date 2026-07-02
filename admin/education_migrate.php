<?php
// education_migrate.php — ESKİ müfredat verisini (coaching_*) YENİ sisteme (education_*) taşır.
// GÜVENLİK: coaching_subjects / coaching_topics / schedule_items.topic_id ASLA değiştirilmez.
// Sadece EKLER: education_topic_map tablosu + schedule_items.edu_topic_id kolonu.
// Idempotent (tekrar çalıştırılabilir), geri alınabilir, birebir doğrulama raporlu.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once '../education_lib.php';

if (!education_is_admin()) { header("Location: ../index.php"); exit; }

education_ensure_schema($pdo);

// Göç için gereken ek yapı (idempotent)
$pdo->exec("CREATE TABLE IF NOT EXISTS education_topic_map (
    old_topic_id INT NOT NULL PRIMARY KEY,
    new_topic_id INT NOT NULL,
    KEY idx_map_new (new_topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

$cols = $pdo->query("SHOW COLUMNS FROM schedule_items")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('edu_topic_id', $cols)) {
    $pdo->exec("ALTER TABLE schedule_items ADD COLUMN edu_topic_id INT NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE schedule_items ADD KEY idx_si_edu_topic (edu_topic_id)");
}

$message = '';

/** find-or-create education_category by name */
function mig_category(PDO $pdo, string $name, ?int $createdBy): int {
    $name = trim($name) ?: 'Genel';
    $st = $pdo->prepare("SELECT id FROM education_categories WHERE name = ?");
    $st->execute([$name]);
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
    $max = (int)$pdo->query("SELECT COALESCE(MAX(display_order),0) FROM education_categories")->fetchColumn();
    $locked = $createdBy === null ? 1 : 0;
    $pdo->prepare("INSERT INTO education_categories (name, display_order, created_by, is_locked) VALUES (?,?,?,?)")
        ->execute([$name, $max + 1, $createdBy, $locked]);
    return (int)$pdo->lastInsertId();
}

/** find-or-create education_subject by (category, lesson_name) */
function mig_subject(PDO $pdo, int $catId, string $lesson, ?int $createdBy): int {
    $lesson = trim($lesson);
    $st = $pdo->prepare("SELECT id FROM education_subjects WHERE category_id = ? AND lesson_name = ?");
    $st->execute([$catId, $lesson]);
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
    $st = $pdo->prepare("SELECT COALESCE(MAX(display_order),0) FROM education_subjects WHERE category_id = ?");
    $st->execute([$catId]); $max = (int)$st->fetchColumn();
    $locked = $createdBy === null ? 1 : 0;
    $pdo->prepare("INSERT INTO education_subjects (category_id, lesson_name, display_order, created_by, is_locked) VALUES (?,?,?,?,?)")
        ->execute([$catId, $lesson, $max + 1, $createdBy, $locked]);
    return (int)$pdo->lastInsertId();
}

/** find-or-create education_topic by (subject, topic_name) — isim eşleşirse mevcut olana BAĞLAR */
function mig_topic(PDO $pdo, int $subjId, string $name, ?int $createdBy, int $isPublic): int {
    $name = trim($name);
    $st = $pdo->prepare("SELECT id FROM education_topics WHERE subject_id = ? AND topic_name = ?");
    $st->execute([$subjId, $name]);
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
    $st = $pdo->prepare("SELECT COALESCE(MAX(display_order),0) FROM education_topics WHERE subject_id = ?");
    $st->execute([$subjId]); $max = (int)$st->fetchColumn();
    $locked = ($createdBy === null || $isPublic == 1) ? 1 : 0;
    $pdo->prepare("INSERT INTO education_topics (subject_id, topic_name, display_order, status, created_by, is_locked) VALUES (?,?,?,1,?,?)")
        ->execute([$subjId, $name, $max + 1, $createdBy, $locked]);
    return (int)$pdo->lastInsertId();
}

// ── İşlemler ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['run_migration'])) {
        $created = 0; $bound = 0; $skipped = 0;
        try {
            $pdo->beginTransaction();

            // Ders eşleştirme haritası (coaching_subject.id => education_subject.id)
            $subjMap = [];
            $subjects = $pdo->query("SELECT id, name, category, created_by FROM coaching_subjects")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($subjects as $sc) {
                $cb = $sc['created_by'] !== null ? (int)$sc['created_by'] : null;
                $catId = mig_category($pdo, $sc['category'] ?? 'Genel', $cb);
                $subjMap[$sc['id']] = mig_subject($pdo, $catId, $sc['name'], $cb);
            }

            // Konular
            $insMap = $pdo->prepare("INSERT INTO education_topic_map (old_topic_id, new_topic_id) VALUES (?, ?)");
            $topics = $pdo->query("SELECT id, subject_id, name, created_by, is_public FROM coaching_topics")->fetchAll(PDO::FETCH_ASSOC);
            $alreadyMapped = $pdo->query("SELECT old_topic_id FROM education_topic_map")->fetchAll(PDO::FETCH_COLUMN);
            $alreadyMapped = array_flip(array_map('intval', $alreadyMapped));

            foreach ($topics as $ct) {
                if (isset($alreadyMapped[(int)$ct['id']])) { $skipped++; continue; } // idempotent
                if (!isset($subjMap[$ct['subject_id']])) { $skipped++; continue; }   // dersi olmayan konu
                $cb = $ct['created_by'] !== null ? (int)$ct['created_by'] : null;
                $existsBefore = $pdo->prepare("SELECT id FROM education_topics WHERE subject_id = ? AND topic_name = ?");
                $existsBefore->execute([$subjMap[$ct['subject_id']], trim($ct['name'])]);
                $wasExisting = (bool)$existsBefore->fetchColumn();
                $newId = mig_topic($pdo, $subjMap[$ct['subject_id']], $ct['name'], $cb, (int)$ct['is_public']);
                $insMap->execute([(int)$ct['id'], $newId]);
                $wasExisting ? $bound++ : $created++;
            }

            // schedule_items.edu_topic_id doldur (topic_id AYNEN korunur)
            $pdo->exec("UPDATE schedule_items si
                        JOIN education_topic_map m ON m.old_topic_id = si.topic_id
                        SET si.edu_topic_id = m.new_topic_id");

            $pdo->commit();
            $message = "✅ Göç tamamlandı. Yeni oluşturulan konu: <b>$created</b>, mevcut konuya bağlanan: <b>$bound</b>, atlanan: <b>$skipped</b>.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "❌ Göç hatası: " . htmlspecialchars($e->getMessage());
        }
    }

    if (isset($_POST['undo_migration'])) {
        try {
            // GÜVENLİ geri alma: sadece bağı çöz (eski topic_id ve coaching_* dokunulmaz)
            $pdo->exec("UPDATE schedule_items SET edu_topic_id = NULL");
            $pdo->exec("DELETE FROM education_topic_map");
            $message = "↩️ Göç bağlantısı geri alındı (edu_topic_id temizlendi, harita boşaltıldı). Eski veriler ve oluşturulan müfredat kayıtları korundu.";
        } catch (Throwable $e) {
            $message = "❌ Geri alma hatası: " . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Durum & Doğrulama ───────────────────────────────────────────────────────
$stat = [
    'coaching_subjects' => (int)$pdo->query("SELECT COUNT(*) FROM coaching_subjects")->fetchColumn(),
    'coaching_topics'   => (int)$pdo->query("SELECT COUNT(*) FROM coaching_topics")->fetchColumn(),
    'mapped'            => (int)$pdo->query("SELECT COUNT(*) FROM education_topic_map")->fetchColumn(),
    'si_total'          => (int)$pdo->query("SELECT COUNT(*) FROM schedule_items WHERE topic_id IS NOT NULL")->fetchColumn(),
    'si_migrated'       => (int)$pdo->query("SELECT COUNT(*) FROM schedule_items WHERE edu_topic_id IS NOT NULL")->fetchColumn(),
];

// Birebir doğrulama: toplam 'soru' miktarı eski topic_id bazında vs yeni edu_topic_id bazında
$oldSum = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM schedule_items WHERE action_type='soru' AND status='yapildi' AND topic_id IS NOT NULL")->fetchColumn();
$newSum = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM schedule_items WHERE action_type='soru' AND status='yapildi' AND edu_topic_id IS NOT NULL")->fetchColumn();
// Eşlenememiş (map dışı) topic_id'li kayıtlar
$unmapped = (int)$pdo->query("SELECT COUNT(*) FROM schedule_items si LEFT JOIN education_topic_map m ON m.old_topic_id = si.topic_id WHERE si.topic_id IS NOT NULL AND m.old_topic_id IS NULL")->fetchColumn();
$verifyOk = ($stat['mapped'] > 0 && $oldSum === $newSum && $unmapped === 0);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veri Göçü — Müfredat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Poppins',sans-serif}</style>
</head>
<body class="bg-slate-100 flex min-h-screen">
<?php include 'sidebar.php'; ?>
<main class="flex-1 p-6 lg:p-8">
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-slate-800">🔄 Eski Veri Göçü</h1>
            <a href="education.php" class="text-sm bg-white border border-slate-200 px-4 py-2 rounded-xl font-bold text-slate-600 hover:bg-slate-50">← Müfredat Yönetimi</a>
        </div>

        <?php if ($message): ?><div class="bg-white border border-indigo-100 p-4 rounded-xl mb-5 text-sm shadow-sm"><?= $message ?></div><?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-5">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4">Durum</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-center">
                <div class="bg-slate-50 rounded-xl p-3"><div class="text-2xl font-black text-slate-700"><?= $stat['coaching_subjects'] ?></div><div class="text-[11px] text-slate-500 mt-1">Eski Ders</div></div>
                <div class="bg-slate-50 rounded-xl p-3"><div class="text-2xl font-black text-slate-700"><?= $stat['coaching_topics'] ?></div><div class="text-[11px] text-slate-500 mt-1">Eski Konu</div></div>
                <div class="bg-indigo-50 rounded-xl p-3"><div class="text-2xl font-black text-indigo-600"><?= $stat['mapped'] ?></div><div class="text-[11px] text-indigo-500 mt-1">Eşlenen Konu</div></div>
                <div class="bg-slate-50 rounded-xl p-3"><div class="text-2xl font-black text-slate-700"><?= $stat['si_total'] ?></div><div class="text-[11px] text-slate-500 mt-1">Konulu Görev</div></div>
                <div class="bg-emerald-50 rounded-xl p-3"><div class="text-2xl font-black text-emerald-600"><?= $stat['si_migrated'] ?></div><div class="text-[11px] text-emerald-500 mt-1">Taşınan Görev</div></div>
                <div class="bg-<?= $unmapped ? 'red' : 'slate' ?>-50 rounded-xl p-3"><div class="text-2xl font-black text-<?= $unmapped ? 'red-600' : 'slate-700' ?>"><?= $unmapped ?></div><div class="text-[11px] text-slate-500 mt-1">Eşlenemeyen</div></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-5">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Birebir Doğrulama (Yapılan Soru Toplamı)</h2>
            <div class="flex items-center gap-6 text-sm">
                <div>Eski (topic_id): <b class="text-slate-800"><?= number_format($oldSum) ?></b></div>
                <div>Yeni (edu_topic_id): <b class="text-slate-800"><?= number_format($newSum) ?></b></div>
                <div class="ml-auto">
                    <?php if ($stat['mapped'] === 0): ?>
                        <span class="text-slate-400 font-bold">— Henüz göç yapılmadı</span>
                    <?php elseif ($verifyOk): ?>
                        <span class="text-emerald-600 font-black">✅ Tutarlı — veri kaybı yok</span>
                    <?php else: ?>
                        <span class="text-red-600 font-black">⚠️ Fark var (<?= $unmapped ?> eşlenemeyen) — inceleyin</span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-3">Not: Eski <code>topic_id</code> ve <code>coaching_*</code> tabloları hiç değiştirilmez; bu yüzden göç her an güvenle geri alınabilir.</p>
        </div>

        <div class="flex gap-3">
            <form method="post" onsubmit="return confirm('Eski müfredat verisi yeni sisteme taşınacak (eski veriye dokunulmaz). Devam?')">
                <button name="run_migration" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-sm">🔄 Göçü Çalıştır <?= $stat['mapped'] ? '(tekrar / güncelle)' : '' ?></button>
            </form>
            <?php if ($stat['mapped'] > 0): ?>
            <form method="post" onsubmit="return confirm('Göç bağlantısı geri alınacak (edu_topic_id temizlenir). Eski veriler etkilenmez. Devam?')">
                <button name="undo_migration" class="bg-white border border-red-200 text-red-500 px-6 py-3 rounded-xl font-bold hover:bg-red-50">↩️ Bağlantıyı Geri Al</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
