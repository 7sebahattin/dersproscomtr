<?php
// teacher_dashboard.php - ÖĞRETMEN ANA PANELİ (Güncellenmiş Sürüm: Şimşek Serileri Eklendi)
if (session_status() === PHP_SESSION_NONE) session_start();

// Güvenlik: Sadece öğretmen girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

require_once 'db.php';
$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['first_name'] ?? 'Hocam';

// Türkçe Ay adları
$monthsTR = [
    'Jan' => 'OCA', 'Feb' => 'ŞUB', 'Mar' => 'MART', 'Apr' => 'NİS',
    'May' => 'MAY', 'Jun' => 'HAZ', 'Jul' => 'TEM', 'Aug' => 'AĞU',
    'Sep' => 'EYL', 'Oct' => 'EKİM', 'Nov' => 'KAS', 'Dec' => 'ARA'
];

// --- İSTATİSTİKLERİ BAŞLAT ---\
$student_count = 0;
$today_appointments = 0;
$unread_msgs = 0;
$upcoming_appointments = [];
$top_question_students = [];
$top_topic_students = [];
$student_streaks = []; // Yeni eklenen dizi
$today_progress = [];  // Bugünkü görev tamamlama yüzdesi (öğrenci başına)

try {
    // 1. Toplam Öğrenci Sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM coaching_relationships WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $student_count = $stmt->fetchColumn();

    // 2. Bugünün Randevuları
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE teacher_id = ? AND appointment_date = ? AND (status IS NULL OR status != 'cancelled')");
    $stmt->execute([$teacher_id, $today]);
    $today_appointments = $stmt->fetchColumn();

    // 3. OKUNMAMIŞ MESAJLAR
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM appointment_messages m
            JOIN appointments a ON a.id = m.appointment_id
            WHERE a.teacher_id = ? AND m.sender_role != 'teacher' AND m.is_read_by_teacher = 0
        ");
        $stmt->execute([$teacher_id]);
        $unread_msgs += $stmt->fetchColumn();
    } catch (Exception $e) {}

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM live_chat_messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$teacher_id]);
        $unread_msgs += $stmt->fetchColumn();
    } catch (Exception $e) {}


    // 4. Yaklaşan İlk 4 Randevu
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name 
        FROM appointments a 
        JOIN users u ON a.student_id = u.id 
        WHERE a.teacher_id = ? 
        AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= NOW()
        AND (a.status IS NULL OR a.status != 'cancelled')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC 
        LIMIT 4
    ");
    $stmt->execute([$teacher_id]);
    $upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // --- LİDERLİK TABLOSU (SON 7 GÜN) ---
    $oneWeekAgo = date('Y-m-d', strtotime('-7 days'));

    // 5. En Çok Soru Çözenler
    try {
        $stmt = $pdo->prepare("
            SELECT u.first_name, u.last_name, SUM(si.amount) as total_val
            FROM schedule_items si
            JOIN users u ON u.id = si.student_id
            JOIN coaching_relationships cr ON cr.student_id = u.id
            WHERE cr.teacher_id = ?
            AND si.action_type = 'soru'
            AND si.status = 'yapildi'
            AND si.date >= ? 
            GROUP BY si.student_id
            ORDER BY total_val DESC
            LIMIT 3
        ");
        $stmt->execute([$teacher_id, $oneWeekAgo]);
        $top_question_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $top_question_students = []; }

    // 6. En Çok Konu Çalışanlar
    try {
        $stmt = $pdo->prepare("
            SELECT u.first_name, u.last_name, COUNT(*) as total_val
            FROM schedule_items si
            JOIN users u ON u.id = si.student_id
            JOIN coaching_relationships cr ON cr.student_id = u.id
            WHERE cr.teacher_id = ?
            AND si.action_type = 'konu'
            AND si.status = 'yapildi'
            AND si.date >= ?
            GROUP BY si.student_id
            ORDER BY total_val DESC
            LIMIT 3
        ");
        $stmt->execute([$teacher_id, $oneWeekAgo]);
        $top_topic_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $top_topic_students = []; }

    // --- 7. ÖĞRENCİ SERİLERİ (ŞİMŞEK) --- YENİ EKLENEN KISIM
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.current_streak, u.freeze_count, u.last_streak_date
            FROM users u
            JOIN coaching_relationships cr ON cr.student_id = u.id
            WHERE cr.teacher_id = ?
            ORDER BY u.current_streak DESC
        ");
        $stmt->execute([$teacher_id]);
        $student_streaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $student_streaks = []; }

    // 8. Sistem Güncelleme Notları
    $system_updates = [];
    try {
        $system_updates = $pdo->query("SELECT * FROM system_updates WHERE is_published = 1 ORDER BY created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    // --- 9. BUGÜNKÜ GÖREV TAMAMLAMA DURUMU (öğrenci başına yüzde) ---
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.last_login_at,
                   COUNT(si.id)                                                      AS total,
                   SUM(CASE WHEN si.status IN ('yapildi','yarim') THEN 1 ELSE 0 END) AS done
            FROM coaching_relationships cr
            JOIN users u ON u.id = cr.student_id
            JOIN schedule_items si ON si.student_id = u.id AND DATE(si.date) = ?
            WHERE cr.teacher_id = ?
            GROUP BY u.id, u.first_name, u.last_name, u.last_login_at
        ");
        $stmt->execute([$today, $teacher_id]);
        $today_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($today_progress as &$tp) {
            $tp['pct']    = (int)$tp['total'] > 0 ? (int)round(100 * (int)$tp['done'] / (int)$tp['total']) : 0;
            $tp['logged'] = $tp['last_login_at'] && substr($tp['last_login_at'], 0, 10) === $today;
        }
        unset($tp);
        // Geride kalan (düşük yüzdeli) öğrenciler üstte — öğretmenin dikkatine
        usort($today_progress, fn($a, $b) => $a['pct'] <=> $b['pct']);
    } catch (Exception $e) { $today_progress = []; }

} catch (PDOException $e) {}

include 'header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* ATLA Renk Paleti */
    :root {
        --atla-blue-dark: #223488;
        --atla-blue-light: #314595;
        --atla-orange: #ec9731;
        --atla-orange-hover: #d68625;
    }
    body { background-color: #f8fafc; font-family: 'Poppins', sans-serif; }
    
    .text-atla-blue { color: var(--atla-blue-dark); }
    .bg-atla-blue { background-color: var(--atla-blue-dark); }
    
    /* Kart Efektleri */
    .dashboard-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }
    
    /* Header Gradient */
    .glass-header {
        background: linear-gradient(135deg, var(--atla-blue-dark) 0%, var(--atla-blue-light) 100%);
        color: white;
    }

    /* Sıralama ikonları renkleri */
    .rank-1 { color: #FFD700; text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
    .rank-2 { color: #C0C0C0; }
    .rank-3 { color: #CD7F32; }

    /* Şimşek Serisi Animasyonları */
    @keyframes streakGoldGlow {
        0%, 100% { filter: drop-shadow(0 0 4px rgba(250,204,21,0.7)); }
        50%       { filter: drop-shadow(0 0 14px rgba(250,204,21,1)) drop-shadow(0 0 28px rgba(251,146,60,0.6)); transform: scale(1.12); }
    }
    @keyframes streakPulse {
        0%, 100% { filter: drop-shadow(0 0 3px rgba(250,204,21,0.5)); }
        50%       { filter: drop-shadow(0 0 9px rgba(250,204,21,0.9)); transform: scale(1.06); }
    }
    .s-glow-gold { animation: streakGoldGlow 1.6s ease-in-out infinite; color: #facc15; }
    .s-glow      { animation: streakPulse 2s ease-in-out infinite; color: #fbbf24; }
    .s-pulse     { animation: streakPulse 2.8s ease-in-out infinite; color: #fcd34d; }
</style>

<div class="min-h-screen pb-20">
    
    <div class="glass-header pt-10 pb-12 px-4 sm:px-6 lg:px-8 rounded-b-[3rem] shadow-xl relative overflow-hidden">
        <div class="absolute top-0 right-0 opacity-10 transform translate-x-10 -translate-y-10">
            <i class="fa-solid fa-graduation-cap text-9xl"></i>
        </div>
        
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <p class="text-blue-200 font-medium text-sm uppercase tracking-wider mb-1">DersPROS Paneli</p>
                    <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">
                        Hoş geldin, <span class="text-[#ec9731]"><?php echo htmlspecialchars($teacher_name); ?></span> 👋
                    </h1>
                </div>
                <?php
                    $dayNamesTR = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
                    $todayName  = $dayNamesTR[date('w')];
                    $m = date('M');
                ?>
                <div class="bg-white/10 backdrop-blur-md border border-white/20 p-2 rounded-2xl flex items-center gap-3 pr-6">
                    <div class="bg-white text-[#223488] w-10 h-10 rounded-xl flex items-center justify-center font-bold text-lg">
                        <?php echo date('d'); ?>
                    </div>
                    <div class="text-left">
                        <div class="text-xs text-blue-200 uppercase font-bold"><?php echo $todayName; ?></div>
                        <div class="font-bold leading-none"><?php echo ($monthsTR[$m] ?? $m) . ' ' . date('Y'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 relative z-20">
        
        <!-- ANA İÇERİK GRİDİ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- SOL SÜTUN (2/3) -->
            <div class="lg:col-span-2 space-y-6">

                <!-- HIZLI İŞLEMLER -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-5 py-4 flex items-center gap-2">
                        <i class="fa-solid fa-bolt text-[#ec9731]"></i>
                        <h2 class="text-xs font-bold text-white uppercase tracking-wider">Hızlı İşlemler</h2>
                    </div>
                    <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <a href="<?php echo BASE_URL; ?>/koc_paneli.php" class="dashboard-card bg-blue-50 border border-blue-100 p-4 rounded-2xl text-center hover:bg-white hover:border-slate-200 group transition-all duration-300">
                            <div class="w-11 h-11 mx-auto bg-blue-600 rounded-xl flex items-center justify-center text-white mb-2.5 shadow-sm group-hover:bg-white group-hover:text-blue-600 group-hover:shadow-md transition-all duration-300">
                                <i class="fa-solid fa-clipboard-list text-lg"></i>
                            </div>
                            <h3 class="font-bold text-blue-700 text-xs group-hover:text-slate-700 transition-colors">Program Yönetimi</h3>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/koc/ogrencilerim.php" class="dashboard-card bg-emerald-50 border border-emerald-100 p-4 rounded-2xl text-center hover:bg-white hover:border-slate-200 group transition-all duration-300">
                            <div class="w-11 h-11 mx-auto bg-emerald-600 rounded-xl flex items-center justify-center text-white mb-2.5 shadow-sm group-hover:bg-white group-hover:text-emerald-600 group-hover:shadow-md transition-all duration-300">
                                <i class="fa-solid fa-user-plus text-lg"></i>
                            </div>
                            <h3 class="font-bold text-emerald-700 text-xs group-hover:text-slate-700 transition-colors">Öğrenci Ekle</h3>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/koc/randevu.php" class="dashboard-card bg-orange-50 border border-orange-100 p-4 rounded-2xl text-center hover:bg-white hover:border-slate-200 group transition-all duration-300">
                            <div class="w-11 h-11 mx-auto bg-orange-500 rounded-xl flex items-center justify-center text-white mb-2.5 shadow-sm group-hover:bg-white group-hover:text-orange-500 group-hover:shadow-md transition-all duration-300">
                                <i class="fa-solid fa-calendar-plus text-lg"></i>
                            </div>
                            <h3 class="font-bold text-orange-700 text-xs group-hover:text-slate-700 transition-colors">Randevu Ekle</h3>
                        </a>
                        <button type="button" onclick="openFeedbackModal()" class="dashboard-card bg-red-50 border border-red-100 p-4 rounded-2xl text-center hover:bg-white hover:border-slate-200 group w-full cursor-pointer transition-all duration-300">
                            <div class="w-11 h-11 mx-auto bg-red-500 rounded-xl flex items-center justify-center text-white mb-2.5 shadow-sm group-hover:bg-white group-hover:text-red-500 group-hover:shadow-md transition-all duration-300">
                                <i class="fa-solid fa-bug text-lg"></i>
                            </div>
                            <h3 class="font-bold text-red-700 text-xs group-hover:text-slate-700 transition-colors">Hata Bildir</h3>
                        </button>
                    </div>
                </div>

                <!-- BUGÜNKÜ GÖREV DURUMU (öğrenci başına yüzde) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-chart-simple text-[#ec9731]"></i>
                            <h2 class="text-xs font-bold text-white uppercase tracking-wider">Bugünkü Görev Durumu</h2>
                        </div>
                        <span class="text-[10px] text-blue-200 font-bold"><?php echo date('d.m.Y'); ?></span>
                    </div>
                    <div class="p-5">
                    <?php if (empty($today_progress)): ?>
                        <div class="text-center py-6 text-slate-400 text-sm">Bugün görevi olan öğrenci yok.</div>
                    <?php else: ?>
                        <div class="space-y-3">
                        <?php foreach ($today_progress as $tp):
                            $pct    = (int)$tp['pct'];
                            $barCls = $pct >= 67 ? 'bg-green-500' : ($pct >= 34 ? 'bg-[#ec9731]' : 'bg-red-500');
                            $txtCls = $pct >= 67 ? 'text-green-600' : ($pct >= 34 ? 'text-[#d68625]' : 'text-red-500');
                        ?>
                            <a href="<?php echo BASE_URL; ?>/koc_paneli.php?student_id=<?php echo (int)$tp['id']; ?>"
                               class="block p-3 rounded-xl bg-slate-50 hover:bg-blue-50 transition-colors">
                                <div class="flex items-center justify-between gap-3 mb-1.5">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-7 h-7 rounded-full bg-[#223488] text-white text-xs font-black flex items-center justify-center shrink-0"><?php echo mb_strtoupper(mb_substr($tp['first_name'], 0, 1, 'UTF-8'), 'UTF-8'); ?></span>
                                        <span class="font-bold text-slate-700 text-sm truncate"><?php echo htmlspecialchars($tp['first_name'] . ' ' . $tp['last_name']); ?></span>
                                        <?php if (!$tp['logged']): ?>
                                            <span class="text-[9px] font-black bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full whitespace-nowrap shrink-0" title="Bugün sisteme hiç girmedi">GİRİŞ YOK</span>
                                        <?php endif; ?>
                                        <?php if ($pct >= 100): ?><span class="shrink-0">🎉</span><?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-[10px] font-bold text-slate-400"><?php echo (int)$tp['done']; ?>/<?php echo (int)$tp['total']; ?> görev</span>
                                        <span class="text-sm font-black <?php echo $txtCls; ?> w-11 text-right">%<?php echo $pct; ?></span>
                                    </div>
                                </div>
                                <div class="h-2 rounded-full bg-slate-200 overflow-hidden">
                                    <div class="h-full rounded-full <?php echo $barCls; ?>" style="width: <?php echo $pct; ?>%"></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>

                <!-- LİDERLİK TABLOLARI -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-5 py-4 flex items-center gap-2">
                            <i class="fa-solid fa-trophy text-[#ec9731]"></i>
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Bu Hafta — En Çok Soru</h3>
                        </div>
                        <div class="p-5">
                        <?php if(empty($top_question_students)): ?>
                            <div class="text-center py-6 text-slate-400 text-sm">Son 1 haftada veri girişi yok.</div>
                        <?php else: ?>
                            <div class="space-y-2.5">
                                <?php foreach($top_question_students as $index => $student):
                                    $rankClass = ($index === 0) ? 'rank-1' : (($index === 1) ? 'rank-2' : 'rank-3'); ?>
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 hover:bg-blue-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-full bg-white shadow-sm flex items-center justify-center font-black text-sm <?php echo $rankClass; ?>">
                                            <?php if($index === 0): ?><i class="fa-solid fa-crown text-xs"></i><?php else: echo $index + 1; endif; ?>
                                        </div>
                                        <span class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                    </div>
                                    <span class="bg-white px-2 py-1 rounded-lg text-xs font-bold text-[#223488] shadow-sm border border-slate-100"><?php echo number_format($student['total_val']); ?> S</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-5 py-4 flex items-center gap-2">
                            <i class="fa-solid fa-book-open text-[#ec9731]"></i>
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Bu Hafta — En Çok Konu</h3>
                        </div>
                        <div class="p-5">
                        <?php if(empty($top_topic_students)): ?>
                            <div class="text-center py-6 text-slate-400 text-sm">Son 1 haftada veri girişi yok.</div>
                        <?php else: ?>
                            <div class="space-y-2.5">
                                <?php foreach($top_topic_students as $index => $student):
                                    $rankClass = ($index === 0) ? 'rank-1' : (($index === 1) ? 'rank-2' : 'rank-3'); ?>
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 hover:bg-orange-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-full bg-white shadow-sm flex items-center justify-center font-black text-sm <?php echo $rankClass; ?>">
                                            <?php if($index === 0): ?><i class="fa-solid fa-medal text-xs"></i><?php else: echo $index + 1; endif; ?>
                                        </div>
                                        <span class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                    </div>
                                    <span class="bg-white px-2 py-1 rounded-lg text-xs font-bold text-[#ec9731] shadow-sm border border-slate-100"><?php echo number_format($student['total_val']); ?> K</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ŞİMŞEK SERİLERİ — LİDERLİK TABLOSU -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <!-- Başlık -->
                    <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl s-glow-gold inline-block">⚡</span>
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Şimşek Seri Sıralaması</h3>
                        </div>
                        <span class="text-[10px] bg-white/10 text-blue-200 font-bold px-2.5 py-1 rounded-full"><?php echo count($student_streaks); ?> öğrenci</span>
                    </div>

                    <?php if(empty($student_streaks)): ?>
                        <div class="text-center py-8 text-slate-400 text-sm">Kayıtlı öğrenci bulunamadı.</div>
                    <?php else: ?>
                    <div class="divide-y divide-slate-50">
                        <?php foreach($student_streaks as $i => $student):
                            $rank      = $i + 1;
                            $streak    = (int)$student['current_streak'];
                            $is_active = ($student['last_streak_date'] === date('Y-m-d') || $student['last_streak_date'] === date('Y-m-d', strtotime('-1 day')));

                            if (!$is_active || $streak === 0) {
                                $rowBg       = 'bg-white';
                                $numColor    = 'text-slate-300';
                                $boltCls     = 'text-slate-300';
                                $unvan       = '—';
                                $unvanCls    = 'bg-slate-100 text-slate-400';
                            } elseif ($streak >= 30) {
                                $rowBg       = 'bg-gradient-to-r from-yellow-50 to-orange-50';
                                $numColor    = 'text-orange-500';
                                $boltCls     = 's-glow-gold';
                                $unvan       = '🌟 Efsane';
                                $unvanCls    = 'bg-orange-100 text-orange-700';
                            } elseif ($streak >= 21) {
                                $rowBg       = 'bg-gradient-to-r from-amber-50 to-yellow-50';
                                $numColor    = 'text-amber-500';
                                $boltCls     = 's-glow';
                                $unvan       = '💥 Fırtına';
                                $unvanCls    = 'bg-amber-100 text-amber-700';
                            } elseif ($streak >= 14) {
                                $rowBg       = 'bg-yellow-50';
                                $numColor    = 'text-yellow-600';
                                $boltCls     = 's-glow';
                                $unvan       = '🔥 Alev';
                                $unvanCls    = 'bg-yellow-100 text-yellow-700';
                            } elseif ($streak >= 7) {
                                $rowBg       = 'bg-yellow-50/60';
                                $numColor    = 'text-yellow-500';
                                $boltCls     = 's-pulse';
                                $unvan       = '⚡ Şimşek';
                                $unvanCls    = 'bg-yellow-100 text-yellow-600';
                            } elseif ($streak >= 4) {
                                $rowBg       = 'bg-orange-50/50';
                                $numColor    = 'text-orange-400';
                                $boltCls     = 'text-orange-300';
                                $unvan       = '🎯 Kararlı';
                                $unvanCls    = 'bg-orange-100 text-orange-600';
                            } else {
                                $rowBg       = 'bg-white';
                                $numColor    = 'text-slate-500';
                                $boltCls     = 'text-slate-300';
                                $unvan       = '🌱 Başlıyor';
                                $unvanCls    = 'bg-slate-100 text-slate-500';
                            }

                            $medal = match($rank) {
                                1 => '<span class="text-2xl">🥇</span>',
                                2 => '<span class="text-2xl">🥈</span>',
                                3 => '<span class="text-2xl">🥉</span>',
                                default => '<span class="text-xs font-black text-slate-400 w-6 text-center">' . $rank . '</span>',
                            };
                        ?>
                        <div class="flex items-center gap-3 px-5 py-3.5 <?php echo $rowBg; ?> transition-all duration-200 hover:brightness-95">
                            <!-- Sıra -->
                            <div class="w-7 flex-shrink-0 flex items-center justify-center"><?php echo $medal; ?></div>
                            <!-- Şimşek -->
                            <div class="text-2xl flex-shrink-0 <?php echo $boltCls; ?> leading-none">⚡</div>
                            <!-- İsim + Unvan -->
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-slate-800 text-sm truncate leading-tight">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                </div>
                                <span class="inline-block text-[10px] font-bold px-1.5 py-0.5 rounded-full mt-0.5 <?php echo $unvanCls; ?>"><?php echo $unvan; ?></span>
                            </div>
                            <!-- Seri Sayısı -->
                            <div class="text-right flex-shrink-0 mr-1">
                                <div class="text-2xl font-black <?php echo $numColor; ?> leading-none"><?php echo $streak; ?></div>
                                <div class="text-[9px] text-slate-400 font-bold uppercase tracking-wide">gün</div>
                            </div>
                            <!-- Kalkan -->
                            <?php if((int)$student['freeze_count'] > 0): ?>
                            <div class="flex-shrink-0 bg-blue-50 border border-blue-100 text-blue-600 px-2 py-1 rounded-lg text-[10px] font-bold flex items-center gap-1">
                                🛡️<span><?php echo $student['freeze_count']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- SAĞ SÜTUN (1/3) -->
            <div class="space-y-6">

                <!-- SIRADAKI RANDEVULAR -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-calendar-check text-[#ec9731]"></i>
                            <h2 class="text-xs font-bold text-white uppercase tracking-wider">Sıradaki Randevular</h2>
                        </div>
                        <a href="<?php echo BASE_URL; ?>/koc/randevu.php" class="text-[10px] font-bold text-blue-200 hover:text-white transition">Tümü →</a>
                    </div>
                    <div class="p-5">

                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="text-center py-8">
                            <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-2 text-slate-300 text-xl">
                                <i class="fa-solid fa-mug-hot"></i>
                            </div>
                            <p class="text-slate-500 text-sm">Planlanmış randevu yok.</p>
                            <a href="<?php echo BASE_URL; ?>/koc/randevu.php" class="mt-1 inline-block text-xs font-bold text-[#ec9731]">Hemen Planla +</a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($upcoming_appointments as $app):
                                $dateObj  = new DateTime($app['appointment_date']);
                                $timeObj  = new DateTime($app['appointment_time']);
                                $isToday  = ($dateObj->format('Y-m-d') == date('Y-m-d'));
                                $trMonth  = $monthsTR[$dateObj->format('M')] ?? $dateObj->format('M');
                            ?>
                            <a href="<?php echo BASE_URL; ?>/koc/randevu.php" class="block group">
                                <div class="flex gap-3 p-3 rounded-xl border <?php echo $isToday ? 'border-orange-200 bg-orange-50' : 'border-slate-100 bg-slate-50'; ?> transition group-hover:shadow-md group-hover:border-[#223488]/30">
                                    <div class="flex flex-col items-center justify-center bg-white rounded-xl w-12 h-12 shadow-sm border border-slate-100 flex-shrink-0">
                                        <span class="text-[9px] font-bold text-slate-400 uppercase"><?php echo $trMonth; ?></span>
                                        <span class="text-lg font-black <?php echo $isToday ? 'text-[#ec9731]' : 'text-[#223488]'; ?>"><?php echo $dateObj->format('d'); ?></span>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <h4 class="font-bold text-slate-800 text-sm truncate group-hover:text-[#223488] transition-colors">
                                            <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                        </h4>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs text-slate-500 flex items-center">
                                                <i class="fa-regular fa-clock mr-1 text-[10px]"></i><?php echo $timeObj->format('H:i'); ?>
                                            </span>
                                            <?php if($isToday): ?>
                                                <span class="text-[9px] font-black bg-[#ec9731] text-white px-1.5 py-0.5 rounded">BUGÜN</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>

                <!-- GÜNCELLEME NOTLARI -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-wand-magic-sparkles text-[#ec9731]"></i>
                            <div>
                                <h2 class="text-xs font-bold text-white uppercase tracking-wider">Yenilikler</h2>
                                <p class="text-blue-300 text-[10px] mt-0.5">Sistem güncelleme notları</p>
                            </div>
                        </div>
                        <?php if (!empty($system_updates) && isset($system_updates[0]['version'])): ?>
                            <span class="bg-white/10 text-blue-200 text-[10px] font-black px-2.5 py-1 rounded-full border border-white/10">
                                <?php echo htmlspecialchars($system_updates[0]['version']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php
                    $typeMap = [
                        'ozellik'     => ['✨', 'bg-blue-100 text-blue-700'],
                        'duzeltme'    => ['🐛', 'bg-red-100 text-red-600'],
                        'iyilestirme' => ['⚡', 'bg-purple-100 text-purple-700'],
                        'duyuru'      => ['📢', 'bg-amber-100 text-amber-700'],
                    ];
                    ?>

                    <?php if (empty($system_updates)): ?>
                        <div class="p-6 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-mug-hot text-2xl mb-2 block opacity-40"></i>
                            Henüz güncelleme notu yok.
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                            <?php foreach ($system_updates as $upd):
                                $tm   = $typeMap[$upd['type']] ?? $typeMap['duyuru'];
                                $icon = $tm[0]; $badge = $tm[1];
                                $dt   = new DateTime($upd['created_at']);
                            ?>
                            <div class="px-5 py-3.5 hover:bg-slate-50 transition-colors">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?php echo $badge ?>"><?php echo $icon ?> <?php echo htmlspecialchars($upd['type'] === 'ozellik' ? 'Özellik' : ($upd['type'] === 'duzeltme' ? 'Düzeltme' : ($upd['type'] === 'iyilestirme' ? 'İyileştirme' : 'Duyuru'))); ?></span>
                                    <span class="text-[10px] text-slate-400"><?php echo $dt->format('d.m.Y'); ?></span>
                                </div>
                                <p class="font-bold text-slate-800 text-xs leading-snug"><?php echo htmlspecialchars($upd['title']); ?></p>
                                <p class="text-slate-500 text-[11px] mt-1 leading-relaxed whitespace-pre-line"><?php echo nl2br(htmlspecialchars($upd['content'])); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div><!-- /sağ sütun -->

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>