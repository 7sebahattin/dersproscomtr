<?php
/**
 * ajax/push_toggle.php
 * Profil sayfasındaki toggle'dan tetiklenir.
 * POST: { enabled: 1|0 }
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/');
    session_start();
}

require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Yetkisiz.']);
    exit;
}

$uid     = (int)$_SESSION['user_id'];
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$enabled = isset($body['enabled']) ? (int)(bool)$body['enabled'] : -1;

if ($enabled === -1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'enabled parametresi gerekli.']);
    exit;
}

try {
    $pdo->prepare("UPDATE users SET push_notifications_enabled = ? WHERE id = ?")
        ->execute([$enabled, $uid]);

    // Kapatılıyorsa aboneliği de sil
    if ($enabled === 0) {
        $pdo->prepare("DELETE FROM push_subscriptions WHERE student_id = ?")
            ->execute([$uid]);
    }

    echo json_encode(['ok' => true, 'enabled' => $enabled]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
