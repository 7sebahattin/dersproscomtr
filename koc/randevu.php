<?php
// randevu.php (TEACHER) - STABLE VERSION
// Build Timestamp: 2025-01-03 14:15:00
// Fixes: Undefined variable $is_cancelled, HTML attributes breaking, Scope isolation

ini_set('display_errors', 1);
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
include $headerPath;

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
    echo "<script>window.location.href='/index.php';</script>"; 
    exit;
}
$teacher_id = (int)$_SESSION['user_id'];

/* --- CSRF --- */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

$message = "";

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

/* --- POST ACTIONS --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf, $post_csrf)) {
        $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ CSRF doğrulaması başarısız.</div>";
    } else {

        // (A) Approve / Reject request
        if (isset($_POST['approve_request']) || isset($_POST['reject_request'])) {
            $req_id = (int)($_POST['request_id'] ?? 0);
            $resp_text = trim((string)($_POST['teacher_response'] ?? ''));

            if (!$REQ_ID || !$REQ_APP_ID || !$REQ_STATUS || !$REQ_TYPE) {
                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ appointment_requests kolonları eksik.</div>";
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
                    $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⛔ Talep bulunamadı.</div>";
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
                            $message = "<div class='bg-green-50 text-green-700 p-4 rounded-2xl mb-4 font-black border border-green-100'>✅ Talep onaylandı (Randevu İptal).</div>";
                        } elseif ($type === 'reschedule') {
                            $new_date = !empty($row['proposed_date']) ? (string)$row['proposed_date'] : (string)$row['appointment_date'];
                            $new_time = !empty($row['proposed_time']) ? normalize_time($row['proposed_time']) : (string)$row['appointment_time'];
                            $new_dur  = !empty($row['proposed_duration']) ? (int)$row['proposed_duration'] : (int)$row['duration'];

                            $check_start = $new_time;
                            $check_end = date('H:i:s', strtotime("$new_time +$new_dur minutes"));

                            if (has_conflict($pdo, $teacher_id, $new_date, $check_start, $check_end, (int)$row['appointment_id'],
                                $APP_TEACHER, $APP_DATE, $APP_TIME, $APP_DUR, $APP_STATUS, $APP_ISCANCEL, $APP_ID)) {
                                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Çakışma var.</div>";
                                $newStatus = null;
                            } else {
                                $pdo->prepare("UPDATE appointments SET `$APP_DATE`=?, `$APP_TIME`=?, `$APP_DUR`=? WHERE `$APP_ID`=? AND `$APP_TEACHER`=?")
                                    ->execute([$new_date, $new_time, $new_dur, (int)$row['appointment_id'], $teacher_id]);
                                $newStatus = 'approved';
                                $message = "<div class='bg-green-50 text-green-700 p-4 rounded-2xl mb-4 font-black border border-green-100'>✅ Değişiklik onaylandı.</div>";
                            }
                        } else {
                            $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Bilinmeyen talep tipi.</div>";
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
                        $message = "<div class='bg-amber-50 text-amber-800 p-4 rounded-2xl mb-4 font-black border border-amber-100'>🚫 Talep reddedildi.</div>";
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
            $message = "<div class='bg-yellow-50 text-yellow-700 p-4 rounded-2xl mb-4 font-black border border-yellow-100'>🚫 Randevu iptal edildi.</div>";
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
                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Eksik bilgi.</div>";
            } else {
                $check_start = $time;
                $check_end = date('H:i:s', strtotime("$time +$duration minutes"));

                if (has_conflict($pdo, $teacher_id, $date, $check_start, $check_end, null, $APP_TEACHER, $APP_DATE, $APP_TIME, $APP_DUR, $APP_STATUS, $APP_ISCANCEL, $APP_ID)) {
                    $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Çakışma var!</div>";
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
                            $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Tekrarlı randevu çakıştı ($target_date).</div>";
                            $okAll = false; 
                            break;
                        }
                        $valsIter = $vals; 
                        $valsIter[2] = $target_date;
                        $insert->execute($valsIter);
                    }
                    if ($okAll && $message === '') $message = "<div class='bg-green-50 text-green-700 p-4 rounded-2xl mb-4 font-black border border-green-100'>✅ Randevu oluşturuldu.</div>";
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
                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Eksik bilgi.</div>";
            } else {
                $check_start = $time;
                $check_end = date('H:i:s', strtotime("$time +$duration minutes"));

                if (has_conflict($pdo, $teacher_id, $date, $check_start, $check_end, $app_id, $APP_TEACHER, $APP_DATE, $APP_TIME, $APP_DUR, $APP_STATUS, $APP_ISCANCEL, $APP_ID)) {
                    $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Çakışma var.</div>";
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
                    $message = "<div class='bg-blue-50 text-blue-700 p-4 rounded-2xl mb-4 font-black border border-blue-100'>✏️ Randevu güncellendi.</div>";
                }
            }
        }
    }
}

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

/* --- STUDENT STATS --- */
$stats_stmt = $pdo->prepare("
    SELECT
        u.id as student_id, u.first_name, u.last_name,
        COUNT(a.`$APP_ID`) as total_app,
        SUM(CASE WHEN ".app_status_expr($APP_STATUS,$APP_ISCANCEL)." = 'cancelled' THEN 1 ELSE 0 END) as cancelled_app
    FROM appointments a
    JOIN users u ON a.`$APP_STUDENT` = u.id
    WHERE a.`$APP_TEACHER` = ?
    GROUP BY a.`$APP_STUDENT`
    ORDER BY total_app DESC
");
$stats_stmt->execute([$teacher_id]);
$student_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

/* --- HISTORY (ALL APPS) --- */
$all_apps_stmt = $pdo->prepare("
    SELECT
        a.`$APP_STUDENT` AS student_id,
        a.`$APP_DATE` AS appointment_date,
        a.`$APP_TIME` AS appointment_time,
        a.`$APP_DUR`  AS duration,
        ".app_status_expr($APP_STATUS,$APP_ISCANCEL)." AS status
        ".($APP_PUBLIC ? ", a.`$APP_PUBLIC` AS public_note" : ", NULL AS public_note")."
        ".($APP_PRIVATE ? ", a.`$APP_PRIVATE` AS private_note" : ", NULL AS private_note")."
        ,
        u.first_name, u.last_name
    FROM appointments a
    JOIN users u ON a.`$APP_STUDENT` = u.id
    WHERE a.`$APP_TEACHER` = ?
    ORDER BY a.`$APP_DATE` DESC, a.`$APP_TIME` DESC
");
$all_apps_stmt->execute([$teacher_id]);
$all_apps = $all_apps_stmt->fetchAll(PDO::FETCH_ASSOC);

$apps_by_student = [];
foreach($all_apps as $ap) {
    $sid = (int)$ap['student_id'];
    $apps_by_student[$sid][] = [
        'date' => date('d.m.Y', strtotime($ap['appointment_date'])),
        'time' => date('H:i', strtotime($ap['appointment_time'])),
        'status' => (string)($ap['status'] ?? 'active'),
        'duration' => (int)($ap['duration'] ?? 0),
        'public_note' => (string)($ap['public_note'] ?? ''),
        'private_note' => (string)($ap['private_note'] ?? ''),
    ];
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
?>
<style>
.custom-scrollbar::-webkit-scrollbar { width: 10px; height: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(100,116,139,.25); border-radius: 999px; border: 3px solid rgba(255,255,255,.6); }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>

<div class="min-h-screen bg-slate-50 font-['Poppins'] pb-24">
  <div class="max-w-7xl mx-auto p-4 md:p-6">

    <div class="rounded-[2rem] p-6 md:p-7 bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-900 text-white shadow-2xl shadow-slate-900/10 border border-white/10 mb-6">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
        <div>
          <h1 class="text-2xl md:text-3xl font-black tracking-tight">Randevu Yönetimi</h1>
          <p class="text-white/70 text-xs md:text-sm font-semibold mt-1">
            7 günlük plan • <span class="font-black"><?php echo h(date('d.m.Y', strtotime($week_start)) . ' - ' . date('d.m.Y', strtotime($week_end))); ?></span>
          </p>
        </div>

        <div class="w-full lg:w-auto">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="?date=<?php echo h($prev_week); ?>"
               class="w-full bg-white/10 hover:bg-white/15 text-white px-4 py-3 rounded-2xl font-extrabold shadow-sm transition flex items-center justify-center gap-2 border border-white/10">
              <span>⏪</span><span class="text-sm">Önceki</span>
            </a>
            <a href="?date=<?php echo h(date('Y-m-d')); ?>"
               class="w-full bg-white/10 hover:bg-white/15 text-white px-4 py-3 rounded-2xl font-extrabold shadow-sm transition flex items-center justify-center gap-2 border border-white/10">
              <span>🧭</span><span class="text-sm">Bugün</span>
            </a>
            <a href="?date=<?php echo h($next_week); ?>"
               class="w-full bg-white/10 hover:bg-white/15 text-white px-4 py-3 rounded-2xl font-extrabold shadow-sm transition flex items-center justify-center gap-2 border border-white/10">
              <span>⏩</span><span class="text-sm">Sonraki</span>
            </a>
            <button onclick="openAddModal()"
                    class="w-full col-span-2 sm:col-span-1 bg-indigo-500 hover:bg-indigo-400 text-white px-4 py-3 rounded-2xl font-extrabold shadow-lg shadow-indigo-500/30 transition flex items-center justify-center gap-2">
              <span class="text-xl">＋</span><span class="hidden sm:inline">Yeni</span><span class="sm:hidden">Ekle</span>
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-6">
        <div class="bg-white/10 border border-white/10 rounded-2xl p-4">
          <p class="text-white/70 text-[10px] font-bold uppercase tracking-wider">Toplam</p>
          <p class="text-xl font-black mt-1"><?php echo (int)$week_stats['total']; ?></p>
        </div>
        <div class="bg-white/10 border border-white/10 rounded-2xl p-4">
          <p class="text-white/70 text-[10px] font-bold uppercase tracking-wider">Aktif</p>
          <p class="text-xl font-black mt-1"><?php echo (int)$week_stats['active']; ?></p>
        </div>
        <div class="bg-white/10 border border-white/10 rounded-2xl p-4">
          <p class="text-white/70 text-[10px] font-bold uppercase tracking-wider">İptal</p>
          <p class="text-xl font-black mt-1"><?php echo (int)$week_stats['cancelled']; ?></p>
        </div>
        <div class="bg-white/10 border border-white/10 rounded-2xl p-4">
          <p class="text-white/70 text-[10px] font-bold uppercase tracking-wider">Süre</p>
          <p class="text-xl font-black mt-1"><?php echo (int)$week_stats['minutes']; ?> <span class="text-sm font-bold text-white/70">dk</span></p>
        </div>
      </div>
    </div>

    <?php echo $message; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <div class="space-y-6">
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
          <div class="flex justify-between items-center mb-5">
            <a href="?date=<?php echo h($prev_week); ?>" class="p-2 bg-slate-50 rounded-xl hover:bg-indigo-50 text-slate-600 transition shadow-sm border border-slate-100">⏪</a>
            <div class="text-center">
              <span class="block text-sm font-black text-slate-800 tracking-tight"><?php echo h(date('d.m.Y', strtotime($week_start)) . ' - ' . date('d.m.Y', strtotime($week_end))); ?></span>
              <span class="text-[10px] text-indigo-600 font-black uppercase bg-indigo-50 px-2 py-0.5 rounded-md mt-1 inline-block border border-indigo-100"><?php echo h($week_badge); ?></span>
            </div>
            <a href="?date=<?php echo h($next_week); ?>" class="p-2 bg-slate-50 rounded-xl hover:bg-indigo-50 text-slate-600 transition shadow-sm border border-slate-100">⏩</a>
          </div>

          <div class="grid grid-cols-7 gap-2 text-center">
            <?php foreach ($week_days as $dt):
              $curr = $dt->format('Y-m-d');
              $is_today = ($curr === date('Y-m-d'));
              $count_total = $daily_counts_total[$curr] ?? 0;
              $day_name = mb_substr($gunlerTR[$dt->format('l')], 0, 3, 'UTF-8');
              $base = "relative p-2 rounded-2xl transition cursor-pointer flex flex-col items-center justify-center h-16 border";
              $cls = $is_today ? "bg-indigo-600 text-white shadow-lg ring-4 ring-indigo-100 border-indigo-200"
                               : "bg-white hover:bg-slate-50 text-slate-700 border-slate-100";
            ?>
              <div class="<?php echo $base.' '.$cls; ?>" onclick="scrollToDay('<?php echo h($curr); ?>')">
                <span class="text-[9px] font-black uppercase opacity-80 mb-1"><?php echo h($day_name); ?></span>
                <span class="text-sm font-black"><?php echo h($dt->format('d')); ?></span>
                <?php if($count_total>0): ?>
                  <span class="absolute bottom-2 w-1.5 h-1.5 rounded-full <?php echo $is_today?'bg-white':'bg-indigo-500'; ?>"></span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 max-h-[420px] overflow-y-auto custom-scrollbar">
          <h3 class="font-black text-slate-800 mb-4 flex items-center justify-between">
            <span class="flex items-center gap-2">🧩 Bekleyen Talepler</span>
            <span class="text-[10px] font-black bg-amber-50 text-amber-800 border border-amber-200 px-2 py-1 rounded-xl"><?php echo count($pending_requests); ?></span>
          </h3>

          <?php if(empty($pending_requests)): ?>
            <div class="text-xs text-slate-500 font-semibold italic">Talep yok.</div>
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

        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 max-h-[420px] overflow-y-auto custom-scrollbar">
          <h3 class="font-black text-slate-800 mb-4 flex items-center justify-between">
            <span class="flex items-center gap-2">📊 Karne</span>
            <span class="text-[9px] font-normal text-slate-400 bg-slate-50 px-2 py-1 rounded">Detay için tıkla</span>
          </h3>
          <?php if (empty($student_stats)): ?>
            <p class="text-xs text-slate-400">Veri yok.</p>
          <?php else: ?>
            <div class="space-y-3">
              <?php foreach ($student_stats as $stat):
                $sId = (int)$stat['student_id'];
                $name = $stat['first_name'].' '.$stat['last_name'];
                $total = (int)$stat['total_app'];
                $cancel = (int)$stat['cancelled_app'];
                $ok = max(0,$total-$cancel);
                $ratio = $total>0 ? (int)round(($ok/$total)*100) : 0;
              ?>
              <div onclick="openHistoryModal(<?php echo $sId; ?>,'<?php echo h($name); ?>')"
                   class="p-4 rounded-2xl bg-slate-50 border border-slate-100 hover:bg-indigo-50 hover:border-indigo-100 transition cursor-pointer group">
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-white border border-slate-200 flex items-center justify-center text-xs font-black text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition">
                      <?php echo h(mb_substr($stat['first_name'],0,1,'UTF-8')); ?>
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-black text-slate-800 truncate group-hover:text-indigo-800"><?php echo h($name); ?></p>
                      <p class="text-[10px] text-slate-500 font-bold"><?php echo $ok; ?> tamam • <?php echo $cancel; ?> iptal</p>
                    </div>
                  </div>
                  <span class="text-[10px] font-black <?php echo $ratio>=70?'text-green-600':'text-amber-600'; ?> bg-white px-2 py-1 rounded-lg border border-slate-200 shadow-sm">%<?php echo $ratio; ?></span>
                </div>
                <div class="mt-3 h-2 bg-white rounded-full border border-slate-200 overflow-hidden">
                  <div class="h-full <?php echo $ratio>=70?'bg-green-500':'bg-amber-500'; ?>" style="width:<?php echo $ratio; ?>%"></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="lg:col-span-2 space-y-4">
        <?php foreach($week_days as $dt):
          $curr_date = $dt->format('Y-m-d');
          $day_appointments = $grouped_appointments[$curr_date] ?? [];
          $activeCount = $daily_counts_active[$curr_date] ?? 0;
          $totalCount  = $daily_counts_total[$curr_date] ?? 0;
        ?>
        <div id="day-<?php echo h($curr_date); ?>" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
          <div class="bg-slate-200 px-6 py-4 border-b border-slate-300 flex justify-between items-center">
            <div class="flex items-center gap-3">
              <span class="text-indigo-700 bg-white w-9 h-9 flex items-center justify-center rounded-2xl shadow-sm text-sm border border-indigo-100 font-black"><?php echo h($dt->format('d')); ?></span>
              <div>
                <h3 class="font-black text-slate-900 text-sm">
                  <?php echo h($gunlerTR[$dt->format('l')]); ?>
                  <span class="text-slate-700 font-bold text-xs ml-1"><?php echo h(date('d.m.Y', strtotime($curr_date))); ?></span>
                </h3>
                <p class="text-[10px] text-slate-700 font-bold"><?php echo (int)$activeCount; ?> aktif • <?php echo (int)$totalCount; ?> toplam</p>
              </div>
            </div>
            <button onclick="openAddModal('<?php echo h($curr_date); ?>')" class="bg-white border border-slate-300 hover:border-indigo-200 hover:bg-indigo-50 text-indigo-700 px-3 py-2 rounded-2xl font-black text-xs transition shadow-sm">➕ Ekle</button>
          </div>

          <div class="divide-y divide-slate-200 bg-slate-100/30">
            <?php if (empty($day_appointments)): ?>
              <div class="p-10 text-center text-slate-500 text-sm font-semibold italic opacity-80">Boş</div>
            <?php else: ?>
              <?php
                // --- LOOP START ---
                // We define ALL variables here to avoid scope issues inside HTML
                $themes = [
                  ['row'=>'bg-indigo-100',  'border'=>'border-indigo-200',  'bar'=>'border-indigo-500',  'time'=>'bg-indigo-200 text-indigo-900 border-indigo-300',   'hover'=>'hover:bg-indigo-200/80'],
                  ['row'=>'bg-emerald-100', 'border'=>'border-emerald-200', 'bar'=>'border-emerald-500', 'time'=>'bg-emerald-200 text-emerald-900 border-emerald-300','hover'=>'hover:bg-emerald-200/80'],
                  ['row'=>'bg-amber-100',   'border'=>'border-amber-200',   'bar'=>'border-amber-500',   'time'=>'bg-amber-200 text-amber-900 border-amber-300',     'hover'=>'hover:bg-amber-200/80'],
                  ['row'=>'bg-sky-100',     'border'=>'border-sky-200',     'bar'=>'border-sky-500',     'time'=>'bg-sky-200 text-sky-900 border-sky-300',            'hover'=>'hover:bg-sky-200/80'],
                  ['row'=>'bg-fuchsia-100', 'border'=>'border-fuchsia-200', 'bar'=>'border-fuchsia-500', 'time'=>'bg-fuchsia-200 text-fuchsia-900 border-fuchsia-300','hover'=>'hover:bg-fuchsia-200/80'],
                  ['row'=>'bg-rose-100',    'border'=>'border-rose-200',    'bar'=>'border-rose-500',    'time'=>'bg-rose-200 text-rose-900 border-rose-300',         'hover'=>'hover:bg-rose-200/80'],
                ];
                
                foreach($day_appointments as $app):
                  // 1. Prepare Logic Variables
                  $status = (string)($app['status'] ?? 'active');
                  $is_cancelled = ($status === 'cancelled');
                  $studentName = trim(($app['first_name'] ?? '').' '.($app['last_name'] ?? ''));
                  $appId = (int)$app['id'];
                  $duration = (int)($app['duration'] ?? 0);
                  $timeStr = date('H:i', strtotime($app['appointment_time']));
                  $privateNote = $app['private_note'] ?? '';

                  // 2. Prepare Style Variables
                  if ($is_cancelled) {
                    $rowClass  = "bg-slate-100 border border-slate-200 border-l-8 border-l-slate-300";
                    $timeClass = "bg-slate-200 text-slate-700 border-slate-300";
                    $hoverClass = "hover:bg-slate-200/60";
                    $badgeClass = "bg-red-50 text-red-700 border-red-200";
                    $badgeText  = "İPTAL";
                  } else {
                    $sid = (int)($app['student_id'] ?? 0);
                    $ix  = $sid % count($themes);
                    $t   = $themes[$ix];
                    $rowClass   = $t['row']." border ".$t['border']." border-l-8 ".$t['bar'];
                    $timeClass  = $t['time'];
                    $hoverClass = $t['hover'];
                    $badgeClass = "bg-green-50 text-green-700 border-green-200";
                    $badgeText  = "AKTİF";
                  }

                  // 3. Prepare Safe JSON for JS
                  $appJson = htmlspecialchars(json_encode($app, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                  
                  // 4. Construct Final CSS String for the container
                  $finalContainerClass = "p-5 transition " . $rowClass . " " . $hoverClass;
              ?>

                <div class="<?php echo $finalContainerClass; ?>">
                  <div class="flex flex-col md:flex-row gap-4 md:items-center">

                    <div class="w-full md:w-28">
                      <div class="<?php echo $timeClass; ?> border rounded-2xl py-3 px-3 shadow-sm flex items-center justify-between md:flex-col md:items-center md:gap-1">
                        <span class="text-lg font-black"><?php echo $timeStr; ?></span>
                        <span class="text-[10px] font-black opacity-80"><?php echo $duration; ?> dk</span>
                      </div>
                    </div>

                    <div class="flex-1 min-w-0">
                      <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                        <div class="min-w-0">
                          <div class="flex items-center gap-2 min-w-0">
                            <div class="text-base font-black text-slate-900 truncate">
                              <?php echo h($studentName); ?>
                            </div>
                            <span class="text-[10px] font-black px-2 py-1 rounded-xl border whitespace-nowrap <?php echo $badgeClass; ?>">
                              <?php echo $badgeText; ?>
                            </span>
                          </div>
                        </div>

                        <div class="w-full md:w-auto flex md:justify-end justify-start gap-2 flex-wrap items-center">
                          <button type="button"
                            class="text-xs font-black text-indigo-700 hover:text-indigo-900 bg-white border border-slate-200 hover:border-indigo-200 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap"
                            data-msg-open="<?php echo $appId; ?>">
                            💬 Mesajlar
                          </button>

                          <?php if(!$is_cancelled): ?>
                            <button type="button" onclick='openEditModal(<?php echo $appJson; ?>)'
                              class="text-xs font-black text-blue-700 hover:text-blue-900 bg-white border border-slate-200 hover:border-blue-200 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap">
                              ✏️ Düzenle
                            </button>

                            <form method="POST" onsubmit="return confirm('İptal etmek istediğinize emin misiniz?');" class="inline">
                              <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                              <input type="hidden" name="cancel_appointment" value="1">
                              <input type="hidden" name="appointment_id" value="<?php echo $appId; ?>">
                              <button type="submit"
                                class="text-xs font-black text-red-600 hover:text-red-800 bg-white border border-slate-200 hover:border-red-200 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap">
                                🚫 İptal
                              </button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </div>

                      <?php if($privateNote): ?>
                        <div class="mt-4 bg-amber-50 border border-amber-200 rounded-2xl p-3 text-amber-900">
                          <p class="text-[11px] font-black text-amber-700 uppercase tracking-wider mb-1">📝 Not</p>
                          <div class="text-sm font-semibold break-words"><?php echo h($privateNote); ?></div>
                        </div>
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

<div id="historyModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4 hidden">
  <div class="bg-white rounded-[2rem] w-full max-w-md shadow-2xl overflow-hidden relative max-h-[80vh] flex flex-col">
    <div class="bg-slate-900 p-5 flex justify-between items-center text-white flex-shrink-0">
      <div>
        <h3 class="font-black text-lg" id="historyModalTitle">Geçmiş</h3>
        <p class="text-xs text-white/60 font-semibold">Tüm randevular</p>
      </div>
      <button onclick="closeModal('historyModal')" class="bg-white/15 hover:bg-white/25 rounded-full p-2 transition">✕</button>
    </div>
    <div class="p-0 overflow-y-auto custom-scrollbar flex-grow bg-white" id="historyModalBody"></div>
  </div>
</div>

<div id="addModal" class="fixed inset-0 z-[120] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden relative">
    <div class="bg-slate-900 p-5 flex justify-between items-center text-white">
      <h3 class="font-black text-lg">📅 Yeni Randevu</h3>
      <button type="button" onclick="closeModal('addModal')" class="bg-white/20 hover:bg-white/30 rounded-full p-2 transition">✕</button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="add_appointment" value="1">
      <div>
        <label class="block text-xs font-black text-slate-500 uppercase mb-1">Öğrenci</label>
        <select name="student_id" required class="w-full border border-slate-200 rounded-2xl p-3 text-sm font-semibold outline-none focus:border-indigo-500 bg-white">
          <?php foreach($my_students as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>"><?php echo h($s['first_name'].' '.$s['last_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-1">
          <label class="block text-xs font-black text-slate-500 uppercase mb-1">Tarih</label>
          <input id="add_date" type="date" name="date" required value="<?php echo h(date('Y-m-d')); ?>" class="w-full border border-slate-200 rounded-2xl p-3 text-sm font-semibold">
        </div>
        <div class="col-span-1">
          <label class="block text-xs font-black text-slate-500 uppercase mb-1">Saat</label>
          <input type="time" name="time" required class="w-full border border-slate-200 rounded-2xl p-3 text-sm font-semibold">
        </div>
        <div class="col-span-1">
          <label class="block text-xs font-black text-slate-500 uppercase mb-1">Süre (dk)</label>
          <input type="number" name="duration" value="30" min="15" step="5" required class="w-full border border-slate-200 rounded-2xl p-3 text-sm font-semibold bg-slate-50">
        </div>
      </div>
      <div>
        <label class="block text-xs font-black text-slate-500 uppercase mb-1">Not</label>
        <input type="text" name="private_note" placeholder="Örn: Konu tekrarı..." class="w-full border border-amber-200 rounded-2xl p-3 text-sm text-amber-700 bg-amber-50">
      </div>
      <div class="flex items-center gap-3 bg-indigo-50 p-4 rounded-2xl border border-indigo-100">
        <input type="checkbox" name="is_recurring" id="is_recurring" class="w-5 h-5 text-indigo-600 rounded border-slate-300">
        <label for="is_recurring" class="text-sm font-black text-indigo-900 cursor-pointer">Her Hafta Tekrarla (4 Hafta)</label>
      </div>
      <div class="pt-2 flex gap-3">
        <button type="button" onclick="closeModal('addModal')" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-2xl font-black text-sm">Vazgeç</button>
        <button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-2xl font-black text-sm shadow-lg">Oluştur</button>
      </div>
    </form>
  </div>
</div>

<div id="editModal" class="fixed inset-0 z-[120] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden relative">
    <div class="bg-slate-900 p-5 flex justify-between items-center text-white">
      <h3 class="font-black text-lg">✏️ Randevu Düzenle</h3>
      <button type="button" onclick="closeModal('editModal')" class="bg-white/20 hover:bg-white/30 rounded-full p-2 transition">✕</button>
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
        <input type="text" name="private_note" id="edit_private_note" class="w-full border border-amber-200 rounded-2xl p-3 text-sm text-amber-700 bg-amber-50">
      </div>
      <div class="pt-2 flex gap-3">
        <button type="button" onclick="closeModal('editModal')" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-2xl font-black text-sm">Vazgeç</button>
        <button type="submit" class="flex-1 bg-blue-600 text-white py-3 rounded-2xl font-black text-sm shadow-lg">Güncelle</button>
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
      <button type="button" onclick="closeMsgModal()" class="bg-white/20 hover:bg-white/30 rounded-full p-2 transition">✕</button>
    </div>
    <div id="msgBody" class="p-5 max-h-[55vh] overflow-y-auto space-y-3 bg-slate-50"></div>
    <div class="p-5 border-t border-slate-200 bg-white">
      <div class="flex gap-2">
        <textarea id="msgText" rows="2" class="flex-1 rounded-2xl border border-slate-200 p-3 text-sm font-semibold outline-none focus:border-indigo-500" placeholder="Mesaj yaz..."></textarea>
        <button type="button" onclick="sendMsg()" class="px-5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-black">Gönder</button>
      </div>
    </div>
  </div>
</div>

<script>
const appsByStudent = <?php echo json_encode($apps_by_student, JSON_UNESCAPED_UNICODE); ?>;

function scrollToDay(dateStr){
  const el = document.getElementById('day-' + dateStr);
  if(!el) return;
  el.scrollIntoView({behavior:'smooth', block:'start'});
  el.classList.add('ring-4','ring-indigo-100');
  setTimeout(()=>el.classList.remove('ring-4','ring-indigo-100'), 1200);
}

function closeModal(id){
  const el = document.getElementById(id);
  if(!el) return;
  el.classList.add('hidden');
  el.classList.remove('flex');
}
function showModal(id){
  const el = document.getElementById(id);
  if(!el) return;
  el.classList.remove('hidden');
  el.classList.add('flex');
}

function openAddModal(dateStr){
  const dateInput = document.getElementById('add_date');
  if(dateStr && dateInput) dateInput.value = dateStr;
  showModal('addModal');
}

function openEditModal(data){
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_student_id').value = data.student_id;
  document.getElementById('edit_date').value = data.appointment_date;
  document.getElementById('edit_time').value = (data.appointment_time || '').substring(0,5);
  document.getElementById('edit_duration').value = data.duration || 30;
  document.getElementById('edit_private_note').value = data.private_note || '';
  showModal('editModal');
}

function openHistoryModal(studentId, studentName){
  const body  = document.getElementById('historyModalBody');
  document.getElementById('historyModalTitle').textContent = studentName;

  const apps = appsByStudent[studentId] || [];
  if(apps.length===0){
    body.innerHTML = '<div class="p-10 text-center text-slate-400 text-sm font-semibold">Kayıt yok.</div>';
  } else {
    body.innerHTML = apps.map(a=>{
      const isC = a.status==='cancelled';
      const notes = ((a.public_note||'') + ' ' + (a.private_note||'')).trim();
      return `
        <div class="p-5 border-b border-slate-100 ${isC?'bg-red-50':'bg-white'}">
          <div class="flex justify-between items-center">
            <div class="font-black text-slate-900">${a.date}</div>
            <span class="text-[10px] font-black px-2 py-1 rounded-xl border ${isC?'text-red-600 bg-red-50 border-red-100':'text-green-700 bg-green-50 border-green-100'}">
              ${isC?'İPTAL':'AKTİF'}
            </span>
          </div>
          <div class="text-xs font-bold text-indigo-700 mt-1">${a.time} <span class="text-slate-400 font-semibold">(${a.duration} dk)</span></div>
          ${notes ? `<div class="mt-3 text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-200 rounded-2xl p-3 break-words">${notes}</div>`:''}
        </div>
      `;
    }).join('');
  }
  showModal('historyModal');
}

window.addEventListener('click', function(e){
  if(e.target && e.target.id === 'addModal') closeModal('addModal');
  if(e.target && e.target.id === 'editModal') closeModal('editModal');
  if(e.target && e.target.id === 'historyModal') closeModal('historyModal');
});

const MSG_API = "/appointment_messages_api.php";
const CSRF_TOKEN = <?php echo json_encode($csrf); ?>;
const MY_ROLE = <?php echo json_encode($_SESSION['role'] ?? ''); ?>;

const MSG_BTN_DEFAULT_CLASS = "text-xs font-black text-indigo-700 hover:text-indigo-900 bg-white border border-slate-200 hover:border-indigo-200 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap";
const MSG_BTN_UNREAD_CLASS = "text-xs font-black text-amber-800 bg-amber-50 border border-amber-200 hover:border-amber-300 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap";

let CURRENT_APP_ID = null;

function showMsgModal(){
  const m = document.getElementById('msgModal');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function closeMsgModal(){
  const m = document.getElementById('msgModal');
  m.classList.add('hidden');
  m.classList.remove('flex');
  CURRENT_APP_ID = null;
  document.getElementById('msgBody').innerHTML = '';
  document.getElementById('msgText').value = '';
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
        const box = mine ? 'bg-indigo-600 text-white ml-auto' : 'bg-white border border-slate-200 text-slate-800';
        const who = mine ? 'Siz' : (m.sender_role === 'teacher' ? 'Öğretmen' : (m.sender_role === 'parent' ? 'Veli' : 'Öğrenci'));
        return `<div class="max-w-[85%] rounded-2xl p-3 ${box}"><div class="text-[10px] font-black opacity-80 mb-1">${who} • ${esc(m.created_at)}</div><div class="text-sm font-semibold break-words">${esc(m.message)}</div></div>`;
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