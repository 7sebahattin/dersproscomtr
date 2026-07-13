<?php
// student_dashboard.php - STREAK REPAIR (SERİ ONARIM) SİSTEMİ EKLENDİ
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. GÜVENLİK
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

require_once __DIR__ . '/db.php';

// Yeni müfredat şeması + schedule_items.edu_topic_id garanti (idempotent, eski sistemi etkilemez)
require_once __DIR__ . '/education_lib.php';
try {
    education_ensure_schema($pdo);
    if ($pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'edu_topic_id'")->rowCount() === 0) {
        $pdo->exec("ALTER TABLE schedule_items ADD COLUMN edu_topic_id INT NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE schedule_items ADD KEY idx_si_edu_topic (edu_topic_id)");
    }
} catch (Throwable $e) { /* şema hazır değilse sayfa eski alanlarla çalışmaya devam eder */ }

$student_id = $_SESSION['user_id'];
$sid = $student_id;
$student_name = $_SESSION['first_name'] ?? 'Öğrenci';

// Tarih Formatı
$monthsTR = ['Jan'=>'OCA','Feb'=>'ŞUB','Mar'=>'MART','Apr'=>'NİS','May'=>'MAY','Jun'=>'HAZ','Jul'=>'TEM','Aug'=>'AĞU','Sep'=>'EYL','Oct'=>'EKİM','Nov'=>'KAS','Dec'=>'ARA'];
$gunlerTR = ['Sunday'=>'Pazar', 'Monday'=>'Pazartesi', 'Tuesday'=>'Salı', 'Wednesday'=>'Çarşamba', 'Thursday'=>'Perşembe', 'Friday'=>'Cuma', 'Saturday'=>'Cumartesi'];

// ==========================================
// 0. VERİTABANI GÜNCELLEMESİ (OTOMATİK)
// ==========================================
try {
    // Seri onarımı için gerekli sütunları kontrol et ve yoksa ekle
    $cols = $pdo->query("SHOW COLUMNS FROM users");
    $existing_cols = $cols->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('last_repair_date', $existing_cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_repair_date DATE DEFAULT NULL");
    }
    if (!in_array('prev_streak', $existing_cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN prev_streak INT DEFAULT 0");
    }
    if (!in_array('last_broken_date', $existing_cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_broken_date DATE DEFAULT NULL");
    }
} catch (Exception $e) { /* Sessizce devam et */ }

// ==========================================
// 1. SERİ ONARIM İŞLEMİ (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair_streak'])) {
    $u = $pdo->prepare("SELECT current_streak, prev_streak, last_broken_date, freeze_count, last_repair_date FROM users WHERE id = ?");
    $u->execute([$sid]);
    $user = $u->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $last_repair = $user['last_repair_date'];
        $days_since_repair = $last_repair ? (new DateTime($last_repair))->diff(new DateTime(date('Y-m-d')))->days : 999;
        
        // Şartlar: Kalkan var mı? + 28 gün geçti mi? + Eski seri var mı?
        if ($user['freeze_count'] > 0 && $days_since_repair >= 28 && $user['prev_streak'] > 0) {
            
            // Aradaki günleri hesapla (Bugün - Bozulduğu Tarih - 1)
            $broken_date = new DateTime($user['last_broken_date']);
            $today_date = new DateTime(date('Y-m-d'));
            $gap_days = max(0, $today_date->diff($broken_date)->days - 1); // Aradaki boş günler
            
            // Yeni Seri Hesabı: Şu anki + Eski + Aradaki Günler (Kalkan boşluğu doldurur)
            $new_streak = $user['current_streak'] + $user['prev_streak'] + $gap_days;
            $new_freeze = $user['freeze_count'] - 1; // 1 Kalkan harca
            
            $upd = $pdo->prepare("UPDATE users SET current_streak = ?, prev_streak = 0, freeze_count = ?, last_repair_date = NOW() WHERE id = ?");
            $upd->execute([$new_streak, $new_freeze, $sid]);
            
            // Başarılı mesajı için yönlendir
            header("Location: student_dashboard.php?repaired=1");
            exit;
        }
    }
}

// ==========================================
// 2. STREAK (ŞİMŞEK) HESAPLAMA MOTORU
// ==========================================
function calculateStreak($pdo, $student_id) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    try {
        $u = $pdo->prepare("SELECT current_streak, max_streak, last_streak_date, freeze_count, prev_streak FROM users WHERE id = ?");
        $u->execute([$student_id]);
        $user = $u->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return ['streak'=>0, 'freeze'=>0, 'ratio'=>0, 'is_active'=>false, 'done'=>0, 'total'=>0]; }
    
    if (!$user) return ['streak'=>0, 'freeze'=>0, 'ratio'=>0, 'is_active'=>false, 'done'=>0, 'total'=>0];

    $streak = (int)($user['current_streak'] ?? 0);
    $maxStreak = (int)($user['max_streak'] ?? 0);
    $lastDate = $user['last_streak_date'];
    $freeze = (int)($user['freeze_count'] ?? 0);
    $prevStreak = (int)($user['prev_streak'] ?? 0);

    // İlerleme ve Hedef Kontrolü
    $s = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='yapildi' THEN 1 ELSE 0 END) as done FROM schedule_items WHERE student_id = ? AND date = ?");
    $s->execute([$student_id, $today]);
    $prog = $s->fetch(PDO::FETCH_ASSOC);
    
    $total = (int)$prog['total'];
    $done = (int)$prog['done'];
    
    // GÜNCELLEME: Eğer hiç görev yoksa (Tatil), başarı %100 sayılsın (Seri bozulmasın diye)
    if ($total === 0) {
        $ratio = 100;
        $goalMetToday = true; // Görev yoksa girilmiş sayılır
    } else {
        $ratio = round(($done / $total) * 100);
        $goalMetToday = ($ratio >= 60); // %60 Barajı
    }

    $dbUpdateNeeded = false;

    if ($lastDate !== $today) {
        if ($goalMetToday) {
            if ($lastDate === $yesterday) {
                // Dün girmişti, seri devam ediyor
                $streak++;
                if ($streak % 7 == 0) $freeze++; // Her 7 günde 1 kalkan
            } else {
                // GÜNCELLEME: Seri Bozuldu! (Dün girmemiş)
                // Otomatik kalkan harcamayı kaldırdık. Seri sıfırlanır, eskisi yedeklenir.
                
                // Eğer son giriş tarihi varsa ve seri 0'dan büyükse yedekle
                if ($lastDate && $streak > 0) {
                    $prevStreak = $streak; // Eski seriyi sakla
                    
                    // last_broken_date'i de güncellememiz lazım, aşağıda update sorgusunda yapacağız
                    $dbUpdateNeeded = true; 
                }
                
                // Yeni seri 1'den başlar (Bugün başardığı için)
                $streak = 1;
            }
            
            if ($streak > $maxStreak) $maxStreak = $streak;
            $dbUpdateNeeded = true;
        }
    }

    if ($dbUpdateNeeded) {
        // Eğer seri bozulduysa (bugün 1 ise ve eskiden büyükse), last_broken_date'i güncelle
        if ($streak == 1 && $prevStreak > 0 && $lastDate !== $yesterday) {
             $upd = $pdo->prepare("UPDATE users SET current_streak=?, max_streak=?, last_streak_date=?, freeze_count=?, prev_streak=?, last_broken_date=? WHERE id=?");
             $upd->execute([$streak, $maxStreak, $today, $freeze, $prevStreak, $lastDate, $student_id]);
        } else {
             // Normal güncelleme
             $upd = $pdo->prepare("UPDATE users SET current_streak=?, max_streak=?, last_streak_date=?, freeze_count=? WHERE id=?");
             $upd->execute([$streak, $maxStreak, $today, $freeze, $student_id]);
        }
    }

    return [
        'streak' => $streak,
        'freeze' => $freeze,
        'ratio' => $ratio,
        'is_active' => ($lastDate === $today), // Bugün işlendi mi?
        'done' => $done,
        'total' => $total,
        'prev_streak' => $prevStreak
    ];
}

$streakData = calculateStreak($pdo, $sid);

// ── 🎯 Sınav geri sayımı (tarih kartının altında ufak yazı) ──────────────────
require_once __DIR__ . '/app_settings_lib.php';
$examLabel = 'YKS'; $examLeft = -1; $examDateStr = '';
try {
    $lvlQ = $pdo->prepare("SELECT school_level FROM users WHERE id = ?");
    $lvlQ->execute([$sid]);
    $examLabel = (($lvlQ->fetchColumn() ?: 'Lise') === 'Ortaokul') ? 'LGS' : 'YKS';
    $examDates = exam_dates($pdo);
    $examLeft  = exam_days_left($examDates[$examLabel]);
    $examDateStr = date('d.m.Y', strtotime($examDates[$examLabel]));
} catch (Throwable $e) { $examLeft = -1; }

// ── 🏅 Rozetler — mevcut verilerden anlık hesap; tek kartta dönerek gösterilir
$badges = [];
try {
    $bq = $pdo->prepare("
        SELECT SUM(CASE WHEN action_type='soru' AND status='yapildi' THEN amount ELSE 0 END) AS soru,
               SUM(CASE WHEN action_type='konu' AND status='yapildi' THEN amount ELSE 0 END) AS konu_dk
        FROM schedule_items WHERE student_id = ?");
    $bq->execute([$sid]);
    $bs = $bq->fetch(PDO::FETCH_ASSOC) ?: [];
    $bSoru   = (int)($bs['soru'] ?? 0);
    $bKonu   = (int)($bs['konu_dk'] ?? 0);
    $bStreak = (int)$streakData['streak'];
    $bDeneme = 0;
    try { $dq = $pdo->prepare("SELECT COUNT(*) FROM quiz_results WHERE student_id = ?"); $dq->execute([$sid]); $bDeneme = (int)$dq->fetchColumn(); } catch (Throwable $e) {}

    // Tam Hafta: son TAMAMLANMIŞ haftada tüm görevler yapıldı mı?
    $twMon = date('Y-m-d', strtotime('monday last week'));
    $twSun = date('Y-m-d', strtotime($twMon . ' +6 days'));
    $tw = $pdo->prepare("SELECT COUNT(*) t, SUM(status='yapildi') d FROM schedule_items WHERE student_id = ? AND date BETWEEN ? AND ?");
    $tw->execute([$sid, $twMon, $twSun]);
    $twr = $tw->fetch(PDO::FETCH_ASSOC) ?: [];
    $fullWeek = ((int)($twr['t'] ?? 0) > 0 && (int)$twr['t'] === (int)($twr['d'] ?? 0));

    // Kademeli rozet: kazanılan en yüksek eşik + bir sonraki hedef
    $tierBadge = function (string $icon, string $name, int $val, array $tiers, string $unit) {
        $earned = null; $next = null;
        foreach ($tiers as $t) { if ($val >= $t) $earned = $t; elseif ($next === null) $next = $t; }
        $on = $earned !== null;
        return [
            'icon'  => $icon,
            'on'    => $on,
            'label' => $name . ' ' . number_format($on ? $earned : $next),
            'sub'   => $on ? number_format($val) . ' ' . $unit
                           : number_format($next - $val) . ' ' . $unit . ' kaldı',
        ];
    };
    $badges[] = $tierBadge('💯', 'Soru',   $bSoru,   [100, 1000, 5000, 10000], 'soru');
    $badges[] = $tierBadge('📚', 'Konu',   $bKonu,   [300, 1000, 3000],        'dk');
    $badges[] = $tierBadge('🔥', 'Seri',   $bStreak, [3, 7, 30],               'gün');
    $badges[] = $tierBadge('📝', 'Deneme', $bDeneme, [5, 15, 30],              'deneme');
    $badges[] = ['icon' => '🏆', 'on' => $fullWeek, 'label' => 'Tam Hafta',
                 'sub' => $fullWeek ? 'geçen hafta %100!' : 'haftayı %100 bitir'];
} catch (Throwable $e) { $badges = []; }

// ==========================================
// 3. KARŞILAMA MESAJI MANTIĞI
// ==========================================
$welcome_title = "";
$welcome_msg = "";
$welcome_icon = "";

$total_tasks = $streakData['total'];
$done_tasks = $streakData['done'];
$ratio = $streakData['ratio'];
$target_60_percent = ceil($total_tasks * 0.6); 
$needed_for_streak = max(0, $target_60_percent - $done_tasks);
$remaining_total = max(0, $total_tasks - $done_tasks);

if (isset($_GET['repaired'])) {
    $welcome_title = "Seri Kurtarıldı! 🛡️";
    $welcome_msg = "Harika hamle! Kalkanını kullanarak serini birleştirdin. Şimdi bu momentumla devam et!";
    $welcome_icon = "🔥";
} elseif ($total_tasks == 0) {
    $welcome_title = "Planın Hazır Değil 😴";
    $welcome_msg = "Bugün için henüz bir program görünmüyor. Ancak giriş yaptığın için serin bozulmayacak!";
    $welcome_icon = "📅";
} elseif ($ratio < 60) {
    $welcome_title = "Serini Ateşle! 🔥";
    $welcome_msg = "Bugünkü hedeflerinin henüz başındasın. Serini korumak için <strong>en az $needed_for_streak görev</strong> daha tamamlaman gerekiyor.";
    $welcome_icon = "💪";
} else {
    if ($remaining_total > 0) {
        $welcome_title = "Seri Güvende! 🛡️";
        $welcome_msg = "Harika! %60 barajını geçtin ve serini korudun. Kalan %40'lık kısmı da halledelim mi?";
        $welcome_icon = "🚀";
    } else {
        $welcome_title = "Muhteşem Bir Gün! 🌟";
        $welcome_msg = "Bugünkü tüm görevlerini eksiksiz tamamladın. Yarın görüşmek üzere!";
        $welcome_icon = "🎉";
    }
}

// ==========================================
// 4. MEVCUT DASHBOARD VERİLERİ
// ==========================================
// (Buradaki kodlar orijinal dosyadakiyle aynı kalacak, sadece include yolları düzeltildi)
$date_param = $_GET['date'] ?? date('Y-m-d');
$week_start = $date_param; 
$week_dates = [];
for ($i = 0; $i < 7; $i++) { $week_dates[] = date('Y-m-d', strtotime("+$i days", strtotime($week_start))); }
$prev_week = date('Y-m-d', strtotime('-1 week', strtotime($week_start)));
$next_week = date('Y-m-d', strtotime('+1 week', strtotime($week_start)));
$prev_day = date('Y-m-d', strtotime('-1 day', strtotime($week_start)));
$next_day = date('Y-m-d', strtotime('+1 day', strtotime($week_start)));
$today_date = date('Y-m-d');

try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT si.*, t.name as topic_name, s.name as subject_name, s.category as subject_category, et.topic_name AS edu_topic_name, es.lesson_name AS edu_subject_name, ec.name AS edu_category_name FROM schedule_items si LEFT JOIN education_topics et ON si.edu_topic_id = et.id LEFT JOIN education_subjects es ON et.subject_id = es.id LEFT JOIN education_categories ec ON es.category_id = ec.id LEFT JOIN coaching_topics t ON si.topic_id = t.id LEFT JOIN coaching_subjects s ON t.subject_id = s.id WHERE si.student_id = ? AND si.date = ?");
    $stmt->execute([$student_id, $today]);
    $todays_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dash_total_tasks = count($todays_tasks); 
    $dash_completed_tasks = 0;
    foreach($todays_tasks as $t) { if($t['status'] == 'yapildi') $dash_completed_tasks++; }
    $progress = $dash_total_tasks > 0 ? round(($dash_completed_tasks / $dash_total_tasks) * 100) : 0;

    $stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name FROM appointments a JOIN users u ON a.teacher_id = u.id WHERE a.student_id = ? AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= NOW() AND (a.status IS NULL OR a.status != 'cancelled') ORDER BY a.appointment_date ASC LIMIT 1");
    $stmt->execute([$student_id]);
    $next_app = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name FROM appointments a JOIN users u ON a.teacher_id = u.id WHERE a.student_id = ? AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= NOW() AND (a.status IS NULL OR a.status != 'cancelled') ORDER BY a.appointment_date ASC LIMIT 5");
    $stmt->execute([$student_id]);
    $upcoming_apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM quiz_results WHERE student_id = ? ORDER BY date_taken DESC LIMIT 1");
    $stmt->execute([$student_id]);
    $last_exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $unread_msg_count = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointment_messages m JOIN appointments a ON a.id = m.appointment_id WHERE a.student_id = ? AND m.sender_role = 'teacher' AND m.is_read_by_student = 0");
        $stmt->execute([$student_id]);
        $unread_msg_count += $stmt->fetchColumn();
    } catch(Exception $e) {}

    $start_week = date('Y-m-d', strtotime('monday this week'));
    $end_week = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM schedule_items WHERE student_id = ? AND action_type = 'soru' AND date BETWEEN ? AND ?");
    $stmt->execute([$student_id, $start_week, $end_week]);
    $week_q_planned = $stmt->fetchColumn() ?: 0;
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM schedule_items WHERE student_id = ? AND action_type = 'soru' AND status = 'yapildi' AND date BETWEEN ? AND ?");
    $stmt->execute([$student_id, $start_week, $end_week]);
    $week_q_done = $stmt->fetchColumn() ?: 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM schedule_items WHERE student_id = ? AND action_type = 'konu' AND date BETWEEN ? AND ?");
    $stmt->execute([$student_id, $start_week, $end_week]);
    $week_t_planned = $stmt->fetchColumn() ?: 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM schedule_items WHERE student_id = ? AND action_type = 'konu' AND status = 'yapildi' AND date BETWEEN ? AND ?");
    $stmt->execute([$student_id, $start_week, $end_week]);
    $week_t_done = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) {
    $dash_total_tasks=0; $dash_completed_tasks=0; $progress=0; $next_app=null; $last_exam=null; $unread_msg_count=0; $week_q_planned=0; $week_q_done=0; $week_t_planned=0; $week_t_done=0;
}

// PROGRAM VERİSİ
$schedule_items = []; 
$sc = $pdo->prepare("SELECT si.*, t.name as topic_name, s.name as subject_name, s.category as subject_category, et.topic_name AS edu_topic_name, es.lesson_name AS edu_subject_name, ec.name AS edu_category_name FROM schedule_items si LEFT JOIN education_topics et ON si.edu_topic_id = et.id LEFT JOIN education_subjects es ON et.subject_id = es.id LEFT JOIN education_categories ec ON es.category_id = ec.id LEFT JOIN coaching_topics t ON si.topic_id = t.id LEFT JOIN coaching_subjects s ON t.subject_id = s.id WHERE si.student_id = ? AND si.date BETWEEN ? AND ?");
$sc->execute([$sid, $week_dates[0], $week_dates[6]]);
$raw_items = $sc->fetchAll(PDO::FETCH_ASSOC);
foreach ($week_dates as $wd) { $schedule_items[$wd] = array_values(array_filter($raw_items, function ($i) use ($wd) { return ($i['date'] ?? '') === $wd; })); }

try {
    $ex = $pdo->prepare("SELECT * FROM quiz_results WHERE student_id = ? ORDER BY date_taken DESC, id DESC");
    $ex->execute([$sid]);
    $exam_results = $ex->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

include __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<style>
    :root { --atla-blue-dark: #223488; --atla-blue-light: #314595; --atla-orange: #ec9731; --atla-orange-hover: #d68625; }
    body { background-color: #f8fafc; font-family: 'Poppins', sans-serif; }
    .dash-card { transition: all 0.3s ease; border: 1px solid #f1f5f9; }
    .dash-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); border-color: #e2e8f0; }
    .glass-banner { background: linear-gradient(120deg, var(--atla-blue-dark) 0%, var(--atla-blue-light) 100%); position: relative; overflow: visible; }
    @keyframes loadProgress { from { width: 0; } to { width: var(--value); } }
    .progress-fill { animation: loadProgress 1.5s ease-out forwards; }
    @keyframes lightning-pulse {
        0%, 100% { filter: drop-shadow(0 0 4px rgba(253,224,71,0.6)); transform: scale(1); }
        50%       { filter: drop-shadow(0 0 18px rgba(253,224,71,1)) drop-shadow(0 0 32px rgba(251,146,60,0.5)); transform: scale(1.15); }
    }
    @keyframes halo-ring {
        0%, 100% { box-shadow: 0 0 0 0 rgba(250,204,21,0.5), 0 0 12px 2px rgba(250,204,21,0.2); }
        50%       { box-shadow: 0 0 0 6px rgba(250,204,21,0), 0 0 24px 6px rgba(251,146,60,0.3); }
    }
    @keyframes countUp { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }
    .lightning-active   { animation: lightning-pulse 2s ease-in-out infinite; color: #facc15; }
    .lightning-inactive { color: #94a3b8; opacity: 0.5; filter: grayscale(100%); }
    .streak-halo-active { animation: halo-ring 2s ease-in-out infinite; }
    .modal-enter { animation: modalPop 0.3s ease-out forwards; }
    @keyframes modalPop { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    .streak-count-anim { animation: countUp 0.4s ease-out forwards; }
    /* 🏅 Dönen rozet: içerik yumuşak geçişle değişir */
    .rotb-body { transition: opacity .25s ease, transform .25s ease; }
    .rotb-fade .rotb-body { opacity: 0; transform: translateY(4px); }
</style>

<div class="min-h-screen pb-12">
    <div class="glass-banner pt-8 pb-12 px-6 lg:px-12 shadow-lg">
        <div class="absolute top-0 right-0 opacity-10 transform translate-x-10 -translate-y-5"><i class="fa-solid fa-graduation-cap text-9xl text-white"></i></div>
        <div class="absolute bottom-0 left-0 opacity-5 transform -translate-x-5 translate-y-5"><i class="fa-solid fa-book-open text-8xl text-white"></i></div>
        <?php
        $sv = $streakData['streak'];
        $streak_title = match(true) {
            $sv >= 100 => '🏆 Şampiyon',
            $sv >= 60  => '🌟 Efsane',
            $sv >= 30  => '⚡ Şimşek',
            $sv >= 21  => '📚 Bilgili',
            $sv >= 14  => '🎯 Tecrübeli',
            $sv >= 1   => '🌱 Çaylak',
            default    => '—',
        };
        $milestone_progress = $sv > 0 ? ($sv % 7 === 0 ? 7 : $sv % 7) : 0;
        $ring_dash = round(($milestone_progress / 7) * 113.1); // 2π×18 ≈ 113.1
    ?>
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-start md:items-center relative z-10 gap-4">
            <div class="w-full md:w-auto flex justify-between items-start md:block">
                <div>
                    <p class="text-blue-200 text-[10px] font-bold uppercase tracking-widest mb-1">DersPROS Kontrol Paneli</p>
                    <h1 class="text-2xl md:text-4xl font-extrabold text-white">Selam, <span class="text-[#ec9731]"><?php echo htmlspecialchars($student_name); ?></span> 👋</h1>
                    <p class="text-blue-100/90 text-xs md:text-sm mt-1 max-w-sm">Hoşgeldin! Çalışmalarına göz atalım.</p>
                    <?php if ($examLeft >= 0): ?>
                    <p class="md:hidden text-[11px] font-bold text-[#ec9731] mt-1" title="<?php echo $examLabel; ?> tarihi: <?php echo $examDateStr; ?>">🎯 <?php echo $examLabel; ?>'ye <?php echo $examLeft; ?> gün</p>
                    <?php endif; ?>
                </div>
                <div class="md:hidden flex items-start gap-2 ml-2">
                    <div onclick="openStreakModal()" class="flex flex-col items-center bg-white/10 backdrop-blur-md rounded-xl px-3 py-2 border border-white/20 cursor-pointer active:scale-95 transition relative overflow-hidden <?php echo $streakData['is_active'] ? 'streak-halo-active' : ''; ?>">
                        <?php if($streakData['is_active'] && $sv >= 7): ?><div class="absolute inset-0 bg-yellow-400/10 rounded-xl"></div><?php endif; ?>
                        <span class="text-3xl relative z-10 <?php echo $streakData['is_active'] ? 'lightning-active' : 'lightning-inactive'; ?>">⚡</span>
                        <span class="text-base font-black text-white relative z-10 leading-tight"><?php echo $sv; ?></span>
                        <span class="text-[9px] font-bold uppercase tracking-wider relative z-10 <?php echo $streakData['is_active'] ? 'text-yellow-300' : 'text-slate-400'; ?>">gün</span>
                    </div>
                    <?php if (!empty($badges)): ?>
                    <!-- 🏅 Dönen rozet (mobil mini) -->
                    <div class="rotb-wrap flex flex-col items-center bg-white/10 backdrop-blur-md rounded-xl px-2.5 py-2 border border-white/20 w-[84px] overflow-hidden" title="Rozetlerim">
                        <div class="rotb-body flex flex-col items-center w-full">
                            <span class="rotb-icon text-2xl leading-none">🏅</span>
                            <span class="rotb-label block w-full truncate text-[10px] font-black text-white leading-tight text-center mt-1"></span>
                            <span class="rotb-sub block w-full truncate text-[8px] font-bold text-blue-200 leading-tight text-center"></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hidden md:flex items-center gap-4">
                <div onclick="openStreakModal()" class="cursor-pointer group flex items-center gap-3 bg-white/10 backdrop-blur-md rounded-2xl p-2 pr-5 border border-white/20 transition hover:bg-white/20 hover:scale-105 relative overflow-hidden <?php echo ($streakData['is_active'] && $sv >= 7) ? 'streak-halo-active' : ''; ?>">
                    <?php if($streakData['is_active'] && $sv >= 7): ?><div class="absolute inset-0 bg-yellow-400/8 rounded-2xl pointer-events-none"></div><?php endif; ?>
                    <!-- Progress Ring -->
                    <div class="relative w-14 h-14 flex items-center justify-center flex-shrink-0">
                        <svg class="absolute inset-0 w-14 h-14 -rotate-90" viewBox="0 0 44 44">
                            <circle cx="22" cy="22" r="18" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="3"/>
                            <?php if($streakData['is_active'] && $sv > 0): ?>
                            <circle cx="22" cy="22" r="18" fill="none" stroke="#facc15" stroke-width="3"
                                stroke-dasharray="<?php echo $ring_dash; ?> 113.1" stroke-linecap="round"/>
                            <?php endif; ?>
                        </svg>
                        <span class="text-3xl relative z-10 <?php echo $streakData['is_active'] ? 'lightning-active' : 'lightning-inactive'; ?>">⚡</span>
                        <?php if($streakData['freeze'] > 0): ?>
                        <div class="absolute -top-1 -right-1 bg-blue-500 text-white text-[9px] font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white shadow-sm">🛡️</div>
                        <?php endif; ?>
                    </div>
                    <!-- Sayı + Unvan -->
                    <div class="text-right relative z-10">
                        <div class="text-3xl font-black text-white leading-none"><?php echo $sv; ?></div>
                        <div class="text-[10px] font-bold <?php echo $streakData['is_active'] ? 'text-yellow-300' : 'text-blue-200'; ?> uppercase tracking-wider"><?php echo $streak_title; ?></div>
                    </div>
                </div>
                <?php if (!empty($badges)): ?>
                <!-- 🏅 Dönen rozet: belli aralıklarla sıradaki rozete geçer -->
                <div class="rotb-wrap bg-white/10 backdrop-blur-md border border-white/20 p-2 rounded-2xl flex items-center gap-3 pr-5 min-w-[185px] max-w-[220px] overflow-hidden" title="Rozetlerim">
                    <div class="rotb-body flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center shrink-0">
                            <span class="rotb-icon text-2xl leading-none">🏅</span>
                        </div>
                        <div class="text-left min-w-0">
                            <div class="rotb-label truncate font-black leading-tight text-white text-sm"></div>
                            <div class="rotb-sub truncate text-[10px] font-semibold text-blue-200"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php
                    $sdDays = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
                    $sdM = date('M');
                ?>
                <div class="bg-white/10 backdrop-blur-md border border-white/20 p-2 rounded-2xl flex items-center gap-3 pr-5">
                    <div class="bg-white text-[#223488] w-10 h-10 rounded-xl flex items-center justify-center font-bold text-lg shrink-0">
                        <?php echo date('d'); ?>
                    </div>
                    <div class="text-left">
                        <div class="text-xs text-blue-200 uppercase font-bold"><?php echo $sdDays[date('w')]; ?></div>
                        <div class="font-bold leading-none text-white"><?php echo ($monthsTR[$sdM] ?? $sdM) . ' ' . date('Y'); ?></div>
                        <?php if ($examLeft >= 0): ?>
                        <!-- 🎯 Sınav geri sayımı — tarihin hemen altında ufak yazı -->
                        <div class="text-[10px] font-bold text-[#ec9731] mt-1" title="<?php echo $examLabel; ?> tarihi: <?php echo $examDateStr; ?>">🎯 <?php echo $examLabel; ?>'ye <?php echo $examLeft; ?> gün</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 relative z-20">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-[#ec9731]"></i>
                            <div>
                                <h2 class="text-sm font-bold text-white uppercase tracking-wider">Bugünün Planı</h2>
                                <p class="text-blue-300 text-[10px] mt-0.5">Hemen Başla!</p>
                            </div>
                        </div>
                        <a href="student/kocluk.php" class="text-[10px] font-bold text-blue-200 hover:text-white transition whitespace-nowrap">Tüm Program →</a>
                    </div>
                    <div class="p-6">
                    <div class="mb-6">
                        <div class="flex justify-between items-end mb-2"><span class="text-xs font-bold text-[#223488] uppercase tracking-wide">Günlük Tamamlanma</span><span class="text-sm font-black text-[#ec9731]">%<?php echo $progress; ?></span></div>
                        <div class="w-full h-3 bg-blue-50 rounded-full overflow-hidden border border-blue-100/50"><div class="h-full bg-gradient-to-r from-[#ec9731] to-[#d68625] progress-fill" style="--value: <?php echo $progress; ?>%"></div></div>
                    </div>
                    <div class="space-y-3">
                        <?php if(empty($todays_tasks)): ?>
                            <div class="text-center py-10 bg-blue-50/50 rounded-2xl border border-dashed border-blue-100">
                                <div class="text-3xl mb-2">⚡</div>
                                <p class="text-slate-700 font-bold text-sm">Şimşek serin devam ediyor!</p>
                                <p class="text-slate-500 text-xs mt-1">Program için koçunla iletişime geç.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($todays_tasks as $task): 
                                $isDone = $task['status'] == 'yapildi';
                                $icon = $task['action_type'] == 'soru' ? 'fa-pen' : 'fa-book-open';
                                $title = $task['edu_topic_name'] ?? $task['topic_name'] ?? $task['custom_topic'] ?? ($task['edu_subject_name'] ?? $task['custom_subject'] ?? 'Genel Çalışma');
                                $desc = $task['action_type'] == 'soru' ? $task['amount'] . " Soru Çözümü" : "Konu Anlatımı / Tekrar";
                            ?>
                            <?php if(!$isDone): ?>
                            <a href="<?php echo BASE_URL; ?>/student/kocluk.php" class="group flex items-center gap-4 p-4 rounded-2xl border transition-all duration-300 bg-white border-slate-100 hover:border-[#223488]/30 hover:shadow-md cursor-pointer">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-lg shrink-0 transition-colors bg-indigo-50 text-[#223488] group-hover:bg-[#223488] group-hover:text-white"><i class="fa-solid <?php echo $icon; ?>"></i></div>
                                <div class="flex-grow min-w-0"><h4 class="text-sm font-bold text-slate-700 truncate"><?php echo htmlspecialchars($title); ?></h4><p class="text-[11px] text-slate-500 font-medium uppercase tracking-wide mt-0.5"><?php echo $desc; ?></p></div>
                                <div class="shrink-0"><i class="fa-solid fa-chevron-right text-xs text-slate-300 group-hover:text-[#ec9731]"></i></div>
                            </a>
                            <?php else: ?>
                            <div class="flex items-center gap-4 p-4 rounded-2xl border bg-slate-50 border-slate-100 opacity-75">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-lg shrink-0 bg-green-100 text-green-600"><i class="fa-solid fa-check"></i></div>
                                <div class="flex-grow min-w-0"><h4 class="text-sm font-bold text-slate-500 truncate line-through decoration-2 decoration-slate-300"><?php echo htmlspecialchars($title); ?></h4><p class="text-[11px] text-slate-400 font-medium uppercase tracking-wide mt-0.5"><?php echo $desc; ?></p></div>
                                <div class="shrink-0"><span class="px-3 py-1 bg-green-50 text-green-600 text-[10px] font-bold rounded-lg border border-green-100">YAPILDI</span></div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>
            <div>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-6 py-4 flex items-center gap-2">
                        <i class="fa-solid fa-bullseye text-[#ec9731]"></i>
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider">Bu Haftanın Hedefleri</h3>
                    </div>
                    <div class="p-6">
                    <div class="mb-6">
                        <div class="flex justify-between items-end mb-2"><span class="text-xs font-bold text-slate-500 uppercase">Soru Çözümü</span><div class="text-right"><span class="text-xl font-black text-[#223488]"><?php echo number_format($week_q_done); ?></span><span class="text-xs text-slate-400 font-bold">/ <?php echo number_format($week_q_planned); ?></span></div></div>
                        <?php $q_percent = ($week_q_planned > 0) ? min(100, round(($week_q_done / $week_q_planned) * 100)) : 0; ?>
                        <div class="w-full bg-blue-50 rounded-full h-3 overflow-hidden border border-blue-100/50"><div class="bg-[#223488] h-3 rounded-full transition-all duration-1000" style="width: <?php echo $q_percent; ?>%"></div></div>
                    </div>
                    <div>
                        <div class="flex justify-between items-end mb-2"><span class="text-xs font-bold text-slate-500 uppercase">Konu Çalışması</span><div class="text-right"><span class="text-xl font-black text-[#ec9731]"><?php echo $week_t_done; ?></span><span class="text-xs text-slate-400 font-bold">/ <?php echo $week_t_planned; ?></span></div></div>
                        <?php $t_percent = ($week_t_planned > 0) ? min(100, round(($week_t_done / $week_t_planned) * 100)) : 0; ?>
                        <div class="w-full bg-blue-50 rounded-full h-3 overflow-hidden border border-blue-100/50"><div class="bg-[#ec9731] h-3 rounded-full transition-all duration-1000" style="width: <?php echo $t_percent; ?>%"></div></div>
                    </div>
                    </div>
                </div>
            </div>

            <!-- SIRADAKI RANDEVULAR -->
            <div>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-calendar-check text-[#ec9731]"></i>
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Sıradaki Randevular</h3>
                        </div>
                        <a href="<?php echo BASE_URL; ?>/student/randevu.php" class="text-[10px] font-bold text-blue-200 hover:text-white transition">Tümü →</a>
                    </div>
                    <div class="p-4">
                        <?php if(empty($upcoming_apps)): ?>
                        <div class="text-center py-6 text-slate-400 text-sm">
                            <i class="fa-regular fa-calendar text-2xl mb-2 block text-slate-300"></i>
                            Yaklaşan randevu yok.
                        </div>
                        <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach($upcoming_apps as $app):
                                $daysTR = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
                                $dow = $daysTR[date('w', strtotime($app['appointment_date']))];
                                $isToday = ($app['appointment_date'] === date('Y-m-d'));
                            ?>
                            <div class="flex items-center gap-3 p-3 rounded-xl <?php echo $isToday ? 'bg-indigo-50 border border-indigo-100' : 'bg-slate-50'; ?>">
                                <div class="w-10 h-10 rounded-xl <?php echo $isToday ? 'bg-[#223488] text-white' : 'bg-white text-slate-600 border border-slate-200'; ?> flex flex-col items-center justify-center shrink-0">
                                    <span class="text-[10px] font-bold leading-none"><?php echo date('d', strtotime($app['appointment_date'])); ?></span>
                                    <span class="text-[8px] uppercase"><?php echo substr($dow, 0, 3); ?></span>
                                </div>
                                <div class="flex-grow min-w-0">
                                    <p class="text-xs font-bold text-slate-700 truncate"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></p>
                                    <p class="text-[10px] text-slate-400"><?php echo date('H:i', strtotime($app['appointment_time'])); ?> <?php echo $isToday ? '· <span class="text-indigo-600 font-bold">Bugün</span>' : ''; ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div id="streakDetailsModal" class="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4 hidden transition-opacity duration-300">
    <div class="bg-white rounded-3xl w-full max-w-sm max-h-[88vh] shadow-2xl border border-slate-200 overflow-y-auto overflow-x-hidden relative transform scale-100 modal-enter">
        <div class="relative overflow-hidden" style="background: linear-gradient(135deg, #1a1a2e 0%, #223488 60%, #2d3a8c 100%);">
            <!-- Arka plan parıltı efekti (pointer-events kapalı — butona engel olmasın) -->
            <?php if($streakData['is_active']): ?>
            <div class="absolute inset-0 opacity-20 pointer-events-none" style="background: radial-gradient(ellipse at 50% 0%, rgba(250,204,21,0.8) 0%, transparent 65%);"></div>
            <?php endif; ?>
            <button onclick="document.getElementById('streakDetailsModal').classList.add('hidden'); document.getElementById('streakInfoPopup').classList.add('hidden');"
                    class="absolute top-3 right-3 z-20 w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/40 text-white text-sm font-bold transition active:scale-90">✕</button>
            <div class="relative z-10 pt-8 pb-6 px-6 text-center">
                <!-- Şimşek hale çemberi -->
                <div class="inline-flex items-center justify-center relative mb-4">
                    <div class="w-28 h-28 rounded-full flex items-center justify-center border-2 <?php echo $streakData['is_active'] ? 'border-yellow-400/40 bg-yellow-400/10' : 'border-white/10 bg-white/5'; ?> <?php echo ($streakData['is_active'] && $sv >= 7) ? 'streak-halo-active' : ''; ?>">
                        <span class="text-7xl select-none <?php echo $streakData['is_active'] ? 'lightning-active' : 'grayscale opacity-40'; ?>">⚡</span>
                    </div>
                    <!-- Progress ring -->
                    <?php if($streakData['is_active'] && $sv > 0): ?>
                    <svg class="absolute inset-0 w-28 h-28 -rotate-90" viewBox="0 0 112 112">
                        <circle cx="56" cy="56" r="50" fill="none" stroke="rgba(250,204,21,0.15)" stroke-width="4"/>
                        <circle cx="56" cy="56" r="50" fill="none" stroke="#facc15" stroke-width="4"
                            stroke-dasharray="<?php echo round(($milestone_progress / 7) * 314.2); ?> 314.2"
                            stroke-linecap="round" style="filter: drop-shadow(0 0 4px rgba(250,204,21,0.8));"/>
                    </svg>
                    <?php endif; ?>
                </div>
                <!-- Sayaç (animasyonlu) -->
                <h2 class="text-5xl font-black text-white tracking-tight leading-none">
                    <span id="streakCounter" data-target="<?php echo $sv; ?>">0</span> GÜN
                </h2>
                <!-- Unvan -->
                <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-widest <?php echo $streakData['is_active'] ? 'bg-yellow-400/20 text-yellow-300 border border-yellow-400/30' : 'bg-white/10 text-slate-300 border border-white/10'; ?>">
                    <?php echo $streak_title; ?>
                </div>
                <!-- Milestone alt bilgi -->
                <?php if($streakData['is_active'] && $sv > 0): ?>
                <p class="text-[10px] text-blue-200/70 mt-3">
                    Sonraki milestone'a <strong class="text-yellow-300"><?php echo 7 - $milestone_progress; ?> gün</strong> kaldı
                </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="p-6 space-y-6">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                <div class="flex justify-between items-end mb-2">
                    <span class="text-xs font-bold text-slate-500 uppercase">Bugünün Hedefi</span>
                    <span class="text-xs font-black <?php echo $streakData['ratio'] >= 60 ? 'text-green-500' : 'text-[#ec9731]'; ?>">%<?php echo $streakData['ratio']; ?> </span>
                </div>
                <div class="w-full bg-slate-200 h-3 rounded-full overflow-hidden">
                    <div class="h-full transition-all duration-1000 bg-[#ec9731]" style="width: <?php echo min(100, ($streakData['ratio'] / 60) * 100); ?>%"></div>
                </div>
                <p class="text-[10px] text-slate-400 mt-2 text-center">
                    <?php if($streakData['ratio'] >= 60): ?>🎉 Tebrikler! Serin güvende.<?php else: ?>⚠️ Serini korumak için en az %60 başarı sağla!<?php endif; ?>
                </p>
            </div>
            
            <div class="flex items-center gap-4 bg-blue-50 border border-blue-100 p-4 rounded-xl">
                <div class="text-3xl">🛡️</div>
                <div>
                    <div class="text-sm font-black text-[#223488] uppercase"><?php echo $streakData['freeze']; ?> KALKAN HAKKI</div>
                    <div class="text-[10px] text-blue-600/80 font-medium leading-tight mt-0.5">Her 7 günlük seride +1 kalkan kazanırsın.</div>
                </div>
            </div>

            <?php 
                // SERİ ONARIM KONTROLÜ
                $can_repair = false;
                $days_since_last_repair = 999;
                
                // Veritabanından tarih bilgisini al (calculateStreak fonksiyonundan gelen veride olmayabilir, tekrar sorgulayalım)
                // Performans için yukarıda zaten çekiyoruz ama burada $user değişkenine erişemiyoruz.
                // Basitlik adına streakData'ya ekleyebilirdik ama burada tekil sorgu yapalım
                try {
                    $u_rep = $pdo->prepare("SELECT last_repair_date, prev_streak FROM users WHERE id = ?");
                    $u_rep->execute([$sid]);
                    $user_rep = $u_rep->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user_rep['last_repair_date']) {
                        $days_since_last_repair = (new DateTime($user_rep['last_repair_date']))->diff(new DateTime(date('Y-m-d')))->days;
                    }
                    
                    if ($streakData['freeze'] > 0 && $days_since_last_repair >= 28 && $user_rep['prev_streak'] > 0) {
                        $can_repair = true;
                    }
                } catch(Exception $e){}
            ?>

            <?php if ($can_repair): ?>
            <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 animate-pulse">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-bold text-orange-700 text-sm flex items-center gap-2"><i class="fa-solid fa-heart-crack"></i> Serin Bölündü!</h4>
                    <span class="text-[10px] bg-white px-2 py-1 rounded border border-orange-100 text-orange-600 font-bold">Eski: <?php echo $streakData['prev_streak']; ?> Gün</span>
                </div>
                <p class="text-xs text-orange-600 mb-3">Geçmiş boşluğunu doldurmak ve serini birleştirmek için kalkanını kullanabilirsin. <span class="text-[9px] opacity-75">(4 haftada 1 kez)</span></p>
                <form method="POST">
                    <input type="hidden" name="repair_streak" value="1">
                    <button type="submit" class="w-full py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-xs font-bold shadow-md transition transform active:scale-95 flex items-center justify-center gap-2">
                        <span>🛡️</span> 1 Kalkan Harca ve Onar
                    </button>
                </form>
            </div>
            <?php elseif ($streakData['prev_streak'] > 0): ?>
            <div class="mt-4 text-center">
                <p class="text-[10px] text-slate-400">Onarım Hakkı: <strong><?php echo max(0, 28 - $days_since_last_repair); ?> gün sonra</strong></p>
            </div>
            <?php endif; ?>

            <!-- Nasıl Çalışır Butonu -->
            <div class="mt-5 pt-4 border-t border-white/10 text-center">
                <button onclick="document.getElementById('streakInfoPopup').classList.toggle('hidden')"
                        class="inline-flex items-center gap-1.5 text-[11px] text-slate-400 hover:text-slate-600 transition font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path stroke-linecap="round" d="M12 8v4m0 4h.01" stroke-width="2.5"/>
                    </svg>
                    Şimşek Serisi nasıl çalışır?
                </button>

                <div id="streakInfoPopup" class="hidden mt-3 bg-slate-50 border border-slate-200 rounded-2xl p-4 text-left text-[11px] text-slate-600 space-y-2.5">
                    <div class="flex gap-2">
                        <span class="text-base leading-none mt-0.5">⚡</span>
                        <div><span class="font-bold text-slate-800">Seri nedir?</span> Her gün görevlerini tamamladığında serin 1 artar. Bir gün atlayarsan serin sıfırlanır.</div>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-base leading-none mt-0.5">🎯</span>
                        <div><span class="font-bold text-slate-800">Bugünün Hedefi:</span> Günlük görev tamamlama oranın %75'in üstüne çıkınca serin güvende olur.</div>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-base leading-none mt-0.5">🛡️</span>
                        <div><span class="font-bold text-slate-800">Kalkan Hakkı:</span> Her <strong>7 günlük</strong> kesintisiz seride +1 kalkan kazanırsın. Kalkan, kırılan serini onarmak için kullanılır (4 haftada 1 kez).</div>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-base leading-none mt-0.5">🏆</span>
                        <div><span class="font-bold text-slate-800">Rozetler:</span>
                            <span class="text-slate-500"> 7 gün → Çaylak · 14 gün → Tecrübeli · 21 gün → Bilgili · 30 gün → Şimşek · 60 gün → Efsane · 100 gün → Şampiyon</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-base leading-none mt-0.5">🔔</span>
                        <div><span class="font-bold text-slate-800">Bildirimler:</span> Görevi eksik bırakırsan seni hatırlatmak için push bildirimi gönderilir.</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div id="dailyWelcomeModal" class="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-900/90 backdrop-blur-md p-4 hidden transition-opacity duration-300">
    <div class="bg-white rounded-3xl w-full max-w-sm shadow-2xl overflow-hidden relative modal-enter text-center p-8 border border-slate-200">
        <div class="mb-5 inline-flex items-center justify-center w-20 h-20 rounded-full bg-blue-50 text-5xl shadow-sm"><?php echo $welcome_icon; ?></div>
        <h2 class="text-2xl font-black text-slate-800 mb-2 leading-tight"><?php echo $welcome_title; ?></h2>
        <p class="text-slate-500 text-sm font-medium mb-8 leading-relaxed"><?php echo $welcome_msg; ?></p>
        <?php if($total_tasks > 0): ?>
            <div class="bg-slate-50 rounded-xl p-3 mb-6 border border-slate-100">
                 <div class="flex justify-between text-[10px] font-bold text-slate-400 uppercase mb-1"><span>İlerleme</span><span><?php echo $done_tasks; ?> / <?php echo $total_tasks; ?></span></div>
                 <div class="w-full bg-slate-200 h-2.5 rounded-full overflow-hidden"><div class="h-full bg-[#223488]" style="width: <?php echo $ratio; ?>%"></div></div>
                 <div class="mt-2 text-[10px] font-bold text-[#ec9731] flex justify-between"><span>Hedef: %60</span><span><?php echo ($ratio >= 60) ? '✅ Tamamlandı' : '⏳ Devam Ediyor'; ?></span></div>
            </div>
        <?php endif; ?>
        <?php if($total_tasks == 0): ?>
            <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 mb-5 text-center">
                <div class="text-2xl mb-2">⚡</div>
                <p class="text-sm font-bold text-[#223488]">Şimşek serin devam ediyor!</p>
                <p class="text-xs text-slate-500 mt-1">Program oluşturmak için koçunla iletişime geç.</p>
            </div>
            <button onclick="closeDailyModal()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 py-3.5 rounded-xl font-bold transition transform active:scale-95">Tamam</button>
        <?php else: ?>
            <button onclick="closeDailyModal()" class="w-full bg-[#223488] hover:bg-[#314595] text-white py-3.5 rounded-xl font-bold shadow-lg shadow-[#223488]/20 transition transform active:scale-95">Hadi Başlayalım!</button>
        <?php endif; ?>
    </div>
</div>

<script>
    // Modal dışına tıklanınca kapat
    document.getElementById('streakDetailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
            document.getElementById('streakInfoPopup').classList.add('hidden');
        }
    });

    // 🏅 Dönen rozet: 4 saniyede bir sıradaki rozete yumuşak geçiş
    (function(){
        var BADGES = <?php echo json_encode($badges, JSON_UNESCAPED_UNICODE); ?>;
        var wraps = document.querySelectorAll('.rotb-wrap');
        if (!BADGES.length || !wraps.length) return;
        var i = 0;
        function render() {
            var b = BADGES[i];
            wraps.forEach(function(w){
                var ic = w.querySelector('.rotb-icon'), lb = w.querySelector('.rotb-label'), sb = w.querySelector('.rotb-sub');
                if (ic) { ic.textContent = b.icon; ic.style.filter = b.on ? '' : 'grayscale(1)'; ic.style.opacity = b.on ? '1' : '.5'; }
                if (lb) { lb.textContent = b.label; lb.style.opacity = b.on ? '1' : '.6'; }
                if (sb) { sb.textContent = b.sub; }
            });
        }
        render();
        setInterval(function(){
            wraps.forEach(function(w){ w.classList.add('rotb-fade'); });
            setTimeout(function(){
                i = (i + 1) % BADGES.length;
                render();
                wraps.forEach(function(w){ w.classList.remove('rotb-fade'); });
            }, 250);
        }, 4000);
    })();

    function openStreakModal() {
        document.getElementById('streakDetailsModal').classList.remove('hidden');
        // Animasyonlu sayaç
        const el = document.getElementById('streakCounter');
        if (!el) return;
        const target = parseInt(el.dataset.target) || 0;
        if (target === 0) { el.textContent = 0; return; }
        let current = 0;
        const step = Math.max(1, Math.ceil(target / 30));
        const timer = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = current;
            if (current >= target) clearInterval(timer);
        }, 40);
    }
    function checkDailyPopup() {
        const todayStr = new Date().toLocaleDateString('tr-TR');
        const lastLogin = localStorage.getItem('lastWelcomeDate');
        // Onarım yapıldıysa veya yeni günse göster
        <?php if(isset($_GET['repaired'])): ?>
            document.getElementById('dailyWelcomeModal').classList.remove('hidden');
            if(typeof confetti === 'function') confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 }, colors: ['#ec9731', '#223488', '#ffffff'] });
        <?php else: ?>
            if (lastLogin !== todayStr) {
                document.getElementById('dailyWelcomeModal').classList.remove('hidden');
                <?php if($ratio >= 60): ?> if(typeof confetti === 'function') confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 }, colors: ['#ec9731', '#223488', '#ffffff'] }); <?php endif; ?>
            }
        <?php endif; ?>
    }
    function closeDailyModal() {
        localStorage.setItem('lastWelcomeDate', new Date().toLocaleDateString('tr-TR'));
        document.getElementById('dailyWelcomeModal').classList.add('hidden');
        <?php if($total_tasks == 0): ?> window.location.href = 'student/kocluk.php'; <?php endif; ?>
    }
    document.addEventListener('DOMContentLoaded', () => { setTimeout(checkDailyPopup, 800); });
</script>

<?php 
if (file_exists(__DIR__ . '/student/modals.php')) {
    include __DIR__ . '/student/modals.php';
    include __DIR__ . '/student/scripts.php';
} else {
    include __DIR__ . '/modals.php';
    include __DIR__ . '/scripts.php';
}
include __DIR__ . '/footer.php'; 
?>