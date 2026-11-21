<?php
// Copy to config.local.php (git-ignored) if you do not use environment variables.
// XAMPP local demo only. Use a dedicated MySQL user with a strong password in production.
return [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'web_mysqli',
    'DB_USER' => 'root',
    'DB_PASS' => '',

    // Optional SMTP configuration for email outbox (leave SMTP_HOST empty to disable email sending).
    // Never commit real SMTP credentials to GitHub.
    'SMTP_HOST' => '',
    'SMTP_PORT' => '587',
    'SMTP_SECURE' => 'tls', // tls | ssl | none
    'SMTP_USER' => '',
    'SMTP_PASS' => '',
    'SMTP_FROM_EMAIL' => '',
    'SMTP_FROM_NAME' => 'BanHang Tech Store',
];
