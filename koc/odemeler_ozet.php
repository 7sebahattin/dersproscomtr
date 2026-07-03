<?php
// koc/odemeler_ozet.php — Tüm öğrencilerin ödeme özeti (kim ne kadar ödedi / borcu var)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../index.php'); exit;
}
$teacher_id = (int)$_SESSION['user_id'];

require_once __DIR__ . '/../payments_lib.php';
payments_ensure_schema($pdo);
payments_generate_due($pdo, $teacher_id); // özet güncel olsun

$rows = [];
$grand = ['paid'=>0,'unpaid'=>0,'overdue'=>0];
try {
    $st = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, cr.lesson_price,
               COALESCE(SUM(CASE WHEN p.status='odendi' THEN p.amount ELSE 0 END),0) AS paid,
               COALESCE(SUM(CASE WHEN p.status='odenmedi' AND p.due_date >= CURDATE() THEN p.amount ELSE 0 END),0) AS unpaid,
               COALESCE(SUM(CASE WHEN p.status='odenmedi' AND p.due_date <  CURDATE() THEN p.amount ELSE 0 END),0) AS overdue,
               MAX(CASE WHEN p.status='odendi' THEN p.paid_date END) AS last_paid,
               COUNT(p.id) AS cnt
        FROM coaching_relationships cr
        JOIN users u ON u.id = cr.student_id
        LEFT JOIN payments p ON p.student_id = cr.student_id AND p.teacher_id = cr.teacher_id
        WHERE cr.teacher_id = ?
        GROUP BY u.id, u.first_name, u.last_name, cr.lesson_price
        ORDER BY overdue DESC, unpaid DESC, u.first_name ASC
    ");
    $st->execute([$teacher_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $grand['paid']+=$r['paid']; $grand['unpaid']+=$r['unpaid']; $grand['overdue']+=$r['overdue']; }
} catch (Throwable $e) {}

$B = defined('BASE_URL') ? BASE_URL : '';
$oh = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
include __DIR__ . '/../header.php';
?>
<style>
:root{--atla-primary:#223488;--atla-primary-600:#314595;--success:#059669;--warning:#d97706;--error:#dc2626;--border:#e2e8f0}
.oz-wrap{max-width:1100px;margin:0 auto;padding:16px}
.oz-card{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
.oz-table{width:100%;border-collapse:collapse;font-size:.84rem}
.oz-table th{background:#f8fafc;padding:.65rem .7rem;text-align:left;font-weight:700;color:#64748b;font-size:.68rem;text-transform:uppercase;border-bottom:2px solid var(--border);white-space:nowrap}
.oz-table td{padding:.65rem .7rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.oz-table tr:hover td{background:#f8fafc}
.oz-av{width:34px;height:34px;border-radius:999px;background:var(--atla-primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem}
.oz-btn{display:inline-flex;align-items:center;gap:.35rem;min-height:38px;padding:0 .8rem;border-radius:10px;font-weight:700;font-size:.75rem;background:var(--atla-primary);color:#fff;text-decoration:none}
.oz-btn:hover{background:var(--atla-primary-600)}
</style>

<div class="oz-wrap">
  <div class="oz-card p-5 mb-4 flex flex-wrap items-center justify-between gap-3" style="background:linear-gradient(135deg,var(--atla-primary),var(--atla-primary-600));color:#fff;border:none">
    <div>
      <h1 class="text-xl md:text-2xl font-extrabold">📊 Tüm Öğrenciler — Ödeme Özeti</h1>
      <p class="text-white/70 text-sm font-semibold mt-0.5"><?= count($rows) ?> öğrenci · borca göre sıralı</p>
    </div>
    <a href="<?= $B ?>/koc/odemeler.php" class="oz-btn" style="background:#fff;color:var(--atla-primary)">← Ödeme Yönetimi</a>
  </div>

  <!-- Genel toplamlar -->
  <div class="grid grid-cols-3 gap-3 mb-4">
    <div class="oz-card p-4" style="border-left:4px solid var(--success)"><div class="text-[11px] font-bold uppercase text-slate-400">Toplam Tahsil</div><div class="text-lg md:text-2xl font-extrabold" style="color:var(--success)"><?= number_format($grand['paid'],0) ?>₺</div></div>
    <div class="oz-card p-4" style="border-left:4px solid var(--warning)"><div class="text-[11px] font-bold uppercase text-slate-400">Toplam Bekleyen</div><div class="text-lg md:text-2xl font-extrabold" style="color:var(--warning)"><?= number_format($grand['unpaid'],0) ?>₺</div></div>
    <div class="oz-card p-4" style="border-left:4px solid var(--error)"><div class="text-[11px] font-bold uppercase text-slate-400">Toplam Gecikmiş</div><div class="text-lg md:text-2xl font-extrabold" style="color:var(--error)"><?= number_format($grand['overdue'],0) ?>₺</div></div>
  </div>

  <div class="oz-card p-4">
    <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
      <h3 class="font-extrabold text-slate-800">Öğrenci Bazlı Durum</h3>
      <input type="text" id="ozSearch" placeholder="🔍 Öğrenci ara…" class="border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-[color:var(--atla-primary)]" oninput="ozFilter(this.value)">
    </div>
    <?php if(empty($rows)): ?>
      <div class="text-center py-10 text-slate-400"><div class="text-4xl mb-2 opacity-40">👥</div><p class="font-semibold">Henüz öğrenci/ödeme yok.</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="oz-table" id="ozTable">
        <thead><tr>
          <th>Öğrenci</th><th style="text-align:right">Tahsil</th><th style="text-align:right">Bekleyen</th>
          <th style="text-align:right">Gecikmiş</th><th style="text-align:right">Toplam Borç</th><th>Son Ödeme</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach($rows as $r):
          $debt = (float)$r['unpaid'] + (float)$r['overdue'];
          $name = $r['first_name'].' '.$r['last_name'];
        ?>
          <tr data-name="<?= $oh(mb_strtolower($name,'UTF-8')) ?>">
            <td>
              <div class="flex items-center gap-2 min-w-0">
                <span class="oz-av"><?= $oh(mb_strtoupper(mb_substr($r['first_name'],0,1),'UTF-8')) ?></span>
                <span class="font-bold text-slate-800 truncate"><?= $oh($name) ?></span>
              </div>
            </td>
            <td style="text-align:right;font-weight:700;color:var(--success)"><?= number_format($r['paid'],0) ?>₺</td>
            <td style="text-align:right;font-weight:700;color:var(--warning)"><?= $r['unpaid']>0?number_format($r['unpaid'],0).'₺':'—' ?></td>
            <td style="text-align:right;font-weight:700;color:var(--error)"><?= $r['overdue']>0?number_format($r['overdue'],0).'₺':'—' ?></td>
            <td style="text-align:right;font-weight:800;color:<?= $debt>0?'var(--error)':'#94a3b8' ?>"><?= $debt>0?number_format($debt,0).'₺':'✓ Borç yok' ?></td>
            <td class="text-slate-500 whitespace-nowrap"><?= $r['last_paid'] ? date('d.m.Y', strtotime($r['last_paid'])) : '—' ?></td>
            <td style="text-align:right"><a href="<?= $B ?>/koc/odemeler.php?student_id=<?= (int)$r['id'] ?>" class="oz-btn">Detay →</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function ozFilter(q){
  q=(q||'').toLowerCase().trim();
  document.querySelectorAll('#ozTable tbody tr').forEach(tr=>{
    tr.style.display = tr.getAttribute('data-name').includes(q) ? '' : 'none';
  });
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>
