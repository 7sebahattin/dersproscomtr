<?php
// appointment_messages_api.php
// Appointment Chat API (Final Privacy Version)

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$BASE = __DIR__;
$dbPath = file_exists($BASE.'/db.php') ? $BASE.'/db.php' : $BASE.'/../db.php';
require_once $dbPath;

// Mail Helper
$mailHelperPath = file_exists($BASE.'/mail_helper.php') ? $BASE.'/mail_helper.php' : $BASE.'/../mail_helper.php';
if (file_exists($mailHelperPath)) {
    require_once $mailHelperPath;
}

function out($ok, $data = [], $code = 200){
  http_response_code($code);
  echo json_encode(['ok'=>$ok] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}

function require_login(){
  if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    out(false, ['error'=>'not_logged_in'], 401);
  }
}

function check_csrf(){
  $token = $_POST['csrf_token'] ?? '';
  $sess  = $_SESSION['csrf_token'] ?? '';
  if (!$token || !$sess || !hash_equals($sess, $token)) {
    out(false, ['error'=>'csrf_failed'], 403);
  }
}

require_login();
$user_id = (int)$_SESSION['user_id'];
$role    = (string)$_SESSION['role']; // teacher / student / parent

function getAppointment(PDO $pdo, int $appointment_id){
  $st = $pdo->prepare("SELECT id, teacher_id, student_id FROM appointments WHERE id=? LIMIT 1");
  $st->execute([$appointment_id]);
  return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function canAccess(PDO $pdo, array $app, int $user_id, string $role): bool {
  if ($role === 'teacher') return (int)$app['teacher_id'] === $user_id;
  if ($role === 'student') return (int)$app['student_id'] === $user_id;
  if ($role === 'parent') {
    $st = $pdo->prepare("SELECT 1 FROM parent_relationships WHERE parent_id=? AND student_id=? LIMIT 1");
    $st->execute([$user_id, (int)$app['student_id']]);
    return (bool)$st->fetchColumn();
  }
  return false;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// SUMMARY
if ($action === 'summary') {
  $idsRaw = $_GET['ids'] ?? '';
  $ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw))));
  if (!$ids) out(true, ['summary'=>[]]);

  $allowed = [];
  foreach ($ids as $aid){
    $app = getAppointment($pdo, $aid);
    if ($app && canAccess($pdo, $app, $user_id, $role)) $allowed[] = $aid;
  }
  if (!$allowed) out(true, ['summary'=>[]]);

  $in = implode(',', array_fill(0, count($allowed), '?'));
  $sql = "SELECT appointment_id, COUNT(*) AS total, SUM(CASE WHEN is_read_by_teacher=0 THEN 1 ELSE 0 END) AS unread_teacher, SUM(CASE WHEN is_read_by_student=0 THEN 1 ELSE 0 END) AS unread_student, MAX(created_at) AS last_at FROM appointment_messages WHERE appointment_id IN ($in) GROUP BY appointment_id";
  $st = $pdo->prepare($sql);
  $st->execute($allowed);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $map = [];
  foreach ($rows as $r){
    $aid = (int)$r['appointment_id'];
    $map[$aid] = [
      'total' => (int)$r['total'],
      'unread' => ($role==='teacher') ? (int)$r['unread_teacher'] : (int)$r['unread_student'],
      'last_at' => $r['last_at']
    ];
  }
  out(true, ['summary'=>$map]);
}

// LIST
if ($action === 'list') {
  $appointment_id = (int)($_GET['appointment_id'] ?? 0);
  if ($appointment_id<=0) out(false, ['error'=>'bad_appointment_id'], 422);

  $app = getAppointment($pdo, $appointment_id);
  if (!$app) out(false, ['error'=>'appointment_not_found'], 404);
  if (!canAccess($pdo, $app, $user_id, $role)) out(false, ['error'=>'forbidden'], 403);

  if ($role === 'teacher') {
    $pdo->prepare("UPDATE appointment_messages SET is_read_by_teacher=1 WHERE appointment_id=?")->execute([$appointment_id]);
  } else {
    $pdo->prepare("UPDATE appointment_messages SET is_read_by_student=1 WHERE appointment_id=?")->execute([$appointment_id]);
  }

  $st = $pdo->prepare("SELECT id, appointment_id, sender_user_id, sender_role, message, created_at FROM appointment_messages WHERE appointment_id=? ORDER BY created_at ASC, id ASC");
  $st->execute([$appointment_id]);
  $msgs = $st->fetchAll(PDO::FETCH_ASSOC);

  out(true, ['messages'=>$msgs]);
}

// SEND
if ($action === 'send') {
  check_csrf();

  $appointment_id = (int)($_POST['appointment_id'] ?? 0);
  $text = trim((string)($_POST['message'] ?? ''));
  if ($appointment_id<=0 || $text==='') out(false, ['error'=>'missing_fields'], 422);

  $app = getAppointment($pdo, $appointment_id);
  if (!$app) out(false, ['error'=>'appointment_not_found'], 404);
  if (!canAccess($pdo, $app, $user_id, $role)) out(false, ['error'=>'forbidden'], 403);

  $sender_role = $role; 
  $readTeacher = ($role === 'teacher') ? 1 : 0;
  $readStudent = ($role === 'teacher') ? 0 : 1;

  $ins = $pdo->prepare("INSERT INTO appointment_messages (appointment_id, sender_user_id, sender_role, message, created_at, is_read_by_teacher, is_read_by_student) VALUES (?, ?, ?, ?, NOW(), ?, ?)");
  $ins->execute([$appointment_id, $user_id, $sender_role, $text, $readTeacher, $readStudent]);

  // --- MAIL BİLDİRİMİ (GİZLİLİK ODAKLI) ---
  if (function_exists('send_mail_notification')) {
      $targetUserId = ($role === 'teacher') ? $app['student_id'] : $app['teacher_id'];
      
      try {
          $stmtUser = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
          $stmtUser->execute([$targetUserId]);
          $rcvUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

          $senderName = ($_SESSION['first_name'] ?? 'Kullanıcı') . ' ' . ($_SESSION['last_name'] ?? '');

          if ($rcvUser && !empty($rcvUser['email'])) {
              $siteUrl = $_SERVER['HTTP_HOST'];
              $targetLink = "http://$siteUrl/login.php"; 

              $subject = "Randevu: Yeni Mesajınız Var";

            
              $body = "<strong>$senderName</strong> randevunuzla ilgili yeni bir mesaj gönderdi.<br><br>" .
                      
                      "Mesajı okumak ve cevaplamak için panele giriş yapın.";

              send_mail_notification($rcvUser['email'], $rcvUser['first_name'], $subject, $body, $targetLink);
          }
      } catch (Exception $e) { }
  }
  // ----------------------------------------

  out(true, ['saved'=>true]);
}

out(false, ['error'=>'unknown_action'], 400);