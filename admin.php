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
$mainBucket = 'Bucket-1'; // Main Bucket ist Bucket-1

// Prüfe ob Buckets konfiguriert sind
if (empty($buckets) || !is_array($buckets)) {
    $bucketStats = [];
} else {
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
                'host' => $buckets[$bucketName]['host'] ?? 'N/A',
                'port' => $buckets[$bucketName]['port'] ?? 'N/A',
                'user_count' => $userCount,
                'file_count' => $fileCount,
                'total_storage_mb' => $totalMB,
                'total_storage_gb' => $totalGB,
                'storage_db_mb' => $storageMB,
                'storage_db_gb' => $storageGB,
                'status' => $status,
                'is_main' => ($bucketName === $mainBucket), // Markiere Main Bucket
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
                'is_main' => ($bucketName === $mainBucket),
            ];
        } catch (Throwable $e) {
            // Fange alle Fehler ab, auch fatale Fehler
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
                'is_main' => ($bucketName === $mainBucket),
            ];
        }
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
    <title>Maxxcloud Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js" defer></script>
    <style>
        :root { color-scheme: dark; }
        body { background-color: #0f1115; }
        .navbar-dark { background-color: #0f1115; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .page-wrapper { padding-top: 0; }
        .nav-link img { width: 18px; height: 18px; margin-right: 8px; filter: brightness(0) invert(1); }
        .animate-enter { animation: fadeUp .28s ease both; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .badge-online { background-color: #2fb344; }
        .badge-offline { background-color: #d63939; }
    </style>
</head>
<body class="theme-dark">
    <?php $activeTab = $_GET['tab'] ?? 'monitoring'; ?>
    <header class="navbar navbar-expand-md navbar-dark">
        <div class="container-xl">
            <a class="navbar-brand" href="admin.php?tab=monitoring">Maxxcloud Admin</a>
            <div class="navbar-nav flex-row order-md-last">
                <form method="POST" action="admin.php" class="d-inline">
                    <input type="hidden" name="logout" value="1">
                    <button type="submit" class="btn btn-danger">Abmelden</button>
                </form>
            </div>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link <?= $activeTab==='monitoring'?'active':'' ?>" href="admin.php?tab=monitoring"><img alt="Monitoring" src="https://img.icons8.com/ios-filled/50/ffffff/monitor.png">Monitoring</a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeTab==='buckets'?'active':'' ?>" href="admin.php?tab=buckets"><img alt="Buckets" src="https://img.icons8.com/ios-filled/50/ffffff/database.png">Buckets</a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeTab==='users'?'active':'' ?>" href="admin.php?tab=users"><img alt="User" src="https://img.icons8.com/ios-filled/50/ffffff/user.png">Userverwaltung</a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeTab==='codes'?'active':'' ?>" href="admin.php?tab=codes"><img alt="Codes" src="https://img.icons8.com/ios-filled/50/ffffff/key.png">Codes</a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeTab==='backup'?'active':'' ?>" href="admin.php?tab=backup"><img alt="Backup" src="https://img.icons8.com/ios-filled/50/ffffff/download.png">Backup</a></li>
                </ul>
            </div>
        </div>
    </header>
    <div class="page-wrapper"><div class="container-xl">

    <?php if ($activeTab==='users'): ?>
    <div class="card animate-enter mt-3">
        <div class="card-header"><h3 class="card-title">Userverwaltung</h3></div>
        <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <input type="hidden" name="tab" value="users">
            <div class="col"><input class="form-control" name="q" placeholder="Suche nach Email oder Anzeigename" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"></div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">Suchen</button></div>
        </form>
        <?php
        $results = [];
        if (!empty($_GET['q'])) {
            $q = '%' . $_GET['q'] . '%';
            foreach (array_keys($buckets) as $bn) {
                try {
                    $pdo = get_bucket_db($bn);
                    $stmt = $pdo->prepare('SELECT id, email, display_name, banned, created_at, email_verified, email_verified_at, reset_token_expires_at, password_reset_at FROM users WHERE email LIKE :q OR display_name LIKE :q LIMIT 50');
                    $stmt->execute(['q' => $q]);
                    while ($row = $stmt->fetch()) { $row['bucket_id'] = $bn; $results[] = $row; }
                } catch (Throwable $e) {}
            }
        }
        ?>
        <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th>User</th><th>Bucket</th><th>Status</th><th>Verifiziert</th><th>Erstellt</th><th>Letzter Reset</th><th>Aktion</th></tr></thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['email']) ?><?php if(!empty($r['display_name'])): ?> (<?= htmlspecialchars($r['display_name']) ?>)<?php endif; ?></td>
                        <td><?= htmlspecialchars($r['bucket_id']) ?></td>
                        <td><?= ((int)$r['banned']===1) ? 'gebanned' : 'ok' ?></td>
                        <td>
                            <?php if ((int)($r['email_verified'] ?? 0) === 1): ?>
                                <span class="badge bg-green">verifiziert</span>
                            <?php else: ?>
                                <span class="badge bg-red">nicht verifiziert</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['password_reset_at'] ?? '') ?></td>
                        <td>
                            <form method="POST" style="display:inline-block">
                                <input type="hidden" name="admin_action" value="toggle_ban">
                                <input type="hidden" name="bucket_id" value="<?= htmlspecialchars($r['bucket_id']) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="ban" value="<?= ((int)$r['banned']===1) ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-primary"><?= ((int)$r['banned']===1) ? 'Entbannen' : 'Bannen' ?></button>
                            </form>
                            <form method="POST" style="display:inline-block" onsubmit="return confirm('User wirklich löschen?')">
                                <input type="hidden" name="admin_action" value="delete_user">
                                <input type="hidden" name="bucket_id" value="<?= htmlspecialchars($r['bucket_id']) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-danger">Löschen</button>
                            </form>
                            <form method="GET" style="display:inline-block">
                                <input type="hidden" name="view_files" value="1">
                                <input type="hidden" name="tab" value="users">
                                <input type="hidden" name="bucket_id" value="<?= htmlspecialchars($r['bucket_id']) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-secondary">Dateien ansehen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if (!empty($_GET['view_files'])):
            $bn = $_GET['bucket_id'] ?? '';
            $uid = (int)($_GET['user_id'] ?? 0);
            try { $pdo = get_bucket_db($bn); $files = $pdo->prepare('SELECT id, name, size_bytes FROM files WHERE user_id = :u ORDER BY id DESC LIMIT 200'); $files->execute(['u'=>$uid]); $fl = $files->fetchAll(); } catch (Throwable $e) { $fl = []; }
        ?>
        <h3 class="mt-3">Dateien von User #<?= (int)$uid ?></h3>
        <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>Name</th><th>Größe</th></tr></thead>
            <tbody>
                <?php foreach ($fl as $f): ?>
                    <tr><td><?= (int)$f['id'] ?></td><td><?= htmlspecialchars($f['name']) ?></td><td><?= (int)$f['size_bytes'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php
    if (isset($_POST['logout'])) {
        unset($_SESSION['admin_authenticated']);
        header('Location: admin.php');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
        try {
            $master = master_db();
            if ($_POST['admin_action'] === 'create_code') {
                $code = strtoupper(trim($_POST['code'] ?? ''));
                if ($code === '') {
                    $code = substr(strtoupper(bin2hex(random_bytes(8))),0,16);
                }
                $mb = max(1, (int)($_POST['storage_mb'] ?? 0));
                $max = max(1, (int)($_POST['max_uses'] ?? 1));
                $noExpiry = (int)($_POST['no_expiry'] ?? 0) === 1;
                $exp = trim($_POST['expires_at'] ?? '');
                if ($noExpiry) {
                    $expVal = null;
                } elseif ($exp === '') {
                    $expVal = date('Y-m-d H:i:s', time() + 24*3600);
                } else {
                    $expVal = $exp;
                }
                $stmt = $master->prepare('INSERT INTO redemption_codes (code, storage_mb, max_uses, expires_at, active) VALUES (:c,:mb,:mx,:exp,1)');
                $stmt->execute(['c'=>$code,'mb'=>$mb,'mx'=>$max,'exp'=>$expVal]);
            } elseif ($_POST['admin_action'] === 'toggle_code') {
                $id = (int)($_POST['id'] ?? 0);
                $active = (int)($_POST['active'] ?? 0);
                $stmt = $master->prepare('UPDATE redemption_codes SET active = :a WHERE id = :id');
                $stmt->execute(['a'=>$active?1:0,'id'=>$id]);
            } elseif ($_POST['admin_action'] === 'delete_code') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $master->prepare('DELETE FROM redemption_codes WHERE id = :id');
                $stmt->execute(['id'=>$id]);
            } elseif ($_POST['admin_action'] === 'toggle_bucket') {
                $bucket = trim($_POST['bucket_id'] ?? '');
                $active = (int)($_POST['active'] ?? 1) ? 1 : 0;
                $stmt = $master->prepare('INSERT INTO bucket_settings (bucket_id, active) VALUES (:b, :a) ON DUPLICATE KEY UPDATE active = :a');
                $stmt->execute(['b'=>$bucket,'a'=>$active]);
            } elseif ($_POST['admin_action'] === 'set_maintenance') {
                $on = (int)($_POST['on'] ?? 0) ? '1' : '0';
                set_setting('maintenance_mode', $on);
            } elseif ($_POST['admin_action'] === 'create_backup') {
                $all = [];
                foreach (array_keys($buckets) as $bn) {
                    try {
                        $pdo = get_bucket_db($bn);
                        $users = $pdo->query('SELECT * FROM users')->fetchAll();
                        $folders = $pdo->query('SELECT * FROM folders')->fetchAll();
                        $files = $pdo->query('SELECT * FROM files')->fetchAll();
                        $all[$bn] = compact('users','folders','files');
                    } catch (Throwable $e) {}
                }
                $json = json_encode(['buckets'=>$all,'created_at'=>date('c')]);
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="backup_'.date('Ymd_His').'.json"');
                echo $json;
                exit;
            } elseif ($_POST['admin_action'] === 'restore_backup') {
                if (!isset($_FILES['backup']) || $_FILES['backup']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Backup-Datei fehlt');
                }
                $content = file_get_contents($_FILES['backup']['tmp_name']);
                $data = json_decode($content, true);
                if (!$data || !isset($data['buckets'])) {
                    throw new Exception('Ungültiges Backup-Format');
                }
                foreach ($data['buckets'] as $bn => $payload) {
                    try {
                        $pdo = get_bucket_db($bn);
                        $pdo->beginTransaction();
                        foreach (['users','folders','files'] as $tbl) {
                            if (isset($payload[$tbl]) && is_array($payload[$tbl])) {
                                foreach ($payload[$tbl] as $row) {
                                    $cols = array_keys($row);
                                    $place = implode(',', array_fill(0, count($cols), '?'));
                                    $sql = 'REPLACE INTO '.$tbl.' ('.implode(',', $cols).') VALUES ('.$place.')';
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute(array_values($row));
                                }
                            }
                        }
                        $pdo->commit();
                    } catch (Throwable $e) {
                        try { $pdo->rollBack(); } catch (Throwable $e2) {}
                    }
                }
            } elseif ($_POST['admin_action'] === 'create_bucket_zip') {
                $bn = trim($_POST['bucket_id'] ?? '');
                if ($bn === '') { throw new Exception('Bucket fehlt'); }
                $pdo = get_bucket_db($bn);
                $storage = get_storage();
                $zipPath = tempnam(sys_get_temp_dir(), 'bucketzip_');
                $zipName = 'bucket_' . preg_replace('/[^a-zA-Z0-9_-]/','_', $bn) . '_' . date('Ymd_His') . '.zip';
                $tmpFiles = [];
                $zip = new ZipArchive();
                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new Exception('ZIP konnte nicht erstellt werden'); }
                $zip->addFromString('meta.txt', "Maxxcloud Bucket Backup\nBucket: {$bn}\nErstellt: " . date('c'));
                // Users CSV
                $users = $pdo->query('SELECT * FROM users')->fetchAll(PDO::FETCH_ASSOC);
                $ufp = fopen('php://temp', 'r+');
                if ($users) { fputcsv($ufp, array_keys($users[0])); foreach ($users as $row) { fputcsv($ufp, $row); } }
                rewind($ufp); $zip->addFromString('users.csv', stream_get_contents($ufp) ?: ''); fclose($ufp);
                // Folders CSV
                $folders = $pdo->query('SELECT * FROM folders')->fetchAll(PDO::FETCH_ASSOC);
                $ffp = fopen('php://temp', 'r+');
                if ($folders) { fputcsv($ffp, array_keys($folders[0])); foreach ($folders as $row) { fputcsv($ffp, $row); } }
                rewind($ffp); $zip->addFromString('folders.csv', stream_get_contents($ffp) ?: ''); fclose($ffp);
                // Files CSV + Inhalte
                $files = $pdo->query('SELECT id, user_id, original_name, size_bytes, created_at FROM files ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
                $cfp = fopen('php://temp', 'r+');
                if ($files) { fputcsv($cfp, array_keys($files[0])); foreach ($files as $row) { fputcsv($cfp, $row); } }
                rewind($cfp); $zip->addFromString('files.csv', stream_get_contents($cfp) ?: ''); fclose($cfp);
                foreach ($files as $f) {
                    $uid = (int)$f['user_id']; $fid = (int)$f['id']; $name = $f['original_name'];
                    $tmp = tempnam(sys_get_temp_dir(), 'file_');
                    try {
                        $storage->getFile($tmp, $uid, $fid);
                        $zip->addFile($tmp, 'files/user_' . $uid . '/file_' . $fid . '__' . preg_replace('/[\\\/:*?"<>|]+/','_', $name));
                        $tmpFiles[] = $tmp;
                    } catch (Throwable $e) { @unlink($tmp); }
                }
                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipName . '"');
                header('Content-Length: ' . filesize($zipPath));
                readfile($zipPath);
                foreach ($tmpFiles as $t) { @unlink($t); }
                @unlink($zipPath);
                exit;
            } elseif ($_POST['admin_action'] === 'toggle_ban') {
                $bn = trim($_POST['bucket_id'] ?? '');
                $uid = (int)($_POST['user_id'] ?? 0);
                $ban = (int)($_POST['ban'] ?? 0) ? 1 : 0;
                $pdo = get_bucket_db($bn);
                $stmt = $pdo->prepare('UPDATE users SET banned = :b WHERE id = :id');
                $stmt->execute(['b'=>$ban,'id'=>$uid]);
            } elseif ($_POST['admin_action'] === 'delete_user') {
                $bn = trim($_POST['bucket_id'] ?? '');
                $uid = (int)($_POST['user_id'] ?? 0);
                $pdo = get_bucket_db($bn);
                // Dateien löschen
                try {
                    $files = $pdo->prepare('SELECT id FROM files WHERE user_id = :u');
                    $files->execute(['u'=>$uid]);
                    $ids = $files->fetchAll(PDO::FETCH_COLUMN);
                    $storage = get_storage();
                    foreach ($ids as $fid) { try { $storage->deleteFile((int)$fid); } catch (Throwable $e) {} }
                } catch (Throwable $e) {}
                // User löschen
                $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id'=>$uid]);
                $pdo->prepare('DELETE FROM folders WHERE user_id = :id')->execute(['id'=>$uid]);
                $pdo->prepare('DELETE FROM files WHERE user_id = :id')->execute(['id'=>$uid]);
            }
        } catch (Throwable $e) {
            $admin_error = $e->getMessage();
        }
    }
    try { $codes = master_db()->query('SELECT * FROM redemption_codes ORDER BY created_at DESC')->fetchAll(); } catch (Throwable $e) { $codes = []; }
    try { $bucketSettings = master_db()->query('SELECT bucket_id, active FROM bucket_settings')->fetchAll(); } catch (Throwable $e) { $bucketSettings = []; }
    try { $maintenance = (bool) get_setting('maintenance_mode', '0'); } catch (Throwable $e) { $maintenance = false; }
    try {
        $traffic1m = master_db()->query("SELECT COUNT(*) FROM traffic_events WHERE created_at > NOW() - INTERVAL 1 MINUTE")->fetchColumn();
        $traffic5m = master_db()->query("SELECT COUNT(*) FROM traffic_events WHERE created_at > NOW() - INTERVAL 5 MINUTE")->fetchColumn();
        $traffic60m = master_db()->query("SELECT COUNT(*) FROM traffic_events WHERE created_at > NOW() - INTERVAL 60 MINUTE")->fetchColumn();
    } catch (Throwable $e) {
        $traffic1m = $traffic5m = $traffic60m = 0;
    }
    
    // Berechne Gesamtstatistiken
    $totalUsers = array_sum(array_column($bucketStats, 'user_count'));
    $totalFiles = array_sum(array_column($bucketStats, 'file_count'));
    $totalStorageGB = array_sum(array_column($bucketStats, 'total_storage_gb'));
    $totalStorageDBGB = array_sum(array_column($bucketStats, 'storage_db_gb'));
    $onlineBuckets = count(array_filter($bucketStats, fn($b) => $b['status'] === 'online'));
    $totalBuckets = count($bucketStats);
    ?>

    <?php if ($activeTab==='monitoring'): ?>
    <div class="row row-deck animate-enter mt-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body"><div class="h1 mb-1"><?= $totalBuckets ?></div><div class="text-muted">Buckets (<?= $onlineBuckets ?> online)</div></div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body"><div class="h1 mb-1"><?= number_format($totalUsers, 0, ',', '.') ?></div><div class="text-muted">Gesamt User</div></div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body"><div class="h1 mb-1"><?= number_format($totalFiles, 0, ',', '.') ?></div><div class="text-muted">Gesamt Dateien</div></div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body"><div class="h1 mb-1"><?= number_format($totalStorageGB, 2, ',', '.') ?> GB</div><div class="text-muted">Gesamt Speicher</div></div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body"><div class="h1 mb-1"><?= number_format($totalStorageDBGB, 2, ',', '.') ?> GB</div><div class="text-muted">Storage DB</div></div></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($activeTab==='codes'): ?>
    <div class="card animate-enter mt-3">
        <div class="card-header"><h3 class="card-title">Codes verwalten</h3></div>
        <div class="card-body">
        <?php if (!empty($admin_error)): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($admin_error) ?></div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-6">
                <form method="POST">
                    <input type="hidden" name="admin_action" value="create_code">
                    <input type="hidden" id="expires_at" name="expires_at" value="">
                    <input type="hidden" id="no_expiry" name="no_expiry" value="0">
                    <div class="row g-2 mb-2">
                        <div class="col-12"><input class="form-control" name="code" placeholder="Code (leer = automatisch)"></div>
                        <div class="col"><input class="form-control" type="number" name="storage_mb" placeholder="Speicher in MB" required></div>
                        <div class="col"><input class="form-control" type="number" name="max_uses" placeholder="Max. Nutzungen" required></div>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <button type="button" class="btn btn-secondary" id="openExpiry">Ablauf einstellen</button>
                        <span class="text-muted" id="expirySummary"></span>
                    </div>
                    <button type="submit" class="btn btn-primary">Erstellen</button>
                </form>
            </div>
            <div class="col-md-6">
                <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>MB</th>
                            <th>Verwendet</th>
                            <th>Limit</th>
                            <th>Ablauf</th>
                            <th>Status</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($codes as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['code']) ?></td>
                                <td><?= (int)$c['storage_mb'] ?></td>
                                <td><?= (int)$c['used_count'] ?></td>
                                <td><?= (int)$c['max_uses'] ?></td>
                                <td><?= htmlspecialchars($c['expires_at'] ?? '') ?></td>
                                <td><?= $c['active'] ? 'aktiv' : 'inaktiv' ?></td>
                                <td>
                                    <form method="POST" style="display:inline-block">
                                        <input type="hidden" name="admin_action" value="toggle_code">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="active" value="<?= $c['active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-primary"><?= $c['active'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
                                    </form>
                                    <form method="POST" style="display:inline-block;margin-left:6px" onsubmit="return confirm('Code löschen?')">
                                        <input type="hidden" name="admin_action" value="delete_code">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="btn btn-danger">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($activeTab==='buckets'): ?>
    <div class="card animate-enter mt-3">
        <div class="card-header"><h3 class="card-title">Buckets</h3></div>
        <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>Bucket</th><th>Status</th><th>Aktion</th></tr></thead>
                    <tbody>
                        <?php foreach (array_keys($buckets) as $bn):
                            $row = null; foreach ($bucketSettings as $bs) { if ($bs['bucket_id'] === $bn) { $row = $bs; break; } }
                            $isActive = $row ? ((int)$row['active']===1) : true; ?>
                            <tr>
                                <td><?= htmlspecialchars($bn) ?></td>
                                <td><?= $isActive ? 'aktiv' : 'deaktiviert' ?></td>
                                <td>
                                    <form method="POST" style="display:inline-block">
                                        <input type="hidden" name="admin_action" value="toggle_bucket">
                                        <input type="hidden" name="bucket_id" value="<?= htmlspecialchars($bn) ?>">
                                        <input type="hidden" name="active" value="<?= $isActive ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-primary"><?= $isActive ? 'Deaktivieren' : 'Aktivieren' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card"><div class="card-body"><canvas id="trafficChart" width="520" height="160"></canvas></div></div>
                <form method="POST" class="d-flex align-items-center gap-2 mt-2">
                    <input type="hidden" name="admin_action" value="set_maintenance">
                    <input type="hidden" name="on" value="<?= $maintenance ? 0 : 1 ?>">
                    <button type="submit" class="btn btn-warning"><?= $maintenance ? 'Wartungsmodus beenden' : 'Wartungsmodus starten' ?></button>
                </form>
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($activeTab==='backup'): ?>
    <div class="card animate-enter mt-3">
        <div class="card-header"><h3 class="card-title">Backup</h3></div>
        <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <form method="POST">
                    <input type="hidden" name="admin_action" value="create_backup">
                    <button type="submit" class="btn btn-primary">Backup erstellen (JSON)</button>
                </form>
                <hr>
                <form method="POST">
                    <input type="hidden" name="admin_action" value="create_bucket_zip">
                    <div class="row g-2 align-items-end">
                        <div class="col">
                            <label class="form-label">Bucket wählen</label>
                            <select class="form-select" name="bucket_id" required>
                                <?php foreach (array_keys($buckets) as $bn): ?>
                                    <option value="<?= htmlspecialchars($bn) ?>"><?= htmlspecialchars($bn) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-success">Bucket ZIP erstellen (Dateien + Nutzerinfos)</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-md-6">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="admin_action" value="restore_backup">
                    <div class="row g-2">
                        <div class="col"><input class="form-control" type="file" name="backup" accept="application/json" required></div>
                        <div class="col-auto"><button type="submit" class="btn btn-danger">Backup wiederherstellen</button></div>
                    </div>
                </form>
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal" id="expiryModal" tabindex="-1">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gültigkeit festlegen</h5>
                    <button type="button" class="btn-close" id="closeExpiry"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><input type="radio" name="expiryMode" value="none"> Kein Ablauf</div>
                    <div class="mb-2 d-flex align-items-center gap-2"><input type="radio" name="expiryMode" value="hours" checked> Ablauf nach Stunden <input class="form-control" style="max-width:120px" type="number" id="expiryHours" min="1" value="24" /></div>
                    <div class="mb-2 d-flex align-items-center gap-2"><input type="radio" name="expiryMode" value="fixed"> Festes Datum & Uhrzeit <input class="form-control" type="date" id="expiryDate" /><input class="form-control" type="time" id="expiryTime" /></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="saveExpiry">Übernehmen</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mt-3">
        <?php foreach ($bucketStats as $stats): ?>
            <div class="col-md-6">
                <div class="card animate-enter">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <div class="me-2 h3 mb-0"><?= htmlspecialchars($stats['name']) ?></div>
                            <?php if (!empty($stats['is_main'])): ?>
                                <span class="badge bg-primary">Main Bucket</span>
                            <?php endif; ?>
                        </div>
                        <span class="badge <?= $stats['status']==='online'?'badge-online':'badge-offline' ?>"><?= $stats['status'] ?></span>
                    </div>
                    <div class="card-body">
                        <div class="mb-2 d-flex justify-content-between"><div class="text-muted">Host</div><div><?= htmlspecialchars($stats['host']) ?>:<?= $stats['port'] ?></div></div>
                        <div class="mb-2 d-flex justify-content-between"><div class="text-muted">User</div><div><?= number_format($stats['user_count'], 0, ',', '.') ?></div></div>
                        <div class="mb-2 d-flex justify-content-between"><div class="text-muted">Dateien</div><div><?= number_format($stats['file_count'], 0, ',', '.') ?></div></div>
                        <div class="mb-2 d-flex justify-content-between"><div class="text-muted">Speicher (Files)</div><div><?= number_format($stats['total_storage_mb'], 2, ',', '.') ?> MB</div></div>
                        <div class="mb-2 d-flex justify-content-between"><div class="text-muted">Speicher (Storage DB)</div><div><?= number_format($stats['storage_db_mb'], 2, ',', '.') ?> MB</div></div>
                        <?php if (isset($stats['error'])): ?>
                            <div class="alert alert-danger mt-2" role="alert">Fehler: <?= htmlspecialchars($stats['error']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Auto-Refresh alle 30 Sekunden
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
    <script>
        (function(){
            const openBtn = document.getElementById('openExpiry');
            const modal = document.getElementById('expiryModal');
            const closeBtn = document.getElementById('closeExpiry');
            const saveBtn = document.getElementById('saveExpiry');
            const hoursInput = document.getElementById('expiryHours');
            const dateInput = document.getElementById('expiryDate');
            const timeInput = document.getElementById('expiryTime');
            const expiresAtHidden = document.getElementById('expires_at');
            const noExpiryHidden = document.getElementById('no_expiry');
            const summaryEl = document.getElementById('expirySummary');

            function fmt(dt){
                const pad = n=> String(n).padStart(2,'0');
                return `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())} ${pad(dt.getHours())}:${pad(dt.getMinutes())}:00`;
            }
            function setSummary(text){
                if(summaryEl){ summaryEl.textContent = text || ''; }
            }
            function open(){ modal?.classList.add('open'); }
            function close(){ modal?.classList.remove('open'); }

            if(openBtn){ openBtn.addEventListener('click', open); }
            if(closeBtn){ closeBtn.addEventListener('click', close); }
            if(saveBtn){
                saveBtn.addEventListener('click', ()=>{
                    const mode = (document.querySelector('input[name="expiryMode"]:checked')?.value) || 'hours';
                    if(mode === 'none'){
                        noExpiryHidden.value = '1';
                        expiresAtHidden.value = '';
                        setSummary('Kein Ablauf');
                    }else if(mode === 'hours'){
                        const h = Math.max(1, parseInt(hoursInput.value || '24', 10));
                        const dt = new Date(Date.now() + h*3600*1000);
                        noExpiryHidden.value = '0';
                        expiresAtHidden.value = fmt(dt);
                        setSummary(`Ablauf in ${h}h (${expiresAtHidden.value})`);
                    }else{
                        const d = dateInput.value;
                        const t = timeInput.value || '00:00';
                        if(!d){
                            alert('Bitte Datum wählen');
                            return;
                        }
                        noExpiryHidden.value = '0';
                        expiresAtHidden.value = `${d} ${t}:00`;
                        setSummary(`Fest: ${expiresAtHidden.value}`);
                    }
                    close();
                });
            }
        })();
        (function(){
            const canvas = document.getElementById('trafficChart');
            if(!canvas) return;
            const ctx = canvas.getContext('2d');
            function draw(series){
                ctx.clearRect(0,0,canvas.width,canvas.height);
                ctx.strokeStyle = '#0ea5a4'; ctx.lineWidth = 2;
                const max = Math.max(1, ...series.map(p=> Number(p.c)||0));
                const w = canvas.width, h = canvas.height, pad = 20;
                ctx.beginPath();
                series.forEach((p,i)=>{
                    const x = pad + (i*(w-2*pad))/Math.max(1,series.length-1);
                    const y = h - pad - ((Number(p.c)||0)* (h-2*pad))/max;
                    if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
                });
                ctx.stroke();
            }
            async function poll(){
                try{
                    const res = await fetch('api.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'traffic_series', minutes:60})});
                    const data = await res.json();
                    draw(data.series || []);
                }catch(e){}
                setTimeout(poll, 5000);
            }
            poll();
        })();
    </script>
</div></div>
</body>
</html>

