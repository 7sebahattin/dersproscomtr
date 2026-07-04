<?php
// teacher_mufredat.php — EMEKLİYE AYRILDI (Müfredat v2)
// Öğretmen "Eski Müfredat" sayfası yeni sistemle değiştirildi; yeni sayfaya yönlendirir.
// (Aşağıdaki eski kod korunuyor; gerekirse bu satırlar kaldırılarak geri dönülebilir.)
if (session_status() === PHP_SESSION_NONE) session_start();
header("Location: mufredat_v2.php");
exit;

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once '../db.php';

// 1. GÜVENLİK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    if($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser') {
        header("Location: ../index.php"); 
        exit;
    }
}

$user_id = $_SESSION['user_id'];
$message = "";

// --- ŞABLON İNDİRME İŞLEMİ ---
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=konu_yukleme_sablonu.csv');
    
    // Excel'in Türkçe karakterleri tanıması için BOM ekliyoruz
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    // Başlıklar
    fputcsv($output, ['Alan (Kategori)', 'Ders Adı', 'Konu Adı'], ';');
    // Örnek Veriler
    fputcsv($output, ['TYT', 'Matematik', 'Üslü Sayılar'], ';');
    fputcsv($output, ['AYT', 'Fizik', 'Modern Fizik'], ';');
    fputcsv($output, ['LGS', 'Türkçe', 'Fiilimsiler'], ';');
    fclose($output);
    exit;
}

// 2. İŞLEMLER (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- CSV YÜKLEME ---
    if (isset($_POST['upload_csv'])) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $file = $_FILES['csv_file']['tmp_name'];
            
            // Dosya içeriğini oku
            $content = file_get_contents($file);
            
            // Kodlama Kontrolü ve Dönüşümü (TR Karakter Sorunu İçin)
            // Eğer UTF-8 değilse (Excel genelde ANSI/Windows-1254 kaydeder), UTF-8'e çevir.
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-9, WINDOWS-1254');
            }
            
            // Geçici bir dosya oluşturup çevrilmiş içeriği yazalım
            $temp_file = tempnam(sys_get_temp_dir(), 'utf8_csv');
            file_put_contents($temp_file, $content);
            
            $handle = fopen($temp_file, "r");
            
            // Ayırıcı Tespiti
            $firstLine = fgets($handle);
            // BOM varsa temizle
            $firstLine = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $firstLine); 
            $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
            rewind($handle);

            $success_count = 0;
            $row_num = 0;
            
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                $row_num++;
                // BOM temizliği
                if($row_num == 1) $data[0] = preg_replace('/[\xEF\xBB\xBF]/', '', $data[0]);

                if (count($data) < 3) continue;

                $cat_name = trim($data[0]); 
                $sub_name = trim($data[1]); 
                $top_name = trim($data[2]); 

                // Başlık satırını atla
                if (strtolower($cat_name) == 'alan (kategori)' || strtolower($sub_name) == 'ders adı') continue;
                if (empty($sub_name) || empty($top_name)) continue;

                try {
                    // Dersi Bul veya Oluştur
                    $stmt = $pdo->prepare("SELECT id FROM coaching_subjects WHERE name = ? AND category = ? AND (created_by IS NULL OR created_by = ?)");
                    $stmt->execute([$sub_name, $cat_name, $user_id]);
                    $sub_id = $stmt->fetchColumn();

                    if (!$sub_id) {
                        $ins = $pdo->prepare("INSERT INTO coaching_subjects (name, category, created_by) VALUES (?, ?, ?)");
                        $ins->execute([$sub_name, $cat_name, $user_id]);
                        $sub_id = $pdo->lastInsertId();
                    }

                    // Konuyu Ekle
                    $check = $pdo->prepare("SELECT id FROM coaching_topics WHERE subject_id = ? AND name = ? AND created_by = ?");
                    $check->execute([$sub_id, $top_name, $user_id]);
                    
                    if ($check->rowCount() == 0) {
                        $ins_top = $pdo->prepare("INSERT INTO coaching_topics (subject_id, name, created_by) VALUES (?, ?, ?)");
                        $ins_top->execute([$sub_id, $top_name, $user_id]);
                        $success_count++;
                    }

                } catch (PDOException $e) { continue; }
            }
            fclose($handle);
            unlink($temp_file); // Geçici dosyayı sil
            $message = "<div class='bg-green-100 text-green-700 p-4 rounded-lg mb-6 shadow-sm border border-green-200'>✅ $success_count adet yeni konu yüklendi.</div>";
        }
    }
    
    // --- TEKLİ DERS EKLEME ---
    if (isset($_POST['add_subject'])) {
        $subject_name = trim($_POST['subject_name']);
        $subject_cat  = trim($_POST['subject_category'] ?? 'Genel');
        $allowed_cats = ['TYT','AYT','LGS','Ara Sınıf','Genel'];
        if (!in_array($subject_cat, $allowed_cats)) $subject_cat = 'Genel';
        if (!empty($subject_name)) {
            $stmt = $pdo->prepare("INSERT INTO coaching_subjects (name, category, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$subject_name, $subject_cat, $user_id]);
            $message = "<div class='bg-green-100 text-green-700 p-4 rounded-lg mb-6 shadow-sm border border-green-200'>✅ Yeni ders oluşturuldu.</div>";
        }
    }

    // --- KONU EKLEME veya GÜNCELLEME ---
    if (isset($_POST['save_topic'])) {
        $sub_id = $_POST['subject_id'];
        $t_name = $_POST['topic_name'];
        $edit_id = $_POST['edit_topic_id'] ?? ''; // Düzenleme ID'si var mı?

        if($sub_id && $t_name) {
            
            // Yetki Kontrolü: Seçilen ders öğretmenin mi veya global mi?
            $checkSub = $pdo->prepare("SELECT id FROM coaching_subjects WHERE id = ? AND (created_by IS NULL OR created_by = ?)");
            $checkSub->execute([$sub_id, $user_id]);
            
            if($checkSub->fetchColumn()){
                
                if (!empty($edit_id)) {
                    // --- GÜNCELLEME ---
                    // Sadece kendi oluşturduğu konuyu güncelleyebilir
                    $update = $pdo->prepare("UPDATE coaching_topics SET name = ?, subject_id = ? WHERE id = ? AND created_by = ?");
                    $update->execute([$t_name, $sub_id, $edit_id, $user_id]);
                    
                    if ($update->rowCount() > 0) {
                        $message = "<div class='bg-blue-100 text-blue-700 p-4 rounded-lg mb-6 shadow-sm border border-blue-200'>✏️ Konu güncellendi.</div>";
                    } else {
                        $message = "<div class='bg-yellow-100 text-yellow-700 p-4 rounded-lg mb-6 shadow-sm border border-yellow-200'>⚠️ Değişiklik yapılmadı veya yetkiniz yok.</div>";
                    }

                } else {
                    // --- YENİ EKLEME ---
                    $pdo->prepare("INSERT INTO coaching_topics (subject_id, name, created_by) VALUES (?, ?, ?)")->execute([$sub_id, $t_name, $user_id]);
                    $message = "<div class='bg-green-100 text-green-700 p-4 rounded-lg mb-6 shadow-sm border border-green-200'>✅ Konu eklendi.</div>";
                }

            } else {
                $message = "<div class='bg-red-100 text-red-700 p-4 rounded-lg mb-6 shadow-sm border border-red-200'>❌ Yetkisiz işlem.</div>";
            }
        }
    }

    // --- SİLME ---
    if (isset($_POST['delete_topic'])) {
        $del_id = $_POST['topic_id'];
        $stmt = $pdo->prepare("DELETE FROM coaching_topics WHERE id = ? AND created_by = ?");
        $stmt->execute([$del_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $message = "<div class='bg-yellow-100 text-yellow-700 p-4 rounded-lg mb-6 shadow-sm border border-yellow-200'>🗑️ Konu silindi.</div>";
        } else {
            $message = "<div class='bg-red-100 text-red-700 p-4 rounded-lg mb-6 shadow-sm border border-red-200'>❌ Silemezsiniz (Sistem konusu).</div>";
        }
    }
}

// 3. VERİLERİ ÇEKME
$subjects = $pdo->prepare("SELECT * FROM coaching_subjects WHERE created_by IS NULL OR created_by = ? ORDER BY category, name");
$subjects->execute([$user_id]);
$subjects = $subjects->fetchAll();

$selected_subject_id = $_GET['filter_subject'] ?? '';
$topics = [];

$base_sql = "SELECT t.*, s.name as subject_name, s.category 
             FROM coaching_topics t 
             JOIN coaching_subjects s ON t.subject_id = s.id 
             WHERE (t.created_by IS NULL OR t.created_by = ?)";

if ($selected_subject_id) {
    $sql = "$base_sql AND t.subject_id = ? ORDER BY t.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $selected_subject_id]);
    $topics = $stmt->fetchAll();
} else {
    $topics = $pdo->prepare("$base_sql ORDER BY t.id DESC LIMIT 50");
    $topics->execute([$user_id]);
    $topics = $topics->fetchAll();
}
?>

<?php include '../header.php'; ?>
<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
<script>
function editTopic(id, name, subjectId) {
    document.getElementById('topic_name').value   = name;
    document.getElementById('subject_select').value = subjectId;
    document.getElementById('edit_topic_id').value  = id;
    document.getElementById('form_title').innerText = '✏️ Konuyu Düzenle';
    document.getElementById('save_btn_text').innerText = 'Güncelle';
    document.getElementById('save_btn').className = 'w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition shadow-md';
    document.getElementById('cancel_btn').classList.remove('hidden');
    document.getElementById('manual_form_area').scrollIntoView({behavior: 'smooth'});
}
function cancelEdit() {
    document.getElementById('topic_name').value   = '';
    document.getElementById('subject_select').value = '';
    document.getElementById('edit_topic_id').value  = '';
    document.getElementById('form_title').innerText = 'Konu Ekle';
    document.getElementById('save_btn_text').innerText = 'Konuyu Kaydet';
    document.getElementById('save_btn').className = 'w-full bg-[#223488] hover:bg-[#314595] text-white font-bold py-3 rounded-xl transition shadow-md';
    document.getElementById('cancel_btn').classList.add('hidden');
}
</script>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?= $message ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">

                <!-- KART 1: CSV/Excel Toplu Yükleme -->
                <div class="bg-white rounded-2xl shadow-sm border border-indigo-100 overflow-hidden">
                    <div class="bg-indigo-600 px-5 py-4">
                        <h3 class="font-bold text-base text-white flex items-center gap-2">📥 Excel/CSV ile Toplu Yükle</h3>
                        <p class="text-indigo-200 text-xs mt-1">Şablonu indirin, doldurun ve yükleyin.</p>
                    </div>
                    <div class="p-5 space-y-4">
                        <a href="?download_template=1" class="flex items-center gap-2 text-sm text-indigo-600 font-bold hover:text-indigo-800 transition">
                            <span class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center text-base">📋</span>
                            Örnek Şablonu İndir (.csv)
                        </a>
                        <form method="POST" enctype="multipart/form-data" class="space-y-3">
                            <input type="hidden" name="upload_csv" value="1">
                            <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-indigo-200 rounded-xl cursor-pointer bg-indigo-50 hover:bg-indigo-100 transition">
                                <span class="text-2xl">📂</span>
                                <span class="text-xs text-indigo-600 font-bold mt-1">CSV dosyası seçin</span>
                                <input type="file" name="csv_file" accept=".csv" required class="hidden" onchange="this.parentElement.querySelector('span:nth-child(2)').textContent = this.files[0]?.name || 'CSV dosyası seçin'">
                            </label>
                            <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2.5 rounded-xl hover:bg-indigo-700 transition text-sm">
                                Yüklemeyi Başlat
                            </button>
                        </form>
                    </div>
                </div>

                <!-- KART 2: Yeni Ders Ekle -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-700 px-5 py-4">
                        <h3 class="font-bold text-base text-white flex items-center gap-2">📖 Yeni Ders Ekle</h3>
                        <p class="text-slate-300 text-xs mt-1">Özel bir ders/alan oluşturun.</p>
                    </div>
                    <div class="p-5">
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="add_subject" value="1">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Alan / Kategori</label>
                                <select name="subject_category" class="w-full border border-slate-300 rounded-xl p-2.5 bg-white text-sm focus:ring-2 focus:ring-slate-400 focus:outline-none" required>
                                    <option value="TYT">TYT</option>
                                    <option value="AYT">AYT</option>
                                    <option value="LGS">LGS</option>
                                    <option value="Ara Sınıf">Ara Sınıf</option>
                                    <option value="Genel" selected>Genel</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Ders Adı</label>
                                <input type="text" name="subject_name" placeholder="Örn: İleri Geometri" class="js-upper w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-slate-400 focus:outline-none" required>
                            </div>
                            <button type="submit" class="w-full bg-slate-700 text-white font-bold py-2.5 rounded-xl hover:bg-slate-800 transition text-sm">
                                Dersi Kaydet
                            </button>
                        </form>
                    </div>
                </div>

                <!-- KART 3: Konu Ekle / Düzenle -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" id="manual_form_area">
                    <div class="bg-green-600 px-5 py-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="font-bold text-base text-white flex items-center gap-2" id="form_title">📝 Konu Ekle</h3>
                                <p class="text-green-100 text-xs mt-1">Mevcut bir derse konu ekleyin.</p>
                            </div>
                            <button type="button" id="cancel_btn" onclick="cancelEdit()" class="text-xs bg-white/20 hover:bg-white/30 text-white font-bold px-3 py-1.5 rounded-lg transition hidden">İptal</button>
                        </div>
                    </div>
                    <div class="p-5">
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="save_topic" value="1">
                            <input type="hidden" name="edit_topic_id" id="edit_topic_id" value="">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Ders Seçin</label>
                                <select name="subject_id" id="subject_select" class="w-full border border-slate-300 rounded-xl p-2.5 bg-white text-sm focus:ring-2 focus:ring-green-300 focus:outline-none" required>
                                    <option value="">— Ders Seçiniz —</option>
                                    <?php
                                    $grouped = [];
                                    foreach ($subjects as $s) {
                                        $grouped[$s['category']][] = $s;
                                    }
                                    foreach ($grouped as $cat => $items): ?>
                                        <optgroup label="── <?= htmlspecialchars($cat) ?> ──">
                                            <?php foreach ($items as $s): ?>
                                                <option value="<?= $s['id'] ?>">
                                                    <?= htmlspecialchars($s['name']) ?>
                                                    <?= ($s['created_by'] == $user_id) ? ' ★' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Konu Adı</label>
                                <input type="text" name="topic_name" id="topic_name" placeholder="Örn: Üslü Sayılar" class="js-upper w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-green-300 focus:outline-none" required>
                            </div>
                            <button type="submit" id="save_btn" class="w-full bg-green-600 text-white font-bold py-2.5 rounded-xl hover:bg-green-700 transition text-sm">
                                <span id="save_btn_text">Konuyu Kaydet</span>
                            </button>
                        </form>
                    </div>
                </div>

        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-col" style="min-height:500px">
                    
                    <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center rounded-t-2xl">
                        <div>
                            <h3 class="font-bold text-lg text-slate-800">📋 Konu Listesi</h3>
                            <p class="text-sm text-slate-500">Toplam <?= count($topics) ?> konu</p>
                        </div>
                        <form method="GET" class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-400 uppercase hidden sm:inline">Filtrele:</span>
                            <select name="filter_subject" class="text-sm border border-slate-300 rounded-lg p-2 bg-white focus:outline-none" onchange="this.form.submit()">
                                <option value="">Tüm Dersler</option>
                                <?php foreach($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $selected_subject_id == $s['id'] ? 'selected' : '' ?>>
                                        [<?= htmlspecialchars($s['category']) ?>] <?= htmlspecialchars($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <div class="flex-grow overflow-y-auto p-0 custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-white sticky top-0 z-10 text-xs font-bold text-slate-500 uppercase tracking-wider shadow-sm">
                                <tr>
                                    <th class="p-4 border-b border-slate-100">Alan</th>
                                    <th class="p-4 border-b border-slate-100">Ders</th>
                                    <th class="p-4 border-b border-slate-100">Konu</th>
                                    <th class="p-4 border-b border-slate-100 text-right">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-slate-600 divide-y divide-slate-100">
                                <?php if(empty($topics)): ?>
                                    <tr>
                                        <td colspan="4" class="p-8 text-center text-slate-400 italic">
                                            Kayıt bulunamadı.
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php foreach ($topics as $t): 
                                    $is_mine = ($t['created_by'] == $user_id);
                                    $row_class = $is_mine ? "bg-white hover:bg-indigo-50" : "bg-slate-50/50 text-slate-500 hover:bg-slate-100";
                                ?>
                                <tr class="<?= $row_class ?> transition group">
                                    <td class="p-4 text-xs font-bold text-indigo-600/80"><?= htmlspecialchars($t['category']) ?></td>
                                    <td class="p-4 font-medium"><?= htmlspecialchars($t['subject_name']) ?></td>
                                    <td class="p-4 flex items-center gap-2">
                                        <?= htmlspecialchars($t['name']) ?>
                                        <?php if(!$is_mine): ?>
                                            <span class="text-[10px] bg-slate-200 px-2 py-0.5 rounded-full text-slate-600 font-bold">Sistem</span>
                                        <?php else: ?>
                                            <span class="text-[10px] bg-indigo-100 px-2 py-0.5 rounded-full text-indigo-600 font-bold">Özel</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                        <?php if ($is_mine): ?>
                                            <button onclick="editTopic(<?= $t['id'] ?>, '<?= addslashes($t['name']) ?>', <?= $t['subject_id'] ?>)" 
                                                    class="bg-blue-50 text-blue-500 hover:text-white hover:bg-blue-500 transition w-8 h-8 rounded-lg flex items-center justify-center" 
                                                    title="Düzenle">
                                                ✏️
                                            </button>

                                            <form method="POST" onsubmit="return confirm('Bu konu silinecek. Emin misiniz?');" class="inline">
                                                <input type="hidden" name="delete_topic" value="1">
                                                <input type="hidden" name="topic_id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="bg-red-50 text-red-400 hover:text-white hover:bg-red-500 transition w-8 h-8 rounded-lg flex items-center justify-center" title="Sil">
                                                    🗑️
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-slate-300 cursor-not-allowed text-xs px-2" title="Sistem konusu değiştirilemez">🔒 Sistem</span>
                                        <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
        </div>
    </main>
</div>
<?php include '../footer.php'; ?>