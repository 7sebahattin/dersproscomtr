<?php
// koc_paneli.php - HATASIZ SÜRÜM (Ders Filtreleme ve Kopyalama Düzeltildi)
ob_start(); // Çıktı tamponlamayı başlat (JSON hatalarını önler)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'db.php';
include 'header.php';

// 1. GÜVENLİK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$teacher_id = $_SESSION['user_id'];
$user_role = 'teacher'; 
$sid = $_GET['student_id'] ?? null;
$message = '';
$error = '';

$exam_results = [];
$progress_data = [];
$schedule_items = [];
$my_students = [];
$selected_student = null;
$report_stats = ['total_q' => 0, 'total_t' => 0, 'week_q' => 0, 'week_t' => 0];

// 2. VERİTABANI GÜNCELLEMELERİ
try {
    $checkTime = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'time_note'");
    if($checkTime->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN time_note VARCHAR(20) DEFAULT NULL");

    $checkParent = $pdo->query("SHOW COLUMNS FROM users LIKE 'parent_name'");
    if($checkParent->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN parent_name VARCHAR(100) DEFAULT NULL AFTER phone");
        $pdo->exec("ALTER TABLE users ADD COLUMN parent_phone VARCHAR(20) DEFAULT NULL AFTER parent_name");
    }

    $checkOrder = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'item_order'");
    if($checkOrder->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN item_order INT DEFAULT 0");

    $checkLevel = $pdo->query("SHOW COLUMNS FROM users LIKE 'school_level'");
    if($checkLevel->rowCount() == 0) $pdo->exec("ALTER TABLE users ADD COLUMN school_level VARCHAR(20) DEFAULT 'Lise' AFTER role");

    $checkTarget = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'target_amount'");
    if($checkTarget->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN target_amount INT DEFAULT NULL");

    $checkCorrect = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'correct_count'");
    if($checkCorrect->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN correct_count INT DEFAULT NULL");

    $checkWrong = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'wrong_count'");
    if($checkWrong->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN wrong_count INT DEFAULT NULL");

} catch (PDOException $e) {}

// 3. TARİH AYARLARI
$date_param = $_GET['date'] ?? date('Y-m-d');
$week_start = $date_param; 

// Hafta İleri/Geri
$prev_week = date('Y-m-d', strtotime('-1 week', strtotime($week_start)));
$next_week = date('Y-m-d', strtotime('+1 week', strtotime($week_start)));

// ✅ EKLENEN KISIM: Gün İleri/Geri ve Bugün
$prev_day  = date('Y-m-d', strtotime('-1 day', strtotime($week_start)));
$next_day  = date('Y-m-d', strtotime('+1 day', strtotime($week_start)));
$today_date = date('Y-m-d');

$week_dates = [];
for($i=0; $i<7; $i++) { 
    $week_dates[] = date('Y-m-d', strtotime("+$i days", strtotime($week_start))); 
}
$gunlerTR = ['Sunday'=>'Pazar','Monday'=>'Pazartesi','Tuesday'=>'Salı','Wednesday'=>'Çarşamba','Thursday'=>'Perşembe','Friday'=>'Cuma','Saturday'=>'Cumartesi'];


// ✅ AJAX İŞLEMLERİ (JSON Yanıt Garantili)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // Önceki tüm çıktıları temizle (Hata mesajları JSON'u bozmasın)
    ob_end_clean(); 
    header('Content-Type: application/json; charset=utf-8');

    if (!$sid) { echo json_encode(['ok' => false, 'error' => 'Öğrenci seçilmemiş.']); exit; }

    try {
        $rel = $pdo->prepare("SELECT 1 FROM coaching_relationships WHERE teacher_id = ? AND student_id = ? LIMIT 1");
        $rel->execute([$teacher_id, $sid]);
        if (!$rel->fetchColumn()) { echo json_encode(['ok' => false, 'error' => 'Yetkisiz işlem.']); exit; }
    } catch (Exception $e) { echo json_encode(['ok' => false, 'error' => 'Yetki hatası.']); exit; }

    $action = $_POST['ajax'];
    $itemId = (int)($_POST['schedule_id'] ?? $_POST['item_id'] ?? 0);
    $toDate = $_POST['to_date'] ?? $_POST['new_date'] ?? '';

    // A. TAŞIMA
    if ($action === 'move_schedule') {
        try {
            $up = $pdo->prepare("UPDATE schedule_items SET date = ? WHERE id = ? AND student_id = ?");
            $up->execute([$toDate, $itemId, $sid]);
            echo json_encode(['ok' => true]); exit;
        } catch (Exception $e) { echo json_encode(['ok' => false, 'error' => 'Taşıma hatası.']); exit; }
    }

    // B. KOPYALAMA
    if ($action === 'copy_schedule') {
        try {
            $get = $pdo->prepare("SELECT * FROM schedule_items WHERE id = ? AND student_id = ? LIMIT 1");
            $get->execute([$itemId, $sid]);
            $row = $get->fetch(PDO::FETCH_ASSOC);

            if (!$row) { echo json_encode(['ok' => false, 'error' => 'Kayıt bulunamadı.']); exit; }

            $ins = $pdo->prepare("
                INSERT INTO schedule_items
                    (student_id, date, amount, action_type, status, topic_id, custom_subject, custom_topic, time_note, item_order)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $ins->execute([
                $sid, $toDate, $row['amount'], $row['action_type'], $row['status'],
                $row['topic_id'], $row['custom_subject'], $row['custom_topic'], $row['time_note']
            ]);
            $newId = (int)$pdo->lastInsertId();
            echo json_encode(['ok' => true, 'new_id' => $newId]); exit;
        } catch (Exception $e) { echo json_encode(['ok' => false, 'error' => 'Kopyalama hatası.']); exit; }
    }

    // C. SIRALAMA GÜNCELLEME
    if ($action === 'reorder_schedule') {
        $orderList = $_POST['order'] ?? [];
        if (is_array($orderList)) {
            try {
                $sql = "UPDATE schedule_items SET item_order = ? WHERE id = ? AND student_id = ?";
                $stmt = $pdo->prepare($sql);
                foreach ($orderList as $index => $id) {
                    $stmt->execute([$index, $id, $sid]);
                }
                echo json_encode(['ok' => true]); exit;
            } catch (Exception $e) {
                echo json_encode(['ok' => false, 'error' => 'Sıralama hatası.']); exit;
            }
        }
    }

    echo json_encode(['ok' => false, 'error' => 'Bilinmeyen işlem.']); exit;
}

// 4. POST İŞLEMLERİ (Form Gönderimi ve Yönlendirme)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax'])) {
    $should_redirect = false;

    // Öğrenci Ekleme
    if (isset($_POST['create_student'])) {
        $u_user = trim($_POST['username']); 
        $u_pass = $_POST['password']; 
        $u_name = trim($_POST['first_name']); 
        $u_last = trim($_POST['last_name']);
        $u_level = $_POST['school_level'] ?? 'Lise';
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?"); 
        $check->execute([$u_user]);
        if ($check->rowCount() > 0) { $error = "Bu kullanıcı adı alınmış."; } 
        else {
            $pass = password_hash($u_pass, PASSWORD_DEFAULT); 
            $mail = $u_user . "@derspros.com"; 
            $ins = $pdo->prepare("INSERT INTO users (username, password, first_name, last_name, role, school_level, email, is_active, date_joined) VALUES (?, ?, ?, ?, 'student', ?, ?, 1, NOW())");
            if ($ins->execute([$u_user, $pass, $u_name, $u_last, $u_level, $mail])) {
                $new_sid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO coaching_relationships (teacher_id, student_id) VALUES (?, ?)")->execute([$teacher_id, $new_sid]);
                $sid = $new_sid; $should_redirect = true;
            }
        }
    }
    // Öğrenci Bağlama
    if (isset($_POST['link_student'])) {
        $search = trim($_POST['search_term']);
        $find = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND role = 'student'");
        $find->execute([$search, $search]); $found = $find->fetch();
        if ($found) {
            $chk = $pdo->prepare("SELECT id FROM coaching_relationships WHERE teacher_id = ? AND student_id = ?");
            $chk->execute([$teacher_id, $found['id']]);
            if ($chk->rowCount() == 0) {
                $pdo->prepare("INSERT INTO coaching_relationships (teacher_id, student_id) VALUES (?, ?)")->execute([$teacher_id, $found['id']]);
                $should_redirect = true;
            } else {
                $error = "Bu öğrenci zaten listenizde kayıtlı.";
            }
        } else {
            $error = "Kullanıcı bulunamadı. Kullanıcı adını kontrol edin veya admin ile iletişime geçin.";
        }
    }
    // Bilgi Güncelleme
    if (isset($_POST['update_student_info']) && $sid) {
        try { 
            $pdo->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, parent_name=?, parent_phone=?, school_level=? WHERE id=?")
                ->execute([$_POST['first_name'], $_POST['last_name'], $_POST['student_phone'], $_POST['parent_name'], $_POST['parent_phone'], $_POST['school_level'], $sid]); 
            $should_redirect = true;
        } catch(Exception $e) {}
    }

    // Görev İşlemleri
    if (isset($_POST['save_schedule']) && $sid) {
        $date = $_POST['date']; $amt = $_POST['amount']; $act = $_POST['action_type'];
        $st = $_POST['status']; $tid = !empty($_POST['topic'])?$_POST['topic']:null;
        $csub = $_POST['custom_subject'] ?? ''; $ctop = $_POST['custom_topic'] ?? ''; $tn = $_POST['time_note'];
        $eduTid = !empty($_POST['edu_topic_id']) ? (int)$_POST['edu_topic_id'] : null; // YENİ müfredat konusu
        $id = $_POST['schedule_id'];
        // edu_topic_id kolonu yoksa oluştur (yeni müfredat konu bağı için — idempotent)
        $hasEduCol = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'edu_topic_id'")->rowCount() > 0;
        if (!$hasEduCol) {
            try {
                $pdo->exec("ALTER TABLE schedule_items ADD COLUMN edu_topic_id INT NULL DEFAULT NULL");
                $pdo->exec("ALTER TABLE schedule_items ADD KEY idx_si_edu_topic (edu_topic_id)");
                $hasEduCol = true;
            } catch (Throwable $e) { $hasEduCol = false; }
        }
        if ($id) {
            if ($hasEduCol) {
                $pdo->prepare("UPDATE schedule_items SET amount=?, action_type=?, status=?, topic_id=?, edu_topic_id=?, custom_subject=?, custom_topic=?, time_note=? WHERE id=?")->execute([$amt, $act, $st, $tid, $eduTid, $csub, $ctop, $tn, $id]);
            } else {
                $pdo->prepare("UPDATE schedule_items SET amount=?, action_type=?, status=?, topic_id=?, custom_subject=?, custom_topic=?, time_note=? WHERE id=?")->execute([$amt, $act, $st, $tid, $csub, $ctop, $tn, $id]);
            }
        } else {
            if ($hasEduCol) {
                $pdo->prepare("INSERT INTO schedule_items (student_id, date, amount, action_type, status, topic_id, edu_topic_id, custom_subject, custom_topic, time_note, item_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)")->execute([$sid, $date, $amt, $act, $st, $tid, $eduTid, $csub, $ctop, $tn]);
            } else {
                $pdo->prepare("INSERT INTO schedule_items (student_id, date, amount, action_type, status, topic_id, custom_subject, custom_topic, time_note, item_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)")->execute([$sid, $date, $amt, $act, $st, $tid, $csub, $ctop, $tn]);
            }
        }
        $should_redirect = true;
    }
    if (isset($_POST['delete_schedule'])) { 
        $pdo->prepare("DELETE FROM schedule_items WHERE id=?")->execute([$_POST['schedule_id']]); 
        $should_redirect = true;
    }

    // Deneme İşlemleri
    if (isset($_POST['add_exam']) && $sid) {
        $nm = $_POST['name']; $dt = $_POST['date']; $cat = $_POST['category']; 
        $det = []; $tot = 0;
        if(isset($_POST['lesson_name'])) { 
            foreach($_POST['lesson_name'] as $k=>$l) { 
                $d=(float)($_POST['dogru'][$k]??0); $y=(float)($_POST['yanlis'][$k]??0); $n=$d-($y*0.25); 
                if($d>0||$y>0){ $det[$l]=['d'=>$d,'y'=>$y,'n'=>$n]; $tot+=$n; } 
            } 
        }
        $jdet = json_encode($det, JSON_UNESCAPED_UNICODE); $ftot = !empty($_POST['total_net'])?$_POST['total_net']:$tot;
        if(!empty($_POST['exam_result_id'])) { 
            $pdo->prepare("UPDATE quiz_results SET exam_name=?, date_taken=?, category=?, total_net=?, details=? WHERE id=?")->execute([$nm, $dt, $cat, $ftot, $jdet, $_POST['exam_result_id']]); 
        } else { 
            $pdo->prepare("INSERT INTO quiz_results (student_id, exam_name, date_taken, category, total_net, details) VALUES (?, ?, ?, ?, ?, ?)")->execute([$sid, $nm, $dt, $cat, $ftot, $jdet]); 
        }
        $should_redirect = true;
    }
    if (isset($_POST['delete_exam'])) { 
        $pdo->prepare("DELETE FROM quiz_results WHERE id=?")->execute([$_POST['delete_exam_id']]); 
        $should_redirect = true;
    }

    // --- YÖNLENDİRME (Hayalet Kopyayı Kesin Çözer) ---
    if ($should_redirect) {
        $current_url = strtok($_SERVER["REQUEST_URI"], '?');
        $qs = $_SERVER['QUERY_STRING'];
        header("Location: $current_url?$qs");
        exit;
    }
}

// 5. VERİ ÇEKME
$st = $pdo->prepare("SELECT u.* FROM users u JOIN coaching_relationships cr ON u.id = cr.student_id WHERE cr.teacher_id = ? ORDER BY u.first_name ASC");
$st->execute([$teacher_id]);
$my_students = $st->fetchAll(PDO::FETCH_ASSOC);

if ($sid) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); 
    $stmt->execute([$sid]); 
    $selected_student = $stmt->fetch(PDO::FETCH_ASSOC);
    $student_level = $selected_student['school_level'] ?? 'Lise';

    // PROGRAM VERİLERİ (Sıralama Düzeltildi)
    $sc = $pdo->prepare("
        SELECT si.*, t.name as topic_name, t.subject_id as subject_id, s.name as subject_name, s.category as subject_category 
        FROM schedule_items si 
        LEFT JOIN coaching_topics t ON si.topic_id = t.id 
        LEFT JOIN coaching_subjects s ON t.subject_id = s.id 
        WHERE si.student_id = ? AND si.date BETWEEN ? AND ?
        ORDER BY si.date ASC, si.item_order ASC, si.id ASC
    ");
    $sc->execute([$sid, $week_dates[0], $week_dates[6]]); 
    $raw_items = $sc->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($week_dates as $wd) { 
        $schedule_items[$wd] = array_filter($raw_items, function($i) use ($wd) { 
            return $i['date'] == $wd; 
        }); 
    }

    // İstatistikler ve Analiz
    try {
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        $q_stmt = $pdo->prepare("SELECT SUM(amount) FROM schedule_items WHERE student_id = ? AND action_type = 'soru' AND status = 'yapildi'"); 
        $q_stmt->execute([$sid]); $report_stats['total_q'] = $q_stmt->fetchColumn() ?: 0;
        
        $q_week_stmt = $pdo->prepare("SELECT SUM(amount) FROM schedule_items WHERE student_id = ? AND action_type = 'soru' AND status = 'yapildi' AND date >= ?"); 
        $q_week_stmt->execute([$sid, $week_ago]); $report_stats['week_q'] = $q_week_stmt->fetchColumn() ?: 0;

        $t_stmt = $pdo->prepare("SELECT COUNT(*) FROM schedule_items WHERE student_id = ? AND action_type = 'konu' AND status = 'yapildi'"); 
        $t_stmt->execute([$sid]); $report_stats['total_t'] = $t_stmt->fetchColumn() ?: 0;
        
        $t_week_stmt = $pdo->prepare("SELECT COUNT(*) FROM schedule_items WHERE student_id = ? AND action_type = 'konu' AND status = 'yapildi' AND date >= ?"); 
        $t_week_stmt->execute([$sid, $week_ago]); $report_stats['week_t'] = $t_week_stmt->fetchColumn() ?: 0;

        $topic_stats = [];
        $completed = $pdo->prepare("SELECT si.*, t.id as topic_id, t.name as topic_name, t.subject_id FROM schedule_items si LEFT JOIN coaching_topics t ON si.topic_id = t.id WHERE si.student_id = ? AND si.status = 'yapildi' ORDER BY si.date DESC");
        $completed->execute([$sid]);
        foreach($completed->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $tid = $item['topic_id'];
            if (!$tid && !empty($item['custom_topic'])) $tid = 'custom_' . md5($item['custom_topic']);
            if (!$tid) continue;
            if(!isset($topic_stats[$tid])) $topic_stats[$tid] = ['total_questions'=>0, 'total_topics'=>0, 'history'=>[], 'name'=>$item['topic_name']??$item['custom_topic']];
            if($item['action_type']=='soru') $topic_stats[$tid]['total_questions'] += (int)$item['amount'];
            if($item['action_type']=='konu') $topic_stats[$tid]['total_topics'] += 1;
            $topic_stats[$tid]['history'][] = [
                'date'          => $item['date'],
                'type'          => $item['action_type'],
                'action_type'   => $item['action_type'],
                'amount'        => $item['amount'],
                'target_amount' => $item['target_amount'],
                'correct_count' => $item['correct_count'] ?? null,
                'wrong_count'   => $item['wrong_count']   ?? null,
                'status'        => $item['status'],
            ];
        }

        $catFilter = ($student_level == 'Ortaokul') ? "category = 'LGS'" : "category IN ('TYT', 'AYT')";
        $subs = $pdo->query("SELECT * FROM coaching_subjects WHERE $catFilter ORDER BY category, name")->fetchAll();
        foreach($subs as $sub) {
            $tops = $pdo->prepare("SELECT * FROM coaching_topics WHERE subject_id = ?"); $tops->execute([$sub['id']]); $t_list = $tops->fetchAll();
            $sub_data = ['subject_name'=>$sub['name'], 'name'=>$sub['name'], 'category'=>$sub['category'], 'topics'=>[], 'q_total'=>0, 't_total'=>0];
            foreach($t_list as $t) {
                $stats = $topic_stats[$t['id']] ?? ['total_questions'=>0, 'total_topics'=>0, 'history'=>[]];
                $sub_data['q_total'] += $stats['total_questions'];
                $sub_data['t_total'] += $stats['total_topics'];
                $sub_data['topics'][] = ['id'=>$t['id'], 'name'=>$t['name'], 'q_count'=>$stats['total_questions'], 't_count'=>$stats['total_topics'], 'history'=>$stats['history']];
            }
            $progress_data[] = $sub_data;
        }
    } catch (Exception $e) {}

    try {
        $ex = $pdo->prepare("SELECT * FROM quiz_results WHERE student_id = ? ORDER BY date_taken DESC, id DESC");
        $ex->execute([$sid]);
        $exam_results = $ex->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$all_subjects = []; 
try { $all_subjects = $pdo->query("SELECT * FROM coaching_subjects ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>

<div class="max-w-[98%] mx-auto py-4">

    <?php include 'koc/sidebar.php'; ?>

    <?php if($message): ?>
        <div class="bg-green-50 text-green-700 p-3 rounded-lg mb-3 border border-green-200 text-sm">✅ <?php echo $message; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-3 border border-red-200 text-sm">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ÜST BAR: Öğrenci Seçici + Aksiyonlar -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 px-4 py-3 mb-4">
        <!-- Mobil: flex-wrap | PC (md+): tek satır, tablar sağda -->
        <div class="flex flex-wrap md:flex-nowrap items-center gap-2">

            <!-- Dropdown + Ekle + Bağla -->
            <div class="flex items-center gap-2 w-full md:w-auto">
                <div class="relative w-full md:w-52">
                    <select onchange="window.location.href='?student_id='+this.value+'&date=<?php echo $date_param; ?>'"
                            class="w-full appearance-none bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-8 py-2.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#223488]/20 cursor-pointer">
                        <option value="">👤 Öğrenci Seçin...</option>
                        <?php foreach($my_students as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($sid == $s['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="w-4 h-4 text-slate-400 absolute right-3 top-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>

                <a href="<?php echo BASE_URL; ?>/koc/ogrencilerim.php"
                   class="flex items-center gap-1.5 px-3 py-2.5 bg-[#223488] text-white text-xs font-bold rounded-xl hover:bg-[#314595] transition whitespace-nowrap flex-shrink-0">
                    ➕ Ekle
                </a>

                <button onclick="openModal('linkStudentModal')"
                        class="flex items-center gap-1.5 px-3 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition whitespace-nowrap flex-shrink-0">
                    🔗 Bağla
                </button>
            </div>

            <?php if ($selected_student): ?>
            <!-- Aksiyon butonları -->
            <div class="flex items-center gap-2 flex-wrap">
                <button onclick="sendWhatsappReport()"
                        class="flex items-center gap-1.5 px-3 py-2.5 bg-[#25D366] text-white text-xs font-bold rounded-xl hover:bg-[#128C7E] transition shadow-sm whitespace-nowrap">
                    <i class="fa-brands fa-whatsapp"></i> Veliye Mesaj At
                </button>

                <button onclick="sendStudentMessage()"
                        class="flex items-center gap-1.5 px-3 py-2.5 bg-[#ec9731] text-white text-xs font-bold rounded-xl hover:bg-[#d68625] transition shadow-sm whitespace-nowrap">
                    <i class="fa-regular fa-paper-plane"></i> Öğrenciye Mesaj At
                </button>

                <button onclick="downloadPdfProgram()"
                        class="flex items-center gap-1.5 px-3 py-2.5 bg-[#223488] text-white text-xs font-bold rounded-xl hover:bg-[#314595] transition shadow-sm whitespace-nowrap">
                    <i class="fa-solid fa-file-pdf"></i> PDF İndir
                </button>
            </div>

            <!-- Tabs — masaüstünde üst barda sağda -->
            <div class="ml-auto hidden md:flex bg-slate-100 p-1 rounded-xl border border-slate-200 flex-shrink-0">
                <button onclick="openTab('schedule')" id="tab-schedule-desk" class="px-4 py-1.5 rounded-lg font-bold text-xs transition bg-slate-800 text-white shadow-sm flex items-center gap-2">📅 Program</button>
                <button onclick="openTab('topics')"   id="tab-topics-desk"   class="px-4 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">📊 Analiz</button>
                <button onclick="openTab('exams')"    id="tab-exams-desk"    class="px-4 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">📝 Denemeler</button>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ANA İÇERİK -->
    <div>
        <?php if ($selected_student): ?>
            <!-- Tabs — sadece mobil -->
            <div class="flex justify-end mb-4 md:hidden">
                <div class="flex bg-white p-1 rounded-xl shadow-sm border border-slate-200 w-fit">
                    <button onclick="openTab('schedule')" id="tab-schedule" class="px-4 py-1.5 rounded-lg font-bold text-xs transition bg-slate-800 text-white shadow-sm flex items-center gap-2">📅 Program</button>
                    <button onclick="openTab('topics')"   id="tab-topics"   class="px-4 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">📊 Analiz</button>
                    <button onclick="openTab('exams')"    id="tab-exams"    class="px-4 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">📝 Denemeler</button>
                </div>
            </div>

            <?php include 'koc/program.php'; ?>
            <?php include 'koc/analiz.php'; ?>
            <?php include 'koc/denemeler.php'; ?>

        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-[55vh] bg-white rounded-2xl border border-dashed border-slate-200 text-center p-8">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-4xl mb-4 text-slate-300">🎓</div>
                <h2 class="text-xl font-bold text-slate-700">Öğrenci Seçin</h2>
                <p class="text-slate-400 text-sm mt-2">Yukarıdaki açılır menüden bir öğrenci seçin.</p>
                <?php if(empty($my_students)): ?>
                <a href="<?php echo BASE_URL; ?>/koc/ogrencilerim.php" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 bg-[#223488] text-white text-sm font-bold rounded-xl hover:bg-[#314595] transition">
                    ➕ İlk Öğrencinizi Ekleyin
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'koc/modals.php'; ?>
<?php include 'koc/scripts.php'; ?>
<?php ob_end_flush(); ?>