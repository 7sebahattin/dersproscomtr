# DersPROS — Öğrenci & Öğretmen Eksik Analizi ve Geliştirme Rotası

> Bu belge, mevcut kod tabanı (PHP + MariaDB, PWA) incelenerek hazırlanmıştır.
> Amaç: öğrenci ve öğretmen (koç) tarafındaki eksikleri **hem eğitim koçu hem de
> öğrenci gözünden** ortaya koymak ve önceliklendirilmiş bir geliştirme rotası çizmek.
> Tarih: 2026-07 · Kapsam: `student/`, `koc/`, `education_*`, `cron_notifications.php`,
> `sql/derspros_db.sql` ve ilişkili paneller.

---

## 0. Yönetici Özeti

DersPROS bugün **koç-merkezli, disiplin/takip odaklı** güçlü bir platform: haftalık
planlayıcı (drag-drop + şablon), faz'lı müfredat kapsama analizi, faz matrisi, şimşek
serisi (streak), randevu + mesajlaşma, ödeme takibi, push bildirim otomasyonu ve veli
paneli mevcut. Temel iş akışı — *koç plan yapar → öğrenci uygular → sistem takip eder* —
sağlam kurulmuş.

Eksikler tek bir cümlede: **veri toplanıyor ama "öğrenmeye" dönüştürülmüyor.** Deneme
netleri giriliyor fakat zayıf konuya bağlanmıyor; görevler işaretleniyor fakat
öğrencinin kendi hedefi/iç motivasyonu beslenmiyor; koç "kim geride" görüyor fakat
"ne yapmalı"yı elle kuruyor. Yol haritasının ekseni bu boşluğu kapatmak olmalı:
**ölç → teşhis et → otomatik aksiyona çevir → motive et.**

Bu belge iki ana bölümden oluşur (Öğrenci, Öğretmen/Koç). Her bölüm eksikleri **iki
mercekten** verir ve sonunda birleşik, fazlara ayrılmış bir yol haritası sunar.

---

## 1. Mevcut Durum (Analiz Zemini)

Eksikleri "zaten var olanı tekrar önermeden" tespit edebilmek için mevcut yetenekler:

**Öğrenci tarafı** (`student/`, `student_dashboard.php`)
- Ana sayfa: bugünün planı, haftalık hedefler, sıradaki randevular
- Şimşek serisi (streak): kalkan (freeze), seri onarım, 7 günde 1 kalkan
- Program/koçluk: koçun atadığı görevleri işaretleme (`yapildi/yarim/yapilmadi`)
- Deneme girişi: TYT/AYT, **ders bazlı** net + Chart.js grafik
- Rapor: günlük/haftalık/aylık tamamlama
- Randevu: görüntüleme + erteleme/iptal talebi + randevu içi mesajlaşma
- Kaynaklar: konuya bağlı kitap/PDF; PWA + push bildirim

**Öğretmen/Koç tarafı** (`koc/`, `teacher_dashboard.php`)
- Planlayıcı (`planlayici.php`): haftalık drag-drop, şablon kaydetme, kaynak seçimi
- Analiz (`analiz.php`): faz'lı müfredat kapsama, yanlış oranı en yüksek ders,
  otomatik "öne çıkanlar/öneriler" motoru
- Faz Matrisi (`faz_matrisi.php`): tüm öğrenciler × dersler ızgarası, kim geride
- Konu eşleştir (`konu_eslestir.php`): görevleri müfredat konusuna bağlama
- Öğrencilerim: öğrenci profili/CRUD, ders fiyatı
- Randevu: tekrarlı + grup randevu; Ödemeler: seans başı ücret, otomatik borç
- Müfredat v2 (`education_lib.php`): faz + sahiplik/kilit modeli, kaynak havuzu
- Bildirim ayarları: öğrenci bazlı özelleştirilebilir push metinleri

**Ortak altyapı**
- `cron_notifications.php`: sabah planı, gün-içi hatırlatma, tamamlama, koç özeti,
  **haftalık veliye gelişim özeti**
- Ödeme otomasyonu, yedekleme cron'u, canlı sohbet (`live_chat_messages`), WhatsApp

**Doğrulanan boşluklar (kodda hiç geçmiyor):** yanlış defteri, rozet/başarım sistemi
(yalnız streak var), sıralama/net tahmini, çalışma süresi/pomodoro, öğrencinin kendi
hedef üniversite/net'i, aralıklı tekrar, liderlik tablosu, online ödeme tahsilatı.

---

## 2. ÖĞRENCİ — Eksik Analizi

### 2.1. Öğrenci Gözünden (öğrenci uygularken ne eksik hissediyor?)

| # | Eksik | Bugünkü durum | Etki |
|---|-------|---------------|------|
| Ö1 | **Deneme yalnızca "net" olarak yaşıyor** | `exam_results`/`quiz_results` ders bazlı net tutuyor; hangi konudan kaç yanlış bilinmiyor | Öğrenci "nereden battım?" sorusuna cevap alamıyor |
| Ö2 | **Yanlış defteri yok** | Yanlış soruyu kaydetme/etiketleme/tekrar etme akışı yok | Türkiye'de en çok kullanılan çalışma aracı eksik |
| Ö3 | **Program tamamen koça bağımlı** | Öğrenci yalnız işaretliyor; kendi görevini ekleyemiyor/serbest çalışma giremiyor | Pasif deneyim, öz-düzenleme gelişmiyor |
| Ö4 | **Çalışma süresi/odak aracı yok** | Koç "dk konu çalışması" atıyor ama gerçek süre ölçülmüyor; pomodoro/kronometre yok | Net çalışma saati bilinmiyor; odak desteklenmiyor |
| Ö5 | **Motivasyon tek ayaklı** | Sadece şimşek serisi var; rozet/başarım/XP/seviye yok | Seri bozulunca motivasyon çöküyor; başka kanca yok |
| Ö6 | **Kendi hedefi yok** | Haftalık hedef koç tarafından belirleniyor; hedef üniversite/bölüm/net yok | "Neden çalışıyorum" görselleşmiyor; bağlılık zayıf |
| Ö7 | **İlerleme/tahmin görselleşmiyor** | Net trendi sınırlı; sıralama tahmini yok | Öğrenci "yolun neresindeyim" hissini alamıyor |
| Ö8 | **Aralıklı tekrar yok** | Faz sistemi çok turlu ama zamanlanmış (spaced) tekrar değil | Konular unutulma eğrisine göre planlanmıyor |
| Ö9 | **İletişim dağınık** | Randevuya bağlı mesaj + basit canlı sohbet; birleşik gelen kutusu/dosya paylaşımı yok | Soru sorma/soru gönderme sürtünmeli |

### 2.2. Eğitim Koçu Gözünden (koç, öğrencide neyin eksik kaldığını görür?)

- **K→Ö1: Teşhis-tedavi kopuk.** Koç `analiz.php`'de "X dersinde yanlış %42" görüyor ama
  bunu öğrencinin planına **tek tıkla remedial göreve** çeviremiyor. Öğrenci de bu bağı
  hiç görmüyor.
- **K→Ö2: Öz-raporlama zayıf.** Öğrenci "yaptım" diyor ama *nasıl* yaptığı (süre, zorluk,
  kendi notu) veri olarak gelmiyor; koç kör noktada kalıyor.
- **K→Ö3: Erken uyarı yok.** Düşen net + bozulan seri + kaçan görev bir arada "risk"
  sinyaline dönüşmüyor; koç sorunu geç fark ediyor.
- **K→Ö4: Veli görünürlüğü sığ.** Yalnız haftalık özet push var; velinin öğrencinin
  gerçek ilerlemesini derinlemesine gördüğü bir pano yok.

### 2.3. Öğrenci Geliştirme Rotası

1. **Deneme → Konu köprüsü (Ö1, K→Ö1)** — Deneme girişine soru/konu kırılımı ekle;
   yanlışları `education_topics`'e bağla. "Bu denemede en çok Paragraf ve Türev'den
   kaybettin" çıktısı + koça "zayıf konulara görev oluştur" butonu.
2. **Yanlış Defteri (Ö2)** — Yeni tablo `mistake_notes` (student_id, topic_id, soru
   görseli/metin, sebep etiketi, çözüldü mü). Denemeden ve serbest çalışmadan besleniyor.
3. **Öz-çalışma girişi + serbest görev (Ö3)** — Öğrenci kendi görevini ekleyip
   işaretleyebilsin (koç onayı opsiyonel); `schedule_items`'a `source` alanı.
4. **Odak/pomodoro + gerçek süre (Ö4)** — Görev başlat/bitir ile `study_minutes` ölç;
   günlük/haftalık net çalışma süresi öğrenci ve koçta görünsün.
5. **Rozet/başarım + hedef (Ö5, Ö6)** — Streak'e ek başarımlar (ilk deneme, 100 soru,
   konu bitirme); öğrenci hedef üniversite/bölüm/net girsin, ilerleme çubuğu.
6. **Sıralama/net tahmini + trend (Ö7)** — Netten yaklaşık sıralama tahmini (yıllık
   katsayı tablosu ile), zaman içi net grafiği ve hedefe uzaklık.
7. **Aralıklı tekrar önerisi (Ö8)** — Bitmiş konuya 3/7/21 gün sonra "tekrar" görevi
   önerisi (koç onaylı).

---

## 3. ÖĞRETMEN / KOÇ — Eksik Analizi

### 3.1. Eğitim Koçu Gözünden (koç, kendi araç setinde ne eksik buluyor?)

| # | Eksik | Bugünkü durum | Etki |
|---|-------|---------------|------|
| K1 | **Analiz → Aksiyon otomasyonu yok** | Zayıf ders/konu tespiti var ama plana dönüşü elle | Planlama emek-yoğun; içgörü aksiyona geç dönüyor |
| K2 | **Erken uyarı / risk skoru yok** | `faz_matrisi` "kim geride"yi gösterir; günlük giriş özeti var ama trend bazlı risk yok | Düşüşteki öğrenci geç fark ediliyor |
| K3 | **Toplu/kohort planlama yok** | Şablon var ama öğrenci-bazlı; bir plan aynı anda gruba atanamıyor | Çok öğrencide zaman kaybı |
| K4 | **Yapılandırılmış seans/görüşme notu yok** | Randevuda `private_note` var ama zaman çizgili görüşme kaydı yok | Öğrenci geçmişi dağınık; devir/takip zor |
| K5 | **Hedef yönetimi yok** | Öğrenci için hedef üniversite/net belirleyip trajektori izlenemiyor | Koçluk "yön" veremiyor, günlük takibe sıkışıyor |
| K6 | **İleri analitik & rapor eksik** | Net trend tahmini, kohort/benchmark karşılaştırması, süre analizi, PDF rapor kısmi | Veli/öğrenciye profesyonel çıktı üretmek zor |
| K7 | **Öğrenci segmentasyonu/etiketleme yok** | Sınıf/alan var ama etiketle grupla-aksiyon al akışı yok | Kitlesel iletişim/plan hedeflemesi zayıf |
| K8 | **Online ödeme tahsilatı yok** | Borç/ödeme *takibi* var; iyzico/PayTR gibi tahsilat yok | Nakit akışı elle; iş yükü + gecikme |
| K9 | **Soru bankası/otomatik ödev yok** | Yüklenen deneme/PDF var; konu bazlı soru bankası + oto-değerlendirme yok | İçerik üretimi ölçeklenmiyor |
| K10 | **Mobil koç akışı olgunlaşıyor** | Git geçmişi çok sayıda mobil hotfix içeriyor | Koç sahada mobilde sürtünme yaşıyor |

### 3.2. Öğrenci Gözünden (öğrenci, koçun araçlarında neyin eksik olmasından etkileniyor?)

- **Ö→K1: Kişiselleşmeyen plan.** Koç toplu/otomatik plan yapamadığında, plan ya
  gecikiyor ya tektipleşiyor; öğrenci "bana özel değil" hissediyor.
- **Ö→K2: Geç müdahale.** Risk skoru olmadığı için öğrenci ancak belirgin şekilde
  düştükten sonra fark ediliyor; erken toparlama şansı kaçıyor.
- **Ö→K3: Kopuk geri bildirim.** Yapılandırılmış görüşme notu olmadığından, bir
  seansta konuşulan hedef sonraki hafta takip edilmiyor; süreklilik hissi zayıf.
- **Ö→K4: Sığ ilerleme hikâyesi.** Hedef yönetimi ve trend tahmini olmayınca öğrenci
  "çabam nereye gidiyor" cevabını koçtan da alamıyor.

### 3.3. Koç Geliştirme Rotası

1. **Analizden tek-tık plan (K1, Ö→K1)** — `analiz.php`'deki her "zayıf konu/ders"
   içgörüsüne "haftaya görev ekle" aksiyonu; deneme-yanlış köprüsünden beslenir.
2. **Risk skoru + erken uyarı (K2, Ö→K2)** — Öğrenci başına skor: net trendi + seri
   durumu + görev tamamlama + giriş sıklığı. Koç panosunda "riskli öğrenciler" listesi;
   eşik aşımında push.
3. **Kohort/grup planlama & etiketleme (K3, K7)** — Etiket/segment (sınıf, alan, risk);
   şablonu seçili gruba toplu ata.
4. **Yapılandırılmış seans notu (K4, Ö→K3)** — Öğrenci başına zaman çizgili görüşme
   kaydı: konu, karar, seans-sonrası ödev, bir sonraki adım.
5. **Hedef & trajektori yönetimi (K5, Ö→K4)** — Öğrenci için hedef net/sıralama/bölüm;
   gerçek trende karşı "hedefe uzaklık" grafiği.
6. **İleri rapor & PDF (K6)** — Veli/öğrenci için tek tık PDF gelişim raporu; kohort
   karşılaştırması ve süre analizi.
7. **Online tahsilat (K8)** — iyzico/PayTR entegrasyonu; ödeme linki + otomatik makbuz.
8. **Soru bankası + oto-ödev (K9)** — Konu bazlı soru havuzu, otomatik değerlendirilen
   ödev; yanlışlar doğrudan öğrencinin yanlış defterine.

---

## 4. Birleşik Yol Haritası (Önceliklendirilmiş)

Öncelik = **etki ÷ efor**. "Ölç → teşhis → aksiyon → motive" ekseninde sıralandı.

### Faz 1 — Öğrenme Döngüsünü Kapat (0–6 hafta) · En yüksek etki

| Madde | Kim için | Etki | Efor |
|-------|----------|------|------|
| Deneme → konu kırılımı + zayıf konu köprüsü | Öğrenci + Koç | ⭐⭐⭐ | Orta |
| Analizden "tek-tık görev oluştur" | Koç | ⭐⭐⭐ | Düşük–Orta |
| Yanlış defteri (denemeden beslenen) | Öğrenci | ⭐⭐⭐ | Orta |
| Risk skoru v1 (net+seri+tamamlama+giriş) | Koç | ⭐⭐⭐ | Orta |

> Faz 1, mevcut verinin (net, görev, seri) üstüne oturur; yeni büyük altyapı gerekmez.
> "Veri toplanıyor ama öğrenmeye dönüşmüyor" boşluğunu doğrudan kapatır.

### Faz 2 — Motivasyon & Kişiselleşme (6–12 hafta)

| Madde | Kim için | Etki | Efor |
|-------|----------|------|------|
| Rozet/başarım + öğrenci hedef (üniversite/net) | Öğrenci | ⭐⭐⭐ | Orta |
| Sıralama/net tahmini + net trend grafiği | Öğrenci + Koç | ⭐⭐ | Orta |
| Öz-çalışma girişi + serbest görev | Öğrenci | ⭐⭐ | Düşük |
| Çalışma süresi/pomodoro + net saat | Öğrenci + Koç | ⭐⭐ | Orta |
| Yapılandırılmış seans notu | Koç | ⭐⭐ | Düşük |

### Faz 3 — Ölçek & İş (12–20 hafta)

| Madde | Kim için | Etki | Efor |
|-------|----------|------|------|
| Kohort/grup planlama + etiketleme | Koç | ⭐⭐ | Orta |
| İleri rapor & PDF (veli/öğrenci) | Koç + Veli | ⭐⭐ | Orta |
| Online ödeme tahsilatı (iyzico/PayTR) | Koç | ⭐⭐ | Orta–Yüksek |
| Aralıklı tekrar önerisi | Öğrenci + Koç | ⭐⭐ | Orta |
| Soru bankası + oto-ödev | Koç + Öğrenci | ⭐⭐ | Yüksek |

### Hızlı Kazanımlar (paralel, düşük efor)

- Öğrenci ana sayfasında **net trend mini-grafiği** (mevcut `quiz_results` verisiyle).
- Koç panosunda **"bugün riskli 3 öğrenci"** kartı (basit kural: seri bozuldu **veya**
  son 2 gün görev yok).
- Deneme girişinde **doğru/yanlış/boş** alanı zaten var → ders bazlı yanlış oranını
  öğrenciye de göster (koçtaki `analiz.php` mantığını öğrenci raporuna taşı).
- Mobil koç akışında hotfix'lerin işaret ettiği ekranlar için küçük bir UX denetimi.

---

## 5. Teknik Notlar (uygulama için)

- **Yeni tablolar (öneri):** `mistake_notes`, `student_goals`, `study_sessions`
  (süre), `achievements` + `student_achievements`, `student_tags`, `session_logs`,
  `risk_scores` (ya da hesaplanan görünüm).
- **Mevcut yapıyı koru:** `education_*` müfredat sistemi faz + sahiplik/kilit modeliyle
  temiz; deneme-konu köprüsü `education_topics.id` üzerinden kurulmalı (`schedule_items.
  edu_topic_id` ile aynı referans — tutarlılık sağlar).
- **Deneme kırılımı:** `quiz_results.details` (JSON) alanı zaten var; ders bazlı yerine
  **konu bazlı** doğru/yanlış tutacak şekilde genişletilebilir — şema kırılmadan ilerler.
- **Risk skoru:** yeni tablo yerine önce `cron_notifications.php` içindeki günlük
  hesapların üstüne türetilmiş bir skor (gece cron'da yaz) ile başlanabilir.
- **Bildirim altyapısı hazır:** rozet kazanımı, risk uyarısı, hedef kilometre taşı gibi
  yeni tetikleyiciler mevcut web-push hattına eklenebilir.

---

### Kapanış

Kısa vadede en yüksek getiri **Faz 1**: deneme sonucunu zayıf konuya, zayıf konuyu da
göreve bağlayan döngüyü kapatmak. Bu, hem öğrenciye "nereye çalışmalıyım" netliğini,
hem koça "ne atamalıyım" otomasyonunu aynı anda kazandırır — ve platformun mevcut
güçlü takip altyapısını gerçek bir *öğrenme motoruna* dönüştürür.
