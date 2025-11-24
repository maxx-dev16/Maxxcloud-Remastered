<?php
/**
 * Test-Script für Datenbankverbindung
 * 
 * Dieses Script testet die Verbindung zu Bucket-1
 * Führe es aus, um zu sehen, ob die Verbindung funktioniert
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/bootstrap.php';

echo "<h1>Datenbankverbindungstest</h1>\n";
echo "<pre>\n";

try {
    global $buckets;
    
    echo "=== Bucket-Konfiguration ===\n";
    if (empty($buckets)) {
        die("FEHLER: Keine Buckets konfiguriert!\n");
    }
    
    foreach ($buckets as $bucketName => $config) {
        echo "\nBucket: {$bucketName}\n";
        echo "Host: {$config['host']}\n";
        echo "Port: {$config['port']}\n";
        echo "DB Name: {$config['db_name']}\n";
        echo "User: {$config['user']}\n";
        echo "Pass: " . str_repeat('*', strlen($config['pass'])) . "\n";
        
        // Teste Verbindung
        echo "\n--- Teste Verbindung ---\n";
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['db_name']
            );
            
            echo "DSN: {$dsn}\n";
            
            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            
            echo "✓ Verbindung erfolgreich!\n";
            
            // Teste Tabellen
            echo "\n--- Prüfe Tabellen ---\n";
            $tables = ['users', 'folders', 'files', 'file_data'];
            foreach ($tables as $table) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                    $count = $stmt->fetchColumn();
                    echo "✓ Tabelle '{$table}' existiert ({$count} Einträge)\n";
                } catch (Exception $e) {
                    echo "✗ Tabelle '{$table}' fehlt oder Fehler: " . $e->getMessage() . "\n";
                }
            }
            
            // Teste Storage-DB
            echo "\n--- Teste Storage-Datenbank ---\n";
            $storageHost = $config['storage_host'] ?? $config['host'];
            $storagePort = $config['storage_port'] ?? $config['port'];
            $storageDbName = $config['storage_db_name'] ?? $config['db_name'];
            $storageUser = $config['storage_user'] ?? $config['user'];
            $storagePass = $config['storage_pass'] ?? $config['pass'];
            
            $storageDsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $storageHost,
                $storagePort,
                $storageDbName
            );
            
            echo "Storage DSN: {$storageDsn}\n";
            
            $storagePdo = new PDO($storageDsn, $storageUser, $storagePass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            
            echo "✓ Storage-Verbindung erfolgreich!\n";
            
            try {
                $stmt = $storagePdo->query("SELECT COUNT(*) FROM file_data");
                $count = $stmt->fetchColumn();
                echo "✓ Storage-Tabelle 'file_data' existiert ({$count} Einträge)\n";
            } catch (Exception $e) {
                echo "✗ Storage-Tabelle 'file_data' fehlt oder Fehler: " . $e->getMessage() . "\n";
            }
            
        } catch (PDOException $e) {
            echo "✗ FEHLER bei Verbindung:\n";
            echo "  Code: " . $e->getCode() . "\n";
            echo "  Message: " . $e->getMessage() . "\n";
            
            // Detaillierte Fehleranalyse
            if ($e->getCode() == 1045) {
                echo "\n  → Falscher Benutzername oder Passwort!\n";
            } elseif ($e->getCode() == 1044) {
                echo "\n  → User hat keinen Zugriff auf diese Datenbank!\n";
                echo "  → Prüfe, ob der Datenbankname korrekt ist.\n";
            } elseif ($e->getCode() == 2002) {
                echo "\n  → Host nicht erreichbar!\n";
            } elseif ($e->getCode() == 1049) {
                echo "\n  → Datenbank existiert nicht!\n";
            }
        }
    }
    
    // Teste Hauptdatenbank
    echo "\n\n=== Hauptdatenbank (Master-DB) ===\n";
    try {
        global $config;
        
        // Debug: Zeige alle config Keys
        echo "Verfügbare Config-Keys: " . implode(', ', array_keys($config)) . "\n\n";
        
        if (!isset($config['master_db_host']) || empty($config['master_db_host'])) {
            echo "⚠ WARNUNG: Hauptdatenbank-Konfiguration fehlt in config.php!\n";
            echo "Die Hauptdatenbank sollte die gleichen Credentials wie Bucket-1 verwenden.\n";
            echo "\nVersuche Fallback auf Bucket-1 Credentials...\n";
            
            // Fallback: Verwende Bucket-1 Credentials
            $firstBucket = array_key_first($buckets);
            if ($firstBucket && isset($buckets[$firstBucket])) {
                $bucketConfig = $buckets[$firstBucket];
                $config['master_db_host'] = $bucketConfig['host'];
                $config['master_db_port'] = $bucketConfig['port'];
                $config['master_db_name'] = $bucketConfig['db_name'];
                $config['master_db_user'] = $bucketConfig['user'];
                $config['master_db_pass'] = $bucketConfig['pass'];
                echo "Verwende Bucket-1 als Hauptdatenbank.\n";
            } else {
                echo "✗ Kein Fallback möglich - keine Buckets konfiguriert.\n";
                return;
            }
        }
        
        $masterDsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['master_db_host'],
            $config['master_db_port'],
            $config['master_db_name']
        );
        
        echo "DSN: {$masterDsn}\n";
        echo "User: {$config['master_db_user']}\n";
        
        $masterPdo = new PDO($masterDsn, $config['master_db_user'], $config['master_db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        
        echo "✓ Hauptdatenbank-Verbindung erfolgreich!\n";
        
        try {
            $stmt = $masterPdo->query("SELECT COUNT(*) FROM user_buckets");
            $count = $stmt->fetchColumn();
            echo "✓ Tabelle 'user_buckets' existiert ({$count} Einträge)\n";
        } catch (Exception $e) {
            echo "✗ Tabelle 'user_buckets' fehlt oder Fehler: " . $e->getMessage() . "\n";
        }
        
    } catch (PDOException $e) {
        echo "✗ FEHLER bei Hauptdatenbank-Verbindung:\n";
        echo "  Code: " . $e->getCode() . "\n";
        echo "  Message: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "FEHLER: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n</pre>\n";

