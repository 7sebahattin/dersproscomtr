<?php
/**
 * suggest_api.php — Görev öneri kuyruğu API (yalnız öğretmen)
 *
 * POST ?action=create   -> analiz "Görev öner" butonu (JSON gövde: draft)
 * POST ?action=approve  -> id, date?, amount?
 * POST ?action=reject   -> id
 * GET  ?action=pending  -> bekleyen öneriler
 *
 * ff_suggest kapalıyken tüm uçlar 403 döner (bayrak: admin/features.php).
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app_settings_lib.php';
require_once __DIR__ . '/../suggest_lib.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Öğretmen girişi gerekli.']);
    exit;
}
if (!ff_enabled($pdo, 'suggest')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Öneri modülü kapalı.']);
    exit;
}

$teacherId = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        case 'create':
            $draft = json_decode(file_get_contents('php://input'), true);
            if (!is_array($draft)) { echo json_encode(['ok' => false, 'error' => 'Geçersiz veri.']); break; }
            $res = suggest_create($pdo, $teacherId, $draft + ['source' => 'analiz']);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'approve':
            $res = suggest_decide($pdo, $teacherId, (int)($_POST['id'] ?? 0), true,
                                  $_POST['date'] ?? null,
                                  isset($_POST['amount']) ? (int)$_POST['amount'] : null);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'reject':
            $res = suggest_decide($pdo, $teacherId, (int)($_POST['id'] ?? 0), false);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'pending':
            echo json_encode(['ok' => true, 'data' => suggest_pending_for_teacher($pdo, $teacherId)],
                             JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Bilinmeyen action.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Sunucu hatası: ' . $e->getMessage()]);
}
