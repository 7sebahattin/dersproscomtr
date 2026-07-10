<?php
/**
 * ajax/push_subscribe.php
 * Tarayıcı push aboneliğini kaydeder / günceller.
 * POST JSON: { subscription: {...}, action: 'subscribe'|'resubscribe' }
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/');
    session_start();
}

require_once __DIR__ . '/../db.php';

// Giriş yapmış öğrenci VEYA öğretmen (öğretmen bildirimleri: T_LOGIN/T_TASKS).
// Not: push_subscriptions.student_id sütunu aslında "user_id" anlamında kullanılır;
// öğrenci cron sorgusu role='student' filtrelediği için öğretmen kayıtları karışmaz.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['student', 'teacher'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Yetkisiz erişim.']);
    exit;
}

$student_id = (int)$_SESSION['user_id'];

// JSON body oku
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['subscription'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Geçersiz veri.']);
    exit;
}

$sub      = $body['subscription'];
$endpoint = $sub['endpoint'] ?? '';
$p256dh   = $sub['keys']['p256dh'] ?? '';
$auth_key = $sub['keys']['auth']   ?? '';

if (!$endpoint || !$p256dh || !$auth_key) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Eksik abonelik verisi.']);
    exit;
}

$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

try {
    // push_subscriptions tablosunu kontrol et (auto-migrate)
    $pdo->query("SELECT 1 FROM push_subscriptions LIMIT 1");
} catch (PDOException $e) {
    // Tablo yoksa oluştur
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            endpoint   TEXT NOT NULL,
            p256dh     TEXT NOT NULL,
            auth       VARCHAR(50) NOT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_student_endpoint (student_id, endpoint(200))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'msg' => 'Tablo oluşturulamadı: ' . $ex->getMessage()]);
        exit;
    }
}

try {
    // INSERT ... ON DUPLICATE KEY UPDATE → hem yeni kayıt hem güncelleme
    $stmt = $pdo->prepare("
        INSERT INTO push_subscriptions (student_id, endpoint, p256dh, auth, user_agent)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            p256dh     = VALUES(p256dh),
            auth       = VALUES(auth),
            user_agent = VALUES(user_agent),
            updated_at = NOW()
    ");
    $stmt->execute([$student_id, $endpoint, $p256dh, $auth_key, $ua]);

    // Bildirimleri aktif et (daha önce kapatılmış olabilir)
    $pdo->prepare("UPDATE users SET push_notifications_enabled = 1 WHERE id = ?")
        ->execute([$student_id]);

    echo json_encode(['ok' => true, 'msg' => 'Abonelik kaydedildi.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'DB hatası: ' . $e->getMessage()]);
}
