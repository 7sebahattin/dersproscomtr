<?php
/** push_config.php — Otomatik oluşturuldu */

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit(); }

define('VAPID_SUBJECT',     'mailto:admin@derspros.com');
define('VAPID_PUBLIC_KEY',  'BDZHFy8e4F2qRFaCUhbnXTK2fq6Ej5vjDmBkXnUf3msstlE28KWaS__eFFGhxLc0gdG1ruS2QhsUInF6a04jRQA');
define('VAPID_PRIVATE_KEY', '***REMOVED***');

function vapid_configured(): bool {
    return defined('VAPID_PUBLIC_KEY')  && strlen(VAPID_PUBLIC_KEY)  > 10
        && defined('VAPID_PRIVATE_KEY') && strlen(VAPID_PRIVATE_KEY) > 10;
}
