# DersPROS — Geliştirme Planı v1 (Seçili Modüller)

> **Durum:** Planlama — kod yazılmadı. Bu belge, üzerinde anlaşılan 4 modül kümesinin
> mimarisini, veri modelini, risklerini ve sprint sırasını tanımlar. Uygulama
> başladığında her sprint bu belgeye referansla ilerler.
>
> **Kapsam (kullanıcının seçtiği):**
> - **A. Zaman Motoru** — pomodoro / kronometre, gerçek çalışma süresi
> - **B. Hedef & Tahmin** — hedef üniversite/net, sıralama tahmini, aralıklı tekrar
> - **C. Koç Otomasyonu** — analiz→aksiyon, risk skoru, kohort planlama, seans notu
> - **D. Oyunlaştırma 2.0** — XP/seviye, eşik-ötesi başarımlar, liderlik
>
> Kapsam DIŞI (şimdilik): yanlış defteri, deneme konu kırılımı, online tahsilat,
> soru bankası, iletişim birleştirme.

---

## 0. Mimari Omurga (tüm modüllerin paylaştığı çekirdek)

Dört küme birbirinden bağımsız görünse de aynı üç çekirdek yapıya dayanır.
**Önce bunlar kurulur; modüller üstüne oturur.**

### 0.1. `student_daily_metrics` — tek doğruluk kaynağı

Bugün `analiz.php`, `student_dashboard.php` ve `cron_notifications.php` aynı
metrikleri (görev tamamlama, soru sayısı, giriş) her sayfa yüklemesinde ağır
sorgularla yeniden hesaplıyor. Risk skoru, XP, liderlik ve raporlar da aynı
metriklere ihtiyaç duyacak → hesap **bir kez, gecede** yapılmalı.

```
student_daily_metrics
  student_id INT, metric_date DATE            → PK (student_id, metric_date)
  tasks_total, tasks_done, tasks_half INT     -- schedule_items özeti
  questions_done, correct, wrong INT          -- amount + correct/wrong_count özeti
  study_minutes INT                           -- A modülünden (study_sessions)
  logged_in TINYINT(1)                        -- click/giriş izi
  last_net FLOAT NULL                         -- o gün deneme girildiyse
  computed_at DATETIME
```

- **Yazan:** yeni `cron_metrics.php` (gece 03:00, mevcut cron düzenine eklenir;
  `cron_runner.bat` + hosting cron zaten var).
- **"Bugün" istisnası:** gün içi ekranlar (koç panosu risk kartı) bugünü canlı
  sorgular, düne kadarını tablodan okur — cron gecikirse sistem yine çalışır.
- **Geri doldurma:** ilk kurulumda son 90 gün tek seferlik backfill.

### 0.2. `task_suggestions` — birleşik öneri kuyruğu  ⭐ yeni öneri

Analiz→aksiyon (C1), aralıklı tekrar (B3) ve risk müdahalesi (C2) üçü de aynı
şeyi üretir: *"şu öğrenciye şu konudan şu görev verilmeli."* Üç ayrı mekanizma
yerine **tek kuyruk**:

```
task_suggestions
  id, student_id, teacher_id
  source ENUM('analiz','tekrar','risk','manuel')
  edu_topic_id INT NULL                       -- education_topics referansı
  action_type ENUM('soru','konu'), amount INT
  reason VARCHAR(255)                         -- "TYT Mat yanlış oranı %38" gibi
  suggested_date DATE NULL
  status ENUM('pending','approved','rejected','expired')
  created_at, decided_at
```

- Koç, planlayıcıda "Öneriler (n)" sekmesinde görür; **onaylanan öneri
  `schedule_items`'a kopyalanır** (`edu_topic_id` zaten oradaki referansla aynı).
- Otomatik hiçbir şey plana *doğrudan* yazılmaz — koçun otoritesi korunur.
  (İleride öğrenci başına "otomatik onay" anahtarı açılabilir.)
- Sel önleme: öğrenci başına günde en çok N (varsayılan 3) yeni öneri; aynı
  konuya önümüzdeki 7 günde plan/öneri varsa üretilmez; 14 gün dokunulmayan
  öneri `expired`.

### 0.3. Kod düzeni, bayraklar, konvansiyonlar

- **Lib deseni:** `education_lib.php` örneği izlenir → `study_lib.php`,
  `goals_lib.php`, `risk_lib.php`, `gamify_lib.php`, `suggest_lib.php`.
  Her lib kendi `*_ensure_schema()` idempotent kurulumunu taşır
  (CREATE TABLE IF NOT EXISTS + SHOW COLUMNS yükseltmesi — mevcut konvansiyon).
- **Feature flag:** `app_settings_lib.php` zaten var → `ff_timer`, `ff_goals`,
  `ff_risk`, `ff_xp`, `ff_league` anahtarları. Her modül bayrakla açılır;
  staging'de açık, prod'da kademeli. Sorunda kod geri alınmadan kapatılır.
- **Charset:** mevcut şemada `latin1` (exams, payments, teacher_profiles…) ile
  `utf8mb4_turkish_ci` karışık. **Tüm yeni tablolar `utf8mb4_turkish_ci`**;
  latin1 tablolara JOIN'lerde collation çakışması riski → karşılaştırmalar
  hep id üzerinden, metin JOIN'i yok.
- **Saat dilimi:** SQL dump `+00:00`, PHP `date()` sunucu saatinde — gün sınırı
  kayması streak'te zaten gizli risk. Yeni kod tek kaynaktan gün hesaplar:
  `date_default_timezone_set('Europe/Istanbul')` (merkezî bootstrap'a) +
  MySQL bağlantısına `SET time_zone='+03:00'`. Metrik günü = İstanbul günü.
- **AJAX:** mevcut `ajax/` klasör düzeni; her modül tek endpoint dosyası
  (`ajax/study_api.php` gibi), action parametreli — `education_api.php` örneği.

---

## A. Zaman Motoru — Pomodoro / Kronometre

### Amaç
"25 dk Matematik çalıştım" beyandan ölçüme dönsün. Öğrenci görevin üstünden
sayaç başlatır; gerçek dakika `study_minutes` olarak birikir, koç ve rapor görür.

### Veri modeli
```
study_sessions
  id, student_id
  schedule_item_id INT NULL     -- görevden başlatıldıysa
  edu_topic_id INT NULL         -- serbest çalışmada konu seçilirse
  mode ENUM('pomodoro','stopwatch')
  status ENUM('active','done','abandoned')
  started_at DATETIME           -- SUNUCU saati; istemci saatine asla güvenilmez
  last_heartbeat DATETIME
  ended_at DATETIME NULL
  duration_sec INT DEFAULT 0    -- net süre (molalar hariç)
  pause_sec INT DEFAULT 0
  source ENUM('timer','manual') -- manuel telafi girişi ayrı damgalanır
```

### Akış
1. Öğrenci görev kartında **▶ Başlat** → `study_api.php?action=start`
   (aktif oturum varsa reddedilir → "başka sekmede/cihazda sayaç açık").
2. İstemci 60 sn'de bir **heartbeat** atar; sunucu `last_heartbeat` günceller
   ve `duration_sec`'i *sunucu zaman farkından* türetir (sekme kısılsa bile doğru).
3. **Bitir** → `done`; görevle bağlıysa görev işaretleme modalı açılır
   (mevcut `openStatusModal` akışına bağlanır — süre otomatik gelir).
4. Pomodoro modu: 25/5 varsayılan (öğrenci ayarı `app_settings` değil, users'a
   iki kolon ya da JSON tercih alanı); mola `pause_sec`'e yazılır.
5. Gece cron: `active` kalıp heartbeat'i 10+ dk eski oturumları `abandoned`
   kapatır, süreyi son heartbeat'e kadar sayar.

### Olası hatalar → çözümler
| Risk | Çözüm |
|---|---|
| Sekme kapanır, oturum `active` kalır | Heartbeat + cron auto-close; yeniden girişte "yarım oturum bulundu, devam/kapat?" diyaloğu |
| İstemci saati oynanır | Tüm süre sunucu timestamp'lerinden; istemci sadece görsel sayaç |
| İki cihaz/sekme aynı anda | DB'de öğrenci başına tek `active` kuralı (kontrol + unique yaklaşım); ikinci başlatma reddedilir |
| Sayacı açık bırakıp gitme | Günlük sayılan tavan (örn. 600 dk); heartbeat'siz süre sayılmaz; tavan üstü koça "anomali" işareti |
| Gece yarısı geçen oturum | Süre **başlangıç gününe** yazılır (v1 basitliği; metrikte kabul edilebilir sapma) |
| PWA arka plan kısıtlaması (iOS) | Heartbeat kaçarsa süre sunucu farkından telafi edilir; iOS'ta "ekran açık tut" ipucu |

### Yeni öneriler
- **Odak modu:** sayaç çalışırken push bildirimleri sunucu tarafında ertelensin
  (cron gönderiminde `active` oturum kontrolü — mevcut push hattına 1 koşul).
- **Manuel telafi girişi** (`source='manual'`): dürüstlük için ayrı renkte
  gösterilir, XP'de daha az puan (D ile kesişim).

---

## B. Hedef & Tahmin Motoru

### B1. Öğrenci hedefi (üniversite / bölüm / net)

```
student_goals
  id, student_id
  exam_category ENUM('TYT','AYT','LGS')
  target_university VARCHAR(150) NULL
  target_department VARCHAR(150) NULL
  target_net DECIMAL(6,2) NULL
  target_rank INT NULL
  set_by ENUM('student','teacher')     -- kim koydu görünür olsun
  is_active TINYINT(1)                 -- tek aktif hedef/kategori
  created_at, updated_at
```

- Öğrenci profilden girer; koç `ogrencilerim.php`'den görebilir/düzenler.
- Ana sayfa kartı: mevcut `exam_dates()` geri sayımıyla birleşir →
  **"YKS'ye 214 gün · hedef 95 net · son ort. 78,5 → fark 16,5"** tek kart. ⭐
- Üniversite/bölüm v1'de **serbest metin** (YÖK Atlas entegrasyonu v2 —
  başta liste bakımı yükü almayalım).

### B2. Sıralama / net tahmini

```
rank_reference           -- admin bakımlı dönüşüm noktaları
  id, year, exam_category, net_value DECIMAL(6,2), rank_estimate INT
```

- **Hesap iki adım:** (1) net projeksiyonu: son 5 denemenin ağırlıklı
  ortalaması + doğrusal eğim (basit regresyon, `quiz_results`ten);
  (2) net → sıralama: `rank_reference` noktaları arasında lineer interpolasyon.
- **Admin ekranı** (mevcut `admin/sinav_tarihleri.php` benzeri basit CRUD):
  yıllık ÖSYM/LGS verisi elle girilir; veri yılı ekranda gösterilir
  ("2025 verisine göre **tahmini**").

**Hatalar → çözümler:**
| Risk | Çözüm |
|---|---|
| 1–2 denemeyle saçma tahmin | Eşik: en az 3 deneme, yoksa "X deneme daha gir" boş durumu |
| Kötü tahmin moral bozar | Nokta değil **aralık** göster (±%10 bant); koça öğrenci başına "tahmini gizle" anahtarı |
| Referans verisi bayatlar | Kayıt yılı damgası + 12 aydan eskiyse "güncel değil" rozeti; veri yoksa modül sadece net trendi gösterir (zarif düşüş) |
| TYT/AYT karışımı | Tahmin kategori başına ayrı; birleşik puan hesabı (katsayılar) v2 |

### B3. Aralıklı tekrar (spaced repetition)

- **v1 kural bazlı, algoritma değil:** bir konu `yapildi` olduğunda
  3 / 7 / 21 gün sonrası için `task_suggestions(source='tekrar')` üretilir.
- Doz, performansa bağlanır: görevde `wrong/(correct+wrong)` ≥ %40 ise
  aralıklar sıklaşır (2/5/10), %10 altındaysa seyrekleşir (7/21).
- Üretim yeri: gece cron (`cron_metrics.php` içinde ikinci faz) — sayfa
  yükünde hesap yok.
- **Koç onayından geçer** (0.2'deki kuyruk kuralları: günlük tavan, çakışma
  kontrolü, otomatik expire). SM-2 benzeri gerçek algoritma v2 — v1'de
  öğretmen güveni kazanmak öncelik.

---

## C. Koç Otomasyon Motoru

### C1. Analiz → Aksiyon ("tek tık görev")

- `analiz.php` zaten içgörü üretiyor (`$insights`: zayıf ders, yanlış oranı,
  düşük kapsama). Her içgörü kartına **"Görev öner"** butonu →
  `task_suggestions(source='analiz')` kaydı; planlayıcıda onay.
- İçgörü → öneri eşlemesi kural tablosuyla kodda tutulur (örn. "yanlış oranı
  yüksek ders" → o dersin en zayıf 2 konusuna 20'şer soru).
- **Risk:** içgörü metni ile öneri içeriği tutarsız düşerse güven kaybı →
  öneri `reason` alanına içgörünün kendisi yazılır, koç ne-neden görür.

### C2. Risk skoru + erken uyarı

```
risk_scores
  student_id, score_date DATE   → PK
  score TINYINT (0-100)
  level ENUM('green','yellow','red','gray')   -- gray = veri yetersiz
  components JSON               -- {"tamamlama":38,"seri":0,"giris":60,"net_egim":-1.2,...}
```

- **Girdi:** son 14 günün `student_daily_metrics`'i + streak durumu + net eğimi.
- **Formül v1 (ağırlıklı, basit ve açıklanabilir):**
  tamamlama %40 · giriş düzeni %20 · seri %15 · net eğimi %15 · çalışma dk %10.
  Her bileşen 0–100 normalize; katsayılar `app_settings`'te (ayar ekranı
  gerekmeden ayarlanabilir).
- **Kararlılık:** 3 günlük üstel yumuşatma (EMA) + histerezis
  (kırmızıya giriş <40, çıkış >50) → gün aşırı renk zıplamaz.
- **Soğuk başlangıç:** <7 gün verisi olan öğrenci `gray` ("veri toplanıyor") —
  yeni öğrenciye asla kırmızı yakılmaz.
- **Görevsiz günler:** `tasks_total=0` olan gün tamamlama bileşeninden
  **düşülmez** (koç plan yapmadıysa öğrenci cezalandırılmaz — mevcut streak
  mantığıyla aynı ilke).
- **Yüzeyler:** koç panosunda "Riskli öğrenciler" kartı (kırmızı+sarı, skor
  bileşenleriyle); `cron_notifications.php`'ye yeni tip **`T_RISK`**
  (mevcut `T_LOGIN`/`T_TASKS` desenine birebir eklenir, koçun bildirim
  ayar ekranı bunu otomatik kapsar).

### C3. Kohort / etiket + toplu plan

```
student_tags:     id, teacher_id, name VARCHAR(50), color VARCHAR(7)
student_tag_map:  tag_id, student_id   → PK (tag_id, student_id)
```

- Etiket serbest (örn. "12-Sayısal", "Sabah grubu", "Riskli"); risk seviyesi
  **dinamik sanal etiket** olarak da filtrede görünür (tabloya yazılmaz). ⭐
- **Toplu plan:** planlayıcıdaki mevcut hafta şablonu mekanizmasına
  "etikete uygula" seçeneği. Akış: şablon seç → etiket seç → **önizleme
  ekranı** (öğrenci başına: eklenecek N görev, çakışan M gün) → onay.
- **Yazım güvenliği:** tek transaction; her eklenen `schedule_items` satırına
  ortak `bulk_id` (randevulardaki `group_id`/`grp_` deseni aynen) →
  **"toplu işlemi geri al"** tek sorguyla mümkün.
- **Çakışma stratejisi** koç seçer: "dolu güne ekleme" (varsayılan) /
  "yine de ekle". Var olan görev asla silinmez/ezilmez.

### C4. Yapılandırılmış seans notu

```
session_notes
  id, teacher_id, student_id
  appointment_id INT NULL          -- randevuya bağlanabilir, zorunlu değil
  note_date DATE
  discussed TEXT      -- ne konuşuldu
  decisions TEXT      -- ne kararlaştırıldı
  homework TEXT       -- seans sonrası ödev
  next_step VARCHAR(255)  -- bir sonraki seansın gündemi
  visibility ENUM('private','student','parent') DEFAULT 'private'
  created_at, updated_at
```

- Öğrenci profilinde **zaman çizgisi** görünümü; yeni not formu bir önceki
  notun `next_step`'ini otomatik gündem olarak açar → süreklilik kendiliğinden.
- `visibility='student'` notlar öğrenci ana sayfasında **"Koçundan"** kartı
  olarak görünür ⭐ (Ö-K kopukluğuna doğrudan ilaç, sıfır ekstra maliyet).
- **Gizlilik riski:** varsayılan `private`; görünürlük yükseltme açık onayla
  (buton metni: "Öğrenci de görsün"). Veli görünürlüğü ancak
  `parent_relationships` kaydı varsa etkili.

---

## D. Oyunlaştırma 2.0 — XP, Başarımlar, Liderlik

### D1. XP / seviye

```
xp_events
  id, student_id, event_type VARCHAR(40), xp SMALLINT
  ref_type VARCHAR(20) NULL, ref_id INT NULL
  UNIQUE (student_id, event_type, ref_type, ref_id)   -- idempotency anahtarı
  created_at
users: + xp_total INT, + level SMALLINT (önbellek; kaynak xp_events)
```

- **Olay kataloğu (v1):** görev tamamlama +10 · günü %100 bitirme +25 ·
  deneme girişi +30 · pomodoro seansı (≥20 dk) +15 · seri kilometre taşı +50 ·
  tekrar önerisini tamamlama +20 (B3 ile kesişim). Manuel süre girişi (+5,
  timer'ın 1/3'ü — dürüstlük teşviki).
- **Idempotency:** UNIQUE anahtar sayesinde görev `yapildi→bekliyor→yapildi`
  gezinse bile XP bir kez yazılır; geri alınan görevde XP **geri alınmaz**
  (negatif XP moral/istismar dengesinde daha riskli — v1 kararı).
- **Enflasyon önleme:** günlük XP tavanı (örn. 300); soru XP'si
  `target_amount` üstünü saymaz; anomali (tavana sürekli çarpan) koç
  panosunda işaretlenir.
- **Seviye eğrisi:** `xp(level) = 100 · level^1.5` gibi düz formül — tablo
  bakımı yok.
- **Geçmiş veri:** retroaktif backfill YOK; herkes "Sezon 1"den başlar ⭐
  (adil, basit, eski verinin XP'ye çevrim tartışmasını tamamen keser).

### D2. Eşik-ötesi başarımlar

```
achievements:         id, code VARCHAR(40) UNIQUE, name, tier TINYINT, criteria JSON, is_active
student_achievements: student_id, achievement_id, earned_at  → PK (student_id, achievement_id)
```

- Mevcut `$tierBadge` eşikleri (Soru 100/1K/5K/10K…) **tabloya taşınır** —
  tek kaynak; dashboard aynı görseli tablodan okur (davranış değişmez).
- Yeni başarım sınıfları: davranışsal ("3 gün üst üste pomodoro", "hafta
  sonu çalışması"), performans ("bir derste yanlış oranını %20 altına
  indirme"), süreç ("ilk hedef belirleme", "10 tekrar görevi tamamlama").
- Değerlendirme **gece cron'da** (metrics tablosundan) — sayfa yükünde
  kriter taraması yok; kazanım anında push (mevcut hat).

### D3. Liderlik (lig)

```
league_weeks:   id, week_start DATE, teacher_id, created_at
league_members: league_week_id, student_id, xp_week INT, rank_final TINYINT NULL
users: + league_optin TINYINT(1) DEFAULT 0, + nickname VARCHAR(30) NULL
```

- **Kapsam: koç-içi lig.** Yalnız aynı koçun opt-in öğrencileri; platform
  geneli lig YOK (v1) — hem KVKK hem motivasyon açısından güvenli.
- **KVKK/mahremiyet (kritik — kullanıcılar çoğu reşit değil):**
  varsayılan **kapalı (opt-in)**; listede gerçek ad değil **rumuz**;
  veli paneli çocuğun katılımını görebilir; kayıt formuna dokunulmaz,
  profilden açılır.
- **Hafta döngüsü:** Pazartesi 00:00 (İstanbul) yeni hafta; skor = o haftanın
  `xp_events` toplamı (gece cron günceller + gün içi canlı toplama hafif).
- **Alt sıra moral riski:** v1'de küme düşme/yükselme YOK; ilk 3 vurgulanır,
  alt yarı "sıralama" yerine **yüzdelik dilim** görür ("ilk %60'tasın").
  Opt-out edenlere alternatif: **"kendine karşı" kartı** — geçen haftanın
  XP'siyle bu haftayı kıyaslar ⭐ (rekabet istemeyen öğrenci de döngüde kalır).
- **Hile:** XP tavanı + anomali işareti (D1) ligi de temizler; koç, kendi
  ligindeki anomaliyi görür.

### XP ↔ mevcut sistem köprüsü ⭐ yeni öneri
Kalkan (freeze) bugün yalnız 7 günlük seriyle kazanılıyor. **XP dükkânı v1:
tek ürün — 500 XP = 1 kalkan** (`freeze_count`'a +1, `xp_events`'e negatif
kayıt `event_type='spend_freeze'`). Mevcut seri onarım mekanizması hiç
değişmeden XP'ye anlam kazandırır. (Dükkânın genişlemesi v2.)

---

## E. Sprint Planı ve Bağımlılıklar

```
S0 ─→ S1 ─→ S2 ─→ S5      S0: temel, her şeyin önkoşulu
 └──→ S3        └→ S6      S2 kuyruğu kurar; S5 (XP) S1'in süresine,
 └──→ S4 ─→ S5 ─→ S6      S6 (lig) S5'in XP'sine dayanır
```

| Sprint | İçerik | Bağımlı | Boyut |
|---|---|---|---|
| **S0** | Omurga: `cron_metrics.php`, `student_daily_metrics` + 90g backfill, timezone sabitleme, feature flag anahtarları | — | S |
| **S1** | Zaman Motoru: `study_sessions`, timer UI, heartbeat, cron kapatma, koç görünümü | S0 | M |
| **S2** | Öneri kuyruğu + Analiz→Aksiyon: `task_suggestions`, analiz butonları, planlayıcı "Öneriler" sekmesi | S0 | M |
| **S3** | Risk skoru: `risk_scores`, formül+EMA, koç panosu kartı, `T_RISK` push | S0 | M |
| **S4** | Hedef & tahmin: `student_goals`, `rank_reference` + admin CRUD, hedef kartı, tahmin bandı | S0 | M |
| **S5** | Aralıklı tekrar: cron üretimi → kuyruk (S2'nin üstüne ince katman) | S2 | S |
| **S6** | Kohort: etiketler, toplu plan + önizleme + `bulk_id` geri alma | — (S0 sonrası herhangi bir an) | M |
| **S7** | Seans notu: `session_notes`, zaman çizgisi, "Koçundan" kartı | — | S |
| **S8** | XP/başarımlar: `xp_events`, katalog, tavanlar, rozet tablosu göçü, kalkan dükkânı | S1 (süre XP'si için) | L |
| **S9** | Liderlik ligi: opt-in+rumuz, haftalık döngü, "kendine karşı" kartı | S8 | M |

**Önerilen değer sırası:** S0 → S3 (risk skoru en hızlı koç değeri) → S2 →
S1 → S4 → S5 → S7 → S6 → S8 → S9. Oyunlaştırma bilinçli olarak sona: altındaki
ölçüm (S1) ve metrik (S0) sağlam olmadan XP dağıtmak istismara açık olur.

### Yatay riskler (tüm sprintler)
| Risk | Çözüm |
|---|---|
| Prod'da sayfa-içi otomigrasyon yarışı (aynı anda 2 istek ALTER dener) | `*_ensure_schema()` yalnız cron + admin sayfasında koşar; kullanıcı sayfaları şemayı hazır varsayar |
| FTP deploy yarım dosya bırakır | Yeni modüller flag kapalıyken deploy edilir; flag son adımda açılır |
| Cron aksarsa modüller kilitlenir | Her ekran "düne kadar tablodan, bugün canlı" ilkesiyle çalışır; cron watchdog: `app_settings.last_metrics_run` 26 saati aşarsa admin uyarısı |
| Kadran kayması (streak İstanbul, metrik UTC) | S0'daki timezone sabitlemesi streak koduna da uygulanır — tek gün tanımı |

---

## F. Karar Günlüğü (uygulama sırasında plandan sapmalar)

Tüm sprintler (S0–S9) uygulandı. Kod incelemesi + `php -l` ile doğrulandı;
uçtan uca test staging'de yapılacak. Tüm modüller **bayrak kapalı** doğar —
`admin/features.php`'den açılır.

| # | Karar | Neden |
|---|-------|-------|
| 1 | S2 onay ekranı planlayıcı sekmesi yerine ayrı sayfa (`koc/oneriler.php`) | 65KB'lık planlayıcı JS'ine müdahale riski alınmadı; sayfa + menü linki aynı işi görüyor |
| 2 | `task_suggestions`'a `custom_subject/custom_topic` kolonları eklendi | Eski müfredat/manuel görevlerden türeyen öneriler de taşınabilsin |
| 3 | S6 "önizleme ekranı" yerine mevcut modal onayı + `bulk_id` geri alma | `plan_apply_multi` zaten ekleme-yalnız/ezme-yok çalışıyor; geri alınabilirlik asıl güvenceyi veriyor |
| 4 | S8 başarım kataloğu DB tablosu yerine PHP dizisi | criteria JSON motoru v2; kazanımlar yine DB'de (`student_achievements`) |
| 5 | Mevcut `$tierBadge` rozetleri tabloya taşınmadı | Çalışan kod korundu; XP kartı ayrı yaşıyor, çakışma yok |
| 6 | S9 `league_weeks/league_members` tabloları yok — canlı hesap | Koç başına tek SUM sorgusu yeterli; sezon arşivi v2 |
| 7 | Risk hesabı bayraktan bağımsız çalışır (görünürlük/push bayraklı) | Bayrak açıldığında geçmiş skor birikmiş olur — soğuk başlangıç kısalır |
| 8 | S4 "koça tahmini gizle" kolonu (`show_prediction`) hazır, koç UI'ı yok | Polish turu; varsayılan görünür |
| 9 | Zaman motoru manuel giriş API'si var, öğrenci UI'ı yok | Kapsam kontrolü; `study_api.php?action=manual` hazır |

## G. Staging Test Kontrol Listesi

1. `admin/features.php` → önce **Metrikleri şimdi hesapla** (90 gün backfill).
2. Bayrakları sırayla aç: risk → suggest → timer → goals → xp → league.
3. Risk: koç panosunda kart; `cron_notifications` logunda "Risk skorları".
4. Öneri: analiz sekmesinde "Görev öner" → `koc/oneriler.php`'de onayla →
   öğrenci programında görev.
5. Sayaç: öğrenci görev kartında ▶ → widget → Bitir → görev modalı açılır;
   sekme kapat/aç → kaldığı yerden devam.
6. Hedef: `admin/rank_reference.php`'ye 2+ nokta gir → öğrenci kartında bant.
7. XP: gece cron sonrası (veya ertesi gün) kart dolar; 500 XP'de kalkan al.
8. Lig: 2+ öğrenciyle rumuzlu katıl; sıralama ve "kendine karşı" satırı.
9. Toplu plan: planlayıcı → Başka Öğrencilere Uygula → etiket çipi →
   `koc/etiketler.php`'de Geri Al.

## H. Bu Konuşmanın Hafıza Yapısı

1. **Bu belge** (`docs/plan-v1-secili-moduller.md`) — mimarinin tek kaynağı;
   her sprint öncesi güncellenir (karar değişirse "Karar Günlüğü" bölümü açılır).
2. **Oturum görev listesi** — S0–S9 sprint'leri bağımlılıklarıyla task
   tracker'da; ilerleme oradan izlenir.
3. Uygulamaya geçildiğinde her sprint kendi branch/commit dizisinde bu
   belgedeki bölüm numarasına atıf verir (örn. "S3 / C2 risk skoru").
