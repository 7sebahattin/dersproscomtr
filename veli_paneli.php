<?php
// veli_paneli.php
// Güncelleme: Sekme hafızası eklendi (Sayfa yenilenince program sekmesi açık kalır)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'db.php';
include 'header.php';

// 1. GÜVENLİK KONTROLÜ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'parent') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$parent_id = $_SESSION['user_id'];
$sid = $_GET['student_id'] ?? null; 

// 2. VELİYE BAĞLI ÖĞRENCİLERİ ÇEK
$stm = $pdo->prepare("SELECT u.* FROM users u JOIN parent_relationships pr ON u.id = pr.student_id WHERE pr.parent_id = ?");
$stm->execute([$parent_id]);
$my_children = $stm->fetchAll(PDO::FETCH_ASSOC);

// Öğrenci seçili değilse ilk öğrenciyi seç
if (!$sid && count($my_children) > 0) { $sid = $my_children[0]['id']; }

// Değişkenleri Başlat
$selected_student = null;
$schedule_items = [];
$report_stats = ['total_q' => 0, 'total_t' => 0, 'week_q' => 0, 'week_t' => 0];
$payments = [];
$announcements = [];

// Navigasyon için varsayılan değerler
$date_param = $_GET['date'] ?? date('Y-m-d'); 
$week_start = $date_param; 
$prev_week = date('Y-m-d', strtotime('-1 week', strtotime($week_start)));
$next_week = date('Y-m-d', strtotime('+1 week', strtotime($week_start)));
$prev_day  = date('Y-m-d', strtotime('-1 day', strtotime($week_start)));
$next_day  = date('Y-m-d', strtotime('+1 day', strtotime($week_start)));
$today_date = date('Y-m-d');

// 3. ÖĞRENCİ VERİLERİNİ HAZIRLA
if ($sid) {
    foreach($my_children as $child) { 
        if($child['id'] == $sid) { 
            $selected_student = $child; 
            break; 
        } 
    }

    if ($selected_student) {
        
        // --- PROGRAM VERİLERİ ---
        $week_dates = [];
        for($i=0; $i<7; $i++) { 
            $week_dates[] = date('Y-m-d', strtotime("+$i days", strtotime($week_start))); 
        }
        
        $sc = $pdo->prepare("
            SELECT si.*, t.name as topic_name, s.name as subject_name, s.category as subject_category 
            FROM schedule_items si 
            LEFT JOIN coaching_topics t ON si.topic_id = t.id 
            LEFT JOIN coaching_subjects s ON t.subject_id = s.id 
            WHERE si.student_id = ? AND si.date BETWEEN ? AND ?
        ");
        $sc->execute([$sid, $week_dates[0], $week_dates[6]]); 
        $raw_items = $sc->fetchAll(PDO::FETCH_ASSOC);

        foreach($week_dates as $wd) { 
            $schedule_items[$wd] = array_filter($raw_items, function($i) use ($wd) { 
                return $i['date'] == $wd; 
            }); 
        }

        // --- RAPOR VERİLERİ ---
        try {
            $completed = $pdo->prepare("SELECT amount, action_type, date FROM schedule_items WHERE student_id = ? AND status = 'yapildi'");
            $completed->execute([$sid]);
            $all_completed = $completed->fetchAll(PDO::FETCH_ASSOC);
            $week_limit = date('Y-m-d', strtotime('-7 days'));
            foreach($all_completed as $item) {
                $amt = (int)$item['amount']; $act = $item['action_type']; $dt = $item['date'];
                if($act == 'soru') { $report_stats['total_q'] += $amt; if($dt >= $week_limit) $report_stats['week_q'] += $amt; }
                if($act == 'konu') { $report_stats['total_t'] += 1; if($dt >= $week_limit) $report_stats['week_t'] += 1; }
            }
        } catch (Exception $e) {}

        // --- ÖDEMELER ---
        try {
            $pay = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY due_date ASC");
            $pay->execute([$sid]);
            $payments = $pay->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }
}

// 4. DUYURULAR
try { 
    $ann = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5"); 
    $announcements = $ann->fetchAll(PDO::FETCH_ASSOC); 
} catch (Exception $e) {}

$gunlerTR = ['Sunday'=>'Pazar','Monday'=>'Pazartesi','Tuesday'=>'Salı','Wednesday'=>'Çarşamba','Thursday'=>'Perşembe','Friday'=>'Cuma','Saturday'=>'Cumartesi'];
?>

<style>
    header, nav.navbar, .top-bar { display: none !important; }
    body { background-color: #f8fafc; }
    .animate-fadeIn { animation: fadeIn 0.4s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="flex h-screen overflow-hidden font-['Poppins']">
    
    <?php include 'veli/sidebar.php'; ?>

    <main class="flex-grow overflow-y-auto p-4 md:p-8">
        
        <?php if ($selected_student): ?>
            <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-3">
                        <span class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></span>
                        <?php echo $selected_student['first_name'] . ' ' . $selected_student['last_name']; ?>
                    </h1>
                    <p class="text-slate-500 text-xs font-bold pl-6"><?php echo $selected_student['school_level'] ?? 'Öğrenci'; ?> Seviyesi</p>
                </div>

                <div class="flex bg-white p-1.5 rounded-2xl shadow-sm border border-slate-200 overflow-x-auto max-w-full">
                    <button onclick="openTab('duyuru')" id="tab-duyuru" class="whitespace-nowrap px-5 py-2 rounded-xl font-bold text-xs transition bg-slate-800 text-white shadow-md flex items-center gap-2">📢 Duyurular</button>
                    <button onclick="openTab('schedule')" id="tab-schedule" class="whitespace-nowrap px-5 py-2 rounded-xl font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">📅 Program</button>
                    <button onclick="openTab('rapor')" id="tab-rapor" class="whitespace-nowrap px-5 py-2 rounded-xl font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">📊 Rapor</button>
                    <button onclick="openTab('odeme')" id="tab-odeme" class="whitespace-nowrap px-5 py-2 rounded-xl font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">💳 Ödemeler</button>
                </div>
            </div>

            <?php include 'veli/duyurular.php'; ?>
            <?php include 'veli/program.php'; ?>
            <?php include 'veli/rapor.php'; ?>
            <?php include 'veli/odemeler.php'; ?>

        <?php else: ?>
            <div class="h-full flex flex-col items-center justify-center text-center opacity-50">
                <div class="text-6xl mb-4">👶</div>
                <h2 class="text-xl font-bold text-slate-700">Öğrenci Seçilmedi</h2>
                <p class="text-sm">Lütfen sol menüden bir öğrenci seçiniz.</p>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php include 'veli/scripts.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        if(activeTab === 'schedule'){
            openTab('schedule');
        }
    });
</script>

<?php include 'footer.php'; ?>