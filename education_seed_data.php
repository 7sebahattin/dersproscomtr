<?php
/**
 * education_seed_data.php — Yeni müfredat sistemi başlangıç (seed) verisi
 *
 * Yapı: Kategori => Ders => [Konular]
 * Bu liste yalnızca İLK KURULUMDA (tablolar boşken) eklenir.
 * Sonrasında tüm yönetim admin/education.php üzerinden yapılır;
 * buradaki listeyi değiştirmek mevcut veritabanını ETKİLEMEZ.
 */

return [

'TYT' => [
    'Türkçe' => [
        'Sözcükte Anlam','Söz Yorumu','Deyim ve Atasözü','Cümlede Anlam','Paragrafta Anlam',
        'Sözel Mantık ve Muhakeme','Ses Bilgisi','Yazım Kuralları','Noktalama İşaretleri',
        'Sözcükte Yapı ve Ekler','İsimler','Zamirler','Sıfatlar','Zarflar','Edat - Bağlaç - Ünlem',
        'Fiiller','Ek Fiil','Fiilimsi','Fiilde Çatı','Cümlenin Ögeleri','Cümle Türleri','Anlatım Bozukluğu',
    ],
    'Matematik' => [
        'Temel Kavramlar','Sayı Basamakları','Bölme ve Bölünebilme','EBOB - EKOK','Rasyonel Sayılar',
        'Basit Eşitsizlikler','Mutlak Değer','Üslü Sayılar','Köklü Sayılar','Çarpanlara Ayırma',
        'Oran - Orantı','Denklem Çözme','Sayı Problemleri','Kesir Problemleri','Yaş Problemleri',
        'Yüzde Problemleri','Kar - Zarar Problemleri','Karışım Problemleri','Hız Problemleri',
        'İşçi Problemleri','Kümeler','Mantık','Fonksiyonlar','Polinomlar','İkinci Dereceden Denklemler',
        'Permütasyon - Kombinasyon','Binom','Olasılık','Veri ve İstatistik',
    ],
    'Geometri' => [
        'Doğruda Açılar','Üçgende Açılar','Özel Üçgenler','Dik Üçgen','İkizkenar Üçgen','Eşkenar Üçgen',
        'Açıortay','Kenarortay','Üçgende Alan','Üçgende Benzerlik','Açı - Kenar Bağıntıları',
        'Çokgenler','Dörtgenler','Paralelkenar','Eşkenar Dörtgen','Dikdörtgen','Kare','Yamuk','Deltoid',
        'Çember ve Daire','Analitik Geometri','Katı Cisimler',
    ],
    'Fizik' => [
        'Fizik Bilimine Giriş','Madde ve Özellikleri','Sıvıların Kaldırma Kuvveti','Basınç',
        'Isı, Sıcaklık ve Genleşme','Hareket ve Kuvvet','Dinamik','İş, Güç ve Enerji',
        'Elektrik','Manyetizma','Dalgalar','Optik',
    ],
    'Kimya' => [
        'Kimya Bilimi','Atom ve Periyodik Sistem','Kimyasal Türler Arası Etkileşimler','Maddenin Halleri',
        'Doğa ve Kimya','Kimyanın Temel Kanunları','Kimyasal Hesaplamalar','Karışımlar',
        'Asitler, Bazlar ve Tuzlar','Kimya Her Yerde',
    ],
    'Biyoloji' => [
        'Canlıların Ortak Özellikleri','Canlıların Temel Bileşenleri','Hücre ve Organeller',
        'Hücre Zarından Madde Geçişleri','Canlıların Sınıflandırılması','Mitoz ve Eşeysiz Üreme',
        'Mayoz ve Eşeyli Üreme','Kalıtım','Ekosistem Ekolojisi','Güncel Çevre Sorunları',
    ],
    'Tarih' => [
        'Tarih ve Zaman','İnsanlığın İlk Dönemleri','Orta Çağ\'da Dünya','İlk ve Orta Çağlarda Türk Dünyası',
        'İslam Medeniyetinin Doğuşu','Türklerin İslamiyet\'i Kabulü','Yerleşme ve Devletleşme Sürecinde Selçuklu Türkiyesi',
        'Beylikten Devlete Osmanlı','Dünya Gücü Osmanlı','Değişim Çağında Avrupa ve Osmanlı',
        'Uluslararası İlişkilerde Denge Stratejisi','Devrimler Çağında Osmanlı','XX. Yüzyıl Başlarında Osmanlı',
        'Milli Mücadele','Atatürkçülük ve Türk İnkılabı',
    ],
    'Coğrafya' => [
        'Doğa ve İnsan','Dünya\'nın Şekli ve Hareketleri','Coğrafi Konum','Harita Bilgisi',
        'Atmosfer ve İklim','İç Kuvvetler','Dış Kuvvetler','Su, Toprak ve Bitkiler','Nüfus','Göç','Yerleşme',
        'Ekonomik Faaliyetler','Uluslararası Ulaşım Hatları','Doğal Afetler','Çevre ve Toplum',
    ],
    'Felsefe' => [
        'Felsefenin Konusu','Bilgi Felsefesi','Varlık Felsefesi','Ahlak Felsefesi','Sanat Felsefesi',
        'Din Felsefesi','Siyaset Felsefesi','Bilim Felsefesi',
    ],
    'Din Kültürü' => [
        'Bilgi ve İnanç','Din ve İslam','İslam ve İbadet','Gençlik ve Değerler','Gönül Coğrafyamız',
        'Allah İnsan İlişkisi','Hz. Muhammed ve Gençlik','Ahlaki Tutum ve Davranışlar','İslam Düşüncesinde Yorumlar',
    ],
],

'AYT' => [
    'Matematik' => [
        'Fonksiyonlar','Polinomlar','İkinci Dereceden Denklemler','Parabol','Eşitsizlikler',
        'Trigonometri','Logaritma','Diziler','Limit ve Süreklilik','Türev','İntegral',
        'Permütasyon - Kombinasyon','Binom ve Olasılık',
    ],
    'Geometri' => [
        'Üçgenler','Dörtgenler ve Çokgenler','Çember ve Daire','Analitik Geometri',
        'Çemberin Analitik İncelenmesi','Katı Cisimler','Dönüşüm Geometrisi',
    ],
    'Fizik' => [
        'Vektörler','Kuvvet, Tork ve Denge','Kütle Merkezi','Basit Makineler','Bağıl Hareket',
        'Newton\'un Hareket Yasaları','Bir Boyutta Sabit İvmeli Hareket','Atışlar','İş, Güç ve Enerji',
        'İtme ve Momentum','Elektrik Alan ve Potansiyel','Paralel Levhalar ve Sığa',
        'Manyetik Alan ve Manyetik Kuvvet','İndüksiyon ve Alternatif Akım','Transformatörler',
        'Çembersel Hareket','Basit Harmonik Hareket','Dalga Mekaniği','Atom Fiziği ve Radyoaktivite',
        'Modern Fizik','Modern Fiziğin Teknolojideki Uygulamaları',
    ],
    'Kimya' => [
        'Modern Atom Teorisi','Gazlar','Sıvı Çözeltiler ve Çözünürlük','Kimyasal Tepkimelerde Enerji',
        'Kimyasal Tepkimelerde Hız','Kimyasal Denge','Asit - Baz Dengesi','Çözünürlük Dengesi',
        'Kimya ve Elektrik','Karbon Kimyasına Giriş','Organik Bileşikler','Enerji Kaynakları ve Bilimsel Gelişmeler',
    ],
    'Biyoloji' => [
        'Sinir Sistemi','Endokrin Sistem','Duyu Organları','Destek ve Hareket Sistemi','Sindirim Sistemi',
        'Dolaşım ve Bağışıklık Sistemi','Solunum Sistemi','Üriner Sistem','Üreme Sistemi ve Embriyonik Gelişim',
        'Komünite Ekolojisi','Popülasyon Ekolojisi','Nükleik Asitler','Genden Proteine',
        'Canlılarda Enerji Dönüşümleri','Fotosentez ve Kemosentez','Hücresel Solunum','Bitki Biyolojisi','Canlılar ve Çevre',
    ],
    'Edebiyat' => [
        'Güzel Sanatlar ve Edebiyat','Metinlerin Sınıflandırılması','Şiir Bilgisi','Edebi Sanatlar',
        'İslamiyet Öncesi Türk Edebiyatı','Geçiş Dönemi Türk Edebiyatı','Halk Edebiyatı','Divan Edebiyatı',
        'Tanzimat Edebiyatı','Servet-i Fünun Edebiyatı','Fecr-i Ati Edebiyatı','Milli Edebiyat',
        'Cumhuriyet Dönemi Şiiri','Cumhuriyet Dönemi Roman ve Hikâyesi','Cumhuriyet Dönemi Tiyatrosu',
        'Edebi Akımlar','Dünya Edebiyatı',
    ],
    'Tarih' => [
        'XX. Yüzyıl Başlarında Osmanlı ve Dünya','Milli Mücadele','Atatürkçülük ve Türk İnkılabı',
        'İki Savaş Arasındaki Dönem','II. Dünya Savaşı Sürecinde Türkiye ve Dünya','Soğuk Savaş Dönemi',
        'Yumuşama Dönemi ve Sonrası','Küreselleşen Dünya','XXI. Yüzyılın Eşiğinde Türkiye ve Dünya',
    ],
    'Coğrafya' => [
        'Ekosistem','Biyoçeşitlilik','Ekstrem Doğa Olayları','Küresel İklim Değişimi','Nüfus Politikaları',
        'Türkiye\'de Nüfus ve Yerleşme','Ekonomik Faaliyet Türleri','Türkiye Ekonomisi','Türkiye\'de Tarım',
        'Türkiye\'de Madenler ve Enerji Kaynakları','Türkiye\'de Sanayi','Türkiye\'de Hizmet Sektörü',
        'Ulaşım, Ticaret ve Turizm','Bölgesel Kalkınma Projeleri','Jeopolitik Konum','Ülkeler ve Bölgesel Örgütler','Çevre Sorunları',
    ],
    'Felsefe Grubu' => [
        'MÖ 6. - MS 2. Yüzyıl Felsefesi','MS 2. - 15. Yüzyıl Felsefesi','15. - 17. Yüzyıl Felsefesi',
        '18. - 19. Yüzyıl Felsefesi','20. Yüzyıl Felsefesi','Psikolojiye Giriş','Öğrenme ve Bellek',
        'Ruh Sağlığı','Sosyolojiye Giriş','Birey ve Toplum','Toplumsal Yapı ve Değişme','Mantığa Giriş',
        'Klasik Mantık','Mantık ve Dil','Sembolik Mantık',
    ],
    'Din Kültürü' => [
        'Dünya ve Ahiret','Kur\'an\'a Göre Hz. Muhammed','Kur\'an\'da Bazı Kavramlar','İnançla İlgili Meseleler',
        'Yahudilik ve Hristiyanlık','İslam ve Bilim','Anadolu\'da İslam','İslam Düşüncesinde Tasavvufi Yorumlar',
        'Güncel Dini Meseleler','Hint ve Çin Dinleri',
    ],
],

'9. Sınıf' => [
    'Matematik' => [
        'Mantık','Kümeler','Sayı Kümeleri','Bölünebilme Kuralları','Birinci Dereceden Denklem ve Eşitsizlikler',
        'Mutlak Değer','Üslü İfadeler','Köklü İfadeler','Oran - Orantı','Problemler','Üçgenler','Veri - İstatistik',
    ],
    'Fizik' => [
        'Fizik Bilimine Giriş','Madde ve Özellikleri','Hareket ve Kuvvet','Enerji','Isı ve Sıcaklık','Elektrostatik',
    ],
    'Kimya' => [
        'Kimya Bilimi','Atom ve Periyodik Sistem','Kimyasal Türler Arası Etkileşimler','Maddenin Halleri','Doğa ve Kimya',
    ],
    'Biyoloji' => [
        'Yaşam Bilimi Biyoloji','Canlıların Temel Bileşenleri','Hücre','Canlılar Dünyası ve Sınıflandırma',
    ],
    'Türk Dili ve Edebiyatı' => [
        'Giriş: Edebiyat Nedir','Hikâye','Şiir','Masal / Fabl','Roman','Tiyatro','Biyografi / Otobiyografi','Mektup / E-posta','Günlük / Blog',
    ],
    'Tarih' => [
        'Tarih ve Zaman','İnsanlığın İlk Dönemleri','Orta Çağ\'da Dünya','İlk ve Orta Çağlarda Türk Dünyası',
        'İslam Medeniyetinin Doğuşu','Türklerin İslamiyet\'i Kabulü ve İlk Türk İslam Devletleri',
    ],
    'Coğrafya' => [
        'Doğa ve İnsan','Dünya\'nın Şekli ve Hareketleri','Coğrafi Konum','Harita Bilgisi','Atmosfer ve İklim',
        'Yerin Şekillenmesi','Beşeri Yapı','Ekonomik Faaliyetler',
    ],
],

'10. Sınıf' => [
    'Matematik' => [
        'Sayma ve Olasılık','Permütasyon','Kombinasyon','Binom','Olasılık','Fonksiyonlar','Polinomlar',
        'İkinci Dereceden Denklemler','Dörtgenler ve Çokgenler','Uzay Geometri (Katı Cisimler)',
    ],
    'Fizik' => [
        'Elektrik','Manyetizma','Basınç','Kaldırma Kuvveti','Dalgalar','Optik',
    ],
    'Kimya' => [
        'Kimyanın Temel Kanunları','Mol Kavramı','Kimyasal Hesaplamalar','Karışımlar','Asitler, Bazlar ve Tuzlar','Kimya Her Yerde',
    ],
    'Biyoloji' => [
        'Mitoz ve Eşeysiz Üreme','Mayoz ve Eşeyli Üreme','Kalıtımın Genel İlkeleri','Ekosistem Ekolojisi','Güncel Çevre Sorunları',
    ],
    'Türk Dili ve Edebiyatı' => [
        'Giriş: Edebiyat - Tarih İlişkisi','Hikâye','Şiir','Destan / Efsane','Roman','Tiyatro','Anı (Hatıra)','Haber Metni','Gezi Yazısı',
    ],
    'Tarih' => [
        'Yerleşme ve Devletleşme Sürecinde Selçuklu Türkiyesi','Beylikten Devlete Osmanlı Siyaseti',
        'Devletleşme Sürecinde Savaşçılar ve Askerler','Beylikten Devlete Osmanlı Medeniyeti',
        'Dünya Gücü Osmanlı','Sultan ve Osmanlı Merkez Teşkilatı','Klasik Çağda Osmanlı Toplum Düzeni',
    ],
    'Coğrafya' => [
        'Dünya\'nın Tektonik Oluşumu','Kayaçlar','İç Kuvvetler','Dış Kuvvetler','Su Kaynakları','Toprak','Bitkiler',
        'Nüfus','Göç','Yerleşme','Ulaşım','Doğal Afetler',
    ],
],

'11. Sınıf' => [
    'Matematik' => [
        'Trigonometri','Analitik Geometri','Fonksiyonlarda Uygulamalar','İkinci Dereceden Fonksiyonlar (Parabol)',
        'Denklem ve Eşitsizlik Sistemleri','Çember ve Daire','Uzay Geometri','Olasılık',
    ],
    'Fizik' => [
        'Vektörler','Bağıl Hareket','Newton\'un Hareket Yasaları','Bir Boyutta Sabit İvmeli Hareket','Atışlar',
        'İş, Güç ve Enerji','İtme ve Çizgisel Momentum','Tork ve Denge','Basit Makineler',
        'Elektrik Alan ve Potansiyel','Paralel Levhalar ve Sığa','Manyetizma ve Elektromanyetik İndüksiyon','Alternatif Akım',
    ],
    'Kimya' => [
        'Modern Atom Teorisi','Gazlar','Sıvı Çözeltiler ve Çözünürlük','Kimyasal Tepkimelerde Enerji',
        'Kimyasal Tepkimelerde Hız','Kimyasal Tepkimelerde Denge','Asit - Baz Dengesi','Çözünürlük Dengesi',
    ],
    'Biyoloji' => [
        'Sinir Sistemi','Endokrin Sistem','Duyu Organları','Destek ve Hareket Sistemi','Sindirim Sistemi',
        'Dolaşım ve Bağışıklık Sistemi','Solunum Sistemi','Üriner Sistem','Üreme Sistemi','Komünite ve Popülasyon Ekolojisi',
    ],
    'Türk Dili ve Edebiyatı' => [
        'Giriş: Edebiyat - Toplum İlişkisi','Hikâye','Şiir','Makale','Sohbet ve Fıkra','Roman','Tiyatro','Eleştiri','Mülakat / Röportaj',
    ],
    'Tarih' => [
        'Değişen Dünya Dengeleri Karşısında Osmanlı Siyaseti','Değişim Çağında Avrupa ve Osmanlı',
        'Uluslararası İlişkilerde Denge Stratejisi','Devrimler Çağında Değişen Devlet - Toplum İlişkileri',
        'Sermaye ve Emek','XIX. ve XX. Yüzyılda Değişen Gündelik Hayat',
    ],
    'Coğrafya' => [
        'Ekosistem','Biyoçeşitlilik','Nüfus Politikaları','Türkiye\'de Yerleşme','Ekonomik Faaliyet Türleri',
        'Türkiye Ekonomisi','Türkiye\'de Tarım','Türkiye\'de Madenler ve Enerji Kaynakları','Türkiye\'de Sanayi',
        'Bölgesel Kalkınma Projeleri','Kültür Bölgeleri','Çevre Sorunları',
    ],
],

'12. Sınıf' => [
    'Matematik' => [
        'Üstel ve Logaritmik Fonksiyonlar','Diziler','Trigonometri (Toplam - Fark, Dönüşüm)','Dönüşümler',
        'Limit ve Süreklilik','Türev','İntegral','Analitik Geometri (Çemberin Analitiği)',
    ],
    'Fizik' => [
        'Çembersel Hareket','Basit Harmonik Hareket','Dalga Mekaniği','Atom Fiziğine Giriş ve Radyoaktivite',
        'Modern Fizik','Modern Fiziğin Teknolojideki Uygulamaları',
    ],
    'Kimya' => [
        'Kimya ve Elektrik','Karbon Kimyasına Giriş','Organik Bileşikler','Enerji Kaynakları ve Bilimsel Gelişmeler',
    ],
    'Biyoloji' => [
        'Nükleik Asitler','Genden Proteine','Canlılarda Enerji Dönüşümleri','Fotosentez','Kemosentez',
        'Hücresel Solunum','Bitki Biyolojisi','Canlılar ve Çevre',
    ],
    'Türk Dili ve Edebiyatı' => [
        'Giriş: Edebiyat ve Felsefe','Hikâye','Şiir','Roman','Tiyatro','Deneme','Söylev (Nutuk)',
    ],
    'Tarih' => [
        'XX. Yüzyıl Başlarında Osmanlı ve Dünya','Milli Mücadele','Atatürkçülük ve Türk İnkılabı',
        'İki Savaş Arasındaki Dönem','II. Dünya Savaşı Sürecinde Türkiye ve Dünya','Soğuk Savaş Dönemi',
        'Toplumsal Devrim Çağında Dünya ve Türkiye','XXI. Yüzyılın Eşiğinde Türkiye ve Dünya',
    ],
    'Coğrafya' => [
        'Ekstrem Doğa Olayları','Küresel İklim Değişimi','Türkiye\'de Hizmet Sektörü','Ulaşım','Ticaret ve Turizm',
        'Jeopolitik Konum','Ülkeler ve Bölgesel Örgütler','Çevre ve Toplum',
    ],
],

];
