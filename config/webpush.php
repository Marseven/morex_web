<?php

return [
    // Sujet VAPID : un mailto: ou l'URL de l'app (identifie l'expéditeur auprès des push services).
    'subject' => env('VAPID_SUBJECT', env('APP_URL', 'https://morex.mebodorichard.com')),
    'public_key' => env('VAPID_PUBLIC_KEY', ''),
    'private_key' => env('VAPID_PRIVATE_KEY', ''),
];
