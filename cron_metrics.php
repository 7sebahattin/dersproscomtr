<?php
/**
 * cron_metrics.php — Günlük metrik hesabı (S0 omurga)
 *
 * Cron: gece 03:00 sonrası günde bir → 0 3 * * * php /path/to/cron_metrics.php
 * Alternatif: cron_notifications.php zaten her tetiklenişinde metrics_daily_tick
 * çağırır (pseudo-cron ile de çalışır); bu dosya crontab/harici tetikleyici
 * isteyenler ve elle zorlamak (?force=1) içindir.
 *
 * Erişim: CLI, localhost veya secrets.php'deki CRON_TOKEN
 * (cron_notifications.php ile birebir aynı koruma deseni).
 */

$isCli = php_sapi_name() === 'cli';
if (!$isCli && !defined('CRON_RUN')) {
    $secretsFile = __DIR__ . '/secrets.php';
    if (file_exists($secretsFile)) require_once $secretsFile;
    $tokenOk = defined('CRON_TOKEN') && CRON_TOKEN !== ''
        && hash_equals((string)CRON_TOKEN, (string)($_GET['token'] ?? ''));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$tokenOk && !in_array($ip, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        exit("Erişim yasak.\n");
    }
}

if (!defined('CRON_RUN')) define('CRON_RUN', true);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/metrics_lib.php';

header('Content-Type: text/plain; charset=utf-8');

$force = $isCli
    ? in_array('--force', $_SERVER['argv'] ?? [], true)
    : (($_GET['force'] ?? '') === '1');

try {
    $res = $force ? metrics_run($pdo) : metrics_daily_tick($pdo);
    if ($res === null) {
        echo "[METRICS] Atlandı (03:00 öncesi veya bugün zaten hesaplandı). force=1 ile zorlanabilir.\n";
    } else {
        echo "[METRICS] Tamam: {$res['days']} gün, {$res['rows']} satır yazıldı. " . date('Y-m-d H:i:s') . "\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "[METRICS ERROR] " . $e->getMessage() . "\n";
}
