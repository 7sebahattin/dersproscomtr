<?php
// Hataları göster
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'db.php';
include 'header.php';

// 1. GÜVENLİK
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';
$message = "";

// 2. İŞLEMLER (POST)

// --- ÖĞRETMEN: SINAV YÜKLEME ---
if (($user_role == 'teacher' || $user_role == 'admin') && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_exam'])) {
    $title = $_POST['title'];
    $category = $_POST['category'];
    $is_online = isset($_POST['is_online']) ? 1 : 0;
    $answer_key = isset($_POST['answer_key']) ? strtoupper(trim($_POST['answer_key'])) : null;
    
    $visible_to = 'all'; 
    if (!isset($_POST['visible_all']) && isset($_POST['selected_students'])) {
        $visible_to = json_encode($_POST['selected_students']);
    }

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        $upload_dir = 'uploads/exams/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_ext = pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . "_" . preg_replace('/[^a-zA-Z0-9]/', '', $title) . "." . $file_ext;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_path)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO teacher_exams (teacher_id, title, category, file_path, is_online, visible_to, answer_key) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $title, $category, $target_path, $is_online, $visible_to, $answer_key]);
                $message = "<div class='bg-green-100 text-green-700 p-4 rounded-xl mb-6 shadow-sm border border-green-200'>✅ Sınav başarıyla yüklendi!</div>";
            } catch (PDOException $e) {
                $message = "<div class='bg-red-100 text-red-700 p-4 rounded-xl mb-6'>Veritabanı Hatası: " . $e->getMessage() . "</div>";
            }
        } else {
            $message = "<div class='bg-red-100 text-red-700 p-4 rounded-xl mb-6'>❌ Dosya yüklenemedi.</div>";
        }
    } else {
         $message = "<div class='bg-yellow-100 text-yellow-700 p-4 rounded-xl mb-6'>⚠️ Lütfen bir PDF dosyası seçin.</div>";
    }
}

// --- ÖĞRETMEN: SİLME ---
if (($user_role == 'teacher' || $user_role == 'admin') && isset($_POST['delete_exam'])) {
    $del_id = $_POST['exam_id'];
    $stmt = $pdo->prepare("SELECT file_path FROM teacher_exams WHERE id = ?");
    $stmt->execute([$del_id]);
    $file = $stmt->fetch();
    if ($file && file_exists($file['file_path'])) { unlink($file['file_path']); }
    
    $pdo->prepare("DELETE FROM teacher_exams WHERE id = ?")->execute([$del_id]);
    $message = "<div class='bg-yellow-100 text-yellow-700 p-4 rounded-xl mb-6 shadow-sm border border-yellow-200'>🗑️ Sınav silindi.</div>";
}

// --- ÖĞRENCİ: MANUEL SONUÇ GİRİŞİ ---
if ($user_role == 'student' && isset($_POST['save_manual_result'])) {
    $exam_id = $_POST['exam_id'];
    $exam_name = $_POST['exam_name'];
    $category = $_POST['category'];
    
    $details = [];
    $total_net = 0;
    
    if (isset($_POST['lesson_name'])) {
        foreach($_POST['lesson_name'] as $k => $lesson) {
            $d = (float)($_POST['dogru'][$k] ?? 0);
            $y = (float)($_POST['yanlis'][$k] ?? 0);
            $n = $d - ($y * 0.25);
            
            if ($d > 0 || $y > 0) {
                $details[$lesson] = ['d'=>$d, 'y'=>$y, 'n'=>$n];
                $total_net += $n;
            }
        }
    }
    
    $json_details = json_encode($details, JSON_UNESCAPED_UNICODE);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO quiz_results (student_id, exam_id, exam_name, category, total_net, details, date_taken) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $exam_id, $exam_name, $category, $total_net, $json_details]);
        $message = "<div class='bg-green-100 text-green-700 p-4 rounded-xl mb-6 shadow-sm border border-green-200'>✅ Sonucunuz kaydedildi!</div>";
    } catch (Exception $e) {
        $message = "<div class='bg-red-100 text-red-700 p-4 rounded-xl mb-6'>Hata: " . $e->getMessage() . "</div>";
    }
}

// 3. VERİLERİ ÇEKME

// ÖĞRETMEN VERİLERİ
$my_students = [];
$exams = [];
if ($user_role == 'teacher' || $user_role == 'admin') {
    $st_stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name FROM users u JOIN coaching_relationships cr ON u.id = cr.student_id WHERE cr.teacher_id = ?");
    $st_stmt->execute([$user_id]);
    $my_students = $st_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $student_map = [];
    foreach ($my_students as $s) {
        $student_map[$s['id']] = $s['first_name'] . ' ' . $s['last_name'];
    }

    $ex_stmt = $pdo->prepare("SELECT * FROM teacher_exams WHERE teacher_id = ? ORDER BY created_at DESC");
    $ex_stmt->execute([$user_id]);
    $exams = $ex_stmt->fetchAll();
}

// ÖĞRENCİ VERİLERİ
$my_exams = [];
$solved_exams = [];
if ($user_role == 'student') {
    // A. ATANAN SINAVLAR
    $stmt = $pdo->query("SELECT te.*, u.first_name, u.last_name FROM teacher_exams te LEFT JOIN users u ON te.teacher_id = u.id ORDER BY te.created_at DESC");
    $all_exams = $stmt->fetchAll();
    
    foreach($all_exams as $exam) {
        $visible = $exam['visible_to'];
        if ($visible === 'all' || strpos($visible, '"'.$user_id.'"') !== false) {
            $my_exams[] = $exam;
        }
    }

    // B. ÇÖZÜLMÜŞ SINAVLAR (ID'leri al)
    $solved_stmt = $pdo->prepare("SELECT exam_id FROM quiz_results WHERE student_id = ? AND exam_id IS NOT NULL");
    $solved_stmt->execute([$user_id]);
    $solved_exams = $solved_stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<div class="max-w-7xl mx-auto px-4 py-8 min-h-screen">
    
    <?= $message ?>

    <?php if ($user_role == 'teacher' || $user_role == 'admin'): ?>
        
        <?php if(isset($_GET['action']) && $_GET['action'] == 'new'): ?>
            <div class="bg-white rounded-3xl shadow-lg border border-slate-100 p-8 max-w-2xl mx-auto animate-fadeIn">
                <div class="flex items-center gap-2 mb-6">
                    <a href="denemeler.php" class="text-slate-400 hover:text-slate-600 transition flex items-center gap-1 font-bold text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg> Geri
                    </a>
                    <h1 class="text-2xl font-black text-slate-800 ml-2">Yeni Sınav Yükle</h1>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="upload_exam" value="1">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Sınav Başlığı</label>
                        <input type="text" name="title" required class="w-full border border-slate-200 rounded-xl p-3 focus:ring-2 focus:ring-indigo-500 outline-none transition" placeholder="Örn: TYT Deneme 1 - Özdebir">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Sınav Türü</label>
                        <select name="category" class="w-full border border-slate-200 rounded-xl p-3 focus:ring-2 focus:ring-indigo-500 outline-none bg-white transition">
                            <option value="TYT">TYT</option>
                            <option value="AYT">AYT</option>
                            <option value="LGS">LGS</option>
                            <option value="Ara Sınıf">Ara Sınıf</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">PDF Dosyası</label>
                        <input type="file" name="pdf_file" accept=".pdf" required class="w-full border border-slate-200 rounded-xl p-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition cursor-pointer">
                    </div>

                    <div class="bg-yellow-50 p-5 rounded-xl border border-yellow-100 transition-all duration-300 hover:shadow-sm">
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input type="checkbox" name="is_online" id="onlineCheck" onchange="toggleAnswerKey()" class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300">
                            <span class="text-sm font-bold text-yellow-800">Bu sınavı online çözülebilir yap ✍️</span>
                        </label>
                        <div id="answerKeyArea" class="hidden mt-4 pt-4 border-t border-yellow-200 animate-fadeIn">
                            <label class="block text-xs font-bold text-yellow-700 uppercase mb-2">Cevap Anahtarı (Bitişik Yazın)</label>
                            <input type="text" name="answer_key" class="w-full border border-yellow-200 rounded-lg p-3 text-sm focus:ring-2 focus:ring-yellow-500 outline-none uppercase tracking-widest font-mono" placeholder="ABCDEABCDE...">
                            <p class="text-[10px] text-yellow-600 mt-2 flex items-center gap-1">Soru sayısı, girdiğiniz harf sayısına göre otomatik belirlenir.</p>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="flex items-center gap-3 cursor-pointer mb-3 select-none">
                            <input type="checkbox" name="visible_all" id="visibleAllCheck" checked onchange="toggleStudentSelect()" class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300">
                            <span class="text-sm font-bold text-slate-700">Bu sınavı tüm öğrencilerim görsün</span>
                        </label>
                        <div id="studentSelectArea" class="hidden pl-8 animate-fadeIn">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Veya Öğrencileri Seç:</label>
                            <select name="selected_students[]" multiple class="w-full border border-slate-200 rounded-xl p-2 text-sm h-32 bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
                                <?php foreach($my_students as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= $s['first_name'] . ' ' . $s['last_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1">* Birden fazla seçim için Ctrl (Windows) veya Cmd (Mac) tuşuna basılı tutun.</p>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white py-4 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 transform active:scale-95 duration-200">
                        Sınavı Yayınla 🚀
                    </button>
                </form>
            </div>

            <script>
                function toggleStudentSelect() {
                    const checkBox = document.getElementById('visibleAllCheck');
                    const area = document.getElementById('studentSelectArea');
                    checkBox.checked ? area.classList.add('hidden') : area.classList.remove('hidden');
                }
                function toggleAnswerKey() {
                    const checkBox = document.getElementById('onlineCheck');
                    const area = document.getElementById('answerKeyArea');
                    checkBox.checked ? area.classList.remove('hidden') : area.classList.add('hidden');
                }
            </script>
            <style>.animate-fadeIn { animation: fadeIn 0.3s ease-out; } @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }</style>

        <?php else: ?>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-slate-800">Deneme Yönetimi</h1>
                    <p class="text-slate-500 mt-2 text-sm">Öğrencilerinize buradan deneme sınavı yükleyebilir ve yönetebilirsiniz.</p>
                </div>
                <a href="denemeler.php?action=new" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition flex items-center gap-2 transform hover:-translate-y-0.5">
                    <span>📤</span> Yeni Sınav Yükle
                </a>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden overflow-x-auto">
                <table class="w-full text-left min-w-[800px]">
                    <thead class="bg-slate-50 text-slate-500 font-bold text-xs uppercase border-b border-slate-100">
                        <tr>
                            <th class="p-5">Sınav Adı</th>
                            <th class="p-5 text-center">Görünürlük</th>
                            <th class="p-5 text-center">Dosyalar</th>
                            <th class="p-5 text-center">Tarih</th>
                            <th class="p-5 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($exams as $ex): 
                            $visible_badge = "";
                            $assigned_names = ""; 
                            if ($ex['visible_to'] == 'all') {
                                $visible_badge = '<span class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold border border-green-200">🌍 Tüm Öğrenciler</span>';
                            } else {
                                $ids = json_decode($ex['visible_to'], true);
                                $names = [];
                                if (is_array($ids)) { foreach ($ids as $id) { if (isset($student_map[$id])) { $names[] = $student_map[$id]; } } }
                                $count = count($names);
                                $assigned_names = implode(", ", $names); 
                                $visible_badge = '<button onclick="alert(\'Atanan Öğrenciler:\\n'. $assigned_names .'\')" class="inline-flex items-center gap-1 bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold border border-indigo-200 hover:bg-indigo-200 transition" title="Kişileri Gör">👥 '. $count .' Kişi (Gör)</button>';
                            }
                        ?>
                        <tr class="hover:bg-slate-50 transition group">
                            <td class="p-5">
                                <div class="font-bold text-slate-800 text-base"><?= htmlspecialchars($ex['title']) ?></div>
                                <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded font-bold uppercase tracking-wide mt-1 inline-block"><?= $ex['category'] ?></span>
                            </td>
                            <td class="p-5 text-center"><?= $visible_badge ?></td>
                            <td class="p-5 text-center">
                                <div class="flex justify-center items-center gap-2">
                                    <a href="<?= $ex['file_path'] ?>" target="_blank" class="text-xs font-bold text-red-500 bg-red-50 px-3 py-1 rounded border border-red-100 hover:bg-red-100 transition flex items-center gap-1">PDF</a>
                                    <?php if($ex['is_online']): ?>
                                        <span class="text-[10px] text-green-600 font-bold bg-green-50 px-2 py-1 rounded border border-green-100 flex items-center gap-1">Online</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-5 text-center text-sm text-slate-500 font-medium"><?= date('d.m.Y', strtotime($ex['created_at'])) ?></td>
                            <td class="p-5 text-right">
                                <form method="POST" onsubmit="return confirm('Silmek istediğine emin misiniz?')">
                                    <input type="hidden" name="delete_exam" value="1">
                                    <input type="hidden" name="exam_id" value="<?= $ex['id'] ?>">
                                    <button type="submit" class="text-slate-300 hover:text-red-500 transition text-xl p-2 rounded-full hover:bg-red-50">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($exams)): ?>
                            <tr><td colspan="5" class="p-12 text-center text-slate-400">Henüz hiç sınav yüklenmemiş.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php elseif ($user_role == 'student'): ?>
        
        <div class="mb-12">
            <div class="text-center mb-10">
                <h1 class="text-3xl font-black text-slate-800">Denemeler</h1>
                <p class="text-slate-500 mt-2">Öğretmeninin atadığı denemeleri buradan indirebilir ve çözebilirsin.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($my_exams as $ex): 
                    // BU SINAV ÇÖZÜLDÜ MÜ?
                    $is_solved = in_array($ex['id'], $solved_exams);

                    // --- TARİH TÜRKÇELEŞTİRME ---
                    $monthsTR = [
                        'Jan' => 'Oca', 'Feb' => 'Şub', 'Mar' => 'Mart', 'Apr' => 'Nis', 'May' => 'May', 
                        'Jun' => 'Haz', 'Jul' => 'Tem', 'Aug' => 'Ağu', 'Sep' => 'Eyl', 'Oct' => 'Ekim', 
                        'Nov' => 'Kas', 'Dec' => 'Ara'
                    ];
                    $exTimestamp = strtotime($ex['created_at']);
                    $exDateFormatted = date('d', $exTimestamp) . ' ' . $monthsTR[date('M', $exTimestamp)] . ' ' . date('Y', $exTimestamp);
                ?>
                <div class="bg-white rounded-3xl shadow-xl shadow-slate-100 border border-slate-50 p-6 relative group hover:-translate-y-1 transition duration-300 flex flex-col h-full">
                    <div class="absolute top-4 right-4 bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-1 rounded-lg">
                        <?= $exDateFormatted ?>
                    </div>

                    <div class="flex items-start gap-4 mb-6">
                        <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl shadow-inner border border-indigo-100">📝</div>
                        <div>
                            <h3 class="font-black text-xl text-slate-800 leading-tight"><?= htmlspecialchars($ex['title']) ?></h3>
                            <p class="text-xs font-bold text-indigo-500 mt-1 uppercase tracking-wider"><?= htmlspecialchars($ex['category']) ?></p>
                            <p class="text-[10px] text-slate-400 mt-2">Yükleyen: <span class="font-bold text-slate-500"><?= htmlspecialchars($ex['first_name'] . ' ' . $ex['last_name']) ?></span></p>
                        </div>
                    </div>

                    <div class="mt-auto space-y-3">
                        
                        <a href="<?= $ex['file_path'] ?>" target="_blank" class="flex items-center justify-center gap-2 bg-slate-100 text-slate-600 py-3 rounded-xl font-bold text-sm hover:bg-slate-200 transition w-full">
                            <span>⬇️</span> İndir
                        </a>
                        
                        <?php if($is_solved): ?>
                            <div class="w-full bg-green-100 text-green-700 border border-green-200 py-3 rounded-xl font-bold text-sm text-center flex items-center justify-center gap-2 cursor-default">
                                <span>✅</span> Tamamlandı
                            </div>
                        <?php else: ?>
                            <?php if($ex['is_online']): ?>
                                <a href="take_exam.php?exam_id=<?= $ex['id'] ?>" class="flex items-center justify-center gap-2 w-full bg-indigo-600 text-white py-3 rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 transform hover:scale-[1.02]">
                                    <span>✍️</span> Çöz
                                </a>
                            <?php else: ?>
                                <button onclick='openResultModal(<?= json_encode($ex) ?>)' class="flex items-center justify-center gap-2 w-full bg-amber-100 text-amber-700 border border-amber-200 py-3 rounded-xl font-bold text-sm hover:bg-amber-200 transition transform hover:scale-[1.02]">
                                    <span>📊</span> Sonuç Gir
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>
                </div>
                <?php endforeach; ?>

                <?php if(empty($my_exams)): ?>
                    <div class="col-span-3 text-center py-20 bg-white rounded-3xl border border-dashed border-slate-200">
                        <div class="text-4xl mb-4 grayscale opacity-50">📭</div>
                        <h3 class="text-lg font-bold text-slate-700">Henüz Deneme Yok</h3>
                        <p class="text-slate-400 text-sm">Öğretmenin sana bir deneme atadığında burada görünecek.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<div id="manualResultModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4 hidden">
    <div class="bg-white rounded-3xl w-full max-w-lg mx-4 shadow-2xl overflow-hidden relative transform transition-all scale-100">
        <div class="bg-white border-b border-slate-100 p-5 flex justify-between items-center">
            <h3 class="font-bold text-xl text-slate-800"><span id="modalExamName">TYT</span> - Sonuç Gir</h3>
            <button onclick="document.getElementById('manualResultModal').classList.add('hidden')" class="text-slate-400 hover:text-red-500 text-2xl leading-none">&times;</button>
        </div>
        <form method="POST" class="p-0">
            <input type="hidden" name="save_manual_result" value="1">
            <input type="hidden" name="exam_id" id="modalExamId">
            <input type="hidden" name="exam_name" id="modalExamNameInput">
            <input type="hidden" name="category" id="modalCategory">

            <div class="p-5 max-h-[60vh] overflow-y-auto custom-scrollbar">
                <div class="flex justify-between items-center mb-4">
                    <div><p class="text-xs font-bold text-slate-400 uppercase">Tarih</p><div class="font-bold text-slate-700"><?= date('d.m.Y') ?></div></div>
                    <div><p class="text-xs font-bold text-slate-400 uppercase text-right">Tür</p><div class="font-bold text-indigo-600 text-right" id="modalCategoryText">TYT</div></div>
                </div>
                <div class="space-y-2" id="lessonsContainer"></div>
            </div>
            <div class="p-5 border-t border-slate-100 bg-slate-50 flex justify-between items-center">
                <div class="font-bold text-slate-600 text-xs">TOPLAM: <span id="modalTotalNet" class="text-lg text-indigo-700 ml-1">0.00</span></div>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Kaydet ve Gönder 🚀</button>
            </div>
        </form>
    </div>
</div>

<script>
    const examTemplates = {
        'TYT': ['Türkçe', 'Tarih', 'Coğrafya', 'Felsefe', 'Din', 'Matematik', 'Fizik', 'Kimya', 'Biyoloji'],
        'AYT': ['Matematik', 'Fizik', 'Kimya', 'Biyoloji', 'Edebiyat', 'Tarih-1', 'Coğrafya-1', 'Tarih-2', 'Coğrafya-2', 'Felsefe', 'Din'],
        'LGS': ['Türkçe', 'Matematik', 'Fen Bilimleri', 'T.C. İnkılap', 'Din Kültürü', 'İngilizce'],
        'Ara Sınıf': ['Türkçe', 'Matematik', 'Fen Bilimleri', 'Sosyal Bilgiler']
    };

    function openResultModal(exam) {
        document.getElementById('modalExamId').value = exam.id;
        document.getElementById('modalExamNameInput').value = exam.title;
        document.getElementById('modalExamName').innerText = exam.title;
        document.getElementById('modalCategory').value = exam.category;
        document.getElementById('modalCategoryText').innerText = exam.category;

        const container = document.getElementById('lessonsContainer');
        container.innerHTML = `<div class="flex text-[10px] font-bold text-slate-400 uppercase mb-2 px-1"><div class="w-1/3">Ders</div><div class="w-1/4 text-center">D</div><div class="w-1/4 text-center">Y</div><div class="w-1/4 text-center text-indigo-500">NET</div></div>`;
        const lessons = examTemplates[exam.category] || examTemplates['TYT'];

        lessons.forEach((lesson) => {
            container.innerHTML += `<div class="flex items-center gap-2 mb-2 group"><div class="w-1/3 text-xs font-bold text-slate-700 truncate" title="${lesson}"><input type="hidden" name="lesson_name[]" value="${lesson}">${lesson}</div><div class="w-1/4"><input type="number" name="dogru[]" placeholder="D" class="w-full border border-slate-200 rounded-lg p-2 text-center text-xs focus:ring-2 focus:ring-green-500 outline-none" oninput="calcModalNet(this)"></div><div class="w-1/4"><input type="number" name="yanlis[]" placeholder="Y" class="w-full border border-slate-200 rounded-lg p-2 text-center text-xs focus:ring-2 focus:ring-red-500 outline-none" oninput="calcModalNet(this)"></div><div class="w-1/4"><input type="text" name="net[]" readonly class="w-full bg-indigo-50 text-indigo-700 font-bold border border-indigo-100 rounded-lg p-2 text-center text-xs" value="0.00"></div></div>`;
        });
        document.getElementById('manualResultModal').classList.remove('hidden');
    }

    function calcModalNet(input) {
        const row = input.closest('.flex');
        const d = parseFloat(row.querySelector('input[name="dogru[]"]').value) || 0;
        const y = parseFloat(row.querySelector('input[name="yanlis[]"]').value) || 0;
        const net = d - (y * 0.25);
        row.querySelector('input[name="net[]"]').value = net.toFixed(2);
        
        let total = 0;
        document.querySelectorAll('input[name="net[]"]').forEach(n => { total += parseFloat(n.value) || 0; });
        document.getElementById('modalTotalNet').innerText = total.toFixed(2);
    }
</script>

<style>.custom-scrollbar::-webkit-scrollbar { width: 4px; } .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }</style>

<?php include 'footer.php'; ?>