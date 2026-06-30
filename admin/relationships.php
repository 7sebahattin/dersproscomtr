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

if (isset($_POST['assign_coach'])) {
    $tid = $_POST['teacher_id'];
    $sid = $_POST['student_id'];

    $chk = $pdo->prepare("SELECT id FROM coaching_relationships WHERE student_id = ?");
    $chk->execute([$sid]);

    if ($chk->rowCount() > 0) {
        $pdo->prepare("UPDATE coaching_relationships SET teacher_id = ? WHERE student_id = ?")->execute([$tid, $sid]);
    } else {
        $pdo->prepare("INSERT INTO coaching_relationships (teacher_id, student_id) VALUES (?, ?)")->execute([$tid, $sid]);
    }
    $message = "Koç ataması güncellendi.";
}

if (isset($_POST['link_parent'])) {
    $pid = $_POST['parent_id'];
    $sid = $_POST['student_id'];

    $chk = $pdo->prepare("SELECT id FROM parent_relationships WHERE parent_id = ? AND student_id = ?");
    $chk->execute([$pid, $sid]);

    if ($chk->rowCount() == 0) {
        $pdo->prepare("INSERT INTO parent_relationships (parent_id, student_id) VALUES (?, ?)")->execute([$pid, $sid]);
        $message = "Öğrenci veliye bağlandı.";
    } else {
        $message = "Bu bağlantı zaten var.";
    }
}

if (isset($_POST['delete_relation'])) {
    $tbl = $_POST['table'];
    $rid = $_POST['id'];

    if ($tbl == 'coaching') $pdo->prepare("DELETE FROM coaching_relationships WHERE id = ?")->execute([$rid]);
    if ($tbl == 'parent')   $pdo->prepare("DELETE FROM parent_relationships WHERE id = ?")->execute([$rid]);

    $message = "İlişki silindi.";
}

$teachers = $pdo->query("SELECT * FROM users WHERE role='teacher'")->fetchAll();
$students = $pdo->query("SELECT * FROM users WHERE role='student'")->fetchAll();
$parents  = $pdo->query("SELECT * FROM users WHERE role='parent'")->fetchAll();

$coaching_rels = $pdo->query("
    SELECT cr.id, t.first_name as t_name, t.last_name as t_last, s.first_name as s_name, s.last_name as s_last
    FROM coaching_relationships cr
    JOIN users t ON cr.teacher_id=t.id
    JOIN users s ON cr.student_id=s.id
")->fetchAll();

$parent_rels = $pdo->query("
    SELECT pr.id, p.first_name as p_name, p.last_name as p_last, s.first_name as s_name, s.last_name as s_last
    FROM parent_relationships pr
    JOIN users p ON pr.parent_id=p.id
    JOIN users s ON pr.student_id=s.id
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İlişkilendirme Yönetimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar{width:8px;height:8px}
        .custom-scrollbar::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px}
        .custom-scrollbar::-webkit-scrollbar-thumb:hover{background:#94a3b8}
    </style>
</head>
<body class="bg-slate-50 flex h-screen overflow-hidden">

<?php include 'sidebar.php'; ?>

<main class="flex-grow overflow-y-auto p-8">
    <h1 class="text-2xl font-black text-slate-800 mb-8 tracking-tight">İlişkilendirme Yönetimi</h1>

    <?php if($message): ?>
        <div class="bg-green-50 text-green-700 p-4 rounded-xl mb-6 font-bold border border-green-100">✅ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6">
                <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">🎓 Koç Atama</h3>
                <form method="POST" class="flex gap-2">
                    <input type="hidden" name="assign_coach" value="1">

                    <select name="teacher_id" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 text-sm focus:bg-white outline-none" required>
                        <option value="">Öğretmen Seç</option>
                        <?php foreach($teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo $t['first_name'].' '.$t['last_name']; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="student_id" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 text-sm focus:bg-white outline-none" required>
                        <option value="">Öğrenci Seç</option>
                        <?php foreach($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo $s['first_name'].' '.$s['last_name']; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button class="bg-indigo-600 text-white px-6 py-2 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg">Ata</button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 font-bold text-slate-500 uppercase text-xs">
                        <tr>
                            <th class="p-4">Öğretmen</th>
                            <th class="p-4">Öğrenci</th>
                            <th class="p-4 text-right">Sil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($coaching_rels as $r): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-4 font-bold text-indigo-700"><?php echo $r['t_name'].' '.$r['t_last']; ?></td>
                            <td class="p-4 font-medium text-slate-700"><?php echo $r['s_name'].' '.$r['s_last']; ?></td>
                            <td class="p-4 text-right">
                                <form method="POST" onsubmit="return confirm('Silinsin mi?')">
                                    <input type="hidden" name="delete_relation" value="1">
                                    <input type="hidden" name="table" value="coaching">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <button class="text-red-400 hover:text-red-600 hover:bg-red-50 p-2 rounded-lg transition">✕</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6">
                <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">👨‍👩‍👧 Veli Bağlama</h3>
                <form method="POST" class="flex gap-2">
                    <input type="hidden" name="link_parent" value="1">

                    <select name="parent_id" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 text-sm focus:bg-white outline-none" required>
                        <option value="">Veli Seç</option>
                        <?php foreach($parents as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo $p['first_name'].' '.$p['last_name']; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="student_id" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 text-sm focus:bg-white outline-none" required>
                        <option value="">Öğrenci Seç</option>
                        <?php foreach($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo $s['first_name'].' '.$s['last_name']; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button class="bg-orange-500 text-white px-6 py-2 rounded-xl font-bold hover:bg-orange-600 transition shadow-lg">Bağla</button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 font-bold text-slate-500 uppercase text-xs">
                        <tr>
                            <th class="p-4">Veli</th>
                            <th class="p-4">Öğrenci</th>
                            <th class="p-4 text-right">Sil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($parent_rels as $r): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-4 font-bold text-orange-700"><?php echo $r['p_name'].' '.$r['p_last']; ?></td>
                            <td class="p-4 font-medium text-slate-700"><?php echo $r['s_name'].' '.$r['s_last']; ?></td>
                            <td class="p-4 text-right">
                                <form method="POST" onsubmit="return confirm('Silinsin mi?')">
                                    <input type="hidden" name="delete_relation" value="1">
                                    <input type="hidden" name="table" value="parent">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <button class="text-red-400 hover:text-red-600 hover:bg-red-50 p-2 rounded-lg transition">✕</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

</body>
</html>
