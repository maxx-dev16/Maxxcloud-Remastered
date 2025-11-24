<?php

$turnstileEnabledEnv = getenv('TURNSTILE_ENABLED');
$turnstileEnabled = $turnstileEnabledEnv === false
    ? false
    : filter_var(
        $turnstileEnabledEnv,
        FILTER_VALIDATE_BOOLEAN,
        ['flags' => FILTER_NULL_ON_FAILURE]
    );

return [
    // Hauptdatenbank für User-Zuordnung zu Buckets
    // Verwendet die gleichen Credentials wie Bucket-1 (Main Bucket)
    'master_db_host' => 'db.novium.world',
    'master_db_port' => 3306,
    'master_db_name' => 's120_Bucket-1', // Gleiche DB wie Bucket-1
    'master_db_user' => 'u120_2Y9MEq18EI',
    'master_db_pass' => '.@91^EMm^G1y!3BF9FoYYsiR',
    
    // Legacy-Kompatibilität (wird nicht mehr verwendet, aber für Fallback)
    'db_host' => 'db.novium.world',
    'db_port' => 3306,
    'db_name' => 's120_Bucket-1',
    'db_user' => 'u120_2Y9MEq18EI',
    'db_pass' => '.@91^EMm^G1y!3BF9FoYYsiR',

    'storage_db_host' => 'db.novium.world',
    'storage_db_port' => 3306,
    'storage_db_name' => 's120_Bucket-1',
    'storage_db_user' => 'u120_2Y9MEq18EI',
    'storage_db_pass' => '.@91^EMm^G1y!3BF9FoYYsiR',

    // Cloudflare Turnstile (Captcha)
    // TODO: trage hier deine echten Keys ein
    'turnstile_site_key' => getenv('TURNSTILE_SITE_KEY') ?: '0x4AAAAAACBl8QylNWRGCPLx',
    'turnstile_secret'   => getenv('TURNSTILE_SECRET') ?: '0x4AAAAAACBl8eFcDr6kufLgL1mh2zgDiIQ',
    'turnstile_enabled'  => $turnstileEnabled ?? false,

    // Storage-Modus: 'database' = in DB, 'ftp' = FTP-Server, 'local' = lokal
    'storage_mode' => getenv('STORAGE_MODE') ?: 'database',

    // FTP Storage (optional)
    'ftp_enabled' => (bool) (getenv('FTP_ENABLED') ?: false),
    'ftp_host' => getenv('FTP_HOST') ?: '134.255.216.5',
    'ftp_port' => (int) (getenv('FTP_PORT') ?: 21),
    'ftp_user' => getenv('FTP_USER') ?: 'zap1265543',
    'ftp_pass' => getenv('FTP_PASS') ?: 'zMv5ebCH2B',

    // Dateipfade
    'storage_path' => __DIR__ . DIRECTORY_SEPARATOR . 'storage',
    'default_storage_limit_mb' => 5120,
    'base_url' => getenv('APP_BASE_URL') ?: 'https://maxxcloud.it',
    'mail_from' => getenv('MAIL_FROM') ?: 'no-reply@maxxcloud.it',
    'mail_sender' => getenv('MAIL_SENDER') ?: 'Maxxcloud',
];


