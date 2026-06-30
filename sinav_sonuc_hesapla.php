<?php
require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $exam_id = $_POST['exam_id'];
    $student_id = $_SESSION['user_id'];
    
    // Sınav bilgilerini çek
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    
    $key_string = $exam['answer_key'];
    $total_questions = strlen($key_string);
    
    $correct = 0;
    $wrong = 0;
    $empty = 0;
    $student_answers = [];
    $details = []; // Hangi dersten kaç net (Basitçe hepsini tek kalem yapıyoruz şimdilik)

    // Soruları döngüye al ve kontrol et
    for ($i = 0; $i < $total_questions; $i++) {
        $q_num = $i + 1;
        $correct_answer = $key_string[$i]; // Örn: 'A'
        
        // Öğrencinin cevabı (q1, q2...)
        $user_answer = isset($_POST['q'.$q_num]) ? $_POST['q'.$q_num] : null;
        
        $student_answers[$q_num] = $user_answer; // Kayıt için
        
        if ($user_answer) {
            if ($user_answer == $correct_answer) {
                $correct++;
            } else {
                $wrong++;
            }
        } else {
            $empty++;
        }
    }
    
    // Net Hesapla (4 Yanlış 1 Doğruyu Götürür)
    $net = $correct - ($wrong * 0.25);
    
    // JSON verisi hazırla
    $details_json = json_encode(['Genel' => ['d'=>$correct, 'y'=>$wrong, 'n'=>$net]]);
    
    // Veritabanına Yaz
    $sql = "INSERT INTO exam_results (student_id, name, date, category, total_net, details) VALUES (?, ?, NOW(), ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id, $exam['title'], $exam['category'], $net, $details_json]);
    
    // Sonuç sayfasına yönlendir (Basit bir alert ile)
    echo "<script>
        alert('Sınav Bitti! Doğru: $correct, Yanlış: $wrong, Net: $net');
        window.location.href = 'kocluk.php';
    </script>";
}
?>