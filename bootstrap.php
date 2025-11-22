<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Berlin');

function db(): PDO
{
    static $pdo;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function storage_db(): PDO
{
    static $pdo;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['storage_db_host'],
        $config['storage_db_port'],
        $config['storage_db_name']
    );

    $pdo = new PDO($dsn, $config['storage_db_user'], $config['storage_db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ensure_schema(): void
{
    $db = db();

    $db->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(120) DEFAULT NULL,
            avatar_url VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

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

    $storageDbExists = false;
    try {
        storage_db()->query('SELECT 1 FROM file_data LIMIT 1')->fetch();
        $storageDbExists = true;
    } catch (Exception $e) {
        // Tabelle existiert noch nicht
    }

    if (!$storageDbExists) {
        $storageDb = storage_db();
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
    $stmt = db()->prepare('SELECT id, email, display_name, avatar_url, created_at FROM users WHERE id = :id');
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



