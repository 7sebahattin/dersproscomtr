<?php
// chat_api.php
// Live Chat & Credit System API (Final + Delete Features)
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once 'db.php';

// Mail Helper
if (file_exists('mail_helper.php')) {
    require_once 'mail_helper.php';
}

$response = ['ok' => false, 'error' => ''];

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Auth required']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? '';

try {
    // 1. MESAJ GÖNDERME
    if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $receiver_id = (int)$_POST['receiver_id'];
        $message = trim($_POST['message']);

        if (!$message || !$receiver_id) throw new Exception("Eksik veri.");

        // Kilit Kontrolü (Öğretmen -> Öğrenci)
        if ($role === 'teacher') {
            $check = $pdo->prepare("SELECT id FROM teacher_student_unlocks WHERE teacher_id = ? AND student_id = ?");
            $check->execute([$user_id, $receiver_id]);
            if (!$check->fetch()) {
                throw new Exception("Bu öğrenciye mesaj göndermek için önce kilidi açmalısınız.");
            }
        }

        $stmt = $pdo->prepare("INSERT INTO live_chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $receiver_id, $message]);
        
        // --- MAIL BİLDİRİMİ ---
        if (function_exists('send_mail_notification')) {
            try {
                $stmtUser = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
                $stmtUser->execute([$receiver_id]);
                $rcvUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
                $senderName = ($_SESSION['first_name'] ?? 'Kullanıcı') . ' ' . ($_SESSION['last_name'] ?? '');

                if ($rcvUser && !empty($rcvUser['email'])) {
                    $siteUrl = $_SERVER['HTTP_HOST'];
                    $targetLink = "http://$siteUrl/login.php";
                    $subject = "Canlı Ders: Yeni Mesajınız Var";
                    $body = "<strong>$senderName</strong> size mesaj gönderdi.<br>Okumak için panele giriş yapın.";
                    send_mail_notification($rcvUser['email'], $rcvUser['first_name'], $subject, $body, $targetLink);
                }
            } catch (Exception $e) { }
        }
        $response['ok'] = true;
    }

    // 2. MESAJLARI GETİR
    elseif ($action === 'get_messages') {
        $other_user_id = (int)$_GET['user_id'];
        
        $pdo->prepare("UPDATE live_chat_messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?")->execute([$user_id, $other_user_id]);

        $stmt = $pdo->prepare("
            SELECT m.*, 
                   CASE WHEN m.sender_id = ? THEN 'me' ELSE 'other' END as type
            FROM live_chat_messages m
            WHERE (m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$user_id, $user_id, $other_user_id, $other_user_id, $user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $is_locked = false;
        if ($role === 'teacher') {
            $check = $pdo->prepare("SELECT id FROM teacher_student_unlocks WHERE teacher_id = ? AND student_id = ?");
            $check->execute([$user_id, $other_user_id]);
            $is_locked = !$check->fetch();
        }

        $response['ok'] = true;
        $response['messages'] = $messages;
        $response['is_locked'] = $is_locked;
    }

    // 3. KONUŞMA LİSTESİ
    elseif ($action === 'get_conversations' && $role === 'teacher') {
        $sql = "
            SELECT 
                u.id, u.first_name, u.last_name, u.photo_path,
                (SELECT message FROM live_chat_messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM live_chat_messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_date,
                (SELECT COUNT(*) FROM live_chat_messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count,
                (SELECT COUNT(*) FROM teacher_student_unlocks WHERE teacher_id = ? AND student_id = u.id) as is_unlocked
            FROM users u
            WHERE u.id IN (
                SELECT sender_id FROM live_chat_messages WHERE receiver_id = ?
                UNION
                SELECT receiver_id FROM live_chat_messages WHERE sender_id = ?
            )
            ORDER BY last_date DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $crStmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
        $crStmt->execute([$user_id]);
        $credits = $crStmt->fetchColumn();

        $response['ok'] = true;
        $response['users'] = $users;
        $response['credits'] = $credits ?: 0;
    }

    // 4. KİLİT AÇMA
    elseif ($action === 'unlock_student' && $role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $student_id = (int)$_POST['student_id'];
        $pdo->beginTransaction();
        try {
            $check = $pdo->prepare("SELECT id FROM teacher_student_unlocks WHERE teacher_id = ? AND student_id = ?");
            $check->execute([$user_id, $student_id]);
            if ($check->fetch()) {
                $pdo->commit();
                $response['ok'] = true; 
                echo json_encode($response);
                exit;
            }
            $credCheck = $pdo->prepare("SELECT credits FROM users WHERE id = ? FOR UPDATE");
            $credCheck->execute([$user_id]);
            $current_credits = $credCheck->fetchColumn();

            if ($current_credits < 1) {
                throw new Exception("Yetersiz kredi.");
            }
            $pdo->prepare("UPDATE users SET credits = credits - 1 WHERE id = ?")->execute([$user_id]);
            $pdo->prepare("INSERT INTO teacher_student_unlocks (teacher_id, student_id) VALUES (?, ?)")->execute([$user_id, $student_id]);
            $pdo->commit();
            $response['ok'] = true;
            $response['new_credits'] = $current_credits - 1;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // 5. TEK MESAJ SİLME
    elseif ($action === 'delete_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $message_id = (int)$_POST['message_id'];
        
        // Sadece kendi gönderdiği mesajı silebilir
        $stmt = $pdo->prepare("DELETE FROM live_chat_messages WHERE id = ? AND sender_id = ?");
        $stmt->execute([$message_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $response['ok'] = true;
        } else {
            throw new Exception("Mesaj silinemedi veya size ait değil.");
        }
    }

    // 6. KOMPLE SOHBETİ TEMİZLE (YENİ)
    elseif ($action === 'clear_conversation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $partner_id = (int)$_POST['partner_id'];

        if(!$partner_id) throw new Exception("Kullanıcı seçilmedi.");

        // İki kullanıcı arasındaki TÜM mesajları siler
        // Not: Bu işlem mesajları veritabanından tamamen siler (WhatsApp'taki 'Herkes için sil' gibi davranır)
        $stmt = $pdo->prepare("
            DELETE FROM live_chat_messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?)
        ");
        $stmt->execute([$user_id, $partner_id, $partner_id, $user_id]);
        
        $response['ok'] = true;
    }

    // 7. BİLDİRİM SAYISI
    elseif ($action === 'check_unread') {
        $count = $pdo->prepare("SELECT COUNT(*) FROM live_chat_messages WHERE receiver_id = ? AND is_read = 0");
        $count->execute([$user_id]);
        $response['ok'] = true;
        $response['count'] = $count->fetchColumn();
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);