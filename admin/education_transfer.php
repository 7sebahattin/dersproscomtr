<?php
// admin/education_transfer.php
// Müfredat (education_categories/subjects/topics) ortamlar arası aktarım aracı.
// KULLANIM:
//   1) STAGING'de bu sayfayı aç → "Dışa Aktar (JSON indir)" ile dosyayı indir.
//   2) PRODUCTION'da bu sayfayı aç → indirdiğin JSON'u yükle → "İçe Aktar".
// İçe aktarma IDEMPOTENT: yalnızca EKSİK olan kategori/ders/konu eklenir; var
// olanlar dokunulmaz, hiçbir şey silinmez, kopya oluşmaz. Eklenen içerik
// created_by=NULL + is_locked=1 (genel/onaylı 🔒) olarak işaretlenir.
// ERİŞİM: yalnızca admin/superuser.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once '../education_lib.php';

if (!education_is_admin()) { header("Location: ../index.php"); exit; }

education_ensure_schema($pdo);

// ── DIŞA AKTAR: tüm müfredatı iç içe JSON olarak indir ───────────────────────
if (($_GET['action'] ?? '') === 'export') {
    $out = ['exported_at' => date('c'), 'categories' => []];
    $cats = $pdo->query("SELECT id, name, display_order FROM education_categories ORDER BY display_order, name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cats as $c) {
        $catNode = ['name' => $c['name'], 'display_order' => (int)$c['display_order'], 'subjects' => []];
        $subs = $pdo->prepare("SELECT id, lesson_name, display_order FROM education_subjects WHERE category_id = ? ORDER BY display_order, lesson_name");
        $subs->execute([$c['id']]);
        foreach ($subs->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $subNode = ['lesson_name' => $s['lesson_name'], 'display_order' => (int)$s['display_order'], 'topics' => []];
            $tops = $pdo->prepare("SELECT topic_name, display_order, status FROM education_topics WHERE subject_id = ? ORDER BY display_order, id");
            $tops->execute([$s['id']]);
            foreach ($tops->fetchAll(PDO::FETCH_ASSOC) as $t) {
                $subNode['topics'][] = ['topic_name' => $t['topic_name'], 'display_order' => (int)$t['display_order'], 'status' => (int)$t['status']];
            }
            $catNode['subjects'][] = $subNode;
        }
        $out['categories'][] = $catNode;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="mufredat_' . date('Ymd_His') . '.json"');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── İÇE AKTAR: yüklenen JSON'u idempotent ekle ───────────────────────────────
$report = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['jsonfile'])) {
    $report = ['cat' => 0, 'subj' => 0, 'topic' => 0, 'err' => ''];
    try {
        if ($_FILES['jsonfile']['error'] !== UPLOAD_ERR_OK) throw new Exception('Dosya yüklenemedi.');
        $data = json_decode(file_get_contents($_FILES['jsonfile']['tmp_name']), true);
        if (!$data || empty($data['categories'])) throw new Exception('Geçersiz JSON (categories bulunamadı).');

        // Yalnızca seçili kategoriler (varsayılan: hepsi). LGS'yi ayrıca işaretleyebilmek için filtre.
        $onlyCat = trim($_POST['only_category'] ?? '');

        // Hazır sorgular (find-or-create)
        $findCat  = $pdo->prepare("SELECT id FROM education_categories WHERE name = ?");
        $insCat   = $pdo->prepare("INSERT INTO education_categories (name, display_order, is_active, created_by, is_locked) VALUES (?,?,1,NULL,1)");
        $findSubj = $pdo->prepare("SELECT id FROM education_subjects WHERE category_id = ? AND lesson_name = ?");
        $insSubj  = $pdo->prepare("INSERT INTO education_subjects (category_id, lesson_name, display_order, is_active, created_by, is_locked) VALUES (?,?,?,1,NULL,1)");
        $findTop  = $pdo->prepare("SELECT id FROM education_topics WHERE subject_id = ? AND topic_name = ?");
        $insTop   = $pdo->prepare("INSERT INTO education_topics (subject_id, topic_name, display_order, status, created_by, is_locked) VALUES (?,?,?,?,NULL,1)");

        $pdo->beginTransaction();
        foreach ($data['categories'] as $cat) {
            $cname = trim((string)($cat['name'] ?? ''));
            if ($cname === '') continue;
            if ($onlyCat !== '' && mb_strtolower($cname, 'UTF-8') !== mb_strtolower($onlyCat, 'UTF-8')) continue;

            $findCat->execute([$cname]);
            $cid = $findCat->fetchColumn();
            if (!$cid) {
                $insCat->execute([$cname, (int)($cat['display_order'] ?? 0)]);
                $cid = (int)$pdo->lastInsertId();
                $report['cat']++;
            }
            foreach (($cat['subjects'] ?? []) as $sub) {
                $lname = trim((string)($sub['lesson_name'] ?? ''));
                if ($lname === '') continue;
                $findSubj->execute([$cid, $lname]);
                $sid = $findSubj->fetchColumn();
                if (!$sid) {
                    $insSubj->execute([$cid, $lname, (int)($sub['display_order'] ?? 0)]);
                    $sid = (int)$pdo->lastInsertId();
                    $report['subj']++;
                }
                foreach (($sub['topics'] ?? []) as $top) {
                    $tname = trim((string)($top['topic_name'] ?? ''));
                    if ($tname === '') continue;
                    $findTop->execute([$sid, $tname]);
                    if (!$findTop->fetchColumn()) {
                        $insTop->execute([$sid, $tname, (int)($top['display_order'] ?? 0), (int)($top['status'] ?? 1)]);
                        $report['topic']++;
                    }
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $report['err'] = $e->getMessage();
    }
}

// Mevcut sayımlar (bilgi)
$curCats  = (int)$pdo->query("SELECT COUNT(*) FROM education_categories")->fetchColumn();
$curSubs  = (int)$pdo->query("SELECT COUNT(*) FROM education_subjects")->fetchColumn();
$curTops  = (int)$pdo->query("SELECT COUNT(*) FROM education_topics")->fetchColumn();
$catList  = $pdo->query("SELECT name FROM education_categories ORDER BY display_order, name")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Müfredat Aktarımı</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:680px;margin:40px auto;padding:0 20px;color:#1e293b;line-height:1.55}
  h1{font-size:1.3rem}
  .box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin:16px 0}
  .btn{display:inline-block;background:#223488;color:#fff;font-weight:700;padding:11px 20px;border-radius:12px;text-decoration:none;border:0;cursor:pointer}
  .btn:hover{background:#314595}
  .btn.green{background:#16a34a}.btn.green:hover{background:#15803d}
  input[type=file],input[type=text]{width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:10px;margin:8px 0}
  .ok{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
  .err{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
  code{background:#eef2ff;padding:1px 5px;border-radius:5px}
  .muted{color:#64748b;font-size:.9rem}
</style>
</head>
<body>
  <h1>📚 Müfredat Aktarımı (ortamlar arası)</h1>
  <p class="muted">Bu ortam: <b><?php echo $curCats; ?></b> kategori · <b><?php echo $curSubs; ?></b> ders · <b><?php echo $curTops; ?></b> konu.
    <?php if ($catList): ?><br>Kategoriler: <?php echo htmlspecialchars(implode(', ', $catList)); ?><?php endif; ?></p>

  <?php if ($report !== null): ?>
    <div class="box <?php echo $report['err'] ? 'err' : 'ok'; ?>">
      <?php if ($report['err']): ?>
        ❌ Hata: <?php echo htmlspecialchars($report['err']); ?>
      <?php else: ?>
        ✅ İçe aktarma tamam — <b><?php echo $report['cat']; ?></b> kategori, <b><?php echo $report['subj']; ?></b> ders, <b><?php echo $report['topic']; ?></b> konu eklendi.
        (Zaten var olanlar atlandı, hiçbir şey silinmedi.)
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="box">
    <h3 style="margin-top:0">1) Dışa Aktar</h3>
    <p class="muted">Bu ortamdaki tüm müfredatı JSON olarak indir. (Genelde <b>STAGING</b>'de çalıştırılır.)</p>
    <a class="btn" href="?action=export">⬇️ JSON İndir</a>
  </div>

  <div class="box">
    <h3 style="margin-top:0">2) İçe Aktar</h3>
    <p class="muted">İndirdiğin JSON'u yükle. Yalnızca eksik içerik eklenir. (Genelde <b>PRODUCTION</b>'da çalıştırılır.)</p>
    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="jsonfile" accept="application/json,.json" required>
      <label class="muted">Sadece belirli bir kategori (opsiyonel — örn: <code>LGS</code>). Boş bırakırsan hepsi aktarılır.</label>
      <input type="text" name="only_category" placeholder="LGS (opsiyonel)">
      <button type="submit" class="btn green">⬆️ İçe Aktar</button>
    </form>
  </div>

  <p class="muted"><a href="../koc/mufredat_v2.php">← Müfredatım sayfasına dön</a></p>
</body>
</html>
