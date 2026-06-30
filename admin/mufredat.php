<?php
// mufredat.php (moved from admin_mufredat.php)
// Gelişmiş Filtreleme, Düzenleme, Görünürlük Yönetimi ve Şablon İndirme

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../db.php';

// 1. GÜVENLİK
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: ../index.php");
    exit;
}

// 2. CSV ŞABLON İNDİRME
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=admin_konu_yukleme_sablonu.csv');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Alan (Kategori)', 'Ders Adı', 'Konu Adı'], ';');
    fputcsv($output, ['TYT', 'Matematik', 'Üslü Sayılar'], ';');
    fputcsv($output, ['AYT', 'Fizik', 'Atışlar'], ';');
    fclose($output);
    exit;
}

$message = "";

// 3. İŞLEMLER (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- CSV YÜKLEME ---
    if (isset($_POST['upload_csv'])) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $file = $_FILES['csv_file']['tmp_name'];
            $content = file_get_contents($file);
            // Karakter seti düzeltme
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-9, WINDOWS-1254');
            }
            $temp = tempnam(sys_get_temp_dir(), 'csv');
            file_put_contents($temp, $content);
            $handle = fopen($temp, "r");

            $firstLine = fgets($handle);
            $firstLine = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $firstLine);
            $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
            rewind($handle);

            $success_count = 0;
            $row = 0;
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                $row++;
                if($row==1) $data[0] = preg_replace('/[\xEF\xBB\xBF]/', '', $data[0]);
                if (count($data) < 3) continue;

                $cat = trim($data[0]);
                $sub = trim($data[1]);
                $top = trim($data[2]);

                if (strtolower($cat) == 'alan' || strtolower($sub) == 'ders' || empty($sub) || empty($top)) continue;

                try {
                    // Dersi Bul/Ekle
                    $stmt = $pdo->prepare("SELECT id FROM coaching_subjects WHERE name = ? AND category = ?");
                    $stmt->execute([$sub, $cat]);
                    $sub_id = $stmt->fetchColumn();

                    if (!$sub_id) {
                        $ins = $pdo->prepare("INSERT INTO coaching_subjects (name, category) VALUES (?, ?)");
                        $ins->execute([$sub, $cat]);
                        $sub_id = $pdo->lastInsertId();
                    }

                    // Konuyu Ekle
                    $check = $pdo->prepare("SELECT id FROM coaching_topics WHERE subject_id = ? AND name = ?");
                    $check->execute([$sub_id, $top]);

                    if ($check->rowCount() == 0) {
                        // Admin yüklediği için created_by NULL, is_public 1 (Otomatik herkese açık)
                        $pdo->prepare("INSERT INTO coaching_topics (subject_id, name, created_by, is_public) VALUES (?, ?, NULL, 1)")
                            ->execute([$sub_id, $top]);
                        $success_count++;
                    }
                } catch (PDOException $e) { continue; }
            }
            fclose($handle);
            unlink($temp);
            $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ $success_count konu başarıyla yüklendi.</div>";
        }
    }

    // --- TEKLİ DERS EKLEME ---
    if (isset($_POST['add_subject'])) {
        $name = trim($_POST['subject_name']);
        $allowed_cats = ['TYT','AYT','LGS','Ara Sınıf','Genel'];
        $cat = in_array($_POST['subject_category'] ?? '', $allowed_cats) ? $_POST['subject_category'] : 'Genel';
        if (!empty($name)) {
            $pdo->prepare("INSERT INTO coaching_subjects (name, category) VALUES (?, ?)")->execute([$name, $cat]);
            $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ Ders eklendi.</div>";
        }
    }

    // --- KONU KAYDETME (EKLEME / GÜNCELLEME) ---
    if (isset($_POST['save_topic'])) {
        $sub_id = $_POST['subject_id'];
        $t_name = $_POST['topic_name'];
        $edit_id = $_POST['topic_id'] ?? '';

        // Admin eklediği için varsayılan olarak created_by NULL ve is_public 1 olsun
        if($sub_id && $t_name) {
            if(!empty($edit_id)) {
                // Güncelleme
                $pdo->prepare("UPDATE coaching_topics SET subject_id=?, name=? WHERE id=?")
                    ->execute([$sub_id, $t_name, $edit_id]);
                $message = "<div class='bg-blue-100 text-blue-700 p-3 rounded mb-4'>✏️ Konu güncellendi.</div>";
            } else {
                // Yeni Ekleme
                $pdo->prepare("INSERT INTO coaching_topics (subject_id, name, created_by, is_public) VALUES (?, ?, NULL, 1)")
                    ->execute([$sub_id, $t_name]);
                $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ Konu eklendi.</div>";
            }
        }
    }

    // --- GÖRÜNÜRLÜK DEĞİŞTİRME (TOGGLE) ---
    if (isset($_POST['toggle_public'])) {
        $tid = $_POST['topic_id'];
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 1) ? 0 : 1;

        $pdo->prepare("UPDATE coaching_topics SET is_public = ? WHERE id = ?")->execute([$new_status, $tid]);
        $status_text = $new_status ? "Herkese Açık" : "Özel (Sadece Öğretmen)";
        $color = $new_status ? "green" : "orange";
        $message = "<div class='bg-{$color}-100 text-{$color}-700 p-3 rounded mb-4'>👁️ Konu durumu: <strong>$status_text</strong> olarak değiştirildi.</div>";
    }

    // --- SİLME ---
    if (isset($_POST['delete_topic'])) {
        $pdo->prepare("DELETE FROM coaching_topics WHERE id = ?")->execute([$_POST['topic_id']]);
        $message = "<div class='bg-yellow-100 text-yellow-700 p-3 rounded mb-4'>🗑️ Konu silindi.</div>";
    }

    // --- TÜMÜNÜ SİL ---
    if (isset($_POST['delete_all'])) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE coaching_topics");
        $pdo->exec("TRUNCATE TABLE coaching_subjects");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>🧹 Tüm veritabanı temizlendi.</div>";
    }
}

// 4. FİLTRELEME VE VERİ ÇEKME
$filter_cat = $_GET['filter_category'] ?? '';
$filter_sub = $_GET['filter_subject'] ?? '';
$filter_creator = $_GET['filter_creator'] ?? '';
$filter_search = $_GET['search'] ?? '';

// Temel Sorgu
$sql = "SELECT t.*, s.name as subject_name, s.category, u.first_name, u.last_name
        FROM coaching_topics t
        JOIN coaching_subjects s ON t.subject_id = s.id
        LEFT JOIN users u ON t.created_by = u.id
        WHERE 1=1";

$params = [];

if ($filter_cat) {
    $sql .= " AND s.category = ?";
    $params[] = $filter_cat;
}
if ($filter_sub) {
    $sql .= " AND t.subject_id = ?";
    $params[] = $filter_sub;
}
if ($filter_creator) {
    if ($filter_creator == 'system') {
        $sql .= " AND t.created_by IS NULL";
    } else {
        $sql .= " AND t.created_by = ?";
        $params[] = $filter_creator;
    }
}
if ($filter_search) {
    $sql .= " AND t.name LIKE ?";
    $params[] = "%$filter_search%";
}

$sql .= " ORDER BY t.id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$topics = $stmt->fetchAll();

// Filtre seçenekleri için veriler
$categories = $pdo->query("SELECT DISTINCT category FROM coaching_subjects ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$subjects = $pdo->query("SELECT * FROM coaching_subjects ORDER BY category, name")->fetchAll();
$creators = $pdo->query("SELECT DISTINCT u.id, u.first_name, u.last_name FROM coaching_topics t JOIN users u ON t.created_by = u.id")->fetchAll();

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Gelişmiş Müfredat Yönetimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
    <script>
        function editTopic(id, name, subId) {
            document.getElementById('formTitle').innerText = '✏️ Konu Düzenle #' + id;
            document.getElementById('topic_id').value = id;
            document.getElementById('topic_name').value = name;
            document.getElementById('subject_id').value = subId;
            document.getElementById('saveBtn').innerText = 'Güncelle';
            document.getElementById('saveBtn').className = "w-full bg-blue-600 text-white font-bold py-2.5 rounded-xl hover:bg-blue-700 transition text-sm";
            document.getElementById('cancelBtn').classList.remove('hidden');
            document.getElementById('manualForm').scrollIntoView({behavior: 'smooth'});
        }
        function cancelEdit() {
            document.getElementById('formTitle').innerText = '📝 Konu Ekle';
            document.getElementById('topic_id').value = '';
            document.getElementById('topic_name').value = '';
            document.getElementById('subject_id').value = '';
            document.getElementById('saveBtn').innerText = 'Konuyu Kaydet';
            document.getElementById('saveBtn').className = "w-full bg-indigo-600 text-white font-bold py-2.5 rounded-xl hover:bg-indigo-700 transition text-sm";
            document.getElementById('cancelBtn').classList.add('hidden');
        }
    </script>
</head>
<body class="bg-slate-50 flex h-screen overflow-hidden">

    <?php include 'sidebar.php'; ?>

    <main class="flex-grow overflow-y-auto bg-slate-50 p-8">
        <h2 class="text-2xl font-bold text-slate-800 mb-6">Müfredat Yönetimi</h2>

        <?= $message ?>

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-5 mb-8">

            <!-- KART 1: CSV Toplu Yükleme -->
            <div class="bg-white rounded-2xl shadow-sm border border-green-100 overflow-hidden">
                <div class="bg-green-600 px-5 py-4">
                    <h3 class="font-bold text-base text-white">📥 Toplu Yükle</h3>
                    <p class="text-green-100 text-xs mt-1">Excel/CSV ile kitlesel yükleme</p>
                </div>
                <div class="p-5 space-y-4">
                    <a href="?download_template=1" class="flex items-center gap-2 text-sm text-green-600 font-bold hover:text-green-800 transition">
                        <span class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center text-base">📋</span>
                        Örnek Şablonu İndir
                    </a>
                    <form method="POST" enctype="multipart/form-data" class="space-y-3">
                        <input type="hidden" name="upload_csv" value="1">
                        <label class="flex flex-col items-center justify-center w-full h-20 border-2 border-dashed border-green-200 rounded-xl cursor-pointer bg-green-50 hover:bg-green-100 transition">
                            <span class="text-xl">📂</span>
                            <span class="text-xs text-green-600 font-bold mt-1" id="csv_label">CSV dosyası seçin</span>
                            <input type="file" name="csv_file" accept=".csv" required class="hidden" onchange="document.getElementById('csv_label').textContent = this.files[0]?.name || 'CSV dosyası seçin'">
                        </label>
                        <button type="submit" class="w-full bg-green-600 text-white font-bold py-2.5 rounded-xl hover:bg-green-700 transition text-sm">Yüklemeyi Başlat</button>
                    </form>
                </div>
            </div>

            <!-- KART 2: Yeni Ders Ekle -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-700 px-5 py-4">
                    <h3 class="font-bold text-base text-white">📖 Ders Ekle</h3>
                    <p class="text-slate-300 text-xs mt-1">Sisteme yeni ders/alan ekleyin</p>
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
                            <input type="text" name="subject_name" placeholder="Örn: Matematik" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-slate-400 focus:outline-none" required>
                        </div>
                        <button type="submit" class="w-full bg-slate-700 text-white font-bold py-2.5 rounded-xl hover:bg-slate-800 transition text-sm">Dersi Kaydet</button>
                    </form>
                </div>
            </div>

            <!-- KART 3: Konu Ekle / Düzenle -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" id="manualForm">
                <div class="bg-indigo-600 px-5 py-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-base text-white" id="formTitle">📝 Konu Ekle</h3>
                            <p class="text-indigo-200 text-xs mt-1">Derse konu ekleyin veya düzenleyin</p>
                        </div>
                        <button type="button" id="cancelBtn" onclick="cancelEdit()" class="hidden text-xs bg-white/20 hover:bg-white/30 text-white font-bold px-3 py-1.5 rounded-lg transition">İptal</button>
                    </div>
                </div>
                <div class="p-5">
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="save_topic" value="1">
                        <input type="hidden" name="topic_id" id="topic_id">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Ders Seçin</label>
                            <select name="subject_id" id="subject_id" class="w-full border border-slate-300 rounded-xl p-2.5 bg-white text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none" required>
                                <option value="">— Ders Seçiniz —</option>
                                <?php
                                $grouped_admin = [];
                                foreach ($subjects as $s) { $grouped_admin[$s['category']][] = $s; }
                                foreach ($grouped_admin as $cat => $items): ?>
                                    <optgroup label="── <?= htmlspecialchars($cat) ?> ──">
                                        <?php foreach ($items as $s): ?>
                                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Konu Adı</label>
                            <input type="text" name="topic_name" id="topic_name" placeholder="Örn: Üslü Sayılar" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none" required>
                        </div>
                        <button type="submit" id="saveBtn" class="w-full bg-indigo-600 text-white font-bold py-2.5 rounded-xl hover:bg-indigo-700 transition text-sm">Konuyu Kaydet</button>
                    </form>
                </div>
            </div>

            <!-- KART 4: Tehlikeli Bölge -->
            <div class="bg-white rounded-2xl shadow-sm border border-red-100 overflow-hidden">
                <div class="bg-red-600 px-5 py-4">
                    <h3 class="font-bold text-base text-white">⚠️ Tehlikeli Bölge</h3>
                    <p class="text-red-100 text-xs mt-1">Geri alınamaz işlemler</p>
                </div>
                <div class="p-5">
                    <p class="text-xs text-slate-500 mb-4">Tüm konu ve ders kayıtlarını kalıcı olarak siler. Bu işlem geri alınamaz.</p>
                    <form method="POST" onsubmit="return confirm('TÜM VERİLER SİLİNECEK! Bu işlem geri alınamaz. Emin misiniz?');">
                        <input type="hidden" name="delete_all" value="1">
                        <button class="w-full bg-red-50 border border-red-200 text-red-600 text-sm font-bold py-2.5 rounded-xl hover:bg-red-600 hover:text-white transition">🗑️ Tümünü Sıfırla</button>
                    </form>
                </div>
            </div>

        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-col h-[600px]">

            <div class="p-4 border-b border-slate-100 bg-slate-50 rounded-t-2xl">
                <form method="GET" class="flex flex-wrap gap-2 items-end">

                    <div class="flex-1 min-w-[120px]">
                        <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Alan</label>
                        <select name="filter_category" class="w-full text-xs border border-slate-200 rounded-lg p-2 bg-white" onchange="this.form.submit()">
                            <option value="">Tümü</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat ?>" <?= $filter_cat == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex-1 min-w-[150px]">
                        <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Ders</label>
                        <select name="filter_subject" class="w-full text-xs border border-slate-200 rounded-lg p-2 bg-white" onchange="this.form.submit()">
                            <option value="">Tümü</option>
                            <?php foreach($subjects as $sub): ?>
                                <option value="<?= $sub['id'] ?>" <?= $filter_sub == $sub['id'] ? 'selected' : '' ?>><?= $sub['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex-1 min-w-[150px]">
                        <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Ekleyen</label>
                        <select name="filter_creator" class="w-full text-xs border border-slate-200 rounded-lg p-2 bg-white" onchange="this.form.submit()">
                            <option value="">Herkes</option>
                            <option value="system" <?= $filter_creator == 'system' ? 'selected' : '' ?>>Sistem (Admin)</option>
                            <?php foreach($creators as $usr): ?>
                                <option value="<?= $usr['id'] ?>" <?= $filter_creator == $usr['id'] ? 'selected' : '' ?>>
                                    <?= $usr['first_name'] . ' ' . $usr['last_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex-[2] min-w-[200px]">
                        <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Konu Ara</label>
                        <div class="flex gap-2">
                            <input type="text" name="search" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Konu adı..." class="w-full text-xs border border-slate-200 rounded-lg p-2">
                            <button type="submit" class="bg-slate-800 text-white px-3 py-2 rounded-lg text-xs font-bold">Ara</button>
                            <?php if($filter_cat || $filter_sub || $filter_creator || $filter_search): ?>
                                <a href="mufredat.php" class="bg-red-100 text-red-600 px-3 py-2 rounded-lg text-xs font-bold flex items-center">✕</a>
                            <?php endif; ?>
                        </div>
                    </div>

                </form>
            </div>

            <div class="flex-grow overflow-y-auto p-0 custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-white sticky top-0 z-10 text-xs font-bold text-slate-400 uppercase shadow-sm">
                        <tr>
                            <th class="p-3 border-b">Alan</th>
                            <th class="p-3 border-b">Ders</th>
                            <th class="p-3 border-b">Konu</th>
                            <th class="p-3 border-b">Ekleyen / Durum</th>
                            <th class="p-3 border-b text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-slate-600 divide-y divide-slate-50">
                        <?php foreach ($topics as $t):
                            $is_system = is_null($t['created_by']);
                            $is_public = $t['is_public'];
                        ?>
                        <tr class="hover:bg-slate-50 transition group">
                            <td class="p-3 text-xs font-bold text-indigo-600"><?= htmlspecialchars($t['category']) ?></td>
                            <td class="p-3 font-medium text-slate-800"><?= htmlspecialchars($t['subject_name']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($t['name']) ?></td>

                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <?php if($is_system): ?>
                                        <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-1 rounded font-bold border border-slate-200">SİSTEM</span>
                                    <?php else: ?>
                                        <span class="text-[10px] bg-indigo-50 text-indigo-600 px-2 py-1 rounded font-bold border border-indigo-100">
                                            <?= htmlspecialchars($t['first_name']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <form method="POST" class="inline">
                                        <input type="hidden" name="toggle_public" value="1">
                                        <input type="hidden" name="topic_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $is_public ?>">

                                        <button type="submit" class="flex items-center gap-1 text-[10px] px-2 py-1 rounded font-bold transition
                                            <?= $is_public ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-orange-100 text-orange-700 hover:bg-orange-200' ?>"
                                            title="<?= $is_public ? 'Kapat (Sadece Öğretmene)' : 'Aç (Herkese Görünür)' ?>">
                                            <?= $is_public ? '👁️ HERKESE AÇIK' : '🔒 SADECE ÖZEL' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>

                            <td class="p-3 text-right">
                                <div class="flex justify-end gap-1">
                                    <button onclick="editTopic(<?= $t['id'] ?>, '<?= addslashes($t['name']) ?>', <?= $t['subject_id'] ?>)"
                                            class="p-1.5 text-blue-500 hover:bg-blue-50 rounded transition" title="Düzenle">
                                        ✏️
                                    </button>

                                    <form method="POST" onsubmit="return confirm('Silinsin mi?');">
                                        <input type="hidden" name="delete_topic" value="1">
                                        <input type="hidden" name="topic_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="p-1.5 text-red-400 hover:bg-red-50 rounded transition" title="Sil">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($topics)): ?>
                            <tr><td colspan="5" class="p-4 text-center text-slate-400 italic">Kriterlere uygun konu bulunamadı.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</body>
</html>
