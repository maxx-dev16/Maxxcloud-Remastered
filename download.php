<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/local_storage.php';
require __DIR__ . '/database_storage.php';

global $config;

if (isset($_GET['share'])) {
    $token = trim($_GET['share']);
    global $buckets;
    $match = null; $bucketName = null;
    foreach (array_keys($buckets) as $bn) {
        try {
            $pdo = get_bucket_db($bn);
            $stmt = $pdo->prepare('SELECT user_id, file_id, expires_at, revoked FROM shares WHERE token = :t');
            $stmt->execute(['t'=>$token]);
            $row = $stmt->fetch();
            if ($row) { $match = $row; $bucketName = $bn; break; }
        } catch (Throwable $e) {}
    }
    if (!$match) { http_response_code(404); echo 'Ungültiger Link'; exit; }
    if (!empty($match['revoked'])) { http_response_code(410); echo 'Link widerrufen'; exit; }
    $exp = $match['expires_at'] ?? null; if ($exp && strtotime($exp) < time()) { http_response_code(410); echo 'Link abgelaufen'; exit; }
    try {
        $pdo = get_bucket_db($bucketName);
        $stmt = $pdo->prepare('SELECT original_name, mime_type, size_bytes FROM files WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => (int)$match['file_id'], 'uid' => (int)$match['user_id']]);
        $file = $stmt->fetch();
        if (!$file) { http_response_code(404); echo 'Datei nicht gefunden'; exit; }
        $mime = $file['mime_type'] ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) ($file['size_bytes'] ?? 0));
        header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
        global $config; $mode = $config['storage_mode'] ?? 'database';
        if ($mode === 'database') {
            $sdb = get_bucket_storage_db($bucketName);
            $st = $sdb->prepare('SELECT file_content FROM file_data WHERE user_id = :u AND file_id = :f');
            $st->execute(['u'=>(int)$match['user_id'],'f'=>(int)$match['file_id']]);
            $row = $st->fetch(); if(!$row){ http_response_code(404); echo 'Datei fehlt im Speicher'; exit; }
            echo $row['file_content'];
        } else {
            $userStoragePath = storage_path((int)$match['user_id']);
            $path = $userStoragePath . DIRECTORY_SEPARATOR . 'file_' . (int)$match['file_id'];
            if (!is_file($path)) { http_response_code(404); echo 'Datei fehlt im Speicher'; exit; }
            readfile($path);
        }
        try { $pdo->prepare('UPDATE shares SET downloads = downloads + 1 WHERE token = :t')->execute(['t'=>$token]); } catch (Throwable $e) {}
        exit;
    } catch (Throwable $e) { http_response_code(500); echo 'Fehler'; exit; }
}

$userId = current_user_id();
if (!$userId) {
    http_response_code(401);
    echo 'Nicht eingeloggt';
    exit;
}

$fileId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($fileId <= 0) {
    http_response_code(400);
    echo 'Ungültige Datei-ID';
    exit;
}

$stmt = db()->prepare('SELECT original_name, mime_type, size_bytes FROM files WHERE id = :id AND user_id = :uid');
$stmt->execute(['id' => $fileId, 'uid' => $userId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    echo 'Datei nicht gefunden';
    exit;
}

$mime = $file['mime_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) ($file['size_bytes'] ?? 0));
header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');

$storage = get_storage();
if ($storage instanceof DatabaseStorage) {
    try {
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'download_' . $fileId . '_' . uniqid();
        $storage->getFile($tmpPath, $userId, $fileId);
        if (is_file($tmpPath)) { readfile($tmpPath); unlink($tmpPath); }
        else { http_response_code(404); echo 'Datei fehlt im Speicher'; }
    } catch (Exception $e) { http_response_code(500); echo 'Fehler beim Laden der Datei'; }
} else {
    $userStoragePath = storage_path($userId);
    $fileName = method_exists($storage, 'getStoredFileName') ? $storage->getStoredFileName($userId, $fileId) : 'file_' . $fileId;
    $path = $userStoragePath . DIRECTORY_SEPARATOR . $fileName;
    if (!is_file($path)) { http_response_code(404); echo 'Datei fehlt im Speicher'; exit; }
    readfile($path);
}

exit;





