<?php
// teachers.php (moved from admin_teachers.php)
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: ../index.php"); exit;
}

// KREDİ GÜNCELLEME İŞLEMİ
if (isset($_POST['update_credits'])) {
    $tid = (int)$_POST['teacher_id'];
    $new_credit = (int)$_POST['credits'];
    $pdo->prepare("UPDATE users SET credits = ? WHERE id = ?")->execute([$new_credit, $tid]);
    header("Location: teachers.php?success=1"); exit;
}

// TALEP LİSTESİ MODAL İÇİN VERİ ÇEKME
$modal_logs = [];
$selected_teacher_name = "";
$show_modal = false;

if (isset($_GET['view_logs'])) {
    $tid = $_GET['view_logs'];
    $show_modal = true;

    // Öğretmen Adı
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$tid]);
    $t_info = $stmt->fetch();
    $selected_teacher_name = $t_info['first_name'] . ' ' . $t_info['last_name'];

    $sql_logs = "SELECT l.created_at, s.first_name, s.last_name, s.phone, s.email
                 FROM click_logs l
                 JOIN users s ON l.student_id = s.id
                 WHERE l.teacher_id = ?
                 ORDER BY l.created_at DESC";
    $stmt = $pdo->prepare($sql_logs);
    $stmt->execute([$tid]);
    $modal_logs = $stmt->fetchAll();
}

// Öğretmen Listesi (Kredilerle beraber)
$teachers = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY credits DESC, whatsapp_clicks DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Öğretmen Yönetimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-50 flex h-screen overflow-hidden">
<?php include 'sidebar.php'; ?>

<main class="flex-grow overflow-y-auto p-8 relative">
    <h1 class="text-3xl font-black text-slate-800 mb-6">Öğretmen Vitrini & Kredi Yönetimi</h1>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-xs">
                <tr>
                    <th class="p-4">Öğretmen</th>
                    <th class="p-4">Branş</th>
                    <th class="p-4 text-center">Mesaj Kredisi</th> <th class="p-4 text-center">Toplam Tık</th>
                    <th class="p-4 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($teachers as $t): ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-4 flex items-center gap-3">
                        <?php if($t['photo_path']): ?>
                            <img src="<?= $t['photo_path'] ?>" class="w-10 h-10 rounded-full object-cover">
                        <?php else: ?>
                            <span class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold">
                                <?= strtoupper(substr($t['first_name'],0,1)) ?>
                            </span>
                        <?php endif; ?>
                        <span class="font-bold text-slate-700"><?= $t['first_name'].' '.$t['last_name'] ?></span>
                    </td>
                    <td class="p-4 text-sm text-slate-600"><?= $t['branch'] ?></td>

                    <td class="p-4 text-center">
                        <form method="POST" class="flex items-center justify-center gap-2">
                            <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
                            <input type="number" name="credits" value="<?= (int)$t['credits'] ?>" class="w-16 border border-slate-300 rounded-lg px-2 py-1 text-center font-bold text-slate-700 focus:border-indigo-500 outline-none text-sm">
                            <button type="submit" name="update_credits" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 p-1.5 rounded-lg transition" title="Kaydet">
                                💾
                            </button>
                        </form>
                    </td>

                    <td class="p-4 text-center">
                        <span class="bg-slate-100 text-slate-700 font-bold px-3 py-1 rounded-full text-xs">
                            <?= $t['whatsapp_clicks'] ?> Tık
                        </span>
                    </td>
                    <td class="p-4 text-right">
                        <a href="?view_logs=<?= $t['id'] ?>" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-4 py-2 rounded-lg text-xs font-bold transition">
                            📄 Talepleri Gör
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if($show_modal): ?>
    <div class="absolute inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col animate-[fadeIn_0.2s_ease-out]">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Eski Talep Geçmişi</h3>
                    <p class="text-xs text-slate-500">Öğretmen: <span class="font-bold text-indigo-600"><?= $selected_teacher_name ?></span></p>
                </div>
                <a href="teachers.php" class="text-slate-400 hover:text-red-500 text-2xl font-bold">&times;</a>
            </div>
            <div class="p-0 overflow-y-auto custom-scrollbar flex-grow">
                <?php if(empty($modal_logs)): ?>
                    <div class="p-8 text-center text-slate-400 italic">Henüz kaydedilmiş bir eski tip talep yok.</div>
                <?php else: ?>
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-xs font-bold text-slate-400 sticky top-0">
                            <tr><th class="p-4">Tarih</th><th class="p-4">Öğrenci</th><th class="p-4">İletişim</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($modal_logs as $log): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-4 whitespace-nowrap"><div class="font-bold text-slate-700"><?= date('d.m.Y', strtotime($log['created_at'])) ?></div></td>
                                <td class="p-4 font-medium text-indigo-700"><?= $log['first_name'].' '.$log['last_name'] ?></td>
                                <td class="p-4 text-xs text-slate-500"><div><?= $log['email'] ?></div><div><?= $log['phone'] ?></div></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="p-4 bg-slate-50 border-t border-slate-100 rounded-b-2xl text-right">
                <a href="teachers.php" class="bg-slate-800 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-slate-900">Kapat</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
