<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: ../index.php");
    exit;
}

$message = '';
$error   = '';

// ---- POST: Durum Güncelle ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $fb_id      = (int)$_POST['fb_id'];
    $new_status = $_POST['new_status'] ?? 'bekliyor';
    $admin_note = trim($_POST['admin_note'] ?? '');
    $allowed    = ['bekliyor', 'inceleniyor', 'cozuldu'];

    if (!in_array($new_status, $allowed)) {
        $error = 'Geçersiz durum.';
    } else {
        try {
            $resolved_at = ($new_status === 'cozuldu') ? date('Y-m-d H:i:s') : null;

            // Önceki durumu al (mail için)
            $prev = $pdo->prepare("SELECT status, user_email, user_name, subject FROM feedback WHERE id = ?");
            $prev->execute([$fb_id]);
            $fbRow = $prev->fetch(PDO::FETCH_ASSOC);

            $upd = $pdo->prepare("UPDATE feedback SET status = ?, admin_note = ?, resolved_at = ? WHERE id = ?");
            $upd->execute([$new_status, $admin_note ?: null, $resolved_at, $fb_id]);

            // Çözüldü olarak işaretlenince mail gönder
            if ($new_status === 'cozuldu' && $fbRow && $fbRow['status'] !== 'cozuldu' && !empty($fbRow['user_email'])) {
                require_once __DIR__ . '/../mail_helper.php';
                $noteText = $admin_note ? "<br><br><strong>Admin Notu:</strong> " . htmlspecialchars($admin_note) : '';
                send_mail_notification(
                    $fbRow['user_email'],
                    $fbRow['user_name'],
                    'Bildiriminiz Çözüldü – DersPROS',
                    "\"<strong>" . htmlspecialchars($fbRow['subject']) . "</strong>\" konulu bildiriminiz incelendi ve çözüme kavuşturuldu." . $noteText
                );
            }

            $message = 'Durum güncellendi.';
        } catch (Exception $e) {
            $error = 'Güncelleme hatası: ' . $e->getMessage();
        }
    }
}

// ---- Filtreler ----
$filter_status   = $_GET['status']   ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_search   = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($filter_status)   { $where[] = 'status = ?';   $params[] = $filter_status; }
if ($filter_category) { $where[] = 'category = ?'; $params[] = $filter_category; }
if ($filter_search)   { $where[] = '(user_name LIKE ? OR subject LIKE ? OR message LIKE ?)'; $params[] = "%$filter_search%"; $params[] = "%$filter_search%"; $params[] = "%$filter_search%"; }

$sql = "SELECT * FROM feedback" . ($where ? " WHERE " . implode(' AND ', $where) : '') . " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $feedbacks = [];
    $error = 'Tablo bulunamadı. Lütfen önce migrate_dogru_yanlis.php dosyasını çalıştırın.';
}

// Sayaçlar
$counts = ['bekliyor' => 0, 'inceleniyor' => 0, 'cozuldu' => 0, 'toplam' => 0];
try {
    $cntRows = $pdo->query("SELECT status, COUNT(*) as c FROM feedback GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cntRows as $r) { $counts[$r['status']] = (int)$r['c']; $counts['toplam'] += (int)$r['c']; }
} catch (Exception $e) {}

$catLabels = ['hata_bildir' => '🐛 Hata Bildir', 'gorusoner' => '💡 Görüş & Öneri', 'sikayet' => '😤 Şikayet', 'diger' => '📋 Diğer'];
$statusLabels = ['bekliyor' => '⏳ Bekliyor', 'inceleniyor' => '🔍 İnceleniyor', 'cozuldu' => '✅ Çözüldü'];
$statusColors = ['bekliyor' => 'bg-yellow-100 text-yellow-800 border-yellow-200', 'inceleniyor' => 'bg-blue-100 text-blue-800 border-blue-200', 'cozuldu' => 'bg-green-100 text-green-800 border-green-200'];
$catColors    = ['hata_bildir' => 'bg-red-100 text-red-700 border-red-200', 'gorusoner' => 'bg-indigo-100 text-indigo-700 border-indigo-200', 'sikayet' => 'bg-orange-100 text-orange-700 border-orange-200', 'diger' => 'bg-slate-100 text-slate-600 border-slate-200'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Geri Bildirimler – Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .animate-fadeIn { animation: fadeIn .25s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
    </style>
</head>
<body class="bg-slate-50 flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; ?>

    <main class="flex-grow overflow-y-auto p-6 custom-scrollbar">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Geri Bildirimler</h1>
                <p class="text-slate-500 text-sm mt-0.5">Öğretmenlerden gelen hata bildirimleri, öneriler ve şikayetler</p>
            </div>
            <span class="bg-[#223488] text-white text-sm font-black px-4 py-2 rounded-xl shadow-sm"><?php echo $counts['toplam']; ?> Toplam</span>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 p-3 rounded-xl mb-4 text-sm font-medium animate-fadeIn">✅ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 p-3 rounded-xl mb-4 text-sm font-medium animate-fadeIn">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- KPI Kartlar -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <?php foreach ([
                ['label'=>'Toplam',      'val'=>$counts['toplam'],      'bg'=>'bg-[#223488]',  'text'=>'text-white'],
                ['label'=>'Bekliyor',    'val'=>$counts['bekliyor'],    'bg'=>'bg-yellow-50',  'text'=>'text-yellow-700'],
                ['label'=>'İnceleniyor', 'val'=>$counts['inceleniyor'], 'bg'=>'bg-blue-50',    'text'=>'text-blue-700'],
                ['label'=>'Çözüldü',     'val'=>$counts['cozuldu'],     'bg'=>'bg-green-50',   'text'=>'text-green-700'],
            ] as $kpi): ?>
            <div class="<?php echo $kpi['bg']; ?> rounded-2xl p-4 border border-slate-100 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider <?php echo $kpi['text']; ?> opacity-70"><?php echo $kpi['label']; ?></p>
                <p class="text-3xl font-black <?php echo $kpi['text']; ?> mt-1"><?php echo $kpi['val']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filtreler -->
        <form method="GET" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-6 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="text-[10px] font-black text-slate-500 uppercase block mb-1">Durum</label>
                <select name="status" class="w-full border border-slate-200 rounded-xl p-2.5 text-sm font-medium outline-none bg-white">
                    <option value="">Tümü</option>
                    <?php foreach ($statusLabels as $v => $l): ?>
                    <option value="<?php echo $v; ?>" <?php echo $filter_status === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[160px]">
                <label class="text-[10px] font-black text-slate-500 uppercase block mb-1">Kategori</label>
                <select name="category" class="w-full border border-slate-200 rounded-xl p-2.5 text-sm font-medium outline-none bg-white">
                    <option value="">Tümü</option>
                    <?php foreach ($catLabels as $v => $l): ?>
                    <option value="<?php echo $v; ?>" <?php echo $filter_category === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-[2] min-w-[200px]">
                <label class="text-[10px] font-black text-slate-500 uppercase block mb-1">Ara</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="İsim, konu veya mesaj..."
                       class="w-full border border-slate-200 rounded-xl p-2.5 text-sm font-medium outline-none focus:border-[#223488] focus:ring-1 focus:ring-[#223488]">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-[#223488] hover:bg-[#314595] text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm transition">Filtrele</button>
                <a href="feedback.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2.5 rounded-xl text-sm font-bold transition">Sıfırla</a>
            </div>
        </form>

        <!-- Liste -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h2 class="font-bold text-slate-700 text-sm"><?php echo count($feedbacks); ?> sonuç listeleniyor</h2>
            </div>

            <?php if (empty($feedbacks)): ?>
                <div class="p-12 text-center text-slate-400">
                    <div class="text-5xl mb-3 grayscale opacity-40">📭</div>
                    <p class="font-medium">Kayıt bulunamadı.</p>
                </div>
            <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($feedbacks as $fb):
                    $catColor  = $catColors[$fb['category']]  ?? 'bg-slate-100 text-slate-600';
                    $statColor = $statusColors[$fb['status']] ?? 'bg-slate-100 text-slate-600';
                    $catLabel  = $catLabels[$fb['category']]  ?? $fb['category'];
                    $statLabel = $statusLabels[$fb['status']] ?? $fb['status'];
                    $dt = date('d.m.Y H:i', strtotime($fb['created_at']));
                    $rddt = $fb['resolved_at'] ? date('d.m.Y H:i', strtotime($fb['resolved_at'])) : null;
                ?>
                <div class="p-5 hover:bg-slate-50 transition-colors animate-fadeIn">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <!-- Başlık ve Kategoriler -->
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-[10px] font-black px-2 py-1 rounded-lg border <?php echo $catColor; ?>"><?php echo $catLabel; ?></span>
                                <span class="text-[10px] font-black px-2 py-1 rounded-lg border <?php echo $statColor; ?>"><?php echo $statLabel; ?></span>
                                <?php if ($rddt): ?>
                                    <span class="text-[9px] font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded-lg">Çözüldü: <?php echo $rddt; ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- Konu -->
                            <h3 class="font-bold text-slate-800 text-sm mb-1"><?php echo htmlspecialchars($fb['subject']); ?></h3>
                            <!-- Mesaj -->
                            <p class="text-slate-600 text-xs leading-relaxed mb-2 whitespace-pre-wrap"><?php echo htmlspecialchars($fb['message']); ?></p>
                            <!-- Admin notu -->
                            <?php if ($fb['admin_note']): ?>
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-2.5 mt-2">
                                <p class="text-[10px] font-black text-blue-600 uppercase mb-0.5">Admin Notu</p>
                                <p class="text-xs text-blue-700"><?php echo htmlspecialchars($fb['admin_note']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- Sağ: Kullanıcı + Tarih -->
                        <div class="text-right flex-shrink-0 min-w-[140px]">
                            <p class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($fb['user_name']); ?></p>
                            <p class="text-[10px] text-slate-400 font-medium"><?php echo htmlspecialchars($fb['user_email']); ?></p>
                            <p class="text-[10px] text-slate-400 mt-1 font-medium"><?php echo $dt; ?></p>
                            <!-- Güncelle butonu -->
                            <button type="button"
                                    onclick="openUpdateModal(<?php echo $fb['id']; ?>, '<?php echo $fb['status']; ?>', <?php echo htmlspecialchars(json_encode($fb['admin_note'] ?? ''), ENT_QUOTES); ?>)"
                                    class="mt-2 text-[10px] font-bold bg-[#223488] text-white px-3 py-1.5 rounded-lg hover:bg-[#314595] transition">
                                Güncelle
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Güncelleme Modalı -->
    <div id="updateModal" class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-fadeIn border border-slate-100">
            <div class="bg-[#223488] p-4 flex justify-between items-center text-white">
                <h3 class="font-bold text-sm">Durumu Güncelle</h3>
                <button onclick="closeUpdateModal()" class="bg-white/20 hover:bg-white/30 rounded-full p-1.5 transition text-xs">✕</button>
            </div>
            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="fb_id" id="um_fb_id">
                <div>
                    <label class="text-[10px] font-black text-slate-500 uppercase block mb-1.5">Durum</label>
                    <select name="new_status" id="um_status" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-bold outline-none focus:border-[#223488] bg-white">
                        <option value="bekliyor">⏳ Bekliyor</option>
                        <option value="inceleniyor">🔍 İnceleniyor</option>
                        <option value="cozuldu">✅ Çözüldü</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-500 uppercase block mb-1.5">Admin Notu <span class="font-normal normal-case">(isteğe bağlı)</span></label>
                    <textarea name="admin_note" id="um_note" rows="3" placeholder="Kullanıcıya iletilecek not veya açıklama..."
                              class="w-full border border-slate-200 rounded-xl p-3 text-sm font-medium outline-none focus:border-[#223488] focus:ring-1 focus:ring-[#223488] resize-none"></textarea>
                </div>
                <p id="um_mail_hint" class="text-xs text-green-600 font-medium hidden">📧 Durum "Çözüldü" yapılırsa kullanıcıya otomatik e-posta gönderilir.</p>
                <div class="flex gap-3">
                    <button type="button" onclick="closeUpdateModal()" class="flex-1 bg-slate-100 text-slate-600 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-200 transition">İptal</button>
                    <button type="submit" class="flex-[2] bg-[#223488] hover:bg-[#314595] text-white py-2.5 rounded-xl font-bold text-sm shadow-sm transition">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openUpdateModal(id, status, note) {
        document.getElementById('um_fb_id').value  = id;
        document.getElementById('um_status').value = status;
        document.getElementById('um_note').value   = note || '';
        updateMailHint();
        document.getElementById('updateModal').classList.remove('hidden');
    }
    function closeUpdateModal() {
        document.getElementById('updateModal').classList.add('hidden');
    }
    function updateMailHint() {
        const hint = document.getElementById('um_mail_hint');
        hint.classList.toggle('hidden', document.getElementById('um_status').value !== 'cozuldu');
    }
    document.getElementById('um_status').addEventListener('change', updateMailHint);
    </script>
</body>
</html>
