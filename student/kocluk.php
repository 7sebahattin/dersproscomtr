<?php
// student/kocluk.php - ÖĞRENCİ PANELİ

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';
include __DIR__ . '/../header.php';

// 1. GÜVENLİK VE OTURUM KONTROLÜ
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo "<script>window.location.href='../index.php';</script>";
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$sid = $user_id;

// Otomatik sütun migration
try {
    $chk = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'target_amount'");
    if($chk->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN target_amount INT DEFAULT NULL");

    $chk = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'correct_count'");
    if($chk->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN correct_count INT DEFAULT NULL");

    $chk = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'wrong_count'");
    if($chk->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN wrong_count INT DEFAULT NULL");
} catch (PDOException $e) {}

// Kullanıcı bilgisini al
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$sid]);
$selected_student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$selected_student) {
    session_destroy();
    echo "<script>window.location.href='../login.php';</script>";
    exit;
}

// KOÇ EŞLEŞTİRME KONTROLÜ
$check_coach = $pdo->prepare("SELECT id FROM coaching_relationships WHERE student_id = ? LIMIT 1");
$check_coach->execute([$sid]);
$has_coach = $check_coach->fetchColumn();

if (!$has_coach) {
    $whatsapp_number = "905395025214"; 
    $whatsapp_message = urlencode("Merhaba, öğrenci panelime erişemiyorum. Koç ataması için yardımcı olabilir misiniz? Öğrenci ID: " . $sid);
    ?>
    <style>body { overflow: hidden; }</style>
    <div class="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-900/60 backdrop-blur-md p-4 animate-fadeIn">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200">
            <div class="bg-red-50 p-6 text-center border-b border-red-100">
                <h2 class="text-xl font-black text-slate-800">Eşleşme Bulunamadı</h2>
            </div>
            <div class="p-6 text-center space-y-4">
                <p class="text-slate-600 font-medium text-sm">Koç ile eşleştirme yapılmamış.</p>
                <a href="https://wa.me/<?php echo $whatsapp_number; ?>?text=<?php echo $whatsapp_message; ?>" target="_blank" 
                   class="flex items-center justify-center gap-2 w-full py-3 bg-green-500 hover:bg-green-600 text-white rounded-xl font-bold transition">
                    WhatsApp İle İletişime Geç
                </a>
                <a href="../logout.php" class="text-slate-400 text-xs font-bold hover:text-slate-600 transition">Çıkış Yap</a>
            </div>
        </div>
    </div>
    <?php exit; 
}

// Öğrenci Seviyesi
$student_level = $selected_student['school_level'] ?? 'Lise';

// DEĞİŞKENLER
$message = '';
$schedule_items = [];
$exam_results = [];
$report_stats = ['total_q' => 0, 'total_t' => 0, 'week_q' => 0, 'week_t' => 0];

// 2. TARİH AYARLARI
$date_param = $_GET['date'] ?? date('Y-m-d');
$week_start = $date_param;

$prev_week  = date('Y-m-d', strtotime('-1 week', strtotime($week_start)));
$next_week  = date('Y-m-d', strtotime('+1 week', strtotime($week_start)));
$prev_day   = date('Y-m-d', strtotime('-1 day', strtotime($week_start)));
$next_day   = date('Y-m-d', strtotime('+1 day', strtotime($week_start)));
$today_date = date('Y-m-d');

$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $week_dates[] = date('Y-m-d', strtotime("+$i days", strtotime($week_start)));
}
$gunlerTR = [
    'Sunday' => 'Pazar', 'Monday' => 'Pazartesi', 'Tuesday' => 'Salı',
    'Wednesday' => 'Çarşamba', 'Thursday' => 'Perşembe', 'Friday' => 'Cuma', 'Saturday' => 'Cumartesi'
];

// 3. POST İŞLEMLERİ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. DURUM GÜNCELLEME (Geliştirilmiş Hedef/Gerçekleşen Mantığı)
    if (isset($_POST['update_status'])) {
        $sched_id    = (int)($_POST['schedule_id'] ?? 0);
        $status      = $_POST['status'] ?? 'bekliyor';
        $amount      = (int)($_POST['amount'] ?? 0);
        $correct_count = (isset($_POST['correct_count']) && $_POST['correct_count'] !== '') ? (int)$_POST['correct_count'] : null;
        $wrong_count   = (isset($_POST['wrong_count'])   && $_POST['wrong_count']   !== '') ? (int)$_POST['wrong_count']   : null;

        // 1. Önce mevcut kaydı çekelim (Hedef daha önce kilitlenmiş mi bakalım)
        $checkStmt = $pdo->prepare("SELECT amount, target_amount FROM schedule_items WHERE id = ? AND student_id = ?");
        $checkStmt->execute([$sched_id, $sid]);
        $currentItem = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($currentItem) {
            // Eğer daha önce bir hedef kopyalanmamışsa (NULL ise),
            // şu an veritabanında yazan 'amount' aslında öğretmenin koyduğu hedeftir.
            $originalTarget = $currentItem['target_amount'];
            if ($originalTarget === null || $originalTarget === '') {
                $finalTarget = $currentItem['amount'];
            } else {
                $finalTarget = $originalTarget;
            }

            // 2. Güncelleme: amount, target_amount, correct_count, wrong_count
            $upd = $pdo->prepare("UPDATE schedule_items SET status = ?, amount = ?, target_amount = ?, correct_count = ?, wrong_count = ? WHERE id = ? AND student_id = ?");
            $upd->execute([$status, $amount, $finalTarget, $correct_count, $wrong_count, $sched_id, $sid]);

            $message = "Durum ve miktar güncellendi.";
        }
    }

    // B. DENEME EKLEME
    if (isset($_POST['add_exam'])) {
        $name = trim($_POST['name'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d');
        $cat  = $_POST['category'] ?? 'TYT';

        $details = [];
        $total_net = 0;

        if (isset($_POST['lesson_name']) && is_array($_POST['lesson_name'])) {
            foreach ($_POST['lesson_name'] as $k => $lesson) {
                $lesson = trim((string)$lesson);
                $d = (float)($_POST['dogru'][$k] ?? 0);
                $y = (float)($_POST['yanlis'][$k] ?? 0);
                $n = (isset($_POST['net'][$k]) && $_POST['net'][$k] !== '')
                    ? (float)$_POST['net'][$k]
                    : ($d - ($y * 0.25));

                if ($d > 0 || $y > 0 || $n > 0) {
                    $details[$lesson] = ['d' => $d, 'y' => $y, 'n' => $n];
                    $total_net += $n;
                }
            }
        }

        $json_details = json_encode($details, JSON_UNESCAPED_UNICODE);
        $final_net = (isset($_POST['total_net']) && $_POST['total_net'] !== '')
            ? (float)$_POST['total_net']
            : (float)$total_net;

        $ins = $pdo->prepare("INSERT INTO quiz_results (student_id, exam_name, date_taken, category, total_net, details)
                              VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([$sid, $name, $date, $cat, $final_net, $json_details]);
        $message = "Deneme başarıyla kaydedildi.";
    }
}

// 4. VERİ ÇEKME
$sc = $pdo->prepare("
    SELECT si.*, t.name as topic_name, s.name as subject_name, s.category as subject_category
    FROM schedule_items si
    LEFT JOIN coaching_topics t ON si.topic_id = t.id
    LEFT JOIN coaching_subjects s ON t.subject_id = s.id
    WHERE si.student_id = ? AND si.date BETWEEN ? AND ?
");
$sc->execute([$sid, $week_dates[0], $week_dates[6]]);
$raw_items = $sc->fetchAll(PDO::FETCH_ASSOC);

foreach ($week_dates as $wd) {
    $schedule_items[$wd] = array_values(array_filter($raw_items, function ($i) use ($wd) {
        return ($i['date'] ?? '') === $wd;
    }));
}

// Denemeler
try {
    $ex = $pdo->prepare("SELECT * FROM quiz_results WHERE student_id = ? ORDER BY date_taken DESC, id DESC");
    $ex->execute([$sid]);
    $exam_results = $ex->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .form-input-visible { background-color: #ffffff; border: 1px solid #94a3b8; color: #0f172a; font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .form-input-visible:focus { border-color: #4f46e5; ring: 2px solid #4f46e5; outline: none; }
    select.form-input-visible { appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem; }
    .custom-scrollbar::-webkit-scrollbar { height: 8px; width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
    .animate-fadeIn { animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="max-w-[98%] mx-auto py-4">
    <?php if ($message): ?>
        <div class="bg-green-50 text-green-700 p-3 rounded-lg mb-3 border border-green-200 text-sm">
            ✅ <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row gap-4">
        <div class="flex-grow overflow-hidden">
            <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
                <h1 class="text-lg font-bold text-slate-800">
                    Hoşgeldin, <?php echo htmlspecialchars($selected_student['first_name'] ?? ''); ?> 👋
                </h1>
                <div class="flex bg-white p-1 rounded-xl shadow-sm border border-slate-200">
                    <button onclick="openTab('schedule')" id="tab-schedule" class="px-4 py-1.5 rounded-lg font-bold text-xs transition bg-slate-800 text-white shadow-sm flex items-center gap-2">📅 Program</button>
                    <button onclick="openTab('topics')" id="tab-topics" class="px-4 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">📊 Rapor</button>
                    <button onclick="openTab('exams')" id="tab-exams" class="px-4 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">📝 Denemeler</button>
                </div>
            </div>

            <?php include __DIR__ . '/program.php'; ?>
            <?php include __DIR__ . '/rapor.php'; ?>
            <?php include __DIR__ . '/denemeler.php'; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modals.php'; ?>
<?php include __DIR__ . '/scripts.php'; ?>
<?php include __DIR__ . '/../footer.php'; ?>