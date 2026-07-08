<?php
// admin/fix_payments_charset.php
// AMAÇ: `payments` tablosu latin1 olduğu için `description` alanına yazılan
// Türkçe karakterler (ş, ğ, İ, ı) '?' oluyordu. Bu betik tabloyu utf8mb4'e çevirir.
//
// GÜVENLİ: Bağlantı zaten utf8mb4 (SET NAMES utf8mb4) olduğundan mevcut latin1
//   baytları CONVERT ile doğru şekilde utf8mb4'e taşınır; latin1'de temsil
//   edilebilen karakterler (ç, ö, ü ...) korunur. Daha önce '?' olarak kaybolmuş
//   eski kayıtlar geri GELMEZ (o veri zaten silinmiş), ancak bundan sonra girilen
//   Türkçe karakterler doğru kaydedilir.
// IDEMPOTENT: Tablo zaten utf8mb4 ise hiçbir değişiklik yapmaz; tekrar çalıştırmak
//   güvenlidir.
// ERİŞİM: Yalnızca admin/superuser. Çalıştırdıktan sonra bu dosya silinebilir.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once '../education_lib.php';

if (!education_is_admin()) { header("Location: ../index.php"); exit; }

header('Content-Type: text/html; charset=UTF-8');

$log = [];
$do  = (($_GET['run'] ?? '') === '1');

try {
    $before = $pdo->query("SELECT TABLE_COLLATION FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'")->fetchColumn();
    $colBefore = $pdo->query("SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='payments' AND COLUMN_NAME='description'")->fetchColumn();

    $log[] = "Mevcut tablo collation: <b>" . htmlspecialchars((string)$before ?: '?') . "</b>";
    $log[] = "Mevcut <code>description</code> karakter seti: <b>" . htmlspecialchars((string)$colBefore ?: '?') . "</b>";

    $alreadyOk = (stripos((string)$colBefore, 'utf8mb4') !== false);

    if ($alreadyOk) {
        $log[] = "✅ <b>Zaten utf8mb4</b> — herhangi bir değişiklik gerekmiyor.";
    } elseif (!$do) {
        $log[] = "⚠️ <code>description</code> latin1 görünüyor. Dönüştürmek için aşağıdaki butona basın.";
    } else {
        $pdo->exec("ALTER TABLE `payments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci");
        $after = $pdo->query("SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='payments' AND COLUMN_NAME='description'")->fetchColumn();
        $log[] = "✅ <b>Dönüştürüldü.</b> Yeni <code>description</code> karakter seti: <b>" . htmlspecialchars((string)$after) . "</b>";
        $log[] = "Bundan sonra girilen Türkçe karakterler doğru kaydedilecek. (Eski '?' kayıtlar geri gelmez.)";
    }
} catch (Throwable $e) {
    $log[] = "❌ HATA: " . htmlspecialchars($e->getMessage());
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>payments karakter seti düzeltmesi</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:640px;margin:48px auto;padding:0 20px;color:#1e293b;line-height:1.6}
  h1{font-size:1.3rem}
  ul{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 16px 16px 34px}
  code{background:#eef2ff;padding:1px 5px;border-radius:5px;font-size:.9em}
  .btn{display:inline-block;background:#223488;color:#fff;font-weight:700;padding:11px 22px;border-radius:12px;text-decoration:none;margin-top:8px}
  .btn:hover{background:#314595}
  a.back{display:inline-block;margin-top:20px;color:#475569}
</style>
</head>
<body>
  <h1>💳 payments tablosu — Türkçe karakter düzeltmesi</h1>
  <ul>
    <?php foreach ($log as $line) echo "<li>$line</li>"; ?>
  </ul>
  <?php if (!$do && stripos((string)($colBefore ?? ''), 'utf8mb4') === false): ?>
    <a class="btn" href="?run=1">🔧 Şimdi utf8mb4'e dönüştür</a>
  <?php endif; ?>
  <br><a class="back" href="../koc/odemeler.php">← Ödemeler sayfasına dön</a>
</body>
</html>
