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

// CLI, localhost veya pseudo-cron (CRON_RUN) ile çalıştırılabilir
$isCli = php_sapi_name() === 'cli';
if (!$isCli && !defined('CRON_RUN')) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        exit("Erişim yasak.\n");
    }
}

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
    string  $url = '/derspros/'
): void {
    global $webPush;

    if (already_sent($pdo, (int)$student['id'], $notifType, $today)) {
        cron_log("  [SKIP] {$student['name']} — $notifType zaten gönderildi.");
        return;
    }

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'icon'  => '/derspros/assets/images/favicon.png',
        'badge' => '/derspros/assets/images/favicon.png',
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
        foreach (['A_12','A_16','A_20','A_22'] as $sc) {
            if (!notif_active($pdo, $tid, $sid, $sc)) continue;
            $h = (int)resolve_notif($pdo, $tid, $sid, $sc, 'hour', $notif_defaults);
            if ($currentHour !== $h) continue;
            $title = resolve_notif($pdo, $tid, $sid, $sc, 'title', $notif_defaults);
            $body  = resolve_notif($pdo, $tid, $sid, $sc, 'body',  $notif_defaults);
            $body  = str_replace(['{ad}','{toplam}'], [$firstName, $totalTasks], $body);
            send_push($webPush, $pdo, $student, $sc, $today, $title, $body, BASE_URL.'/kocluk.php');
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // DURUM B — Öğrenci giriş yaptı
    // ════════════════════════════════════════════════════════════════════════
    if ($loggedInToday) {
        // B_17 — Hiç görev yapmadıysa
        if (notif_active($pdo, $tid, $sid, 'B_17') && $currentHour === (int)resolve_notif($pdo, $tid, $sid, 'B_17', 'hour', $notif_defaults) && $doneTasks === 0) {
            $title = resolve_notif($pdo, $tid, $sid, 'B_17', 'title', $notif_defaults);
            $body  = resolve_notif($pdo, $tid, $sid, 'B_17', 'body',  $notif_defaults);
            send_push($webPush, $pdo, $student, 'B_17_no_tasks', $today, $title, $body, BASE_URL.'/kocluk.php');
        }

        // B_20 — %50'den az görev
        if (notif_active($pdo, $tid, $sid, 'B_20') && $currentHour === (int)resolve_notif($pdo, $tid, $sid, 'B_20', 'hour', $notif_defaults) && $donePercent < 50 && $doneTasks > 0) {
            $title = resolve_notif($pdo, $tid, $sid, 'B_20', 'title', $notif_defaults);
            $body  = str_replace('{yuzde}', round($donePercent), resolve_notif($pdo, $tid, $sid, 'B_20', 'body', $notif_defaults));
            send_push($webPush, $pdo, $student, 'B_20_below50', $today, $title, $body, BASE_URL.'/kocluk.php');
        }

        // B_22_no — Hiç görev yok
        if (notif_active($pdo, $tid, $sid, 'B_22_no') && $currentHour === (int)resolve_notif($pdo, $tid, $sid, 'B_22_no', 'hour', $notif_defaults) && $doneTasks === 0) {
            $title = resolve_notif($pdo, $tid, $sid, 'B_22_no', 'title', $notif_defaults);
            $body  = resolve_notif($pdo, $tid, $sid, 'B_22_no', 'body',  $notif_defaults);
            send_push($webPush, $pdo, $student, 'B_22_no_tasks', $today, $title, $body, BASE_URL.'/kocluk.php');
        }

        // B_22_done — Tüm görevler tamam
        if (notif_active($pdo, $tid, $sid, 'B_22_done') && $currentHour === (int)resolve_notif($pdo, $tid, $sid, 'B_22_done', 'hour', $notif_defaults) && $allCompleted) {
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
                COALESCE(cs.name, si.custom_subject, 'Görev') AS subject_name,
                COALESCE(ct.name, '')                          AS topic_name,
                COALESCE(
                    CASE WHEN si.target_amount IS NOT NULL THEN si.target_amount ELSE si.amount END,
                    0
                ) AS amount,
                cs.category
            FROM schedule_items si
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

            // Şu anki zamandan ±5 dakika penceresi içinde mi?
            $taskTotalMin = $taskHour * 60 + $taskMin;
            $nowTotalMin  = $currentHour * 60 + $currentMin;
            cron_log("  [C] Görev #{$task['id']} time_note=$timeNote → {$taskHour}:{$taskMin} | Şu an={$currentHour}:{$currentMin} | Fark=" . abs($taskTotalMin - $nowTotalMin) . " dk");
            if ($taskHour < 0 || abs($taskTotalMin - $nowTotalMin) > 5) {
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
                '/derspros/kocluk.php'
            );
        }
    } catch (Throwable $e) {
        cron_log("  [HATA] Durum C sorgusu: " . $e->getMessage());
    }
}

cron_log("\n=== Cron tamamlandı: " . (new DateTime())->format('Y-m-d H:i:s') . " ===\n");

// ── Yardımcı: Log Yaz ─────────────────────────────────────────────────────────
function cron_log(string $msg): void {
    $line = '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    file_put_contents(__DIR__ . '/cron_log.txt', $line, FILE_APPEND);
}
