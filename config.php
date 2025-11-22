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
    'db_host' => 'db.novium.world',
    'db_port' => 3306,
    'db_name' => 's120_maxxcloud-old-test',
    'db_user' => 'u120_wSZG0l2YR3',
    'db_pass' => 'p^jcp!c48K4isHc.Tgh4bO2o',

    'storage_db_host' => 'db.novium.world',
    'storage_db_port' => 3306,
    'storage_db_name' => 's120_maxxcloud-old-test',
    'storage_db_user' => 'u120_hH9NtInsda',
    'storage_db_pass' => 'WvYK7vE.@!Eo6U!AYAUBXCP2',

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
];


