<?php
// admin/sinav_tarihleri.php — YKS/LGS sınav tarihlerini ayarla (geri sayım için)
// Tarih kaydedilmezse sistem yaklaşan öğretim yılına göre tahmini varsayılan kullanır
// (YKS ~20 Haziran, LGS ~15 Haziran) ve yıl geçince otomatik bir sonraki yıla kayar.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once '../education_lib.php';
require_once '../app_settings_lib.php';

if (!education_is_admin()) { header("Location: ../index.php"); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['yks' => 'exam_date_yks', 'lgs' => 'exam_date_lgs'] as $f => $k) {
        $v = trim($_POST[$f] ?? '');
        if ($v !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            app_setting_set($pdo, $k, $v);
        }
    }
    $message = '✅ Sınav tarihleri kaydedildi.';
}

$dates = exam_dates($pdo);
$yksLeft = exam_days_left($dates['YKS']);
$lgsLeft = exam_days_left($dates['LGS']);
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sınav Tarihleri</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:560px;margin:48px auto;padding:0 20px;color:#1e293b;line-height:1.6}
  h1{font-size:1.3rem}
  .box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin:16px 0}
  label{display:block;font-size:.8rem;font-weight:700;color:#475569;margin:10px 0 4px}
  input[type=date]{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem}
  .btn{display:inline-block;background:#223488;color:#fff;font-weight:700;padding:11px 22px;border-radius:12px;border:0;cursor:pointer;margin-top:14px}
  .btn:hover{background:#314595}
  .ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:10px 14px;font-size:.9rem}
  .chip{display:inline-block;background:#e0e7ff;color:#4338ca;font-weight:800;font-size:.8rem;border-radius:999px;padding:3px 12px;margin-left:6px}
  .muted{color:#64748b;font-size:.85rem}
</style>
</head>
<body>
  <h1>📅 Sınav Tarihleri</h1>
  <p class="muted">Geri sayım rozetleri (öğretmen paneli + öğrenci ekranı) bu tarihlere göre hesaplanır. Tarih girilmezse tahmini varsayılan kullanılır ve yıl geçince otomatik güncellenir.</p>

  <?php if ($message): ?><div class="ok"><?= $message ?></div><?php endif; ?>

  <form method="POST" class="box">
    <label>🎯 YKS (TYT/AYT) tarihi <span class="chip"><?= $yksLeft >= 0 ? $yksLeft . ' gün kaldı' : 'geçti' ?></span></label>
    <input type="date" name="yks" value="<?= htmlspecialchars($dates['YKS']) ?>">

    <label>📘 LGS tarihi <span class="chip"><?= $lgsLeft >= 0 ? $lgsLeft . ' gün kaldı' : 'geçti' ?></span></label>
    <input type="date" name="lgs" value="<?= htmlspecialchars($dates['LGS']) ?>">

    <button type="submit" class="btn">💾 Kaydet</button>
  </form>

  <p class="muted"><a href="index.php" style="color:#475569">← Admin paneline dön</a></p>
</body>
</html>
