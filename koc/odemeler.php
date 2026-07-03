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
            case 'mark_paid': // Ödendi işaretle (+ düzenlenebilir tutar + isteğe bağlı dekont)
                if (payment_owned_by($pdo, $pid, $teacher_id)) {
                    $paidAmount = isset($_POST['amount']) && $_POST['amount'] !== '' ? (float)$_POST['amount'] : null;
                    $receiptPath = isset($_FILES['receipt']) ? odemeler_store_receipt($_FILES['receipt']) : null;
                    $set = "status='odendi', paid_date=CURDATE()";
                    $vals = [];
                    if ($paidAmount !== null) { $set .= ", amount=?"; $vals[] = $paidAmount; }
                    if ($receiptPath)        { $set .= ", receipt_path=?"; $vals[] = $receiptPath; }
                    $vals[] = $pid; $vals[] = $teacher_id;
                    $pdo->prepare("UPDATE payments SET $set WHERE id=? AND teacher_id=?")->execute($vals);
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

$data = ['student_name' => '', 'lesson_price' => 0, 'parent_phone' => '', 'student_phone' => '', 'stats' => ['paid'=>0,'unpaid'=>0,'overdue'=>0,'total'=>0], 'payments' => [], 'future_sessions' => []];

// ?msg= parametresini toast'a çevir (PRG geri bildirimi)
$msgMap = [
    'price_updated'=>['success','Seans ücreti güncellendi.'], 'added'=>['success','Ödeme kaydı eklendi.'],
    'updated'=>['info','Kayıt güncellendi.'], 'deleted'=>['warning','Kayıt silindi.'],
    'note_saved'=>['success','Not kaydedildi.'], 'paid'=>['success','Ödeme "ödendi" olarak işaretlendi.'],
    'unpaid'=>['warning','Ödeme geri alındı.'], 'receipt_added'=>['success','Dekont eklendi.'],
    'bulk_paid'=>['success','Seçili kayıtlar ödendi işaretlendi.'], 'bulk_deleted'=>['warning','Seçili kayıtlar silindi.'],
];
$flash = isset($_GET['msg']) && isset($msgMap[$_GET['msg']]) ? $msgMap[$_GET['msg']] : null;

if ($selected_student) {
    foreach($student_list as $s) { if($s['id'] == $selected_student) { $data['student_name'] = $s['first_name'].' '.$s['last_name']; $data['lesson_price'] = $s['lesson_price']; break; } }

    // Veli / öğrenci telefonu (WhatsApp için)
    try {
        $ph = $pdo->prepare("SELECT phone, parent_phone FROM users WHERE id = ?");
        $ph->execute([$selected_student]);
        if ($prow = $ph->fetch(PDO::FETCH_ASSOC)) {
            $data['student_phone'] = wa_phone($prow['phone'] ?? '');
            $data['parent_phone']  = wa_phone($prow['parent_phone'] ?? '');
        }
    } catch (Throwable $e) {}

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

<?php $oh = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); $B = defined('BASE_URL')?BASE_URL:''; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
:root{
  --atla-primary:#223488; --atla-primary-600:#314595; --atla-primary-050:#eef1fb;
  --atla-accent:#ec9731; --atla-accent-600:#d68625; --atla-accent-050:#fdf3e7;
  --success:#059669; --success-050:#ecfdf5; --warning:#d97706; --warning-050:#fffbeb;
  --error:#dc2626; --error-050:#fef2f2; --border:#e2e8f0;
}
.odm-wrap{max-width:1200px;margin:0 auto;padding:16px}
.odm-card{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
.odm-btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;min-height:44px;padding:0 1rem;border-radius:12px;font-weight:700;font-size:.82rem;border:1px solid transparent;cursor:pointer;transition:all .15s;white-space:nowrap}
.odm-btn:active{transform:scale(.97)}
.b-primary{background:var(--atla-primary);color:#fff}.b-primary:hover{background:var(--atla-primary-600)}
.b-accent{background:var(--atla-accent);color:#fff}.b-accent:hover{background:var(--atla-accent-600)}
.b-wa{background:#25D366;color:#fff}.b-wa:hover{background:#1ebe5b}
.b-soft{background:#fff;color:#334155;border-color:var(--border)}.b-soft:hover{background:#f8fafc}
.b-danger{background:var(--error-050);color:var(--error);border-color:#fecaca}.b-danger:hover{background:var(--error);color:#fff}
.b-sm{min-height:36px;padding:0 .7rem;font-size:.72rem;border-radius:10px}
.odm-badge{display:inline-flex;align-items:center;gap:.3rem;font-weight:700;font-size:.68rem;padding:.24rem .6rem;border-radius:999px;border:1px solid}
.bg-paid{background:var(--success-050);color:var(--success);border-color:#a7f3d0}
.bg-unpaid{background:var(--warning-050);color:var(--warning);border-color:#fde68a}
.bg-over{background:var(--error-050);color:var(--error);border-color:#fecaca}
.odm-input{width:100%;padding:.6rem .7rem;border:1px solid var(--border);border-radius:10px;font-size:.85rem;outline:none;font-family:inherit}
.odm-input:focus{border-color:var(--atla-primary)}
.stu-item{display:flex;align-items:center;gap:.6rem;padding:.55rem .6rem;border-radius:10px;text-decoration:none;color:#475569;font-weight:600;font-size:.85rem;transition:all .12s}
.stu-item:hover{background:#f1f5f9}
.stu-item.active{background:var(--atla-primary);color:#fff}
.stu-av{width:32px;height:32px;border-radius:999px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;flex-shrink:0}
.stu-item.active .stu-av{background:rgba(255,255,255,.2);color:#fff}
.odm-table{width:100%;border-collapse:collapse;font-size:.82rem}
.odm-table th{background:#f8fafc;padding:.6rem .7rem;text-align:left;font-weight:700;color:#64748b;font-size:.68rem;text-transform:uppercase;border-bottom:2px solid var(--border);white-space:nowrap}
.odm-table td{padding:.6rem .7rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.note-input{border:1px dashed var(--atla-accent);background:var(--atla-accent-050,#fdf3e7);border-radius:8px;padding:.35rem .5rem;font-size:.78rem;width:100%;min-width:110px;font-family:inherit;color:#92400e}
.note-input::placeholder{color:var(--atla-accent);opacity:.8;font-weight:600}
.note-input:hover{border-color:var(--atla-accent-600)}
.note-input:focus{border-color:var(--atla-primary);background:#fff;outline:none;color:#334155}
/* Toast */
#odmToast{position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;gap:8px;width:min(92vw,420px)}
.odm-toast{display:flex;align-items:center;gap:.6rem;padding:.85rem 1rem;border-radius:14px;font-weight:600;font-size:.85rem;box-shadow:0 10px 25px -5px rgba(0,0,0,.2);border:1px solid;animation:odmIn .35s cubic-bezier(.2,.8,.2,1)}
@keyframes odmIn{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:none}}
.odm-toast.success{background:var(--success-050);color:var(--success);border-color:#a7f3d0}
.odm-toast.info{background:var(--atla-primary-050);color:var(--atla-primary);border-color:#c7d2fe}
.odm-toast.warning{background:var(--warning-050);color:var(--warning);border-color:#fde68a}
.odm-toast.error{background:var(--error-050);color:var(--error);border-color:#fecaca}
/* Modal */
.odm-modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(2px);align-items:center;justify-content:center;z-index:1000;padding:16px}
.odm-modal.active{display:flex}
.odm-modal-c{background:#fff;border-radius:18px;width:100%;max-width:440px;overflow:hidden}
.stat-tile{background:#fff;border:1px solid var(--border);border-left-width:4px;border-radius:14px;padding:.9rem 1rem}
@media (max-width:900px){ .odm-grid{grid-template-columns:1fr !important} .odm-side{position:static !important} }
</style>

<div id="odmToast" aria-live="polite"></div>

<div class="odm-wrap">
  <!-- Başlık -->
  <div class="odm-card p-4 md:p-5 mb-4 flex flex-wrap items-center justify-between gap-3" style="background:linear-gradient(135deg,var(--atla-primary),var(--atla-primary-600));color:#fff;border:none">
    <div class="min-w-0">
      <h1 class="text-xl md:text-2xl font-extrabold">💰 Ödeme Yönetimi</h1>
      <?php if($selected_student): ?><p class="text-white/70 text-sm font-semibold mt-0.5"><?= $oh($data['student_name']) ?></p><?php endif; ?>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= $B ?>/koc/odemeler_ozet.php" class="odm-btn b-accent">📊 Tüm Öğrenciler Özeti</a>
    </div>
  </div>

  <div class="grid gap-4 odm-grid" style="grid-template-columns:250px 1fr">
    <!-- Öğrenci listesi -->
    <aside class="odm-card p-3 odm-side h-fit" style="position:sticky;top:16px">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wide px-1 mb-2">Öğrenciler (<?= count($student_list) ?>)</div>
      <div class="space-y-1 max-h-[70vh] overflow-y-auto">
      <?php foreach($student_list as $s): ?>
        <a href="?student_id=<?= (int)$s['id'] ?>" class="stu-item <?= $selected_student==$s['id']?'active':'' ?>">
          <span class="stu-av"><?= $oh(mb_strtoupper(mb_substr($s['first_name'],0,1))) ?></span>
          <span class="truncate"><?= $oh($s['first_name'].' '.$s['last_name']) ?></span>
        </a>
      <?php endforeach; ?>
      </div>
    </aside>

    <!-- Ana içerik -->
    <main>
    <?php if(!$selected_student): ?>
      <div class="odm-card p-10 text-center text-slate-400">
        <div class="text-4xl mb-2 opacity-40">👈</div>
        <p class="font-semibold">Soldaki listeden bir öğrenci seçin.</p>
      </div>
    <?php else:
      $stats = $data['stats'];
      $waParent = $data['parent_phone'] ?: $data['student_phone'];
      $outstanding = (float)$stats['unpaid'] + (float)$stats['overdue'];
    ?>
      <!-- İstatistik + aksiyonlar -->
      <div class="flex flex-wrap items-center justify-end gap-2 mb-3">
        <?php if($waParent): ?>
          <button type="button" onclick="sendReminder()" class="odm-btn b-wa">💬 Ödeme Talebi (Veli)</button>
        <?php else: ?>
          <span class="text-[11px] text-slate-400 font-semibold self-center">Veli/öğrenci telefonu yok — WhatsApp kapalı</span>
        <?php endif; ?>
        <button type="button" onclick="openStatement()" class="odm-btn b-soft">📄 Ekstre (PDF)</button>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="stat-tile" style="border-left-color:var(--success)"><div class="text-[11px] font-bold uppercase text-slate-400">Tahsil Edilen</div><div class="text-xl font-extrabold" style="color:var(--success)"><?= number_format($stats['paid'],0) ?>₺</div></div>
        <div class="stat-tile" style="border-left-color:var(--warning)"><div class="text-[11px] font-bold uppercase text-slate-400">Bekleyen</div><div class="text-xl font-extrabold" style="color:var(--warning)"><?= number_format($stats['unpaid'],0) ?>₺</div></div>
        <div class="stat-tile" style="border-left-color:var(--error)"><div class="text-[11px] font-bold uppercase text-slate-400">Gecikmiş</div><div class="text-xl font-extrabold" style="color:var(--error)"><?= number_format($stats['overdue'],0) ?>₺</div></div>
        <div class="stat-tile" style="border-left-color:var(--atla-primary)"><div class="text-[11px] font-bold uppercase text-slate-400">Toplam Borç</div><div class="text-xl font-extrabold" style="color:var(--atla-primary)"><?= number_format($outstanding,0) ?>₺</div></div>
      </div>

      <!-- Ücret + Manuel ekle + Gelecek seanslar -->
      <div class="grid lg:grid-cols-3 gap-4 mb-4">
        <div class="odm-card p-4 lg:col-span-2">
          <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
            <h3 class="font-extrabold text-slate-800">📅 Ödeme Atanmamış Seanslar</h3>
            <form method="POST" class="flex items-center gap-2 bg-slate-50 px-2.5 py-1.5 rounded-xl border border-slate-200">
              <input type="hidden" name="action" value="update_price">
              <label class="text-xs font-bold text-slate-500 whitespace-nowrap">Seans Ücreti</label>
              <input type="number" name="new_price" id="global_lesson_price" value="<?= $oh($data['lesson_price']) ?>" step="0.01" class="w-20 text-center font-bold text-sm border border-slate-200 rounded-lg px-1 py-1 outline-none focus:border-[color:var(--atla-primary)]">
              <button type="submit" class="odm-btn b-primary b-sm">Kaydet</button>
            </form>
          </div>
          <?php if(!empty($data['future_sessions'])): ?>
            <div class="space-y-1.5 max-h-52 overflow-y-auto">
            <?php foreach($data['future_sessions'] as $fs): ?>
              <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-sm font-semibold text-slate-700"><?= date('d.m.Y', strtotime($fs['appointment_date'])) ?> Tarihli Seans</span>
                <button onclick="openAddModal('<?= $oh($fs['appointment_date']) ?>')" class="odm-btn b-soft b-sm">➕ Borç Ekle</button>
              </div>
            <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-slate-400 font-medium py-4 text-center">Ödeme atanacak seans yok — tümü listelenmiş.</p>
          <?php endif; ?>
        </div>

        <div class="odm-card p-4">
          <h3 class="font-extrabold text-slate-800 mb-3">➕ Manuel Kayıt</h3>
          <form method="POST" class="space-y-2.5">
            <input type="hidden" name="action" value="add_manual">
            <div><label class="text-xs font-bold text-slate-500 block mb-1">Açıklama</label><input type="text" name="description" class="odm-input" required placeholder="Örn: Şubat ek ödeme"></div>
            <div class="grid grid-cols-2 gap-2">
              <div><label class="text-xs font-bold text-slate-500 block mb-1">Tutar ₺</label><input type="number" step="0.01" name="amount" class="odm-input" required></div>
              <div><label class="text-xs font-bold text-slate-500 block mb-1">Vade</label><input type="date" name="due_date" class="odm-input" required value="<?= date('Y-m-d') ?>"></div>
            </div>
            <button type="submit" class="odm-btn b-primary w-full">Ekle</button>
          </form>
        </div>
      </div>

      <!-- Ödeme geçmişi -->
      <div class="odm-card p-4">
        <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
          <h3 class="font-extrabold text-slate-800">📊 Ödeme Geçmişi</h3>
          <div class="flex items-center gap-1.5 flex-wrap">
            <button onclick="filterStatus('all',this)" class="odm-btn b-primary b-sm flt" data-f="all">Tümü</button>
            <button onclick="filterStatus('odenmedi',this)" class="odm-btn b-soft b-sm flt" data-f="odenmedi">Ödenmedi</button>
            <button onclick="filterStatus('odendi',this)" class="odm-btn b-soft b-sm flt" data-f="odendi">Ödendi</button>
            <span class="w-px h-6 bg-slate-200 mx-1"></span>
            <button onclick="submitBulk('bulk_paid','ödendi işaretlenecek')" class="odm-btn b-sm" style="background:var(--success-050);color:var(--success);border:1px solid #a7f3d0">✓ Toplu Öde</button>
            <button onclick="submitBulk('bulk_delete','KALICI SİLİNECEK')" class="odm-btn b-danger b-sm">🗑️ Toplu Sil</button>
          </div>
        </div>
        <?php if(!empty($data['payments'])): ?>
        <div class="overflow-x-auto">
          <table class="odm-table" id="paymentsTable">
            <thead><tr>
              <th style="width:28px"><input type="checkbox" onchange="toggleAll(this)"></th>
              <th>Açıklama</th><th>Not</th><th>Vade</th><th>Tutar</th><th>Durum</th><th>Dekont</th><th style="text-align:right">İşlem</th>
            </tr></thead>
            <tbody>
            <?php foreach($data['payments'] as $p):
              $isPaid = $p['status']==='odendi';
              $isOver = (!$isPaid && strtotime($p['due_date']) < strtotime(date('Y-m-d')));
              $badgeClass = $isPaid?'bg-paid':($isOver?'bg-over':'bg-unpaid');
              $badgeTxt = $isPaid?'Ödendi':($isOver?'Gecikmiş':'Bekliyor');
              $pj = $oh(json_encode($p, JSON_UNESCAPED_UNICODE));
            ?>
              <tr data-status="<?= $oh($p['status']) ?>">
                <td><input type="checkbox" class="row-check" value="<?= (int)$p['id'] ?>"></td>
                <td class="font-semibold text-slate-700"><?= $oh($p['description']) ?></td>
                <td>
                  <form method="POST" class="note-form" onsubmit="return true">
                    <input type="hidden" name="action" value="save_note">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="text" name="note" value="<?= $oh($p['note'] ?? '') ?>" placeholder="+ not ekle" class="note-input"
                           onblur="if(this.value!==this.defaultValue)this.form.submit()">
                  </form>
                </td>
                <td class="whitespace-nowrap text-slate-600"><?= date('d.m.Y', strtotime($p['due_date'])) ?></td>
                <td class="font-extrabold" style="color:var(--atla-primary)"><?= number_format($p['amount'],2) ?>₺</td>
                <td><span class="odm-badge <?= $badgeClass ?>"><?= $badgeTxt ?></span></td>
                <td>
                  <?php if(!empty($p['receipt_path'])): ?>
                    <a href="<?= $B ?>/koc/dekont.php?id=<?= (int)$p['id'] ?>" target="_blank" class="text-xs font-bold" style="color:var(--atla-primary)">📎 Gör</a>
                  <?php else: ?>
                    <span class="text-slate-300 text-xs">—</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;white-space:nowrap">
                  <?php if(!$isPaid): ?>
                    <button class="odm-btn b-sm" style="background:var(--success-050);color:var(--success);border:1px solid #a7f3d0" onclick='openPaidModal(<?= $pj ?>)' title="Ödendi + dekont">✓ Öde</button>
                    <?php if($waParent): ?><button class="odm-btn b-soft b-sm" onclick='remindOne(<?= $pj ?>)' title="Veliye hatırlat">💬</button><?php endif; ?>
                  <?php else: ?>
                    <button class="odm-btn b-soft b-sm" onclick="unmarkPaid(<?= (int)$p['id'] ?>)" title="Geri al">↩︎</button>
                  <?php endif; ?>
                  <button class="odm-btn b-soft b-sm" onclick='editPay(<?= $pj ?>)'>✏️</button>
                  <button class="odm-btn b-soft b-sm" onclick="delPay(<?= (int)$p['id'] ?>)">🗑️</button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="text-center py-10 text-slate-400">
            <div class="text-4xl mb-2 opacity-40">🧾</div>
            <p class="font-semibold">Henüz ödeme kaydı yok.</p>
            <p class="text-xs mt-1">Seanslar geçtikçe otomatik borç düşer ya da soldan manuel ekleyin.</p>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    </main>
  </div>
</div>

<!-- Gizli ekstre şablonu (PDF) -->
<div id="statementSheet" style="display:none;width:800px;background:#fff;color:#111;padding:28px;font-family:Poppins,Arial,sans-serif"></div>

<!-- Ekstre modalı (script confirm yerine) -->
<div class="odm-modal" id="stmtModal">
  <div class="odm-modal-c">
    <div class="p-4 text-white flex justify-between items-center" style="background:var(--atla-primary)">
      <h3 class="font-extrabold">📄 Ekstre Oluştur</h3>
      <button onclick="closeM('stmtModal')" aria-label="Kapat" class="bg-white/20 rounded-full w-8 h-8">✕</button>
    </div>
    <div class="p-5 space-y-4">
      <div>
        <label class="text-xs font-bold text-slate-500 block mb-1.5">Kapsam</label>
        <div class="flex gap-2">
          <button type="button" id="stmt_all" onclick="stmtSetScope('all')" class="odm-btn b-primary flex-1">Tüm Kayıtlar</button>
          <button type="button" id="stmt_unpaid" onclick="stmtSetScope('odenmedi')" class="odm-btn b-soft flex-1">Sadece Ödenmemiş</button>
        </div>
      </div>
      <div class="pt-1 grid grid-cols-2 gap-2">
        <button type="button" onclick="stmtDownload()" class="odm-btn b-soft">⬇️ PDF İndir</button>
        <button type="button" onclick="stmtWhatsApp()" class="odm-btn b-wa">💬 WhatsApp Gönder</button>
      </div>
    </div>
  </div>
</div>

<!-- Ödendi + dekont modalı -->
<div class="odm-modal" id="paidModal">
  <div class="odm-modal-c">
    <div class="p-4 text-white flex justify-between items-center" style="background:var(--atla-primary)">
      <h3 class="font-extrabold">Ödeme Al</h3>
      <button onclick="closeM('paidModal')" aria-label="Kapat" class="bg-white/20 rounded-full w-8 h-8">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-5 space-y-3">
      <input type="hidden" name="action" value="mark_paid">
      <input type="hidden" name="id" id="paid_id">
      <p class="text-sm text-slate-600" id="paid_desc"></p>
      <div>
        <label class="text-xs font-bold text-slate-500 block mb-1">Alınan Tutar (₺) — eksik ödeme için değiştirebilirsiniz</label>
        <input type="number" step="0.01" name="amount" id="paid_amount" class="odm-input" required>
      </div>
      <div>
        <label class="text-xs font-bold text-slate-500 block mb-1">Dekont (isteğe bağlı — resim/PDF)</label>
        <input type="file" name="receipt" accept="image/*,application/pdf" class="odm-input" style="padding:.4rem">
      </div>
      <div class="flex gap-2 pt-1">
        <button type="button" onclick="closeM('paidModal')" class="odm-btn b-soft flex-1">Vazgeç</button>
        <button type="submit" class="odm-btn b-primary flex-1" style="background:var(--success)">✓ Ödendi İşaretle</button>
      </div>
    </form>
  </div>
</div>

<!-- Düzenle modalı -->
<div class="odm-modal" id="editModal">
  <div class="odm-modal-c">
    <div class="p-4 text-white flex justify-between items-center" style="background:var(--atla-primary)">
      <h3 class="font-extrabold">Kaydı Düzenle</h3>
      <button onclick="closeM('editModal')" aria-label="Kapat" class="bg-white/20 rounded-full w-8 h-8">✕</button>
    </div>
    <form method="POST" class="p-5 space-y-3">
      <input type="hidden" name="action" value="update"><input type="hidden" name="id" id="edit_id">
      <div><label class="text-xs font-bold text-slate-500 block mb-1">Açıklama</label><input type="text" name="description" id="edit_desc" class="odm-input"></div>
      <div class="grid grid-cols-2 gap-2">
        <div><label class="text-xs font-bold text-slate-500 block mb-1">Tutar</label><input type="number" step="0.01" name="amount" id="edit_amount" class="odm-input"></div>
        <div><label class="text-xs font-bold text-slate-500 block mb-1">Vade</label><input type="date" name="due_date" id="edit_due" class="odm-input"></div>
      </div>
      <div><label class="text-xs font-bold text-slate-500 block mb-1">Durum</label>
        <select name="status" id="edit_status" class="odm-input"><option value="odenmedi">Ödenmedi</option><option value="odendi">Ödendi</option></select>
      </div>
      <button type="submit" class="odm-btn b-primary w-full">Güncelle</button>
    </form>
  </div>
</div>

<!-- Manuel ekle modalı (gelecek seanstan) -->
<div class="odm-modal" id="addModal">
  <div class="odm-modal-c">
    <div class="p-4 text-white flex justify-between items-center" style="background:var(--atla-primary)">
      <h3 class="font-extrabold">Seans Borcu Ekle</h3>
      <button onclick="closeM('addModal')" aria-label="Kapat" class="bg-white/20 rounded-full w-8 h-8">✕</button>
    </div>
    <form method="POST" class="p-5 space-y-3">
      <input type="hidden" name="action" value="add_manual">
      <div><label class="text-xs font-bold text-slate-500 block mb-1">Açıklama</label><input type="text" name="description" id="add_desc" class="odm-input" required></div>
      <div class="grid grid-cols-2 gap-2">
        <div><label class="text-xs font-bold text-slate-500 block mb-1">Tutar</label><input type="number" step="0.01" name="amount" id="add_amount" class="odm-input" required></div>
        <div><label class="text-xs font-bold text-slate-500 block mb-1">Vade</label><input type="date" name="due_date" id="add_due" class="odm-input" required></div>
      </div>
      <button type="submit" class="odm-btn b-primary w-full">Ekle</button>
    </form>
  </div>
</div>

<script>
const ODM = {
  student: <?= json_encode($data['student_name']) ?>,
  teacher: <?= json_encode(trim(($_SESSION['first_name']??'').' '.($_SESSION['last_name']??''))) ?>,
  parentPhone: <?= json_encode($data['parent_phone'] ?: $data['student_phone']) ?>,
  payments: <?= json_encode($data['payments'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
  base: <?= json_encode($B) ?>
};

function toast(type,msg,t){ const w=document.getElementById('odmToast'); if(!w)return;
  const ic={success:'✅',info:'💬',warning:'⚠️',error:'⛔'}; const e=document.createElement('div');
  e.className='odm-toast '+(type||'info'); e.innerHTML='<span>'+(ic[type]||'💬')+'</span><span style="flex:1">'+msg+'</span>';
  w.appendChild(e); setTimeout(()=>{e.style.opacity='0';e.style.transition='opacity .3s';setTimeout(()=>e.remove(),300)}, t||3500);
}
<?php if($flash): ?>toast(<?= json_encode($flash[0]) ?>, <?= json_encode($flash[1]) ?>);<?php endif; ?>

function closeM(id){ document.getElementById(id).classList.remove('active'); }
document.addEventListener('keydown',e=>{ if(e.key==='Escape') document.querySelectorAll('.odm-modal.active').forEach(m=>m.classList.remove('active')); });
document.querySelectorAll('.odm-modal').forEach(m=>m.addEventListener('click',e=>{ if(e.target===m) m.classList.remove('active'); }));

function openAddModal(dateStr){
  const d=(dateStr||'').split(' ')[0], pr=document.getElementById('global_lesson_price');
  const p=d.split('-'); const tr=p.length===3?`${p[2]}.${p[1]}.${p[0]}`:d;
  document.getElementById('add_desc').value = tr+' Tarihli Seans';
  document.getElementById('add_amount').value = pr?pr.value:'';
  document.getElementById('add_due').value = d;
  document.getElementById('addModal').classList.add('active');
}
function editPay(p){
  document.getElementById('edit_id').value=p.id; document.getElementById('edit_desc').value=p.description;
  document.getElementById('edit_amount').value=p.amount; document.getElementById('edit_due').value=p.due_date;
  document.getElementById('edit_status').value=p.status; document.getElementById('editModal').classList.add('active');
}
function openPaidModal(p){
  document.getElementById('paid_id').value=p.id;
  document.getElementById('paid_amount').value=p.amount;
  document.getElementById('paid_desc').textContent='Beklenen: '+p.description+' ('+Number(p.amount).toLocaleString('tr-TR')+'₺)';
  document.getElementById('paidModal').classList.add('active');
}
function postAction(fields){ const f=document.createElement('form'); f.method='POST';
  f.innerHTML=Object.entries(fields).map(([k,v])=>`<input type="hidden" name="${k}" value="${String(v).replace(/"/g,'&quot;')}">`).join('');
  document.body.appendChild(f); f.submit(); }
function unmarkPaid(id){ if(confirm('Ödemeyi geri almak istiyor musunuz?')) postAction({action:'unmark_paid',id}); }
function delPay(id){ if(confirm('Bu kayıt silinsin mi?')) postAction({action:'delete',id}); }

function toggleAll(cb){ document.querySelectorAll('.row-check').forEach(c=>{ if(c.closest('tr').style.display!=='none') c.checked=cb.checked; }); }
function submitBulk(action,msg){ const ids=[...document.querySelectorAll('.row-check:checked')].map(c=>c.value);
  if(!ids.length) return toast('warning','Lütfen en az bir kayıt seçin.');
  if(!confirm(`${ids.length} kayıt ${msg}. Emin misiniz?`)) return;
  const f=document.createElement('form'); f.method='POST';
  f.innerHTML=`<input type="hidden" name="action" value="${action}">`+ids.map(i=>`<input type="hidden" name="ids[]" value="${i}">`).join('');
  document.body.appendChild(f); f.submit();
}
function filterStatus(status,btn){
  document.querySelectorAll('.flt').forEach(b=>{ b.classList.remove('b-primary'); b.classList.add('b-soft'); });
  btn.classList.remove('b-soft'); btn.classList.add('b-primary');
  document.querySelectorAll('#paymentsTable tbody tr').forEach(r=>{
    r.style.display = (status==='all' || r.getAttribute('data-status')===status) ? '' : 'none';
  });
}

/* ── WhatsApp ödeme hatırlatma (wa.me evrensel link — hem mobil app hem masaüstü) ── */
function waSend(phone,text){
  if(!phone){ toast('warning','Telefon kayıtlı değil.'); return; }
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`,'_blank');
}
function remindOne(p){
  if(!ODM.parentPhone) return toast('warning','Telefon kayıtlı değil.');
  const due=new Date(p.due_date).toLocaleDateString('tr-TR');
  const t=`Sayın velimiz,\n${ODM.student} için ödeme hatırlatması:\n• ${p.description}\n• Tutar: ${Number(p.amount).toLocaleString('tr-TR')}₺\n• Vade: ${due}\nİlginiz için teşekkürler. — ${ODM.teacher}`;
  waSend(ODM.parentPhone,t);
}
function sendReminder(){
  if(!ODM.parentPhone) return toast('warning','Telefon kayıtlı değil.');
  const unpaid=ODM.payments.filter(p=>p.status!=='odendi');
  const total=unpaid.reduce((s,p)=>s+Number(p.amount),0);
  if(total<=0) return toast('info','Bekleyen ödeme yok.');
  let t=`Sayın velimiz,\n${ODM.student} için güncel ödeme durumu:\n`;
  unpaid.slice(0,12).forEach(p=>{ t+=`• ${new Date(p.due_date).toLocaleDateString('tr-TR')} — ${Number(p.amount).toLocaleString('tr-TR')}₺\n`; });
  t+=`\nToplam bekleyen: ${total.toLocaleString('tr-TR')}₺\nİlginiz için teşekkürler. — ${ODM.teacher}`;
  waSend(ODM.parentPhone,t);
}

/* ── Ekstre (PDF): modal ile kapsam + eylem seçimi ── */
let STMT_SCOPE = 'all';
function openStatement(){ stmtSetScope('all'); document.getElementById('stmtModal').classList.add('active'); }
function stmtSetScope(scope){
  STMT_SCOPE = scope;
  const a=document.getElementById('stmt_all'), u=document.getElementById('stmt_unpaid');
  a.classList.toggle('b-primary', scope==='all'); a.classList.toggle('b-soft', scope!=='all');
  u.classList.toggle('b-primary', scope!=='all'); u.classList.toggle('b-soft', scope==='all');
}
async function stmtDownload(){ buildStatement(STMT_SCOPE); closeM('stmtModal'); await withSheet(el=>html2pdf().set(_pdfOpt()).from(el).save()); }
async function stmtWhatsApp(){
  if(!ODM.parentPhone){ toast('warning','Telefon kayıtlı değil.'); return; }
  buildStatement(STMT_SCOPE); closeM('stmtModal');
  await sendStatementWA();
}
/* Gizli şablonu geçici görünür yapıp html2pdf ile yakala.
   NOT: scrollIntoView KULLANMA — sayfa scroll'u html2canvas çıktısını kaydırır/boşaltır.
   Kanıtlanmış desen: display:block + html2canvas scrollY:0 (koc/sidebar.php ile birebir). */
async function withSheet(fn){
  const el=document.getElementById('statementSheet');
  el.style.display='block';
  await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))); // render otursun
  try { return await fn(el); } finally { el.style.display='none'; }
}
function buildStatement(scope){
  const rows = ODM.payments.filter(p=> scope==='all' ? true : p.status!=='odendi');
  let paid=0,unpaid=0;
  ODM.payments.forEach(p=> p.status==='odendi' ? paid+=Number(p.amount) : unpaid+=Number(p.amount));
  const body = rows.map(p=>`<tr>
      <td style="border:1px solid #ccc;padding:6px">${p.description}</td>
      <td style="border:1px solid #ccc;padding:6px;white-space:nowrap">${new Date(p.due_date).toLocaleDateString('tr-TR')}</td>
      <td style="border:1px solid #ccc;padding:6px;text-align:right;font-weight:700">${Number(p.amount).toLocaleString('tr-TR')}₺</td>
      <td style="border:1px solid #ccc;padding:6px">${p.status==='odendi'?'Ödendi':'Bekliyor'}</td>
    </tr>`).join('');
  document.getElementById('statementSheet').innerHTML = `
    <div style="display:flex;justify-content:space-between;border-bottom:3px solid #223488;padding-bottom:10px;margin-bottom:16px">
      <div style="font-size:22px;font-weight:800;color:#223488">Ders<span style="color:#ec9731">PROS</span></div>
      <div style="text-align:right;font-size:12px">Tarih: ${new Date().toLocaleDateString('tr-TR')}</div>
    </div>
    <div style="display:flex;justify-content:space-between;margin-bottom:14px;font-size:13px">
      <div><b>Öğrenci:</b> ${ODM.student}</div><div><b>Öğretmen:</b> ${ODM.teacher}</div>
    </div>
    <div style="font-size:15px;font-weight:700;margin-bottom:8px">Ödeme Ekstresi (${scope==='all'?'Tümü':'Ödenmemişler'})</div>
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead><tr style="background:#f1f5f9">
        <th style="border:1px solid #ccc;padding:6px;text-align:left">Açıklama</th>
        <th style="border:1px solid #ccc;padding:6px;text-align:left">Vade</th>
        <th style="border:1px solid #ccc;padding:6px;text-align:right">Tutar</th>
        <th style="border:1px solid #ccc;padding:6px;text-align:left">Durum</th>
      </tr></thead><tbody>${body||'<tr><td colspan="4" style="padding:10px;text-align:center;color:#888">Kayıt yok</td></tr>'}</tbody>
    </table>
    <div style="margin-top:16px;border-top:2px solid #223488;padding-top:10px;font-size:13px">
      <div style="display:flex;justify-content:space-between"><span>Toplam Tahsil Edilen:</span><b style="color:#059669">${paid.toLocaleString('tr-TR')}₺</b></div>
      <div style="display:flex;justify-content:space-between"><span>Toplam Bekleyen:</span><b style="color:#d97706">${unpaid.toLocaleString('tr-TR')}₺</b></div>
    </div>`;
}
function _pdfOpt(){ return { margin:8, filename:`Ekstre_${ODM.student}.pdf`, image:{type:'jpeg',quality:.98},
  html2canvas:{scale:2, useCORS:true, scrollY:0}, jsPDF:{unit:'mm',format:'a4',orientation:'portrait'} }; }
async function sendStatementWA(){
  try{
    toast('info','Ekstre hazırlanıyor…');
    const blob = await withSheet(el=>html2pdf().set(_pdfOpt()).from(el).outputPdf('blob'));
    const fd=new FormData(); fd.append('pdf', blob, `Ekstre_${ODM.student}.pdf`);
    const r=await fetch(ODM.base+'/save_temp_pdf.php',{method:'POST',body:fd}); const j=await r.json();
    if(!j.ok) throw new Error(j.error||'Yükleme hatası');
    waSend(ODM.parentPhone, `📄 ${ODM.student} - Ödeme Ekstresi:\n${j.url}`);
  }catch(e){ toast('error','Ekstre gönderilemedi: '+e.message); }
}
</script>
<?php include '../footer.php'; ?>
