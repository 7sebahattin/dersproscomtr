<?php
// Hataları göster
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once '../db.php';

// 1. GÜVENLİK
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: ../index.php");
    exit;
}

// 2. TABLO KONTROLÜ
$pdo->exec("CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    discount_price DECIMAL(10, 2) DEFAULT NULL,
    features TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$message = "";

// 3. DÜZENLEME MODU KONTROLÜ (Verileri Çek)
$edit_mode = false;
$edit_data = [];

if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();

    if ($edit_data) {
        $edit_mode = true;
    }
}

// 4. İŞLEM: PAKET EKLEME VEYA GÜNCELLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Değişkenleri al
    $title = $_POST['title'];
    $price = $_POST['price'];
    $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : NULL;
    $features = $_POST['features'];
    $description = $_POST['description'];

    // GÜNCELLEME İŞLEMİ (UPDATE)
    if (isset($_POST['update_package'])) {
        $id = $_POST['package_id'];
        try {
            $sql = "UPDATE packages SET title=?, price=?, discount_price=?, features=?, description=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $price, $discount_price, $features, $description, $id]);

            // Başarılı olursa edit modundan çıkmak için sayfayı temizle
            header("Location: paketler.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Güncelleme Hatası: " . $e->getMessage() . "</div>";
        }
    }

    // YENİ EKLEME İŞLEMİ (INSERT)
    elseif (isset($_POST['add_package'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO packages (title, price, discount_price, features, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $price, $discount_price, $features, $description]);
            $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ Paket başarıyla oluşturuldu!</div>";
        } catch (PDOException $e) {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Hata: " . $e->getMessage() . "</div>";
        }
    }
}

// 5. SİLME İŞLEMİ
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM packages WHERE id = ?");
    $stmt->execute([$del_id]);
    header("Location: paketler.php?msg=deleted");
    exit;
}

// MESAJLAR
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') $message = "<div class='bg-yellow-100 text-yellow-700 p-3 rounded mb-4'>🗑️ Paket silindi.</div>";
    if ($_GET['msg'] == 'updated') $message = "<div class='bg-blue-100 text-blue-700 p-3 rounded mb-4'>🔄 Paket başarıyla güncellendi!</div>";
}

// 6. LİSTELEME
$packages = $pdo->query("SELECT * FROM packages ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Paket Yönetimi | NurgülPanel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-50 flex h-screen overflow-hidden">

<?php include 'sidebar.php'; ?>

    <main class="flex-grow overflow-y-auto bg-slate-50 p-8">
        <h2 class="text-2xl font-bold text-slate-800 mb-6">Paket Yönetimi</h2>

        <?= $message ?>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 mb-8 transition-all" id="formArea">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-lg text-slate-700">
                    <?= $edit_mode ? '✏️ Paketi Düzenle' : '➕ Yeni Paket Oluştur' ?>
                </h3>
                <?php if($edit_mode): ?>
                    <a href="paketler.php" class="text-sm text-red-500 hover:underline">Düzenlemeyi İptal Et</a>
                <?php endif; ?>
            </div>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <?php if($edit_mode): ?>
                    <input type="hidden" name="package_id" value="<?= $edit_data['id'] ?>">
                <?php endif; ?>

                <div class="col-span-2 md:col-span-1">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Paket Adı</label>
                    <input type="text" name="title" required
                           value="<?= $edit_mode ? htmlspecialchars($edit_data['title']) : '' ?>"
                           class="w-full border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                           placeholder="Örn: Yıllık VIP Üyelik">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Fiyat (TL)</label>
                        <input type="number" step="0.01" name="price" required
                               value="<?= $edit_mode ? $edit_data['price'] : '' ?>"
                               class="w-full border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                               placeholder="1000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">İndirimli Fiyat</label>
                        <input type="number" step="0.01" name="discount_price"
                               value="<?= $edit_mode ? $edit_data['discount_price'] : '' ?>"
                               class="w-full border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                               placeholder="850">
                    </div>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Açıklama</label>
                    <input type="text" name="description"
                           value="<?= $edit_mode ? htmlspecialchars($edit_data['description']) : '' ?>"
                           class="w-full border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                           placeholder="Kısa bir açıklama yazın...">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Özellikler (Her satıra bir özellik)</label>
                    <textarea name="features" rows="4" class="w-full border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                              placeholder="Örn:&#10;Canlı Ders Erişimi&#10;PDF Kaynaklar&#10;7/24 Soru Çözümü"><?= $edit_mode ? htmlspecialchars($edit_data['features']) : '' ?></textarea>
                </div>

                <div class="col-span-2 mt-2 flex gap-3">
                    <?php if($edit_mode): ?>
                        <button type="submit" name="update_package" class="bg-orange-500 text-white font-bold py-2 px-6 rounded-lg hover:bg-orange-600 w-full md:w-auto flex items-center justify-center gap-2">
                            🔄 Güncelle
                        </button>
                        <a href="paketler.php" class="bg-slate-200 text-slate-700 font-bold py-2 px-6 rounded-lg hover:bg-slate-300 w-full md:w-auto text-center">
                            İptal
                        </a>
                    <?php else: ?>
                        <button type="submit" name="add_package" class="bg-indigo-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-indigo-700 w-full md:w-auto flex items-center justify-center gap-2">
                            💾 Paketi Kaydet
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h3 class="font-bold text-lg mb-4 text-slate-700">Mevcut Paketler</h3>

        <?php if(count($packages) == 0): ?>
            <div class="p-8 text-center bg-white rounded-xl text-slate-400 border border-dashed border-slate-300">
                Henüz hiç paket oluşturulmamış.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($packages as $p): ?>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col hover:shadow-md transition relative group">

                    <div class="p-6 flex-grow">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($p['title']) ?></h4>
                            <a href="paketler.php?edit_id=<?= $p['id'] ?>" class="text-orange-500 hover:text-orange-700 bg-orange-50 p-2 rounded-lg transition" title="Düzenle">
                                ✏️
                            </a>
                        </div>

                        <p class="text-sm text-slate-500 mb-4"><?= htmlspecialchars($p['description']) ?></p>

                        <div class="flex items-baseline gap-2 mb-4">
                            <?php if($p['discount_price'] > 0): ?>
                                <span class="text-3xl font-bold text-indigo-600">₺<?= number_format($p['discount_price'], 0) ?></span>
                                <span class="text-sm text-slate-400 line-through">₺<?= number_format($p['price'], 0) ?></span>
                            <?php else: ?>
                                <span class="text-3xl font-bold text-indigo-600">₺<?= number_format($p['price'], 0) ?></span>
                            <?php endif; ?>
                        </div>

                        <ul class="space-y-2 mb-4">
                            <?php
                            $feats = explode("\n", $p['features']);
                            foreach($feats as $f):
                                if(trim($f) == "") continue;
                            ?>
                            <li class="flex items-start text-sm text-slate-600">
                                <span class="text-green-500 mr-2">✓</span> <?= htmlspecialchars($f) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="bg-slate-50 p-4 border-t border-slate-100 flex justify-between items-center">
                        <span class="text-xs text-slate-400">ID: <?= $p['id'] ?></span>

                        <div class="flex gap-2">
                            <a href="paketler.php?edit_id=<?= $p['id'] ?>" class="text-orange-600 hover:text-orange-800 font-bold text-sm bg-orange-100 px-3 py-1 rounded border border-orange-200">
                                Düzenle
                            </a>
                            <a href="paketler.php?delete_id=<?= $p['id'] ?>" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-600 hover:text-red-800 font-bold text-sm bg-red-100 px-3 py-1 rounded border border-red-200">
                                Sil
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <?php if($edit_mode): ?>
    <script>
        document.getElementById('formArea').scrollIntoView({ behavior: 'smooth' });
    </script>
    <?php endif; ?>
</body>
</html>
