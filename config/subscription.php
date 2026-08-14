<?php

return [
    'bank' => [
        'name' => env('SUBSCRIPTION_BANK_NAME', 'BSI'),
        'account' => env('SUBSCRIPTION_BANK_ACCOUNT', ''),
        'holder' => env('SUBSCRIPTION_BANK_HOLDER', 'Amzha Digital Nusantara'),
    ],

    'email' => [
        'imap_host' => env('SUBSCRIPTION_IMAP_HOST', 'imap.gmail.com'),
        'imap_port' => (int) env('SUBSCRIPTION_IMAP_PORT', 993),
        'username' => env('SUBSCRIPTION_IMAP_USERNAME', 'amzhadigitalnusantara@gmail.com'),
        'password' => env('SUBSCRIPTION_IMAP_PASSWORD'),
        'from_address' => env('SUBSCRIPTION_EMAIL_FROM', 'BSICenter@bankbsi.co.id'),
        'subject' => env('SUBSCRIPTION_EMAIL_SUBJECT', 'NotifikasiKredit'),
        'lookback_days' => (int) env('SUBSCRIPTION_EMAIL_LOOKBACK_DAYS', 7),
    ],

    'unit_code' => [
        'min' => 100,
        'max' => 999,
    ],

    'payment_expires_hours' => (int) env('SUBSCRIPTION_PAYMENT_EXPIRES_HOURS', 48),
];
