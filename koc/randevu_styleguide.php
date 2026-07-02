<?php
// koc/randevu_styleguide.php — ADIM 3: Randevu Yeniden Tasarımı Stil Rehberi (UI Kit)
// Geçici onay/inceleme sayfasıdır; gerçek randevu.php'ye DOKUNMAZ.
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../index.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Randevu UI Kit — DersPROS</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  /* ATLA marka */
  --atla-primary:#223488; --atla-primary-600:#314595; --atla-primary-050:#eef1fb;
  --atla-accent:#ec9731;  --atla-accent-600:#d68625;  --atla-accent-050:#fdf3e7;
  /* Semantik */
  --success:#059669; --success-050:#ecfdf5;
  --warning:#d97706; --warning-050:#fffbeb;
  --error:#dc2626;   --error-050:#fef2f2;
  /* Nötr */
  --surface:#f8fafc; --card:#ffffff;
  --text-1:#1e293b; --text-2:#64748b; --text-3:#94a3b8;
  --border:#e2e8f0;
  /* Gölge */
  --shadow-sm:0 1px 2px 0 rgba(0,0,0,.05);
  --shadow-md:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -2px rgba(0,0,0,.1);
  --shadow-lg:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -4px rgba(0,0,0,.1);
  --shadow-fab:0 10px 20px -3px rgba(34,52,136,.45);
  /* Yarıçap */
  --r-sm:8px; --r-md:12px; --r-lg:16px; --r-xl:24px;
}
body{font-family:'Poppins',sans-serif;background:var(--surface);color:var(--text-1);-webkit-tap-highlight-color:transparent}
.swatch{height:72px;border-radius:var(--r-md);box-shadow:var(--shadow-sm) inset,0 0 0 1px rgba(0,0,0,.04)}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--shadow-sm)}
/* Buton sistemi — hepsi min 44px dokunma alanı */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:44px;padding:0 1.1rem;border-radius:var(--r-md);font-weight:700;font-size:.875rem;transition:all .18s cubic-bezier(.4,0,.2,1);cursor:pointer;border:1px solid transparent}
.btn:active{transform:scale(.97)}
.btn-primary{background:var(--atla-primary);color:#fff;box-shadow:var(--shadow-sm)}
.btn-primary:hover{background:var(--atla-primary-600)}
.btn-accent{background:var(--atla-accent);color:#fff;box-shadow:var(--shadow-sm)}
.btn-accent:hover{background:var(--atla-accent-600)}
.btn-secondary{background:#fff;color:var(--text-1);border-color:var(--border)}
.btn-secondary:hover{background:var(--surface)}
.btn-ghost{background:transparent;color:var(--text-2)}
.btn-ghost:hover{background:var(--surface)}
.btn-danger{background:var(--error-050);color:var(--error);border-color:#fecaca}
.btn-danger:hover{background:var(--error);color:#fff}
.btn:focus-visible{outline:3px solid var(--atla-primary-050);outline-offset:2px}
/* Çipler */
.chip{display:inline-flex;align-items:center;gap:.3rem;font-weight:700;font-size:.68rem;padding:.28rem .6rem;border-radius:999px;border:1px solid;text-transform:uppercase;letter-spacing:.02em}
.chip-pending{background:var(--warning-050);color:var(--warning);border-color:#fde68a}
.chip-confirmed{background:var(--atla-primary-050);color:var(--atla-primary);border-color:#c7d2fe}
.chip-live{background:var(--success-050);color:var(--success);border-color:#a7f3d0}
.chip-done{background:#f1f5f9;color:var(--text-2);border-color:var(--border)}
.chip-cancel{background:var(--error-050);color:var(--error);border-color:#fecaca}
.pulse-dot{width:7px;height:7px;border-radius:999px;background:var(--success);box-shadow:0 0 0 0 rgba(5,150,105,.5);animation:pulse 1.6s infinite}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(5,150,105,.5)}70%{box-shadow:0 0 0 8px rgba(5,150,105,0)}100%{box-shadow:0 0 0 0 rgba(5,150,105,0)}}
/* Randevu kartı sol durum çubuğu */
.appt{background:#fff;border:1px solid var(--border);border-left-width:5px;border-radius:var(--r-lg);box-shadow:var(--shadow-sm);transition:box-shadow .18s,transform .18s}
.appt:hover{box-shadow:var(--shadow-md);transform:translateY(-1px)}
.bar-pending{border-left-color:var(--warning)}
.bar-confirmed{border-left-color:var(--atla-primary)}
.bar-live{border-left-color:var(--success)}
.bar-done{border-left-color:var(--text-3)}
.bar-cancel{border-left-color:var(--error);opacity:.7}
.h-label{font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-3)}
.fab{position:fixed;right:24px;bottom:24px;width:56px;height:56px;border-radius:999px;background:var(--atla-primary);color:#fff;box-shadow:var(--shadow-fab);display:flex;align-items:center;justify-content:center;font-size:1.6rem}
</style>
</head>
<body class="pb-28">
<div class="max-w-5xl mx-auto p-4 md:p-8 space-y-10">

  <!-- Başlık -->
  <header class="card p-6" style="box-shadow:var(--shadow-md)">
    <p class="h-label">DersPROS · Adım 3</p>
    <h1 class="text-2xl md:text-3xl font-extrabold mt-1" style="color:var(--atla-primary)">Randevu Yeniden Tasarımı — Stil Rehberi</h1>
    <p class="text-sm mt-2" style="color:var(--text-2)">ATLA renk paleti · Poppins tipografi · yeniden kullanılabilir bileşen kiti. Bu sayfa gerçek randevu ekranına dokunmaz; yalnızca onay içindir.</p>
  </header>

  <!-- 1. Renk Token'ları -->
  <section class="card p-6">
    <h2 class="text-lg font-extrabold mb-4">1 · Renk Token'ları</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div><div class="swatch" style="background:var(--atla-primary)"></div><p class="text-xs font-bold mt-2">Primary</p><p class="text-[11px]" style="color:var(--text-3)">#223488</p></div>
      <div><div class="swatch" style="background:var(--atla-primary-600)"></div><p class="text-xs font-bold mt-2">Primary Hover</p><p class="text-[11px]" style="color:var(--text-3)">#314595</p></div>
      <div><div class="swatch" style="background:var(--atla-accent)"></div><p class="text-xs font-bold mt-2">Accent</p><p class="text-[11px]" style="color:var(--text-3)">#ec9731</p></div>
      <div><div class="swatch" style="background:var(--atla-accent-600)"></div><p class="text-xs font-bold mt-2">Accent Hover</p><p class="text-[11px]" style="color:var(--text-3)">#d68625</p></div>
      <div><div class="swatch" style="background:var(--success)"></div><p class="text-xs font-bold mt-2">Success</p><p class="text-[11px]" style="color:var(--text-3)">#059669</p></div>
      <div><div class="swatch" style="background:var(--warning)"></div><p class="text-xs font-bold mt-2">Warning</p><p class="text-[11px]" style="color:var(--text-3)">#d97706</p></div>
      <div><div class="swatch" style="background:var(--error)"></div><p class="text-xs font-bold mt-2">Error</p><p class="text-[11px]" style="color:var(--text-3)">#dc2626</p></div>
      <div><div class="swatch" style="background:var(--surface);box-shadow:0 0 0 1px var(--border) inset"></div><p class="text-xs font-bold mt-2">Surface</p><p class="text-[11px]" style="color:var(--text-3)">#f8fafc</p></div>
    </div>
  </section>

  <!-- 2. Tipografi -->
  <section class="card p-6">
    <h2 class="text-lg font-extrabold mb-4">2 · Tipografi (Poppins · akışkan clamp)</h2>
    <div class="space-y-2">
      <p style="font-size:clamp(1.5rem,4vw,2rem);font-weight:800;line-height:1.1">Display · Başlık 32/24px</p>
      <p style="font-size:clamp(1.15rem,3vw,1.5rem);font-weight:700">H1 · Bölüm başlığı 24/18px</p>
      <p style="font-size:1rem;font-weight:600">H2 · Kart başlığı 16px</p>
      <p style="font-size:.875rem;font-weight:500;color:var(--text-1)">Gövde metni 14px — okunabilirlik için taban</p>
      <p style="font-size:.75rem;font-weight:600;color:var(--text-2)">Küçük etiket 12px (minimum — 9-10px kaldırıldı)</p>
      <p class="h-label">MİKRO ETİKET · 11px uppercase</p>
    </div>
  </section>

  <!-- 3. Butonlar -->
  <section class="card p-6">
    <h2 class="text-lg font-extrabold mb-4">3 · Buton Varyantları <span class="text-xs font-medium" style="color:var(--text-3)">(hepsi ≥44px dokunma alanı, klavye odak halkalı)</span></h2>
    <div class="flex flex-wrap gap-3">
      <button class="btn btn-primary">Birincil Eylem</button>
      <button class="btn btn-accent">Vurgu (Yeni)</button>
      <button class="btn btn-secondary">İkincil</button>
      <button class="btn btn-ghost">Ghost</button>
      <button class="btn btn-danger">İptal / Sil</button>
    </div>
  </section>

  <!-- 4. Durum & Ödeme Çipleri -->
  <section class="card p-6">
    <h2 class="text-lg font-extrabold mb-4">4 · Çipler — Randevu Durumu + Ödeme</h2>
    <p class="h-label mb-2">Randevu durumu</p>
    <div class="flex flex-wrap gap-2 mb-5">
      <span class="chip chip-pending">⏳ Bekliyor</span>
      <span class="chip chip-confirmed">✓ Onaylı</span>
      <span class="chip chip-live"><span class="pulse-dot"></span> Devam ediyor</span>
      <span class="chip chip-done">✓ Tamamlandı</span>
      <span class="chip chip-cancel">✕ İptal</span>
    </div>
    <p class="h-label mb-2">Ödeme durumu (randevuya bağlı)</p>
    <div class="flex flex-wrap gap-2">
      <span class="chip chip-done">— Ödeme yok</span>
      <span class="chip chip-pending">💳 Ödeme bekliyor</span>
      <span class="chip chip-live">✅ Ödendi</span>
    </div>
  </section>

  <!-- 5. İstatistik Kartı -->
  <section class="card p-6">
    <h2 class="text-lg font-extrabold mb-4">5 · Akıllı İstatistik Kartı <span class="text-xs font-medium" style="color:var(--text-3)">(tıkla=filtrele, hover=tooltip, count-up)</span></h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <button class="card p-4 text-left hover:shadow-md transition" style="transition:box-shadow .18s">
        <div class="flex items-center justify-between"><span class="h-label">Toplam</span><span style="color:var(--atla-primary)">📅</span></div>
        <div class="text-2xl font-extrabold mt-1" style="color:var(--atla-primary)">12</div>
      </button>
      <button class="card p-4 text-left hover:shadow-md transition" style="transition:box-shadow .18s">
        <div class="flex items-center justify-between"><span class="h-label">Aktif</span><span style="color:var(--success)">✅</span></div>
        <div class="text-2xl font-extrabold mt-1" style="color:var(--success)">9</div>
      </button>
      <button class="card p-4 text-left hover:shadow-md transition" style="transition:box-shadow .18s">
        <div class="flex items-center justify-between"><span class="h-label">İptal</span><span style="color:var(--error)">✕</span></div>
        <div class="text-2xl font-extrabold mt-1" style="color:var(--error)">3</div>
      </button>
      <button class="card p-4 text-left hover:shadow-md transition" style="transition:box-shadow .18s">
        <div class="flex items-center justify-between"><span class="h-label">Süre</span><span style="color:var(--atla-accent)">⏱</span></div>
        <div class="text-2xl font-extrabold mt-1">6.5<span class="text-sm font-bold" style="color:var(--text-3)"> sa</span></div>
      </button>
    </div>
  </section>

  <!-- 6. Randevu Kartı (durum çubuklu) -->
  <section class="card p-6">
    <h2 class="text-lg font-extrabold mb-4">6 · Randevu Kartı — sol renk çubuğu = durum</h2>
    <div class="space-y-3">
      <!-- Onaylı + ödendi -->
      <div class="appt bar-confirmed p-4 flex items-center gap-4">
        <div class="text-center px-3 py-2 rounded-xl shrink-0" style="background:var(--atla-primary-050)">
          <div class="text-lg font-extrabold" style="color:var(--atla-primary)">14:00</div>
          <div class="text-[10px] font-bold" style="color:var(--text-3)">45 dk</div>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-extrabold truncate">Ali Veli</span>
            <span class="chip chip-confirmed">Onaylı</span>
            <span class="chip chip-live">✅ Ödendi</span>
          </div>
          <p class="text-xs mt-1" style="color:var(--text-2)">Matematik · Konu tekrarı</p>
        </div>
        <div class="hidden sm:flex gap-2 shrink-0">
          <button class="btn btn-secondary" style="min-height:38px;padding:0 .8rem">✓ Tamamla</button>
          <button class="btn btn-ghost" style="min-height:38px;padding:0 .7rem">⋯</button>
        </div>
      </div>
      <!-- Devam ediyor (pulse) + ödeme bekliyor -->
      <div class="appt bar-live p-4 flex items-center gap-4">
        <div class="text-center px-3 py-2 rounded-xl shrink-0" style="background:var(--success-050)">
          <div class="text-lg font-extrabold" style="color:var(--success)">15:00</div>
          <div class="text-[10px] font-bold" style="color:var(--text-3)">30 dk</div>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-extrabold truncate">Mehmet Kaya</span>
            <span class="chip chip-live"><span class="pulse-dot"></span> Devam</span>
            <span class="chip chip-pending">💳 Ödeme bekliyor</span>
          </div>
          <p class="text-xs mt-1" style="color:var(--text-2)">Fizik · Deneme çözümü</p>
        </div>
      </div>
      <!-- İptal -->
      <div class="appt bar-cancel p-4 flex items-center gap-4">
        <div class="text-center px-3 py-2 rounded-xl shrink-0" style="background:#f1f5f9">
          <div class="text-lg font-extrabold line-through" style="color:var(--text-3)">16:00</div>
          <div class="text-[10px] font-bold" style="color:var(--text-3)">45 dk</div>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-extrabold truncate line-through" style="color:var(--text-3)">Zeynep Ak</span>
            <span class="chip chip-cancel">✕ İptal</span>
          </div>
        </div>
      </div>
      <!-- Boş saat yönlendirme -->
      <button class="w-full p-4 rounded-2xl border-2 border-dashed text-sm font-bold transition" style="border-color:var(--border);color:var(--text-3)"
        onmouseover="this.style.borderColor='var(--atla-primary)';this.style.color='var(--atla-primary)'"
        onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-3)'">＋ Bu saate randevu ekle</button>
    </div>
  </section>

  <!-- 7. Hafta günü hücresi + yoğunluk -->
  <section class="card p-6">
    <h2 class="text-lg font-extrabold mb-4">7 · Hafta Şeridi — yoğunluk göstergeli</h2>
    <div class="flex gap-2 overflow-x-auto pb-2">
      <?php
        $days = [['Pzt','3',0],['Sal','4',1],['Çar','5',2],['Per','6',3],['Cum','7',0],['Cmt','8',1],['Paz','9',2]];
        foreach($days as $i=>$d){
          [$dn,$dd,$dens]=$d; $sel=($i===3);
          $dot = $dens===0?'#cbd5e1':($dens<=2?'var(--success)':'var(--atla-accent)');
      ?>
      <button class="shrink-0 w-16 h-20 rounded-2xl border flex flex-col items-center justify-center gap-1 transition"
        style="<?= $sel?'background:var(--atla-primary);color:#fff;border-color:var(--atla-primary)':'background:#fff;border-color:var(--border)' ?>">
        <span class="text-[10px] font-extrabold uppercase opacity-80"><?= $dn ?></span>
        <span class="text-lg font-extrabold"><?= $dd ?></span>
        <span class="flex gap-0.5">
          <?php for($k=0;$k<3;$k++): ?>
            <span style="width:5px;height:5px;border-radius:999px;background:<?= $k<$dens ? ($sel?'#fff':$dot) : ($sel?'rgba(255,255,255,.3)':'#e2e8f0') ?>"></span>
          <?php endfor; ?>
        </span>
      </button>
      <?php } ?>
    </div>
    <p class="text-xs mt-2" style="color:var(--text-3)">Nokta yoğunluğu: 0=boş · 1-2=<span style="color:var(--success)">yeşil</span> · 3+=<span style="color:var(--atla-accent)">turuncu</span></p>
  </section>

  <!-- 8. Karne (dairesel + rozet) -->
  <section class="card p-6">
    <h2 class="text-lg font-extrabold mb-4">8 · Karne — dairesel ilerleme + rozet</h2>
    <div class="space-y-3">
      <?php
        $karne=[['Ali Veli',85,'⭐','var(--success)'],['Ayşe Demir',62,'🎯','var(--atla-accent)'],['Can Yıldız',40,'','var(--error)']];
        foreach($karne as $k){ [$nm,$pct,$badge,$col]=$k; $circ=2*3.14159*20; $off=$circ*(1-$pct/100);
      ?>
      <div class="flex items-center gap-4 p-3 rounded-2xl" style="background:var(--surface)">
        <div class="relative shrink-0" style="width:52px;height:52px">
          <svg width="52" height="52" viewBox="0 0 52 52">
            <circle cx="26" cy="26" r="20" fill="none" stroke="#e2e8f0" stroke-width="5"/>
            <circle cx="26" cy="26" r="20" fill="none" stroke="<?= $col ?>" stroke-width="5" stroke-linecap="round"
              stroke-dasharray="<?= $circ ?>" stroke-dashoffset="<?= $off ?>" transform="rotate(-90 26 26)"/>
          </svg>
          <span class="absolute inset-0 flex items-center justify-center text-xs font-extrabold">%<?= $pct ?></span>
        </div>
        <div class="flex-1">
          <p class="font-bold flex items-center gap-1"><?= $nm ?> <span><?= $badge ?></span></p>
          <p class="text-xs" style="color:var(--text-2)">Detay için tıkla →</p>
        </div>
      </div>
      <?php } ?>
    </div>
  </section>

  <!-- 9. Boş durum + toast -->
  <section class="grid sm:grid-cols-2 gap-6">
    <div class="card p-6">
      <h2 class="text-lg font-extrabold mb-4">9 · Boş Durum</h2>
      <div class="text-center py-6">
        <div class="text-5xl mb-3">🗓️</div>
        <p class="font-bold">Planınız tamamen boş</p>
        <p class="text-xs mt-1 mb-4" style="color:var(--text-2)">Yeni randevu için takvimde bir saate tıklayın veya + butonunu kullanın.</p>
        <button class="btn btn-primary mx-auto">＋ İlk Randevuyu Oluştur</button>
      </div>
    </div>
    <div class="card p-6">
      <h2 class="text-lg font-extrabold mb-4">10 · Toast Bildirimleri</h2>
      <div class="space-y-3">
        <div class="flex items-center gap-3 p-3 rounded-xl" style="background:var(--success-050);border:1px solid #a7f3d0"><span>✅</span><span class="text-sm font-semibold" style="color:var(--success)">Randevu oluşturuldu</span></div>
        <div class="flex items-center gap-3 p-3 rounded-xl" style="background:var(--warning-050);border:1px solid #fde68a"><span>⚠️</span><span class="text-sm font-semibold" style="color:var(--warning)">Bu saatte Ali V. ile randevunuz var</span></div>
        <div class="flex items-center gap-3 p-3 rounded-xl" style="background:var(--error-050);border:1px solid #fecaca"><span>⛔</span><span class="text-sm font-semibold" style="color:var(--error)">İşlem kaydedilemedi</span></div>
        <div class="flex items-center gap-3 p-3 rounded-xl" style="background:var(--atla-primary-050);border:1px solid #c7d2fe"><span>💳</span><span class="text-sm font-semibold" style="color:var(--atla-primary)">Ödeme talebi oluşturuldu (₺2.900)</span></div>
      </div>
    </div>
  </section>

  <p class="text-center text-xs" style="color:var(--text-3)">Bu bir onay/inceleme sayfasıdır — gerçek randevu ekranı bu tokenlarla Adım 4'te inşa edilecek.</p>
</div>

<div class="fab" title="Yeni (speed-dial)">＋</div>
</body>
</html>
