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

// Aşağıdakiler İSTEĞE BAĞLIDIR. Tanımlanmazsa canlı sunucunun mevcut
// değerleri (derspros_db / derspros_sebo) kullanılır. Test (staging) ortamı
// kendi ayrı veritabanına bağlanmak için bunları tanımlar:
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'derspros_test');
// define('DB_USER', 'derspros_testuser');
