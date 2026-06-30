<?php
require_once 'db.php';
include 'header.php';

if (!isset($_GET['id'])) {
    echo "Sınav bulunamadı.";
    exit;
}

$exam_id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam || !$exam['is_online']) {
    echo "Bu sınav online çözüme kapalı.";
    exit;
}

// Cevap anahtarı uzunluğu kadar soru oluştur
$question_count = strlen($exam['answer_key']);
?>

<div class="h-[calc(100vh-80px)] flex flex-col md:flex-row overflow-hidden bg-slate-100">
    
    <div class="w-full md:w-2/3 h-[50vh] md:h-full bg-slate-800 flex flex-col">
        <div class="bg-slate-900 text-white p-3 flex justify-between items-center shadow-md z-10">
            <h2 class="font-bold truncate max-w-xs"><?php echo $exam['title']; ?></h2>
            <a href="denemeler.php" class="text-xs bg-red-600 px-3 py-1 rounded hover:bg-red-700 transition">Çıkış</a>
        </div>
        <iframe src="<?php echo $exam['pdf_file']; ?>" class="w-full h-full border-none"></iframe>
    </div>

    <div class="w-full md:w-1/3 h-[50vh] md:h-full bg-white border-l border-slate-300 flex flex-col">
        <div class="p-4 bg-indigo-600 text-white shadow-md">
            <h3 class="font-bold text-lg text-center">Optik Form</h3>
            <p class="text-xs text-center text-indigo-200"><?php echo $question_count; ?> Soru</p>
        </div>

        <div class="flex-grow overflow-y-auto p-4 bg-slate-50">
            <form action="sinav_sonuc_hesapla.php" method="POST" id="examForm">
                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                
                <div class="space-y-2">
                    <?php for ($i = 1; $i <= $question_count; $i++): ?>
                    <div class="flex items-center justify-between bg-white p-2 rounded shadow-sm border border-slate-100 hover:bg-indigo-50/30 transition">
                        <span class="font-bold text-slate-700 w-8 text-center text-sm"><?php echo $i; ?>.</span>
                        
                        <div class="flex gap-1 justify-center flex-grow">
                            <?php foreach (['A','B','C','D','E'] as $opt): ?>
                            <div class="relative w-8 h-8">
                                <input type="radio" name="q<?php echo $i; ?>" value="<?php echo $opt; ?>" id="q<?php echo $i; ?>_<?php echo $opt; ?>" class="peer sr-only">
                                <label for="q<?php echo $i; ?>_<?php echo $opt; ?>" 
                                       onclick="checkDeselect(this)"
                                       class="w-full h-full rounded-full border border-slate-300 flex items-center justify-center text-slate-500 font-bold text-xs cursor-pointer peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 hover:bg-slate-100 transition select-none">
                                    <?php echo $opt; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </form>
        </div>

        <div class="p-4 bg-white border-t border-slate-200">
            <button type="button" onclick="confirmSubmit()" class="w-full bg-green-600 text-white font-bold py-3 rounded-xl hover:bg-green-700 transition shadow-lg">
                Sınavı Bitir ve Gönder ✅
            </button>
        </div>
    </div>
</div>

<script>
    let lastChecked = {};
    function checkDeselect(label) {
        const radio = document.getElementById(label.getAttribute('for'));
        const name = radio.name;
        if (lastChecked[name] === radio) {
            radio.checked = false;
            lastChecked[name] = null;
            setTimeout(() => radio.checked = false, 0);
        } else {
            lastChecked[name] = radio;
        }
    }
    function confirmSubmit() {
        if(confirm('Sınavı bitirmek istediğine emin misin?')) {
            document.getElementById('examForm').submit();
        }
    }
</script>

<?php // Footer include etmiyoruz çünkü ekranı tam kaplamasını istiyoruz ?>
</body></html>