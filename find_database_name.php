<?php
/**
 * Script zum Finden des korrekten Datenbanknamens
 * 
 * Dieses Script verbindet sich ohne Datenbankname und listet alle verfügbaren Datenbanken auf
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Suche verfügbare Datenbanken</h1>\n";
echo "<pre>\n";

$host = 'db.novium.world';
$port = 3306;
$user = 'u120_2Y9MEq18EI';
$pass = '.@91^EMm^G1y!3BF9FoYYsiR';

echo "=== Verbindungsinformationen ===\n";
echo "Host: {$host}\n";
echo "Port: {$port}\n";
echo "User: {$user}\n";
echo "\n";

// Versuche Verbindung OHNE Datenbankname
try {
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
    echo "DSN (ohne DB): {$dsn}\n";
    echo "\n--- Verbinde ohne Datenbankname ---\n";
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    
    echo "✓ Verbindung erfolgreich!\n\n";
    
    // Liste alle verfügbaren Datenbanken auf
    echo "=== Verfügbare Datenbanken ===\n";
    $stmt = $pdo->query('SHOW DATABASES');
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($databases)) {
        echo "Keine Datenbanken gefunden.\n";
    } else {
        echo "Gefundene Datenbanken:\n";
        foreach ($databases as $db) {
            // Überspringe System-Datenbanken
            if (in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys'])) {
                continue;
            }
            
            echo "  - {$db}\n";
            
            // Teste Verbindung zu dieser Datenbank
            try {
                $testDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
                $testPdo = new PDO($testDsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                
                // Prüfe ob users Tabelle existiert
                try {
                    $testPdo->query('SELECT 1 FROM users LIMIT 1');
                    echo "    ✓ Zugriff möglich + 'users' Tabelle vorhanden → MÖGLICHER KANDIDAT!\n";
                } catch (Exception $e) {
                    echo "    ✓ Zugriff möglich (keine 'users' Tabelle)\n";
                }
            } catch (Exception $e) {
                echo "    ✗ Kein Zugriff: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n=== Empfehlung ===\n";
    echo "Verwende die Datenbank, die:\n";
    echo "1. Zugriff hat (✓)\n";
    echo "2. Die 'users' Tabelle hat (falls bereits erstellt)\n";
    echo "\nTrage den Namen in buckets.php ein (Zeilen 33 und 39)\n";
    
} catch (PDOException $e) {
    echo "✗ FEHLER bei Verbindung:\n";
    echo "  Code: " . $e->getCode() . "\n";
    echo "  Message: " . $e->getMessage() . "\n";
    
    if ($e->getCode() == 1045) {
        echo "\n  → Falscher Benutzername oder Passwort!\n";
    } elseif ($e->getCode() == 2002) {
        echo "\n  → Host nicht erreichbar!\n";
    }
    
    echo "\n=== Alternative: Manuelle Prüfung ===\n";
    echo "1. Öffne phpMyAdmin\n";
    echo "2. Logge dich mit User '{$user}' ein\n";
    echo "3. Schaue in der linken Seitenleiste nach verfügbaren Datenbanken\n";
    echo "4. Der Datenbankname sollte ähnlich wie '{$user}' sein\n";
    echo "   Oft: 's120_...' oder 'u120_...' oder ähnlich\n";
}

echo "\n</pre>\n";



