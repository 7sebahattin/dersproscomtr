<?php
/**
 * study_api.php — Zaman Motoru API (yalnız öğrenci)
 *
 * POST ?action=start      -> schedule_item_id?, edu_topic_id?, mode (pomodoro|stopwatch)
 * POST ?action=heartbeat  -> session_id, active_sec (istemci kümülatif aktif saniye)
 * POST ?action=finish     -> session_id, active_sec
 * POST ?action=manual     -> minutes, edu_topic_id?
 * GET  ?action=status     -> aktif oturum + bugünkü toplam dakika
 *
 * ff_timer kapalıyken 403 (bayrak: admin/features.php).
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app_settings_lib.php';
require_once __DIR__ . '/../study_lib.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Öğrenci girişi gerekli.']);
    exit;
}
if (!ff_enabled($pdo, 'timer')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Zaman motoru kapalı.']);
    exit;
}

$sid    = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        case 'start':
            echo json_encode(study_start(
                $pdo, $sid,
                !empty($_POST['schedule_item_id']) ? (int)$_POST['schedule_item_id'] : null,
                !empty($_POST['edu_topic_id'])     ? (int)$_POST['edu_topic_id']     : null,
                (string)($_POST['mode'] ?? 'stopwatch')
            ), JSON_UNESCAPED_UNICODE);
            break;

        case 'heartbeat':
            echo json_encode(study_heartbeat($pdo, $sid,
                (int)($_POST['session_id'] ?? 0), (int)($_POST['active_sec'] ?? 0)), JSON_UNESCAPED_UNICODE);
            break;

        case 'finish':
            echo json_encode(study_finish($pdo, $sid,
                (int)($_POST['session_id'] ?? 0), (int)($_POST['active_sec'] ?? 0)), JSON_UNESCAPED_UNICODE);
            break;

        case 'manual':
            echo json_encode(study_manual($pdo, $sid,
                (int)($_POST['minutes'] ?? 0),
                !empty($_POST['edu_topic_id']) ? (int)$_POST['edu_topic_id'] : null), JSON_UNESCAPED_UNICODE);
            break;

        case 'status':
            study_ensure_schema($pdo);
            echo json_encode([
                'ok'            => true,
                'session'       => study_session_public(study_active_session($pdo, $sid)),
                'today_minutes' => study_today_minutes($pdo, $sid),
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Bilinmeyen action.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Sunucu hatası: ' . $e->getMessage()]);
}
