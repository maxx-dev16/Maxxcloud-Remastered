<?php

declare(strict_types=1);

class FTPStorage
{
    private $connection = null;
    private $host;
    private $port;
    private $user;
    private $pass;
    private $baseDir = '';

    public function __construct(string $host, int $port, string $user, string $pass)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function connect(): bool
    {
        if ($this->connection) {
            return true;
        }

        $conn = @ftp_connect($this->host, $this->port, 10);
        if (!$conn) {
            throw new Exception("FTP-Verbindung zu {$this->host}:{$this->port} fehlgeschlagen. Prüfe Host und Port.");
        }

        $login = @ftp_login($conn, $this->user, $this->pass);
        if (!$login) {
            @ftp_close($conn);
            throw new Exception("FTP-Login fehlgeschlagen für Benutzer '{$this->user}'. Prüfe Benutzer und Passwort.");
        }

        $pasv = @ftp_pasv($conn, true);
        if (!$pasv) {
            error_log("FTP: Passiv-Modus fehlgeschlagen, versuche Active-Modus");
        }

        $this->baseDir = @ftp_pwd($conn);
        if (!$this->baseDir) {
            $this->baseDir = '/';
        }

        $this->connection = $conn;
        return true;
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
    }

    public function makeDirRecursive(string $path): bool
    {
        $this->connect();

        $path = ltrim($path, '/');
        if (empty($path)) {
            return true;
        }

        $parts = explode('/', $path);
        
        @ftp_chdir($this->connection, $this->baseDir);
        $currentPath = '';

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            $currentPath .= '/' . $part;

            if (@ftp_chdir($this->connection, $part)) {
                @ftp_chmod($this->connection, 0777, $part);
                continue;
            }

            if (!@ftp_mkdir($this->connection, $part)) {
                throw new Exception("Konnte Verzeichnis nicht erstellen: {$currentPath}");
            }

            @ftp_chmod($this->connection, 0777, $part);

            if (!@ftp_chdir($this->connection, $part)) {
                throw new Exception("Konnte nicht in Verzeichnis navigieren: {$currentPath}");
            }
        }

        return true;
    }

    public function canWriteToDir(string $remotePath): array
    {
        $this->connect();
        $testFile = '.writetest_' . uniqid();
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $testFile;
        
        try {
            file_put_contents($tempFile, 'test');
            
            @ftp_chdir($this->connection, $this->baseDir);
            $dirPath = ltrim($remotePath, '/');
            
            if (!@ftp_chdir($this->connection, $dirPath)) {
                return ['canWrite' => false, 'reason' => 'Verzeichnis existiert nicht oder nicht zugänglich'];
            }
            
            $result = @ftp_put($this->connection, $testFile, $tempFile, FTP_BINARY);
            
            if ($result) {
                @ftp_delete($this->connection, $testFile);
                @unlink($tempFile);
                return ['canWrite' => true];
            }
            
            @unlink($tempFile);
            return ['canWrite' => false, 'reason' => 'ftp_put() Test fehlgeschlagen'];
        } catch (Exception $e) {
            @unlink($tempFile);
            return ['canWrite' => false, 'reason' => $e->getMessage()];
        }
    }

    public function putFile(string $localPath, string $remotePath): bool
    {
        $this->connect();

        if (!is_file($localPath)) {
            throw new Exception("Lokale Datei nicht gefunden: {$localPath}");
        }

        if (!is_readable($localPath)) {
            throw new Exception("Lokale Datei nicht lesbar: {$localPath}");
        }

        $remoteDir = dirname($remotePath);
        $fileName = basename($remotePath);
        $fileSize = filesize($localPath);

        if ($remoteDir !== '.' && $remoteDir !== '/') {
            $this->makeDirRecursive($remoteDir);
        }

        @ftp_chdir($this->connection, $this->baseDir);
        if ($remoteDir !== '.' && $remoteDir !== '/') {
            $remoteDirPath = ltrim($remoteDir, '/');
            if (!@ftp_chdir($this->connection, $remoteDirPath)) {
                throw new Exception("Konnte nicht in Upload-Verzeichnis navigieren: {$remoteDirPath}");
            }
            
            $writeTest = $this->canWriteToDir($remoteDirPath);
            if (!$writeTest['canWrite']) {
                $pwd = @ftp_pwd($this->connection);
                $reason = $writeTest['reason'] ?? 'Unbekannter Grund';
                throw new Exception("Schreibtest fehlgeschlagen im Verzeichnis {$pwd}: {$reason}. Bitte Server-Admin kontaktieren.");
            }
        }

        @ftp_chdir($this->connection, $this->baseDir);
        if ($remoteDir !== '.' && $remoteDir !== '/') {
            $remoteDirPath = ltrim($remoteDir, '/');
            @ftp_chdir($this->connection, $remoteDirPath);
        }

        $result = @ftp_put($this->connection, $fileName, $localPath, FTP_BINARY);

        if (!$result) {
            $ftp_systype = @ftp_systype($this->connection);
            $ftp_pwd = @ftp_pwd($this->connection);
            $ftp_nlist = @ftp_nlist($this->connection, '.');
            $listStr = is_array($ftp_nlist) ? implode(', ', array_slice($ftp_nlist, 0, 5)) : '(keine)';
            
            $details = "Dateigröße: {$fileSize} Bytes, Remote: {$remotePath}, System: {$ftp_systype}, PWD: {$ftp_pwd}, Verzeichnis: {$listStr}";
            throw new Exception("FTP-Upload fehlgeschlagen. Prüfe Serverberechtigungen, Speicher und Netzwerk. Details: {$details}");
        }

        return true;
    }

    public function getFile(string $remotePath, string $localPath): bool
    {
        $this->connect();

        $localDir = dirname($localPath);
        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        @ftp_chdir($this->connection, $this->baseDir);
        $remotePath = ltrim($remotePath, '/');
        $result = @ftp_get($this->connection, $localPath, $remotePath, FTP_BINARY);

        if (!$result) {
            $ftp_systype = @ftp_systype($this->connection);
            throw new Exception("FTP-Download fehlgeschlagen: {$remotePath} (System: {$ftp_systype})");
        }

        return true;
    }

    public function deleteFile(string $remotePath): bool
    {
        $this->connect();

        @ftp_chdir($this->connection, $this->baseDir);
        $remotePath = ltrim($remotePath, '/');
        $result = @ftp_delete($this->connection, $remotePath);

        return (bool) $result;
    }

    public function listFiles(string $remotePath = '/'): array
    {
        $this->connect();

        @ftp_chdir($this->connection, $this->baseDir);
        $remotePath = ltrim($remotePath, '/');
        if (empty($remotePath)) {
            $remotePath = '.';
        }

        $list = @ftp_nlist($this->connection, $remotePath);

        if ($list === false) {
            return [];
        }

        return array_filter($list, function ($item) {
            return !empty($item) && $item !== '.' && $item !== '..';
        });
    }

    public function fileExists(string $remotePath): bool
    {
        $this->connect();

        @ftp_chdir($this->connection, $this->baseDir);
        $remotePath = ltrim($remotePath, '/');
        $size = @ftp_size($this->connection, $remotePath);

        return $size !== -1;
    }

    public function getFileSize(string $remotePath): int
    {
        $this->connect();

        @ftp_chdir($this->connection, $this->baseDir);
        $remotePath = ltrim($remotePath, '/');
        $size = @ftp_size($this->connection, $remotePath);

        return $size !== -1 ? $size : 0;
    }

    public function removeDirectory(string $remotePath): bool
    {
        $this->connect();

        @ftp_chdir($this->connection, $this->baseDir);
        $remotePath = ltrim($remotePath, '/');
        $result = @ftp_rmdir($this->connection, $remotePath);

        return (bool) $result;
    }

    public function moveFile(string $oldPath, string $newPath): bool
    {
        $this->connect();

        $oldPath = ltrim($oldPath, '/');
        $newPath = ltrim($newPath, '/');

        $newDir = dirname($newPath);
        if ($newDir !== '.' && $newDir !== '/') {
            $this->makeDirRecursive($newDir);
        }

        @ftp_chdir($this->connection, $this->baseDir);
        $result = @ftp_rename($this->connection, $oldPath, $newPath);

        if (!$result) {
            $ftp_systype = @ftp_systype($this->connection);
            throw new Exception("FTP-Verschiebung fehlgeschlagen: {$oldPath} -> {$newPath} (System: {$ftp_systype})");
        }

        return true;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}

function get_ftp_storage(): FTPStorage
{
    global $config;

    static $ftp = null;

    if ($ftp === null) {
        $ftp = new FTPStorage(
            $config['ftp_host'],
            $config['ftp_port'],
            $config['ftp_user'],
            $config['ftp_pass']
        );
    }

    return $ftp;
}
