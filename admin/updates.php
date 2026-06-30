<?php
// updates.php — Sistem Güncelleme / Duyuru Yönetimi

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: ../index.php"); exit;
}

$message = '';

// --- İŞLEMLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_update'])) {
        $version   = trim(substr($_POST['version']  ?? '', 0, 20));
        $title     = trim(substr($_POST['title']    ?? '', 0, 255));
        $content   = trim($_POST['content']  ?? '');
        $type      = $_POST['type'] ?? 'duyuru';
        $published = isset($_POST['is_published']) ? 1 : 0;
        $allowed   = ['ozellik','duzeltme','iyilestirme','duyuru'];
        if (!in_array($type, $allowed)) $type = 'duyuru';

        if ($title && $content) {
            $pdo->prepare("INSERT INTO system_updates (version, title, content, type, is_published) VALUES (?, ?, ?, ?, ?)")
                ->execute([$version ?: null, $title, $content, $type, $published]);
            $message = "<div class='bg-green-100 text-green-700 p-3 rounded-xl mb-4 text-sm'>✅ Güncelleme notu eklendi.</div>";
        } else {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded-xl mb-4 text-sm'>❌ Başlık ve içerik zorunludur.</div>";
        }
    }

    if (isset($_POST['toggle_published'])) {
        $id  = (int)$_POST['update_id'];
        $cur = (int)$_POST['current'];
        $pdo->prepare("UPDATE system_updates SET is_published = ? WHERE id = ?")->execute([$cur ? 0 : 1, $id]);
        $message = "<div class='bg-blue-100 text-blue-700 p-3 rounded-xl mb-4 text-sm'>👁️ Yayın durumu güncellendi.</div>";
    }

    if (isset($_POST['delete_update'])) {
        $id = (int)$_POST['update_id'];
        $pdo->prepare("DELETE FROM system_updates WHERE id = ?")->execute([$id]);
        $message = "<div class='bg-yellow-100 text-yellow-700 p-3 rounded-xl mb-4 text-sm'>🗑️ Güncelleme notu silindi.</div>";
    }
}

// Tabloyu otomatik oluştur (yoksa)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_updates (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        version      VARCHAR(20)  DEFAULT NULL,
        title        VARCHAR(255) NOT NULL,
        content      TEXT         NOT NULL,
        type         ENUM('ozellik','duzeltme','iyilestirme','duyuru') DEFAULT 'duyuru',
        is_published TINYINT(1)   DEFAULT 1,
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

// Veri çek
$updates = [];
try {
    $updates = $pdo->query("SELECT * FROM system_updates ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {}

$typeLabels = [
    'ozellik'     => ['label' => 'Özellik',      'color' => 'bg-blue-100 text-blue-700'],
    'duzeltme'    => ['label' => 'Düzeltme',     'color' => 'bg-red-100 text-red-600'],
    'iyilestirme' => ['label' => 'İyileştirme',  'color' => 'bg-purple-100 text-purple-700'],
    'duyuru'      => ['label' => 'Duyuru',        'color' => 'bg-amber-100 text-amber-700'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Güncelleme Notları — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-50 flex h-screen overflow-hidden">

<?php include 'sidebar.php'; ?>

<main class="flex-grow overflow-y-auto bg-slate-50 p-8">

    <div class="max-w-5xl mx-auto">

        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-800">📋 Güncelleme Notları</h2>
            <p class="text-slate-500 text-sm mt-1">Öğretmen panelinde gösterilecek duyuru ve sürüm notlarını yönetin.</p>
        </div>

        <?= $message ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <!-- EKLEME FORMU -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden sticky top-4">
                    <div class="bg-[#1e1b4b] px-5 py-4">
                        <h3 class="font-bold text-white text-base">➕ Yeni Not Ekle</h3>
                        <p class="text-indigo-300 text-xs mt-1">Öğretmenlere görünür olacak</p>
                    </div>
                    <div class="p-5">
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="add_update" value="1">

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Sürüm</label>
                                    <input type="text" name="version" placeholder="Örn: v1.5" maxlength="20"
                                           class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Tür</label>
                                    <select name="type" class="w-full border border-slate-300 rounded-xl p-2.5 bg-white text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                                        <option value="ozellik">✨ Özellik</option>
                                        <option value="duzeltme">🐛 Düzeltme</option>
                                        <option value="iyilestirme">⚡ İyileştirme</option>
                                        <option value="duyuru" selected>📢 Duyuru</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Başlık *</label>
                                <input type="text" name="title" placeholder="Kısa ve açıklayıcı başlık" maxlength="255" required
                                       class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Açıklama *</label>
                                <textarea name="content" rows="4" placeholder="Ne yapıldı, ne değişti..." required
                                          class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none"></textarea>
                            </div>

                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" name="is_published" value="1" checked class="w-4 h-4 rounded text-indigo-600">
                                <span class="text-sm font-medium text-slate-700">Hemen yayınla</span>
                            </label>

                            <button type="submit" class="w-full bg-[#1e1b4b] text-white font-bold py-2.5 rounded-xl hover:bg-indigo-900 transition text-sm">
                                Notu Kaydet
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- LİSTE -->
            <div class="lg:col-span-3 space-y-3">
                <?php if (empty($updates)): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400 text-sm">
                        Henüz güncelleme notu yok.
                    </div>
                <?php endif; ?>

                <?php foreach ($updates as $u):
                    $t = $typeLabels[$u['type']] ?? $typeLabels['duyuru'];
                    $dt = new DateTime($u['created_at']);
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 <?= !$u['is_published'] ? 'opacity-60' : '' ?>">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $t['color'] ?>"><?= $t['label'] ?></span>
                            <?php if ($u['version']): ?>
                                <span class="text-xs font-bold bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full"><?= htmlspecialchars($u['version']) ?></span>
                            <?php endif; ?>
                            <?php if (!$u['is_published']): ?>
                                <span class="text-xs font-bold bg-slate-200 text-slate-500 px-2.5 py-1 rounded-full">Gizli</span>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-slate-400 whitespace-nowrap flex-shrink-0"><?= $dt->format('d.m.Y') ?></span>
                    </div>

                    <h4 class="font-bold text-slate-800 mt-2 text-sm"><?= htmlspecialchars($u['title']) ?></h4>
                    <p class="text-slate-500 text-xs mt-1 leading-relaxed"><?= nl2br(htmlspecialchars($u['content'])) ?></p>

                    <div class="flex gap-2 mt-4 pt-3 border-t border-slate-100">
                        <form method="POST" class="inline">
                            <input type="hidden" name="toggle_published" value="1">
                            <input type="hidden" name="update_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="current" value="<?= $u['is_published'] ?>">
                            <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg transition
                                <?= $u['is_published'] ? 'bg-slate-100 text-slate-600 hover:bg-slate-200' : 'bg-green-100 text-green-700 hover:bg-green-200' ?>">
                                <?= $u['is_published'] ? '🙈 Gizle' : '👁️ Yayınla' ?>
                            </button>
                        </form>
                        <form method="POST" class="inline" onsubmit="return confirm('Bu not silinecek. Emin misiniz?')">
                            <input type="hidden" name="delete_update" value="1">
                            <input type="hidden" name="update_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition">
                                🗑️ Sil
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</main>
</body>
</html>
