<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ftp.php';
require __DIR__ . '/local_storage.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $payload = get_payload();
    $action = $payload['action'] ?? null;

    switch ($action) {
        case 'status':
            handle_status();
            break;
        case 'login':
            handle_login($payload);
            break;
        case 'register':
            handle_register($payload);
            break;
        case 'logout':
            handle_logout();
            break;
        case 'create_folder':
            handle_create_folder($payload);
            break;
        case 'upload_file':
            handle_upload();
            break;
        case 'delete_file':
            handle_delete_file($payload);
            break;
        case 'delete_folder':
            handle_delete_folder($payload);
            break;
        case 'update_profile':
            handle_update_profile($payload);
            break;
        default:
            respond_error('Unbekannte Aktion', 400);
    }
} catch (Throwable $e) {
    respond_error('Serverfehler', 500, ['details' => $e->getMessage()]);
}

function get_payload(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    return $_GET ? $_GET : [];
}

function handle_status(): void
{
    $userId = current_user_id();

    if (!$userId) {
        respond_json([
            'user' => null,
            'folders' => [],
            'files' => [],
            'storage' => ['bytes' => 0, 'megabytes' => 0],
        ]);
    }

    try {
        $user = fetch_user($userId);
        
        if (!$user) {
            // User nicht gefunden oder kein Bucket zugeordnet
            respond_json([
                'user' => null,
                'folders' => [],
                'files' => [],
                'storage' => ['bytes' => 0, 'megabytes' => 0],
            ]);
        }
        
        $folders = fetch_folders($userId);
        $files = fetch_files($userId);
        $storage = calculate_storage_usage($userId);

        global $config;
        $user['storage_limit'] = $config['default_storage_limit_mb'];

        respond_json([
            'user' => $user,
            'folders' => $folders,
            'files' => $files,
            'storage' => $storage,
        ]);
    } catch (Exception $e) {
        // Fehler beim Zugriff auf Bucket - gebe leere Daten zurück
        respond_json([
            'user' => null,
            'folders' => [],
            'files' => [],
            'storage' => ['bytes' => 0, 'megabytes' => 0],
        ]);
    }
}

function handle_login(array $payload): void
{
    $email = trim($payload['email'] ?? '');
    $password = $payload['password'] ?? '';
    $cfToken = $payload['cfToken'] ?? '';

    if (!$email || !$password) {
        respond_error('Email und Passwort sind erforderlich', 422);
    }

    if (turnstile_enabled() && !verify_turnstile($cfToken)) {
        respond_error('Captcha ungültig oder abgelaufen', 403);
    }

    // Suche User in allen Buckets
    global $buckets;
    $user = null;
    $foundBucket = null;
    
    foreach (array_keys($buckets) as $bucketName) {
        try {
            $db = get_bucket_db($bucketName);
            $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            $candidate = $stmt->fetch();
            
            if ($candidate && password_verify($password, $candidate['password_hash'])) {
                $user = $candidate;
                $foundBucket = $bucketName;
                break;
            }
        } catch (Exception $e) {
            // Bucket nicht erreichbar, weiter zum nächsten
            continue;
        }
    }

    if (!$user) {
        respond_error('Login fehlgeschlagen', 401);
    }
    
    $userId = (int) $user['id'];
    $bucketName = $user['bucket_id'] ?: $foundBucket;
    
    // Aktualisiere bucket_id im Bucket, falls noch nicht gesetzt
    if (empty($user['bucket_id'])) {
        $db = get_bucket_db($foundBucket);
        $stmt = $db->prepare('UPDATE users SET bucket_id = :bucket_id WHERE id = :id');
        $stmt->execute(['bucket_id' => $foundBucket, 'id' => $userId]);
    }
    
    // Speichere Zuordnung in der Hauptdatenbank
    assign_user_to_bucket($userId, $bucketName);
    
    // Speichere bucket_id in Session für schnellen Zugriff
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_bucket_id'] = $bucketName;
    respond_json(['success' => true]);
}

function handle_register(array $payload): void
{
    $email = trim($payload['email'] ?? '');
    $password = $payload['password'] ?? '';
    $cfToken = $payload['cfToken'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond_error('Ungültige Email', 422);
    }

    if (strlen($password) < 8) {
        respond_error('Passwort muss mindestens 8 Zeichen lang sein', 422);
    }

    if (turnstile_enabled() && !verify_turnstile($cfToken)) {
        respond_error('Captcha ungültig oder abgelaufen', 403);
    }

    // Prüfe, ob Email bereits in einem Bucket existiert
    global $buckets;
    foreach (array_keys($buckets) as $bucketName) {
        try {
            $db = get_bucket_db($bucketName);
            $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                respond_error('Email bereits registriert', 409);
            }
        } catch (Exception $e) {
            // Bucket nicht erreichbar, weiter zum nächsten
            continue;
        }
    }

    // Wähle den am wenigsten ausgelasteten Bucket
    $selectedBucket = get_least_loaded_bucket();
    $pdo = get_bucket_db($selectedBucket);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $displayName = explode('@', $email)[0];
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, display_name, bucket_id) VALUES (:email, :hash, :display, :bucket_id)');
    $stmt->execute([
        'email' => $email,
        'hash' => $hash,
        'display' => $displayName,
        'bucket_id' => $selectedBucket
    ]);
    $userId = (int) $pdo->lastInsertId();

    // Speichere Zuordnung in der Hauptdatenbank
    assign_user_to_bucket($userId, $selectedBucket);

    storage_path($userId); // ensure folder exists

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_bucket_id'] = $selectedBucket;
    respond_json(['success' => true, 'user_id' => $userId]);
}

function handle_logout(): void
{
    unset($_SESSION['user_id']);
    unset($_SESSION['user_bucket_id']);
    session_regenerate_id(true);
    respond_json(['success' => true]);
}

function handle_create_folder(array $payload): void
{
    $userId = require_login();
    $name = trim($payload['name'] ?? '');
    if ($name === '') {
        respond_error('Name darf nicht leer sein', 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO folders (user_id, name) VALUES (:uid, :name)');
    $stmt->execute(['uid' => $userId, 'name' => $name]);
    $folderId = (int) $pdo->lastInsertId();

    respond_json(['success' => true, 'folder' => ['id' => $folderId, 'name' => $name]]);
}

function handle_upload(): void
{
    global $config;
    $userId = require_login();

    $folderId = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? (int) $_POST['folder_id'] : null;

    if ($folderId !== null && !folder_belongs_to_user($folderId, $userId)) {
        respond_error('Ordner gehört nicht zum Benutzer', 403);
    }

    if (!isset($_FILES['file'])) {
        respond_error('Keine Datei gefunden', 400);
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond_error('Upload fehlgeschlagen', 400, ['code' => $file['error']]);
    }

    $pdo = db();
    $originalName = $file['name'];
    $fileSize = filesize($file['tmp_name']);

    $stmt = $pdo->prepare('INSERT INTO files (user_id, folder_id, original_name, stored_name, mime_type, size_bytes) VALUES (:uid, :folder, :orig, :stored, :mime, :size)');
    $stmt->execute([
        'uid' => $userId,
        'folder' => $folderId,
        'orig' => $originalName,
        'stored' => '',
        'mime' => $file['type'] ?? null,
        'size' => $fileSize,
    ]);
    $fileId = (int) $pdo->lastInsertId();

    try {
        $storage = get_local_file_storage();
        $storage->putFile($file['tmp_name'], $userId, $fileId);
        
        $storedName = $storage->getStoredFileName($userId, $fileId);
        $stmt = $pdo->prepare('UPDATE files SET stored_name = :stored WHERE id = :id');
        $stmt->execute(['stored' => $storedName, 'id' => $fileId]);
    } catch (Exception $e) {
        $pdo->prepare('DELETE FROM files WHERE id = :id AND user_id = :uid')->execute(['id' => $fileId, 'uid' => $userId]);
        respond_error('Datei-Speicherung fehlgeschlagen: ' . $e->getMessage(), 500, ['details' => $e->getMessage()]);
    }

    respond_json(['success' => true]);
}

function handle_delete_file(array $payload): void
{
    global $config;
    $userId = require_login();
    $fileId = isset($payload['file_id']) ? (int) $payload['file_id'] : 0;
    if (!$fileId) {
        respond_error('Datei-ID fehlt', 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM files WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $fileId, 'uid' => $userId]);

    if (!$stmt->fetch()) {
        respond_error('Datei nicht gefunden', 404);
    }

    try {
        $storage = get_local_file_storage();
        $storage->deleteFile($userId, $fileId);
    } catch (Exception $e) {
        error_log('Datei-Löschung fehlgeschlagen - ' . $e->getMessage());
    }

    $stmt = $pdo->prepare('DELETE FROM files WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $fileId, 'uid' => $userId]);

    respond_json(['success' => true]);
}

function handle_delete_folder(array $payload): void
{
    global $config;
    $userId = require_login();
    $folderId = isset($payload['folder_id']) ? (int) $payload['folder_id'] : 0;
    if (!$folderId) {
        respond_error('Ordner-ID fehlt', 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM folders WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $folderId, 'uid' => $userId]);
    if (!$stmt->fetch()) {
        respond_error('Ordner nicht gefunden', 404);
    }

    $stmt = $pdo->prepare('SELECT id FROM files WHERE folder_id = :folder_id AND user_id = :uid');
    $stmt->execute(['folder_id' => $folderId, 'uid' => $userId]);
    $files = $stmt->fetchAll();

    $storage = get_local_file_storage();
    foreach ($files as $file) {
        try {
            $storage->deleteFile($userId, (int) $file['id']);
        } catch (Exception $e) {
            error_log('Datei-Löschung fehlgeschlagen - ' . $e->getMessage());
        }
    }

    $stmt = $pdo->prepare('DELETE FROM files WHERE folder_id = :folder_id AND user_id = :uid');
    $stmt->execute(['folder_id' => $folderId, 'uid' => $userId]);

    $stmt = $pdo->prepare('DELETE FROM folders WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $folderId, 'uid' => $userId]);

    respond_json(['success' => true]);
}

function handle_update_profile(array $payload): void
{
    $userId = require_login();
    $pdo = db();

    $fields = [];
    $params = ['id' => $userId];

    if (array_key_exists('display_name', $payload)) {
        $displayName = trim((string) $payload['display_name']);
        if ($displayName === '') {
            $fields[] = 'display_name = NULL';
        } else {
            if (mb_strlen($displayName) > 80) {
                respond_error('Name darf maximal 80 Zeichen lang sein', 422);
            }
            $fields[] = 'display_name = :display_name';
            $params['display_name'] = $displayName;
        }
    }

    $avatarFile = $_FILES['avatar'] ?? null;
    if ($avatarFile && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedPath = store_user_avatar($userId, $avatarFile);
        $fields[] = 'avatar_url = :avatar_url';
        $params['avatar_url'] = $storedPath;
    } elseif (array_key_exists('avatar_url', $payload)) {
        $avatarUrl = trim((string) $payload['avatar_url']);
        if ($avatarUrl === '') {
            $fields[] = 'avatar_url = NULL';
        } else {
            if (!filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                respond_error('Ungültige Avatar-URL', 422);
            }
            $fields[] = 'avatar_url = :avatar_url';
            $params['avatar_url'] = $avatarUrl;
        }
    }

    if (!$fields) {
        respond_json(['success' => true]);
    }

    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    respond_json(['success' => true]);
}

function fetch_folders(int $userId): array
{
    $stmt = db()->prepare('SELECT id, name FROM folders WHERE user_id = :uid ORDER BY name ASC');
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function fetch_files(int $userId): array
{
    $stmt = db()->prepare('SELECT id, folder_id, original_name, mime_type, size_bytes, created_at FROM files WHERE user_id = :uid ORDER BY created_at DESC');
    $stmt->execute(['uid' => $userId]);
    $files = [];
    foreach ($stmt as $row) {
        $row['size_kb'] = round(($row['size_bytes'] ?? 0) / 1024, 1);
        $files[] = $row;
    }
    return $files;
}

function folder_belongs_to_user(int $folderId, int $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM folders WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $folderId, 'uid' => $userId]);
    return (bool) $stmt->fetchColumn();
}

function verify_turnstile(?string $token): bool
{
    if (!turnstile_enabled()) {
        return true;
    }

    global $config;

    if (!$token) {
        return false;
    }

    $secret = $config['turnstile_secret'];
    if (!$secret || str_contains($secret, '000000000')) {
        // Secret placeholder -> fail fast so admin notices
        return false;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]),
        CURLOPT_TIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        return false;
    }

    $data = json_decode($response, true);
    return !empty($data['success']);
}

function store_user_avatar(int $userId, array $file): string
{
    global $config;

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        respond_error('Avatar-Upload fehlgeschlagen', 400);
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        respond_error('Avatar darf maximal 2 MB groß sein', 422);
    }

    $tmp = $file['tmp_name'] ?? null;
    if (!$tmp || !is_uploaded_file($tmp)) {
        respond_error('Ungültiger Upload', 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        respond_error('Nur PNG, JPG oder WEBP erlaubt', 422);
    }

    $filename = 'avatar_' . uniqid('', true) . '.' . $allowed[$mime];

    if ($config['ftp_enabled']) {
        $ftp = get_ftp_storage();
        $remotePath = 'zap1265543/user_' . $userId . '/avatar/' . $filename;

        try {
            $ftp->putFile($tmp, $remotePath);
            
            $avatarDir = 'zap1265543/user_' . $userId . '/avatar';
            $files = @$ftp->listFiles($avatarDir);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file !== $filename && strpos($file, 'avatar_') === 0) {
                        try {
                            $ftp->deleteFile($avatarDir . '/' . $file);
                        } catch (Exception $e) {
                        }
                    }
                }
            }
        } catch (Exception $e) {
            respond_error('Avatar-Upload fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    } else {
        $userDir = storage_path($userId) . DIRECTORY_SEPARATOR . 'avatar';
        if (!is_dir($userDir)) {
            mkdir($userDir, 0775, true);
        }

        foreach (glob($userDir . DIRECTORY_SEPARATOR . 'avatar_*') as $previous) {
            @unlink($previous);
        }

        $target = $userDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmp, $target)) {
            respond_error('Avatar konnte nicht gespeichert werden', 500);
        }
    }

    $user = fetch_user($userId);
    $dirName = (!$user || empty($user['email'])) ? 'user_' . $userId : $user['email'];
    return $dirName . '/avatar/' . $filename;
}


