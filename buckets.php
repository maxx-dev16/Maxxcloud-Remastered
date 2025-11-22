<?php

/**
 * Bucket-Konfiguration
 * 
 * Jeder Bucket repräsentiert einen Datenbankserver.
 * Neue User werden automatisch dem am wenigsten ausgelasteten Bucket zugeordnet.
 * 
 * Format:
 * [
 *     'bucket_name' => [
 *         'host' => 'db.example.com',
 *         'port' => 3306,
 *         'db_name' => 'database_name',
 *         'user' => 'username',
 *         'pass' => 'password',
 *         'storage_host' => 'db.example.com',  // Optional, falls anders als host
 *         'storage_port' => 3306,              // Optional, falls anders als port
 *         'storage_db_name' => 'storage_db',   // Optional, falls anders als db_name
 *         'storage_user' => 'storage_user',    // Optional, falls anders als user
 *         'storage_pass' => 'storage_pass',   // Optional, falls anders als pass
 *     ],
 *     ...
 * ]
 */

return [
    'Bucket-1' => [
        'host' => 'db.novium.world',
        'port' => 3306,
        'db_name' => 's120_maxxcloud-old-test',
        'user' => 'u120_2Y9MEq18EI',
        'pass' => '.@91^EMm^G1y!3BF9FoYYsiR',
        // Storage verwendet dieselben Credentials wie die Hauptdatenbank
        'storage_host' => 'db.novium.world',
        'storage_port' => 3306,
        'storage_db_name' => 's120_maxxcloud-old-test',
        'storage_user' => 'u120_2Y9MEq18EI',
        'storage_pass' => '.@91^EMm^G1y!3BF9FoYYsiR',
    ],
];

