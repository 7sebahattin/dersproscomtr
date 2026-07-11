<?php
/**
 * app_settings_lib.php — Basit anahtar/değer uygulama ayarları
 * İlk kullanım: sınav tarihleri (YKS/LGS geri sayımı).
 *
 * Varsayılanlar KAYDEDİLMEZ; yalnızca admin kaydederse kalıcı olur.
 * Böylece yıl devrildiğinde (tarih geçince) varsayılan otomatik bir
 * sonraki yılın tahminine kayar; admin gerçek tarihi girince o kullanılır.
 */

function app_settings_ensure(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            k VARCHAR(64) PRIMARY KEY,
            v VARCHAR(255) NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
        $done = true;
    } catch (Throwable $e) {}
}

function app_setting_get(PDO $pdo, string $key, ?string $default = null): ?string {
    app_settings_ensure($pdo);
    try {
        $st = $pdo->prepare("SELECT v FROM app_settings WHERE k = ?");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === '') ? $default : (string)$v;
    } catch (Throwable $e) { return $default; }
}

function app_setting_set(PDO $pdo, string $key, string $value): void {
    app_settings_ensure($pdo);
    try {
        $pdo->prepare("INSERT INTO app_settings (k, v) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()")->execute([$key, $value]);
    } catch (Throwable $e) {}
}

/**
 * Sınav tarihleri: kayıtlı değer varsa o; yoksa yaklaşan öğretim yılına göre
 * tahmini varsayılan (YKS ~20 Haziran, LGS ~15 Haziran).
 * Dönüş: ['YKS' => 'Y-m-d', 'LGS' => 'Y-m-d']
 */
function exam_dates(PDO $pdo): array {
    $y = (int)date('Y');
    $examYear = ((int)date('n') >= 7) ? $y + 1 : $y;
    $defYks = sprintf('%d-06-20', $examYear);
    $defLgs = sprintf('%d-06-15', $examYear);
    $yks = app_setting_get($pdo, 'exam_date_yks', $defYks);
    $lgs = app_setting_get($pdo, 'exam_date_lgs', $defLgs);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$yks)) $yks = $defYks;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$lgs)) $lgs = $defLgs;
    return ['YKS' => $yks, 'LGS' => $lgs];
}

/** Sınava kalan TAM gün (bugün dahil değil). Geçmişse negatif döner. */
function exam_days_left(string $dateYmd): int {
    $t = strtotime($dateYmd . ' 00:00:00');
    $today = strtotime(date('Y-m-d') . ' 00:00:00');
    return (int)round(($t - $today) / 86400);
}
