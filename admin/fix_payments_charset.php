<?php
// admin/fix_payments_charset.php
// AMAÇ: latin1 karakter setiyle oluşturulmuş tabloları utf8mb4'e çevirir.
//   latin1 kolonlara yazılan Türkçe karakterler (ş, ğ, İ, ı) '?' oluyordu.
//   (Örn: payments.description → "TAR?HL? SEANS".)
//
// GÜVENLİ: Bağlantı zaten utf8mb4 (SET NAMES utf8mb4) olduğundan mevcut latin1
//   baytları CONVERT ile doğru şekilde utf8mb4'e taşınır; latin1'de temsil
//   edilebilen karakterler (ç, ö, ü ...) korunur. Daha önce '?' olarak kaybolmuş
//   ESKİ kayıtlar geri GELMEZ (o veri zaten silinmiş), ancak bundan sonra girilen
//   Türkçe karakterler doğru kaydedilir.
// IDEMPOTENT: Zaten utf8mb4 olan tablolara dokunmaz; tekrar çalıştırmak güvenlidir.
// ERİŞİM: Yalnızca admin/superuser. Çalıştırdıktan sonra bu dosya silinebilir.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once '../education_lib.php';

if (!education_is_admin()) { header("Location: ../index.php"); exit; }

header('Content-Type: text/html; charset=UTF-8');

// Düzeltilecek latin1 tablolar
$TABLES = ['exams', 'exam_results', 'materials', 'payments', 'student_topic_targets', 'teacher_profiles', 'zoom_sessions'];

$do  = (($_GET['run'] ?? '') === '1');
$rows = [];   // rapor satırları: [tablo, öncesi, sonrası, durum]
$anyLatin1 = false;

foreach ($TABLES as $t) {
    try {
        $coll = $pdo->query("SELECT TABLE_COLLATION FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($t))->fetchColumn();
        if ($coll === false || $coll === null) {
            $rows[] = [$t, '—', '—', 'yok (tablo bulunamadı)'];
            continue;
        }
        $isUtf8mb4 = (stripos((string)$coll, 'utf8mb4') !== false);
        if ($isUtf8mb4) {
            $rows[] = [$t, $coll, $coll, '✓ zaten utf8mb4'];
        } else {
            $anyLatin1 = true;
            if ($do) {
                $pdo->exec("ALTER TABLE `$t` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci");
                $after = $pdo->query("SELECT TABLE_COLLATION FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($t))->fetchColumn();
                $rows[] = [$t, $coll, $after, '✅ dönüştürüldü'];
            } else {
                $rows[] = [$t, $coll, '—', '⚠️ latin1 (dönüştürülecek)'];
            }
        }
    } catch (Throwable $e) {
        $rows[] = [$t, '?', '?', '❌ HATA: ' . $e->getMessage()];
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Karakter seti (utf8mb4) düzeltmesi</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:760px;margin:48px auto;padding:0 20px;color:#1e293b;line-height:1.55}
  h1{font-size:1.3rem}
  table{width:100%;border-collapse:collapse;margin:18px 0;font-size:.9rem}
  th,td{border:1px solid #e2e8f0;padding:8px 10px;text-align:left}
  th{background:#f8fafc}
  code{background:#eef2ff;padding:1px 5px;border-radius:5px;font-size:.9em}
  .btn{display:inline-block;background:#223488;color:#fff;font-weight:700;padding:11px 22px;border-radius:12px;text-decoration:none;margin-top:8px}
  .btn:hover{background:#314595}
  .note{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;font-size:.88rem}
  a.back{display:inline-block;margin-top:22px;color:#475569}
</style>
</head>
<body>
  <h1>🔤 Karakter seti düzeltmesi (latin1 → utf8mb4)</h1>
  <table>
    <thead><tr><th>Tablo</th><th>Öncesi</th><th>Sonrası</th><th>Durum</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><code><?= htmlspecialchars($r[0]) ?></code></td>
          <td><?= htmlspecialchars((string)$r[1]) ?></td>
          <td><?= htmlspecialchars((string)$r[2]) ?></td>
          <td><?= htmlspecialchars((string)$r[3]) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($anyLatin1 && !$do): ?>
    <a class="btn" href="?run=1">🔧 Şimdi utf8mb4'e dönüştür</a>
    <p class="note">Bu işlem tabloları utf8mb4'e çevirir. Var olan geçerli veriler korunur; daha önce
      <code>?</code> olarak bozulmuş eski kayıtlar geri gelmez, ancak bundan sonra girilen Türkçe
      karakterler doğru kaydedilir.</p>
  <?php elseif (!$anyLatin1): ?>
    <p class="note">✅ Tüm tablolar zaten utf8mb4 — yapılacak bir şey yok.</p>
  <?php else: ?>
    <p class="note">✅ Dönüştürme tamamlandı. Artık ödeme açıklaması dahil tüm alanlarda Türkçe karakterler doğru kaydedilir.</p>
  <?php endif; ?>

  <br><a class="back" href="../koc/odemeler.php">← Ödemeler sayfasına dön</a>
</body>
</html>
