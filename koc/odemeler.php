<?php
// koc/odemeler.php - YAZDIRMA HATALARI GİDERİLMİŞ FİNAL SÜRÜM
ob_start();
header('Content-Type: text/html; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';

// Güvenlik
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$teacher_id = (int)$_SESSION['user_id'];
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$alert = null;

require_once __DIR__ . '/../payments_lib.php';
payments_ensure_schema($pdo);

/** Dekont yükleme yardımcı — güvenli dosya adı, tip/boyut kontrolü. Yol döner ya da null. */
function odemeler_store_receipt(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) return null; // 5MB
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
    if ($finfo) finfo_close($finfo);
    if (!isset($allowed[$mime])) return null;
    $dir = __DIR__ . '/../uploads/receipts';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = 'dekont_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) return null;
    return 'uploads/receipts/' . $name; // repo köküne göreli
}

// --- İŞLEMLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

    try {
        $pid = (int)($_POST['id'] ?? 0);
        switch($_POST['action']) {
            case 'update_price':
                if ($selected_student) {
                    $new_price = (float)$_POST['new_price'];
                    $pdo->prepare("UPDATE coaching_relationships SET lesson_price = ? WHERE student_id = ? AND teacher_id = ?")
                        ->execute([$new_price, $selected_student, $teacher_id]);
                    header("Location: ?student_id=$selected_student&msg=price_updated"); exit;
                }
                break;
            case 'add_manual':
                if ($selected_student) {
                    $amount = (float)$_POST['amount'];
                    $desc = trim($_POST['description']);
                    $due = $_POST['due_date'];
                    $pdo->prepare("INSERT INTO payments (student_id, teacher_id, amount, description, due_date, status) VALUES (?, ?, ?, ?, ?, 'odenmedi')")
                        ->execute([$selected_student, $teacher_id, $amount, $desc, $due]);
                    header("Location: ?student_id=$selected_student&msg=added"); exit;
                }
                break;
            case 'update':
                if (payment_owned_by($pdo, $pid, $teacher_id)) {
                    $pdo->prepare("UPDATE payments SET amount=?, description=?, due_date=?, status=? WHERE id=? AND teacher_id=?")
                        ->execute([$_POST['amount'], $_POST['description'], $_POST['due_date'], $_POST['status'], $pid, $teacher_id]);
                }
                header("Location: ?student_id=$selected_student&msg=updated"); exit;
                break;
            case 'save_note': // Kısa not kaydet
                if (payment_owned_by($pdo, $pid, $teacher_id)) {
                    $note = mb_substr(trim($_POST['note'] ?? ''), 0, 255);
                    $pdo->prepare("UPDATE payments SET note=? WHERE id=? AND teacher_id=?")->execute([$note ?: null, $pid, $teacher_id]);
                }
                header("Location: ?student_id=$selected_student&msg=note_saved"); exit;
                break;
            case 'mark_paid': // Ödendi işaretle (+ isteğe bağlı dekont)
                if (payment_owned_by($pdo, $pid, $teacher_id)) {
                    $receiptPath = isset($_FILES['receipt']) ? odemeler_store_receipt($_FILES['receipt']) : null;
                    if ($receiptPath) {
                        $pdo->prepare("UPDATE payments SET status='odendi', paid_date=CURDATE(), receipt_path=? WHERE id=? AND teacher_id=?")
                            ->execute([$receiptPath, $pid, $teacher_id]);
                    } else {
                        $pdo->prepare("UPDATE payments SET status='odendi', paid_date=CURDATE() WHERE id=? AND teacher_id=?")
                            ->execute([$pid, $teacher_id]);
                    }
                }
                header("Location: ?student_id=$selected_student&msg=paid"); exit;
                break;
            case 'unmark_paid': // Ödemeyi geri al
                if (payment_owned_by($pdo, $pid, $teacher_id)) {
                    $pdo->prepare("UPDATE payments SET status='odenmedi', paid_date=NULL WHERE id=? AND teacher_id=?")->execute([$pid, $teacher_id]);
                }
                header("Location: ?student_id=$selected_student&msg=unpaid"); exit;
                break;
            case 'upload_receipt': // Var olan ödemeye dekont ekle/güncelle
                if (payment_owned_by($pdo, $pid, $teacher_id)) {
                    $receiptPath = isset($_FILES['receipt']) ? odemeler_store_receipt($_FILES['receipt']) : null;
                    if ($receiptPath) $pdo->prepare("UPDATE payments SET receipt_path=? WHERE id=? AND teacher_id=?")->execute([$receiptPath, $pid, $teacher_id]);
                }
                header("Location: ?student_id=$selected_student&msg=receipt_added"); exit;
                break;
            case 'delete':
                if (payment_owned_by($pdo, $pid, $teacher_id)) {
                    $pdo->prepare("DELETE FROM payments WHERE id=? AND teacher_id=?")->execute([$pid, $teacher_id]);
                }
                header("Location: ?student_id=$selected_student&msg=deleted"); exit;
                break;
            case 'bulk_delete':
                if (!empty($_POST['ids'])) {
                    $ids = array_map('intval', $_POST['ids']);
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $pdo->prepare("DELETE FROM payments WHERE id IN ($placeholders) AND teacher_id=?")->execute(array_merge($ids, [$teacher_id]));
                    header("Location: ?student_id=$selected_student&msg=bulk_deleted&count=".count($ids)); exit;
                }
                break;
            case 'bulk_paid':
                if (!empty($_POST['ids'])) {
                    $ids = array_map('intval', $_POST['ids']);
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $pdo->prepare("UPDATE payments SET status='odendi', paid_date=CURDATE() WHERE id IN ($placeholders) AND teacher_id=?")->execute(array_merge($ids, [$teacher_id]));
                    header("Location: ?student_id=$selected_student&msg=bulk_paid&count=".count($ids)); exit;
                }
                break;
        }
    } catch(Exception $e) { error_log($e->getMessage()); }
}

// --- OTOMASYON: günü geçen iptal edilmemiş randevular -> 'odenmedi' borç kaydı ---
// (Ödeme olarak İŞARETLEMEZ; cron_notifications.php'de de global çalışır.)
payments_generate_due($pdo, $teacher_id);

include '../header.php';

// Veri Çekme
$students = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, cr.lesson_price FROM users u JOIN coaching_relationships cr ON u.id = cr.student_id WHERE cr.teacher_id = ? ORDER BY u.first_name");
$students->execute([$teacher_id]);
$student_list = $students->fetchAll(PDO::FETCH_ASSOC);

$data = ['student_name' => '', 'lesson_price' => 0, 'stats' => ['paid'=>0,'unpaid'=>0,'overdue'=>0,'total'=>0], 'payments' => [], 'future_sessions' => []];

if ($selected_student) {
    foreach($student_list as $s) { if($s['id'] == $selected_student) { $data['student_name'] = $s['first_name'].' '.$s['last_name']; $data['lesson_price'] = $s['lesson_price']; break; } }
    
    // İstatistikler
    $stats = $pdo->prepare("SELECT SUM(CASE WHEN status='odendi' THEN amount ELSE 0 END) as paid, SUM(CASE WHEN status='odenmedi' AND due_date >= CURDATE() THEN amount ELSE 0 END) as unpaid, SUM(CASE WHEN status='odenmedi' AND due_date < CURDATE() THEN amount ELSE 0 END) as overdue, COUNT(*) as total FROM payments WHERE student_id=? AND teacher_id=?");
    $stats->execute([$selected_student, $teacher_id]);
    $data['stats'] = $stats->fetch(PDO::FETCH_ASSOC);
    
    // Ödeme Geçmişi
    $pays = $pdo->prepare("SELECT * FROM payments WHERE student_id=? AND teacher_id=? ORDER BY due_date DESC, id DESC");
    $pays->execute([$selected_student, $teacher_id]);
    $data['payments'] = $pays->fetchAll(PDO::FETCH_ASSOC);
    
    // Ödeme Atanmamış Gelecek Seanslar
    $future = $pdo->prepare("
        SELECT a.* FROM appointments a 
        WHERE a.student_id=? AND a.teacher_id=? 
        AND a.status != 'cancelled' 
        AND NOT EXISTS (
            SELECT 1 FROM payments p 
            WHERE p.student_id = a.student_id AND p.teacher_id = a.teacher_id 
            AND DATE(p.due_date) = DATE(a.appointment_date)
        ) 
        ORDER BY a.appointment_date ASC LIMIT 10
    ");
    $future->execute([$selected_student, $teacher_id]);
    $data['future_sessions'] = $future->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme Yönetimi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- GENEL STİLLER --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; color: #1e293b; line-height: 1.5; font-size: 14px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 16px 24px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; font-weight: 500; }
        .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #86efac; }
        
        /* Layout Grid */
        .layout { display: grid; grid-template-columns: 250px 1fr; gap: 20px; }
        @media (max-width: 1024px) { .layout { grid-template-columns: 1fr; } }
        
        .sidebar { background: white; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); height: fit-content; position: sticky; top: 20px; }
        .student-item { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 6px; text-decoration: none; color: #475569; font-weight: 500; margin-bottom: 4px; transition: all 0.15s; }
        .student-item:hover { background: #f1f5f9; color: #0f172a; }
        .student-item.active { background: #3b82f6; color: white; }
        .student-avatar { width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px; flex-shrink: 0; }
        .student-item.active .student-avatar { background: rgba(255,255,255,0.2); color: white; }

        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; }
        
        /* Orta Kısım Grid */
        .middle-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
        @media (max-width: 900px) { .middle-layout { grid-template-columns: 1fr; } }

        .card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
        .card-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; }
        
        /* Seans Ücreti Alanı */
        .price-setter form { display: flex; align-items: center; gap: 8px; background: #f1f5f9; padding: 6px 12px; border-radius: 8px; }
        .price-setter label { font-size: 12px; font-weight: 600; color: #64748b; white-space: nowrap; }
        .price-setter input { width: 70px; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: bold; color: #0f172a; text-align: center; }
        .btn-save-price { padding: 4px 8px; background: #0f172a; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; }
        .btn-save-price:hover { background: #334155; }

        /* İstatistikler */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 8px; padding: 16px; border-left: 3px solid; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card.green { border-color: #10b981; } .stat-card.yellow { border-color: #f59e0b; }
        .stat-card.red { border-color: #ef4444; } .stat-card.blue { border-color: #3b82f6; }
        .stat-label { font-size: 10px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 5px; }
        .stat-value { font-size: 20px; font-weight: 700; color: #0f172a; }
        
        /* Form & Butonlar */
        .btn { padding: 8px 16px; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 6px; font-weight: 500; }
        
        .form-input, .form-select { width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; }
        
        /* Dikey Form */
        .vertical-form .form-group { margin-bottom: 12px; width: 100%; }
        .vertical-form label { display: block; margin-bottom: 4px; font-weight: 500; font-size: 12px; color: #64748b; }

        /* TOOLBAR */
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; background: #fff; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; flex-wrap: wrap; gap: 12px; }
        .toolbar-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .toolbar-label { font-size: 13px; font-weight: 500; color: #64748b; margin-right: 4px; }
        
        #btn-all.active, #btn-odenmedi.active, #btn-odendi.active { background-color: #3b82f6; color: white; }
        #btn-all, #btn-odenmedi, #btn-odendi { background-color: #e2e8f0; color: #475569; }
        
        .btn-paid-action { background-color: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0 !important; }
        .btn-delete-action { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca !important; }
        .btn-print-action { background-color: #1e293b; color: white; }

        /* TABLO */
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 600px; }
        th { background: #f8fafc; padding: 12px; text-align: left; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        
        .compact-table th, .compact-table td { padding: 8px 10px; font-size: 12px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; white-space: nowrap; }
        .badge.paid { background: #dcfce7; color: #15803d; }
        .badge.unpaid { background: #fee2e2; color: #b91c1c; }
        .icon-btn { border: none; background: none; cursor: pointer; font-size: 16px; padding: 4px; }
        .icon-btn.edit { color: #f97316; } .icon-btn.delete { color: #94a3b8; }
        
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index: 1000; padding: 20px; }
        .modal.active { display:flex; }
        .modal-content { background:white; padding:24px; border-radius:12px; width:100%; max-width: 400px; }

        .mobile-divider { display: none; }

        @media (max-width: 768px) {
            .layout { display: block; width: 100%; overflow-x: hidden; }
            .container { padding: 10px; width: 100%; max-width: 100vw; overflow-x: hidden; }
            .header { flex-direction: column; align-items: flex-start; }
            .middle-layout { margin-bottom: 0; }
            .card { padding: 15px; width: 100%; margin-bottom: 15px; }
            .toolbar { display: block; padding: 10px; }
            .toolbar-group { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; width: 100%; margin-bottom: 0; }
            .toolbar-label { display: none; }
            .mobile-divider { display: block; border-top: 1px solid #f1f5f9; margin: 10px 0; width: 100%; }
            .btn-sm { width: 100%; justify-content: center; padding: 8px 4px; font-size: 12px; }
            #paymentsTable { min-width: 600px; }
            
            /* Seanslar tablosu için özel mobil ayarı */
    .compact-table { 
        min-width: auto !important; /* Tabloyu ekrana sığdır */
        width: 100% !important; 
    }
    
    .compact-table th, .compact-table td { 
        padding: 8px 4px !important; /* Yan boşlukları daralt */
        white-space: normal !important; /* Yazı gerekirse alt satıra geçsin */
    }
    
    /* Butonun olduğu hücreyi sağa yasla ve daralt */
    .compact-table td:last-child {
        text-align: right;
        width: 1%;
        white-space: nowrap !important;
    }
        }

        /* ========== YAZDIRMA CSS (GÜNCELLENDİ) ========== */
        .print-header, .print-info-box, .print-summary-box, .print-footer { display: none; }
        
        @media print {
            @page { margin: 15mm; size: A4 portrait; }
            
            /* SİSTEM ELEMANLARINI GİZLE - GÜÇLENDİRİLMİŞ SELECTORLER */
            nav, header, footer, aside,
            #mobile-menu-drawer, #mobile-menu-overlay, /* Header.php'den gelen mobil menü */
            .toolbar, .add-section, .alert, .modal, .middle-layout, 
            .price-setter, .stats, .action-btns, .card-title,
            .btn, .sidebar, .header /* .header class'ı çakışabilir, tekrar gizle */
            { display: none !important; }
            
            /* Sayfa Yapısını Sıfırla */
            body, .container, .layout, .main, .card {
                background: white !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                display: block !important;
                position: static !important;
                box-shadow: none !important;
                border: none !important;
                grid-template-columns: none !important;
            }
            
            /* Yazdırma elemanlarını göster */
            .print-header, .print-info-box, .print-summary-box { display: block !important; }
            
            /* Header */
            .print-header { 
                display: flex !important; 
                justify-content: space-between !important; 
                margin-bottom: 15px !important; 
                padding-bottom: 8px !important; 
                border-bottom: 2px solid #000 !important; 
            }
            .print-logo { font-size: 20pt !important; font-weight: bold !important; }
            .print-date { text-align: right !important; font-size: 10pt !important; }
            
            /* Info Box */
            .print-info-box { 
                display: flex !important; 
                justify-content: space-between !important; 
                margin-bottom: 20px !important; 
                padding: 10px !important; 
                border: 1px solid #333 !important; 
                background: #f9f9f9 !important; 
            }
            .print-info-col { width: 48% !important; }
            .print-label { font-size: 9pt !important; color: #555 !important; margin-bottom: 3px !important; }
            .print-value { font-size: 12pt !important; font-weight: bold !important; }
            
            /* Tablo */
            table { 
                width: 100% !important; 
                border-collapse: collapse !important; 
                margin-bottom: 15px !important; 
                border: 1px solid #000 !important;
                min-width: 100% !important;
            }
            th { 
                background: #e0e0e0 !important; 
                border: 1px solid #000 !important; 
                padding: 8px !important; 
                font-size: 10pt !important; 
                color: #000 !important;
            }
            td { 
                border: 1px solid #000 !important; 
                padding: 6px !important; 
                font-size: 10pt !important; 
            }
            
            /* Sadece seçili satırları göster */
            tbody tr { display: none !important; }
            tbody tr.print-selected { display: table-row !important; }
            
            /* Summary Box */
            .print-summary-box { 
                margin-top: 15px !important; 
                padding: 12px !important; 
                border: 2px solid #000 !important; 
                background: #f5f5f5 !important; 
                width: 100% !important;
                box-sizing: border-box !important;
            }
            .print-summary-title { font-size: 11pt !important; font-weight: bold !important; margin-bottom: 8px !important; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
            .print-summary-row { 
                display: flex !important; 
                justify-content: space-between !important; 
                padding: 4px 0 !important; 
                font-size: 10pt !important; 
                width: 100% !important;
            }
            .print-summary-row.total { 
                border-top: 2px solid #000 !important; 
                margin-top: 6px !important; 
                padding-top: 8px !important; 
                font-weight: bold !important; 
                font-size: 11pt !important; 
            }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if($alert): ?>
    <div class="alert <?php echo $alert[0]; ?>"><?php echo htmlspecialchars($alert[1]); ?></div>
    <?php endif; ?>
    
    <div class="header">
        <h1 style="font-size: 20px; font-weight: 700;">💰 Ödeme Yönetimi</h1>
        <?php if($selected_student): ?>
        <span style="color: #64748b;">Öğrenci: <strong><?php echo htmlspecialchars($data['student_name']); ?></strong></span>
        <?php endif; ?>
    </div>
    
    <div class="layout">
        <aside class="sidebar">
            <div style="font-weight:600; margin-bottom:15px; font-size:14px; color:#64748b;">Öğrenciler (<?php echo count($student_list); ?>)</div>
            <?php foreach($student_list as $s): ?>
            <a href="?student_id=<?php echo $s['id']; ?>" class="student-item <?php echo $selected_student == $s['id'] ? 'active' : ''; ?>">
                <div class="student-avatar"><?php echo mb_strtoupper(mb_substr($s['first_name'], 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></span>
            </a>
            <?php endforeach; ?>
        </aside>
        
        <main class="main">
            <?php if($selected_student): ?>
                
                <div class="print-header">
                    <div class="print-logo">DersPROS</div>
                    <div class="print-date"><strong>Tarih:</strong> <?php echo date('d.m.Y'); ?></div>
                </div>
                
                <div class="print-info-box">
                    <div class="print-info-col">
                        <div class="print-label">Öğrenci</div>
                        <div class="print-value"><?php echo htmlspecialchars($data['student_name']); ?></div>
                    </div>
                    <div class="print-info-col" style="text-align: right;">
                        <div class="print-label">Öğretmen</div>
                        <div class="print-value"><?php echo htmlspecialchars(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''))); ?></div>
                    </div>
                </div>
                
                <div class="stats">
                    <div class="stat-card green"><div class="stat-label">Tahsil Edilen</div><div class="stat-value"><?php echo number_format($data['stats']['paid'], 0); ?>₺</div></div>
                    <div class="stat-card yellow"><div class="stat-label">Bekleyen</div><div class="stat-value"><?php echo number_format($data['stats']['unpaid'], 0); ?>₺</div></div>
                    <div class="stat-card red"><div class="stat-label">Gecikmiş</div><div class="stat-value"><?php echo number_format($data['stats']['overdue'], 0); ?>₺</div></div>
                    <div class="stat-card blue"><div class="stat-label">Toplam Kayıt</div><div class="stat-value"><?php echo number_format($data['stats']['total'], 0); ?></div></div>
                </div>

                <div class="middle-layout">
                    
                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-header-flex">
                            <div class="card-title">📅 Seanslar</div>
                            
                            <div class="price-setter">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_price">
                                    <label>Seans Ücreti:</label>
                                    <input type="number" name="new_price" id="global_lesson_price" value="<?php echo $data['lesson_price']; ?>" step="0.01">
                                    <button type="submit" class="btn-save-price">Kaydet</button>
                                </form>
                            </div>
                        </div>

                        <?php if(!empty($data['future_sessions'])): ?>
                        <div class="table-wrapper">
                            <table class="compact-table">
                                <thead>
                                    <tr>
                                        <th>Seans Başlığı</th>
                                        <th style="text-align:right">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($data['future_sessions'] as $fs): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y', strtotime($fs['appointment_date'])); ?> Tarihli</td>
                                        <td style="text-align:right">
                                            <button onclick="openAddModal('<?php echo $fs['appointment_date']; ?>')" class="btn btn-sm btn-primary">➕ Ekle</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <div class="empty" style="color:#94a3b8; font-style:italic; font-size:13px;">Ödeme atanacak gelecek seans yok.</div>
                        <?php endif; ?>
                    </div>

                    <div class="card add-section" style="margin-bottom: 0;">
                        <div class="card-title">➕ Manuel Ekle</div>
                        <form method="POST" class="vertical-form">
                            <input type="hidden" name="action" value="add_manual">
                            <div class="form-group">
                                <label>Açıklama</label>
                                <input type="text" name="description" class="form-input" required placeholder="Örn: 10 Şubat Seans Ücreti">
                            </div>
                            <div class="form-group">
                                <label>Tutar (₺)</label>
                                <input type="number" step="0.01" name="amount" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label>Vade</label>
                                <input type="date" name="due_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%">Kaydet</button>
                        </form>
                    </div>

                </div>
                <div class="card">
                    <div class="card-title">📊 Ödeme Geçmişi</div>
                    <?php if(!empty($data['payments'])): ?>
                    <div class="toolbar">
                        <div class="toolbar-group">
                            <span class="toolbar-label">Filtre:</span>
                            <button onclick="filterStatus('all')" class="btn btn-sm active" id="btn-all">Tümü</button>
                            <button onclick="filterStatus('odenmedi')" class="btn btn-sm" id="btn-odenmedi">Ödenmedi</button>
                            <button onclick="filterStatus('odendi')" class="btn btn-sm" id="btn-odendi">Ödendi</button>
                        </div>
                        
                        <div class="mobile-divider"></div>
                        
                        <div class="toolbar-group">
                            <span class="toolbar-label">İşlem:</span>
                            <button onclick="submitBulk('bulk_paid', 'ödendi işaretlenecek')" class="btn btn-sm btn-paid-action">✓ Öde</button>
                            <button onclick="submitBulk('bulk_delete', 'KALICI OLARAK SİLİNECEK')" class="btn btn-sm btn-delete-action">🗑️ Sil</button>
                            <button onclick="printSelected()" class="btn btn-sm btn-print-action">🖨️ Yazdır</button>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table id="paymentsTable">
                            <thead>
                                <tr>
                                    <th style="width: 30px;" class="action-btns"><input type="checkbox" onchange="toggleAll(this)"></th>
                                    <th>Açıklama</th>
                                    <th>Vade</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th class="action-btns">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($data['payments'] as $p): ?>
                                <tr data-status="<?php echo $p['status']; ?>" data-amount="<?php echo $p['amount']; ?>" data-dueraw="<?php echo $p['due_date']; ?>">
                                    <td class="action-btns"><input type="checkbox" class="row-check" value="<?php echo $p['id']; ?>"></td>
                                    <td><?php echo htmlspecialchars($p['description']); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($p['due_date'])); ?></td>
                                    <td style="font-weight:bold; color:#2563eb;"><?php echo number_format($p['amount'], 2); ?>₺</td>
                                    <td><span class="badge <?php echo $p['status']=='odendi'?'paid':'unpaid'; ?>"><?php echo $p['status']=='odendi'?'Ödendi':'Bekliyor'; ?></span></td>
                                    <td class="action-btns">
                                        <button class="icon-btn edit" onclick='edit(<?php echo json_encode($p); ?>)'>✏️</button>
                                        <button class="icon-btn delete" onclick="del(<?php echo $p['id']; ?>)">🗑️</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="print-summary-box">
                        <div class="print-summary-title">Özet</div>
                        <div class="print-summary-row">
                            <span>Ödenen:</span>
                            <strong id="print-sum-paid">0.00₺</strong>
                        </div>
                        <div class="print-summary-row">
                            <span>Bekleyen:</span>
                            <strong id="print-sum-unpaid">0.00₺</strong>
                        </div>
                        <div class="print-summary-row">
                            <span>Gecikmiş:</span>
                            <strong id="print-sum-overdue">0.00₺</strong>
                        </div>
                        <div class="print-summary-row total">
                            <span>Toplam:</span>
                            <strong id="print-sum-total">0.00₺</strong>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <div class="empty">Kayıt bulunamadı.</div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <div class="card"><div class="empty">İşlem yapmak için soldaki menüden bir öğrenci seçiniz.</div></div>
            <?php endif; ?>
        </main>
    </div>
</div>

<div class="modal" id="editModal">
    <div class="modal-content">
        <h3>Düzenle <button onclick="closeModal('editModal')" style="float:right; border:none; background:none; font-size:20px; cursor:pointer;">×</button></h3><br>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group" style="margin-bottom:12px"><label style="display:block;margin-bottom:4px">Açıklama</label><input type="text" name="description" id="edit_desc" class="form-input"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px">
                <div><label style="display:block;margin-bottom:4px">Tutar</label><input type="number" step="0.01" name="amount" id="edit_amount" class="form-input"></div>
                <div><label style="display:block;margin-bottom:4px">Vade</label><input type="date" name="due_date" id="edit_due" class="form-input"></div>
            </div>
            <div class="form-group" style="margin-bottom:12px"><label style="display:block;margin-bottom:4px">Durum</label>
                <select name="status" id="edit_status" class="form-select">
                    <option value="odenmedi">Ödenmedi</option>
                    <option value="odendi">Ödendi</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Güncelle</button>
        </form>
    </div>
</div>

<div class="modal" id="addModal">
    <div class="modal-content">
        <h3>Seans Ödemesi Ekle <button onclick="closeModal('addModal')" style="float:right; border:none; background:none; font-size:20px; cursor:pointer;">×</button></h3><br>
        <form method="POST">
            <input type="hidden" name="action" value="add_manual">
            <div class="form-group" style="margin-bottom:12px"><label style="display:block;margin-bottom:4px">Açıklama</label><input type="text" name="description" id="add_desc" class="form-input" required></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px">
                <div><label style="display:block;margin-bottom:4px">Tutar</label><input type="number" step="0.01" name="amount" id="add_amount" class="form-input" required></div>
                <div><label style="display:block;margin-bottom:4px">Vade</label><input type="date" name="due_date" id="add_due" class="form-input" required></div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Kaydet</button>
        </form>
    </div>
</div>

<script>
function openAddModal(dateStr) {
    const justDate = dateStr.split(' ')[0];
    const dateParts = justDate.split('-');
    const formattedDate = `${dateParts[2]}.${dateParts[1]}.${dateParts[0]}`;
    const defaultPrice = document.getElementById('global_lesson_price').value;
    
    document.getElementById('add_desc').value = formattedDate + ' Tarihli Seans';
    document.getElementById('add_amount').value = defaultPrice;
    document.getElementById('add_due').value = justDate;
    document.getElementById('addModal').classList.add('active');
}

function edit(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_desc').value = data.description;
    document.getElementById('edit_amount').value = data.amount;
    document.getElementById('edit_due').value = data.due_date;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('editModal').classList.add('active');
}
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function del(id) {
    if(confirm('Bu kayıt silinsin mi?')) {
        const f = document.createElement('form'); f.method='POST';
        f.innerHTML=`<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(f); f.submit();
    }
}

function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => {
        if(c.closest('tr').style.display !== 'none') c.checked = cb.checked;
    });
}

function submitBulk(action, msg) {
    const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(c => c.value);
    if(ids.length === 0) return alert('Lütfen listeden en az bir kayıt seçin.');
    if(!confirm(`${ids.length} adet kayıt ${msg}. Emin misiniz?`)) return;
    
    const f = document.createElement('form'); f.method='POST';
    f.innerHTML=`<input type="hidden" name="action" value="${action}">`;
    ids.forEach(id => f.innerHTML += `<input type="hidden" name="ids[]" value="${id}">`);
    document.body.appendChild(f); f.submit();
}

function filterStatus(status) {
    const rows = document.querySelectorAll('#paymentsTable tbody tr');
    document.querySelectorAll('.toolbar .btn-sm').forEach(btn => {
        if(btn.id.startsWith('btn-')) {
             btn.classList.remove('active');
             btn.style.backgroundColor = '#e2e8f0';
             btn.style.color = '#475569';
        }
    });
    const activeBtn = document.getElementById('btn-'+status);
    activeBtn.classList.add('active');
    activeBtn.style.backgroundColor = '#3b82f6';
    activeBtn.style.color = 'white';

    rows.forEach(row => {
        if(status === 'all') row.style.display = '';
        else row.style.display = (row.getAttribute('data-status') === status) ? '' : 'none';
    });
}

function printSelected() {
    const checkboxes = document.querySelectorAll('.row-check:checked');
    if(checkboxes.length === 0) {
        alert('Yazdırmak için lütfen en az bir kayıt seçin.');
        return;
    }
    
    // Seçili satırları işaretle
    document.querySelectorAll('tr').forEach(r => r.classList.remove('print-selected'));
    checkboxes.forEach(cb => cb.closest('tr').classList.add('print-selected'));
    
    // Özet hesaplama
    let sumPaid = 0, sumUnpaid = 0, sumOverdue = 0, sumTotal = 0;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    checkboxes.forEach(cb => {
        const row = cb.closest('tr');
        const status = row.getAttribute('data-status');
        const amount = parseFloat(row.getAttribute('data-amount'));
        const dueRaw = row.getAttribute('data-dueraw');
        const dueDate = new Date(dueRaw);
        
        sumTotal += amount;
        if (status === 'odendi') {
            sumPaid += amount;
        } else {
            if (dueDate < today) sumOverdue += amount;
            else sumUnpaid += amount;
        }
    });
    
    // Özet güncelle
    document.getElementById('print-sum-paid').textContent = sumPaid.toFixed(2) + '₺';
    document.getElementById('print-sum-unpaid').textContent = sumUnpaid.toFixed(2) + '₺';
    document.getElementById('print-sum-overdue').textContent = sumOverdue.toFixed(2) + '₺';
    document.getElementById('print-sum-total').textContent = sumTotal.toFixed(2) + '₺';
    
    window.print();
}

setTimeout(() => { const a = document.querySelector('.alert'); if(a) a.style.display='none'; }, 3000);
</script>

</body>
</html>