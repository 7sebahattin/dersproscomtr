<?php
// koc/ogrencilerim.php - MODERN UI DESIGN REVISION
// Design update: Senior UI/UX Standards

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Dosya yollarını ayarla
$baseDir = __DIR__;
$dbPath = file_exists($baseDir . '/../db.php') ? $baseDir . '/../db.php' : $baseDir . '/db.php';
$headerPath = file_exists($baseDir . '/header.php') ? $baseDir . '/header.php' : $baseDir . '/../header.php';
$footerPath = file_exists($baseDir . '/footer.php') ? $baseDir . '/footer.php' : $baseDir . '/../footer.php';

require_once $dbPath;
include $headerPath;

// Güvenlik Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    echo "<script>window.location.href='../index.php';</script>";
    exit;
}

$teacher_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Sınıf/Alan kolonları (yoksa ekle — idempotent).
// grade: 9/10/11/12/Mezun (Lise) veya 5-8 (Ortaokul); track: Sayısal/Sözel/Eşit Ağırlık (yalnızca 11/12/Mezun)
try {
    if ($pdo->query("SHOW COLUMNS FROM users LIKE 'grade'")->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN grade VARCHAR(10) DEFAULT NULL");
    }
    if ($pdo->query("SHOW COLUMNS FROM users LIKE 'track'")->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN track VARCHAR(20) DEFAULT NULL");
    }
} catch (Throwable $e) {}

// Sınıf/Alan doğrulama: seviyeye uymayan değerleri NULL'a çevirir
function ogr_validate_grade_track(string $level, string $grade, string $track): array {
    $validGrades = ($level === 'Ortaokul') ? ['5','6','7','8'] : ['9','10','11','12','Mezun'];
    if (!in_array($grade, $validGrades, true)) $grade = null;
    $trackAllowed = ($level !== 'Ortaokul' && in_array($grade, ['11','12','Mezun'], true));
    if (!$trackAllowed || !in_array($track, ['Sayısal','Sözel','Eşit Ağırlık'], true)) $track = null;
    return [$grade, $track];
}

// --- İŞLEMLER (POST) ---

// 1. Öğrenci Ekleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_student'])) {
    $u_user   = trim($_POST['username']);
    $u_pass   = $_POST['password'];
    $u_name   = trim($_POST['first_name']);
    $u_last   = trim($_POST['last_name']);
    $u_level  = $_POST['school_level'] ?? 'Lise';
    $u_email  = trim($_POST['email']);
    $u_phone  = trim($_POST['phone']);
    $u_pname  = trim($_POST['parent_name']  ?? '');
    $u_pphone = trim($_POST['parent_phone'] ?? '');
    [$u_grade, $u_track] = ogr_validate_grade_track($u_level, trim($_POST['grade'] ?? ''), trim($_POST['track'] ?? ''));

    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$u_user]);

    if ($check->rowCount() > 0) {
        $error = "Bu kullanıcı adı zaten kullanımda.";
    } else {
        try {
            $passHash = password_hash($u_pass, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, first_name, last_name, email, phone, parent_name, parent_phone, role, school_level, grade, track, is_active, date_joined)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'student', ?, ?, ?, 1, NOW())";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$u_user, $passHash, $u_name, $u_last, $u_email, $u_phone, $u_pname, $u_pphone, $u_level, $u_grade, $u_track])) {
                $new_sid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO coaching_relationships (teacher_id, student_id) VALUES (?, ?)")->execute([$teacher_id, $new_sid]);
                $message = "Öğrenci başarıyla sisteme eklendi.";
            }
        } catch (PDOException $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// 2. Öğrenci Güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_student'])) {
    $sid = $_POST['student_id'];
    $fname = $_POST['first_name'];
    $lname = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $pname = $_POST['parent_name'];
    $pphone = $_POST['parent_phone'];
    $level = $_POST['school_level'];
    [$grade, $track] = ogr_validate_grade_track($level, trim($_POST['grade'] ?? ''), trim($_POST['track'] ?? ''));

    $checkRel = $pdo->prepare("SELECT id FROM coaching_relationships WHERE teacher_id = ? AND student_id = ?");
    $checkRel->execute([$teacher_id, $sid]);

    if ($checkRel->rowCount() > 0) {
        $sql = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, parent_name=?, parent_phone=?, school_level=?, grade=?, track=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$fname, $lname, $email, $phone, $pname, $pphone, $level, $grade, $track, $sid])) {
            $message = "Bilgiler başarıyla güncellendi.";
        } else {
            $error = "Güncelleme sırasında bir sorun oluştu.";
        }
    } else {
        $error = "Yetkisiz işlem girişimi.";
    }
}

// 3. Öğrenci Silme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_student'])) {
    $sid = $_POST['delete_student_id'];
    $checkRel = $pdo->prepare("SELECT id FROM coaching_relationships WHERE teacher_id = ? AND student_id = ?");
    $checkRel->execute([$teacher_id, $sid]);

    if ($checkRel->rowCount() > 0) {
        $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($del->execute([$sid])) {
            $message = "Öğrenci kaydı silindi.";
        } else {
            $error = "Silme işlemi başarısız.";
        }
    } else {
        $error = "Yetki hatası.";
    }
}

// 4. Öğrenci Bağla
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['link_student'])) {
    $search_term = trim($_POST['search_term'] ?? '');
    if (!empty($search_term)) {
        $findUser = $pdo->prepare("SELECT id, username, first_name, last_name, role FROM users WHERE (username = ? OR email = ?) AND role = 'student' LIMIT 1");
        $findUser->execute([$search_term, $search_term]);
        $foundUser = $findUser->fetch(PDO::FETCH_ASSOC);
        if (!$foundUser) {
            $error = "Bu kullanıcı adı veya e-posta ile kayıtlı bir öğrenci bulunamadı.";
        } else {
            $alreadyLinked = $pdo->prepare("SELECT id FROM coaching_relationships WHERE teacher_id = ? AND student_id = ?");
            $alreadyLinked->execute([$teacher_id, $foundUser['id']]);
            if ($alreadyLinked->rowCount() > 0) {
                $error = htmlspecialchars($foundUser['first_name'] . ' ' . $foundUser['last_name']) . " zaten listenize bağlı.";
            } else {
                $pdo->prepare("INSERT INTO coaching_relationships (teacher_id, student_id) VALUES (?, ?)")->execute([$teacher_id, $foundUser['id']]);
                $message = htmlspecialchars($foundUser['first_name'] . ' ' . $foundUser['last_name']) . " başarıyla listenize eklendi.";
            }
        }
    } else {
        $error = "Lütfen bir kullanıcı adı veya e-posta girin.";
    }
}

// --- VERİ ÇEKME ---
$sql = "
    SELECT
        u.*,
        COALESCE(u.push_notifications_enabled, 1) AS push_notifications_enabled,
        (SELECT COUNT(*) FROM push_subscriptions ps WHERE ps.student_id = u.id) AS has_subscription,
        (
            SELECT CONCAT(a.appointment_date, ' ', a.appointment_time)
            FROM appointments a
            WHERE a.student_id = u.id
            AND a.teacher_id = :tid
            AND (a.status IS NULL OR a.status != 'cancelled')
            AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= NOW()
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
            LIMIT 1
        ) as next_appointment_dt
    FROM users u
    JOIN coaching_relationships cr ON u.id = cr.student_id
    WHERE cr.teacher_id = :tid
    ORDER BY u.first_name ASC, u.last_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':tid' => $teacher_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
    .glass-effect { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    .table-row-hover:hover td { background-color: #f8fafc; }
    .animate-fadeIn { animation: fadeIn 0.4s ease-out forwards; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="min-h-screen pb-20">
    
    <div class="bg-white border-b border-slate-100 sticky top-0 z-30 shadow-sm/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center py-5 gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 shadow-sm border border-blue-100">
                        <i class="fa-solid fa-users-viewfinder text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Öğrenci Yönetimi</h1>
                        <p class="text-slate-400 text-sm font-medium">Toplam <span class="text-blue-600 font-bold"><?php echo count($students); ?></span> öğrenciye koçluk yapıyorsunuz.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button onclick="openModal('linkStudentModal')" class="inline-flex items-center justify-center px-5 py-3 font-bold text-[#223488] transition-all duration-200 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 hover:shadow-md hover:-translate-y-0.5 focus:outline-none">
                        <i class="fa-solid fa-link mr-2"></i>
                        Öğrenci Bağla
                    </button>
                    <button onclick="openAddModal()" class="group relative inline-flex items-center justify-center px-6 py-3 font-bold text-white transition-all duration-200 bg-blue-600 rounded-xl hover:bg-blue-700 hover:shadow-lg hover:shadow-blue-200 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600">
                        <span class="mr-2 text-lg group-hover:rotate-90 transition-transform duration-300">＋</span>
                        Yeni Öğrenci Ekle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 animate-fadeIn">
        
        <?php if($message): ?>
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-800 flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-check text-xl"></i>
                <span class="font-bold text-sm"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-800 flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                <span class="font-bold text-sm"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-[24px] shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-100">
                            <th class="p-5 pl-8 text-xs font-extrabold text-slate-400 uppercase tracking-wider">Öğrenci Profili</th>
                            <th class="p-5 text-xs font-extrabold text-slate-400 uppercase tracking-wider">Kullanıcı Bilgileri</th>
                            <th class="p-5 text-xs font-extrabold text-slate-400 uppercase tracking-wider">İletişim</th>
                            <th class="p-5 text-xs font-extrabold text-slate-400 uppercase tracking-wider">Sonraki Randevu</th>
                            <th class="p-5 pr-8 text-right text-xs font-extrabold text-slate-400 uppercase tracking-wider">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-600">
                        <?php if(count($students) == 0): ?>
                            <tr>
                                <td colspan="5" class="py-20 text-center">
                                    <div class="flex flex-col items-center justify-center opacity-60">
                                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                                            <i class="fa-regular fa-folder-open text-3xl text-slate-300"></i>
                                        </div>
                                        <p class="text-slate-500 font-bold">Henüz kayıtlı öğrenciniz yok.</p>
                                        <p class="text-xs text-slate-400 mt-1">Yukarıdaki butonu kullanarak ilk öğrencinizi ekleyin.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach($students as $s): 
                            $initial = strtoupper(mb_substr($s['first_name'], 0, 1, 'UTF-8'));
                            $colorIndex = (ord($initial) % 5); // Basit renk rotasyonu
                            $colors = ['bg-blue-100 text-blue-600', 'bg-purple-100 text-purple-600', 'bg-pink-100 text-pink-600', 'bg-orange-100 text-orange-600', 'bg-teal-100 text-teal-600'];
                            $avatarClass = $colors[$colorIndex];
                            
                            // Randevu İşleme
                            $nextApp = null;
                            if ($s['next_appointment_dt']) {
                                $dt = strtotime($s['next_appointment_dt']);
                                $isToday = (date('Y-m-d', $dt) == date('Y-m-d'));
                                $nextApp = [
                                    'date' => date('d.m.Y', $dt),
                                    'time' => date('H:i', $dt),
                                    'class' => $isToday 
                                        ? 'bg-orange-50 text-orange-700 border-orange-100 ring-orange-100' 
                                        : 'bg-blue-50 text-blue-700 border-blue-100 ring-blue-50'
                                ];
                            }

                            $sJson = htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="group transition-colors hover:bg-slate-50/60">
                            <td class="p-5 pl-8">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl <?php echo $avatarClass; ?> flex items-center justify-center font-black text-lg shadow-sm group-hover:scale-105 transition-transform">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-slate-900 font-bold text-sm group-hover:text-blue-600 transition-colors">
                                                <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
                                            </span>
                                            <?php
                                            $pushEnabled = (int)($s['push_notifications_enabled'] ?? 1);
                                            $hasSub      = (int)($s['has_subscription'] ?? 0);
                                            if ($pushEnabled == 0): ?>
                                            <span class="inline-flex items-center gap-1 text-[9px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-600 border border-red-200 flex-shrink-0">
                                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zm0 16a2 2 0 01-2-2h4a2 2 0 01-2 2z"/></svg>
                                                Bildirim Kapalı
                                            </span>
                                            <?php elseif ($hasSub == 0): ?>
                                            <span class="inline-flex items-center gap-1 text-[9px] font-bold px-2 py-0.5 rounded-full bg-orange-100 text-orange-600 border border-orange-200 flex-shrink-0">
                                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zm0 16a2 2 0 01-2-2h4a2 2 0 01-2 2z"/></svg>
                                                Abonelik Yok
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 uppercase tracking-wide">
                                                <?php echo htmlspecialchars($s['school_level']); ?>
                                            </span>
                                            <?php if (!empty($s['grade'])): ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-600 border border-blue-100 uppercase tracking-wide">
                                                <?php echo htmlspecialchars($s['grade'] === 'Mezun' ? 'Mezun' : $s['grade'] . '. Sınıf'); ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (!empty($s['track'])): ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-100 uppercase tracking-wide">
                                                <?php echo htmlspecialchars($s['track']); ?>
                                            </span>
                                            <?php elseif (($s['school_level'] ?? '') === 'Ortaokul'): ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-orange-50 text-orange-600 border border-orange-100 uppercase tracking-wide">LGS</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="p-5">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-slate-400 uppercase mb-0.5">Kullanıcı Adı</span>
                                    <span class="text-sm font-semibold text-slate-700 font-mono">@<?php echo htmlspecialchars($s['username']); ?></span>
                                </div>
                            </td>

                            <td class="p-5">
                                <div class="space-y-1">
                                    <?php if($s['email']): ?>
                                    <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                                        <i class="fa-regular fa-envelope text-slate-400"></i> <?php echo htmlspecialchars($s['email']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if($s['phone']): ?>
                                    <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                                        <i class="fa-solid fa-phone text-slate-400"></i> <?php echo htmlspecialchars($s['phone']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="p-5">
                                <?php if($nextApp): ?>
                                    <div class="inline-flex flex-col items-center px-4 py-2 rounded-xl border <?php echo $nextApp['class']; ?> ring-4 ring-opacity-30">
                                        <span class="text-xs font-black"><?php echo $nextApp['date']; ?></span>
                                        <span class="text-[10px] font-bold opacity-80"><?php echo $nextApp['time']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs font-bold text-slate-400 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">Planlanmadı</span>
                                <?php endif; ?>
                            </td>

                            <td class="p-5 pr-8 text-right">
                                <div class="flex justify-end gap-2 opacity-80 group-hover:opacity-100 transition-opacity">
                                    <?php
                                    $bellClass = ($pushEnabled == 0 || $hasSub == 0)
                                        ? "w-9 h-9 rounded-xl bg-red-50 border border-red-300 text-red-500 hover:bg-red-100 hover:border-red-400 hover:shadow-md transition-all flex items-center justify-center group/btn"
                                        : "w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 hover:shadow-md transition-all flex items-center justify-center group/btn";
                                    $bellTitle = ($pushEnabled == 0)
                                        ? 'Bildirimler Kapalı — Geçmişi Gör'
                                        : (($hasSub == 0) ? 'Abonelik Yok — Geçmişi Gör' : 'Bildirim Geçmişi');
                                    ?>
                                    <a href="<?php echo BASE_URL; ?>/student/bildirimler.php?student_id=<?php echo $s['id']; ?>" class="<?php echo $bellClass; ?>" title="<?php echo $bellTitle; ?>">
                                        <i class="fa-<?php echo ($pushEnabled == 0 || $hasSub == 0) ? 'solid' : 'regular'; ?> fa-bell group-hover/btn:scale-110 transition-transform"></i>
                                    </a>
                                    <button onclick='openEditModal(<?php echo $sJson; ?>)' class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50 hover:shadow-md transition-all flex items-center justify-center group/btn" title="Düzenle">
                                        <i class="fa-solid fa-pen-to-square group-hover/btn:scale-110 transition-transform"></i>
                                    </button>
                                    <button onclick="openDeleteModal(<?php echo $s['id']; ?>)" class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-rose-600 hover:border-rose-200 hover:bg-rose-50 hover:shadow-md transition-all flex items-center justify-center group/btn" title="Sil">
                                        <i class="fa-solid fa-trash-can group-hover/btn:scale-110 transition-transform"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-6 text-center">
            <p class="text-xs font-bold text-slate-300">DersPROS Öğrenci Yönetim Paneli &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>
</div>

<div id="addStudentModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-md p-4 transition-all duration-300">
    <div class="bg-white rounded-[32px] w-full max-w-lg shadow-2xl overflow-hidden transform scale-95 transition-all duration-300 animate-fadeIn" id="addModalContent">
        <div class="bg-blue-600 px-8 py-6 flex justify-between items-center relative overflow-hidden flex-shrink-0">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-10 -mt-10 blur-2xl"></div>
            <h3 class="font-bold text-white text-xl relative z-10 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-blue-200"></i> Yeni Öğrenci
            </h3>
            <button onclick="closeModal('addStudentModal')" class="text-white/60 hover:text-white hover:bg-white/10 rounded-full w-8 h-8 flex items-center justify-center transition relative z-10">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" class="overflow-y-auto max-h-[75vh]">
        <div class="p-8 space-y-5">
            <input type="hidden" name="create_student" value="1">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Ad <span class="text-red-400">*</span></label>
                    <input type="text" name="first_name" required placeholder="İsim"
                           class="js-upper w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Soyad <span class="text-red-400">*</span></label>
                    <input type="text" name="last_name" required placeholder="Soyisim"
                           class="js-upper w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Kullanıcı Adı (Giriş için) <span class="text-red-400">*</span></label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-slate-400 text-sm font-bold">@</span>
                    <input type="text" name="username" required placeholder="kullaniciadi"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-8 pr-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Şifre <span class="text-red-400">*</span></label>
                    <input type="password" name="password" required placeholder="••••••"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Seviye</label>
                    <div class="relative">
                        <select name="school_level" id="add_level_sel" onchange="syncGradeTrack('add')" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none appearance-none cursor-pointer">
                            <option value="Lise">🎓 Lise (TYT/AYT)</option>
                            <option value="Ortaokul">🎒 Ortaokul (LGS)</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
            </div>

            <!-- SINIF + ALAN -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Sınıf</label>
                    <div class="relative">
                        <select name="grade" id="add_grade_sel" onchange="syncGradeTrack('add')" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none appearance-none cursor-pointer"></select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
                <div id="add_track_wrap">
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Alan</label>
                    <div class="relative">
                        <select name="track" id="add_track_sel" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none appearance-none cursor-pointer">
                            <option value="Sayısal">🔢 Sayısal</option>
                            <option value="Eşit Ağırlık">⚖️ Eşit Ağırlık</option>
                            <option value="Sözel">📖 Sözel</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 text-xs pointer-events-none"></i>
                    </div>
                    <p id="add_track_hint" class="hidden text-[10px] text-slate-400 mt-1 ml-1"></p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">E-posta</label>
                    <input type="email" name="email"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Öğrenci Telefon</label>
                    <input type="text" name="phone"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
            </div>

            <!-- VELİ BİLGİLERİ -->
            <div class="p-4 bg-orange-50 rounded-2xl border border-orange-100">
                <div class="text-xs font-bold text-orange-800 uppercase mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-people-roof"></i> Veli Bilgileri
                    <span class="font-normal text-orange-500 normal-case">(isteğe bağlı)</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-orange-700 mb-1 ml-1">Veli Adı Soyadı</label>
                        <input type="text" name="parent_name" placeholder="Veli Adı"
                               class="js-upper w-full bg-white border border-orange-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-orange-400 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-orange-700 mb-1 ml-1">Veli Telefonu</label>
                        <input type="text" name="parent_phone" placeholder="05xx xxx xx xx"
                               class="w-full bg-white border border-orange-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-orange-400 outline-none transition-all">
                    </div>
                </div>
            </div>

            <div class="pt-1">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-xl shadow-blue-200 transition-all hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-2">
                    <span>Öğrenciyi Kaydet</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>
        </form>
    </div>
</div>

<div id="editStudentModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-md p-4 transition-all duration-300">
    <div class="bg-white rounded-[32px] w-full max-w-lg shadow-2xl overflow-hidden transform scale-95 transition-all duration-300 animate-fadeIn">
        <div class="bg-slate-800 px-8 py-6 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-white text-xl flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-slate-400"></i> Bilgileri Düzenle
            </h3>
            <button onclick="closeModal('editStudentModal')" class="text-white/60 hover:text-white hover:bg-white/10 rounded-full w-8 h-8 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" class="overflow-y-auto max-h-[75vh]">
        <div class="p-8 space-y-5">
            <input type="hidden" name="update_student" value="1">
            <input type="hidden" name="student_id" id="edit_id">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Ad</label>
                    <input type="text" name="first_name" id="edit_name" required class="js-upper w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Soyad</label>
                    <input type="text" name="last_name" id="edit_lastname" required class="js-upper w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Öğrenci Tel</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">E-posta</label>
                    <input type="email" name="email" id="edit_email" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
            </div>

            <!-- VELİ BİLGİLERİ -->
            <div class="p-4 bg-orange-50 rounded-2xl border border-orange-100">
                <div class="text-xs font-bold text-orange-800 uppercase mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-people-roof"></i> Veli Bilgileri
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-orange-700 mb-1 ml-1">Veli Adı Soyadı</label>
                        <input type="text" name="parent_name" id="edit_parent" placeholder="Veli Adı"
                               class="js-upper w-full bg-white border border-orange-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-orange-400 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-orange-700 mb-1 ml-1">Veli Telefonu</label>
                        <input type="text" name="parent_phone" id="edit_parent_phone" placeholder="05xx xxx xx xx"
                               class="w-full bg-white border border-orange-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-orange-400 outline-none transition-all">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Seviye</label>
                <div class="relative">
                    <select name="school_level" id="edit_level" onchange="syncGradeTrack('edit')" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none appearance-none">
                        <option value="Lise">🎓 Lise (TYT/AYT)</option>
                        <option value="Ortaokul">🎒 Ortaokul (LGS)</option>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 text-xs pointer-events-none"></i>
                </div>
            </div>

            <!-- SINIF + ALAN -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Sınıf</label>
                    <div class="relative">
                        <select name="grade" id="edit_grade_sel" onchange="syncGradeTrack('edit')" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none appearance-none"></select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
                <div id="edit_track_wrap">
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">Alan</label>
                    <div class="relative">
                        <select name="track" id="edit_track_sel" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none appearance-none">
                            <option value="Sayısal">🔢 Sayısal</option>
                            <option value="Eşit Ağırlık">⚖️ Eşit Ağırlık</option>
                            <option value="Sözel">📖 Sözel</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 text-xs pointer-events-none"></i>
                    </div>
                    <p id="edit_track_hint" class="hidden text-[10px] text-slate-400 mt-1 ml-1"></p>
                </div>
            </div>

            <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-4 rounded-2xl shadow-lg transition-all hover:-translate-y-1">Değişiklikleri Kaydet</button>
        </div>
        </form>
    </div>
</div>

<div id="deleteStudentModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-md p-4">
    <div class="bg-white rounded-[32px] w-full max-w-sm shadow-2xl overflow-hidden p-8 text-center animate-fadeIn">
        <div class="w-20 h-20 bg-rose-100 text-rose-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 shadow-sm">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3 class="font-extrabold text-slate-800 text-2xl mb-3">Emin misiniz?</h3>
        <p class="text-slate-500 text-sm font-medium mb-8 leading-relaxed">
            Bu öğrenciyi ve ona ait tüm verileri (program, analiz vb.) kalıcı olarak silmek üzeresiniz. <br><span class="text-rose-500 font-bold">Bu işlem geri alınamaz.</span>
        </p>
        <form method="POST">
            <input type="hidden" name="delete_student" value="1">
            <input type="hidden" name="delete_student_id" id="delete_id">
            <div class="flex gap-3">
                <button type="button" onclick="closeModal('deleteStudentModal')" class="flex-1 py-3.5 rounded-2xl font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">Vazgeç</button>
                <button type="submit" class="flex-1 py-3.5 rounded-2xl font-bold text-white bg-rose-500 hover:bg-rose-600 shadow-lg shadow-rose-200 transition transform active:scale-95">Evet, Sil</button>
            </div>
        </form>
    </div>
</div>

<!-- Öğrenci Bağla Modalı -->
<div id="linkStudentModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-md p-4 transition-all duration-300">
    <div class="bg-white rounded-[32px] w-full max-w-md shadow-2xl overflow-hidden animate-fadeIn">
        <div class="bg-gradient-to-r from-[#223488] to-[#314595] px-8 py-6 flex justify-between items-center">
            <div>
                <h3 class="font-bold text-white text-xl flex items-center gap-2">
                    <i class="fa-solid fa-link text-indigo-300"></i> Öğrenci Bağla
                </h3>
                <p class="text-indigo-200 text-xs mt-0.5">Mevcut öğrenciyi koç listenize ekleyin</p>
            </div>
            <button onclick="closeModal('linkStudentModal')" class="text-white/60 hover:text-white hover:bg-white/10 rounded-full w-8 h-8 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" class="p-8 space-y-5">
            <input type="hidden" name="link_student" value="1">
            <div>
                <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wide mb-1.5 ml-1">
                    Kullanıcı Adı veya E-posta <span class="text-red-400">*</span>
                </label>
                <input type="text" name="search_term" required placeholder="kullaniciadi veya ornek@email.com"
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-[#223488] focus:border-[#223488] outline-none transition-all">
                <p class="text-[11px] text-slate-400 mt-1.5 ml-1">Sisteme kayıtlı öğrencinin kullanıcı adı veya e-posta adresi</p>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="button" onclick="closeModal('linkStudentModal')" class="flex-1 py-3.5 rounded-2xl font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">İptal</button>
                <button type="submit" class="flex-[2] py-3.5 rounded-2xl font-bold text-white bg-[#223488] hover:bg-[#314595] shadow-lg shadow-[#223488]/20 transition active:scale-95 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-link"></i> Bağla
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // ── Sınıf/Alan senkronu ──
    // Lise: 9,10 (alan yok) · 11,12,Mezun (Sayısal/Sözel/Eşit Ağırlık)
    // Ortaokul: 5-8, alan her zaman LGS (seçilmez)
    function syncGradeTrack(prefix, keepGrade) {
        const levelSel = document.getElementById(prefix + '_level_sel') || document.getElementById(prefix + '_level');
        const gradeSel = document.getElementById(prefix + '_grade_sel');
        const trackSel = document.getElementById(prefix + '_track_sel');
        const hint     = document.getElementById(prefix + '_track_hint');
        if (!levelSel || !gradeSel || !trackSel) return;

        const level  = levelSel.value;
        const grades = (level === 'Ortaokul') ? ['5','6','7','8'] : ['9','10','11','12','Mezun'];
        const cur    = keepGrade !== undefined ? keepGrade : gradeSel.value;

        gradeSel.innerHTML = grades.map(g =>
            '<option value="' + g + '">' + (g === 'Mezun' ? '🎓 Mezun' : g + '. Sınıf') + '</option>').join('');
        gradeSel.value = grades.includes(cur) ? cur : grades[0];

        const trackOk = (level !== 'Ortaokul' && ['11','12','Mezun'].includes(gradeSel.value));
        trackSel.disabled = !trackOk;
        trackSel.closest('div').classList.toggle('opacity-40', !trackOk);
        if (hint) {
            hint.classList.toggle('hidden', trackOk);
            hint.textContent = (level === 'Ortaokul') ? 'Ortaokulda alan: LGS' : '9-10. sınıfta alan seçilmez';
        }
    }

    function openAddModal() {
        const modal = document.getElementById('addStudentModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        syncGradeTrack('add');

        // Animasyon için içeriği biraz büyüterek göster
        setTimeout(() => {
            const content = modal.querySelector('div');
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        }, 10);
    }

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_name').value = data.first_name;
        document.getElementById('edit_lastname').value = data.last_name;
        document.getElementById('edit_phone').value = data.phone || '';
        document.getElementById('edit_email').value = data.email || '';
        document.getElementById('edit_parent').value = data.parent_name || '';
        document.getElementById('edit_parent_phone').value = data.parent_phone || '';
        document.getElementById('edit_level').value = data.school_level || 'Lise';
        syncGradeTrack('edit', data.grade || '');
        const trackSel = document.getElementById('edit_track_sel');
        if (trackSel && !trackSel.disabled && data.track) trackSel.value = data.track;

        const modal = document.getElementById('editStudentModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        setTimeout(() => {
            const content = modal.querySelector('div');
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        }, 10);
    }

    function openDeleteModal(id) {
        document.getElementById('delete_id').value = id;
        const modal = document.getElementById('deleteStudentModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        const content = modal.querySelector('div');
        
        // Kapanış animasyonu
        if(content) {
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
        }

        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 200);
    }

    // Modal dışına tıklayınca kapatma
    window.onclick = function(event) {
        if (event.target.classList.contains('fixed')) {
            closeModal(event.target.id);
        }
    }
</script>

<?php include $footerPath; ?>