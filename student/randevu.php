<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../db.php';

$headerPath = __DIR__ . '/../header.php';
$footerPath = __DIR__ . '/../footer.php';

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
    include $headerPath;
    echo "<div class='max-w-3xl mx-auto p-6'>
            <div class='bg-red-50 text-red-700 border border-red-100 p-5 rounded-3xl font-bold'>
              ⛔ Bu sayfaya erişim yetkiniz yok.
            </div>
          </div>";
    include $footerPath;
    exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

$flash = null; // PRG/toast: ['t'=>tip,'m'=>metin]

// URL’yi (query ile) koru: form action’ı ve PRG hedefini şaşırtmasın
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
        include $headerPath;
        echo "<div class='max-w-3xl mx-auto p-6'>
                <div class='bg-amber-50 text-amber-800 border border-amber-200 p-5 rounded-3xl font-bold'>
                  ⚠️ Bu veli hesabına bağlı öğrenci bulunamadı.
                </div>
              </div>";
        include $footerPath;
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
        $flash = ['t'=>'error','m'=>'⚠️ Güvenlik doğrulaması başarısız (CSRF).'];
    } else {

        // (1) Kendime Not
        if (isset($_POST['save_student_note'])) {
            $app_id = (int)($_POST['appointment_id'] ?? 0);
            $note = trim((string)($_POST['student_note'] ?? ''));

            if ($app_id <= 0) {
                $flash = ['t'=>'error','m'=>'⚠️ Randevu ID boş geldi. (Modal açılırken ID set edilmemiş olabilir.)'];
            } else {
                $app = ensureAppointmentOwner($pdo, $app_id, $selected_student_id);
                if (!$app) {
                    $flash = ['t'=>'error','m'=>'⚠️ Randevu bulunamadı.'];
                } elseif (!empty($app['_forbidden'])) {
                    $flash = ['t'=>'error','m'=>'⛔ Bu randevu için yetkin yok.'];
                } else {
                    try {
                        $up = $pdo->prepare("UPDATE appointments SET student_note=? WHERE id=?");
                        $up->execute([$note, $app_id]);
                        $flash = ['t'=>'success','m'=>'✅ Kendime Not kaydedildi.'];
                    } catch (Throwable $e) {
                        $flash = ['t'=>'error','m'=>'⚠️ Not kaydedilemedi: '.$e->getMessage()];
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
                $flash = ['t'=>'error','m'=>'⚠️ Randevu ID boş geldi. (Talep modalında appointment_id set edilmemiş olabilir.)'];
            } elseif ($type !== 'cancel' && $type !== 'reschedule') {
                $flash = ['t'=>'error','m'=>'⚠️ Talep tipi geçersiz.'];
            } else {
                $app = ensureAppointmentOwner($pdo, $app_id, $selected_student_id);
                if (!$app) {
                    $flash = ['t'=>'error','m'=>'⚠️ Randevu bulunamadı.'];
                } elseif (!empty($app['_forbidden'])) {
                    $flash = ['t'=>'error','m'=>'⛔ Bu randevu için yetkin yok.'];
                } else {
                    if (($app['status'] ?? 'active') !== 'active') {
                        $flash = ['t'=>'warning','m'=>'⚠️ Bu randevu aktif değil, talep gönderemezsin.'];
                    } else {
                        if ($type === 'reschedule') {
                            $hasAny = (!empty($pdate) || !empty($ptime) || !empty($pdur));
                            if (!$hasAny) {
                                $flash = ['t'=>'error','m'=>'⚠️ Değişiklik talebi için en az bir öneri (tarih/saat/süre) gir.'];
                            }
                        }
                        if ($flash === null) {
                            $chk = $pdo->prepare("
                                SELECT COUNT(*)
                                FROM appointment_requests
                                WHERE appointment_id=? AND requester_user_id=? AND requester_role=? AND status='pending'
                            ");
                            $chk->execute([$app_id, $user_id, $role]);
                            if ((int)$chk->fetchColumn() > 0) {
                                $flash = ['t'=>'warning','m'=>'⏳ Bu randevu için zaten bekleyen bir talebin var.'];
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
                                    $flash = ['t'=>'success','m'=>'✅ Talebin gönderildi. (Bekliyor)'];
                                } catch (Throwable $e) {
                                    $flash = ['t'=>'error','m'=>'⚠️ Talep gönderilemedi: '.$e->getMessage()];
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
                $flash = ['t'=>'error','m'=>'⚠️ Talep bulunamadı.'];
            } else {
                $app = ensureAppointmentOwner($pdo, (int)$req['appointment_id'], $selected_student_id);
                $isMine = ((int)$req['requester_user_id'] === $user_id) && (($req['requester_role'] ?? '') === $role);

                if (!$app || !empty($app['_forbidden']) || !$isMine) {
                    $flash = ['t'=>'error','m'=>'⛔ Bu talep için yetkin yok.'];
                } elseif (($req['status'] ?? '') !== 'pending') {
                    $flash = ['t'=>'warning','m'=>'⚠️ Bu talep artık beklemede değil, iptal edemezsin.'];
                } else {
                    try {
                        $del = $pdo->prepare("DELETE FROM appointment_requests WHERE id=?");
                        $del->execute([$req_id]);
                        $flash = ['t'=>'info','m'=>'🧹 Talep iptal edildi (kaldırıldı).'];
                    } catch (Throwable $e) {
                        $flash = ['t'=>'error','m'=>'⚠️ Talep iptal edilemedi: '.$e->getMessage()];
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
                $flash = ['t'=>'error','m'=>'⚠️ Talep tipi geçersiz.'];
            } else {
                $st = $pdo->prepare("SELECT * FROM appointment_requests WHERE id=? LIMIT 1");
                $st->execute([$req_id]);
                $req = $st->fetch(PDO::FETCH_ASSOC);

                if (!$req) {
                    $flash = ['t'=>'error','m'=>'⚠️ Talep bulunamadı.'];
                } else {
                    $app = ensureAppointmentOwner($pdo, (int)$req['appointment_id'], $selected_student_id);
                    $isMine = ((int)$req['requester_user_id'] === $user_id) && (($req['requester_role'] ?? '') === $role);

                    if (!$app || !empty($app['_forbidden']) || !$isMine) {
                        $flash = ['t'=>'error','m'=>'⛔ Bu talep için yetkin yok.'];
                    } elseif (($req['status'] ?? '') !== 'pending') {
                        $flash = ['t'=>'warning','m'=>'⚠️ Bu talep artık beklemede değil, düzenleyemezsin.'];
                    } else {
                        if ($type === 'reschedule') {
                            $hasAny = (!empty($pdate) || !empty($ptime) || !empty($pdur));
                            if (!$hasAny) {
                                $flash = ['t'=>'error','m'=>'⚠️ Değişiklik talebi için en az bir öneri (tarih/saat/süre) gir.'];
                            }
                        }
                        if ($flash === null) {
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
                                $flash = ['t'=>'info','m'=>'✏️ Talep güncellendi (hala beklemede).'];
                            } catch (Throwable $e) {
                                $flash = ['t'=>'error','m'=>'⚠️ Talep güncellenemedi: '.$e->getMessage()];
                            }
                        }
                    }
                }
            }
        }
    }

    /* --- POST/REDIRECT/GET: çift gönderimi (F5) önler, sonucu toast olarak taşır --- */
    if ($flash !== null) { $_SESSION['flash'] = $flash; }
    $redirect_to = $self_url ?: (($_SERVER['PHP_SELF'] ?? 'randevu.php'));
    header('Location: ' . $redirect_to);
    exit;
}

/* --- GET: bir önceki işlemin flash mesajını al (PRG) --- */
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

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
$studentParam = ($role === 'parent') ? '&student_id='.(int)$selected_student_id : '';

/* HTML çıktısı buradan başlıyor — header artık PRG için POST işlemeden SONRA dahil ediliyor */
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
.chip-req-wait{background:var(--warning-050);color:var(--warning);border-color:#fde68a}
.chip-req-approved{background:var(--success-050);color:var(--success);border-color:#a7f3d0}
.chip-req-rejected{background:var(--error-050);color:var(--error);border-color:#fecaca}
.pulse-dot{width:7px;height:7px;border-radius:999px;background:var(--success);animation:pulse 1.6s infinite}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(5,150,105,.5)}70%{box-shadow:0 0 0 7px rgba(5,150,105,0)}100%{box-shadow:0 0 0 0 rgba(5,150,105,0)}}
/* Kart aksiyon butonu */
.act{display:inline-flex;align-items:center;gap:.3rem;min-height:44px;padding:0 .9rem;border-radius:12px;font-weight:700;font-size:.75rem;background:#fff;border:1px solid #e2e8f0;color:#334155;transition:all .15s;white-space:nowrap;cursor:pointer}
.act:hover{background:var(--surface,#f8fafc);border-color:#cbd5e1}
.act:focus-visible{outline:3px solid var(--atla-primary-050);outline-offset:1px}
.act[disabled]{opacity:.5;cursor:not-allowed}
.act-edit:hover{border-color:#c7d2fe;color:var(--atla-primary)}
.act-cancel:hover{border-color:#fecaca;color:var(--error)}
</style>

<div id="toastWrap" aria-live="polite" aria-atomic="true"></div>

<div class="min-h-screen bg-slate-50 font-['Poppins'] pb-24">
  <div class="max-w-7xl mx-auto p-4 md:p-6">

    <!-- ════ KATMAN 1: KOMUTA ŞERİDİ (ATLA lacivert) ════ -->
    <div class="rounded-3xl p-5 md:p-6 mb-5 text-white shadow-lg" style="background:linear-gradient(135deg,var(--atla-primary),var(--atla-primary-600))">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="min-w-0">
          <h1 class="text-xl md:text-2xl font-extrabold tracking-tight">
            <?php echo ($role === 'parent') ? 'Randevular (Veli)' : 'Randevularım'; ?>
          </h1>
          <p class="text-white/70 text-xs md:text-sm font-semibold mt-0.5">7 günlük plan · <?php echo h(date('d.m.Y', strtotime($week_start)) . ' – ' . date('d.m.Y', strtotime($week_end))); ?></p>
          <p class="text-white/60 text-[11px] font-semibold mt-2">📌 Değişiklik için <span class="font-black">"Talep Oluştur"</span> butonuna tıklayınız.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <a href="?start=<?php echo h($prev_week).$studentParam; ?>" aria-label="Önceki hafta"
             class="w-11 h-11 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 border border-white/10 transition text-lg font-black">‹</a>
          <div class="text-center px-3 min-w-[7rem]">
            <span class="block text-[11px] font-extrabold uppercase tracking-wide"><?php echo h($week_badge); ?></span>
            <span class="block text-[11px] text-white/70 font-semibold"><?php echo h(date('d M', strtotime($week_start))); ?> – <?php echo h(date('d M', strtotime($week_end))); ?></span>
          </div>
          <a href="?start=<?php echo h($next_week).$studentParam; ?>" aria-label="Sonraki hafta"
             class="w-11 h-11 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 border border-white/10 transition text-lg font-black">›</a>
          <a href="?start=<?php echo h(date('Y-m-d')).$studentParam; ?>"
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

    <?php if ($role === 'parent'): ?>
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="GET" class="flex flex-col sm:flex-row gap-3 items-center">
          <input type="hidden" name="start" value="<?php echo h($week_start); ?>">
          <div class="w-full sm:w-auto text-[11px] font-black text-slate-500 uppercase">Öğrenci seç:</div>
          <select name="student_id" class="w-full sm:w-80 rounded-xl px-4 py-3 text-sm font-bold text-slate-900 bg-slate-50 border border-slate-200 outline-none focus:border-[color:var(--atla-primary)]">
            <?php foreach ($children as $ch): ?>
              <option value="<?php echo (int)$ch['id']; ?>" <?php echo ((int)$ch['id']===(int)$selected_student_id) ? 'selected' : ''; ?>>
                <?php echo h($ch['first_name'].' '.$ch['last_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="w-full sm:w-auto px-5 py-3 rounded-xl text-white font-black transition" style="background:var(--atla-primary)" onmouseover="this.style.background='var(--atla-primary-600)'" onmouseout="this.style.background='var(--atla-primary)'">Göster</button>
        </form>
      </div>
    <?php endif; ?>

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

      <!-- SOL: bilgi kartı -->
      <div class="space-y-6">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
          <h3 class="font-extrabold text-slate-800 mb-4 flex items-center gap-2">💡 Mini Akış</h3>
          <ul class="text-sm text-slate-600 font-semibold space-y-2">
            <li>• "Kendime Not" sadece kendin görürsün.</li>
            <li>• "Mesajlar" ile öğretmene direkt mesaj atabilirsin.</li>
            <li>• Bekleyen talebi düzenleyebilir veya iptal edebilirsin.</li>
          </ul>
        </div>
      </div>

      <!-- SAĞ: Gün listesi -->
      <div class="lg:col-span-2 space-y-4">
        <?php
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
          </div>

          <div class="p-3 md:p-4 space-y-3 bg-slate-50/40">
            <?php if (empty($day_appointments)): ?>
              <div class="w-full py-5 rounded-xl border-2 border-dashed border-slate-200 text-sm font-bold text-slate-400 text-center">
                Bu gün için randevu yok 👀
              </div>
            <?php else: ?>
              <?php
                foreach($day_appointments as $app):
                  $vs         = appt_view_status($app);
                  $meta       = $statusMeta[$vs];
                  $is_cancelled = ($vs === 'cancel');
                  $teacherName = trim(($app['t_fn'] ?? '').' '.($app['t_ln'] ?? ''));
                  $aid        = (int)$app['id'];
                  $duration   = (int)($app['duration'] ?? 0);
                  $timeStr    = date('H:i', strtotime($app['appointment_time']));

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
                        <span class="font-extrabold text-slate-900 truncate <?php echo $is_cancelled?'line-through text-slate-400':''; ?>"><?php echo h($teacherName ?: 'Öğretmen'); ?></span>
                        <span class="chip <?php echo $meta['chip']; ?>"><?php if($vs==='live'): ?><span class="pulse-dot"></span><?php endif; ?><?php echo h($meta['t']); ?></span>
                        <?php if($latestReq):
                          $rqType = $latestReq['type'] ?? '';
                          $rqStatus = $latestReq['status'] ?? 'pending';
                          $rqChip = $rqStatus==='approved' ? 'chip-req-approved' : ($rqStatus==='rejected' ? 'chip-req-rejected' : 'chip-req-wait');
                        ?>
                          <span class="chip <?php echo $rqChip; ?>">
                            Talep: <?php echo ($rqType==='cancel' ? 'İptal' : 'Değişiklik'); ?> (<?php echo h($rqStatus==='pending'?'Bekliyor':($rqStatus==='approved'?'Onaylandı':'Reddedildi')); ?>)
                          </span>
                        <?php endif; ?>
                      </div>

                      <?php if(!empty($app['student_note'])): ?>
                        <div class="mt-2 bg-amber-50 border border-amber-200 rounded-xl p-3 text-amber-900">
                          <p class="text-[10px] font-black text-amber-700 uppercase tracking-wider mb-1">📝 Kendime Not</p>
                          <div class="text-xs font-semibold break-words"><?php echo h($app['student_note']); ?></div>
                        </div>
                      <?php endif; ?>

                      <?php if($latestReq && !empty($latestReq['teacher_response'])): ?>
                        <div class="mt-2 bg-white border border-slate-200 rounded-xl p-3 text-slate-800">
                          <p class="text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">💬 Öğretmen Yanıtı</p>
                          <div class="text-xs font-semibold break-words"><?php echo h($latestReq['teacher_response']); ?></div>
                        </div>
                      <?php endif; ?>
                    </div>

                    <!-- Aksiyonlar -->
                    <div class="flex gap-2 flex-wrap sm:justify-end shrink-0">
                      <button type="button" class="act"
                        data-note-btn="1" data-app-id="<?php echo $aid; ?>" data-note="<?php echo h($app['student_note'] ?? ''); ?>">📝 Kendime Not</button>
                      <button type="button" class="act" data-msg-open="<?php echo $aid; ?>">💬 Mesajlar</button>

                      <?php if ($myPendingReq): ?>
                        <button type="button" onclick='openEditRequestModal(<?php echo json_encode($myPendingReq, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                          class="act act-edit">✏️ Talebi Düzenle</button>
                        <form method="POST" action="<?php echo h($self_url); ?>" onsubmit="return confirm('Bekleyen talebi iptal etmek istiyor musun?');" class="inline">
                          <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                          <input type="hidden" name="cancel_request" value="1">
                          <input type="hidden" name="request_id" value="<?php echo (int)$myPendingReq['id']; ?>">
                          <button type="submit" class="act act-cancel">🧹 Talebi İptal Et</button>
                        </form>
                      <?php else: ?>
                        <button type="button" onclick="openRequestModal(<?php echo $aid; ?>)" class="act act-edit" <?php echo $can_request ? '' : 'disabled'; ?>>📩 Talep Oluştur</button>
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

<!-- NOTE MODAL -->
<div id="noteModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden">
    <div class="p-5 flex justify-between items-center text-white" style="background:var(--atla-primary)">
      <div>
        <h3 class="font-extrabold text-lg">Kendime Not</h3>
        <p class="text-xs text-white/70 font-semibold">Bu not sadece sana görünür.</p>
      </div>
      <button type="button" onclick="closeModal('noteModal')" aria-label="Kapat" class="bg-white/20 hover:bg-white/30 rounded-full w-9 h-9 flex items-center justify-center transition">✕</button>
    </div>

    <form method="POST" action="<?php echo h($self_url); ?>" class="p-6 space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="save_student_note" value="1">
      <input type="hidden" name="appointment_id" id="note_appointment_id" value="">

      <textarea name="student_note" id="note_text" rows="5"
        class="js-upper w-full rounded-xl border border-slate-200 bg-white p-4 text-sm font-semibold outline-none focus:border-[color:var(--atla-primary)]"
        placeholder="Notunu yaz..."></textarea>

      <div class="flex gap-3">
        <button type="button" onclick="closeModal('noteModal')" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-xl font-bold text-sm hover:bg-slate-200 transition">Vazgeç</button>
        <button type="submit" class="flex-1 text-white py-3 rounded-xl font-bold text-sm shadow-lg transition" style="background:var(--atla-primary)" onmouseover="this.style.background='var(--atla-primary-600)'" onmouseout="this.style.background='var(--atla-primary)'">Kaydet</button>
      </div>
    </form>
  </div>
</div>

<!-- REQUEST MODAL (Yeni Talep) -->
<div id="requestModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden">
    <div class="p-5 flex justify-between items-center text-white" style="background:var(--atla-primary)">
      <div>
        <h3 class="font-extrabold text-lg">Talep Oluştur</h3>
        <p class="text-xs text-white/70 font-semibold">Bu talep mevcut randevu için iptal/değişiklik isteğidir.</p>
      </div>
      <button type="button" onclick="closeModal('requestModal')" aria-label="Kapat" class="bg-white/20 hover:bg-white/30 rounded-full w-9 h-9 flex items-center justify-center transition">✕</button>
    </div>

    <form method="POST" action="<?php echo h($self_url); ?>" class="p-6 space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="send_request" value="1">
      <input type="hidden" name="appointment_id" id="req_appointment_id" value="">
      <input type="hidden" name="type" id="req_type" value="">

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button type="button" onclick="selectReqType('cancel')" id="btnCancel"
          class="px-4 py-3 rounded-xl border border-red-200 bg-red-50 text-red-700 font-black">🚫 İptal Talebi</button>
        <button type="button" onclick="selectReqType('reschedule')" id="btnReschedule"
          class="px-4 py-3 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 font-black">🔁 Değişiklik Talebi</button>
      </div>

      <div id="rescheduleFields" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-xs font-black text-slate-600">Önerilen Tarih</label>
          <input type="date" name="proposed_date" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
        <div>
          <label class="text-xs font-black text-slate-600">Önerilen Saat</label>
          <input type="time" name="proposed_time" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
        <div>
          <label class="text-xs font-black text-slate-600">Süre (dk)</label>
          <input type="number" min="5" step="5" name="proposed_duration" placeholder="30"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
      </div>

      <div>
        <label class="text-xs font-black text-slate-600">Mesaj (opsiyonel ama önerilir)</label>
        <textarea name="message" rows="4"
          class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-4 text-sm font-semibold outline-none focus:border-[color:var(--atla-primary)]"
          placeholder="Kısaca sebep / öneri yaz..."></textarea>
      </div>

      <div class="flex gap-3">
        <button type="button" onclick="closeModal('requestModal')" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-xl font-bold text-sm hover:bg-slate-200 transition">Vazgeç</button>
        <button type="submit" class="flex-1 text-white py-3 rounded-xl font-bold text-sm shadow-lg transition" style="background:var(--atla-primary)" onmouseover="this.style.background='var(--atla-primary-600)'" onmouseout="this.style.background='var(--atla-primary)'">Gönder</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT REQUEST MODAL (Pending düzenleme) -->
<div id="editRequestModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden">
    <div class="p-5 flex justify-between items-center text-white" style="background:var(--atla-primary)">
      <div>
        <h3 class="font-extrabold text-lg">Talebi Düzenle</h3>
        <p class="text-xs text-white/70 font-semibold">Bekleyen talebini güncelleyebilirsin.</p>
      </div>
      <button type="button" onclick="closeModal('editRequestModal')" aria-label="Kapat" class="bg-white/20 hover:bg-white/30 rounded-full w-9 h-9 flex items-center justify-center transition">✕</button>
    </div>

    <form method="POST" action="<?php echo h($self_url); ?>" class="p-6 space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="update_request" value="1">
      <input type="hidden" name="request_id" id="edit_req_id" value="">
      <input type="hidden" name="type" id="edit_req_type" value="">

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button type="button" onclick="selectEditReqType('cancel')" id="editBtnCancel"
          class="px-4 py-3 rounded-xl border border-red-200 bg-red-50 text-red-700 font-black">🚫 İptal Talebi</button>
        <button type="button" onclick="selectEditReqType('reschedule')" id="editBtnReschedule"
          class="px-4 py-3 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 font-black">🔁 Değişiklik Talebi</button>
      </div>

      <div id="editRescheduleFields" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-xs font-black text-slate-600">Önerilen Tarih</label>
          <input type="date" name="proposed_date" id="edit_pdate" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
        <div>
          <label class="text-xs font-black text-slate-600">Önerilen Saat</label>
          <input type="time" name="proposed_time" id="edit_ptime" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
        <div>
          <label class="text-xs font-black text-slate-600">Süre (dk)</label>
          <input type="number" min="5" step="5" name="proposed_duration" id="edit_pdur"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold">
        </div>
      </div>

      <div>
        <label class="text-xs font-black text-slate-600">Mesaj</label>
        <textarea name="message" id="edit_msg" rows="4"
          class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-4 text-sm font-semibold outline-none focus:border-[color:var(--atla-primary)]"></textarea>
      </div>

      <div class="flex gap-3">
        <button type="button" onclick="closeModal('editRequestModal')" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-xl font-bold text-sm hover:bg-slate-200 transition">Vazgeç</button>
        <button type="submit" class="flex-1 text-white py-3 rounded-xl font-bold text-sm shadow-lg transition" style="background:var(--atla-primary)" onmouseover="this.style.background='var(--atla-primary-600)'" onmouseout="this.style.background='var(--atla-primary)'">Güncelle</button>
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
      const val = Math.round(target * (1 - Math.pow(1-p,3)));
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

/* ── Erişilebilir modal aç/kapa (focus-trap + Escape) ── */
let _lastFocused = null;
function closeModal(id){
  const el = document.getElementById(id);
  if(!el) return;
  el.classList.add('hidden');
  el.classList.remove('flex');
  el.removeAttribute('aria-modal'); el.removeAttribute('role');
  if(_lastFocused && typeof _lastFocused.focus === 'function'){ _lastFocused.focus(); _lastFocused = null; }
}
function showModal(id){
  const el = document.getElementById(id);
  if(!el) return;
  _lastFocused = document.activeElement;
  el.classList.remove('hidden');
  el.classList.add('flex');
  el.setAttribute('role','dialog'); el.setAttribute('aria-modal','true');
  const focusable = el.querySelector('input,select,textarea,button');
  if(focusable) setTimeout(()=>focusable.focus(), 30);
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

window.addEventListener('click', function(e){
  ['noteModal','requestModal','editRequestModal'].forEach(id=>{
    if(e.target && e.target.id === id) closeModal(id);
  });
});

/* Erişilebilirlik: Escape ile açık modalı kapat */
document.addEventListener('keydown', function(e){
  if(e.key !== 'Escape') return;
  ['noteModal','requestModal','editRequestModal'].forEach(id=>{
    const el = document.getElementById(id);
    if(el && !el.classList.contains('hidden')) closeModal(id);
  });
  const m = document.getElementById('msgModal');
  if(m && !m.classList.contains('hidden')) closeMsgModal();
});

// Kendime Not butonları (inline onclick yerine güvenli yakalama)
document.addEventListener('click', function(e){
  const btn = e.target.closest('[data-note-btn]');
  if(!btn) return;
  const appId = parseInt(btn.dataset.appId || '0', 10);
  const note = btn.dataset.note || '';
  if(!appId) { alert('Randevu ID alınamadı. (data-app-id boş)'); return; }
  openNoteModal(appId, note);
});
</script>

<script>
const MSG_API = "/appointment_messages_api.php";
const CSRF_TOKEN = <?php echo json_encode($csrf); ?>;
const MY_ROLE = <?php echo json_encode($_SESSION['role'] ?? ''); ?>;

const MSG_BTN_DEFAULT_CLASS = "act";
const MSG_BTN_UNREAD_CLASS = "act act-edit";

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
  } catch(e){ console.error(e); }
}

async function openMsg(appId){
  CURRENT_APP_ID = appId;
  document.getElementById('msgBody').innerHTML = '<div class="text-sm text-slate-500 font-semibold">Yükleniyor…</div>';
  showMsgModal();

  try {
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
