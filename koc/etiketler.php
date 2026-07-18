<?php
// koc/etiketler.php — Öğrenci Etiketleri + Toplu İşlem Geçmişi (S6)
// Etiket oluştur, öğrencileri ata; planlayıcıdaki "Başka Öğrencilere Uygula"
// modalında etiketler hızlı seçim çipi olur. Toplu uygulanan partiler
// bulk_id ile burada listelenir ve tek tıkla geri alınabilir
// (yalnız öğrencinin henüz dokunmadığı 'bekliyor' görevler silinir).

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../tags_lib.php';
include __DIR__ . '/../header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo "<script>window.location.href='../index.php';</script>";
    exit;
}
$teacher_id = (int)$_SESSION['user_id'];

$message = ''; $messageOk = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_tag'])) {
        $res = tags_create($pdo, $teacher_id, (string)($_POST['name'] ?? ''), (string)($_POST['color'] ?? '#223488'));
        $message = !empty($res['ok']) ? '✅ Etiket oluşturuldu.' : '❌ ' . ($res['error'] ?? 'Hata');
        $messageOk = !empty($res['ok']);
    } elseif (isset($_POST['del_tag'])) {
        $messageOk = tags_delete($pdo, $teacher_id, (int)$_POST['del_tag']);
        $message = $messageOk ? 'Etiket silindi.' : '❌ Etiket silinemedi.';
    } elseif (isset($_POST['save_members'])) {
        $res = tags_set_members($pdo, $teacher_id, (int)$_POST['tag_id'], $_POST['members'] ?? []);
        $message = !empty($res['ok']) ? '✅ Üyeler kaydedildi (' . (int)($res['count'] ?? 0) . ' öğrenci).' : '❌ ' . ($res['error'] ?? 'Hata');
        $messageOk = !empty($res['ok']);
    } elseif (isset($_POST['undo_bulk'])) {
        $n = tags_undo_bulk($pdo, $teacher_id, (string)$_POST['undo_bulk']);
        $message = $n > 0 ? "✅ $n görev geri alındı (öğrencinin işaretledikleri korundu)."
                          : 'Geri alınacak bekleyen görev kalmamış.';
    }
}

// Koçun öğrencileri
$students = [];
try {
    $st = $pdo->prepare("SELECT u.id, u.first_name, u.last_name
                         FROM users u JOIN coaching_relationships cr ON cr.student_id = u.id
                         WHERE cr.teacher_id = ? ORDER BY u.first_name, u.last_name");
    $st->execute([$teacher_id]);
    $students = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$tags  = tags_for_teacher($pdo, $teacher_id);
$bulks = tags_recent_bulks($pdo, $teacher_id);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="min-h-screen bg-slate-50 pb-20">
  <div class="max-w-4xl mx-auto px-4 pt-8">

    <h1 class="text-2xl font-black text-[#223488]">🏷 Öğrenci Etiketleri</h1>
    <p class="text-xs text-slate-500 font-medium mt-1 mb-6">
      Öğrencilerini grupla (örn. "12-Sayısal", "Sabah grubu"). Planlayıcıdaki
      "Başka Öğrencilere Uygula" penceresinde etiketler tek tık grup seçimi olur.
    </p>

    <?php if ($message): ?>
      <div class="mb-5 px-4 py-3 rounded-xl text-sm font-bold <?php echo $messageOk ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <!-- YENİ ETİKET -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-6">
      <form method="POST" class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-end">
        <div class="flex-grow">
          <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1">Etiket adı</label>
          <input name="name" maxlength="50" required placeholder="Örn: 12-Sayısal"
                 class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5 font-medium text-slate-700">
        </div>
        <div>
          <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1">Renk</label>
          <input type="color" name="color" value="#223488"
                 class="w-16 h-10 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
        </div>
        <button name="create_tag" value="1"
                class="bg-[#223488] hover:bg-[#314595] text-white text-sm font-black px-5 py-2.5 rounded-xl transition shrink-0">+ Oluştur</button>
      </form>
    </div>

    <!-- ETİKETLER -->
    <?php if (!$tags): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 text-center text-slate-400 text-sm font-bold mb-6">Henüz etiket yok.</div>
    <?php else: ?>
      <div class="space-y-4 mb-8">
      <?php foreach ($tags as $t): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="px-5 py-3.5 flex items-center justify-between" style="background: linear-gradient(90deg, <?php echo htmlspecialchars($t['color']); ?>18, transparent)">
            <div class="flex items-center gap-2.5">
              <span class="w-3.5 h-3.5 rounded-full" style="background: <?php echo htmlspecialchars($t['color']); ?>"></span>
              <h2 class="text-sm font-black text-slate-700"><?php echo htmlspecialchars($t['name']); ?></h2>
              <span class="text-[10px] font-bold text-slate-400"><?php echo count($t['members']); ?> öğrenci</span>
            </div>
            <form method="POST" onsubmit="return confirm('Etiket silinsin mi? (Öğrencilere ve görevlere dokunulmaz)')" style="margin:0">
              <button name="del_tag" value="<?php echo (int)$t['id']; ?>"
                      class="text-[10px] font-bold text-slate-300 hover:text-red-500 transition">Sil</button>
            </form>
          </div>
          <form method="POST" class="p-4">
            <input type="hidden" name="tag_id" value="<?php echo (int)$t['id']; ?>">
            <div class="flex flex-wrap gap-1.5 mb-3">
              <?php foreach ($students as $s): $on = in_array((int)$s['id'], $t['members'], true); ?>
              <label class="cursor-pointer">
                <input type="checkbox" name="members[]" value="<?php echo (int)$s['id']; ?>" class="peer hidden" <?php echo $on ? 'checked' : ''; ?>>
                <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-full border transition
                             peer-checked:text-white border-slate-200 text-slate-500 bg-slate-50"
                      style="<?php echo $on ? 'background:' . htmlspecialchars($t['color']) . ';border-color:' . htmlspecialchars($t['color']) . ';color:#fff' : ''; ?>">
                  <?php echo htmlspecialchars($s['first_name'] . ' ' . mb_substr($s['last_name'], 0, 1, 'UTF-8') . '.'); ?>
                </span>
              </label>
              <?php endforeach; ?>
            </div>
            <button name="save_members" value="1"
                    class="text-xs font-black text-[#223488] bg-blue-50 hover:bg-blue-100 px-4 py-2 rounded-xl transition">Üyeleri Kaydet</button>
          </form>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- TOPLU İŞLEM GEÇMİŞİ -->
    <h2 class="text-lg font-black text-[#223488] mb-1">↩️ Toplu İşlem Geçmişi</h2>
    <p class="text-xs text-slate-500 font-medium mb-4">
      "Başka Öğrencilere Uygula" ile eklenen partiler. Geri alma yalnız öğrencinin
      henüz dokunmadığı (bekleyen) görevleri siler — işaretlenmiş görev korunur.
    </p>
    <?php if (!$bulks): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 text-center text-slate-400 text-sm font-bold">Henüz toplu işlem yok.</div>
    <?php else: ?>
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 divide-y divide-slate-100">
      <?php foreach ($bulks as $b): ?>
        <div class="p-4 flex items-center justify-between gap-3">
          <div class="min-w-0">
            <div class="text-sm font-bold text-slate-700">
              <?php echo (int)$b['total']; ?> görev · <?php echo (int)$b['students']; ?> öğrenci
              <span class="text-slate-400 font-medium text-xs">(<?php echo date('d.m', strtotime($b['d1'])); ?>–<?php echo date('d.m.Y', strtotime($b['d2'])); ?>)</span>
            </div>
            <div class="text-[10px] font-medium text-slate-400">
              <?php echo (int)$b['undoable']; ?> görev hâlâ geri alınabilir · <code class="text-[9px]"><?php echo htmlspecialchars($b['bulk_id']); ?></code>
            </div>
          </div>
          <?php if ((int)$b['undoable'] > 0): ?>
          <form method="POST" onsubmit="return confirm('<?php echo (int)$b['undoable']; ?> bekleyen görev silinecek. Emin misin?')" style="margin:0">
            <button name="undo_bulk" value="<?php echo htmlspecialchars($b['bulk_id']); ?>"
                    class="bg-red-50 hover:bg-red-100 text-red-600 text-xs font-black px-3.5 py-2 rounded-xl transition shrink-0">Geri Al</button>
          </form>
          <?php else: ?>
          <span class="text-[10px] font-bold text-slate-300 shrink-0">tamamı işlenmiş</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
