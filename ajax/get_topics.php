<?php
// Dosya Yolu: public_html/ajax/get_topics.php

require_once __DIR__ . '/../db.php';

session_start();

// 1. Ders ID Kontrolü
if (!isset($_GET['subject_id'])) {
    echo json_encode([]);
    exit;
}
$subject_id = (int)$_GET['subject_id'];

// 2. Öğrenci ID Belirleme
$student_id = 0;
if (isset($_GET['student_id']) && !empty($_GET['student_id']) && $_GET['student_id'] != 'undefined') {
    $student_id = (int)$_GET['student_id'];
} elseif (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student') {
    $student_id = (int)$_SESSION['user_id'];
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
    echo json_encode(['error' => $e->getMessage()]);
}
?>