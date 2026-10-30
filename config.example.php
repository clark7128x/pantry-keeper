<?php
return [
    'timezone' => 'Europe/Kyiv',
    'database' => __DIR__ . '/var/pantry.sqlite',
    'secure_cookies' => false, // Set true for HTTPS deployment.
    'vapid' => ['subject' => 'mailto:replace@example.com', 'publicKey' => '', 'privateKey' => ''],
];
