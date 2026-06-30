<?php
// ajax/calendar_api.php
ini_set('display_errors', 0);
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]); exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    if ($action == 'fetch') {
        // Tarih aralığındaki randevuları çek
        $sql = "SELECT a.*, u.first_name, u.last_name 
                FROM appointments a 
                JOIN users u ON a.student_id = u.id 
                WHERE a.start_event BETWEEN ? AND ? AND a.coach_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_POST['start'], $_POST['end'], $user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach($rows as $r) {
            $color = ($r['status'] == 'cancelled') ? '#ef4444' : '#3b82f6'; // Kırmızı veya Mavi
            $events[] = [
                'id' => $r['id'],
                'title' => $r['first_name'] . ' ' . $r['last_name'], // Takvimde İsim Yazar
                'start' => $r['start_event'],
                'end' => $r['end_event'],
                'backgroundColor' => $color,
                'extendedProps' => [
                    'student_id' => $r['student_id'],
                    'coach_note' => $r['coach_note'],
                    'status' => $r['status']
                ]
            ];
        }
        echo json_encode($events);
    }
    elseif ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO appointments (coach_id, student_id, start_event, end_event, coach_note) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $_POST['student_id'], $_POST['start'], $_POST['end'], $_POST['coach_note']]);
        echo json_encode(['status' => 'success']);
    }
    elseif ($action == 'update') {
        $stmt = $pdo->prepare("UPDATE appointments SET start_event=?, end_event=?, coach_note=?, status=? WHERE id=? AND coach_id=?");
        $stmt->execute([$_POST['start'], $_POST['end'], $_POST['coach_note'], $_POST['status'], $_POST['id'], $user_id]);
        echo json_encode(['status' => 'success']);
    }
    elseif ($action == 'delete') {
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id=? AND coach_id=?");
        $stmt->execute([$_POST['id'], $user_id]);
        echo json_encode(['status' => 'success']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>