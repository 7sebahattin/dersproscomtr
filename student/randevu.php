<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../db.php';
include __DIR__ . '/../header.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function normalize_time($t){
    $t = trim((string)$t);
    if ($t === '') return '';
    if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
    return $t;
}

if (!isset($_SESSION['user_id'])) { echo "<script>window.location.href='/login.php';</script>"; exit; }

$role = $_SESSION['role'] ?? null;
$user_id = (int)$_SESSION['user_id'];

// Bu sayfa: öğrenci + veli
if ($role !== 'student' && $role !== 'parent') {
    echo "<div class='max-w-3xl mx-auto p-6'>
            <div class='bg-red-50 text-red-700 border border-red-100 p-5 rounded-3xl font-bold'>
              ⛔ Bu sayfaya erişim yetkiniz yok.
            </div>
          </div>";
    include __DIR__ . '/../footer.php';
    exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

$message = "";

// URL’yi (query ile) koru: base href vb. şeyler form action’ı şaşırtmasın
$self_url = $_SERVER['REQUEST_URI'] ?? '';

/** Parent ise çocuklar */
$children = [];
$selected_student_id = null;

if ($role === 'parent') {
    $st = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name
        FROM parent_relationships pr
        JOIN users u ON u.id = pr.student_id
        WHERE pr.parent_id = ?
        ORDER BY u.first_name ASC, u.last_name ASC
    ");
    $st->execute([$user_id]);
    $children = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($children)) {
        echo "<div class='max-w-3xl mx-auto p-6'>
                <div class='bg-amber-50 text-amber-800 border border-amber-200 p-5 rounded-3xl font-bold'>
                  ⚠️ Bu veli hesabına bağlı öğrenci bulunamadı.
                </div>
              </div>";
        include __DIR__ . '/../footer.php';
        exit;
    }

    $requested_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (int)$children[0]['id'];
    $ok = false;
    foreach ($children as $ch) if ((int)$ch['id'] === $requested_student) { $ok = true; break; }
    $selected_student_id = $ok ? $requested_student : (int)$children[0]['id'];
} else {
    $selected_student_id = $user_id;
}

/** 7 gün */
$start = $_GET['start'] ?? date('Y-m-d');
$week_start = date('Y-m-d', strtotime($start));
$week_end   = date('Y-m-d', strtotime("$week_start +6 days"));
$prev_week  = date('Y-m-d', strtotime("-7 days", strtotime($week_start)));
$next_week  = date('Y-m-d', strtotime("+7 days", strtotime($week_start)));

$gunlerTR = [
    'Monday'=>'Pazartesi','Tuesday'=>'Salı','Wednesday'=>'Çarşamba',
    'Thursday'=>'Perşembe','Friday'=>'Cuma','Saturday'=>'Cumartesi','Sunday'=>'Pazar'
];

/** Ownership helper */
function ensureAppointmentOwner(PDO $pdo, int $appointment_id, int $selected_student_id): ?array {
    $st = $pdo->prepare("SELECT * FROM appointments WHERE id=? LIMIT 1");
    $st->execute([$appointment_id]);
    $app = $st->fetch(PDO::FETCH_ASSOC);
    if (!$app) return null;
    if ((int)$app['student_id'] !== $selected_student_id) return ['_forbidden'=>true];
    return $app;
}

/** POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf, $post_csrf)) {
        $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Güvenlik doğrulaması başarısız (CSRF).</div>";
    } else {

        // (1) Kendime Not
        if (isset($_POST['save_student_note'])) {
            $app_id = (int)($_POST['appointment_id'] ?? 0);
            $note = trim((string)($_POST['student_note'] ?? ''));

            if ($app_id <= 0) {
                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Randevu ID boş geldi. (Modal açılırken ID set edilmemiş olabilir.)</div>";
            } else {
                $app = ensureAppointmentOwner($pdo, $app_id, $selected_student_id);
                if (!$app) {
                    $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Randevu bulunamadı.</div>";
                } elseif (!empty($app['_forbidden'])) {
                    $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⛔ Bu randevu için yetkin yok.</div>";
                } else {
                    try {
                        $up = $pdo->prepare("UPDATE appointments SET student_note=? WHERE id=?");
                        $up->execute([$note, $app_id]);
                        $message = "<div class='bg-green-50 text-green-700 p-4 rounded-2xl mb-4 font-black border border-green-100'>✅ Kendime Not kaydedildi.</div>";
                    } catch (Throwable $e) {
                        $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Not kaydedilemedi: ".h($e->getMessage())."</div>";
                    }
                }
            }
        }

        // (2) Talep gönder (Mevcut randevu için iptal/değişiklik)
        if (isset($_POST['send_request'])) {
            $app_id = (int)($_POST['appointment_id'] ?? 0);
            $type = $_POST['type'] ?? '';
            $msg = trim((string)($_POST['message'] ?? ''));

            $pdate = $_POST['proposed_date'] ?? null;
            $ptime = normalize_time($_POST['proposed_time'] ?? '');
            $pdur  = isset($_POST['proposed_duration']) && $_POST['proposed_duration'] !== '' ? (int)$_POST['proposed_duration'] : null;

            if ($app_id <= 0) {
                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Randevu ID boş geldi. (Talep modalında appointment_id set edilmemiş olabilir.)</div>";
            } elseif ($type !== 'cancel' && $type !== 'reschedule') {
                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Talep tipi geçersiz.</div>";
            } else {
                $app = ensureAppointmentOwner($pdo, $app_id, $selected_student_id);
                if (!$app) {
                    $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Randevu bulunamadı.</div>";
                } elseif (!empty($app['_forbidden'])) {
                    $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⛔ Bu randevu için yetkin yok.</div>";
                } else {
                    if (($app['status'] ?? 'active') !== 'active') {
                        $message = "<div class='bg-amber-50 text-amber-800 p-4 rounded-2xl mb-4 font-black border border-amber-200'>⚠️ Bu randevu aktif değil, talep gönderemezsin.</div>";
                    } else {
                        if ($type === 'reschedule') {
                            $hasAny = (!empty($pdate) || !empty($ptime) || !empty($pdur));
                            if (!$hasAny) {
                                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Değişiklik talebi için en az bir öneri (tarih/saat/süre) gir.</div>";
                            }
                        }
                        if ($message === "") {
                            $chk = $pdo->prepare("
                                SELECT COUNT(*)
                                FROM appointment_requests
                                WHERE appointment_id=? AND requester_user_id=? AND requester_role=? AND status='pending'
                            ");
                            $chk->execute([$app_id, $user_id, $role]);
                            if ((int)$chk->fetchColumn() > 0) {
                                $message = "<div class='bg-amber-50 text-amber-800 p-4 rounded-2xl mb-4 font-black border border-amber-200'>⏳ Bu randevu için zaten bekleyen bir talebin var.</div>";
                            } else {
                                try {
                                    $ins = $pdo->prepare("
                                        INSERT INTO appointment_requests
                                        (appointment_id, requester_user_id, requester_role, type, message, proposed_date, proposed_time, proposed_duration, status, created_at)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                                    ");
                                    $ins->execute([
                                        $app_id, $user_id, $role, $type,
                                        $msg ?: null,
                                        $pdate ?: null,
                                        $ptime ?: null,
                                        $pdur
                                    ]);
                                    $message = "<div class='bg-green-50 text-green-700 p-4 rounded-2xl mb-4 font-black border border-green-100'>✅ Talebin gönderildi. (Bekliyor)</div>";
                                } catch (Throwable $e) {
                                    $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Talep gönderilemedi: ".h($e->getMessage())."</div>";
                                }
                            }
                        }
                    }
                }
            }
        }

        // (3) Pending talebi iptal et (DELETE)
        if (isset($_POST['cancel_request'])) {
            $req_id = (int)($_POST['request_id'] ?? 0);
            $st = $pdo->prepare("SELECT * FROM appointment_requests WHERE id=? LIMIT 1");
            $st->execute([$req_id]);
            $req = $st->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Talep bulunamadı.</div>";
            } else {
                $app = ensureAppointmentOwner($pdo, (int)$req['appointment_id'], $selected_student_id);
                $isMine = ((int)$req['requester_user_id'] === $user_id) && (($req['requester_role'] ?? '') === $role);

                if (!$app || !empty($app['_forbidden']) || !$isMine) {
                    $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⛔ Bu talep için yetkin yok.</div>";
                } elseif (($req['status'] ?? '') !== 'pending') {
                    $message = "<div class='bg-amber-50 text-amber-800 p-4 rounded-2xl mb-4 font-black border border-amber-200'>⚠️ Bu talep artık beklemede değil, iptal edemezsin.</div>";
                } else {
                    try {
                        $del = $pdo->prepare("DELETE FROM appointment_requests WHERE id=?");
                        $del->execute([$req_id]);
                        $message = "<div class='bg-slate-100 text-slate-800 p-4 rounded-2xl mb-4 font-black border border-slate-200'>🧹 Talep iptal edildi (kaldırıldı).</div>";
                    } catch (Throwable $e) {
                        $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Talep iptal edilemedi: ".h($e->getMessage())."</div>";
                    }
                }
            }
        }

        // (4) Pending talebi güncelle
        if (isset($_POST['update_request'])) {
            $req_id = (int)($_POST['request_id'] ?? 0);
            $type = $_POST['type'] ?? '';
            $msg = trim((string)($_POST['message'] ?? ''));

            $pdate = $_POST['proposed_date'] ?? null;
            $ptime = normalize_time($_POST['proposed_time'] ?? '');
            $pdur  = isset($_POST['proposed_duration']) && $_POST['proposed_duration'] !== '' ? (int)$_POST['proposed_duration'] : null;

            if ($type !== 'cancel' && $type !== 'reschedule') {
                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Talep tipi geçersiz.</div>";
            } else {
                $st = $pdo->prepare("SELECT * FROM appointment_requests WHERE id=? LIMIT 1");
                $st->execute([$req_id]);
                $req = $st->fetch(PDO::FETCH_ASSOC);

                if (!$req) {
                    $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Talep bulunamadı.</div>";
                } else {
                    $app = ensureAppointmentOwner($pdo, (int)$req['appointment_id'], $selected_student_id);
                    $isMine = ((int)$req['requester_user_id'] === $user_id) && (($req['requester_role'] ?? '') === $role);

                    if (!$app || !empty($app['_forbidden']) || !$isMine) {
                        $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⛔ Bu talep için yetkin yok.</div>";
                    } elseif (($req['status'] ?? '') !== 'pending') {
                        $message = "<div class='bg-amber-50 text-amber-800 p-4 rounded-2xl mb-4 font-black border border-amber-200'>⚠️ Bu talep artık beklemede değil, düzenleyemezsin.</div>";
                    } else {
                        if ($type === 'reschedule') {
                            $hasAny = (!empty($pdate) || !empty($ptime) || !empty($pdur));
                            if (!$hasAny) {
                                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Değişiklik talebi için en az bir öneri (tarih/saat/süre) gir.</div>";
                            }
                        }
                        if ($message === "") {
                            try {
                                $up = $pdo->prepare("
                                    UPDATE appointment_requests
                                    SET type=?, message=?, proposed_date=?, proposed_time=?, proposed_duration=?
                                    WHERE id=?
                                ");
                                $up->execute([
                                    $type,
                                    $msg ?: null,
                                    $pdate ?: null,
                                    $ptime ?: null,
                                    $pdur,
                                    $req_id
                                ]);
                                $message = "<div class='bg-blue-50 text-blue-700 p-4 rounded-2xl mb-4 font-black border border-blue-100'>✏️ Talep güncellendi (hala beklemede).</div>";
                            } catch (Throwable $e) {
                                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-2xl mb-4 font-black border border-red-100'>⚠️ Talep güncellenemedi: ".h($e->getMessage())."</div>";
                            }
                        }
                    }
                }
            }
        }
    }
}

/** 7 gün randevular */
$app_stmt = $pdo->prepare("
    SELECT a.*,
           t.first_name AS t_fn, t.last_name AS t_ln
    FROM appointments a
    JOIN users t ON t.id = a.teacher_id
    WHERE a.student_id = ?
      AND a.appointment_date BETWEEN ? AND ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$app_stmt->execute([$selected_student_id, $week_start, $week_end]);
$appointments = $app_stmt->fetchAll(PDO::FETCH_ASSOC);

$app_ids = array_map(fn($a)=>(int)$a['id'], $appointments);
$requests_by_app = [];

if (!empty($app_ids)) {
    $in = implode(',', array_fill(0, count($app_ids), '?'));
    $rq = $pdo->prepare("
        SELECT *
        FROM appointment_requests
        WHERE appointment_id IN ($in)
        ORDER BY created_at DESC
    ");
    $rq->execute($app_ids);
    $rows = $rq->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $aid = (int)$r['appointment_id'];
        $requests_by_app[$aid][] = $r;
    }
}

/** Grupla */
$grouped_appointments = [];
$daily_counts_total = [];
$daily_counts_active = [];
$week_stats = ['total'=>0,'active'=>0,'cancelled'=>0,'minutes'=>0];

foreach ($appointments as $app) {
    $d = $app['appointment_date'];
    $grouped_appointments[$d][] = $app;

    $daily_counts_total[$d] = ($daily_counts_total[$d] ?? 0) + 1;
    if (($app['status'] ?? 'active') === 'active') {
        $daily_counts_active[$d] = ($daily_counts_active[$d] ?? 0) + 1;
        $week_stats['active']++;
    } else {
        $week_stats['cancelled']++;
    }
    $week_stats['total']++;
    $week_stats['minutes'] += (int)($app['duration'] ?? 0);
}

/** 7 gün üret */
$week_days = [];
$dtStart = new DateTime($week_start);
for($i=0;$i<7;$i++){ $tmp=clone $dtStart; $tmp->modify("+$i day"); $week_days[]=$tmp; }

$week_badge = ($week_start === date('Y-m-d')) ? "BU HAFTA" : "SEÇİLİ 7 GÜN";
?>
<style>
.custom-scrollbar::-webkit-scrollbar { width: 10px; height: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(100,116,139,.25); border-radius: 999px; border: 3px solid rgba(255,255,255,.6); }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>

<div class="min-h-screen bg-slate-50 pb-24">
  <div class="max-w-7xl mx-auto p-4 md:p-6">

    <div class="rounded-[2rem] p-6 md:p-7 bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-900 text-white shadow-2xl shadow-slate-900/10 border border-white/10 mb-6">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
        <div class="min-w-0">
          <h1 class="text-2xl md:text-3xl font-black tracking-tight">
            <?php echo ($role === 'parent') ? 'Randevular (Veli)' : 'Randevularım'; ?>
          </h1>
          <p class="text-white/70 text-xs md:text-sm font-semibold mt-1">
            7 günlük plan • <span class="font-black"><?php echo h(date('d.m.Y', strtotime($week_start)) . ' - ' . date('d.m.Y', strtotime($week_end))); ?></span>
          </p>
          <p class="text-white/60 text-[11px] font-semibold mt-2">
            📌 Değişiklik için <span class="font-black">"Talep Oluştur"</span> Butonuna Tıklayınız.
          </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
          <a href="?start=<?php echo h($prev_week); ?><?php echo ($role==='parent' ? '&student_id='.(int)$selected_student_id : ''); ?>"
             class="flex-1 lg:flex-none px-5 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/10 font-black text-sm text-center">⏪ Önceki 7 Gün</a>
          <a href="?start=<?php echo h(date('Y-m-d')); ?><?php echo ($role==='parent' ? '&student_id='.(int)$selected_student_id : ''); ?>"
             class="flex-1 lg:flex-none px-5 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/10 font-black text-sm text-center">🧭 Bugün</a>
          <a href="?start=<?php echo h($next_week); ?><?php echo ($role==='parent' ? '&student_id='.(int)$selected_student_id : ''); ?>"
             class="flex-1 lg:flex-none px-5 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/10 font-black text-sm text-center">Sonraki 7 Gün ⏩</a>
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-6">
        <div class="bg-white/10 border border-white/10 rounded-2xl p-4">
          <p class="text-white/70 text-[10px] font-bold uppercase tracking-wider">Bu hafta toplam</p>
          <p class="text-xl font-black mt-1"><?php echo (int)$week_stats['total']; ?></p>
        </div>
        <div class="bg-white/10 border border-white/10 rounded-2xl p-4">
          <p class="text-white/70 text-[10px] font-bold uppercase tracking-wider">Bu hafta aktif</p>
          <p class="text-xl font-black mt-1"><?php echo (int)$week_stats['active']; ?></p>
        </div>
        <div class="bg-white/10 border border-white/10 rounded-2xl p-4">
          <p class="text-white/70 text-[10px] font-bold uppercase tracking-wider">Bu hafta iptal</p>
          <p class="text-xl font-black mt-1"><?php echo (int)$week_stats['cancelled']; ?></p>
        </div>
        <div class="bg-white/10 border border-white/10 rounded-2xl p-4">
          <p class="text-white/70 text-[10px] font-bold uppercase tracking-wider">Bu hafta toplam süre</p>
          <p class="text-xl font-black mt-1"><?php echo (int)$week_stats['minutes']; ?> <span class="text-sm font-bold text-white/70">dk</span></p>
        </div>
      </div>

      <?php if ($role === 'parent'): ?>
        <div class="mt-5 bg-white/10 border border-white/10 rounded-2xl p-4">
          <form method="GET" class="flex flex-col sm:flex-row gap-3 items-center">
            <input type="hidden" name="start" value="<?php echo h($week_start); ?>">
            <div class="w-full sm:w-auto text-[11px] font-black text-white/80">Öğrenci seç:</div>
            <select name="student_id" class="w-full sm:w-80 rounded-2xl px-4 py-3 text-sm font-bold text-slate-900 bg-white border border-white/20 outline-none">
              <?php foreach ($children as $ch): ?>
                <option value="<?php echo (int)$ch['id']; ?>" <?php echo ((int)$ch['id']===(int)$selected_student_id) ? 'selected' : ''; ?>>
                  <?php echo h($ch['first_name'].' '.$ch['last_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button class="w-full sm:w-auto px-5 py-3 rounded-2xl bg-indigo-500 hover:bg-indigo-400 text-white font-black">Göster</button>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <?php echo $message; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- SOL: Gün seçici -->
      <div class="space-y-6">
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
          <div class="flex justify-between items-center mb-5">
            <a href="?start=<?php echo h($prev_week); ?><?php echo ($role==='parent' ? '&student_id='.(int)$selected_student_id : ''); ?>"
               class="p-2 bg-slate-50 rounded-xl hover:bg-indigo-50 text-slate-600 transition shadow-sm border border-slate-100">⏪</a>
            <div class="text-center">
              <span class="block text-sm font-black text-slate-800 tracking-tight"><?php echo h(date('d.m.Y', strtotime($week_start)) . ' - ' . date('d.m.Y', strtotime($week_end))); ?></span>
              <span class="text-[10px] text-indigo-600 font-black uppercase bg-indigo-50 px-2 py-0.5 rounded-md mt-1 inline-block border border-indigo-100"><?php echo h($week_badge); ?></span>
            </div>
            <a href="?start=<?php echo h($next_week); ?><?php echo ($role==='parent' ? '&student_id='.(int)$selected_student_id : ''); ?>"
               class="p-2 bg-slate-50 rounded-xl hover:bg-indigo-50 text-slate-600 transition shadow-sm border border-slate-100">⏩</a>
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

        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
          <div class="font-black text-slate-900 mb-2">💡 Mini akış</div>
          <ul class="text-sm text-slate-600 font-semibold space-y-2">
            <li>• “Kendime Not” sadece kendin görürsün.</li>
            <li>• "Mesaj" ile öğretmene direkt mesaj atabilirsin.</li>
            <li>• Bekleyen talebi düzenleyebilir veya iptal edebilirsin.</li>
          </ul>
        </div>
      </div>

      <!-- SAĞ: Gün listesi -->
      <div class="lg:col-span-2 space-y-4">
        <?php foreach($week_days as $dt):
          $curr_date = $dt->format('Y-m-d');
          $day_appointments = $grouped_appointments[$curr_date] ?? [];
        ?>
          <div id="day-<?php echo h($curr_date); ?>" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-slate-200 px-6 py-4 border-b border-slate-300">
              <div class="flex items-center gap-3">
                <span class="text-indigo-700 bg-white w-9 h-9 flex items-center justify-center rounded-2xl shadow-sm text-sm border border-indigo-100 font-black"><?php echo h($dt->format('d')); ?></span>
                <div>
                  <h3 class="font-black text-slate-900 text-sm">
                    <?php echo h($gunlerTR[$dt->format('l')]); ?>
                    <span class="text-slate-700 font-bold text-xs ml-1"><?php echo h(date('d.m.Y', strtotime($curr_date))); ?></span>
                  </h3>
                  <p class="text-[10px] text-slate-700 font-bold">
                    <?php echo (int)($daily_counts_active[$curr_date] ?? 0); ?> aktif • <?php echo (int)($daily_counts_total[$curr_date] ?? 0); ?> toplam
                  </p>
                </div>
              </div>
            </div>

            <div class="divide-y divide-slate-200 bg-slate-100/30">
              <?php if(empty($day_appointments)): ?>
                <div class="p-10 text-center text-slate-500 text-sm font-semibold italic opacity-80">Boş (şimdilik 👀)</div>
              <?php else:
                $rowIndex=0;
                foreach($day_appointments as $app):
                  $rowIndex++;
                  $is_cancelled = (($app['status'] ?? 'active') === 'cancelled');
                  $rowBg = ($rowIndex%2===0) ? 'bg-slate-50' : 'bg-white';
                  $teacherName = trim(($app['t_fn'] ?? '').' '.($app['t_ln'] ?? ''));

                  $aid = (int)$app['id'];
                  $appRequests = $requests_by_app[$aid] ?? [];
                  $latestReq = $appRequests[0] ?? null;

                  // sadece kendi request’i üzerinde edit/cancel göstereceğiz
                  $myPendingReq = null;
                  foreach ($appRequests as $r) {
                      if (($r['status'] ?? '') === 'pending'
                          && (int)$r['requester_user_id'] === $user_id
                          && ($r['requester_role'] ?? '') === $role) {
                          $myPendingReq = $r;
                          break;
                      }
                  }
                  $can_request = (($app['status'] ?? 'active') === 'active');
              ?>
                <div class="p-5 <?php echo $rowBg; ?> hover:bg-indigo-50/40 transition group">
                  <div class="flex flex-col md:flex-row gap-4 md:items-center">

                    <div class="w-full md:w-28">
                      <div class="<?php echo $is_cancelled ? 'bg-slate-200 text-slate-600 border-slate-300' : 'bg-indigo-100 text-indigo-900 border-indigo-200'; ?> border rounded-2xl py-3 px-3 shadow-sm flex items-center justify-between md:flex-col md:items-center md:gap-1">
                        <span class="text-lg font-black"><?php echo h(date('H:i', strtotime($app['appointment_time']))); ?></span>
                        <span class="text-[10px] font-black opacity-80"><?php echo (int)$app['duration']; ?> dk</span>
                      </div>
                    </div>

                    <div class="flex-grow min-w-0">
                      <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                        <div class="min-w-0">
                          <p class="font-black text-slate-900 text-base truncate <?php echo $is_cancelled?'line-through text-red-500/70':''; ?>">
                            <?php echo h($teacherName ?: 'Öğretmen'); ?>
                          </p>

                          <div class="flex items-center gap-2 mt-1 flex-wrap">
                            <?php if($is_cancelled): ?>
                              <span class="text-[10px] font-black text-red-600 bg-red-50 border border-red-100 px-2 py-1 rounded-xl">İPTAL</span>
                            <?php else: ?>
                              <span class="text-[10px] font-black text-green-700 bg-green-50 border border-green-100 px-2 py-1 rounded-xl">AKTİF</span>
                            <?php endif; ?>

                            <?php if($latestReq): ?>
                              <?php
                                $rqType = $latestReq['type'] ?? '';
                                $rqStatus = $latestReq['status'] ?? 'pending';
                                $badgeBase = "text-[10px] font-black px-2 py-1 rounded-xl border";
                                $badgeColor = "bg-amber-50 text-amber-800 border-amber-200";
                                if ($rqStatus === 'approved') $badgeColor = "bg-green-50 text-green-700 border-green-100";
                                if ($rqStatus === 'rejected') $badgeColor = "bg-red-50 text-red-700 border-red-100";
                              ?>
                              <span class="<?php echo $badgeBase.' '.$badgeColor; ?>">
                                Talep: <?php echo ($rqType==='cancel' ? 'İptal' : 'Değişiklik'); ?> (<?php echo h($rqStatus==='pending'?'Bekliyor':($rqStatus==='approved'?'Onaylandı':'Reddedildi')); ?>)
                              </span>
                            <?php endif; ?>
                          </div>
                        </div>

                        <!-- ✅ BUTONLAR ARTIK HER ZAMAN GÖRÜNÜR -->
                        <div class="w-full md:w-auto flex flex-wrap justify-end gap-2 opacity-100">
                          <button type="button"
  data-note-btn="1"
  data-app-id="<?php echo (int)$aid; ?>"
  data-note="<?php echo h($app['student_note'] ?? ''); ?>"
  class="text-xs font-black text-indigo-700 hover:text-indigo-900 bg-white border border-slate-200 hover:border-indigo-200 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap">
  📝 Kendime Not
</button>


  <!-- 💬 MESAJLAR -->
  <button type="button"
    class="text-xs font-black text-indigo-700 hover:text-indigo-900 bg-white border border-slate-200 hover:border-indigo-200 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap"
    data-msg-open="<?php echo (int)$aid; ?>">
    💬 Mesajlar
  </button>



                          <?php if ($myPendingReq): ?>
                            <button type="button"
                              onclick='openEditRequestModal(<?php echo json_encode($myPendingReq, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                              class="text-xs font-black text-blue-700 hover:text-blue-900 bg-white border border-slate-200 hover:border-blue-200 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap">
                              ✏️ Talebi Düzenle
                            </button>

                            <form method="POST" action="<?php echo h($self_url); ?>" onsubmit="return confirm('Bekleyen talebi iptal etmek istiyor musun?');">
                              <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                              <input type="hidden" name="cancel_request" value="1">
                              <input type="hidden" name="request_id" value="<?php echo (int)$myPendingReq['id']; ?>">
                              <button type="submit"
                                class="text-xs font-black text-red-600 hover:text-red-800 bg-white border border-slate-200 hover:border-red-200 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap">
                                🧹 Talebi İptal Et
                              </button>
                            </form>
                          <?php else: ?>
                            <button type="button" onclick="openRequestModal(<?php echo (int)$aid; ?>)"
                              class="text-xs font-black text-slate-700 hover:text-slate-900 bg-white border border-slate-200 hover:border-slate-300 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap <?php echo $can_request ? '' : 'opacity-50 cursor-not-allowed'; ?>"
                              <?php echo $can_request ? '' : 'disabled'; ?>>
                              📩 Talep Oluştur
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>

                      <div class="mt-3 space-y-2">
                        
                        <?php if(!empty($app['student_note'])): ?>
                          <div class="bg-amber-50 border border-amber-200 rounded-2xl p-3 text-amber-900">
                            <p class="text-[11px] font-black text-amber-700 uppercase tracking-wider mb-1">📝 Kendime Not</p>
                            <div class="text-sm font-semibold break-words"><?php echo h($app['student_note']); ?></div>
                          </div>
                        <?php endif; ?>

                        <?php if($latestReq && !empty($latestReq['teacher_response'])): ?>
                          <div class="bg-white border border-slate-200 rounded-2xl p-3 text-slate-800">
                            <p class="text-[11px] font-black text-slate-500 uppercase tracking-wider mb-1">💬 Öğretmen Yanıtı</p>
                            <div class="text-sm font-semibold break-words"><?php echo h($latestReq['teacher_response']); ?></div>
                          </div>
                        <?php endif; ?>
                      </div>

                    </div>
                  </div>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- NOTE MODAL -->
<div id="noteModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden">
    <div class="bg-slate-900 p-5 flex justify-between items-center text-white">
      <div>
        <h3 class="font-black text-lg">Kendime Not</h3>
        <p class="text-xs text-white/60 font-semibold">Bu not sadece sana görünür.</p>
      </div>
      <button type="button" onclick="closeModal('noteModal')" class="bg-white/15 hover:bg-white/25 rounded-full p-2 transition">✕</button>
    </div>

    <form method="POST" action="<?php echo h($self_url); ?>" class="p-5 space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="save_student_note" value="1">
      <input type="hidden" name="appointment_id" id="note_appointment_id" value="">

      <textarea name="student_note" id="note_text" rows="5"
        class="js-upper w-full rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold outline-none focus:border-indigo-500"
        placeholder="Notunu yaz..."></textarea>

      <div class="flex gap-2">
        <button type="button" onclick="closeModal('noteModal')"
          class="flex-1 px-4 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-black">Vazgeç</button>
        <button type="submit"
          class="flex-1 px-4 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-black">Kaydet</button>
      </div>
    </form>
  </div>
</div>

<!-- REQUEST MODAL (Yeni Talep) -->
<div id="requestModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden">
    <div class="bg-slate-900 p-5 flex justify-between items-center text-white">
      <div>
        <h3 class="font-black text-lg">Talep Oluştur</h3>
        <p class="text-xs text-white/60 font-semibold">Bu talep mevcut randevu için iptal/değişiklik isteğidir.</p>
      </div>
      <button type="button" onclick="closeModal('requestModal')" class="bg-white/15 hover:bg-white/25 rounded-full p-2 transition">✕</button>
    </div>

    <form method="POST" action="<?php echo h($self_url); ?>" class="p-5 space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="send_request" value="1">
      <input type="hidden" name="appointment_id" id="req_appointment_id" value="">
      <input type="hidden" name="type" id="req_type" value="">

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button type="button" onclick="selectReqType('cancel')"
          id="btnCancel"
          class="px-4 py-3 rounded-2xl border border-red-200 bg-red-50 text-red-700 font-black">🚫 İptal Talebi</button>

        <button type="button" onclick="selectReqType('reschedule')"
          id="btnReschedule"
          class="px-4 py-3 rounded-2xl border border-indigo-200 bg-indigo-50 text-indigo-700 font-black">🔁 Değişiklik Talebi</button>
      </div>

      <div id="rescheduleFields" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-xs font-black text-slate-600">Önerilen Tarih</label>
          <input type="date" name="proposed_date" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
        <div>
          <label class="text-xs font-black text-slate-600">Önerilen Saat</label>
          <input type="time" name="proposed_time" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
        <div>
          <label class="text-xs font-black text-slate-600">Süre (dk)</label>
          <input type="number" min="5" step="5" name="proposed_duration" placeholder="30"
                 class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
      </div>

      <div>
        <label class="text-xs font-black text-slate-600">Mesaj (opsiyonel ama önerilir)</label>
        <textarea name="message" rows="4"
          class="mt-1 w-full rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold outline-none focus:border-indigo-500"
          placeholder="Kısaca sebep / öneri yaz..."></textarea>
      </div>

      <div class="flex gap-2">
        <button type="button" onclick="closeModal('requestModal')"
          class="flex-1 px-4 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-black">Vazgeç</button>
        <button type="submit"
          class="flex-1 px-4 py-3 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-black">Gönder</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT REQUEST MODAL (Pending düzenleme) -->
<div id="editRequestModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden">
    <div class="bg-slate-900 p-5 flex justify-between items-center text-white">
      <div>
        <h3 class="font-black text-lg">Talebi Düzenle</h3>
        <p class="text-xs text-white/60 font-semibold">Bekleyen talebini güncelleyebilirsin.</p>
      </div>
      <button type="button" onclick="closeModal('editRequestModal')" class="bg-white/15 hover:bg-white/25 rounded-full p-2 transition">✕</button>
    </div>

    <form method="POST" action="<?php echo h($self_url); ?>" class="p-5 space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="update_request" value="1">
      <input type="hidden" name="request_id" id="edit_req_id" value="">
      <input type="hidden" name="type" id="edit_req_type" value="">

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button type="button" onclick="selectEditReqType('cancel')"
          id="editBtnCancel"
          class="px-4 py-3 rounded-2xl border border-red-200 bg-red-50 text-red-700 font-black">🚫 İptal Talebi</button>

        <button type="button" onclick="selectEditReqType('reschedule')"
          id="editBtnReschedule"
          class="px-4 py-3 rounded-2xl border border-indigo-200 bg-indigo-50 text-indigo-700 font-black">🔁 Değişiklik Talebi</button>
      </div>

      <div id="editRescheduleFields" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-xs font-black text-slate-600">Önerilen Tarih</label>
          <input type="date" name="proposed_date" id="edit_pdate" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
        <div>
          <label class="text-xs font-black text-slate-600">Önerilen Saat</label>
          <input type="time" name="proposed_time" id="edit_ptime" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
        <div>
          <label class="text-xs font-black text-slate-600">Süre (dk)</label>
          <input type="number" min="5" step="5" name="proposed_duration" id="edit_pdur"
                 class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
      </div>

      <div>
        <label class="text-xs font-black text-slate-600">Mesaj</label>
        <textarea name="message" id="edit_msg" rows="4"
          class="mt-1 w-full rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold outline-none focus:border-indigo-500"></textarea>
      </div>

      <div class="flex gap-2">
        <button type="button" onclick="closeModal('editRequestModal')"
          class="flex-1 px-4 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-black">Vazgeç</button>
        <button type="submit"
          class="flex-1 px-4 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">Güncelle</button>
      </div>
    </form>
  </div>
</div>


<!-- MESSAGE MODAL -->
<div id="msgModal" class="fixed inset-0 z-[200] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-xl shadow-2xl overflow-hidden">
    <div class="bg-slate-900 p-5 flex justify-between items-center text-white">
      <div class="min-w-0">
        <h3 class="font-black text-lg truncate" id="msgTitle">Mesajlar</h3>
        <p class="text-xs text-white/60 font-semibold" id="msgSub">Randevu sohbeti</p>
      </div>
      <button type="button" onclick="closeMsgModal()" class="bg-white/20 hover:bg-white/30 rounded-full p-2 transition">✕</button>
    </div>

    <div id="msgBody" class="p-5 max-h-[55vh] overflow-y-auto space-y-3 bg-slate-50"></div>

    <div class="p-5 border-t border-slate-200 bg-white">
      <div class="flex gap-2">
        <textarea id="msgText" rows="2"
          class="flex-1 rounded-2xl border border-slate-200 p-3 text-sm font-semibold outline-none focus:border-indigo-500"
          placeholder="Mesaj yaz..."></textarea>

        <button type="button" onclick="sendMsg()"
          class="px-5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-black">
          Gönder
        </button>
      </div>
    </div>
  </div>
</div>


<script>
function scrollToDay(dateStr){
  const el = document.getElementById('day-' + dateStr);
  if(!el) return;
  el.scrollIntoView({behavior:'smooth', block:'start'});
  el.classList.add('ring-4','ring-indigo-100');
  setTimeout(()=>el.classList.remove('ring-4','ring-indigo-100'), 1200);
}

function showModal(id){
  const m = document.getElementById(id);
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function closeModal(id){
  const m = document.getElementById(id);
  m.classList.add('hidden');
  m.classList.remove('flex');
}

function openNoteModal(appId, note){
  document.getElementById('note_appointment_id').value = appId;
  document.getElementById('note_text').value = note || '';
  showModal('noteModal');
}

function openRequestModal(appId){
  document.getElementById('req_appointment_id').value = appId;
  document.getElementById('req_type').value = '';
  document.getElementById('rescheduleFields').classList.add('hidden');
  document.getElementById('btnCancel').classList.remove('ring-2','ring-red-400');
  document.getElementById('btnReschedule').classList.remove('ring-2','ring-indigo-400');
  showModal('requestModal');
}

function selectReqType(type){
  document.getElementById('req_type').value = type;

  const btnCancel = document.getElementById('btnCancel');
  const btnRes = document.getElementById('btnReschedule');
  btnCancel.classList.remove('ring-2','ring-red-400');
  btnRes.classList.remove('ring-2','ring-indigo-400');

  if(type==='cancel'){
    btnCancel.classList.add('ring-2','ring-red-400');
    document.getElementById('rescheduleFields').classList.add('hidden');
  } else {
    btnRes.classList.add('ring-2','ring-indigo-400');
    document.getElementById('rescheduleFields').classList.remove('hidden');
  }
}

/** EDIT MODAL */
function openEditRequestModal(req){
  document.getElementById('edit_req_id').value = req.id || '';
  document.getElementById('edit_msg').value = req.message || '';
  document.getElementById('edit_pdate').value = req.proposed_date || '';
  document.getElementById('edit_ptime').value = (req.proposed_time || '').substring(0,5);
  document.getElementById('edit_pdur').value = req.proposed_duration || '';

  selectEditReqType(req.type || 'cancel');
  showModal('editRequestModal');
}

function selectEditReqType(type){
  document.getElementById('edit_req_type').value = type;

  const btnCancel = document.getElementById('editBtnCancel');
  const btnRes = document.getElementById('editBtnReschedule');

  btnCancel.classList.remove('ring-2','ring-red-400');
  btnRes.classList.remove('ring-2','ring-indigo-400');

  if(type==='cancel'){
    btnCancel.classList.add('ring-2','ring-red-400');
    document.getElementById('editRescheduleFields').classList.add('hidden');
  } else {
    btnRes.classList.add('ring-2','ring-indigo-400');
    document.getElementById('editRescheduleFields').classList.remove('hidden');
  }
}

['noteModal','requestModal','editRequestModal'].forEach(id=>{
  const el = document.getElementById(id);
  if(!el) return;
  el.addEventListener('click', (e)=>{ if(e.target === el) closeModal(id); });
});

// Kendime Not butonları (inline onclick yerine güvenli yakalama)
document.addEventListener('click', function(e){
  const btn = e.target.closest('[data-note-btn]');
  if(!btn) return;

  const appId = parseInt(btn.dataset.appId || '0', 10);
  const note = btn.dataset.note || '';

  if(!appId) {
    alert('Randevu ID alınamadı. (data-app-id boş)');
    return;
  }

  openNoteModal(appId, note);
});


</script>

<script>
const MSG_API = "/appointment_messages_api.php";
const CSRF_TOKEN = <?php echo json_encode($csrf); ?>;
const MY_ROLE = <?php echo json_encode($_SESSION['role'] ?? ''); ?>;

const MSG_BTN_DEFAULT_CLASS =
  "text-xs font-black text-indigo-700 hover:text-indigo-900 bg-white border border-slate-200 hover:border-indigo-200 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap";

const MSG_BTN_UNREAD_CLASS =
  "text-xs font-black text-amber-800 bg-amber-50 border border-amber-200 hover:border-amber-300 px-4 py-2 rounded-2xl transition shadow-sm whitespace-nowrap";

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
  return (s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

async function loadSummary(){
  const ids = [...document.querySelectorAll('[data-msg-open]')]
    .map(b => parseInt(b.getAttribute('data-msg-open'),10))
    .filter(Boolean);

  if(!ids.length) return;

  const res = await fetch(MSG_API + "?action=summary&ids=" + encodeURIComponent(ids.join(',')), {credentials:'same-origin'});
  const js = await res.json();
  if(!js.ok) return;

  ids.forEach(id=>{
    const st = js.summary?.[id] || {total:0,unread:0};
    const btn = document.querySelector(`[data-msg-open="${id}"]`);
    if(!btn) return;

    if(st.unread > 0){
      btn.className = MSG_BTN_UNREAD_CLASS;
      btn.textContent = `💬 Mesajlar (${st.unread})`;
    } else {
      btn.className = MSG_BTN_DEFAULT_CLASS;
      btn.textContent = `💬 Mesajlar`;
    }
  });
}

async function openMsg(appId){
  CURRENT_APP_ID = appId;
  document.getElementById('msgBody').innerHTML = '<div class="text-sm text-slate-500 font-semibold">Yükleniyor…</div>';
  showMsgModal();

  const res = await fetch(MSG_API + "?action=list&appointment_id=" + appId, {credentials:'same-origin'});
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
      return `
        <div class="max-w-[85%] rounded-2xl p-3 ${box}">
          <div class="text-[10px] font-black opacity-80 mb-1">${who} • ${esc(m.created_at)}</div>
          <div class="text-sm font-semibold break-words">${esc(m.message)}</div>
        </div>
      `;
    }).join('');
    const body = document.getElementById('msgBody');
    body.scrollTop = body.scrollHeight;
  }

  // liste açılınca "okundu" sayılır → buton rengi/sayacı güncelle
  loadSummary();
}

async function sendMsg(){
  const txt = document.getElementById('msgText').value.trim();
  if(!CURRENT_APP_ID || !txt) return;

  const body = new URLSearchParams();
  body.set('action','send');
  body.set('csrf_token', CSRF_TOKEN);
  body.set('appointment_id', CURRENT_APP_ID);
  body.set('message', txt);

  const res = await fetch(MSG_API, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: body.toString(),
    credentials:'same-origin'
  });
  const js = await res.json();
  if(!js.ok){ alert("Mesaj gönderilemedi: " + (js.error||'unknown')); return; }

  document.getElementById('msgText').value = '';
  await openMsg(CURRENT_APP_ID);
}

document.addEventListener('click', (e)=>{
  const openBtn = e.target.closest('[data-msg-open]');
  if(openBtn){
    openMsg(parseInt(openBtn.getAttribute('data-msg-open'),10));
    return;
  }
});

window.addEventListener('DOMContentLoaded', loadSummary);

document.getElementById('msgModal')?.addEventListener('click', (e)=>{
  if(e.target && e.target.id === 'msgModal') closeMsgModal();
});
</script>



<?php include __DIR__ . '/../footer.php'; ?>
