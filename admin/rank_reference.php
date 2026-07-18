<?php
// admin/rank_reference.php — Net→Sıralama referans noktaları (S4)
// Sıralama tahmini bu noktalar arasında lineer interpolasyonla hesaplanır.
// Her yıl ÖSYM/LGS açıklamalarından güncellenir; 12 aydan eski veri
// öğrenci ekranında "güncel değil" işaretiyle gösterilir.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once '../education_lib.php';
require_once '../goals_lib.php';

if (!education_is_admin()) { header("Location: ../index.php"); exit; }

goals_ensure_schema($pdo);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_point'])) {
        $yr  = (int)($_POST['ref_year'] ?? 0);
        $cat = in_array($_POST['exam_category'] ?? '', ['TYT','AYT','LGS'], true) ? $_POST['exam_category'] : '';
        $net = (float)($_POST['net_value'] ?? -1);
        $rk  = (int)($_POST['rank_estimate'] ?? 0);
        if ($yr >= 2020 && $yr <= 2035 && $cat && $net >= 0 && $rk > 0) {
            try {
                $pdo->prepare("INSERT INTO rank_reference (ref_year, exam_category, net_value, rank_estimate)
                               VALUES (?,?,?,?)
                               ON DUPLICATE KEY UPDATE rank_estimate = VALUES(rank_estimate)")
                    ->execute([$yr, $cat, $net, $rk]);
                $message = '✅ Nokta kaydedildi.';
            } catch (Throwable $e) { $message = '❌ Kayıt hatası.'; }
        } else {
            $message = '❌ Geçersiz değerler (yıl 2020-2035, net ≥ 0, sıralama > 0).';
        }
    }
    if (isset($_POST['del_point'])) {
        $pdo->prepare("DELETE FROM rank_reference WHERE id = ?")->execute([(int)$_POST['del_point']]);
        $message = 'Nokta silindi.';
    }
}

$rows = $pdo->query("SELECT * FROM rank_reference ORDER BY exam_category, ref_year DESC, net_value DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
$byCat = [];
foreach ($rows as $r) { $byCat[$r['exam_category']][] = $r; }
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Net → Sıralama Referansı</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:720px;margin:48px auto;padding:0 20px;color:#1e293b;line-height:1.6}
  h1{font-size:1.3rem} h2{font-size:1rem;margin-top:26px}
  .box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin:16px 0}
  label{display:block;font-size:.75rem;font-weight:700;color:#475569;margin:8px 0 3px}
  input,select{width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:10px;font-size:.95rem;box-sizing:border-box}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
  .btn{background:#223488;color:#fff;font-weight:700;padding:10px 20px;border-radius:12px;border:0;cursor:pointer;margin-top:12px}
  .btn:hover{background:#314595}
  .del{background:none;border:0;color:#dc2626;cursor:pointer;font-weight:700;font-size:.8rem}
  table{width:100%;border-collapse:collapse;font-size:.85rem}
  th,td{text-align:left;padding:7px 10px;border-bottom:1px solid #e2e8f0}
  th{font-size:.7rem;text-transform:uppercase;color:#64748b}
  .ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:10px 14px;font-size:.9rem}
  .muted{color:#64748b;font-size:.85rem}
  .chip{display:inline-block;background:#e0e7ff;color:#4338ca;font-weight:800;font-size:.75rem;border-radius:999px;padding:2px 10px}
  a{color:#223488}
</style>
</head>
<body>
  <h1>📈 Net → Sıralama Referansı</h1>
  <p class="muted">Öğrenci ekranındaki sıralama tahmini bu noktalar arasında interpolasyonla hesaplanır.
  Kategori başına en az 2 nokta gerekir; en güncel yıl kullanılır. Örnek: TYT 2025, 90 net → ~50.000.</p>

  <?php if ($message): ?><div class="ok"><?php echo $message; ?></div><?php endif; ?>

  <div class="box">
    <form method="POST">
      <div class="grid">
        <div><label>Yıl</label><input type="number" name="ref_year" value="<?php echo (int)date('Y'); ?>" min="2020" max="2035"></div>
        <div><label>Kategori</label><select name="exam_category"><option>TYT</option><option>AYT</option><option>LGS</option></select></div>
        <div><label>Net</label><input type="number" name="net_value" step="0.25" min="0" max="120" placeholder="90"></div>
        <div><label>Sıralama</label><input type="number" name="rank_estimate" min="1" placeholder="50000"></div>
      </div>
      <button class="btn" name="add_point" value="1">Nokta Ekle / Güncelle</button>
    </form>
  </div>

  <?php foreach (['TYT','AYT','LGS'] as $cat): if (empty($byCat[$cat])) continue; ?>
  <h2><span class="chip"><?php echo $cat; ?></span></h2>
  <table>
    <tr><th>Yıl</th><th>Net</th><th>Sıralama</th><th></th></tr>
    <?php foreach ($byCat[$cat] as $r): ?>
    <tr>
      <td><?php echo (int)$r['ref_year']; ?></td>
      <td><?php echo number_format((float)$r['net_value'], 2, ',', '.'); ?></td>
      <td>~<?php echo number_format((int)$r['rank_estimate'], 0, ',', '.'); ?></td>
      <td>
        <form method="POST" style="margin:0" onsubmit="return confirm('Bu nokta silinsin mi?')">
          <button class="del" name="del_point" value="<?php echo (int)$r['id']; ?>">Sil</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endforeach; ?>

  <?php if (!$rows): ?>
  <div class="box muted">Henüz nokta girilmedi. Tahmin, veri girilene kadar öğrenci ekranında yalnızca net trendi olarak görünür.</div>
  <?php endif; ?>

  <p style="margin-top:26px"><a href="index.php">← Admin panele dön</a> · <a href="features.php">Özellik Bayrakları</a></p>
</body>
</html>
