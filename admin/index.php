<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: ../index.php");
    exit;
}

// İSTATİSTİKLER
$student_cnt = $pdo->query("SELECT count(*) FROM users WHERE role='student'")->fetchColumn();
$teacher_cnt = $pdo->query("SELECT count(*) FROM users WHERE role='teacher'")->fetchColumn();
$parent_cnt  = $pdo->query("SELECT count(*) FROM users WHERE role='parent'")->fetchColumn();

$resource_cnt = 0;
try {
    $resource_cnt = $pdo->query("SELECT count(*) FROM resources")->fetchColumn();
} catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
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
        <h1 class="text-2xl font-black text-slate-800 mb-8 tracking-tight">Genel Bakış</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl">🎓</div>
                <div>
                    <p class="text-slate-500 text-xs font-bold uppercase">Öğrenciler</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $student_cnt; ?></h3>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-2xl">👨‍🏫</div>
                <div>
                    <p class="text-slate-500 text-xs font-bold uppercase">Öğretmenler</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $teacher_cnt; ?></h3>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-pink-50 text-pink-600 flex items-center justify-center text-2xl">👨‍👩‍👧</div>
                <div>
                    <p class="text-slate-500 text-xs font-bold uppercase">Veliler</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $parent_cnt; ?></h3>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">📚</div>
                <div>
                    <p class="text-slate-500 text-xs font-bold uppercase">Kaynaklar</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $resource_cnt; ?></h3>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h3 class="font-bold text-slate-800 mb-4">Hızlı İşlemler</h3>
                <div class="flex gap-4">
                    <a href="users.php" class="flex-1 flex items-center justify-center gap-2 p-4 rounded-xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 transition border border-slate-100 font-bold text-sm">
                        <span>👤</span> Kullanıcı Ekle
                    </a>
                    <a href="relationships.php" class="flex-1 flex items-center justify-center gap-2 p-4 rounded-xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 transition border border-slate-100 font-bold text-sm">
                        <span>🔗</span> Eşleştirme Yap
                    </a>
                </div>
            </div>

            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-6 text-white shadow-lg flex flex-col justify-center">
                <h2 class="text-xl font-bold mb-2">Admin Paneline Hoşgeldin! 👋</h2>
                <p class="text-indigo-100 text-sm">Sol menüyü kullanarak sistemi yönetebilirsin.</p>
            </div>
        </div>
    </main>
</body>
</html>
