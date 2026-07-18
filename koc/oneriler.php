<?php
// koc/oneriler.php — Görev Önerileri onay ekranı (S2)
// Analiz→aksiyon, aralıklı tekrar ve risk kaynaklı öneriler burada toplanır;
// koç tarih seçip onaylar (schedule_items'a kopyalanır) veya reddeder.
// Hiçbir öneri onaysız plana girmez.

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app_settings_lib.php';
require_once __DIR__ . '/../suggest_lib.php';
include __DIR__ . '/../header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo "<script>window.location.href='../index.php';</script>";
    exit;
}
$teacher_id = (int)$_SESSION['user_id'];

if (!ff_enabled($pdo, 'suggest')) {
    echo '<div class="max-w-3xl mx-auto mt-16 p-8 text-center text-slate-500 font-bold bg-white rounded-2xl shadow-sm border border-slate-200">
            Öneri modülü şu an kapalı. Admin panelindeki Özellik Bayrakları ekranından açılabilir.
          </div>';
    include __DIR__ . '/../footer.php';
    return;
}

$message = ''; $messageOk = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sug_id = (int)($_POST['sug_id'] ?? 0);
    if (isset($_POST['approve'])) {
        $res = suggest_decide($pdo, $teacher_id, $sug_id, true,
                              $_POST['date'] ?? null,
                              isset($_POST['amount']) && $_POST['amount'] !== '' ? (int)$_POST['amount'] : null);
        $message   = $res['ok'] ? '✅ Öneri onaylandı ve programa eklendi.' : '❌ ' . ($res['error'] ?? 'Hata');
        $messageOk = $res['ok'];
    } elseif (isset($_POST['reject'])) {
        $res = suggest_decide($pdo, $teacher_id, $sug_id, false);
        $message   = $res['ok'] ? 'Öneri reddedildi.' : '❌ ' . ($res['error'] ?? 'Hata');
        $messageOk = $res['ok'];
    }
}

suggest_expire($pdo); // 14 günü geçen bekleyenler otomatik düşer
$pending = suggest_pending_for_teacher($pdo, $teacher_id);

// Öğrenciye göre grupla
$byStudent = [];
foreach ($pending as $p) {
    $byStudent[(int)$p['student_id']]['name'] = $p['first_name'] . ' ' . $p['last_name'];
    $byStudent[(int)$p['student_id']]['items'][] = $p;
}

$srcBadge = [
    'analiz' => ['📊 Analiz',  'bg-blue-100 text-blue-700'],
    'tekrar' => ['🔁 Tekrar',  'bg-purple-100 text-purple-700'],
    'risk'   => ['🚨 Risk',    'bg-red-100 text-red-700'],
    'manuel' => ['✍️ Manuel',  'bg-slate-100 text-slate-600'],
];
$actLabel = ['soru' => 'soru', 'konu' => 'dk konu', 'video' => 'video'];
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="min-h-screen bg-slate-50 pb-20">
  <div class="max-w-4xl mx-auto px-4 pt-8">

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-black text-[#223488]">💡 Görev Önerileri</h1>
        <p class="text-xs text-slate-500 font-medium mt-1">
          Analiz, tekrar ve risk motorlarından gelen öneriler. Onayladıkların öğrencinin programına eklenir;
          hiçbir öneri onaysız plana girmez. 14 gün bekleyen öneriler otomatik düşer.
        </p>
      </div>
      <span class="bg-[#223488] text-white text-sm font-black px-3 py-1.5 rounded-xl shrink-0"><?php echo count($pending); ?> bekleyen</span>
    </div>

    <?php if ($message): ?>
      <div class="mb-5 px-4 py-3 rounded-xl text-sm font-bold <?php echo $messageOk ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <?php if (empty($byStudent)): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-10 text-center">
        <div class="text-4xl mb-3">🎉</div>
        <p class="font-bold text-slate-600">Bekleyen öneri yok.</p>
        <p class="text-xs text-slate-400 mt-1">Analiz sekmesindeki "Görev öner" butonları ve gece çalışan tekrar motoru buraya öneri gönderir.</p>
      </div>
    <?php else: ?>

      <?php foreach ($byStudent as $stuId => $grp): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-5">
        <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-5 py-3.5 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <span class="w-7 h-7 rounded-full bg-white/15 text-white text-xs font-black flex items-center justify-center">
              <?php echo mb_strtoupper(mb_substr($grp['name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
            </span>
            <h2 class="text-sm font-bold text-white"><?php echo htmlspecialchars($grp['name']); ?></h2>
          </div>
          <a href="<?php echo BASE_URL; ?>/koc_paneli.php?student_id=<?php echo $stuId; ?>"
             class="text-[10px] font-bold text-blue-200 hover:text-white transition">Panel →</a>
        </div>

        <div class="divide-y divide-slate-100">
          <?php foreach ($grp['items'] as $p):
              [$badgeTxt, $badgeCls] = $srcBadge[$p['source']] ?? $srcBadge['manuel'];
              $topicTxt = $p['edu_topic_name']
                  ? ($p['edu_subject_name'] . ' › ' . $p['edu_topic_name'])
                  : trim(($p['custom_subject'] ?? '') . ' › ' . ($p['custom_topic'] ?? ''), ' ›');
              $defDate = $p['suggested_date'] ?: date('Y-m-d', strtotime('+1 day'));
          ?>
          <div class="p-4 flex flex-col md:flex-row md:items-center gap-3">
            <div class="flex-grow min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[10px] font-black px-2 py-0.5 rounded-md <?php echo $badgeCls; ?>"><?php echo $badgeTxt; ?></span>
                <span class="font-bold text-slate-700 text-sm truncate"><?php echo htmlspecialchars($topicTxt ?: 'Konu belirtilmemiş'); ?></span>
                <span class="text-xs font-black text-[#ec9731]"><?php echo (int)$p['amount']; ?> <?php echo $actLabel[$p['action_type']] ?? ''; ?></span>
              </div>
              <?php if ($p['reason']): ?>
                <p class="text-[11px] text-slate-500 mt-1 leading-snug">↳ <?php echo htmlspecialchars($p['reason']); ?></p>
              <?php endif; ?>
            </div>

            <form method="POST" class="flex items-center gap-2 shrink-0">
              <input type="hidden" name="sug_id" value="<?php echo (int)$p['id']; ?>">
              <input type="date" name="date" value="<?php echo $defDate; ?>"
                     class="border border-slate-200 rounded-lg text-xs p-2 font-medium text-slate-600 bg-slate-50">
              <input type="number" name="amount" value="<?php echo (int)$p['amount']; ?>" min="1" max="500"
                     class="w-16 border border-slate-200 rounded-lg text-xs p-2 font-bold text-slate-700 bg-slate-50 text-center">
              <button name="approve" value="1"
                      class="bg-green-600 hover:bg-green-700 text-white text-xs font-black px-3 py-2 rounded-lg transition">Onayla</button>
              <button name="reject" value="1"
                      class="bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 text-xs font-bold px-3 py-2 rounded-lg transition"
                      onclick="return confirm('Bu öneri reddedilsin mi?')">Reddet</button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
