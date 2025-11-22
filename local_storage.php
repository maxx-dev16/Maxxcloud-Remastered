<?php

declare(strict_types=1);

class LocalFileStorage
{
    public function putFile(string $localPath, int $userId, int $fileId): bool
    {
        if (!is_file($localPath)) {
            throw new Exception("Lokale Datei nicht gefunden: {$localPath}");
        }

        if (!is_readable($localPath)) {
            throw new Exception("Lokale Datei nicht lesbar: {$localPath}");
        }

        $userStoragePath = storage_path($userId);
        $fileName = $this->generateFileName($fileId);
        $targetPath = $userStoragePath . DIRECTORY_SEPARATOR . $fileName;

        if (!copy($localPath, $targetPath)) {
            throw new Exception("Datei konnte nicht in Speicher kopiert werden: {$localPath} -> {$targetPath}");
        }

        return true;
    }

    public function getFile(string $localPath, int $userId, int $fileId): bool
    {
        $userStoragePath = storage_path($userId);
        $fileName = $this->generateFileName($fileId);
        $sourcePath = $userStoragePath . DIRECTORY_SEPARATOR . $fileName;

        if (!is_file($sourcePath)) {
            throw new Exception("Datei nicht in Speicher gefunden: {$sourcePath}");
        }

        $localDir = dirname($localPath);
        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        if (!copy($sourcePath, $localPath)) {
            throw new Exception("Datei konnte nicht kopiert werden: {$sourcePath} -> {$localPath}");
        }

        return true;
    }

    public function deleteFile(int $userId, int $fileId): bool
    {
        $userStoragePath = storage_path($userId);
        $fileName = $this->generateFileName($fileId);
        $filePath = $userStoragePath . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($filePath)) {
            if (!unlink($filePath)) {
                throw new Exception("Datei konnte nicht gelöscht werden: {$filePath}");
            }
        }

        return true;
    }

    public function fileExists(int $userId, int $fileId): bool
    {
        $userStoragePath = storage_path($userId);
        $fileName = $this->generateFileName($fileId);
        $filePath = $userStoragePath . DIRECTORY_SEPARATOR . $fileName;

        return is_file($filePath);
    }

    public function getFileSize(int $userId, int $fileId): int
    {
        $userStoragePath = storage_path($userId);
        $fileName = $this->generateFileName($fileId);
        $filePath = $userStoragePath . DIRECTORY_SEPARATOR . $fileName;

        if (!is_file($filePath)) {
            return 0;
        }

        $size = filesize($filePath);
        return $size !== false ? (int) $size : 0;
    }

    public function getStoredFileName(int $userId, int $fileId): string
    {
        return $this->generateFileName($fileId);
    }

    private function generateFileName(int $fileId): string
    {
        return 'file_' . $fileId;
    }
}

function get_local_file_storage(): LocalFileStorage
{
    static $storage = null;

    if ($storage === null) {
        $storage = new LocalFileStorage();
    }

    return $storage;
}
