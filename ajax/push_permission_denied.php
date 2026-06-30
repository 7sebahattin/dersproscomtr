<?php
/**
 * ajax/push_permission_denied.php
 * Öğrenci tarayıcıdan bildirimleri engellediğinde çağrılır.
 * DB'de push_notifications_enabled = 0 yapar ve aboneliği siler.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/');
    session_start();
}

require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$uid = (int)$_SESSION['user_id'];

try {
    // Bildirimleri kapat
    $pdo->prepare("UPDATE users SET push_notifications_enabled = 0 WHERE id = ?")
        ->execute([$uid]);

    // Abonelik kaydını temizle
    $pdo->prepare("DELETE FROM push_subscriptions WHERE student_id = ?")
        ->execute([$uid]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
