<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$buckets = require __DIR__ . '/buckets.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Berlin');

/**
 * Verbindung zur Hauptdatenbank (Master-DB)
 * Diese wird für die User-Zuordnung zu Buckets verwendet
 */
function master_db(): PDO
{
    static $pdo;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['master_db_host'],
        $config['master_db_port'],
        $config['master_db_name']
    );

    $pdo = new PDO($dsn, $config['master_db_user'], $config['master_db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

/**
 * Erstellt eine PDO-Verbindung zu einem bestimmten Bucket
 */
function get_bucket_db(string $bucketName): PDO
{
    static $connections = [];
    
    if (isset($connections[$bucketName])) {
        return $connections[$bucketName];
    }
    
    global $buckets;
    
    if (!isset($buckets[$bucketName])) {
        throw new Exception("Bucket '{$bucketName}' nicht gefunden");
    }
    
    $bucket = $buckets[$bucketName];
    
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $bucket['host'],
        $bucket['port'],
        $bucket['db_name']
    );
    
    $pdo = new PDO($dsn, $bucket['user'], $bucket['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    $connections[$bucketName] = $pdo;
    return $pdo;
}

/**
 * Erstellt eine PDO-Verbindung zum Storage eines bestimmten Buckets
 */
function get_bucket_storage_db(string $bucketName): PDO
{
    static $connections = [];
    
    if (isset($connections[$bucketName])) {
        return $connections[$bucketName];
    }
    
    global $buckets;
    
    if (!isset($buckets[$bucketName])) {
        throw new Exception("Bucket '{$bucketName}' nicht gefunden");
    }
    
    $bucket = $buckets[$bucketName];
    
    $host = $bucket['storage_host'] ?? $bucket['host'];
    $port = $bucket['storage_port'] ?? $bucket['port'];
    $dbName = $bucket['storage_db_name'] ?? $bucket['db_name'];
    $user = $bucket['storage_user'] ?? $bucket['user'];
    $pass = $bucket['storage_pass'] ?? $bucket['pass'];
    
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $dbName
    );
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    $connections[$bucketName] = $pdo;
    return $pdo;
}

/**
 * Ermittelt den Bucket-Namen für einen User
 * Die Zuordnung wird in der Hauptdatenbank gespeichert
 */
function get_user_bucket(int $userId): ?string
{
    static $cache = [];
    
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    
    // Prüfe zuerst die Session
    if (isset($_SESSION['user_bucket_id'])) {
        $bucketName = $_SESSION['user_bucket_id'];
        $cache[$userId] = $bucketName;
        return $bucketName;
    }
    
    // Suche in der Hauptdatenbank
    try {
        $masterDb = master_db();
        $stmt = $masterDb->prepare('SELECT bucket_id FROM user_buckets WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
        $bucketName = $stmt->fetchColumn();
        
        if ($bucketName) {
            $cache[$userId] = $bucketName;
            $_SESSION['user_bucket_id'] = $bucketName;
            return $bucketName;
        }
    } catch (Exception $e) {
        // Hauptdatenbank nicht erreichbar oder Tabelle existiert noch nicht, suche in Buckets
        error_log("Hauptdatenbank-Fehler in get_user_bucket: " . $e->getMessage());
    }
    
    // Falls nicht in Hauptdatenbank gefunden, suche in allen Buckets
    global $buckets;
    foreach (array_keys($buckets) as $bucketName) {
        try {
            $db = get_bucket_db($bucketName);
            $stmt = $db->prepare('SELECT bucket_id FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                // User gefunden in diesem Bucket
                $foundBucket = $user['bucket_id'] ?: $bucketName;
                
                // Speichere Zuordnung in Hauptdatenbank für zukünftige Zugriffe
                try {
                    assign_user_to_bucket($userId, $foundBucket);
                } catch (Exception $e) {
                    // Ignoriere Fehler beim Speichern, verwende gefundenen Bucket
                }
                
                $cache[$userId] = $foundBucket;
                $_SESSION['user_bucket_id'] = $foundBucket;
                return $foundBucket;
            }
        } catch (Exception $e) {
            // Bucket nicht erreichbar, weiter zum nächsten
            continue;
        }
    }
    
    return null;
}

/**
 * Gibt die Datenbankverbindung für den aktuellen User zurück
 * Verwendet automatisch den richtigen Bucket
 */
function db(): PDO
{
    static $pdoCache = [];
    
    $userId = current_user_id();
    
    if ($userId === null) {
        // Fallback auf ersten Bucket, wenn kein User eingeloggt ist
        global $buckets;
        $bucketNames = array_keys($buckets);
        if (empty($bucketNames)) {
            throw new Exception('Keine Buckets konfiguriert');
        }
        return get_bucket_db($bucketNames[0]);
    }
    
    $bucketName = get_user_bucket($userId);
    
    if ($bucketName === null) {
        throw new Exception("Kein Bucket für User {$userId} gefunden");
    }
    
    if (isset($pdoCache[$bucketName])) {
        return $pdoCache[$bucketName];
    }
    
    $pdo = get_bucket_db($bucketName);
    $pdoCache[$bucketName] = $pdo;
    
    return $pdo;
}

/**
 * Gibt die Storage-Datenbankverbindung für den aktuellen User zurück
 */
function storage_db(): PDO
{
    static $pdoCache = [];
    
    $userId = current_user_id();
    
    if ($userId === null) {
        // Fallback auf ersten Bucket, wenn kein User eingeloggt ist
        global $buckets;
        $bucketNames = array_keys($buckets);
        if (empty($bucketNames)) {
            throw new Exception('Keine Buckets konfiguriert');
        }
        return get_bucket_storage_db($bucketNames[0]);
    }
    
    $bucketName = get_user_bucket($userId);
    
    if ($bucketName === null) {
        throw new Exception("Kein Bucket für User {$userId} gefunden");
    }
    
    if (isset($pdoCache[$bucketName])) {
        return $pdoCache[$bucketName];
    }
    
    $pdo = get_bucket_storage_db($bucketName);
    $pdoCache[$bucketName] = $pdo;
    
    return $pdo;
}

/**
 * Ermittelt den am wenigsten ausgelasteten Bucket
 */
function get_least_loaded_bucket(): string
{
    global $buckets;
    
    if (empty($buckets)) {
        throw new Exception('Keine Buckets konfiguriert');
    }
    
    $bucketLoads = [];
    
    foreach (array_keys($buckets) as $bucketName) {
        try {
            $db = get_bucket_db($bucketName);
            $stmt = $db->query('SELECT COUNT(*) FROM users');
            $userCount = (int) $stmt->fetchColumn();
            $bucketLoads[$bucketName] = $userCount;
        } catch (Exception $e) {
            // Wenn Bucket nicht erreichbar ist, setze hohe Last
            $bucketLoads[$bucketName] = PHP_INT_MAX;
        }
    }
    
    // Sortiere nach Last (niedrigste zuerst)
    asort($bucketLoads);
    
    // Gib den ersten (am wenigsten ausgelasteten) Bucket zurück
    return array_key_first($bucketLoads);
}

/**
 * Speichert die Zuordnung eines Users zu einem Bucket in der Hauptdatenbank
 */
function assign_user_to_bucket(int $userId, string $bucketName): void
{
    try {
        $masterDb = master_db();
        
        // Erstelle Tabelle falls nicht vorhanden
        $masterDb->exec(
            'CREATE TABLE IF NOT EXISTS user_buckets (
                user_id INT UNSIGNED PRIMARY KEY,
                bucket_id VARCHAR(100) NOT NULL,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bucket_id (bucket_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        
        $stmt = $masterDb->prepare(
            'INSERT INTO user_buckets (user_id, bucket_id) 
             VALUES (:user_id, :bucket_id)
             ON DUPLICATE KEY UPDATE bucket_id = :bucket_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'bucket_id' => $bucketName
        ]);
        
        // Cache und Session aktualisieren
        $_SESSION['user_bucket_id'] = $bucketName;
    } catch (Exception $e) {
        // Fehler beim Speichern ignorieren, Session trotzdem aktualisieren
        $_SESSION['user_bucket_id'] = $bucketName;
        error_log("Fehler beim Speichern der Bucket-Zuordnung: " . $e->getMessage());
    }
}

function ensure_schema(): void
{
    global $buckets;
    
    // Erstelle Schema in allen Buckets
    foreach (array_keys($buckets) as $bucketName) {
        $db = get_bucket_db($bucketName);
        
        $db->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(120) DEFAULT NULL,
                avatar_url VARCHAR(255) DEFAULT NULL,
                bucket_id VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bucket_id (bucket_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        
        // Füge bucket_id Spalte hinzu, falls sie noch nicht existiert
        $bucketIdExists = $db->query("SHOW COLUMNS FROM users LIKE 'bucket_id'")->fetch();
        if (!$bucketIdExists) {
            $db->exec("ALTER TABLE users ADD COLUMN bucket_id VARCHAR(100) DEFAULT NULL AFTER avatar_url");
            $db->exec("ALTER TABLE users ADD INDEX idx_bucket_id (bucket_id)");
        }
        
        $db->exec(
            'CREATE TABLE IF NOT EXISTS folders (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                name VARCHAR(190) NOT NULL,
                path VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_folder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS files (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                folder_id INT UNSIGNED NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) DEFAULT NULL,
                size_bytes BIGINT UNSIGNED DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_files_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $sizeBytesExists = $db->query("SHOW COLUMNS FROM files LIKE 'size_bytes'")->fetch();
        if (!$sizeBytesExists) {
            $db->exec("ALTER TABLE files ADD COLUMN size_bytes BIGINT UNSIGNED DEFAULT 0 AFTER mime_type");
        }

        $displayNameExists = $db->query("SHOW COLUMNS FROM users LIKE 'display_name'")->fetch();
        if (!$displayNameExists) {
            $db->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(120) DEFAULT NULL AFTER email");
        }

        $avatarExists = $db->query("SHOW COLUMNS FROM users LIKE 'avatar_url'")->fetch();
        if (!$avatarExists) {
            $db->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL AFTER display_name");
        }

        $pathExists = $db->query("SHOW COLUMNS FROM folders LIKE 'path'")->fetch();
        if (!$pathExists) {
            $db->exec("ALTER TABLE folders ADD COLUMN path VARCHAR(255) DEFAULT NULL AFTER name");
        } else if ($pathExists && $pathExists['Null'] === 'NO') {
            $db->exec("ALTER TABLE folders MODIFY path VARCHAR(255) DEFAULT NULL");
        }
    }
    
    // Erstelle Storage-Tabellen in allen Buckets
    foreach (array_keys($buckets) as $bucketName) {
        $storageDb = get_bucket_storage_db($bucketName);
        
        $storageDbExists = false;
        try {
            $storageDb->query('SELECT 1 FROM file_data LIMIT 1')->fetch();
            $storageDbExists = true;
        } catch (Exception $e) {
            // Tabelle existiert noch nicht
        }

        if (!$storageDbExists) {
            $storageDb->exec(
                'CREATE TABLE IF NOT EXISTS file_data (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    file_id INT UNSIGNED NOT NULL,
                    file_content LONGBLOB NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_file_id (file_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }
    
    // Erstelle user_buckets Tabelle in der Hauptdatenbank
    $masterDb = master_db();
    $masterDb->exec(
        'CREATE TABLE IF NOT EXISTS user_buckets (
            user_id INT UNSIGNED PRIMARY KEY,
            bucket_id VARCHAR(100) NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bucket_id (bucket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

ensure_schema();

function turnstile_enabled(): bool
{
    global $config;
    return !empty($config['turnstile_enabled']);
}

function storage_path(?int $userId = null): string
{
    global $config;
    $base = $config['storage_path'];
    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }

    if ($userId === null) {
        return $base;
    }

    $user = fetch_user($userId);
    if (!$user || empty($user['email'])) {
        $dirName = 'user_' . $userId;
    } else {
        $dirName = $user['email'];
    }

    if ($config['ftp_enabled']) {
        $path = $base . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . $dirName;
    } else {
        $path = $base . DIRECTORY_SEPARATOR . $dirName;
    }

    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }

    return $path;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function require_login(): int
{
    $userId = current_user_id();
    if (!$userId) {
        respond_error('Nicht eingeloggt', 401);
    }
    return $userId;
}

function fetch_user(int $userId): ?array
{
    $bucketName = get_user_bucket($userId);
    if ($bucketName === null) {
        return null;
    }
    
    $db = get_bucket_db($bucketName);
    $stmt = $db->prepare('SELECT id, email, display_name, avatar_url, bucket_id, created_at FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function respond_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function respond_error(string $message, int $status = 400, array $extra = []): void
{
    respond_json(array_merge(['error' => $message], $extra), $status);
}

function normalize_filename(string $name): string
{
    $name = preg_replace('/[^\w\.\-]+/u', '_', $name);
    return substr($name, 0, 180);
}

function calculate_storage_usage(int $userId): array
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(size_bytes),0) as bytes FROM files WHERE user_id = :uid');
    $stmt->execute(['uid' => $userId]);
    $bytes = (int) ($stmt->fetchColumn() ?: 0);
    $megabytes = round($bytes / 1024 / 1024, 2);

    return ['bytes' => $bytes, 'megabytes' => $megabytes];
}
