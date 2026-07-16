<?php
/**
 * ajax/push_test.php — Anlık Test Bildirimi (TEŞHİS aracı)
 *
 * Cron zamanlamasını bypass ederek push pipeline'ını hemen çalıştırır ve
 * her cihaz (abonelik) için gerçek sonucu (başarı / HTTP kodu / sebep) döner.
 * Böylece "bildirim gitmiyor" sorununun kaynağı anında ayırt edilir:
 *   - abonelik yok            → tarayıcı izni/kaydı sorunu
 *   - HTTP 403 (Forbidden)    → VAPID anahtar çifti uyuşmuyor
 *   - HTTP 410/404 (Expired)  → abonelik bayat, yenilenmeli
 *   - success                 → pipeline sağlam; sorun cron ZAMANLAMASINDA
 *
 * Kullanım (POST JSON):
 *   - Öğretmen:  { student_id: <id> }  (kendi öğrencisine)
 *   - Öğrenci:   {}                    (kendine)
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/');
    session_start();
}

require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Yetkisiz erişim.']);
    exit;
}

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Hedef kullanıcıyı belirle ─────────────────────────────────────────────────
if ($role === 'teacher' && ($body['target'] ?? '') === 'self') {
    $student_id = $uid; // öğretmen KENDİNE test (öğretmen bildirimleri için)
} elseif ($role === 'teacher') {
    $student_id = (int)($body['student_id'] ?? 0);
    if ($student_id <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Test için bir öğrenci seçin.']);
        exit;
    }
    // Yetki: yalnızca kendi öğrencisine
    $chk = $pdo->prepare("SELECT 1 FROM coaching_relationships WHERE teacher_id=? AND student_id=? LIMIT 1");
    $chk->execute([$uid, $student_id]);
    if (!$chk->fetchColumn()) {
        echo json_encode(['ok' => false, 'msg' => 'Bu öğrenci sizin listenizde değil.']);
        exit;
    }
} elseif ($role === 'student') {
    $student_id = $uid; // kendine test
} else {
    echo json_encode(['ok' => false, 'msg' => 'Bu işlem yalnızca koç veya öğrenci içindir.']);
    exit;
}

// ── VAPID / WebPush hazırlığı ─────────────────────────────────────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(['ok' => false, 'msg' => 'Sunucu eksik: vendor/autoload.php yok (composer install).']);
    exit;
}
require_once $autoload;
require_once __DIR__ . '/../push_config.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

if (!function_exists('vapid_configured') || !vapid_configured()) {
    echo json_encode(['ok' => false, 'msg' => 'VAPID anahtarları tanımlı değil. push_setup.php çalıştırılmalı.']);
    exit;
}

// ── Öğrenci tercih + abonelikleri ─────────────────────────────────────────────
$u = $pdo->prepare("SELECT first_name, last_name, push_notifications_enabled FROM users WHERE id=?");
$u->execute([$student_id]);
$student = $u->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    echo json_encode(['ok' => false, 'msg' => 'Öğrenci bulunamadı.']);
    exit;
}

$prefEnabled = (int)($student['push_notifications_enabled'] ?? 0);
$isSelf      = ($student_id === $uid);

$subStmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE student_id=?");
$subStmt->execute([$student_id]);
$subs = $subStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Erken teşhis: neden gitmeyeceğini önceden söyle ──────────────────────────
$diagnostics = [];
if ($prefEnabled === 0) {
    $diagnostics[] = $isSelf
        ? 'Bildirim tercihin KAPALI (users.push_notifications_enabled = 0). "Bu cihazda bildirimlere izin ver" ile tekrar açabilirsin.'
        : 'Öğrencinin bildirim tercihi KAPALI (users.push_notifications_enabled = 0). Öğrenci kendi ekranından açmalı.';
}
if (empty($subs)) {
    echo json_encode([
        'ok'      => false,
        'msg'     => $isSelf
            ? 'Bu hesabın kayıtlı cihaz aboneliği yok. Önce "🔔 Bu cihazda bildirimlere izin ver" butonunu kullan.'
            : 'Bu öğrencinin kayıtlı cihaz aboneliği yok. Öğrenci telefonunda/tarayıcısında bildirim iznini vermeli.',
        'pref_enabled' => $prefEnabled,
        'device_count' => 0,
        'diagnostics'  => $diagnostics,
    ]);
    exit;
}

// ── WebPush gönder ────────────────────────────────────────────────────────────
$webPush = new WebPush([
    'VAPID' => [
        'subject'    => VAPID_SUBJECT,
        'publicKey'  => VAPID_PUBLIC_KEY,
        'privateKey' => VAPID_PRIVATE_KEY,
    ],
]);

$firstName = trim(explode(' ', trim($student['first_name'] . ' ' . $student['last_name']))[0] ?: 'Öğrenci');
$payload = json_encode([
    'title' => '🔔 Test Bildirimi',
    'body'  => "Merhaba {$firstName}! Bu bir test bildirimidir — bildirim sistemin çalışıyor. ✅",
    'icon'  => '/assets/images/favicon.png',
    'badge' => '/assets/images/favicon.png',
    'tag'   => 'test-' . time(),
    'url'   => '/kocluk.php',
], JSON_UNESCAPED_UNICODE);

$results   = [];
$anySucces = false;

foreach ($subs as $i => $sub) {
    $label = 'Cihaz #' . ($i + 1);
    // Güvenlik (SSRF): kayıtlı endpoint sonradan bir şekilde geçersiz hale geldiyse
    // (eski kayıt, doğrudan DB müdahalesi vb.) gönderim anında da tekrar doğrula.
    if (!push_endpoint_is_allowed($sub['endpoint'])) {
        $results[] = ['device' => $label, 'ok' => false, 'http' => null, 'detail' => 'Geçersiz abonelik adresi.'];
        continue;
    }
    try {
        $subscription = Subscription::create([
            'endpoint' => $sub['endpoint'],
            'keys'     => ['p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
        ]);
        $report = $webPush->sendOneNotification($subscription, $payload);
        $http   = $report->getResponse() ? $report->getResponse()->getStatusCode() : null;

        if ($report->isSuccess()) {
            $anySucces = true;
            $results[] = ['device' => $label, 'ok' => true, 'http' => $http, 'detail' => 'Gönderildi ✅'];
        } elseif ($report->isSubscriptionExpired()) {
            // Bayat aboneliği temizle
            $pdo->prepare("DELETE FROM push_subscriptions WHERE student_id=? AND endpoint=?")
                ->execute([$student_id, $sub['endpoint']]);
            $results[] = ['device' => $label, 'ok' => false, 'http' => $http,
                          'detail' => 'Abonelik süresi dolmuş (silindi). Öğrenci yeniden izin vermeli.'];
        } else {
            $reason = $report->getReason();
            $hint = '';
            if ($http === 403) $hint = ' → VAPID anahtar çifti büyük olasılıkla uyuşmuyor.';
            elseif ($http === 401) $hint = ' → VAPID kimlik doğrulama başlığı reddedildi.';
            $results[] = ['device' => $label, 'ok' => false, 'http' => $http,
                          'detail' => 'Başarısız: ' . $reason . $hint];
        }
    } catch (Throwable $e) {
        $results[] = ['device' => $label, 'ok' => false, 'http' => null,
                      'detail' => 'İstisna: ' . $e->getMessage()];
    }
}

echo json_encode([
    'ok'           => $anySucces,
    'msg'          => $anySucces
        ? 'Test bildirimi gönderildi. Cihazda görünmüyorsa tarayıcı/işletim sistemi bildirim ayarlarını kontrol edin.'
        : 'Hiçbir cihaza gönderilemedi. Ayrıntılar aşağıda.',
    'pref_enabled' => $prefEnabled,
    'device_count' => count($subs),
    'diagnostics'  => $diagnostics,
    'results'      => $results,
], JSON_UNESCAPED_UNICODE);
