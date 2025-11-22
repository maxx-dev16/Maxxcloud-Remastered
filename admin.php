<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// Einfache Admin-Authentifizierung (kann später erweitert werden)
$adminPassword = getenv('ADMIN_PASSWORD') ?: 'admin123';
$isAuthenticated = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_authenticated'] = true;
        $isAuthenticated = true;
    } else {
        $error = 'Falsches Passwort';
    }
}

if (!$isAuthenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - MaxxCloud</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-card {
                background: rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                padding: 40px;
                border: 1px solid rgba(255, 255, 255, 0.2);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                max-width: 400px;
                width: 90%;
            }
            h1 { color: #fff; margin-bottom: 20px; text-align: center; }
            .form-group { margin-bottom: 20px; }
            input {
                width: 100%;
                padding: 12px;
                border: none;
                border-bottom: 2px solid rgba(255, 255, 255, 0.4);
                background: transparent;
                color: #fff;
                font-size: 16px;
                outline: none;
            }
            input::placeholder { color: rgba(255, 255, 255, 0.6); }
            button {
                width: 100%;
                padding: 12px;
                background: rgba(255, 255, 255, 0.2);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 8px;
                color: #fff;
                font-size: 16px;
                cursor: pointer;
            }
            button:hover { background: rgba(255, 255, 255, 0.3); }
            .error { color: #ffcccc; margin-top: 10px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="login-card">
            <h1>Admin Login</h1>
            <form method="POST">
                <div class="form-group">
                    <input type="password" name="password" placeholder="Admin Passwort" required autofocus>
                </div>
                <button type="submit">Anmelden</button>
                <?php if (isset($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Sammle Statistiken für alle Buckets
global $buckets;
$bucketStats = [];

foreach (array_keys($buckets) as $bucketName) {
    try {
        $db = get_bucket_db($bucketName);
        $storageDb = get_bucket_storage_db($bucketName);
        
        // User-Anzahl
        $userStmt = $db->query('SELECT COUNT(*) FROM users');
        $userCount = (int) $userStmt->fetchColumn();
        
        // Gesamter Speicherplatz
        $storageStmt = $db->query('SELECT COALESCE(SUM(size_bytes), 0) FROM files');
        $totalBytes = (int) $storageStmt->fetchColumn();
        $totalMB = round($totalBytes / 1024 / 1024, 2);
        $totalGB = round($totalMB / 1024, 2);
        
        // Datei-Anzahl
        $fileStmt = $db->query('SELECT COUNT(*) FROM files');
        $fileCount = (int) $fileStmt->fetchColumn();
        
        // Storage-Datenbank Größe
        $storageSizeStmt = $storageDb->query('SELECT COALESCE(SUM(OCTET_LENGTH(file_content)), 0) FROM file_data');
        $storageBytes = (int) $storageSizeStmt->fetchColumn();
        $storageMB = round($storageBytes / 1024 / 1024, 2);
        $storageGB = round($storageMB / 1024, 2);
        
        // Verbindungsstatus
        $status = 'online';
        
        $bucketStats[$bucketName] = [
            'name' => $bucketName,
            'host' => $buckets[$bucketName]['host'],
            'port' => $buckets[$bucketName]['port'],
            'user_count' => $userCount,
            'file_count' => $fileCount,
            'total_storage_mb' => $totalMB,
            'total_storage_gb' => $totalGB,
            'storage_db_mb' => $storageMB,
            'storage_db_gb' => $storageGB,
            'status' => $status,
        ];
    } catch (Exception $e) {
        $bucketStats[$bucketName] = [
            'name' => $bucketName,
            'host' => $buckets[$bucketName]['host'] ?? 'N/A',
            'port' => $buckets[$bucketName]['port'] ?? 'N/A',
            'user_count' => 0,
            'file_count' => 0,
            'total_storage_mb' => 0,
            'total_storage_gb' => 0,
            'storage_db_mb' => 0,
            'storage_db_gb' => 0,
            'status' => 'offline',
            'error' => $e->getMessage(),
        ];
    }
}

// Sortiere nach Auslastung (User-Anzahl)
uasort($bucketStats, function($a, $b) {
    return $b['user_count'] <=> $a['user_count'];
});

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Bucket Auslastung</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a1a;
            color: #e6e6e6;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
        }
        h1 { font-size: 28px; }
        .logout-btn {
            padding: 10px 20px;
            background: #dc3545;
            border: none;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .logout-btn:hover { background: #c82333; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .bucket-card {
            background: #242424;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 20px;
        }
        .bucket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #333;
        }
        .bucket-name {
            font-size: 20px;
            font-weight: 600;
            color: #0ea5a4;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-online {
            background: #28a745;
            color: #fff;
        }
        .status-offline {
            background: #dc3545;
            color: #fff;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .stat-label {
            color: #6b7280;
        }
        .stat-value {
            font-weight: 600;
            color: #e6e6e6;
        }
        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 10px;
            padding: 8px;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 6px;
        }
        .summary {
            background: #242424;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-value {
            font-size: 32px;
            font-weight: 700;
            color: #0ea5a4;
            margin-bottom: 5px;
        }
        .summary-label {
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bucket Auslastung Dashboard</h1>
        <form method="POST" action="admin.php" style="display: inline;">
            <input type="hidden" name="logout" value="1">
            <button type="submit" class="logout-btn" onclick="return confirm('Wirklich abmelden?')">Abmelden</button>
        </form>
    </div>

    <?php
    if (isset($_POST['logout'])) {
        unset($_SESSION['admin_authenticated']);
        header('Location: admin.php');
        exit;
    }
    
    // Berechne Gesamtstatistiken
    $totalUsers = array_sum(array_column($bucketStats, 'user_count'));
    $totalFiles = array_sum(array_column($bucketStats, 'file_count'));
    $totalStorageGB = array_sum(array_column($bucketStats, 'total_storage_gb'));
    $totalStorageDBGB = array_sum(array_column($bucketStats, 'storage_db_gb'));
    $onlineBuckets = count(array_filter($bucketStats, fn($b) => $b['status'] === 'online'));
    $totalBuckets = count($bucketStats);
    ?>

    <div class="summary">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value"><?= $totalBuckets ?></div>
                <div class="summary-label">Buckets (<?= $onlineBuckets ?> online)</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?= number_format($totalUsers, 0, ',', '.') ?></div>
                <div class="summary-label">Gesamt User</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?= number_format($totalFiles, 0, ',', '.') ?></div>
                <div class="summary-label">Gesamt Dateien</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?= number_format($totalStorageGB, 2, ',', '.') ?> GB</div>
                <div class="summary-label">Gesamt Speicher</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?= number_format($totalStorageDBGB, 2, ',', '.') ?> GB</div>
                <div class="summary-label">Storage DB</div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <?php foreach ($bucketStats as $stats): ?>
            <div class="bucket-card">
                <div class="bucket-header">
                    <div class="bucket-name"><?= htmlspecialchars($stats['name']) ?></div>
                    <span class="status-badge status-<?= $stats['status'] ?>">
                        <?= $stats['status'] ?>
                    </span>
                </div>
                
                <div class="stat-row">
                    <span class="stat-label">Host</span>
                    <span class="stat-value"><?= htmlspecialchars($stats['host']) ?>:<?= $stats['port'] ?></span>
                </div>
                
                <div class="stat-row">
                    <span class="stat-label">User</span>
                    <span class="stat-value"><?= number_format($stats['user_count'], 0, ',', '.') ?></span>
                </div>
                
                <div class="stat-row">
                    <span class="stat-label">Dateien</span>
                    <span class="stat-value"><?= number_format($stats['file_count'], 0, ',', '.') ?></span>
                </div>
                
                <div class="stat-row">
                    <span class="stat-label">Speicher (Files)</span>
                    <span class="stat-value"><?= number_format($stats['total_storage_mb'], 2, ',', '.') ?> MB</span>
                </div>
                
                <div class="stat-row">
                    <span class="stat-label">Speicher (Storage DB)</span>
                    <span class="stat-value"><?= number_format($stats['storage_db_mb'], 2, ',', '.') ?> MB</span>
                </div>
                
                <?php if (isset($stats['error'])): ?>
                    <div class="error-message">
                        Fehler: <?= htmlspecialchars($stats['error']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Auto-Refresh alle 30 Sekunden
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

