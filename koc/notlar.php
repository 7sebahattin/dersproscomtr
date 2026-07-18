<?php
// koc/notlar.php — Seans Notları (S7)
// Öğrenci başına zaman çizgili görüşme kaydı. Yeni not formu bir önceki
// notun "sonraki adım"ını gündem olarak açar. Görünürlük: varsayılan
// yalnız koç; "öğrenci görsün" işaretlenirse öğrenci ana sayfasında
// "Koçundan" kartında görünür.

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../notes_lib.php';
include __DIR__ . '/../header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo "<script>window.location.href='../index.php';</script>";
    exit;
}
$teacher_id = (int)$_SESSION['user_id'];

$message = ''; $messageOk = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_note'])) {
        $res = notes_save($pdo, $teacher_id, $_POST);
        $message   = !empty($res['ok']) ? '✅ Not kaydedildi.' : '❌ ' . ($res['error'] ?? 'Hata');
        $messageOk = !empty($res['ok']);
    } elseif (isset($_POST['del_note'])) {
        $messageOk = notes_delete($pdo, $teacher_id, (int)$_POST['del_note']);
        $message   = $messageOk ? 'Not silindi.' : '❌ Not silinemedi.';
    }
}

// Koçun öğrencileri
$students = [];
try {
    $st = $pdo->prepare("SELECT u.id, u.first_name, u.last_name
                         FROM users u
                         JOIN coaching_relationships cr ON cr.student_id = u.id
                         WHERE cr.teacher_id = ? ORDER BY u.first_name, u.last_name");
    $st->execute([$teacher_id]);
    $students = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$selId = (int)($_REQUEST['student_id'] ?? 0);
if ($selId && !in_array($selId, array_map(fn($s) => (int)$s['id'], $students), true)) $selId = 0;
if (!$selId && $students) $selId = (int)$students[0]['id'];

$notes    = $selId ? notes_for_student($pdo, $teacher_id, $selId) : [];
$nextStep = $selId ? notes_last_next_step($pdo, $teacher_id, $selId) : null;

$visBadge = [
    'private' => ['🔒 Yalnız ben', 'bg-slate-100 text-slate-600'],
    'student' => ['👤 Öğrenci görüyor', 'bg-blue-100 text-blue-700'],
    'parent'  => ['👨‍👩‍👧 Öğrenci + veli', 'bg-purple-100 text-purple-700'],
];
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="min-h-screen bg-slate-50 pb-20">
  <div class="max-w-4xl mx-auto px-4 pt-8">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
      <div>
        <h1 class="text-2xl font-black text-[#223488]">🗒 Seans Notları</h1>
        <p class="text-xs text-slate-500 font-medium mt-1">Görüşme kaydı: ne konuşuldu, ne kararlaştırıldı, sonraki adım ne. Varsayılan olarak yalnız sen görürsün.</p>
      </div>
      <form method="GET" class="shrink-0">
        <select name="student_id" onchange="this.form.submit()"
                class="bg-white border border-slate-200 rounded-xl text-sm p-2.5 font-bold text-slate-700 shadow-sm">
          <?php foreach ($students as $s): ?>
          <option value="<?php echo (int)$s['id']; ?>" <?php echo (int)$s['id'] === $selId ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if ($message): ?>
      <div class="mb-5 px-4 py-3 rounded-xl text-sm font-bold <?php echo $messageOk ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <?php if (!$students): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-10 text-center text-slate-400 font-bold">Henüz kayıtlı öğrenciniz yok.</div>
    <?php else: ?>

    <!-- YENİ NOT -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
      <div class="bg-gradient-to-r from-[#1a1a2e] to-[#223488] px-5 py-3.5 flex items-center gap-2">
        <i class="fa-solid fa-pen-nib text-[#ec9731]"></i>
        <h2 class="text-xs font-bold text-white uppercase tracking-wider">Yeni Seans Notu</h2>
      </div>
      <form method="POST" class="p-5 space-y-3">
        <input type="hidden" name="save_note" value="1">
        <input type="hidden" name="student_id" value="<?php echo $selId; ?>">

        <?php if ($nextStep): ?>
        <div class="p-3 rounded-xl bg-amber-50 border border-amber-100 text-xs font-bold text-amber-700">
          📌 Geçen seanstan gündem: <?php echo htmlspecialchars($nextStep); ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1">Tarih</label>
            <input type="date" name="note_date" value="<?php echo date('Y-m-d'); ?>"
                   class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5 font-medium text-slate-700">
          </div>
          <div>
            <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1">Kim görebilsin?</label>
            <select name="visibility" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5 font-medium text-slate-700">
              <option value="private">🔒 Yalnız ben</option>
              <option value="student">👤 Öğrenci de görsün</option>
              <option value="parent">👨‍👩‍👧 Öğrenci + veli görsün</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1">Ne konuşuldu?</label>
          <textarea name="discussed" rows="2" placeholder="Seansta ele alınan konular..."
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5 font-medium text-slate-700"></textarea>
        </div>
        <div>
          <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1">Ne kararlaştırıldı?</label>
          <textarea name="decisions" rows="2" placeholder="Alınan kararlar..."
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5 font-medium text-slate-700"></textarea>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1">Seans sonrası ödev</label>
            <textarea name="homework" rows="2" placeholder="Öğrenciye verilen ödev..."
                      class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5 font-medium text-slate-700"></textarea>
          </div>
          <div>
            <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1">Sonraki adım (gelecek seansın gündemi)</label>
            <textarea name="next_step" rows="2" maxlength="255" placeholder="Bir sonraki seansta..."
                      class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-2.5 font-medium text-slate-700"></textarea>
          </div>
        </div>

        <button class="bg-[#223488] hover:bg-[#314595] text-white text-sm font-black px-6 py-2.5 rounded-xl transition">Notu Kaydet</button>
      </form>
    </div>

    <!-- ZAMAN ÇİZGİSİ -->
    <?php if (empty($notes)): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 text-center text-slate-400 text-sm font-bold">
        Bu öğrenci için henüz not yok — ilk seans notunu yukarıdan ekle.
      </div>
    <?php else: ?>
      <div class="space-y-4">
      <?php foreach ($notes as $n): [$vTxt, $vCls] = $visBadge[$n['visibility']] ?? $visBadge['private']; ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 relative">
          <div class="flex items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-2">
              <span class="text-sm font-black text-[#223488]"><?php echo date('d.m.Y', strtotime($n['note_date'])); ?></span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-md <?php echo $vCls; ?>"><?php echo $vTxt; ?></span>
            </div>
            <form method="POST" onsubmit="return confirm('Bu not silinsin mi?')" style="margin:0">
              <input type="hidden" name="student_id" value="<?php echo $selId; ?>">
              <button name="del_note" value="<?php echo (int)$n['id']; ?>"
                      class="text-[10px] font-bold text-slate-300 hover:text-red-500 transition">Sil</button>
            </form>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <?php foreach ([['discussed','💬 Konuşulan'], ['decisions','✅ Kararlar'],
                            ['homework','📚 Ödev'], ['next_step','📌 Sonraki adım']] as [$f, $lbl]):
                if (empty($n[$f])) continue; ?>
            <div class="bg-slate-50 rounded-xl p-3">
              <div class="text-[9px] font-black text-slate-400 uppercase tracking-wider mb-1"><?php echo $lbl; ?></div>
              <div class="text-xs font-medium text-slate-700 whitespace-pre-line"><?php echo htmlspecialchars($n[$f]); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
