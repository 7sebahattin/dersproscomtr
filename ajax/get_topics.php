<?php
// Dosya Yolu: public_html/ajax/get_topics.php

require_once __DIR__ . '/../db.php';

session_start();

// Güvenlik: giriş yapılmamışsa erişim yok
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

// 1. Ders ID Kontrolü
if (!isset($_GET['subject_id'])) {
    echo json_encode([]);
    exit;
}
$subject_id = (int)$_GET['subject_id'];

// 2. Öğrenci ID Belirleme — rol bazlı, sahiplik kontrolüyle
$role = $_SESSION['role'] ?? '';
$requestedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($role === 'student') {
    // Öğrenci yalnızca kendi verisini görebilir; query param'daki değer yok sayılır
    $student_id = (int)$_SESSION['user_id'];
} elseif ($role === 'teacher') {
    if ($requestedStudentId <= 0) { echo json_encode([]); exit; }
    $own = $pdo->prepare("SELECT 1 FROM coaching_relationships WHERE teacher_id = ? AND student_id = ?");
    $own->execute([(int)$_SESSION['user_id'], $requestedStudentId]);
    if (!$own->fetchColumn()) { http_response_code(403); echo json_encode([]); exit; }
    $student_id = $requestedStudentId;
} elseif (in_array($role, ['admin', 'superuser'], true)) {
    $student_id = $requestedStudentId;
} else {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

try {
    // 3. Konuları ve İstatistikleri Çeken Sorgu (GÜNCELLENDİ)
    // total_soru: Soru sayısını toplar (SUM amount)
    // total_konu: Yapılan konu çalışma sayısını sayar (COUNT) - Dakika değil adet.
    $query = "
        SELECT 
            t.id, 
            t.name,
            COALESCE(SUM(CASE 
                WHEN si.student_id = ? AND si.action_type = 'soru' AND si.status = 'yapildi' 
                THEN si.amount ELSE 0 END), 0) as total_soru,
            COALESCE(COUNT(CASE 
                WHEN si.student_id = ? AND si.action_type = 'konu' AND si.status = 'yapildi' 
                THEN 1 ELSE NULL END), 0) as total_konu
        FROM coaching_topics t
        LEFT JOIN schedule_items si ON t.id = si.topic_id
        WHERE t.subject_id = ?
        GROUP BY t.id, t.name
        ORDER BY t.id ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$student_id, $student_id, $subject_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($topics);

} catch (PDOException $e) {
    error_log('get_topics.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Sunucu hatası.']);
}
?>