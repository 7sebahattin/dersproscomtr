<?php
require_once 'db.php';
include 'header.php';

// Sadece öğretmenler
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    $is_online = isset($_POST['is_online']) ? 1 : 0;
    $answer_key = strtoupper(trim($_POST['answer_key'])); // Harfleri büyüt

    // Dosya Yükleme
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        $upload_dir = 'assets/uploads/';
        // Dosya ismini benzersiz yap
        $file_name = time() . '_' . basename($_FILES['pdf_file']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if ($file_type == 'pdf') {
            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_file)) {
                // Veritabanına kaydet
                $stmt = $pdo->prepare("INSERT INTO exams (teacher_id, title, category, pdf_file, answer_key, is_online) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$_SESSION['user_id'], $title, $category, $target_file, $answer_key, $is_online])) {
                    $message = "Sınav başarıyla yüklendi!";
                } else {
                    $error = "Veritabanı hatası.";
                }
            } else {
                $error = "Dosya yüklenirken hata oluştu.";
            }
        } else {
            $error = "Sadece PDF dosyası yükleyebilirsiniz.";
        }
    } else {
        $error = "Lütfen bir dosya seçin.";
    }
}
?>

<div class="max-w-2xl mx-auto py-10 px-4">
    <div class="bg-white rounded-3xl shadow-xl border border-indigo-50 p-8">
        
        <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
            <a href="koc_paneli.php" class="text-slate-400 hover:text-indigo-600 transition">← Geri</a>
            <h1 class="text-2xl font-bold text-slate-800">Yeni Sınav Yükle</h1>
        </div>

        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo $error; ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Sınav Başlığı</label>
                <input type="text" name="title" required class="js-upper w-full border p-3 rounded-xl" placeholder="Örn: TYT Deneme 1">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Sınav Türü</label>
                <select name="category" class="w-full border p-3 rounded-xl">
                    <option value="TYT">TYT</option>
                    <option value="AYT">AYT</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">PDF Dosyası</label>
                <input type="file" name="pdf_file" accept=".pdf" required class="w-full border p-3 rounded-xl bg-slate-50">
            </div>

            <div class="bg-amber-50 p-4 rounded-xl border border-amber-100 mt-4">
                <div class="flex items-center gap-2 mb-4">
                    <input type="checkbox" name="is_online" id="is_online_checkbox" onclick="toggleOnlineOptions()" class="w-5 h-5 text-amber-600">
                    <label for="is_online_checkbox" class="text-sm font-bold text-amber-900 cursor-pointer select-none">
                        Bu sınavı online çözülebilir yap ✍️
                    </label>
                </div>

                <div id="online_options" class="hidden space-y-4 pl-6 border-l-2 border-amber-200">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Cevap Anahtarı (Bitişik Yazın: ADCBE...)</label>
                        <input type="text" name="answer_key" class="js-upper w-full border p-3 rounded-xl font-mono tracking-widest uppercase" placeholder="ABCDE...">
                        <p class="text-[10px] text-slate-400 mt-1">Soru sayısı, girdiğiniz harf sayısına göre otomatik belirlenir.</p>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-4 rounded-xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                Sınavı Yayınla 🚀
            </button>
        </form>
    </div>
</div>

<script>
    function toggleOnlineOptions() {
        const checkbox = document.getElementById('is_online_checkbox');
        const options = document.getElementById('online_options');
        if (checkbox.checked) options.classList.remove('hidden');
        else options.classList.add('hidden');
    }
</script>

<?php include 'footer.php'; ?>