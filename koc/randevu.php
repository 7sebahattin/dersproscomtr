<?php
// randevu.php (TEACHER) - STABLE VERSION
// Build Timestamp: 2025-01-03 14:15:00
// Fixes: Undefined variable $is_cancelled, HTML attributes breaking, Scope isolation

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Path fallback
 */
$BASE = __DIR__;
$dbPath     = file_exists($BASE . '/db.php') ? ($BASE . '/db.php') : ($BASE . '/../db.php');
$headerPath = file_exists($BASE . '/header.php') ? ($BASE . '/header.php') : ($BASE . '/../header.php');
$footerPath = file_exists($BASE . '/footer.php') ? ($BASE . '/footer.php') : ($BASE . '/../footer.php');

require_once $dbPath;
// NOT: header.php (HTML çıktısı) artık POST işlemeden SONRA dahil ediliyor.
// Böylece Post/Redirect/Get (PRG) deseni için header('Location') kullanılabilir.

/* --- HELPER FUNCTIONS --- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function normalize_time($t){
    $t = trim((string)$t);
    if ($t === '') return '';
    if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t.':00';
    return $t;
}

function getColumns(PDO $pdo, $table){
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try{
        $cols = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $cols[] = $r['Field'];
        return $cache[$table] = $cols;
    } catch(Exception $e){
        return $cache[$table] = [];
    }
}

function pickCol(array $cols, array $candidates){
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return null;
}

/* --- AUTH CHECK --- */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'teacher') {
    header('Location: ../index.php');
    exit;
}
$teacher_id = (int)$_SESSION['user_id'];

/* --- CSRF --- */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

$flash = null; // PRG/toast: ['t'=>tip,'m'=>metin]

/* --- DYNAMIC SCHEMA MAPPING --- */
$colsApps = getColumns($pdo, 'appointments');
$APP_ID       = pickCol($colsApps, ['id']);
$APP_TEACHER  = pickCol($colsApps, ['teacher_id','coach_id']);
$APP_STUDENT  = pickCol($colsApps, ['student_id','learner_id','user_id']);
$APP_DATE     = pickCol($colsApps, ['appointment_date','date']);
$APP_TIME     = pickCol($colsApps, ['appointment_time','time']);
$APP_DUR      = pickCol($colsApps, ['duration','minutes']);
$APP_STATUS   = pickCol($colsApps, ['status','appointment_status']);
$APP_ISCANCEL = pickCol($colsApps, ['is_cancelled','cancelled']);
$APP_PRIVATE  = pickCol($colsApps, ['private_note']);
$APP_PUBLIC   = pickCol($colsApps, ['public_note','note']);
$APP_RECUR    = pickCol($colsApps, ['is_recurring','recurring']);
$APP_GROUP    = pickCol($colsApps, ['group_id','recurring_group_id']);

$needApps = [$APP_ID,$APP_TEACHER,$APP_STUDENT,$APP_DATE,$APP_TIME,$APP_DUR];
if (in_array(null, $needApps, true)) {
    include $headerPath;
    echo "<div class='max-w-3xl mx-auto p-6'>
            <div class='bg-red-50 text-red-700 border border-red-100 p-5 rounded-3xl font-black'>
              ⛔ appointments tablosunda zorunlu kolon(lar) eksik.
            </div>
          </div>";
    include $footerPath;
    exit;
}

$colsReq = getColumns($pdo, 'appointment_requests');
$REQ_ID         = pickCol($colsReq, ['id']);
$REQ_APP_ID     = pickCol($colsReq, ['appointment_id','app_id']);
$REQ_STATUS     = pickCol($colsReq, ['status','request_status']);
$REQ_TYPE       = pickCol($colsReq, ['type','request_type']);
$REQ_MESSAGE    = pickCol($colsReq, ['message','note','reason','request_message']);
$REQ_PDATE      = pickCol($colsReq, ['proposed_date','new_date','requested_date']);
$REQ_PTIME      = pickCol($colsReq, ['proposed_time','new_time','requested_time']);
$REQ_PDUR       = pickCol($colsReq, ['proposed_duration','new_duration','requested_duration','duration']);
$REQ_REQUESTER  = pickCol($colsReq, ['requester_user_id','requester_id','user_id','student_id','parent_id']);
$REQ_ROLE       = pickCol($colsReq, ['requester_role','role']);
$REQ_TRESP      = pickCol($colsReq, ['teacher_response','response','teacher_note']);
$REQ_DECIDED_AT = pickCol($colsReq, ['decided_at','resolved_at','updated_at']);
$REQ_CREATED_AT = pickCol($colsReq, ['created_at','request_date','created']);

/* --- ÖDEME KÖPRÜSÜ İÇİN ŞEMA HAZIRLIĞI (idempotent, additive; hiçbir şey bozulmaz) ---
   randevu -> ödeme bağı: randevu günü gelip iptal edilmezse ileride otomatik
   payments kaydı üretilecek. Şimdilik yalnızca kolonlar garanti ediliyor. */
try {
    if (!in_array('price', $colsApps, true)) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN price DECIMAL(10,2) NULL DEFAULT NULL");
        $colsApps[] = 'price';
    }
    $colsPay = getColumns($pdo, 'payments');
    if ($colsPay && !in_array('appointment_id', $colsPay, true)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN appointment_id INT NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE payments ADD KEY idx_pay_appt (appointment_id)");
    }
} catch (Throwable $e) { /* yetki yoksa sayfa eski haliyle çalışmaya devam eder */ }
$APP_PRICE = pickCol($colsApps, ['price']);

function app_status_expr($APP_STATUS, $APP_ISCANCEL){
    if ($APP_STATUS) return "COALESCE(a.`$APP_STATUS`, 'active')";
    if ($APP_ISCANCEL) return "IF(a.`$APP_ISCANCEL`=1,'cancelled','active')";
    return "'active'";
}

/* --- CONFLICT CHECK --- */
function has_conflict(PDO $pdo, int $teacher_id, string $date, string $new_start, string $new_end, ?int $exclude_id,
                      string $APP_TEACHER, string $APP_DATE, string $APP_TIME, string $APP_DUR, ?string $APP_STATUS, ?string $APP_ISCANCEL, string $APP_ID) {

    $whereNotCancelled = "1=1";
    if ($APP_STATUS) {
        $whereNotCancelled = "(a.`$APP_STATUS` IS NULL OR a.`$APP_STATUS` != 'cancelled')";
    } elseif ($APP_ISCANCEL) {
        $whereNotCancelled = "(a.`$APP_ISCANCEL` IS NULL OR a.`$APP_ISCANCEL` = 0)";
    }

    $sql = "
        SELECT COUNT(*)
        FROM appointments a
        WHERE a.`$APP_TEACHER` = ?
          AND a.`$APP_DATE` = ?
          AND $whereNotCancelled
          " . ($exclude_id ? " AND a.`$APP_ID` != ? " : "") . "
          AND (a.`$APP_TIME` < ?)
          AND (ADDTIME(a.`$APP_TIME`, SEC_TO_TIME(IFNULL(a.`$APP_DUR`,0)*60)) > ?)
    ";

    $params = [$teacher_id, $date];
    if ($exclude_id) $params[] = $exclude_id;
    $params[] = $new_end;
    $params[] = $new_start;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return ((int)$st->fetchColumn() > 0);
}

/* --- AJAX: CANLI ÇAKIŞMA ÖN-KONTROLÜ (modalda saat seçilirken) ---
   JSON döner, header'dan önce çalışır ve çıkar. */
if (($_GET['ajax'] ?? '') === 'check_conflict') {
    header('Content-Type: application/json; charset=utf-8');
    $c_date = (string)($_GET['date'] ?? '');
    $c_time = normalize_time($_GET['time'] ?? '');
    $c_dur  = (int)($_GET['duration'] ?? 0);
    $c_excl = !empty($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;
    if (!$c_date || !$c_time || $c_dur < 5) { echo json_encode(['ok'=>false]); exit; }

    $c_start = $c_time;
    $c_end   = date('H:i:s', strtotime("$c_time +$c_dur minutes"));

    // Çakışan randevunun kimle/ne zaman olduğunu da döndürelim
    $whereNotCancelled = "1=1";
    if ($APP_STATUS)       $whereNotCancelled = "(a.`$APP_STATUS` IS NULL OR a.`$APP_STATUS` != 'cancelled')";
    elseif ($APP_ISCANCEL) $whereNotCancelled = "(a.`$APP_ISCANCEL` IS NULL OR a.`$APP_ISCANCEL` = 0)";
    $sqlC = "SELECT u.first_name, u.last_name, a.`$APP_TIME` AS t, a.`$APP_DUR` AS d
             FROM appointments a JOIN users u ON a.`$APP_STUDENT` = u.id
             WHERE a.`$APP_TEACHER` = ? AND a.`$APP_DATE` = ? AND $whereNotCancelled
               ".($c_excl ? " AND a.`$APP_ID` != ? " : "")."
               AND (a.`$APP_TIME` < ?) AND (ADDTIME(a.`$APP_TIME`, SEC_TO_TIME(IFNULL(a.`$APP_DUR`,0)*60)) > ?)
             LIMIT 1";
    $pC = [$teacher_id, $c_date]; if ($c_excl) $pC[] = $c_excl; $pC[] = $c_end; $pC[] = $c_start;
    $stC = $pdo->prepare($sqlC); $stC->execute($pC);
    $hit = $stC->fetch(PDO::FETCH_ASSOC);
    if ($hit) {
        $who = trim(($hit['first_name'] ?? '').' '.($hit['last_name'] ?? ''));
        $ht  = date('H:i', strtotime($hit['t']));
        $he  = date('H:i', strtotime($hit['t'].' +'.((int)$hit['d']).' minutes'));
        echo json_encode(['ok'=>true, 'conflict'=>true, 'who'=>$who, 'range'=>"$ht–$he"]);
    } else {
        echo json_encode(['ok'=>true, 'conflict'=>false]);
    }
    exit;
}

/* --- POST ACTIONS --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf, $post_csrf)) {
        $flash = ['t'=>'error','m'=>'⚠️ CSRF doğrulaması başarısız.'];
    } else {

        // (A) Approve / Reject request
        if (isset($_POST['approve_request']) || isset($_POST['reject_request'])) {
            $req_id = (int)($_POST['request_id'] ?? 0);
            $resp_text = trim((string)($_POST['teacher_response'] ?? ''));

            if (!$REQ_ID || !$REQ_APP_ID || !$REQ_STATUS || !$REQ_TYPE) {
                $flash = ['t'=>'error','m'=>'⚠️ appointment_requests kolonları eksik.'];
            } else {
                $sql = "
                    SELECT
                        r.`$REQ_ID` AS req_id,
                        r.`$REQ_APP_ID` AS appointment_id,
                        r.`$REQ_STATUS` AS req_status,
                        r.`$REQ_TYPE` AS req_type,
                        ".($REQ_MESSAGE ? "r.`$REQ_MESSAGE` AS req_message," : "NULL AS req_message,")."
                        ".($REQ_PDATE ? "r.`$REQ_PDATE` AS proposed_date," : "NULL AS proposed_date,")."
                        ".($REQ_PTIME ? "r.`$REQ_PTIME` AS proposed_time," : "NULL AS proposed_time,")."
                        ".($REQ_PDUR  ? "r.`$REQ_PDUR` AS proposed_duration," : "NULL AS proposed_duration,")."
                        ".($REQ_TRESP ? "r.`$REQ_TRESP` AS teacher_response," : "NULL AS teacher_response,")."
                        a.`$APP_ID` AS app_id,
                        a.`$APP_TEACHER` AS teacher_id,
                        a.`$APP_STUDENT` AS student_id,
                        a.`$APP_DATE` AS appointment_date,
                        a.`$APP_TIME` AS appointment_time,
                        a.`$APP_DUR`  AS duration,
                        ".app_status_expr($APP_STATUS,$APP_ISCANCEL)." AS app_status
                    FROM appointment_requests r
                    JOIN appointments a ON r.`$REQ_APP_ID` = a.`$APP_ID`
                    WHERE r.`$REQ_ID` = ? AND a.`$APP_TEACHER` = ?
                    LIMIT 1
                ";
                $rq = $pdo->prepare($sql);
                $rq->execute([$req_id, $teacher_id]);
                $row = $rq->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $flash = ['t'=>'error','m'=>'⛔ Talep bulunamadı.'];
                } else {
                    $type = (string)($row['req_type'] ?? '');
                    $isApprove = isset($_POST['approve_request']);

                    if ($isApprove) {
                        $newStatus = null;
                        if ($type === 'cancel') {
                            if ($APP_STATUS) {
                                $pdo->prepare("UPDATE appointments SET `$APP_STATUS`='cancelled' WHERE `$APP_ID`=? AND `$APP_TEACHER`=?")->execute([(int)$row['appointment_id'], $teacher_id]);
                            } elseif ($APP_ISCANCEL) {
                                $pdo->prepare("UPDATE appointments SET `$APP_ISCANCEL`=1 WHERE `$APP_ID`=? AND `$APP_TEACHER`=?")->execute([(int)$row['appointment_id'], $teacher_id]);
                            }
                            $newStatus = 'approved';
                            $flash = ['t'=>'success','m'=>'✅ Talep onaylandı (Randevu İptal).'];
                        } elseif ($type === 'reschedule') {
                            $new_date = !empty($row['proposed_date']) ? (string)$row['proposed_date'] : (string)$row['appointment_date'];
                            $new_time = !empty($row['proposed_time']) ? normalize_time($row['proposed_time']) : (string)$row['appointment_time'];
                            $new_dur  = !empty($row['proposed_duration']) ? (int)$row['proposed_duration'] : (int)$row['duration'];

                            $check_start = $new_time;
                            $check_end = date('H:i:s', strtotime("$new_time +$new_dur minutes"));

                            if (has_conflict($pdo, $teacher_id, $new_date, $check_start, $check_end, (int)$row['appointment_id'],
                                $APP_TEACHER, $APP_DATE, $APP_TIME, $APP_DUR, $APP_STATUS, $APP_ISCANCEL, $APP_ID)) {
                                $flash = ['t'=>'error','m'=>'⚠️ Çakışma var.'];
                                $newStatus = null;
                            } else {
                                $pdo->prepare("UPDATE appointments SET `$APP_DATE`=?, `$APP_TIME`=?, `$APP_DUR`=? WHERE `$APP_ID`=? AND `$APP_TEACHER`=?")
                                    ->execute([$new_date, $new_time, $new_dur, (int)$row['appointment_id'], $teacher_id]);
                                $newStatus = 'approved';
                                $flash = ['t'=>'success','m'=>'✅ Değişiklik onaylandı.'];
                            }
                        } else {
                            $flash = ['t'=>'error','m'=>'⚠️ Bilinmeyen talep tipi.'];
                        }

                        if ($newStatus) {
                            $fields = ["`$REQ_STATUS`=?"];
                            $vals   = [$newStatus];
                            if ($REQ_TRESP) { $fields[] = "`$REQ_TRESP`=?"; $vals[] = $resp_text; }
                            if ($REQ_DECIDED_AT) { $fields[] = "`$REQ_DECIDED_AT`=NOW()"; }
                            $vals[] = $req_id;
                            $pdo->prepare("UPDATE appointment_requests SET ".implode(',', $fields)." WHERE `$REQ_ID`=?")->execute($vals);
                        }
                    } else {
                        // Reject
                        $fields = ["`$REQ_STATUS`=?"];
                        $vals   = ['rejected'];
                        if ($REQ_TRESP) { $fields[]="`$REQ_TRESP`=?"; $vals[]=$resp_text; }
                        if ($REQ_DECIDED_AT) { $fields[]="`$REQ_DECIDED_AT`=NOW()"; }
                        $vals[] = $req_id;
                        $pdo->prepare("UPDATE appointment_requests SET ".implode(',', $fields)." WHERE `$REQ_ID`=?")->execute($vals);
                        $flash = ['t'=>'warning','m'=>'🚫 Talep reddedildi.'];
                    }
                }
            }
        }

        // (B) Cancel Appointment
        if (isset($_POST['cancel_appointment'])) {
            $app_id = (int)($_POST['appointment_id'] ?? 0);
            if ($APP_STATUS) {
                $pdo->prepare("UPDATE appointments SET `$APP_STATUS`='cancelled' WHERE `$APP_ID`=? AND `$APP_TEACHER`=?")->execute([$app_id, $teacher_id]);
            } elseif ($APP_ISCANCEL) {
                $pdo->prepare("UPDATE appointments SET `$APP_ISCANCEL`=1 WHERE `$APP_ID`=? AND `$APP_TEACHER`=?")->execute([$app_id, $teacher_id]);
            }
            $flash = ['t'=>'warning','m'=>'🚫 Randevu iptal edildi.'];
        }

        // (B2) Complete Appointment — "Tamamla" (yalnızca status kolonu varsa)
        if (isset($_POST['complete_appointment']) && $APP_STATUS) {
            $app_id = (int)($_POST['appointment_id'] ?? 0);
            $pdo->prepare("UPDATE appointments SET `$APP_STATUS`='completed' WHERE `$APP_ID`=? AND `$APP_TEACHER`=? AND `$APP_STATUS`!='cancelled'")
                ->execute([$app_id, $teacher_id]);
            $flash = ['t'=>'success','m'=>'✅ Randevu tamamlandı olarak işaretlendi.'];
        }

        // (C) Add Appointment
        if (isset($_POST['add_appointment'])) {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $date = (string)($_POST['date'] ?? '');
            $time = normalize_time($_POST['time'] ?? '');
            $duration = (int)($_POST['duration'] ?? 30);
            $p_note = trim((string)($_POST['private_note'] ?? ''));
            $recurring = isset($_POST['is_recurring']);

            if ($student_id <= 0 || !$date || !$time || $duration < 5) {
                $flash = ['t'=>'error','m'=>'⚠️ Eksik bilgi.'];
            } else {
                $check_start = $time;
                $check_end = date('H:i:s', strtotime("$time +$duration minutes"));

                if (has_conflict($pdo, $teacher_id, $date, $check_start, $check_end, null, $APP_TEACHER, $APP_DATE, $APP_TIME, $APP_DUR, $APP_STATUS, $APP_ISCANCEL, $APP_ID)) {
                    $flash = ['t'=>'error','m'=>'⚠️ Çakışma var!'];
                } else {
                    $repeat_count = ($recurring && $APP_RECUR) ? 4 : 1;
                    $group_id = ($recurring && $APP_GROUP) ? uniqid('grp_') : null;

                    $cols = []; $vals = []; $qms  = [];
                    $cols[] = "`$APP_TEACHER`"; $vals[] = $teacher_id; $qms[]='?';
                    $cols[] = "`$APP_STUDENT`"; $vals[] = $student_id; $qms[]='?';
                    $cols[] = "`$APP_DATE`";    $vals[] = $date;       $qms[]='?';
                    $cols[] = "`$APP_TIME`";    $vals[] = $time;       $qms[]='?';
                    $cols[] = "`$APP_DUR`";     $vals[] = $duration;   $qms[]='?';
                    if ($APP_PRIVATE) { $cols[]="`$APP_PRIVATE`"; $vals[] = ($p_note!==''?$p_note:null); $qms[]='?'; }
                    if ($APP_RECUR && $recurring) { $cols[]="`$APP_RECUR`"; $vals[] = 1; $qms[]='?'; }
                    if ($APP_GROUP && $recurring) { $cols[]="`$APP_GROUP`"; $vals[] = $group_id; $qms[]='?'; }

                    $sqlIns = "INSERT INTO appointments (".implode(',',$cols).") VALUES (".implode(',',$qms).")";
                    $insert = $pdo->prepare($sqlIns);
                    $okAll = true;

                    for ($i = 0; $i < $repeat_count; $i++) {
                        $target_date = date('Y-m-d', strtotime("$date +$i weeks"));
                        if (has_conflict($pdo, $teacher_id, $target_date, $check_start, $check_end, null, $APP_TEACHER, $APP_DATE, $APP_TIME, $APP_DUR, $APP_STATUS, $APP_ISCANCEL, $APP_ID)) {
                            $flash = ['t'=>'error','m'=>'⚠️ Tekrarlı randevu çakıştı ($target_date).'];
                            $okAll = false; 
                            break;
                        }
                        $valsIter = $vals; 
                        $valsIter[2] = $target_date;
                        $insert->execute($valsIter);
                    }
                    if ($okAll && $flash === null) $flash = ['t'=>'success','m'=>'Randevu oluşturuldu.'];
                }
            }
        }

        // (D) Edit Appointment
        if (isset($_POST['edit_appointment'])) {
            $app_id = (int)($_POST['appointment_id'] ?? 0);
            $student_id = (int)($_POST['student_id'] ?? 0);
            $date = (string)($_POST['date'] ?? '');
            $time = normalize_time($_POST['time'] ?? '');
            $duration = (int)($_POST['duration'] ?? 30);
            $p_note = trim((string)($_POST['private_note'] ?? ''));

            if ($app_id <= 0 || $student_id <= 0 || !$date || !$time || $duration < 5) {
                $flash = ['t'=>'error','m'=>'⚠️ Eksik bilgi.'];
            } else {
                $check_start = $time;
                $check_end = date('H:i:s', strtotime("$time +$duration minutes"));

                if (has_conflict($pdo, $teacher_id, $date, $check_start, $check_end, $app_id, $APP_TEACHER, $APP_DATE, $APP_TIME, $APP_DUR, $APP_STATUS, $APP_ISCANCEL, $APP_ID)) {
                    $flash = ['t'=>'error','m'=>'⚠️ Çakışma var.'];
                } else {
                    $fields = []; $vals = [];
                    $fields[] = "`$APP_STUDENT`=?"; $vals[] = $student_id;
                    $fields[] = "`$APP_DATE`=?";    $vals[] = $date;
                    $fields[] = "`$APP_TIME`=?";    $vals[] = $time;
                    $fields[] = "`$APP_DUR`=?";     $vals[] = $duration;
                    if ($APP_PRIVATE) { $fields[]="`$APP_PRIVATE`=?"; $vals[] = ($p_note!==''?$p_note:null); }
                    
                    $vals[] = $app_id;
                    $vals[] = $teacher_id;

                    $sqlUp = "UPDATE appointments SET ".implode(',', $fields)." WHERE `$APP_ID`=? AND `$APP_TEACHER`=?";
                    $pdo->prepare($sqlUp)->execute($vals);
                    $flash = ['t'=>'info','m'=>'✏️ Randevu güncellendi.'];
                }
            }
        }
    }

    /* --- POST/REDIRECT/GET: çift gönderimi (F5) önler, sonucu toast olarak taşır --- */
    if ($flash !== null) { $_SESSION['flash'] = $flash; }
    $redirect_to = $_SERVER['REQUEST_URI'] ?: (($_SERVER['PHP_SELF'] ?? 'randevu.php'));
    header('Location: ' . $redirect_to);
    exit;
}

/* --- GET: bir önceki işlemin flash mesajını al (PRG) --- */
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

/* --- LOAD DATA --- */
$students = $pdo->prepare("
  SELECT u.id, u.first_name, u.last_name
  FROM users u
  JOIN coaching_relationships cr ON u.id = cr.student_id
  WHERE cr.teacher_id = ?
  ORDER BY u.first_name ASC, u.last_name ASC
");
$students->execute([$teacher_id]);
$my_students = $students->fetchAll(PDO::FETCH_ASSOC);

$selected_date = $_GET['date'] ?? date('Y-m-d');
$week_start = date('Y-m-d', strtotime($selected_date));
$week_end   = date('Y-m-d', strtotime("$week_start +6 days"));
$prev_week  = date('Y-m-d', strtotime('-7 days', strtotime($week_start)));
$next_week  = date('Y-m-d', strtotime('+7 days', strtotime($week_start)));
$gunlerTR = ['Monday'=>'Pazartesi','Tuesday'=>'Salı','Wednesday'=>'Çarşamba','Thursday'=>'Perşembe','Friday'=>'Cuma','Saturday'=>'Cumartesi','Sunday'=>'Pazar'];

$sqlApps = "
  SELECT
    a.`$APP_ID` AS id,
    a.`$APP_STUDENT` AS student_id,
    a.`$APP_DATE` AS appointment_date,
    a.`$APP_TIME` AS appointment_time,
    a.`$APP_DUR`  AS duration,
    ".app_status_expr($APP_STATUS,$APP_ISCANCEL)." AS status
    ".($APP_PRIVATE ? ", a.`$APP_PRIVATE` AS private_note" : ", NULL AS private_note")."
    ".($APP_PUBLIC  ? ", a.`$APP_PUBLIC` AS public_note"  : ", NULL AS public_note")."
    ,
    u.first_name, u.last_name
  FROM appointments a
  JOIN users u ON a.`$APP_STUDENT` = u.id
  WHERE a.`$APP_TEACHER` = ?
    AND a.`$APP_DATE` BETWEEN ? AND ?
  ORDER BY a.`$APP_DATE` ASC, a.`$APP_TIME` ASC
";
$app_stmt = $pdo->prepare($sqlApps);
$app_stmt->execute([$teacher_id, $week_start, $week_end]);
$appointments = $app_stmt->fetchAll(PDO::FETCH_ASSOC);

/* --- ÖDEME DURUMU HARİTASI (randevuya bağlı payments kaydı) --- */
$pay_by_appt = [];
$colsPay2 = getColumns($pdo, 'payments');
if ($colsPay2 && in_array('appointment_id', $colsPay2, true) && $appointments) {
    $ids = array_values(array_unique(array_map(fn($a)=>(int)$a['id'], $appointments)));
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            $pst = $pdo->prepare("SELECT appointment_id, status, amount FROM payments WHERE teacher_id = ? AND appointment_id IN ($ph)");
            $pst->execute(array_merge([$teacher_id], $ids));
            foreach ($pst->fetchAll(PDO::FETCH_ASSOC) as $pr) {
                $pay_by_appt[(int)$pr['appointment_id']] = ['status'=>(string)$pr['status'], 'amount'=>(float)$pr['amount']];
            }
        } catch (Throwable $e) {}
    }
}

/* Öğrenci başına ders ücreti (ödeme talebi tutarı için) */
$lesson_prices = [];
try {
    $lp = $pdo->prepare("SELECT student_id, lesson_price FROM coaching_relationships WHERE teacher_id = ?");
    $lp->execute([$teacher_id]);
    foreach ($lp->fetchAll(PDO::FETCH_ASSOC) as $r) $lesson_prices[(int)$r['student_id']] = (float)$r['lesson_price'];
} catch (Throwable $e) {}

/* Randevu için görüntülenecek türetilmiş durum: confirmed | live | done | cancel */
function appt_view_status(array $app): string {
    $s = (string)($app['status'] ?? 'active');
    if ($s === 'cancelled') return 'cancel';
    if ($s === 'completed') return 'done';
    $start = strtotime($app['appointment_date'].' '.$app['appointment_time']);
    $end   = $start + ((int)($app['duration'] ?? 0)) * 60;
    $now   = time();
    if ($now >= $start && $now < $end) return 'live';
    return 'confirmed';
}

$grouped_appointments = [];
$daily_counts_active = [];
$daily_counts_total = [];
$week_stats = ['total'=>0,'active'=>0,'cancelled'=>0,'minutes'=>0];

foreach ($appointments as $app) {
    $d = (string)$app['appointment_date'];
    $status = (string)($app['status'] ?? 'active');
    $dur = (int)($app['duration'] ?? 0);

    $grouped_appointments[$d][] = $app;
    $daily_counts_total[$d] = ($daily_counts_total[$d] ?? 0) + 1;

    if ($status === 'cancelled') {
        $week_stats['cancelled']++;
    } else {
        $daily_counts_active[$d] = ($daily_counts_active[$d] ?? 0) + 1;
        $week_stats['active']++;
        $week_stats['minutes'] += $dur;
    }
    $week_stats['total']++;
}

$week_days = [];
$dtStart = new DateTime($week_start);
for($i=0;$i<7;$i++){ $tmp=clone $dtStart; $tmp->modify("+$i day"); $week_days[]=$tmp; }

/* --- PENDING REQUESTS --- */
$pending_requests = [];
if ($REQ_ID && $REQ_APP_ID && $REQ_STATUS) {
    $orderExpr = $REQ_CREATED_AT ? "r.`$REQ_CREATED_AT` DESC" : "r.`$REQ_ID` DESC";
    $sqlPending = "
      SELECT
        r.`$REQ_ID` AS id, r.`$REQ_APP_ID` AS appointment_id, r.`$REQ_STATUS` AS status,
        ".($REQ_TYPE ? "r.`$REQ_TYPE` AS type," : "NULL AS type,")."
        ".($REQ_MESSAGE ? "r.`$REQ_MESSAGE` AS message," : "NULL AS message,")."
        ".($REQ_PDATE ? "r.`$REQ_PDATE` AS proposed_date," : "NULL AS proposed_date,")."
        ".($REQ_PTIME ? "r.`$REQ_PTIME` AS proposed_time," : "NULL AS proposed_time,")."
        ".($REQ_PDUR  ? "r.`$REQ_PDUR` AS proposed_duration," : "NULL AS proposed_duration,")."
        a.`$APP_DATE` AS appointment_date, a.`$APP_TIME` AS appointment_time, a.`$APP_DUR` AS duration,
        s.first_name AS s_fn, s.last_name AS s_ln
      FROM appointment_requests r
      JOIN appointments a ON r.`$REQ_APP_ID` = a.`$APP_ID`
      JOIN users s ON a.`$APP_STUDENT` = s.id
      WHERE a.`$APP_TEACHER` = ? AND r.`$REQ_STATUS` = 'pending'
      ORDER BY $orderExpr LIMIT 50
    ";
    $rq = $pdo->prepare($sqlPending);
    $rq->execute([$teacher_id]);
    $pending_requests = $rq->fetchAll(PDO::FETCH_ASSOC);
}
$week_badge = ($week_start === date('Y-m-d')) ? "BU HAFTA" : "SEÇİLİ 7 GÜN";

/* HTML çıktısı buradan başlıyor — header artık burada dahil ediliyor (PRG için gerekliydi) */
include $headerPath;
?>
<style>
:root{
  --atla-primary:#223488; --atla-primary-600:#314595; --atla-primary-050:#eef1fb;
  --atla-accent:#ec9731;  --atla-accent-600:#d68625;  --atla-accent-050:#fdf3e7;
  --success:#059669; --success-050:#ecfdf5;
  --warning:#d97706; --warning-050:#fffbeb;
  --error:#dc2626;   --error-050:#fef2f2;
}
.custom-scrollbar::-webkit-scrollbar { width: 10px; height: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(100,116,139,.25); border-radius: 999px; border: 3px solid rgba(255,255,255,.6); }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.no-scrollbar::-webkit-scrollbar{display:none} .no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
/* Toast */
#toastWrap{position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;gap:8px;width:min(92vw,420px)}
.toast{display:flex;align-items:center;gap:.6rem;padding:.85rem 1rem;border-radius:14px;font-weight:600;font-size:.875rem;box-shadow:0 10px 25px -5px rgba(0,0,0,.2);border:1px solid;animation:toastIn .35s cubic-bezier(.2,.8,.2,1)}
@keyframes toastIn{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:none}}
.toast.success{background:var(--success-050);color:var(--success);border-color:#a7f3d0}
.toast.error{background:var(--error-050);color:var(--error);border-color:#fecaca}
.toast.warning{background:var(--warning-050);color:var(--warning);border-color:#fde68a}
.toast.info{background:var(--atla-primary-050);color:var(--atla-primary);border-color:#c7d2fe}
/* Hafta günü hücresi yoğunluk noktası */
.wk-day{scroll-snap-align:center}
/* Randevu kartı + çipler (UI Kit) */
.appt{background:#fff;border:1px solid var(--border,#e2e8f0);border-left-width:5px;border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.05);transition:box-shadow .18s,transform .18s}
.appt:hover{box-shadow:0 4px 6px -1px rgba(0,0,0,.1);transform:translateY(-1px)}
.bar-confirmed{border-left-color:var(--atla-primary)}
.bar-live{border-left-color:var(--success)}
.bar-done{border-left-color:#94a3b8}
.bar-cancel{border-left-color:var(--error)}
.appt.is-cancel{opacity:.72}
.chip{display:inline-flex;align-items:center;gap:.3rem;font-weight:700;font-size:.66rem;padding:.24rem .55rem;border-radius:999px;border:1px solid;text-transform:uppercase;letter-spacing:.02em;white-space:nowrap}
.chip-confirmed{background:var(--atla-primary-050);color:var(--atla-primary);border-color:#c7d2fe}
.chip-live{background:var(--success-050);color:var(--success);border-color:#a7f3d0}
.chip-done{background:#f1f5f9;color:#64748b;border-color:#e2e8f0}
.chip-cancel{background:var(--error-050);color:var(--error);border-color:#fecaca}
.chip-pay-none{background:#f1f5f9;color:#94a3b8;border-color:#e2e8f0}
.chip-pay-wait{background:var(--warning-050);color:var(--warning);border-color:#fde68a}
.chip-pay-paid{background:var(--success-050);color:var(--success);border-color:#a7f3d0}
.pulse-dot{width:7px;height:7px;border-radius:999px;background:var(--success);animation:pulse 1.6s infinite}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(5,150,105,.5)}70%{box-shadow:0 0 0 7px rgba(5,150,105,0)}100%{box-shadow:0 0 0 0 rgba(5,150,105,0)}}
/* Kart aksiyon butonu */
.act{display:inline-flex;align-items:center;gap:.3rem;min-height:44px;padding:0 .9rem;border-radius:12px;font-weight:700;font-size:.75rem;background:#fff;border:1px solid #e2e8f0;color:#334155;transition:all .15s;white-space:nowrap;cursor:pointer}
.act:hover{background:var(--surface,#f8fafc);border-color:#cbd5e1}
.act:focus-visible{outline:3px solid var(--atla-primary-050);outline-offset:1px}
.act-done:hover{border-color:#a7f3d0;color:var(--success)}
.act-edit:hover{border-color:#c7d2fe;color:var(--atla-primary)}
.act-cancel:hover{border-color:#fecaca;color:var(--error)}
.act-pay:hover{border-color:#fde68a;color:var(--warning)}
</style>

<div id="toastWrap" aria-live="polite" aria-atomic="true"></div>

<div class="min-h-screen bg-slate-50 font-['Poppins'] pb-24">
  <div class="max-w-7xl mx-auto p-4 md:p-6">

    <!-- ════ KATMAN 1: KOMUTA ŞERİDİ (ATLA lacivert) ════ -->
    <div class="rounded-3xl p-5 md:p-6 mb-5 text-white shadow-lg" style="background:linear-gradient(135deg,var(--atla-primary),var(--atla-primary-600))">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="min-w-0">
          <h1 class="text-xl md:text-2xl font-extrabold tracking-tight">Randevu Yönetimi</h1>
          <p class="text-white/70 text-xs md:text-sm font-semibold mt-0.5">Haftalık plan · <?php echo h(date('d.m.Y', strtotime($week_start)) . ' – ' . date('d.m.Y', strtotime($week_end))); ?></p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <a href="?date=<?php echo h($prev_week); ?>" aria-label="Önceki hafta"
             class="w-11 h-11 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 border border-white/10 transition text-lg font-black">‹</a>
          <div class="text-center px-3 min-w-[7rem]">
            <span class="block text-[11px] font-extrabold uppercase tracking-wide"><?php echo h($week_badge); ?></span>
            <span class="block text-[11px] text-white/70 font-semibold"><?php echo h(date('d M', strtotime($week_start))); ?> – <?php echo h(date('d M', strtotime($week_end))); ?></span>
          </div>
          <a href="?date=<?php echo h($next_week); ?>" aria-label="Sonraki hafta"
             class="w-11 h-11 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 border border-white/10 transition text-lg font-black">›</a>
          <a href="?date=<?php echo h(date('Y-m-d')); ?>"
             class="h-11 px-4 flex items-center rounded-xl bg-white text-[color:var(--atla-primary)] font-extrabold text-sm hover:bg-white/90 transition shadow-sm">Bugün</a>
        </div>
      </div>
    </div>

    <!-- ════ KATMAN 2: AKILLI İSTATİSTİK KARTLARI ════ -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <?php
        $statCards = [
          ['Toplam','📅', (int)$week_stats['total'],     'var(--atla-primary)', ''],
          ['Aktif','✅',  (int)$week_stats['active'],     'var(--success)',      ''],
          ['İptal','✕',   (int)$week_stats['cancelled'],  'var(--error)',        ''],
          ['Süre','⏱',   (int)$week_stats['minutes'],    'var(--atla-accent)',  'dk'],
        ];
        foreach($statCards as $sc): [$lbl,$ic,$val,$col,$suf]=$sc; ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400"><?php echo h($lbl); ?></span>
            <span aria-hidden="true"><?php echo $ic; ?></span>
          </div>
          <div class="text-2xl font-extrabold mt-1 count-up" data-target="<?php echo (int)$val; ?>" style="color:<?php echo $col; ?>">0<?php if($suf): ?><span class="text-sm font-bold text-slate-400"> <?php echo h($suf); ?></span><?php endif; ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ════ KATMAN 3: HAFTA ŞERİDİ (yatay kaydırmalı · yoğunluk noktalı) ════ -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 mb-6">
      <div class="flex gap-2 overflow-x-auto no-scrollbar" style="scroll-snap-type:x mandatory">
        <?php foreach ($week_days as $dt):
          $curr = $dt->format('Y-m-d');
          $is_today = ($curr === date('Y-m-d'));
          $dens = $daily_counts_total[$curr] ?? 0;
          $day_name = mb_substr($gunlerTR[$dt->format('l')], 0, 3, 'UTF-8');
          $dotCol = $dens===0 ? '#cbd5e1' : ($dens<=2 ? 'var(--success)' : 'var(--atla-accent)');
        ?>
          <button type="button" onclick="scrollToDay('<?php echo h($curr); ?>')"
            class="wk-day shrink-0 flex-1 min-w-[3.4rem] h-[4.6rem] rounded-xl flex flex-col items-center justify-center gap-1 border transition"
            style="<?php echo $is_today ? 'background:var(--atla-primary);color:#fff;border-color:var(--atla-primary)' : 'background:#fff;border-color:var(--border,#e2e8f0);color:#334155'; ?>"
            aria-label="<?php echo h($gunlerTR[$dt->format('l')].' '.$dt->format('d').', '.$dens.' randevu'); ?>">
            <span class="text-[10px] font-extrabold uppercase opacity-80"><?php echo h($day_name); ?></span>
            <span class="text-base font-extrabold"><?php echo h($dt->format('d')); ?></span>
            <span class="flex gap-0.5">
              <?php for($k=0;$k<3;$k++): ?>
                <span style="width:5px;height:5px;border-radius:999px;background:<?php echo $k<$dens ? ($is_today?'#fff':$dotCol) : ($is_today?'rgba(255,255,255,.3)':'#e2e8f0'); ?>"></span>
              <?php endfor; ?>
            </span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <div class="space-y-6">

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 max-h-[420px] overflow-y-auto custom-scrollbar">
          <h3 class="font-extrabold text-slate-800 mb-4 flex items-center justify-between">
            <span class="flex items-center gap-2">🔔 Bekleyen Talepler</span>
            <span class="chip <?php echo count($pending_requests)>0 ? 'chip-pay-wait' : 'chip-done'; ?>"><?php echo count($pending_requests); ?></span>
          </h3>

          <?php if(empty($pending_requests)): ?>
            <div class="text-center py-6">
              <div class="text-3xl mb-2 opacity-40">✅</div>
              <p class="text-xs text-slate-500 font-semibold">Bekleyen talep yok — her şey güncel.</p>
            </div>
          <?php else: ?>
            <div class="space-y-3">
              <?php foreach($pending_requests as $r):
                $studentName = trim(($r['s_fn'] ?? '').' '.($r['s_ln'] ?? ''));
                $type = (string)($r['type'] ?? '');
                $msg  = (string)($r['message'] ?? '');
              ?>
                <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50 hover:bg-indigo-50/60 transition">
                  <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                      <div class="text-sm font-black text-slate-900 truncate"><?php echo h($studentName); ?></div>
                      <div class="text-[11px] font-bold text-slate-600">
                        <?php echo h(date('d.m.Y H:i', strtotime($r['appointment_date'].' '.$r['appointment_time']))); ?>
                      </div>
                    </div>
                    <span class="text-[10px] font-black px-2 py-1 rounded-xl border <?php echo $type==='cancel'?'bg-red-50 text-red-700 border-red-100':'bg-blue-50 text-blue-700 border-blue-100'; ?>">
                      <?php echo $type==='cancel'?'İPTAL':'DEĞİŞİM'; ?>
                    </span>
                  </div>
                  <?php if($msg): ?>
                    <div class="mt-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-2xl p-3 break-words">“<?php echo h($msg); ?>”</div>
                  <?php endif; ?>
                  <form method="POST" class="mt-3 space-y-2">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                    <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                    <textarea name="teacher_response" rows="2" class="w-full rounded-2xl border border-slate-200 bg-white p-3 text-xs font-semibold outline-none focus:border-indigo-500" placeholder="Cevap (opsiyonel)"></textarea>
                    <div class="flex gap-2">
                      <button name="approve_request" value="1" class="flex-1 px-4 py-2 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-black text-xs">✅ Onayla</button>
                      <button name="reject_request" value="1" class="flex-1 px-4 py-2 rounded-2xl bg-amber-500 hover:bg-amber-600 text-white font-black text-xs">🚫 Reddet</button>
                    </div>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="lg:col-span-2 space-y-4">
        <?php
          // Durum -> etiket + çip sınıfı + zaman-kutusu tema eşlemesi
          $statusMeta = [
            'confirmed' => ['t'=>'Onaylı',      'chip'=>'chip-confirmed', 'bar'=>'bar-confirmed', 'timeBg'=>'var(--atla-primary-050)','timeFg'=>'var(--atla-primary)'],
            'live'      => ['t'=>'Devam',       'chip'=>'chip-live',      'bar'=>'bar-live',      'timeBg'=>'var(--success-050)',     'timeFg'=>'var(--success)'],
            'done'      => ['t'=>'Tamamlandı',  'chip'=>'chip-done',      'bar'=>'bar-done',      'timeBg'=>'#f1f5f9',                'timeFg'=>'#64748b'],
            'cancel'    => ['t'=>'İptal',       'chip'=>'chip-cancel',    'bar'=>'bar-cancel',    'timeBg'=>'#f1f5f9',                'timeFg'=>'#94a3b8'],
          ];
        foreach($week_days as $dt):
          $curr_date = $dt->format('Y-m-d');
          $day_appointments = $grouped_appointments[$curr_date] ?? [];
          $activeCount = $daily_counts_active[$curr_date] ?? 0;
          $totalCount  = $daily_counts_total[$curr_date] ?? 0;
          $is_today_card = ($curr_date === date('Y-m-d'));
        ?>
        <div id="day-<?php echo h($curr_date); ?>" class="bg-white rounded-2xl shadow-sm border <?php echo $is_today_card?'border-[color:var(--atla-primary)]':'border-slate-200'; ?> overflow-hidden">
          <div class="px-4 md:px-5 py-3 border-b border-slate-100 flex justify-between items-center" style="background:<?php echo $is_today_card?'var(--atla-primary-050)':'#fff'; ?>">
            <div class="flex items-center gap-3">
              <span class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-extrabold" style="background:<?php echo $is_today_card?'var(--atla-primary)':'#f1f5f9'; ?>;color:<?php echo $is_today_card?'#fff':'#334155'; ?>"><?php echo h($dt->format('d')); ?></span>
              <div>
                <h3 class="font-extrabold text-slate-900 text-sm flex items-center gap-2">
                  <?php echo h($gunlerTR[$dt->format('l')]); ?>
                  <?php if($is_today_card): ?><span class="chip chip-confirmed">Bugün</span><?php endif; ?>
                </h3>
                <p class="text-[11px] text-slate-500 font-semibold"><?php echo (int)$activeCount; ?> aktif · <?php echo (int)$totalCount; ?> toplam</p>
              </div>
            </div>
            <button onclick="openAddModal('<?php echo h($curr_date); ?>')" class="act act-edit" aria-label="<?php echo h($curr_date); ?> gününe randevu ekle">＋ Ekle</button>
          </div>

          <div class="p-3 md:p-4 space-y-3 bg-slate-50/40">
            <?php if (empty($day_appointments)): ?>
              <button type="button" onclick="openAddModal('<?php echo h($curr_date); ?>')"
                class="w-full py-5 rounded-xl border-2 border-dashed border-slate-200 text-sm font-bold text-slate-400 hover:border-[color:var(--atla-primary)] hover:text-[color:var(--atla-primary)] transition">
                Bu gün boş — ＋ randevu eklemek için tıklayın
              </button>
            <?php else: ?>
              <?php
                foreach($day_appointments as $app):
                  $vs         = appt_view_status($app);
                  $meta       = $statusMeta[$vs];
                  $is_cancelled = ($vs === 'cancel');
                  $is_done      = ($vs === 'done');
                  $studentName = trim(($app['first_name'] ?? '').' '.($app['last_name'] ?? ''));
                  $appId      = (int)$app['id'];
                  $duration   = (int)($app['duration'] ?? 0);
                  $timeStr    = date('H:i', strtotime($app['appointment_time']));
                  $privateNote= $app['private_note'] ?? '';
                  $pay        = $pay_by_appt[$appId] ?? null;
                  $appJson    = htmlspecialchars(json_encode($app, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
              ?>
                <div class="appt <?php echo $meta['bar']; ?> <?php echo $is_cancelled?'is-cancel':''; ?> p-3 md:p-4">
                  <div class="flex flex-col sm:flex-row gap-3 sm:items-center">

                    <!-- Zaman kutusu -->
                    <div class="shrink-0 text-center px-3 py-2 rounded-xl w-full sm:w-20 flex sm:flex-col items-center justify-between sm:justify-center" style="background:<?php echo $meta['timeBg']; ?>">
                      <span class="text-lg font-extrabold <?php echo $is_cancelled?'line-through':''; ?>" style="color:<?php echo $meta['timeFg']; ?>"><?php echo $timeStr; ?></span>
                      <span class="text-[10px] font-bold text-slate-400"><?php echo $duration; ?> dk</span>
                    </div>

                    <!-- Bilgi -->
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-extrabold text-slate-900 truncate <?php echo $is_cancelled?'line-through text-slate-400':''; ?>"><?php echo h($studentName); ?></span>
                        <span class="chip <?php echo $meta['chip']; ?>"><?php if($vs==='live'): ?><span class="pulse-dot"></span><?php endif; ?><?php echo h($meta['t']); ?></span>
                        <?php if($pay): ?>
                          <?php if(($pay['status'] ?? '')==='odendi'): ?>
                            <span class="chip chip-pay-paid">✅ Ödendi</span>
                          <?php else: ?>
                            <span class="chip chip-pay-wait">💳 Ödeme bekliyor</span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                      <?php if($privateNote): ?>
                        <p class="text-xs text-slate-500 font-medium mt-1 truncate">📝 <?php echo h($privateNote); ?></p>
                      <?php endif; ?>
                    </div>

                    <!-- Aksiyonlar -->
                    <div class="flex gap-2 flex-wrap sm:justify-end shrink-0">
                      <button type="button" class="act" data-msg-open="<?php echo $appId; ?>">💬 Mesaj</button>
                      <?php if(!$is_cancelled && !$is_done): ?>
                        <?php if($APP_STATUS): ?>
                        <form method="POST" class="inline">
                          <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                          <input type="hidden" name="complete_appointment" value="1">
                          <input type="hidden" name="appointment_id" value="<?php echo $appId; ?>">
                          <button type="submit" class="act act-done">✓ Tamamla</button>
                        </form>
                        <?php endif; ?>
                        <button type="button" onclick='openEditModal(<?php echo $appJson; ?>)' class="act act-edit">✏️ Düzenle</button>
                        <form method="POST" onsubmit="return confirm('Bu randevuyu iptal etmek istediğinize emin misiniz?');" class="inline">
                          <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                          <input type="hidden" name="cancel_appointment" value="1">
                          <input type="hidden" name="appointment_id" value="<?php echo $appId; ?>">
                          <button type="submit" class="act act-cancel">🚫 İptal</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </div>
  </div>
</div>

<!-- ════ FAB — birincil eylem (sağ alt sabit) ════ -->
<button type="button" onclick="openAddModal()" aria-label="Yeni randevu oluştur"
  class="fixed right-5 bottom-5 md:right-8 md:bottom-8 w-14 h-14 rounded-full text-white text-3xl leading-none flex items-center justify-center z-[90] transition active:scale-95"
  style="background:var(--atla-primary);box-shadow:0 10px 20px -3px rgba(34,52,136,.45)"
  onmouseover="this.style.background='var(--atla-primary-600)'" onmouseout="this.style.background='var(--atla-primary)'">＋</button>

<div id="addModal" class="fixed inset-0 z-[120] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[1.5rem] w-full max-w-lg shadow-2xl overflow-hidden relative">
    <div class="p-5 flex justify-between items-center text-white" style="background:var(--atla-primary)">
      <h3 class="font-extrabold text-lg">📅 Yeni Randevu</h3>
      <button type="button" onclick="closeModal('addModal')" aria-label="Kapat" class="bg-white/20 hover:bg-white/30 rounded-full w-9 h-9 flex items-center justify-center transition">✕</button>
    </div>
    <form method="POST" id="addForm" class="p-6 space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="add_appointment" value="1">
      <div>
        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Öğrenci</label>
        <select name="student_id" id="add_student" required class="w-full border border-slate-200 rounded-xl p-3 text-sm font-semibold outline-none focus:border-[color:var(--atla-primary)] bg-white">
          <?php foreach($my_students as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>"><?php echo h($s['first_name'].' '.$s['last_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-1">
          <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tarih</label>
          <input id="add_date" type="date" name="date" required value="<?php echo h(date('Y-m-d')); ?>" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-semibold outline-none focus:border-[color:var(--atla-primary)]">
        </div>
        <div class="col-span-1">
          <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Saat</label>
          <input id="add_time" type="time" name="time" required class="w-full border border-slate-200 rounded-xl p-3 text-sm font-semibold outline-none focus:border-[color:var(--atla-primary)]">
        </div>
        <div class="col-span-1">
          <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Süre (dk)</label>
          <input id="add_duration" type="number" name="duration" value="30" min="15" step="5" required class="w-full border border-slate-200 rounded-xl p-3 text-sm font-semibold bg-slate-50 outline-none focus:border-[color:var(--atla-primary)]">
        </div>
      </div>

      <!-- Canlı çakışma / müsaitlik geri bildirimi -->
      <div id="add_conflict" class="hidden rounded-xl p-3 text-xs font-semibold" role="status" aria-live="polite"></div>

      <div>
        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Not</label>
        <input type="text" name="private_note" placeholder="Örn: Konu tekrarı..." class="js-upper w-full border border-amber-200 rounded-xl p-3 text-sm text-amber-700 bg-amber-50 outline-none">
      </div>
      <label class="flex items-center gap-3 bg-[color:var(--atla-primary-050)] p-3.5 rounded-xl border border-indigo-100 cursor-pointer">
        <input type="checkbox" name="is_recurring" id="is_recurring" class="w-5 h-5 rounded border-slate-300" style="accent-color:var(--atla-primary)">
        <span class="text-sm font-bold" style="color:var(--atla-primary)">Her Hafta Tekrarla (4 Hafta)</span>
      </label>
      <div class="pt-1 flex gap-3">
        <button type="button" onclick="closeModal('addModal')" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-xl font-bold text-sm hover:bg-slate-200 transition">Vazgeç</button>
        <button type="submit" id="add_submit" class="flex-1 text-white py-3 rounded-xl font-bold text-sm shadow-lg transition" style="background:var(--atla-primary)" onmouseover="this.style.background='var(--atla-primary-600)'" onmouseout="this.style.background='var(--atla-primary)'">Oluştur</button>
      </div>
    </form>
  </div>
</div>

<div id="editModal" class="fixed inset-0 z-[120] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden relative">
    <div class="p-5 flex justify-between items-center text-white" style="background:var(--atla-primary)">
      <h3 class="font-extrabold text-lg">✏️ Randevu Düzenle</h3>
      <button type="button" onclick="closeModal('editModal')" aria-label="Kapat" class="bg-white/20 hover:bg-white/30 rounded-full w-9 h-9 flex items-center justify-center transition">✕</button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="edit_appointment" value="1">
      <input type="hidden" name="appointment_id" id="edit_id">
      <div>
        <label class="block text-xs font-black text-slate-500 uppercase mb-1">Öğrenci</label>
        <select name="student_id" id="edit_student_id" required class="w-full border border-slate-200 rounded-2xl p-3 text-sm font-semibold outline-none bg-slate-50">
          <?php foreach($my_students as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>"><?php echo h($s['first_name'].' '.$s['last_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-1">
          <label class="block text-xs font-black text-slate-500 uppercase mb-1">Tarih</label>
          <input type="date" name="date" id="edit_date" required class="w-full border border-slate-200 rounded-2xl p-3 text-sm font-semibold">
        </div>
        <div class="col-span-1">
          <label class="block text-xs font-black text-slate-500 uppercase mb-1">Saat</label>
          <input type="time" name="time" id="edit_time" required class="w-full border border-slate-200 rounded-2xl p-3 text-sm font-semibold">
        </div>
        <div class="col-span-1">
          <label class="block text-xs font-black text-slate-500 uppercase mb-1">Süre (dk)</label>
          <input type="number" name="duration" id="edit_duration" min="15" step="5" required class="w-full border border-slate-200 rounded-2xl p-3 text-sm font-semibold">
        </div>
      </div>
      <div>
        <label class="block text-xs font-black text-slate-500 uppercase mb-1">Not</label>
        <input type="text" name="private_note" id="edit_private_note" class="js-upper w-full border border-amber-200 rounded-2xl p-3 text-sm text-amber-700 bg-amber-50">
      </div>
      <div class="pt-2 flex gap-3">
        <button type="button" onclick="closeModal('editModal')" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-2xl font-black text-sm">Vazgeç</button>
        <button type="submit" class="flex-1 text-white py-3 rounded-xl font-bold text-sm shadow-lg transition" style="background:var(--atla-primary)" onmouseover="this.style.background='var(--atla-primary-600)'" onmouseout="this.style.background='var(--atla-primary)'">Güncelle</button>
      </div>
    </form>
  </div>
</div>

<div id="msgModal" class="fixed inset-0 z-[200] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-xl shadow-2xl overflow-hidden">
    <div class="bg-slate-900 p-5 flex justify-between items-center text-white">
      <div class="min-w-0">
        <h3 class="font-black text-lg truncate" id="msgTitle">Mesajlar</h3>
        <p class="text-xs text-white/60 font-semibold" id="msgSub">Sohbet</p>
      </div>
      <button type="button" onclick="closeMsgModal()" aria-label="Kapat" class="bg-white/20 hover:bg-white/30 rounded-full w-9 h-9 flex items-center justify-center transition">✕</button>
    </div>
    <div id="msgBody" class="p-5 max-h-[55vh] overflow-y-auto space-y-3 bg-slate-50"></div>
    <div class="p-5 border-t border-slate-200 bg-white">
      <div class="flex gap-2">
        <textarea id="msgText" rows="2" class="flex-1 rounded-2xl border border-slate-200 p-3 text-sm font-semibold outline-none focus:border-indigo-500" placeholder="Mesaj yaz..."></textarea>
        <button type="button" onclick="sendMsg()" class="px-5 rounded-xl text-white font-bold transition" style="background:var(--atla-primary)" onmouseover="this.style.background='var(--atla-primary-600)'" onmouseout="this.style.background='var(--atla-primary)'">Gönder</button>
      </div>
    </div>
  </div>
</div>

<script>
/* ── Toast (PRG flash) ── */
function showToast(type, text, timeout){
  const wrap = document.getElementById('toastWrap');
  if(!wrap) return;
  const icons = {success:'✅', error:'⛔', warning:'⚠️', info:'💬'};
  const t = document.createElement('div');
  t.className = 'toast ' + (type||'info');
  t.setAttribute('role','status');
  t.innerHTML = '<span aria-hidden="true">'+(icons[type]||'💬')+'</span><span style="flex:1">'+text+'</span>';
  wrap.appendChild(t);
  setTimeout(()=>{ t.style.transition='opacity .3s,transform .3s'; t.style.opacity='0'; t.style.transform='translateY(-10px)'; setTimeout(()=>t.remove(),300); }, timeout||4000);
}
<?php if (!empty($flash)): ?>
showToast(<?php echo json_encode($flash['t']); ?>, <?php echo json_encode($flash['m']); ?>);
<?php endif; ?>

/* ── İstatistik sayaç animasyonu (count-up) ── */
function runCountUp(){
  document.querySelectorAll('.count-up').forEach(el=>{
    const target = parseInt(el.getAttribute('data-target'),10) || 0;
    if(target === 0){ el.firstChild.nodeValue = '0'; return; }
    const dur = 700, t0 = performance.now();
    function step(now){
      const p = Math.min((now - t0)/dur, 1);
      const val = Math.round(target * (1 - Math.pow(1-p,3))); // easeOutCubic
      el.firstChild.nodeValue = String(val);
      if(p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  });
}
window.addEventListener('DOMContentLoaded', runCountUp);

function scrollToDay(dateStr){
  const el = document.getElementById('day-' + dateStr);
  if(!el) return;
  el.scrollIntoView({behavior:'smooth', block:'start'});
  el.classList.add('ring-4','ring-indigo-100');
  setTimeout(()=>el.classList.remove('ring-4','ring-indigo-100'), 1200);
}

let _lastFocused = null;
function closeModal(id){
  const el = document.getElementById(id);
  if(!el) return;
  el.classList.add('hidden');
  el.classList.remove('flex');
  el.removeAttribute('aria-modal'); el.removeAttribute('role');
  // Erişilebilirlik: odağı modalı açan öğeye geri ver
  if(_lastFocused && typeof _lastFocused.focus === 'function'){ _lastFocused.focus(); _lastFocused = null; }
}
function showModal(id){
  const el = document.getElementById(id);
  if(!el) return;
  _lastFocused = document.activeElement;
  el.classList.remove('hidden');
  el.classList.add('flex');
  el.setAttribute('role','dialog'); el.setAttribute('aria-modal','true');
  // İlk odaklanabilir öğeye odak
  const focusable = el.querySelector('input,select,textarea,button');
  if(focusable) setTimeout(()=>focusable.focus(), 30);
  // Basit focus-trap: Tab modal içinde döner
  el.addEventListener('keydown', trapTab);
}
function trapTab(e){
  if(e.key !== 'Tab') return;
  const el = e.currentTarget;
  const items = [...el.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled])')].filter(x=>x.offsetParent!==null);
  if(!items.length) return;
  const first = items[0], last = items[items.length-1];
  if(e.shiftKey && document.activeElement === first){ e.preventDefault(); last.focus(); }
  else if(!e.shiftKey && document.activeElement === last){ e.preventDefault(); first.focus(); }
}

function openAddModal(dateStr){
  const dateInput = document.getElementById('add_date');
  if(dateStr && dateInput) dateInput.value = dateStr;
  const cb = document.getElementById('add_conflict');
  if(cb){ cb.classList.add('hidden'); cb.textContent=''; }
  showModal('addModal');
  checkAddConflict();
}

/* Canlı çakışma / müsaitlik ön-kontrolü (add modal) */
let _conflictTimer = null;
function checkAddConflict(){
  const d = document.getElementById('add_date')?.value;
  const t = document.getElementById('add_time')?.value;
  const dur = document.getElementById('add_duration')?.value;
  const box = document.getElementById('add_conflict');
  if(!box) return;
  if(!d || !t || !dur){ box.classList.add('hidden'); return; }
  clearTimeout(_conflictTimer);
  _conflictTimer = setTimeout(async ()=>{
    try {
      const url = `?ajax=check_conflict&date=${encodeURIComponent(d)}&time=${encodeURIComponent(t)}&duration=${encodeURIComponent(dur)}`;
      const res = await fetch(url, {credentials:'same-origin'});
      const js = await res.json();
      if(!js.ok){ box.classList.add('hidden'); return; }
      box.classList.remove('hidden');
      if(js.conflict){
        box.style.background='var(--warning-050)'; box.style.color='var(--warning)'; box.style.border='1px solid #fde68a';
        box.innerHTML = `⚠️ Bu saatte <b>${(js.who||'bir öğrenci')}</b> ile randevunuz var (${js.range||''}). Yine de kaydedebilirsiniz.`;
      } else {
        box.style.background='var(--success-050)'; box.style.color='var(--success)'; box.style.border='1px solid #a7f3d0';
        box.innerHTML = '✓ Bu saat müsait.';
      }
    } catch(e){ box.classList.add('hidden'); }
  }, 300);
}
['add_date','add_time','add_duration'].forEach(id=>{
  document.addEventListener('input', e=>{ if(e.target && e.target.id===id) checkAddConflict(); });
});

function openEditModal(data){
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_student_id').value = data.student_id;
  document.getElementById('edit_date').value = data.appointment_date;
  document.getElementById('edit_time').value = (data.appointment_time || '').substring(0,5);
  document.getElementById('edit_duration').value = data.duration || 30;
  document.getElementById('edit_private_note').value = data.private_note || '';
  showModal('editModal');
}

window.addEventListener('click', function(e){
  if(e.target && e.target.id === 'addModal') closeModal('addModal');
  if(e.target && e.target.id === 'editModal') closeModal('editModal');
});

/* Erişilebilirlik: Escape ile açık modalı kapat */
document.addEventListener('keydown', function(e){
  if(e.key !== 'Escape') return;
  ['addModal','editModal'].forEach(id=>{
    const el = document.getElementById(id);
    if(el && !el.classList.contains('hidden')) closeModal(id);
  });
  const m = document.getElementById('msgModal');
  if(m && !m.classList.contains('hidden')) closeMsgModal();
});

const MSG_API = "/appointment_messages_api.php";
const CSRF_TOKEN = <?php echo json_encode($csrf); ?>;
const MY_ROLE = <?php echo json_encode($_SESSION['role'] ?? ''); ?>;

const MSG_BTN_DEFAULT_CLASS = "text-xs font-black text-indigo-700 hover:text-indigo-900 bg-white border border-slate-200 hover:border-indigo-200 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap";
const MSG_BTN_UNREAD_CLASS = "text-xs font-black text-amber-800 bg-amber-50 border border-amber-200 hover:border-amber-300 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap";

let CURRENT_APP_ID = null;

function showMsgModal(){
  const m = document.getElementById('msgModal');
  _lastFocused = document.activeElement;
  m.classList.remove('hidden');
  m.classList.add('flex');
  m.setAttribute('role','dialog'); m.setAttribute('aria-modal','true');
  m.addEventListener('keydown', trapTab);
  setTimeout(()=>document.getElementById('msgText')?.focus(), 30);
}
function closeMsgModal(){
  const m = document.getElementById('msgModal');
  m.classList.add('hidden');
  m.classList.remove('flex');
  m.removeAttribute('aria-modal'); m.removeAttribute('role');
  CURRENT_APP_ID = null;
  document.getElementById('msgBody').innerHTML = '';
  document.getElementById('msgText').value = '';
  if(_lastFocused && typeof _lastFocused.focus === 'function'){ _lastFocused.focus(); _lastFocused = null; }
}
function esc(s){
  return (s ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

async function loadSummary(){
  const btns = [...document.querySelectorAll('[data-msg-open]')];
  const ids = [...new Set(btns.map(b => parseInt(b.getAttribute('data-msg-open'), 10)).filter(Boolean))];
  if(!ids.length) return;

  try {
      const res = await fetch(MSG_API + "?action=summary&ids=" + encodeURIComponent(ids.join(',')), { credentials: 'same-origin' });
      const js = await res.json();
      if(!js.ok) return;

      ids.forEach(id => {
        const st = js.summary?.[id] || { total: 0, unread: 0 };
        const btn = document.querySelector(`[data-msg-open="${id}"]`);
        if(!btn) return;
        if(st.unread > 0){
          btn.className = MSG_BTN_UNREAD_CLASS;
          btn.textContent = `💬 Mesajlar (${st.unread})`;
        } else {
          btn.className = MSG_BTN_DEFAULT_CLASS;
          btn.textContent = "💬 Mesajlar";
        }
      });
  } catch(e){ console.error(e); }
}

async function openMsg(appId){
  CURRENT_APP_ID = appId;
  document.getElementById('msgBody').innerHTML = '<div class="text-sm text-slate-500 font-semibold">Yükleniyor…</div>';
  showMsgModal();

  try {
    const res = await fetch(MSG_API + "?action=list&appointment_id=" + appId, { credentials:'same-origin' });
    const js = await res.json();
    if(!js.ok){
      document.getElementById('msgBody').innerHTML = `<div class="text-sm text-red-600 font-black">Hata: ${esc(js.error||'unknown')}</div>`;
      return;
    }
    const msgs = js.messages || [];
    if(!msgs.length){
      document.getElementById('msgBody').innerHTML = '<div class="text-sm text-slate-500 font-semibold italic">Henüz mesaj yok.</div>';
    } else {
      document.getElementById('msgBody').innerHTML = msgs.map(m=>{
        const mine = (m.sender_role === MY_ROLE);
        const box = mine ? 'text-white ml-auto' : 'bg-white border border-slate-200 text-slate-800';
        const boxStyle = mine ? 'style="background:var(--atla-primary)"' : '';
        const who = mine ? 'Siz' : (m.sender_role === 'teacher' ? 'Öğretmen' : (m.sender_role === 'parent' ? 'Veli' : 'Öğrenci'));
        return `<div class="max-w-[85%] rounded-2xl p-3 ${box}" ${boxStyle}><div class="text-[10px] font-black opacity-80 mb-1">${who} • ${esc(m.created_at)}</div><div class="text-sm font-semibold break-words">${esc(m.message)}</div></div>`;
      }).join('');
      const body = document.getElementById('msgBody');
      body.scrollTop = body.scrollHeight;
    }
    loadSummary();
  } catch(e){ console.error(e); }
}

async function sendMsg(){
  const txt = document.getElementById('msgText').value.trim();
  if(!CURRENT_APP_ID || !txt) return;
  const body = new URLSearchParams();
  body.set('action','send');
  body.set('csrf_token', CSRF_TOKEN);
  body.set('appointment_id', CURRENT_APP_ID);
  body.set('message', txt);

  try {
    const res = await fetch(MSG_API, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString(), credentials:'same-origin' });
    const js = await res.json();
    if(!js.ok){ alert("Hata: " + (js.error||'unknown')); return; }
    document.getElementById('msgText').value = '';
    await openMsg(CURRENT_APP_ID);
  } catch(e){ console.error(e); }
}

document.addEventListener('click', (e)=>{
  const openBtn = e.target.closest('[data-msg-open]');
  if(openBtn) openMsg(parseInt(openBtn.getAttribute('data-msg-open'),10));
});

window.addEventListener('DOMContentLoaded', () => { loadSummary(); setInterval(loadSummary, 20000); });
document.getElementById('msgModal')?.addEventListener('click', (e)=>{
  if(e.target && e.target.id === 'msgModal') closeMsgModal();
});
</script>
<?php include $footerPath; ?>