<?php
// admin/yedekler.php — Veritabanı yedeklerini listele/indir (yalnızca admin)
// Yedekler cron_backup.php tarafından üretilir (backups/ web erişimine kapalı;
// indirme yalnızca bu sayfa üzerinden, oturumlu admin ile yapılır).

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once '../education_lib.php';

if (!education_is_admin()) { header("Location: ../index.php"); exit; }

$dir = dirname(__DIR__) . '/backups/';

// İndirme: yalnızca backups/ içindeki db_*.sql.gz adları (path traversal engelli)
if (isset($_GET['f'])) {
    $f = basename((string)$_GET['f']);
    if (preg_match('/^db_\d{8}_\d{6}\.sql\.gz$/', $f) && is_file($dir . $f)) {
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $f . '"');
        header('Content-Length: ' . filesize($dir . $f));
        readfile($dir . $f);
        exit;
    }
    http_response_code(404);
    exit('Yedek bulunamadı.');
}

$files = is_dir($dir) ? (glob($dir . 'db_*.sql.gz') ?: []) : [];
rsort($files);
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Veritabanı Yedekleri</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:640px;margin:48px auto;padding:0 20px;color:#1e293b;line-height:1.6}
  h1{font-size:1.3rem}
  table{width:100%;border-collapse:collapse;margin:16px 0;font-size:.92rem}
  th,td{border:1px solid #e2e8f0;padding:9px 12px;text-align:left}
  th{background:#f8fafc}
  a.dl{background:#223488;color:#fff;font-weight:700;font-size:.8rem;padding:6px 14px;border-radius:10px;text-decoration:none}
  a.dl:hover{background:#314595}
  .muted{color:#64748b;font-size:.85rem}
  .warn{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;font-size:.88rem;color:#92400e}
</style>
</head>
<body>
  <h1>💾 Veritabanı Yedekleri</h1>
  <p class="muted">Yedekler <code>cron_backup.php</code> ile günlük alınır (en yeni 7 adet saklanır). Hosting arızasına karşı yedekleri düzenli olarak <b>bilgisayarına indir</b>.</p>

  <?php if (!$files): ?>
    <div class="warn">Henüz yedek yok. cron-job.org'a şu URL'yi günde 1 kez çağırt:<br>
      <code>https://derspros.com.tr/cron_backup.php?token=CRON_TOKEN</code><br>
      (ya da tarayıcıda bir kez elle açarak ilk yedeği hemen alabilirsin)</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Dosya</th><th>Boyut</th><th>Tarih</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($files as $f): $b = basename($f); ?>
        <tr>
          <td><code><?= htmlspecialchars($b) ?></code></td>
          <td><?= round(filesize($f) / 1024) ?> KB</td>
          <td><?= date('d.m.Y H:i', filemtime($f)) ?></td>
          <td><a class="dl" href="?f=<?= urlencode($b) ?>">⬇️ İndir</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <p class="muted"><a href="index.php" style="color:#475569">← Admin paneline dön</a></p>
</body>
</html>
