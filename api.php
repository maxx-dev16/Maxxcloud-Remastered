<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/local_storage.php';
require __DIR__ . '/database_storage.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $payload = get_payload();
    $action = $payload['action'] ?? null;
    log_traffic_event((string)$action, current_user_id());

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
        case 'redeem_code':
            handle_redeem_code($payload);
            break;
        case 'traffic_series':
            handle_traffic_series($payload);
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
        case 'avatar':
            handle_avatar($payload);
            break;
        case 'buckets_status':
            handle_buckets_status();
            break;
        case 'request_password_reset':
            handle_request_password_reset($payload);
            break;
        case 'perform_password_reset':
            handle_perform_password_reset($payload);
            break;
        case 'verify_email':
            handle_verify_email($payload);
            break;
        case 'resend_verification':
            handle_resend_verification($payload);
            break;
        case 'create_share_link':
            handle_create_share_link($payload);
            break;
        case 'revoke_share':
            handle_revoke_share($payload);
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
        
        if (!empty($user['banned']) && (int)$user['banned'] === 1) {
            respond_json([
                'user' => null,
                'folders' => [],
                'files' => [],
                'storage' => ['bytes' => 0, 'megabytes' => 0],
                'banned' => true,
                'maintenance' => (bool) get_setting('maintenance_mode', '0'),
            ], 403);
        }

        $folders = fetch_folders($userId);
        $files = fetch_files($userId);
        $storage = calculate_storage_usage($userId);

        $user['storage_limit'] = user_storage_limit_mb($userId);
        $maintenance = (bool) get_setting('maintenance_mode', '0');

        respond_json([
            'user' => $user,
            'folders' => $folders,
            'files' => $files,
            'storage' => $storage,
            'maintenance' => $maintenance,
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

function handle_redeem_code(array $payload): void
{
    $userId = current_user_id();
    if (!$userId) {
        respond_error('Nicht eingeloggt', 401);
    }
    $code = strtoupper(trim($payload['code'] ?? ''));
    if ($code === '') {
        respond_error('Code erforderlich', 422);
    }
    try {
        ensure_codes_schema();
        $db = master_db();
        $stmt = $db->prepare('SELECT * FROM redemption_codes WHERE code = :code AND active = 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        if (!$row) {
            respond_error('Ungültiger Code', 404);
        }
        $expiresOk = empty($row['expires_at']) || strtotime($row['expires_at']) > time();
        if (!$expiresOk) {
            respond_error('Code abgelaufen', 410);
        }
        if ((int)$row['used_count'] >= (int)$row['max_uses']) {
            respond_error('Code bereits aufgebraucht', 409);
        }
        $check = $db->prepare('SELECT 1 FROM storage_grants WHERE user_id = :uid AND code_id = :cid');
        $check->execute(['uid' => $userId, 'cid' => (int)$row['id']]);
        if ($check->fetch()) {
            respond_error('Code bereits verwendet', 409);
        }
        $insert = $db->prepare('INSERT INTO storage_grants (user_id, amount_mb, source, code_id, expires_at) VALUES (:uid, :mb, :src, :cid, :exp)');
        $insert->execute([
            'uid' => $userId,
            'mb' => (int)$row['storage_mb'],
            'src' => 'code',
            'cid' => (int)$row['id'],
            'exp' => $row['expires_at'] ?: null,
        ]);
        $upd = $db->prepare('UPDATE redemption_codes SET used_count = used_count + 1 WHERE id = :id');
        $upd->execute(['id' => (int)$row['id']]);
        $limit = user_storage_limit_mb($userId);
        respond_json(['success' => true, 'new_limit_mb' => $limit]);
    } catch (Throwable $e) {
        respond_error('Einlösen fehlgeschlagen', 500, ['details' => $e->getMessage()]);
    }
}

function handle_request_password_reset(array $payload): void
{
    $email = trim($payload['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond_error('Ungültige Email', 422);
    }
    global $buckets, $config;
    foreach (array_keys($buckets) as $bucketName) {
        try {
            $db = get_bucket_db($bucketName);
            $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
            $stmt->execute(['email'=>$email]);
            $id = $stmt->fetchColumn();
            if($id){
                $token = bin2hex(random_bytes(32));
                $exp = date('Y-m-d H:i:s', time()+3600);
                $db->prepare('UPDATE users SET reset_token = :t, reset_token_expires_at = :e WHERE id = :id')->execute(['t'=>$token,'e'=>$exp,'id'=>$id]);
                $base = $config['base_url'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.($_SERVER['HTTP_HOST'] ?? ''));
                $link = rtrim($base,'/').'/index.php?reset='.$token;
                $html = '<div style="font-family:Segoe UI,Arial,sans-serif;padding:20px"><h2>Maxxcloud</h2><p>Passwort zurücksetzen.</p><p><a href="'.$link+'" style="display:inline-block;padding:10px 16px;background:#0ea5a4;color:#fff;text-decoration:none;border-radius:6px">Passwort zurücksetzen</a></p><p>Falls der Button nicht funktioniert, öffne diesen Link:<br>'.$link.'</p></div>';
                send_mail($email, 'Passwort zurücksetzen', $html);
                break;
            }
        } catch (Exception $e) {}
    }
    respond_json(['success'=>true]);
}

function handle_perform_password_reset(array $payload): void
{
    $token = trim($payload['token'] ?? '');
    $password = (string)($payload['password'] ?? '');
    if(strlen($password) < 8){ respond_error('Passwort zu kurz', 422); }
    if($token === ''){ respond_error('Token fehlt', 422); }
    global $buckets;
    foreach (array_keys($buckets) as $bucketName) {
        try {
            $db = get_bucket_db($bucketName);
            $stmt = $db->prepare('SELECT id, reset_token_expires_at FROM users WHERE reset_token = :t');
            $stmt->execute(['t'=>$token]);
            $row = $stmt->fetch();
            if($row){
                $exp = $row['reset_token_expires_at'] ?? null;
                if($exp && strtotime($exp) < time()){
                    respond_error('Token abgelaufen', 410);
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare('UPDATE users SET password_hash = :h, reset_token = NULL, reset_token_expires_at = NULL, password_reset_at = NOW() WHERE id = :id')->execute(['h'=>$hash,'id'=>$row['id']]);
                respond_json(['success'=>true]);
            }
        } catch (Exception $e) {}
    }
    respond_error('Ungültiger Token', 404);
}

function handle_verify_email(array $payload): void
{
    $token = trim($payload['token'] ?? '');
    global $buckets, $config;
    foreach (array_keys($buckets) as $bucketName) {
        try {
            $db = get_bucket_db($bucketName);
            $stmt = $db->prepare('SELECT id FROM users WHERE verify_token = :t');
            $stmt->execute(['t'=>$token]);
            $id = $stmt->fetchColumn();
            if($id){
                $db->prepare('UPDATE users SET email_verified = 1, email_verified_at = NOW(), verify_token = NULL WHERE id = :id')->execute(['id'=>$id]);
                $base = $config['base_url'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.($_SERVER['HTTP_HOST'] ?? ''));
                header('Location: '.rtrim($base,'/').'/index.php?verified=1');
                exit;
            }
        } catch (Exception $e) {}
    }
    respond_error('Verifizierung fehlgeschlagen', 400);
}

function handle_resend_verification(array $payload): void
{
    $email = trim($payload['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond_error('Ungültige Email', 422);
    }
    global $buckets, $config;
    foreach (array_keys($buckets) as $bucketName) {
        try {
            $db = get_bucket_db($bucketName);
            $stmt = $db->prepare('SELECT id, email_verified FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            $row = $stmt->fetch();
            if ($row) {
                if ((int)($row['email_verified'] ?? 0) === 1) {
                    respond_json(['success' => true, 'already_verified' => true]);
                }
                $token = bin2hex(random_bytes(32));
                $db->prepare('UPDATE users SET verify_token = :t WHERE id = :id')->execute(['t' => $token, 'id' => (int)$row['id']]);
                $base = $config['base_url'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? ''));
                $link = rtrim($base, '/') . '/api.php?action=verify_email&token=' . $token;
                $html = '<div style="font-family:Segoe UI,Arial,sans-serif;padding:20px"><h2>Maxxcloud</h2><p>Bitte bestätige deine Email-Adresse.</p><p><a href="' . $link . '" style="display:inline-block;padding:10px 16px;background:#0ea5a4;color:#fff;text-decoration:none;border-radius:6px">Email bestätigen</a></p><p>Falls der Button nicht funktioniert, öffne diesen Link:<br>' . $link . '</p></div>';
                send_mail($email, 'Bitte bestätige deine Email-Adresse', $html);
                respond_json(['success' => true]);
            }
        } catch (Exception $e) {}
    }
    respond_json(['success' => true]);
}

function handle_create_share_link(array $payload): void
{
    $userId = current_user_id();
    if (!$userId) { respond_error('Nicht eingeloggt', 401); }
    $fileId = (int)($payload['file_id'] ?? 0);
    if ($fileId <= 0) { respond_error('Ungültige Datei', 422); }
    $hours = max(1, (int)($payload['expires_hours'] ?? 168));
    $exp = date('Y-m-d H:i:s', time() + $hours*3600);
    try {
        $stmt = db()->prepare('SELECT id FROM files WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id'=>$fileId,'uid'=>$userId]);
        if(!$stmt->fetch()) { respond_error('Keine Berechtigung', 403); }
        $token = bin2hex(random_bytes(16));
        db()->prepare('INSERT INTO shares (user_id, file_id, token, expires_at) VALUES (:u,:f,:t,:e)')
            ->execute(['u'=>$userId,'f'=>$fileId,'t'=>$token,'e'=>$exp]);
        global $config; $base = $config['base_url'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.($_SERVER['HTTP_HOST'] ?? ''));
        $url = rtrim($base,'/').'/download.php?share='.$token;
        respond_json(['success'=>true,'url'=>$url,'expires_at'=>$exp]);
    } catch (Throwable $e) { respond_error('Freigabe fehlgeschlagen', 500, ['details'=>$e->getMessage()]); }
}

function handle_revoke_share(array $payload): void
{
    $userId = current_user_id();
    if (!$userId) { respond_error('Nicht eingeloggt', 401); }
    $token = trim($payload['token'] ?? '');
    if ($token === '') { respond_error('Token fehlt', 422); }
    try {
        db()->prepare('UPDATE shares SET revoked = 1 WHERE token = :t AND user_id = :u')->execute(['t'=>$token,'u'=>$userId]);
        respond_json(['success'=>true]);
    } catch (Throwable $e) { respond_error('Widerruf fehlgeschlagen', 500, ['details'=>$e->getMessage()]); }
}

function ensure_codes_schema(): void
{
    try {
        $db = master_db();
        $db->exec(
            'CREATE TABLE IF NOT EXISTS redemption_codes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(64) NOT NULL UNIQUE,
                storage_mb INT UNSIGNED NOT NULL,
                max_uses INT UNSIGNED NOT NULL,
                used_count INT UNSIGNED NOT NULL DEFAULT 0,
                expires_at DATETIME NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS storage_grants (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                amount_mb INT UNSIGNED NOT NULL,
                source VARCHAR(20) NOT NULL,
                code_id INT UNSIGNED NULL,
                expires_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_code (code_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $e) {
        // Ignorieren, Handler fängt Fehler später ab
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
                if (!empty($candidate['banned']) && (int)$candidate['banned'] === 1) {
                    respond_error('Account gesperrt', 403);
                }
                if (empty($candidate['email_verified']) || (int)$candidate['email_verified'] !== 1) {
                    respond_error('Bitte bestätige deine Email', 403, ['unverified' => true]);
                }
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
    try {
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
                error_log("Fehler beim Prüfen der Email in Bucket {$bucketName}: " . $e->getMessage());
                continue;
            }
        }

        // Wähle den am wenigsten ausgelasteten Bucket
        try {
            $selectedBucket = get_least_loaded_bucket();
        } catch (Exception $e) {
            error_log("Fehler beim Ermitteln des Buckets: " . $e->getMessage());
            respond_error('Registrierung fehlgeschlagen: Kein Bucket verfügbar', 500);
        }

        try {
            $pdo = get_bucket_db($selectedBucket);
        } catch (Exception $e) {
            $errorCode = $e->getCode();
            $errorMsg = $e->getMessage();
            error_log("Fehler beim Verbinden mit Bucket {$selectedBucket}: " . $errorMsg);
            
            // Detaillierte Fehlermeldung für den Benutzer
            if ($errorCode == 1045) {
                $userMsg = 'Registrierung fehlgeschlagen: Falscher Benutzername oder Passwort';
            } elseif ($errorCode == 1044) {
                $userMsg = 'Registrierung fehlgeschlagen: Kein Zugriff auf Datenbank. Bitte Datenbankname prüfen.';
            } elseif ($errorCode == 1049) {
                $userMsg = 'Registrierung fehlgeschlagen: Datenbank existiert nicht. Bitte Datenbankname prüfen.';
            } elseif ($errorCode == 2002) {
                $userMsg = 'Registrierung fehlgeschlagen: Datenbankserver nicht erreichbar';
            } else {
                $userMsg = 'Registrierung fehlgeschlagen: Datenbankverbindung fehlgeschlagen (' . $errorCode . ')';
            }
            
            respond_error($userMsg, 500, ['details' => $errorMsg]);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $displayName = explode('@', $email)[0];
        
        try {
            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, display_name, bucket_id) VALUES (:email, :hash, :display, :bucket_id)');
            $stmt->execute([
                'email' => $email,
                'hash' => $hash,
                'display' => $displayName,
                'bucket_id' => $selectedBucket
            ]);
            $userId = (int) $pdo->lastInsertId();
            $verifyToken = bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE users SET email_verified = 0, verify_token = :t WHERE id = :id')->execute(['t'=>$verifyToken,'id'=>$userId]);
            global $config;
            $base = $config['base_url'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.($_SERVER['HTTP_HOST'] ?? ''));
            $link = rtrim($base,'/').'/api.php?action=verify_email&token='.$verifyToken;
            $html = '<div style="font-family:Segoe UI,Arial,sans-serif;padding:20px"><h2>Maxxcloud</h2><p>Bitte bestätige deine Email-Adresse.</p><p><a href="'.$link.'" style="display:inline-block;padding:10px 16px;background:#0ea5a4;color:#fff;text-decoration:none;border-radius:6px">Email bestätigen</a></p><p>Falls der Button nicht funktioniert, öffne diesen Link:<br>'.$link.'</p></div>';
            send_mail($email, 'Bitte bestätige deine Email-Adresse', $html);
        } catch (Exception $e) {
            error_log("Fehler beim Erstellen des Users: " . $e->getMessage());
            respond_error('Registrierung fehlgeschlagen: ' . $e->getMessage(), 500);
        }

        // Speichere Zuordnung in der Hauptdatenbank (nicht kritisch, wenn es fehlschlägt)
        try {
            assign_user_to_bucket($userId, $selectedBucket);
        } catch (Exception $e) {
            error_log("Fehler beim Speichern der Bucket-Zuordnung: " . $e->getMessage());
            // Nicht kritisch, weiter mit Registrierung
        }

        // Erstelle Storage-Pfad (nicht kritisch, wenn es fehlschlägt)
        try {
            storage_path($userId);
        } catch (Exception $e) {
            error_log("Fehler beim Erstellen des Storage-Pfads: " . $e->getMessage());
            // Nicht kritisch, weiter mit Registrierung
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_bucket_id'] = $selectedBucket;
        respond_json(['success' => true, 'user_id' => $userId]);
        
    } catch (Throwable $e) {
        error_log("Fataler Fehler bei Registrierung: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        respond_error('Registrierung fehlgeschlagen: ' . $e->getMessage(), 500);
    }
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
    $maxFileBytes = 50 * 1024 * 1024 * 1024; // 50 GB
    if ($fileSize > $maxFileBytes) {
        respond_error('Datei überschreitet maximale Größe von 50GB', 413, [
            'details' => 'Maximale Dateigröße überschritten',
            'max_file_mb' => 51200,
            'file_mb' => round($fileSize/1024/1024,2)
        ]);
    }
    $usage = calculate_storage_usage($userId);
    $limitMb = user_storage_limit_mb($userId);
    $limitBytes = $limitMb * 1024 * 1024;
    if (($usage['bytes'] + $fileSize) > $limitBytes) {
        respond_error('Speicherlimit erreicht. Bitte Speicher erweitern oder Dateien löschen.', 413, [
            'details' => 'Limit überschritten',
            'limit_mb' => $limitMb,
            'current_mb' => $usage['megabytes'],
            'file_mb' => round($fileSize/1024/1024,2)
        ]);
    }

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
        $storage = get_storage();
        $storage->putFile($file['tmp_name'], $userId, $fileId);
        
        // Für DatabaseStorage gibt es keine getStoredFileName, verwende leeren String
        $storedName = '';
        if (method_exists($storage, 'getStoredFileName')) {
            $storedName = $storage->getStoredFileName($userId, $fileId);
        }
        
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
        $storage = get_storage();
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

    $storage = get_storage();
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
            $len = function_exists('mb_strlen') ? mb_strlen($displayName) : strlen($displayName);
            if ($len > 80) {
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

function handle_avatar(array $payload): void
{
    $userId = require_login();
    $path = (string) ($payload['path'] ?? ($_GET['path'] ?? ''));
    if ($path === '') {
        respond_error('Pfad fehlt', 422);
    }
    $filename = basename($path);
    try {
        $full = storage_path($userId) . DIRECTORY_SEPARATOR . 'avatar' . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($full)) {
            respond_error('Avatar nicht gefunden', 404);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($full) ?: 'image/png';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($full));
        readfile($full);
        exit;
    } catch (Throwable $e) {
        respond_error('Avatar laden fehlgeschlagen', 500, ['details' => $e->getMessage()]);
    }
}

function handle_buckets_status(): void
{
    global $buckets;
    $list = [];
    foreach (array_keys($buckets) as $bn) {
        $list[] = [
            'bucket_id' => $bn,
            'active' => is_bucket_active($bn),
        ];
    }
    respond_json(['buckets' => $list]);
}

function handle_traffic_series(array $payload): void
{
    $minutes = max(1, min(240, (int)($payload['minutes'] ?? 60)));
    try {
        $db = master_db();
        $rows = $db->prepare('SELECT DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") AS t, COUNT(*) AS c FROM traffic_events WHERE created_at > NOW() - INTERVAL :m MINUTE GROUP BY t ORDER BY t');
        $rows->execute(['m' => $minutes]);
        $data = $rows->fetchAll();
        respond_json(['series' => $data]);
    } catch (Throwable $e) {
        respond_error('Traffic Serie fehlgeschlagen', 500, ['details' => $e->getMessage()]);
    }
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

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
    }
    if ($mime === '' && function_exists('exif_imagetype')) {
        $type = @exif_imagetype($tmp);
        $map = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_WEBP => 'image/webp'];
        $mime = $map[$type] ?? '';
    }
    if ($mime === '') {
        $ext = strtolower(pathinfo(($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : ($ext === 'webp' ? 'image/webp' : ''));
    }
    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        respond_error('Nur PNG, JPG oder WEBP erlaubt', 422);
    }

    $filename = 'avatar_' . uniqid('', true) . '.' . $allowed[$mime];

    {
        $userDir = storage_path($userId) . DIRECTORY_SEPARATOR . 'avatar';
        if (!is_dir($userDir)) {
            mkdir($userDir, 0775, true);
        }

        foreach (glob($userDir . DIRECTORY_SEPARATOR . 'avatar_*') as $previous) {
            @unlink($previous);
        }

        $target = $userDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmp, $target)) {
            if (!@copy($tmp, $target)) {
                $data = @file_get_contents($tmp);
                if ($data === false || @file_put_contents($target, $data) === false) {
                    respond_error('Avatar konnte nicht gespeichert werden', 500);
                }
            }
        }
    }

    $user = fetch_user($userId);
    $dirName = (!$user || empty($user['email'])) ? 'user_' . $userId : $user['email'];
    return $dirName . '/avatar/' . $filename;
}
