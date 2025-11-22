<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ftp.php';
require __DIR__ . '/local_storage.php';

global $config;

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

$storage = get_local_file_storage();
$userStoragePath = storage_path($userId);
$fileName = $storage->getStoredFileName($userId, $fileId);
$path = $userStoragePath . DIRECTORY_SEPARATOR . $fileName;

if (!is_file($path)) {
    http_response_code(404);
    echo 'Datei fehlt im Speicher';
    exit;
}

readfile($path);

exit;






