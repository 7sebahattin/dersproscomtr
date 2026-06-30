<?php
require_once '../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success'=>false,'message'=>'Yetkisiz erişim']); exit;
}
$teacher_id = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

// ── Tüm öğrencilere kopyala ───────────────────────────────────────────────────
if (($input['action'] ?? '') === 'copy_to_all') {
    $from_sid = (int)($input['from_student_id'] ?? 0);
    if (!$from_sid) { echo json_encode(['success'=>false,'message'=>'Öğrenci belirtilmedi']); exit; }

    // Kaynak ayarları al
    $src = $pdo->prepare("SELECT scenario, is_active, hour, title, body FROM notification_settings WHERE teacher_id=? AND student_id=?");
    $src->execute([$teacher_id, $from_sid]);
    $srcRows = $src->fetchAll();

    // Genel ayar olarak kaydet (student_id = NULL)
    foreach ($srcRows as $r) {
        $pdo->prepare("INSERT INTO notification_settings (teacher_id, student_id, scenario, is_active, hour, title, body)
            VALUES (?,NULL,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE is_active=VALUES(is_active), hour=VALUES(hour), title=VALUES(title), body=VALUES(body), updated_at=NOW()")
            ->execute([$teacher_id, $r['scenario'], $r['is_active'], $r['hour'], $r['title'], $r['body']]);
    }
    echo json_encode(['success'=>true,'message'=>'Tüm öğrencilere uygulandı']); exit;
}

// ── Tek senaryo kaydet ────────────────────────────────────────────────────────
$scenario  = preg_replace('/[^A-Za-z0-9_]/', '', $input['scenario'] ?? '');
$student_id= isset($input['student_id']) && $input['student_id'] ? (int)$input['student_id'] : null;
$is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
$hour      = isset($input['hour']) && $input['hour'] !== null ? (int)$input['hour'] : null;
$title     = isset($input['title']) ? trim($input['title']) : null;
$body      = isset($input['body'])  ? trim($input['body'])  : null;

if (!$scenario) { echo json_encode(['success'=>false,'message'=>'Senaryo belirtilmedi']); exit; }

try {
    $pdo->prepare("INSERT INTO notification_settings (teacher_id, student_id, scenario, is_active, hour, title, body)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE is_active=VALUES(is_active), hour=VALUES(hour), title=VALUES(title), body=VALUES(body), updated_at=NOW()")
        ->execute([$teacher_id, $student_id, $scenario, $is_active, $hour, $title, $body]);

    $who = $student_id ? 'öğrenciye özel' : 'tüm öğrenciler için';
    echo json_encode(['success'=>true,'message'=>"$scenario ayarı kaydedildi ($who)"]);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'Veritabanı hatası: '.$e->getMessage()]);
}
