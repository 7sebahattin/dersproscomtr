<?php
/**
 * cron_backup.php — Günlük veritabanı yedeği (saf PHP dump + gzip + rotasyon)
 *
 * KURULUM (bir kez): cron-job.org'a şu URL'yi GÜNDE 1 kez (örn. 03:30) çağırt:
 *   https://derspros.com.tr/cron_backup.php?token=CRON_TOKEN_DEGERI
 *
 * - Yedekler web erişimine KAPALI backups/ klasöründe tutulur (kendi .htaccess'i).
 * - Rotasyon: en yeni 7 yedek saklanır, eskisi silinir.
 * - İndirme/uzak kopya: admin/yedekler.php (yalnızca admin) — düzenli olarak
 *   bilgisayarına indirip saklaman önerilir (hosting çökerse tek kopya olmasın).
 * - mysqldump'a bağımlı DEĞİL (paylaşımlı hosting'de binary olmayabilir);
 *   dump PDO üzerinden üretilir, 500'erlik parçalarla belleği yormaz.
 */

$secretsFile = __DIR__ . '/secrets.php';
if (file_exists($secretsFile)) require_once $secretsFile;

$isCli   = php_sapi_name() === 'cli';
$tokenOk = defined('CRON_TOKEN') && CRON_TOKEN !== ''
    && hash_equals((string)CRON_TOKEN, (string)($_GET['token'] ?? ''));
if (!$isCli && !$tokenOk && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    exit("Erişim yasak.\n");
}

set_time_limit(300);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

$dir = __DIR__ . '/backups/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
// Klasörü web'e kapat (her koşuda tazele — deploy üzerine yazmış olabilir)
@file_put_contents($dir . '.htaccess', "Require all denied\n");
@file_put_contents($dir . 'index.php', '<?php // no direct access');

$file = $dir . 'db_' . date('Ymd_His') . '.sql.gz';
$gz = gzopen($file, 'wb6');
if (!$gz) exit("HATA: yedek dosyası açılamadı.\n");
$w = function (string $s) use ($gz) { gzwrite($gz, $s); };

$w("-- DersPROS otomatik veritabanı yedeği — " . date('c') . "\n");
$w("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$totalRows = 0;
foreach ($tables as $t) {
    $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
    $w("DROP TABLE IF EXISTS `$t`;\n" . $create[1] . ";\n\n");

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    $totalRows += $cnt;
    for ($off = 0; $off < $cnt; $off += 500) {
        $rows = $pdo->query("SELECT * FROM `$t` LIMIT 500 OFFSET $off")->fetchAll(PDO::FETCH_NUM);
        if (!$rows) break;
        $vals = [];
        foreach ($rows as $r) {
            $vals[] = '(' . implode(',', array_map(
                fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $r)) . ')';
        }
        $w("INSERT INTO `$t` VALUES\n" . implode(",\n", $vals) . ";\n");
    }
    $w("\n");
}
$w("SET FOREIGN_KEY_CHECKS=1;\n");
gzclose($gz);

// Rotasyon: en yeni 7 yedek kalsın
$files = glob($dir . 'db_*.sql.gz') ?: [];
rsort($files);
foreach (array_slice($files, 7) as $old) @unlink($old);

$sizeKb = round(filesize($file) / 1024);
echo "OK — yedek alındı: " . basename($file) . " ({$sizeKb} KB · " . count($tables) . " tablo · " . number_format($totalRows) . " satır)\n";
echo "Saklanan yedek sayısı: " . min(count($files), 7) . " (en fazla 7)\n";
