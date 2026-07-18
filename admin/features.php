<?php
// admin/features.php — Özellik bayrakları (feature flag) + metrik watchdog
// Yeni modüller (zaman motoru, öneri kuyruğu, risk skoru, hedef, XP, lig)
// kapalı doğar; staging'de test edilir, buradan tek tıkla açılır/kapanır.
// Sorun çıkarsa kod geri alınmadan bayrak kapatılır.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once '../education_lib.php';
require_once '../app_settings_lib.php';
require_once '../metrics_lib.php';

if (!education_is_admin()) { header("Location: ../index.php"); exit; }

$flags = [
    'timer'   => ['⏱ Zaman Motoru',      'Pomodoro/kronometre, gerçek çalışma süresi (S1)'],
    'suggest' => ['💡 Öneri Kuyruğu',     'Analiz→aksiyon + tekrar önerileri, koç onaylı (S2/S5)'],
    'risk'    => ['🚨 Risk Skoru',        'Erken uyarı, koç panosu kartı + T_RISK push (S3)'],
    'goals'   => ['🎯 Hedef & Tahmin',    'Hedef üniversite/net, sıralama tahmini (S4)'],
    'xp'      => ['⚡ XP & Başarımlar',   'XP/seviye, başarımlar, kalkan dükkânı (S8)'],
    'league'  => ['🏆 Liderlik Ligi',     'Koç-içi haftalık lig, opt-in + rumuz (S9)'],
];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle']) && isset($flags[$_POST['toggle']])) {
        $name = $_POST['toggle'];
        $now  = !ff_enabled($pdo, $name);
        ff_set($pdo, $name, $now);
        $message = '✅ ' . $flags[$name][0] . ($now ? ' açıldı.' : ' kapatıldı.');
    }
    if (isset($_POST['run_metrics'])) {
        try {
            $res = metrics_run($pdo);
            require_once '../risk_lib.php';
            $rr = risk_run($pdo);
            $message = "✅ Metrikler hesaplandı: {$res['days']} gün, {$res['rows']} satır. "
                     . "Risk: {$rr['students']} öğrenci (kırmızı {$rr['red']}, sarı {$rr['yellow']}).";
        } catch (Throwable $e) {
            $message = '❌ Metrik hatası: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$lastRun = app_setting_get($pdo, 'metrics_last_run');
$stale   = true;
if ($lastRun) {
    $stale = (time() - strtotime($lastRun)) > 26 * 3600; // 26 saat watchdog eşiği
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Özellik Bayrakları</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:640px;margin:48px auto;padding:0 20px;color:#1e293b;line-height:1.6}
  h1{font-size:1.3rem}
  .box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px 18px;margin:12px 0;display:flex;align-items:center;gap:14px}
  .box .info{flex:1}
  .box b{display:block}
  .box .desc{color:#64748b;font-size:.82rem}
  .btn{background:#223488;color:#fff;font-weight:700;padding:9px 18px;border-radius:10px;border:0;cursor:pointer;font-size:.85rem}
  .btn:hover{background:#314595}
  .btn.off{background:#e2e8f0;color:#475569}
  .btn.off:hover{background:#cbd5e1}
  .state{font-weight:800;font-size:.78rem;border-radius:999px;padding:3px 12px}
  .state.on{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
  .state.off{background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0}
  .ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:10px 14px;font-size:.9rem;margin:14px 0}
  .warn{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:10px;padding:10px 14px;font-size:.85rem;margin:14px 0}
  .muted{color:#64748b;font-size:.85rem}
  a{color:#223488}
</style>
</head>
<body>
  <h1>🚩 Özellik Bayrakları</h1>
  <p class="muted">Yeni modüller kapalı doğar. Staging'de test edilip burada açılır; sorun çıkarsa kod geri alınmadan kapatılır. Plan: <code>docs/plan-v1-secili-moduller.md</code></p>

  <?php if ($message): ?><div class="ok"><?php echo $message; ?></div><?php endif; ?>

  <?php foreach ($flags as $name => [$title, $desc]): $on = ff_enabled($pdo, $name); ?>
  <div class="box">
    <div class="info">
      <b><?php echo $title; ?></b>
      <span class="desc"><?php echo $desc; ?></span>
    </div>
    <span class="state <?php echo $on ? 'on' : 'off'; ?>"><?php echo $on ? 'AÇIK' : 'KAPALI'; ?></span>
    <form method="POST" style="margin:0">
      <button class="btn <?php echo $on ? 'off' : ''; ?>" name="toggle" value="<?php echo $name; ?>">
        <?php echo $on ? 'Kapat' : 'Aç'; ?>
      </button>
    </form>
  </div>
  <?php endforeach; ?>

  <h1 style="margin-top:34px">📊 Metrik Watchdog</h1>
  <?php if ($stale): ?>
    <div class="warn">⚠️ Günlük metrik hesabı <?php echo $lastRun ? '26 saatten eski (son: ' . htmlspecialchars($lastRun) . ')' : 'hiç çalışmamış'; ?>.
    Cron'u kontrol edin veya aşağıdan elle çalıştırın (ilk çalıştırma 90 gün geriye doldurur).</div>
  <?php else: ?>
    <div class="ok">✅ Son hesap: <?php echo htmlspecialchars($lastRun); ?></div>
  <?php endif; ?>
  <form method="POST">
    <button class="btn" name="run_metrics" value="1">Metrikleri şimdi hesapla</button>
  </form>

  <p style="margin-top:26px"><a href="index.php">← Admin panele dön</a></p>
</body>
</html>
