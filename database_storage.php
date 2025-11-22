<?php

declare(strict_types=1);

class DatabaseStorage
{
    public function putFile(string $localPath, int $userId, int $fileId): bool
    {
        if (!is_file($localPath)) {
            throw new Exception("Lokale Datei nicht gefunden: {$localPath}");
        }

        if (!is_readable($localPath)) {
            throw new Exception("Lokale Datei nicht lesbar: {$localPath}");
        }

        $fileContent = file_get_contents($localPath);
        if ($fileContent === false) {
            throw new Exception("Datei konnte nicht gelesen werden: {$localPath}");
        }

        $storageDb = storage_db();
        $stmt = $storageDb->prepare(
            'INSERT INTO file_data (user_id, file_id, file_content) 
             VALUES (:user_id, :file_id, :file_content)
             ON DUPLICATE KEY UPDATE file_content = :file_content'
        );

        $stmt->execute([
            'user_id' => $userId,
            'file_id' => $fileId,
            'file_content' => $fileContent,
        ]);

        return true;
    }

    public function getFile(string $localPath, int $userId, int $fileId): bool
    {
        $storageDb = storage_db();
        $stmt = $storageDb->prepare(
            'SELECT file_content FROM file_data WHERE user_id = :user_id AND file_id = :file_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'file_id' => $fileId,
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception("Datei nicht in Speicher gefunden: user_id={$userId}, file_id={$fileId}");
        }

        $localDir = dirname($localPath);
        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        $result = file_put_contents($localPath, $row['file_content']);
        if ($result === false) {
            throw new Exception("Datei konnte nicht geschrieben werden: {$localPath}");
        }

        return true;
    }

    public function deleteFile(int $userId, int $fileId): bool
    {
        $storageDb = storage_db();
        $stmt = $storageDb->prepare(
            'DELETE FROM file_data WHERE user_id = :user_id AND file_id = :file_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'file_id' => $fileId,
        ]);

        return true;
    }

    public function fileExists(int $userId, int $fileId): bool
    {
        $storageDb = storage_db();
        $stmt = $storageDb->prepare(
            'SELECT 1 FROM file_data WHERE user_id = :user_id AND file_id = :file_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'file_id' => $fileId,
        ]);

        return (bool) $stmt->fetch();
    }

    public function getFileSize(int $userId, int $fileId): int
    {
        $storageDb = storage_db();
        $stmt = $storageDb->prepare(
            'SELECT OCTET_LENGTH(file_content) as size FROM file_data WHERE user_id = :user_id AND file_id = :file_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'file_id' => $fileId,
        ]);
        $row = $stmt->fetch();

        return $row ? (int) $row['size'] : 0;
    }
}

function get_database_storage(): DatabaseStorage
{
    static $storage = null;

    if ($storage === null) {
        $storage = new DatabaseStorage();
    }

    return $storage;
}
