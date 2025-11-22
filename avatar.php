<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ftp.php';

global $config;

$userId = isset($_GET['user']) ? (int) $_GET['user'] : 0;
if ($userId <= 0) {
    http_response_code(404);
    exit;
}

$user = fetch_user($userId);
if (!$user || empty($user['avatar_url'])) {
    http_response_code(404);
    exit;
}

$avatarRef = $user['avatar_url'];
if (preg_match('#^https?://#i', $avatarRef)) {
    header('Location: ' . $avatarRef);
    exit;
}

$relative = str_replace(['..', '\\'], '', $avatarRef);

if ($config['ftp_enabled']) {
    $ftp = get_ftp_storage();
    $remotePath = 'zap1265543/' . $relative;

    $tempFile = tempnam(sys_get_temp_dir(), 'avatar_');

    try {
        $ftp->getFile($remotePath, $tempFile);
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tempFile) ?: 'image/png';
        
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($tempFile);
        @unlink($tempFile);
    } catch (Exception $e) {
        http_response_code(404);
        @unlink($tempFile);
    }
} else {
    $filePath = storage_path() . DIRECTORY_SEPARATOR . $relative;

    if (!is_file($filePath)) {
        http_response_code(404);
        exit;
    }

    $mime = mime_content_type($filePath) ?: 'image/png';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    readfile($filePath);
}

exit;




