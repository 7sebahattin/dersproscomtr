<?php
// Hataları göster
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
// Header'ı dahil etmiyoruz çünkü bu sayfa tam ekran sınav modu olacak
session_start();

// 1. GÜVENLİK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: index.php");
    exit;
}

// 2. SINAV BİLGİSİNİ ÇEK
if (!isset($_GET['exam_id'])) { die("Sınav ID eksik."); }
$exam_id = $_GET['exam_id'];

$stmt = $pdo->prepare("SELECT * FROM teacher_exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) { die("Sınav bulunamadı."); }
if (!$exam['is_online']) { die("Bu sınav online çözüme kapalı."); }

// Soru Sayısını Belirle (Cevap anahtarı uzunluğu kadar)
$answer_key = $exam['answer_key'] ?? '';
$question_count = strlen($answer_key);
if ($question_count == 0) $question_count = 20; // Varsayılan

// 3. SINAVI KAYDET (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correct = 0;
    $wrong = 0;
    $empty = 0;
    $student_answers = "";

    for ($i = 1; $i <= $question_count; $i++) {
        $user_ans = $_POST["q$i"] ?? '-';
        $true_ans = substr($answer_key, $i-1, 1);
        
        $student_answers .= $user_ans;

        if ($user_ans == '-') {
            $empty++;
        } elseif ($user_ans == $true_ans) {
            $correct++;
        } else {
            $wrong++;
        }
    }

    $net = $correct - ($wrong * 0.25);

// Sonucu Kaydet (exam_id İLE BİRLİKTE)
    $stmt = $pdo->prepare("INSERT INTO quiz_results (student_id, exam_id, exam_name, category, correct_count, wrong_count, empty_count, total_net) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $exam_id, $exam['title'], $exam['category'], $correct, $wrong, $empty, $net]);

    // Sonuç Sayfasına Yönlendir (Mesajı güncelledik)
    header("Location: denemeler.php?msg=success&net=$net");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav: <?= htmlspecialchars($exam['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: sans-serif; overflow: hidden; }
        /* Optik Form Radyo Buton İptal Özelliği İçin */
        .radio-cancel:checked + label { background-color: #4f46e5; color: white; border-color: #4f46e5; }
    </style>
</head>
<body class="bg-gray-100 h-screen flex flex-col">

    <div class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 shadow-sm z-20">
        <h1 class="font-bold text-lg text-gray-800"><?= htmlspecialchars($exam['title']) ?></h1>
        <a href="denemeler.php" class="text-sm text-red-500 font-bold hover:underline">Çıkış Yap</a>
    </div>

    <div class="flex-grow flex overflow-hidden">
        
        <div class="w-2/3 bg-gray-800 h-full relative">
            <object data="<?= $exam['file_path'] ?>" type="application/pdf" class="w-full h-full">
                <div class="flex flex-col items-center justify-center h-full text-white">
                    <p class="mb-4">Tarayıcınız PDF'i görüntüleyemiyor.</p>
                    <a href="<?= $exam['file_path'] ?>" target="_blank" class="bg-indigo-600 px-6 py-3 rounded-full font-bold hover:bg-indigo-700 transition">PDF'i İndir</a>
                </div>
            </object>
        </div>

        <div class="w-1/3 bg-white h-full overflow-y-auto border-l border-gray-200 shadow-xl relative">
            <div class="p-6">
                <div class="mb-6 sticky top-0 bg-white z-10 pb-4 border-b">
                    <h2 class="text-xl font-bold text-gray-800">Cevap Kağıdı</h2>
                    <p class="text-sm text-gray-500"><?= $question_count ?> Soru • 4 Yanlış 1 Doğruyu Götürür</p>
                </div>

                <form method="POST">
                    <div class="space-y-3 pb-20">
                        <?php for ($i = 1; $i <= $question_count; $i++): ?>
                        <div class="flex items-center justify-between p-2 hover:bg-slate-50 rounded border border-transparent hover:border-slate-100 transition">
                            <span class="font-bold text-indigo-600 w-8 text-lg"><?= $i ?>.</span>
                            <div class="flex gap-2">
                                <?php foreach (['A', 'B', 'C', 'D', 'E'] as $opt): ?>
                                <div class="relative">
                                    <input type="radio" name="q<?= $i ?>" id="q<?= $i ?>_<?= $opt ?>" value="<?= $opt ?>" class="peer sr-only radio-cancel" data-question="q<?= $i ?>">
                                    <label for="q<?= $i ?>_<?= $opt ?>" class="w-8 h-8 rounded-full border-2 border-slate-300 flex items-center justify-center text-xs font-bold text-slate-500 cursor-pointer select-none transition hover:border-indigo-400 hover:bg-indigo-50">
                                        <?= $opt ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div class="sticky bottom-0 bg-white pt-4 pb-6 border-t border-slate-100">
                        <button type="submit" onclick="return confirm('Sınavı bitirmek istediğinize emin misiniz?')" class="w-full bg-green-600 text-white py-4 rounded-xl font-bold text-lg hover:bg-green-700 transition shadow-lg shadow-green-200 transform hover:-translate-y-1 active:scale-95">
                            Sınavı Bitir 🏁
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const radios = document.querySelectorAll('.radio-cancel');
        radios.forEach(radio => {
            radio.addEventListener('click', function(e) {
                if (this.dataset.wasChecked === "true") {
                    this.checked = false;
                    this.dataset.wasChecked = "false";
                } else {
                    const questionName = this.name;
                    document.querySelectorAll(`input[name="${questionName}"]`).forEach(r => {
                        r.dataset.wasChecked = "false";
                    });
                    this.dataset.wasChecked = "true";
                }
            });
        });
    });
    </script>
</body>
</html>