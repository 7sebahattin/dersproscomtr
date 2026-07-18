<?php
/** push_config.php — Otomatik oluşturuldu */

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit(); }

require_once __DIR__ . '/secrets.php';

define('VAPID_SUBJECT',     'mailto:admin@derspros.com');
define('VAPID_PUBLIC_KEY',  'BDZHFy8e4F2qRFaCUhbnXTK2fq6Ej5vjDmBkXnUf3msstlE28KWaS__eFFGhxLc0gdG1ruS2QhsUInF6a04jRQA');
// VAPID_PRIVATE_KEY artık secrets.php içinde tanımlanıyor

function vapid_configured(): bool {
    return defined('VAPID_PUBLIC_KEY')  && strlen(VAPID_PUBLIC_KEY)  > 10
        && defined('VAPID_PRIVATE_KEY') && strlen(VAPID_PRIVATE_KEY) > 10;
}

/**
 * Güvenlik (SSRF önleme): Web Push abonelik endpoint'i yalnızca bilinen tarayıcı
 * push servislerine ait olabilir. Bu kontrol olmadan bir kullanıcı rastgele bir
 * URL (iç ağ adresi, cloud metadata, vb.) kaydedip sunucuyu oraya HTTP isteği
 * atmaya zorlayabilir. Hem kayıt anında (push_subscribe.php) hem gönderim
 * anında (push_test.php, cron_notifications.php) uygulanır — savunma derinliği.
 */
function push_endpoint_is_allowed(string $endpoint): bool {
    $parts = parse_url($endpoint);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
        return false;
    }
    $host = strtolower($parts['host']);
    $allowedSuffixes = [
        'fcm.googleapis.com',              // Chrome/Edge (Android dahil)
        'android.googleapis.com',
        'updates.push.services.mozilla.com', // Firefox
        'web.push.apple.com',              // Safari
        'notify.windows.com',              // Windows/Edge (wns2-xx.notify.windows.com)
    ];
    foreach ($allowedSuffixes as $suffix) {
        if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
            return true;
        }
    }
    return false;
}
