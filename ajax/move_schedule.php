<?php
require_once '../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Güvenlik: yalnızca öğretmen, kendi öğrencisine ait bir görevin tarihini değiştirebilir
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(401);
    exit('Yetkisiz erişim.');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item_id']) && isset($_POST['new_date'])) {
    $teacherId = (int)$_SESSION['user_id'];
    $itemId    = (int)$_POST['item_id'];
    $newDate   = $_POST['new_date'];

    $stmt = $pdo->prepare("UPDATE schedule_items si
                            JOIN coaching_relationships cr ON cr.student_id = si.student_id
                            SET si.date = ?
                            WHERE si.id = ? AND cr.teacher_id = ?");
    $stmt->execute([$newDate, $itemId, $teacherId]);
    echo $stmt->rowCount() > 0 ? "OK" : "NOT_FOUND";
}
?>