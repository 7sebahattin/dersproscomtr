<?php
/**
 * cron_notifications.php — DersPROS Web Push Bildirim Görevleri
 *
 * Cron: Her saat başı → 0 * * * * php /path/to/cron_notifications.php
 *
 * Senaryolar:
 *  A) Öğrenci bugün HIÇ giriş yapmadıysa → 12, 16, 20, 22
 *  B) Öğrenci giriş yaptı ama görev yapmadı/az yaptı → 17, 20, 22
 *  C) schedule_items.time_note eşleşen saatte → özel görev bildirimi
 */

// CLI, localhost, pseudo-cron (CRON_RUN) veya jetonlu harici tetikleyici ile çalıştırılabilir.
// Harici tetikleyici (cron-job.org / UptimeRobot vb.): secrets.php içine
//   define('CRON_TOKEN', 'uzun-rastgele-dizi');
// ekleyin ve şu adresi 5 dakikada bir çağırtın:
//   https://derspros.com.tr/cron_notifications.php?token=uzun-rastgele-dizi
// Böylece bildirimler site trafiğinden bağımsız, düzenli çalışır.
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

// Kaçan saat dilimlerini telafi penceresi: cron hedef saatte çalışamadıysa
// bildirimi bu kadar saat boyunca hâlâ gönderebilir (tekrar koruması
// push_notification_log üzerinden zaten var — aynı bildirim 2 kez gitmez).
const NOTIF_CATCHUP_HOURS = 2;

define('CRON_RUN', true);
require_once __DIR__ . '/db.php';

// Composer autoloader
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    exit("[CRON ERROR] vendor/autoload.php bulunamadı. 'composer install' çalıştırın.\n");
}
require_once $autoload;
require_once __DIR__ . '/push_config.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// Yeni müfredat şeması + schedule_items.edu_topic_id garanti (idempotent).
// Bildirim sorgusu education_* tablolarına JOIN yaptığı için burada da hazır olmalı.
require_once __DIR__ . '/education_lib.php';
try {
    education_ensure_schema($pdo);
    if ($pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'edu_topic_id'")->rowCount() === 0) {
        $pdo->exec("ALTER TABLE schedule_items ADD COLUMN edu_topic_id INT NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE schedule_items ADD KEY idx_si_edu_topic (edu_topic_id)");
    }
} catch (Throwable $e) { /* şema hazır değilse bildirim eski alanlarla devam eder */ }

// ── Ödeme otomasyonu: günü geçen iptal edilmemiş randevular için 'odenmedi' borç
//    kaydı üret (tüm öğretmenler). Öğretmen sayfayı hiç açmasa da otomatik işler. ──
try {
    require_once __DIR__ . '/payments_lib.php';
    payments_ensure_schema($pdo);
    $genCount = payments_generate_due($pdo, null);
    if ($genCount > 0) cron_log("  [ÖDEME] $genCount adet vadesi geçmiş seans borç olarak eklendi.");
} catch (Throwable $e) { /* ödeme tablosu yoksa yok say */ }

// ── Günlük bakım: 90 günden eski bildirim loglarını temizle (günde 1 kez) ────
require_once __DIR__ . '/app_settings_lib.php';
try {
    if (app_setting_get($pdo, 'last_log_prune') !== date('Y-m-d')) {
        $delCnt = $pdo->exec("DELETE FROM push_notification_log WHERE scheduled_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
        app_setting_set($pdo, 'last_log_prune', date('Y-m-d'));
        if ($delCnt) cron_log("  [BAKIM] $delCnt eski bildirim log kaydı silindi (>90 gün).");
    }
} catch (Throwable $e) { /* log tablosu yoksa yok say */ }

// ── Bildirim Ayarları: DB'den oku, yoksa varsayılan kullan ───────────────────
function get_notif_setting(PDO $pdo, int $teacher_id, int $student_id, string $scenario): array {
    static $cache = [];
    $key = "$teacher_id:$student_id:$scenario";
    if (isset($cache[$key])) return $cache[$key];

    // Önce öğrenciye özel, sonra genel öğretmen ayarı
    $row = null;
    $stmt = $pdo->prepare("SELECT is_active, hour, title, body FROM notification_settings
        WHERE teacher_id=? AND student_id=? AND scenario=? LIMIT 1");
    $stmt->execute([$teacher_id, $student_id, $scenario]);
    $row = $stmt->fetch();

    if (!$row) {
        $stmt2 = $pdo->prepare("SELECT is_active, hour, title, body FROM notification_settings
            WHERE teacher_id=? AND student_id IS NULL AND scenario=? LIMIT 1");
        $stmt2->execute([$teacher_id, $scenario]);
        $row = $stmt2->fetch();
    }

    $cache[$key] = $row ?: [];
    return $cache[$key];
}

$notif_defaults = [
    'A_12'     => ['hour'=>12, 'title'=>'📚 Günaydın! Bugünkü programın hazır',        'body'=>'Merhaba {ad}! Bugün için {toplam} görevin seni bekliyor. Hadi başlayalım!'],
    'A_16'     => ['hour'=>16, 'title'=>'⏰ Programın Seni Bekliyor',                   'body'=>'Bugün henüz sisteme girmedin. {toplam} görevin tamamlanmayı bekliyor. Zaman geçiyor!'],
    'A_20'     => ['hour'=>20, 'title'=>'⚠️ Önemli: Bugünkü Programın Tamamlanmadı',  'body'=>'Bugün sisteme hiç girmedin. Hedefine ulaşmak için hâlâ zamanın var, şimdi başla!'],
    'A_22'     => ['hour'=>22, 'title'=>'🚨 Bugün Kaçan Bir Gün Daha',                 'body'=>'Başarı tesadüf değildir. Bugün programına hiç girmedin. Yarın mutlaka devam et.'],
    'B_17'     => ['hour'=>17, 'title'=>'📝 Günlük Görevlerini Tamamla',              'body'=>'Çalıştıklarını sisteme işlemeyi unutma! Bugün hâlâ hiç görev işaretlemedin.'],
    'B_20'     => ['hour'=>20, 'title'=>'📊 Hedefin Gerisinde Kalma!',                 'body'=>'Bugünkü görevlerin %50\'den azı tamamlandı. Yaptıklarını sisteme işlemeyi unutma!'],
    'B_22_no'  => ['hour'=>22, 'title'=>'🌙 Bugün Çalışmayı Unutma',                  'body'=>'Sisteme girdin ama henüz hiç görev işaretlemedin. Başarı için her gün düzenli çalışmak şart!'],
    'B_22_done'=> ['hour'=>22, 'title'=>'🎉 Harika Bir Gün!',                          'body'=>'Tebrikler! Bugünkü tüm görevlerini tamamladın. Yarının programına da göz atmayı unutma!'],
    'C'        => ['hour'=>null,'title'=>null,'body'=>null],
    // Öğretmene giden özet bildirimler (student_id her zaman NULL kaydedilir)
    'T_LOGIN'  => ['hour'=>17, 'title'=>'👀 Giriş Yapmayan Öğrenciler',  'body'=>'{toplam} öğrenciden {sayi} tanesi bugün sisteme hiç girmedi: {isimler}'],
    'T_TASKS'  => ['hour'=>22, 'title'=>'📉 Görev Yapmayan Öğrenciler', 'body'=>'Bugün görevi olan {toplam} öğrenciden {sayi} tanesi henüz hiç görev işaretlemedi: {isimler}'],
    'T_RISK'   => ['hour'=>8,  'title'=>'🚨 Risk Bölgesindeki Öğrenciler', 'body'=>'{sayi} öğrencin risk bölgesinde: {isimler}. Detaylar koç panelindeki risk kartında.'],
    // Veliye giden haftalık özet (yalnızca Pazar; student_id=NULL kaydedilir)
    'P_WEEKLY' => ['hour'=>20, 'title'=>'📊 Haftalık Gelişim Özeti — {ogrenci}', 'body'=>'{ogrenci} bu hafta {gorev_yapilan}/{gorev_toplam} görevi tamamladı (tamamlama: %{yuzde}). Çözülen soru: {soru} · Konu çalışması: {konu_dk} dk.'],
];

function resolve_notif(PDO $pdo, int $teacher_id, int $student_id, string $scenario, string $field, array $defaults): mixed {
    $row = get_notif_setting($pdo, $teacher_id, $student_id, $scenario);
    if (!empty($row[$field])) return $row[$field];
    return $defaults[$scenario][$field] ?? null;
}

function notif_active(PDO $pdo, int $teacher_id, int $student_id, string $scenario): bool {
    $row = get_notif_setting($pdo, $teacher_id, $student_id, $scenario);
    if ($row && isset($row['is_active'])) return (bool)$row['is_active'];
    return true; // varsayılan aktif
}

// ── Zaman Bilgisi ─────────────────────────────────────────────────────────────
date_default_timezone_set('Europe/Istanbul');
$now         = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
$currentHour = (int)$now->format('H');
$today       = $now->format('Y-m-d');

cron_log("=== Cron başladı: {$now->format('Y-m-d H:i:s')} | Saat: $currentHour ===");

if (!vapid_configured()) {
    cron_log("[HATA] VAPID anahtarları tanımlı değil. push_setup.php çalıştırın.");
    exit;
}

// ── WebPush Nesnesi ───────────────────────────────────────────────────────────
$webPush = new WebPush([
    'VAPID' => [
        'subject'    => VAPID_SUBJECT,
        'publicKey'  => VAPID_PUBLIC_KEY,
        'privateKey' => VAPID_PRIVATE_KEY,
    ],
]);

// ── Bildirimi Daha Önce Gönderip Göndermediğimizi Kontrol Et ─────────────────
function already_sent(PDO $pdo, int $student_id, string $type, string $date): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM push_notification_log WHERE student_id=? AND notification_type=? AND scheduled_date=?");
        $stmt->execute([$student_id, $type, $date]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function mark_sent(PDO $pdo, int $student_id, string $type, string $date, string $title = '', string $body = ''): void {
    try {
        $pdo->prepare("INSERT IGNORE INTO push_notification_log (student_id, notification_type, scheduled_date, title, body) VALUES (?,?,?,?,?)")
            ->execute([$student_id, $type, $date, $title, $body]);
    } catch (Throwable $e) {
        // title/body sütunu henüz yoksa sadece temel kaydı yap
        try {
            $pdo->prepare("INSERT IGNORE INTO push_notification_log (student_id, notification_type, scheduled_date) VALUES (?,?,?)")
                ->execute([$student_id, $type, $date]);
        } catch (Throwable $e2) {}
    }
}

// ── Bildirimi Gönder ──────────────────────────────────────────────────────────
function send_push(
    WebPush $webPush,
    PDO     $pdo,
    array   $student,
    string  $notifType,
    string  $today,
    string  $title,
    string  $body,
    string  $url = '/'
): void {
    global $webPush;

    if (already_sent($pdo, (int)$student['id'], $notifType, $today)) {
        cron_log("  [SKIP] {$student['name']} — $notifType zaten gönderildi.");
        return;
    }

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'icon'  => '/assets/images/favicon.png',
        'badge' => '/assets/images/favicon.png',
        'tag'   => $notifType,
        'url'   => $url,
    ], JSON_UNESCAPED_UNICODE);

    // Öğrencinin tüm aboneliklerine gönder (birden fazla cihaz)
    foreach ($student['subscriptions'] as $sub) {
        try {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys'     => [
                    'p256dh' => $sub['p256dh'],
                    'auth'   => $sub['auth'],
                ],
            ]);

            $report = $webPush->sendOneNotification($subscription, $payload);

            if ($report->isSuccess()) {
                cron_log("  [OK] {$student['name']} — $notifType gönderildi.");
            } elseif ($report->isSubscriptionExpired()) {
                // 410/404 → Aboneliği sil, bildirimleri kapat
                cron_log("  [EXPIRED] {$student['name']} aboneliği sona erdi. Siliniyor...");
                $pdo->prepare("DELETE FROM push_subscriptions WHERE student_id=? AND endpoint=?")
                    ->execute([(int)$student['id'], $sub['endpoint']]);
                $pdo->prepare("UPDATE users SET push_notifications_enabled=0 WHERE id=?")
                    ->execute([(int)$student['id']]);
            } else {
                $statusCode = $report->getResponse() ? $report->getResponse()->getStatusCode() : 'N/A';
                cron_log("  [FAIL] {$student['name']} — $notifType | HTTP $statusCode | " . $report->getReason());
            }
        } catch (Throwable $e) {
            cron_log("  [EXCEPTION] {$student['name']} — {$e->getMessage()}");
        }
    }

    mark_sent($pdo, (int)$student['id'], $notifType, $today, $title, $body);
}

// ── Öğrenci Verilerini Çek ────────────────────────────────────────────────────
// push_notifications_enabled = 1, aboneliği olan, bugün görevi olan öğrenciler
try {
    $stmtStudents = $pdo->prepare("
        SELECT DISTINCT
            u.id,
            CONCAT(u.first_name, ' ', u.last_name) AS name,
            u.last_login_at,
            u.push_notifications_enabled,
            cr.teacher_id
        FROM users u
        JOIN push_subscriptions ps ON ps.student_id = u.id
        LEFT JOIN coaching_relationships cr ON cr.student_id = u.id
        WHERE u.role = 'student'
          AND u.push_notifications_enabled = 1
          AND EXISTS (
              SELECT 1 FROM schedule_items si
              WHERE si.student_id = u.id
                AND DATE(si.date) = :today
          )
        ORDER BY u.first_name
    ");
    $stmtStudents->execute([':today' => $today]);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    cron_log("[HATA] Öğrenci sorgusu: " . $e->getMessage());
    exit;
}

cron_log("Bugün görevi olan aktif öğrenci sayısı: " . count($students));

// ── Her Öğrenci İçin Bildirim Mantığı ────────────────────────────────────────
foreach ($students as $student) {
    $sid = (int)$student['id'];

    // Abonelikleri al
    $subStmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE student_id = ?");
    $subStmt->execute([$sid]);
    $student['subscriptions'] = $subStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($student['subscriptions'])) continue;

    cron_log("\n► Öğrenci: {$student['name']} (ID: $sid)");

    // ── Görev istatistikleri ─────────────────────────────────────────────────
    try {
        $taskStmt = $pdo->prepare("
            SELECT
                COUNT(*)                                                          AS total,
                SUM(CASE WHEN status IN ('yapildi','yarim') THEN 1 ELSE 0 END)   AS done,
                SUM(CASE WHEN status = 'yapildi' THEN 1 ELSE 0 END)              AS completed
            FROM schedule_items
            WHERE student_id = :sid AND DATE(date) = :today
        ");
        $taskStmt->execute([':sid' => $sid, ':today' => $today]);
        $taskStats = $taskStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        cron_log("  [HATA] Görev sorgusu: " . $e->getMessage());
        continue;
    }

    $totalTasks     = (int)($taskStats['total']     ?? 0);
    $doneTasks      = (int)($taskStats['done']      ?? 0);
    $completedTasks = (int)($taskStats['completed'] ?? 0);
    $donePercent    = $totalTasks > 0 ? ($doneTasks / $totalTasks) * 100 : 0;
    $allCompleted   = ($totalTasks > 0 && $completedTasks >= $totalTasks);

    cron_log("  Görevler: $doneTasks/$totalTasks yapıldı (%{$donePercent})");
    cron_log("  Abonelik sayısı: " . count($student['subscriptions']));

    // ── Bugün giriş yapıp yapmadığı ──────────────────────────────────────────
    $lastLogin    = $student['last_login_at'];
    $loggedInToday = $lastLogin && (substr($lastLogin, 0, 10) === $today);

    cron_log("  Son giriş: " . ($lastLogin ?: 'hiç') . " | Bugün girdi: " . ($loggedInToday ? 'Evet' : 'Hayır'));

    $tid = (int)($student['teacher_id'] ?? 0);
    $firstName = explode(' ', $student['name'])[0];

    // ════════════════════════════════════════════════════════════════════════
    // DURUM A — Öğrenci bugün sisteme HİÇ GİRİŞ YAPMADI
    // ════════════════════════════════════════════════════════════════════════
    if (!$loggedInToday) {
        // Geç saatliden erkene bak: penceresine girilen İLK senaryo gönderilir ve
        // döngü biter. Böylece cron saatlerce çalışamadıysa bile öğrenci tek
        // koşuda 2-3 bildirimle spamlenmez; kaçan saat telafi penceresi içinde
        // hâlâ yakalanır (tam saat eşleşmesi şartı kaldırıldı).
        foreach (['A_22','A_20','A_16','A_12'] as $sc) {
            if (!notif_active($pdo, $tid, $sid, $sc)) continue;
            $h = (int)resolve_notif($pdo, $tid, $sid, $sc, 'hour', $notif_defaults);
            if ($currentHour < $h || $currentHour > $h + NOTIF_CATCHUP_HOURS) continue;
            $title = resolve_notif($pdo, $tid, $sid, $sc, 'title', $notif_defaults);
            $body  = resolve_notif($pdo, $tid, $sid, $sc, 'body',  $notif_defaults);
            $body  = str_replace(['{ad}','{toplam}'], [$firstName, $totalTasks], $body);
            send_push($webPush, $pdo, $student, $sc, $today, $title, $body, BASE_URL.'/kocluk.php');
            break;
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // DURUM B — Öğrenci giriş yaptı
    // ════════════════════════════════════════════════════════════════════════
    if ($loggedInToday) {
        // Saat eşleşmesi: tam saat yerine telafi penceresi (kaçan dilim yakalanır,
        // tekrar koruması aynı bildirimi ikinci kez göndermez).
        $inWindow = function (string $sc) use ($pdo, $tid, $sid, $notif_defaults, $currentHour): bool {
            $h = (int)resolve_notif($pdo, $tid, $sid, $sc, 'hour', $notif_defaults);
            return $currentHour >= $h && $currentHour <= $h + NOTIF_CATCHUP_HOURS;
        };

        // B_17 — Hiç görev yapmadıysa
        if (notif_active($pdo, $tid, $sid, 'B_17') && $inWindow('B_17') && $doneTasks === 0) {
            $title = resolve_notif($pdo, $tid, $sid, 'B_17', 'title', $notif_defaults);
            $body  = resolve_notif($pdo, $tid, $sid, 'B_17', 'body',  $notif_defaults);
            send_push($webPush, $pdo, $student, 'B_17_no_tasks', $today, $title, $body, BASE_URL.'/kocluk.php');
        }

        // B_20 — %50'den az görev
        if (notif_active($pdo, $tid, $sid, 'B_20') && $inWindow('B_20') && $donePercent < 50 && $doneTasks > 0) {
            $title = resolve_notif($pdo, $tid, $sid, 'B_20', 'title', $notif_defaults);
            $body  = str_replace('{yuzde}', round($donePercent), resolve_notif($pdo, $tid, $sid, 'B_20', 'body', $notif_defaults));
            send_push($webPush, $pdo, $student, 'B_20_below50', $today, $title, $body, BASE_URL.'/kocluk.php');
        }

        // B_22_no — Hiç görev yok
        if (notif_active($pdo, $tid, $sid, 'B_22_no') && $inWindow('B_22_no') && $doneTasks === 0) {
            $title = resolve_notif($pdo, $tid, $sid, 'B_22_no', 'title', $notif_defaults);
            $body  = resolve_notif($pdo, $tid, $sid, 'B_22_no', 'body',  $notif_defaults);
            send_push($webPush, $pdo, $student, 'B_22_no_tasks', $today, $title, $body, BASE_URL.'/kocluk.php');
        }

        // B_22_done — Tüm görevler tamam
        if (notif_active($pdo, $tid, $sid, 'B_22_done') && $inWindow('B_22_done') && $allCompleted) {
            $title = resolve_notif($pdo, $tid, $sid, 'B_22_done', 'title', $notif_defaults);
            $body  = resolve_notif($pdo, $tid, $sid, 'B_22_done', 'body',  $notif_defaults);
            send_push($webPush, $pdo, $student, 'B_22_completed', $today, $title, $body, BASE_URL.'/kocluk.php');
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // DURUM C — Göreve Özel Saat Bildirimleri (time_note eşleşmesi)
    // ════════════════════════════════════════════════════════════════════════
    if (!notif_active($pdo, $tid, $sid, 'C')) { cron_log("  [C] Durum C pasif, atlandı."); continue; }
    try {
        $timeStmt = $pdo->prepare("
            SELECT
                si.id,
                si.time_note,
                COALESCE(es.lesson_name, cs.name, si.custom_subject, 'Görev') AS subject_name,
                COALESCE(et.topic_name, ct.name, si.custom_topic, '')        AS topic_name,
                COALESCE(
                    CASE WHEN si.target_amount IS NOT NULL THEN si.target_amount ELSE si.amount END,
                    0
                ) AS amount,
                COALESCE(ec.name, cs.category) AS category
            FROM schedule_items si
            LEFT JOIN education_topics    et ON et.id = si.edu_topic_id
            LEFT JOIN education_subjects  es ON es.id = et.subject_id
            LEFT JOIN education_categories ec ON ec.id = es.category_id
            LEFT JOIN coaching_topics   ct ON ct.id = si.topic_id
            LEFT JOIN coaching_subjects cs ON cs.id = ct.subject_id
            WHERE si.student_id = :sid
              AND DATE(si.date) = :today
              AND si.time_note IS NOT NULL
              AND si.time_note != ''
              AND si.status NOT IN ('yapildi')
        ");
        $timeStmt->execute([':sid' => $sid, ':today' => $today]);
        $timedTasks = $timeStmt->fetchAll(PDO::FETCH_ASSOC);

        $currentMin = (int)$now->format('i');

        foreach ($timedTasks as $task) {
            $timeNote = trim($task['time_note']);
            // time_note formatları: "20:56", "14:30", "14", "14.30"
            preg_match('/^(\d{1,2})(?:[:\.](\d{2}))?/', $timeNote, $tm);
            $taskHour = isset($tm[1]) ? (int)$tm[1] : -1;
            $taskMin  = isset($tm[2]) ? (int)$tm[2] : 0;

            // Pencere: görev saatinden 5 dk önce → 30 dk sonra. Cron o dakikada
            // çalışamadıysa bildirim yarım saat boyunca hâlâ yakalanır (tekrar
            // koruması var; geç kalmış hatırlatma hiç gitmemesinden iyidir).
            $taskTotalMin = $taskHour * 60 + $taskMin;
            $nowTotalMin  = $currentHour * 60 + $currentMin;
            $delta        = $nowTotalMin - $taskTotalMin; // pozitif = görev saati geçmiş
            cron_log("  [C] Görev #{$task['id']} time_note=$timeNote → {$taskHour}:{$taskMin} | Şu an={$currentHour}:{$currentMin} | Fark=$delta dk");
            if ($taskHour < 0 || $delta < -5 || $delta > 30) {
                cron_log("  [C] Atlandı (zaman penceresi dışında)");
                continue;
            }

            $notifType = 'C_task_' . (int)$task['id'];
            $subject   = $task['subject_name'];
            $topic     = $task['topic_name'] ? " — {$task['topic_name']}" : '';
            $category  = $task['category'] ? " [{$task['category']}]" : '';
            $amount    = (int)$task['amount'];

            send_push($webPush, $pdo, $student, $notifType, $today,
                "📌 Şimdi: {$subject}{$category}",
                "{$subject}{$topic}" . ($amount > 0 ? " — {$amount} adet" : '') . " göreviniz var. Hadi başlayalım!",
                '/kocluk.php'
            );
        }
    } catch (Throwable $e) {
        cron_log("  [HATA] Durum C sorgusu: " . $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════════════
// ÖĞRETMEN BİLDİRİMLERİ — kendi telefonuna özet:
//   T_LOGIN: bugün görevi olup sisteme hiç girmeyen öğrenciler (vars. 17:00)
//   T_TASKS: bugün görevi olup hiç görev işaretlemeyen öğrenciler (vars. 22:00)
// Ayarlar notification_settings'te (teacher_id, student_id=NULL, scenario=T_*).
// Tekrar koruması push_notification_log üzerinden (student_id sütununa
// öğretmenin user id'si yazılır — aynı gün aynı tip ikinci kez gitmez).
// ════════════════════════════════════════════════════════════════════════════
try {
    $teachers = $pdo->query("
        SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
        FROM users u
        JOIN push_subscriptions ps ON ps.student_id = u.id
        WHERE u.role = 'teacher' AND u.push_notifications_enabled = 1
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $teachers = []; }

if ($teachers) cron_log("\nÖğretmen bildirimleri — abone öğretmen sayısı: " . count($teachers));

foreach ($teachers as $teacher) {
    $tid = (int)$teacher['id'];

    $subStmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE student_id = ?");
    $subStmt->execute([$tid]);
    $teacher['subscriptions'] = $subStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($teacher['subscriptions'])) continue;

    // Bugün görevi olan öğrencilerin giriş + görev durumu (tek sorguda)
    try {
        $ps = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_login_at,
                   COUNT(si.id)                                                    AS total,
                   SUM(CASE WHEN si.status IN ('yapildi','yarim') THEN 1 ELSE 0 END) AS done
            FROM coaching_relationships cr
            JOIN users u ON u.id = cr.student_id
            JOIN schedule_items si ON si.student_id = u.id AND DATE(si.date) = :today
            WHERE cr.teacher_id = :tid
            GROUP BY u.id, u.first_name, u.last_login_at
        ");
        $ps->execute([':today' => $today, ':tid' => $tid]);
        $tStudents = $ps->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        cron_log("  [T] Öğrenci sorgusu hatası: " . $e->getMessage());
        continue;
    }

    if (!$tStudents) continue;
    cron_log("► Öğretmen: {$teacher['name']} (ID: $tid) — bugün görevli öğrenci: " . count($tStudents));

    // İsim listesi: en fazla 4 ad, kalanı "+N"
    $nameList = function (array $rows): string {
        $names = array_map(fn($r) => $r['first_name'], $rows);
        if (count($names) > 4) return implode(', ', array_slice($names, 0, 4)) . ' +' . (count($names) - 4);
        return implode(', ', $names);
    };

    $sendTeacher = function (string $sc, array $problem) use ($pdo, $webPush, $tid, $teacher, $tStudents, $today, $currentHour, $notif_defaults, $nameList): void {
        if (!$problem) { cron_log("  [$sc] Sorunlu öğrenci yok, bildirim gerekmiyor."); return; }
        if (!notif_active($pdo, $tid, 0, $sc)) { cron_log("  [$sc] Pasif, atlandı."); return; }
        $h = (int)resolve_notif($pdo, $tid, 0, $sc, 'hour', $notif_defaults);
        if ($currentHour < $h || $currentHour > $h + NOTIF_CATCHUP_HOURS) { cron_log("  [$sc] Saat penceresi dışında (hedef: $h)."); return; }
        $title = resolve_notif($pdo, $tid, 0, $sc, 'title', $notif_defaults);
        $body  = str_replace(
            ['{toplam}', '{sayi}', '{isimler}'],
            [count($tStudents), count($problem), $nameList($problem)],
            resolve_notif($pdo, $tid, 0, $sc, 'body', $notif_defaults)
        );
        send_push($webPush, $pdo, $teacher, $sc, $today, $title, $body, BASE_URL . '/koc_paneli.php');
    };

    $notLogged = array_values(array_filter($tStudents,
        fn($s) => !($s['last_login_at'] && substr($s['last_login_at'], 0, 10) === $today)));
    $noTasks   = array_values(array_filter($tStudents, fn($s) => (int)$s['done'] === 0));

    $sendTeacher('T_LOGIN', $notLogged);
    $sendTeacher('T_TASKS', $noTasks);

    // T_RISK (S3): kırmızı seviyedeki öğrenciler — ff_risk açıkken, günde bir.
    // Bugünkü görev listesinden bağımsızdır; koçun TÜM öğrencilerini tarar.
    try {
        require_once __DIR__ . '/app_settings_lib.php';
        require_once __DIR__ . '/risk_lib.php';
        if (ff_enabled($pdo, 'risk') && notif_active($pdo, $tid, 0, 'T_RISK')) {
            $hR = (int)resolve_notif($pdo, $tid, 0, 'T_RISK', 'hour', $notif_defaults);
            if ($currentHour >= $hR && $currentHour <= $hR + NOTIF_CATCHUP_HOURS) {
                $redOnes = array_values(array_filter(risk_get_for_teacher($pdo, $tid),
                    fn($r) => $r['level'] === 'red'));
                if ($redOnes) {
                    $titleR = resolve_notif($pdo, $tid, 0, 'T_RISK', 'title', $notif_defaults);
                    $bodyR  = str_replace(['{sayi}', '{isimler}'],
                        [count($redOnes), $nameList($redOnes)],
                        resolve_notif($pdo, $tid, 0, 'T_RISK', 'body', $notif_defaults));
                    send_push($webPush, $pdo, $teacher, 'T_RISK', $today, $titleR, $bodyR,
                              BASE_URL . '/teacher_dashboard.php');
                } else {
                    cron_log("  [T_RISK] Kırmızı öğrenci yok.");
                }
            }
        }
    } catch (Throwable $e) { cron_log("  [T_RISK] Hata: " . $e->getMessage()); }
}

// ════════════════════════════════════════════════════════════════════════════
// VELİ HAFTALIK ÖZETİ — P_WEEKLY (yalnızca PAZAR, vars. 20:00)
// Veli hesabının push aboneliğine, çocuğunun bu haftaki görev/soru özetini yollar.
// Ayar: öğretmenin notification_settings kaydı (student_id=NULL, scenario=P_WEEKLY).
// Tekrar koruması: push_notification_log (student_id=VELİ id, tip P_WEEKLY_{çocukId},
// tarih=hafta başlangıcı) — aynı hafta ikinci kez gitmez.
// ════════════════════════════════════════════════════════════════════════════
if ((int)$now->format('N') === 7) {
    try {
        $parents = $pdo->query("
            SELECT DISTINCT p.id AS parent_id,
                   CONCAT(p.first_name, ' ', p.last_name) AS parent_name,
                   pr.student_id,
                   CONCAT(stu.first_name, ' ', stu.last_name) AS student_name,
                   stu.first_name AS student_first,
                   cr.teacher_id
            FROM users p
            JOIN push_subscriptions ps ON ps.student_id = p.id
            JOIN parent_relationships pr ON pr.parent_id = p.id
            JOIN users stu ON stu.id = pr.student_id
            LEFT JOIN coaching_relationships cr ON cr.student_id = pr.student_id
            WHERE p.role = 'parent' AND p.push_notifications_enabled = 1
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $parents = []; }

    if ($parents) cron_log("\nVeli haftalık özet — abone veli-çocuk çifti: " . count($parents));

    $weekMon = date('Y-m-d', strtotime('monday this week'));
    $weekSun = date('Y-m-d', strtotime($weekMon . ' +6 days'));

    foreach ($parents as $pr) {
        $pid   = (int)$pr['parent_id'];
        $stuId = (int)$pr['student_id'];
        $tid   = (int)($pr['teacher_id'] ?? 0);

        if (!notif_active($pdo, $tid, 0, 'P_WEEKLY')) { cron_log("  [P] Öğretmen kapatmış, atlandı ({$pr['student_name']})."); continue; }
        $h = (int)resolve_notif($pdo, $tid, 0, 'P_WEEKLY', 'hour', $notif_defaults);
        if ($currentHour < $h || $currentHour > $h + NOTIF_CATCHUP_HOURS) continue;

        // Haftalık istatistik (Pzt-Paz)
        try {
            $ws = $pdo->prepare("
                SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status IN ('yapildi','yarim') THEN 1 ELSE 0 END) AS done,
                       SUM(CASE WHEN action_type='soru' AND status='yapildi' THEN amount ELSE 0 END) AS soru,
                       SUM(CASE WHEN action_type='konu' AND status='yapildi' THEN amount ELSE 0 END) AS konu_dk
                FROM schedule_items
                WHERE student_id = ? AND date BETWEEN ? AND ?");
            $ws->execute([$stuId, $weekMon, $weekSun]);
            $st = $ws->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { continue; }

        $total = (int)($st['total'] ?? 0);
        if ($total === 0) { cron_log("  [P] {$pr['student_name']} — bu hafta görev yok, özet gönderilmedi."); continue; }
        $done  = (int)($st['done'] ?? 0);
        $pct   = (int)round(100 * $done / $total);

        // Velinin abonelikleri (send_push'un beklediği yapı)
        $subStmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE student_id = ?");
        $subStmt->execute([$pid]);
        $parent = ['id' => $pid, 'name' => $pr['parent_name'], 'subscriptions' => $subStmt->fetchAll(PDO::FETCH_ASSOC)];
        if (empty($parent['subscriptions'])) continue;

        $repl = [
            '{ogrenci}'       => $pr['student_first'] ?: $pr['student_name'],
            '{yuzde}'         => $pct,
            '{gorev_yapilan}' => $done,
            '{gorev_toplam}'  => $total,
            '{soru}'          => (int)($st['soru'] ?? 0),
            '{konu_dk}'       => (int)($st['konu_dk'] ?? 0),
        ];
        $title = strtr((string)resolve_notif($pdo, $tid, 0, 'P_WEEKLY', 'title', $notif_defaults), $repl);
        $body  = strtr((string)resolve_notif($pdo, $tid, 0, 'P_WEEKLY', 'body',  $notif_defaults), $repl);

        cron_log("► Veli: {$pr['parent_name']} ← {$pr['student_name']} ($done/$total, %$pct)");
        send_push($webPush, $pdo, $parent, 'P_WEEKLY_' . $stuId, $weekMon, $title, $body, BASE_URL . '/veli/veli_paneli.php');
    }
}

// ── Günlük metrik hesabı (S0 omurga): gece 03:00 sonrası günde bir kez ────────
// Ucuz kapı metrics_daily_tick içinde; pseudo-cron'un her 4 dk çağrısında
// yalnızca tek SELECT maliyeti vardır.
try {
    require_once __DIR__ . '/metrics_lib.php';
    $mres = metrics_daily_tick($pdo);
    if ($mres !== null) {
        cron_log("► Metrikler hesaplandı: {$mres['days']} gün, {$mres['rows']} satır");
    }
} catch (Throwable $e) {
    cron_log("► Metrik hatası: " . $e->getMessage());
}

// ── Risk skoru (S3): metriklerden sonra günde bir kez. Hesap bayraktan
// bağımsız çalışır (geçmiş birikir); görünürlük + push ff_risk ile açılır.
try {
    require_once __DIR__ . '/risk_lib.php';
    $rres = risk_daily_tick($pdo);
    if ($rres !== null) {
        cron_log("► Risk skorları: {$rres['students']} öğrenci (kırmızı {$rres['red']}, sarı {$rres['yellow']})");
    }
} catch (Throwable $e) {
    cron_log("► Risk hatası: " . $e->getMessage());
}

cron_log("\n=== Cron tamamlandı: " . (new DateTime())->format('Y-m-d H:i:s') . " ===\n");

// ── Yardımcı: Log Yaz (2 MB'ı aşınca kendini sıfırlar) ───────────────────────
function cron_log(string $msg): void {
    $file = __DIR__ . '/cron_log.txt';
    if (is_file($file) && filesize($file) > 2 * 1024 * 1024) {
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . "] (log 2MB'ı aştı, sıfırlandı)\n");
    }
    $line = '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    file_put_contents($file, $line, FILE_APPEND);
}
