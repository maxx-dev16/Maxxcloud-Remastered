<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ftp.php';

global $config;

echo "=== FTP Integration Test Suite ===\n\n";

try {
    echo "1. Testing FTP Connection...\n";
    $ftp = get_ftp_storage();
    $ftp->connect();
    echo "✓ FTP Connection successful\n\n";

    echo "2. Testing directory creation...\n";
    $testDir = 'zap1265543/test_' . uniqid();
    $ftp->makeDirRecursive($testDir);
    echo "✓ Directory created: {$testDir}\n\n";

    echo "3. Testing file upload...\n";
    $testFile = tempnam(sys_get_temp_dir(), 'ftp_test_');
    file_put_contents($testFile, "Test content for FTP upload - " . date('Y-m-d H:i:s'));
    
    $remotePath = $testDir . '/test_file.txt';
    $ftp->putFile($testFile, $remotePath);
    echo "✓ File uploaded: {$remotePath}\n";
    
    if ($ftp->fileExists($remotePath)) {
        echo "✓ File exists verification passed\n";
        $fileSize = $ftp->getFileSize($remotePath);
        echo "✓ File size: {$fileSize} bytes\n\n";
    } else {
        echo "✗ File existence verification failed\n\n";
    }

    echo "4. Testing file download...\n";
    $downloadPath = tempnam(sys_get_temp_dir(), 'ftp_download_');
    $ftp->getFile($remotePath, $downloadPath);
    $downloadContent = file_get_contents($downloadPath);
    if ($downloadContent === file_get_contents($testFile)) {
        echo "✓ File downloaded and content matches\n\n";
    } else {
        echo "✗ Downloaded file content mismatch\n\n";
    }

    echo "5. Testing file move (simulating deletion)...\n";
    $movedPath = $testDir . '/.deleted/test_file_moved.txt';
    $ftp->moveFile($remotePath, $movedPath);
    if ($ftp->fileExists($movedPath)) {
        echo "✓ File moved successfully to: {$movedPath}\n";
        if (!$ftp->fileExists($remotePath)) {
            echo "✓ Original file no longer exists\n\n";
        } else {
            echo "✗ Original file still exists\n\n";
        }
    } else {
        echo "✗ File move failed\n\n";
    }

    echo "6. Testing file list...\n";
    $fileList = $ftp->listFiles($testDir);
    echo "✓ Files in {$testDir}:\n";
    foreach ($fileList as $file) {
        echo "  - {$file}\n";
    }
    echo "\n";

    echo "7. Testing file deletion...\n";
    $ftp->deleteFile($movedPath);
    if (!$ftp->fileExists($movedPath)) {
        echo "✓ File deleted successfully\n\n";
    } else {
        echo "✗ File deletion failed\n\n";
    }

    echo "8. Testing directory removal...\n";
    $deletedDir = $testDir . '/.deleted';
    try {
        $ftp->removeDirectory($deletedDir);
        echo "✓ Deleted directory removed\n";
    } catch (Exception $e) {
        echo "✗ Could not remove .deleted directory (may still have files)\n";
    }
    
    try {
        $ftp->removeDirectory($testDir);
        echo "✓ Test directory removed\n\n";
    } catch (Exception $e) {
        echo "✗ Could not remove test directory\n\n";
    }

    echo "=== All FTP Tests Completed Successfully ===\n";
    
    @unlink($testFile);
    @unlink($downloadPath);
    
} catch (Exception $e) {
    echo "✗ Test failed with error: " . $e->getMessage() . "\n";
    exit(1);
}
