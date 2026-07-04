<?php
/**
 * Bu dosya bir ŞABLONDUR (örnek). Gerçek şifreleri buraya YAZMA, bu dosya GitHub'a yüklenir.
 *
 * Yapman gereken:
 * 1. Bu dosyayı "secrets.php" adıyla kopyala.
 * 2. secrets.php dosyasını SADECE sunucuda (hosting'de), gerçek değerlerle doldur.
 * 3. secrets.php asla git'e eklenmez (.gitignore içinde) — sadece sunucuda durur.
 */

define('DB_PASSWORD', '');       // Veritabanı (MySQL) şifresi
define('SMTP_PASSWORD', '');     // noreply@derspros.com.tr e-posta şifresi
define('VAPID_PRIVATE_KEY', ''); // Push bildirim özel anahtarı

// İSTEĞE BAĞLI: Bildirim cron'unu dışarıdan tetikleme jetonu.
// Tanımlanırsa cron-job.org / UptimeRobot gibi ücretsiz bir servise şu adresi
// 5 dakikada bir çağırtın (bildirimler site trafiğinden bağımsız çalışır):
//   https://derspros.com.tr/cron_notifications.php?token=BURAYA_YAZDIGINIZ_JETON
// Jeton uzun ve tahmin edilemez olmalı (örn. 40+ karakter rastgele dizi).
// define('CRON_TOKEN', '');

// Aşağıdakiler İSTEĞE BAĞLIDIR. Tanımlanmazsa canlı sunucunun mevcut
// değerleri (derspros_db / derspros_sebo) kullanılır. Test (staging) ortamı
// kendi ayrı veritabanına bağlanmak için bunları tanımlar:
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'derspros_test');
// define('DB_USER', 'derspros_testuser');
