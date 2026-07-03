<?php
// koc/odemeler_ozet.php — Tüm öğrencilerin ödeme özeti (dönem + durum filtreli, PDF)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../index.php'); exit;
}
$teacher_id = (int)$_SESSION['user_id'];

require_once __DIR__ . '/../payments_lib.php';
payments_ensure_schema($pdo);
payments_generate_due($pdo, $teacher_id);

// Dönem seçimi (hazır kısayollar + özel tarih aralığı)
$period = $_GET['period'] ?? 'all';
$periodStart = $periodEnd = null; $periodLabel = 'Tüm Zamanlar';
$dFrom = $_GET['from'] ?? ''; $dTo = $_GET['to'] ?? '';
$validDate = fn($s)=> (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$s);

if ($period === 'this') {
    $periodStart = date('Y-m-01'); $periodEnd = date('Y-m-t'); $periodLabel = 'Bu Ay (' . date('m.Y') . ')';
} elseif ($period === 'last') {
    $periodStart = date('Y-m-01', strtotime('first day of last month'));
    $periodEnd   = date('Y-m-t', strtotime('last day of last month'));
    $periodLabel = 'Geçen Ay (' . date('m.Y', strtotime('last month')) . ')';
} elseif ($period === 'range' && ($validDate($dFrom) || $validDate($dTo))) {
    $periodStart = $validDate($dFrom) ? $dFrom : '2000-01-01';
    $periodEnd   = $validDate($dTo)   ? $dTo   : date('Y-m-d');
    $periodLabel = 'Tarih Aralığı (' . date('d.m.Y', strtotime($periodStart)) . ' – ' . date('d.m.Y', strtotime($periodEnd)) . ')';
} else { $period = 'all'; }

// Ödeme JOIN'ine uygulanacak dönem filtresi
$payDateFilter = ''; $params = [$teacher_id];
if ($periodStart) { $payDateFilter = " AND p.due_date BETWEEN ? AND ?"; }

$rows = [];
$grand = ['paid'=>0,'unpaid'=>0,'overdue'=>0];
try {
    $sql = "
        SELECT u.id, u.first_name, u.last_name, cr.lesson_price,
               COALESCE(SUM(CASE WHEN p.status='odendi' THEN p.amount ELSE 0 END),0) AS paid,
               COALESCE(SUM(CASE WHEN p.status='odenmedi' AND p.due_date >= CURDATE() THEN p.amount ELSE 0 END),0) AS unpaid,
               COALESCE(SUM(CASE WHEN p.status='odenmedi' AND p.due_date <  CURDATE() THEN p.amount ELSE 0 END),0) AS overdue,
               MAX(CASE WHEN p.status='odendi' THEN p.paid_date END) AS last_paid,
               COUNT(p.id) AS cnt
        FROM coaching_relationships cr
        JOIN users u ON u.id = cr.student_id
        LEFT JOIN payments p ON p.student_id = cr.student_id AND p.teacher_id = cr.teacher_id" . $payDateFilter . "
        WHERE cr.teacher_id = ?
        GROUP BY u.id, u.first_name, u.last_name, cr.lesson_price
        ORDER BY overdue DESC, unpaid DESC, u.first_name ASC
    ";
    // parametre sırası: (dönem varsa) start,end SONRA teacher_id — sorguda JOIN önce WHERE sonra
    $execParams = $periodStart ? [$periodStart, $periodEnd, $teacher_id] : [$teacher_id];
    $st = $pdo->prepare($sql);
    $st->execute($execParams);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $grand['paid']+=$r['paid']; $grand['unpaid']+=$r['unpaid']; $grand['overdue']+=$r['overdue']; }
} catch (Throwable $e) {}

$B = defined('BASE_URL') ? BASE_URL : '';
$oh = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
include __DIR__ . '/../header.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
:root{--atla-primary:#223488;--atla-primary-600:#314595;--success:#059669;--warning:#d97706;--error:#dc2626;--border:#e2e8f0}
.oz-wrap{max-width:1100px;margin:0 auto;padding:16px}
.oz-card{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
.oz-table{width:100%;border-collapse:collapse;font-size:.84rem}
.oz-table th{background:#f8fafc;padding:.65rem .7rem;text-align:left;font-weight:700;color:#64748b;font-size:.68rem;text-transform:uppercase;border-bottom:2px solid var(--border);white-space:nowrap}
.oz-table td{padding:.65rem .7rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.oz-table tr:hover td{background:#f8fafc}
.oz-av{width:34px;height:34px;border-radius:999px;background:var(--atla-primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem}
.oz-btn{display:inline-flex;align-items:center;gap:.35rem;min-height:38px;padding:0 .8rem;border-radius:10px;font-weight:700;font-size:.75rem;text-decoration:none;cursor:pointer;border:1px solid transparent}
.oz-b-primary{background:var(--atla-primary);color:#fff}.oz-b-primary:hover{background:var(--atla-primary-600)}
.oz-b-soft{background:#fff;color:#334155;border:1px solid var(--border)}.oz-b-soft:hover{background:#f8fafc}
.oz-chip{display:inline-flex;align-items:center;min-height:34px;padding:0 .8rem;border-radius:999px;font-weight:700;font-size:.74rem;text-decoration:none;border:1px solid var(--border);background:#fff;color:#475569}
.oz-chip.active{background:var(--atla-primary);color:#fff;border-color:var(--atla-primary)}
</style>

<div class="oz-wrap">
  <div class="oz-card p-5 mb-4 flex flex-wrap items-center justify-between gap-3" style="background:linear-gradient(135deg,var(--atla-primary),var(--atla-primary-600));color:#fff;border:none">
    <div>
      <h1 class="text-xl md:text-2xl font-extrabold">📊 Tüm Öğrenciler — Ödeme Özeti</h1>
      <p class="text-white/70 text-sm font-semibold mt-0.5"><?= count($rows) ?> öğrenci · <?= $oh($periodLabel) ?> · borca göre sıralı</p>
    </div>
    <a href="<?= $B ?>/koc/odemeler.php" class="oz-btn" style="background:#fff;color:var(--atla-primary)">← Ödeme Yönetimi</a>
  </div>

  <!-- Dönem filtresi: kısayollar + özel tarih aralığı -->
  <div class="oz-card p-3 mb-4">
    <div class="flex flex-wrap items-center gap-2">
      <span class="text-xs font-bold text-slate-400 uppercase mr-1">Dönem:</span>
      <a href="?period=all"  class="oz-chip <?= $period==='all'?'active':'' ?>">Tüm Zamanlar</a>
      <a href="?period=this" class="oz-chip <?= $period==='this'?'active':'' ?>">Bu Ay</a>
      <a href="?period=last" class="oz-chip <?= $period==='last'?'active':'' ?>">Geçen Ay</a>
      <form method="get" class="flex items-center gap-1.5 flex-wrap ml-auto">
        <input type="hidden" name="period" value="range">
        <input type="date" name="from" value="<?= $oh($dFrom) ?>" class="border border-slate-200 rounded-lg px-2 py-1.5 text-xs outline-none focus:border-[color:var(--atla-primary)]">
        <span class="text-slate-400 text-xs">–</span>
        <input type="date" name="to" value="<?= $oh($dTo) ?>" class="border border-slate-200 rounded-lg px-2 py-1.5 text-xs outline-none focus:border-[color:var(--atla-primary)]">
        <button type="submit" class="oz-btn oz-b-primary">Uygula</button>
      </form>
    </div>
    <?php if($period==='range'): ?><p class="text-[11px] text-slate-500 font-semibold mt-2">Seçili: <?= $oh($periodLabel) ?></p><?php endif; ?>
  </div>

  <!-- Genel toplamlar -->
  <div class="grid grid-cols-3 gap-3 mb-4">
    <div class="oz-card p-4" style="border-left:4px solid var(--success)"><div class="text-[11px] font-bold uppercase text-slate-400">Toplam Tahsil</div><div class="text-lg md:text-2xl font-extrabold" style="color:var(--success)"><?= number_format($grand['paid'],0) ?>₺</div></div>
    <div class="oz-card p-4" style="border-left:4px solid var(--warning)"><div class="text-[11px] font-bold uppercase text-slate-400">Toplam Bekleyen</div><div class="text-lg md:text-2xl font-extrabold" style="color:var(--warning)"><?= number_format($grand['unpaid'],0) ?>₺</div></div>
    <div class="oz-card p-4" style="border-left:4px solid var(--error)"><div class="text-[11px] font-bold uppercase text-slate-400">Toplam Gecikmiş</div><div class="text-lg md:text-2xl font-extrabold" style="color:var(--error)"><?= number_format($grand['overdue'],0) ?>₺</div></div>
  </div>

  <div class="oz-card p-4">
    <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
      <div class="flex items-center gap-1.5 flex-wrap">
        <button onclick="ozStatus('all',this)" class="oz-chip active st" data-s="all">Hepsi</button>
        <button onclick="ozStatus('debt',this)" class="oz-chip st" data-s="debt">Borçlu</button>
        <button onclick="ozStatus('overdue',this)" class="oz-chip st" data-s="overdue">Gecikmiş</button>
      </div>
      <div class="flex items-center gap-2">
        <input type="text" id="ozSearch" placeholder="🔍 Ara…" class="border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-[color:var(--atla-primary)]" oninput="ozFilter()">
        <button onclick="ozExport()" class="oz-btn oz-b-soft">⬇️ PDF</button>
      </div>
    </div>
    <?php if(empty($rows)): ?>
      <div class="text-center py-10 text-slate-400"><div class="text-4xl mb-2 opacity-40">👥</div><p class="font-semibold">Bu dönemde kayıt yok.</p></div>
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
          <tr data-name="<?= $oh(mb_strtolower($name,'UTF-8')) ?>" data-debt="<?= $debt>0?1:0 ?>" data-overdue="<?= $r['overdue']>0?1:0 ?>">
            <td>
              <div class="flex items-center gap-2 min-w-0">
                <span class="oz-av"><?= $oh(mb_strtoupper(mb_substr($r['first_name'],0,1),'UTF-8')) ?></span>
                <span class="font-bold text-slate-800 truncate"><?= $oh($name) ?></span>
              </div>
            </td>
            <td style="text-align:right;font-weight:700;color:var(--success)"><?= number_format($r['paid'],0) ?>₺</td>
            <td style="text-align:right;font-weight:700;color:var(--warning)"><?= $r['unpaid']>0?number_format($r['unpaid'],0).'₺':'—' ?></td>
            <td style="text-align:right;font-weight:700;color:var(--error)"><?= $r['overdue']>0?number_format($r['overdue'],0).'₺':'—' ?></td>
            <td style="text-align:right;font-weight:800;color:<?= $debt>0?'var(--error)':'#94a3b8' ?>"><?= $debt>0?number_format($debt,0).'₺':'✓ yok' ?></td>
            <td class="text-slate-500 whitespace-nowrap"><?= $r['last_paid'] ? date('d.m.Y', strtotime($r['last_paid'])) : '—' ?></td>
            <td style="text-align:right"><a href="<?= $B ?>/koc/odemeler.php?student_id=<?= (int)$r['id'] ?>" class="oz-btn oz-b-primary">Detay →</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- PDF şablonu -->
<div id="ozSheet" style="display:none;width:800px;max-width:100%;background:#fff;color:#111;padding:28px;font-family:Poppins,Arial,sans-serif"></div>

<script>
const OZ = {
  teacher: <?= json_encode(trim(($_SESSION['first_name']??'').' '.($_SESSION['last_name']??''))) ?>,
  period: <?= json_encode($periodLabel) ?>,
  grand: { paid: <?= (float)$grand['paid'] ?>, unpaid: <?= (float)$grand['unpaid'] ?>, overdue: <?= (float)$grand['overdue'] ?> },
  rows: <?= json_encode(array_map(fn($r)=>[
    'name'=>$r['first_name'].' '.$r['last_name'], 'paid'=>(float)$r['paid'],
    'unpaid'=>(float)$r['unpaid'], 'overdue'=>(float)$r['overdue'],
    'last'=>$r['last_paid'] ? date('d.m.Y', strtotime($r['last_paid'])) : '—'
  ], $rows), JSON_UNESCAPED_UNICODE) ?>
};
let OZ_STATUS='all', OZ_Q='';
function ozStatus(s,btn){ OZ_STATUS=s; document.querySelectorAll('.st').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); ozFilter(); }
function ozFilter(){
  OZ_Q=(document.getElementById('ozSearch')?.value||'').toLowerCase().trim();
  document.querySelectorAll('#ozTable tbody tr').forEach(tr=>{
    const okName = tr.getAttribute('data-name').includes(OZ_Q);
    const okStat = OZ_STATUS==='all' || (OZ_STATUS==='debt' && tr.getAttribute('data-debt')==='1') || (OZ_STATUS==='overdue' && tr.getAttribute('data-overdue')==='1');
    tr.style.display = (okName && okStat) ? '' : 'none';
  });
}
function tl(n){ return Number(n).toLocaleString('tr-TR')+'₺'; }
async function ozExport(){
  const body = OZ.rows.map(r=>{
    const debt=r.unpaid+r.overdue;
    return `<tr>
      <td style="border:1px solid #ccc;padding:6px">${r.name}</td>
      <td style="border:1px solid #ccc;padding:6px;text-align:right">${tl(r.paid)}</td>
      <td style="border:1px solid #ccc;padding:6px;text-align:right">${tl(r.unpaid)}</td>
      <td style="border:1px solid #ccc;padding:6px;text-align:right">${tl(r.overdue)}</td>
      <td style="border:1px solid #ccc;padding:6px;text-align:right;font-weight:700">${tl(debt)}</td>
      <td style="border:1px solid #ccc;padding:6px">${r.last}</td></tr>`;
  }).join('');
  const el=document.getElementById('ozSheet');
  el.innerHTML=`
    <div style="display:flex;justify-content:space-between;border-bottom:3px solid #223488;padding-bottom:10px;margin-bottom:14px">
      <div style="font-size:22px;font-weight:800;color:#223488">Ders<span style="color:#ec9731">PROS</span></div>
      <div style="text-align:right;font-size:12px">Tarih: ${new Date().toLocaleDateString('tr-TR')}</div>
    </div>
    <div style="font-size:15px;font-weight:700;margin-bottom:6px">Öğrenci Ödeme Özeti — ${OZ.period}</div>
    <div style="font-size:12px;margin-bottom:10px">Öğretmen: ${OZ.teacher}</div>
    <table style="width:100%;border-collapse:collapse;font-size:11px">
      <thead><tr style="background:#f1f5f9">
        <th style="border:1px solid #ccc;padding:6px;text-align:left">Öğrenci</th>
        <th style="border:1px solid #ccc;padding:6px;text-align:right">Tahsil</th>
        <th style="border:1px solid #ccc;padding:6px;text-align:right">Bekleyen</th>
        <th style="border:1px solid #ccc;padding:6px;text-align:right">Gecikmiş</th>
        <th style="border:1px solid #ccc;padding:6px;text-align:right">Borç</th>
        <th style="border:1px solid #ccc;padding:6px;text-align:left">Son Ödeme</th>
      </tr></thead><tbody>${body||'<tr><td colspan=6 style="padding:10px;text-align:center">Kayıt yok</td></tr>'}</tbody>
    </table>
    <div style="margin-top:14px;border-top:2px solid #223488;padding-top:10px;font-size:12px">
      <div style="display:flex;justify-content:space-between"><span>Toplam Tahsil:</span><b style="color:#059669">${tl(OZ.grand.paid)}</b></div>
      <div style="display:flex;justify-content:space-between"><span>Toplam Bekleyen:</span><b style="color:#d97706">${tl(OZ.grand.unpaid)}</b></div>
      <div style="display:flex;justify-content:space-between"><span>Toplam Gecikmiş:</span><b style="color:#dc2626">${tl(OZ.grand.overdue)}</b></div>
    </div>`;
  el.style.display='block';
  el.scrollIntoView({block:'center'});
  await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));
  try {
    await html2pdf().set({margin:8,filename:`Ozet_${OZ.period}.pdf`,image:{type:'jpeg',quality:.98},html2canvas:{scale:2,useCORS:true,backgroundColor:'#ffffff',windowWidth:820},jsPDF:{unit:'mm',format:'a4',orientation:'portrait'}}).from(el).save();
  } finally { el.style.display='none'; }
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>
