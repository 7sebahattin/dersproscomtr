<?php
// Hataları göster
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once '../db.php'; // Veritabanı bağlantısı

// 1. GÜVENLİK
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: ../index.php");
    exit;
}

// 2. SINIFLARI ÇEK (Dropdown için gerekli)
// Eğer grades tablosu yoksa kod patlamasın diye try-catch kullanıyoruz
$grades = [];
try {
    $grades = $pdo->query("SELECT * FROM grades ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    // Grades tablosu yoksa hata vermesin, boş dizi kalsın
}

// 3. TABLO ONARIMI (Eksik sütunları ekle)
try {
    // Tablo yoksa oluştur (grade_id sütununa dikkat)
    $pdo->exec("CREATE TABLE IF NOT EXISTS materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        type VARCHAR(50) DEFAULT 'other',
        file_path VARCHAR(500) NOT NULL,
        grade_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Sütunlar eksikse ekle (Yama)
    $pdo->exec("ALTER TABLE materials ADD COLUMN type VARCHAR(50) DEFAULT 'other'");
    $pdo->exec("ALTER TABLE materials ADD COLUMN file_path VARCHAR(500) NOT NULL");

    // Eğer veritabanında grade_id zorunluysa ve bizde yoksa ekleyelim
    // NOT: Hata constraint'ten geldiği için grade_id zaten var, biz sadece koddan göndermeliyiz.
} catch (PDOException $e) { /* Hata varsa yoksay (zaten var demektir) */ }

$message = "";

// 4. İŞLEM: DOSYA YÜKLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $title = $_POST['title'];
    $type = $_POST['type'];
    $grade_id = !empty($_POST['grade_id']) ? $_POST['grade_id'] : NULL; // Sınıf seçimi

    // Klasör Kontrolü
    if (!file_exists('../uploads')) { mkdir('../uploads', 0777, true); }

    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        // Güvenlik: uzantı değil GERÇEK dosya içeriği (MIME) doğrulanır; dosya adı
        // tamamen sunucu tarafında rastgele üretilir (yüklenen isim asla kullanılmaz).
        // Bu, uzantı sahteciliğiyle .php/.phtml gibi çalıştırılabilir dosya yüklenmesini engeller.
        $allowedMime = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
        ];
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime  = $finfo ? finfo_file($finfo, $_FILES['file']['tmp_name']) : null;
        if ($finfo) finfo_close($finfo);

        if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Dosya çok büyük (maks. 20MB).</div>";
        } elseif (!$mime || !isset($allowedMime[$mime])) {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Desteklenmeyen dosya türü. İzin verilenler: PDF, Word, Excel, PowerPoint, resim, MP4.</div>";
        } else {
            $file_name = 'mat_' . bin2hex(random_bytes(10)) . '.' . $allowedMime[$mime];
            $target_path = '../uploads/' . $file_name;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
                try {
                    // SQL GÜNCELLENDİ: grade_id eklendi
                    $stmt = $pdo->prepare("INSERT INTO materials (title, type, file_path, grade_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$title, $type, 'uploads/' . $file_name, $grade_id]);
                    $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ Dosya başarıyla yüklendi!</div>";
                } catch (PDOException $e) {
                    // Hata kodunu yakalayıp insani dile çevirelim (ham hata mesajı asla kullanıcıya gösterilmez)
                    if ($e->getCode() == '23000') {
                        $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Hata: Lütfen geçerli bir SINIF (Grade) seçtiğinizden emin olun.</div>";
                    } else {
                        error_log('materyal.php DB error: ' . $e->getMessage());
                        $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Veritabanı hatası oluştu. Lütfen tekrar deneyin.</div>";
                    }
                }
            } else {
                $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>❌ Dosya taşınamadı.</div>";
            }
        }
    } else {
        $message = "<div class='bg-yellow-100 text-yellow-700 p-3 rounded mb-4'>⚠️ Lütfen bir dosya seçin.</div>";
    }
}

// 5. İŞLEM: SİLME
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $stmt = $pdo->prepare("SELECT file_path FROM materials WHERE id = ?");
    $stmt->execute([$del_id]);
    $file = $stmt->fetchColumn();
    if ($file && file_exists('../' . $file)) { unlink('../' . $file); }
    $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
    $stmt->execute([$del_id]);
    header("Location: materyal.php?msg=deleted");
    exit;
}
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>🗑️ Silindi.</div>";

// 6. LİSTELEME (Sınıf adını da çekmek için JOIN kullandım)
try {
    // Eğer grades tablosu varsa JOIN yap, yoksa düz çek
    if(count($grades) > 0) {
        $sql = "SELECT m.*, g.name as grade_name FROM materials m
                LEFT JOIN grades g ON m.grade_id = g.id
                ORDER BY m.created_at DESC";
    } else {
        $sql = "SELECT *, 'Tanımsız' as grade_name FROM materials ORDER BY created_at DESC";
    }
    $materials = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) { $materials = []; }
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Materyal Yönetimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-50 flex h-screen overflow-hidden">

<?php include 'sidebar.php'; ?>

    <main class="flex-grow overflow-y-auto bg-slate-50 p-8">
        <h2 class="text-2xl font-bold text-slate-800 mb-6">Kaynak Yönetimi</h2>
        <?= $message ?>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 mb-8">
            <h3 class="font-bold text-lg mb-4 text-slate-700">Yeni Materyal Ekle</h3>
            <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Başlık</label>
                    <input type="text" name="title" required class="js-upper w-full border border-slate-300 rounded-lg p-2" placeholder="Örn: Fizik PDF">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Sınıf/Seviye</label>
                    <select name="grade_id" required class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                        <option value="">-- Sınıf Seç --</option>
                        <?php foreach($grades as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name'] ?? $g['id'] . '. Sınıf') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tür</label>
                    <select name="type" class="w-full border border-slate-300 rounded-lg p-2 bg-white">
                        <option value="pdf">PDF</option>
                        <option value="image">Resim</option>
                        <option value="video">Video</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Dosya</label>
                    <input type="file" name="file" required class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:bg-indigo-50 file:text-indigo-700">
                </div>

                <div class="md:col-span-4 mt-2">
                    <button type="submit" name="upload" class="bg-indigo-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-indigo-700 w-full">Yükle</button>
                </div>
            </form>

            <?php if(empty($grades)): ?>
                <div class="mt-4 p-3 bg-yellow-50 text-yellow-700 text-sm rounded border border-yellow-200">
                    ⚠️ <b>Uyarı:</b> Veritabanında kayıtlı 'Sınıf' (Grades) bulunamadı. Dosya yükleyebilmek için önce veritabanına sınıf eklemelisiniz veya 'mufredat.php' üzerinden sınıf oluşturmalısınız.
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-slate-500 text-sm uppercase">
                    <tr>
                        <th class="px-6 py-3">Tür</th>
                        <th class="px-6 py-3">Başlık</th>
                        <th class="px-6 py-3">Sınıf</th>
                        <th class="px-6 py-3">Tarih</th>
                        <th class="px-6 py-3 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($materials as $m): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4"><span class="bg-slate-100 px-2 py-1 rounded text-xs font-bold"><?= strtoupper($m['type']) ?></span></td>
                        <td class="px-6 py-4 font-medium"><a href="/<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="hover:text-indigo-600 underline"><?= htmlspecialchars($m['title']) ?></a></td>
                        <td class="px-6 py-4 text-sm text-slate-600"><?= htmlspecialchars($m['grade_name'] ?? '-') ?></td>
                        <td class="px-6 py-4 text-sm text-slate-500"><?= date("d.m.Y", strtotime($m['created_at'])) ?></td>
                        <td class="px-6 py-4 text-right">
                            <a href="materyal.php?delete_id=<?= $m['id'] ?>" onclick="return confirm('Silinsin mi?')" class="text-red-600 hover:text-red-800 font-bold text-sm">Sil</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
