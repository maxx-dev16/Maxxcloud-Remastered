<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$overall = [
  'maintenance' => (bool) get_setting('maintenance_mode', '0'),
  'master_db' => false,
  'email_configured' => !empty(($GLOBALS['config']['mail_from'] ?? '')),
  'turnstile' => turnstile_enabled(),
  'storage_writable' => is_writable($GLOBALS['config']['storage_path'] ?? __DIR__),
];
try { $ok = master_db()->query('SELECT 1')->fetchColumn(); $overall['master_db'] = (bool)$ok; } catch (Throwable $e) { $overall['master_db'] = false; }

global $buckets;
$bucketStats = [];
$bucketSettings = [];
try { $bucketSettings = master_db()->query('SELECT bucket_id, active FROM bucket_settings')->fetchAll(); } catch (Throwable $e) { $bucketSettings = []; }

if (!empty($buckets) && is_array($buckets)) {
  foreach (array_keys($buckets) as $bucketName) {
    try {
      $db = get_bucket_db($bucketName);
      $storageDb = get_bucket_storage_db($bucketName);
      $userCount = (int) ($db->query('SELECT COUNT(*) FROM users')->fetchColumn() ?: 0);
      $fileCount = (int) ($db->query('SELECT COUNT(*) FROM files')->fetchColumn() ?: 0);
      $totalBytes = (int) ($db->query('SELECT COALESCE(SUM(size_bytes),0) FROM files')->fetchColumn() ?: 0);
      $storageBytes = (int) ($storageDb->query('SELECT COALESCE(SUM(OCTET_LENGTH(file_content)),0) FROM file_data')->fetchColumn() ?: 0);
      $isActive = true; foreach ($bucketSettings as $row) { if (($row['bucket_id'] ?? '') === $bucketName) { $isActive = ((int)$row['active']===1); break; } }
      $bucketStats[$bucketName] = [
        'name' => $bucketName,
        'host' => $buckets[$bucketName]['host'] ?? 'N/A',
        'port' => $buckets[$bucketName]['port'] ?? 'N/A',
        'user_count' => $userCount,
        'file_count' => $fileCount,
        'total_storage_mb' => round($totalBytes/1024/1024, 2),
        'storage_db_mb' => round($storageBytes/1024/1024, 2),
        'status' => 'online',
        'active' => $isActive,
      ];
    } catch (Throwable $e) {
      $isActive = true; foreach ($bucketSettings as $row) { if (($row['bucket_id'] ?? '') === $bucketName) { $isActive = ((int)$row['active']===1); break; } }
      $bucketStats[$bucketName] = [
        'name' => $bucketName,
        'host' => $buckets[$bucketName]['host'] ?? 'N/A',
        'port' => $buckets[$bucketName]['port'] ?? 'N/A',
        'user_count' => 0,
        'file_count' => 0,
        'total_storage_mb' => 0,
        'storage_db_mb' => 0,
        'status' => 'offline',
        'active' => $isActive,
      ];
    }
  }
}

?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maxxcloud Status</title>
  <style>
    :root { --bg:#0f1115; --card:#151822; --muted:#97a0af; --accent:#0ea5a4; --ok:#2fb344; --bad:#d63939; }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:#fff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu,Arial,sans-serif}
    .wrap{max-width:1100px;margin:0 auto;padding:24px}
    .header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
    .brand{display:flex;align-items:center;gap:10px;font-weight:700}
    .brand-badge{padding:4px 8px;border-radius:6px;background:rgba(255,255,255,0.06);font-size:12px;color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
    .card{background:var(--card);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:16px}
    .card h3{margin:0 0 10px 0;font-size:18px}
    .status{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px}
    .ok{background:var(--ok)} .bad{background:var(--bad)}
    .muted{color:var(--muted)}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid rgba(255,255,255,0.06);padding:8px;text-align:left;font-size:14px}
    .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;background:rgba(255,255,255,0.08)}
    .badge-ok{background:rgba(47,179,68,0.2);color:#b9f6c5}
    .badge-bad{background:rgba(214,57,57,0.2);color:#ffc9c9}
    .foot{margin-top:16px;color:var(--muted);font-size:12px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="header">
      <div class="brand"><div style="width:10px;height:10px;border-radius:50%;background:var(--accent)"></div> Maxxcloud Status <span class="brand-badge">öffentlich</span></div>
      <a href="index.php" style="color:#fff;text-decoration:none">Zur App</a>
    </div>
    <div class="grid" style="margin-bottom:16px">
      <div class="card"><h3>System</h3>
        <div class="muted">Wartungsmodus</div>
        <div class="status <?= $overall['maintenance']?'bad':'ok' ?>"><?= $overall['maintenance']?'aktiv':'aus' ?></div>
        <div class="muted" style="margin-top:10px">Master Datenbank</div>
        <div class="status <?= $overall['master_db']?'ok':'bad' ?>"><?= $overall['master_db']?'online':'offline' ?></div>
      </div>
      <div class="card"><h3>Dienste</h3>
        <div class="muted">E-Mail</div>
        <div class="status <?= $overall['email_configured']?'ok':'bad' ?>"><?= $overall['email_configured']?'konfiguriert':'fehlt' ?></div>
        <div class="muted" style="margin-top:10px">Captcha</div>
        <div class="status <?= $overall['turnstile']?'ok':'bad' ?>"><?= $overall['turnstile']?'aktiv':'inaktiv' ?></div>
      </div>
      <div class="card"><h3>Speicher</h3>
        <div class="muted">Pfad</div>
        <div class="status <?= $overall['storage_writable']?'ok':'bad' ?>"><?= $overall['storage_writable']?'schreibbar':'blockiert' ?></div>
      </div>
    </div>
    <div class="card">
      <h3>Buckets</h3>
      <div class="muted" style="margin-bottom:6px">Status und Auslastung</div>
      <div class="table-responsive">
      <table>
        <thead><tr><th>Bucket</th><th>Aktiv</th><th>Status</th><th>Host</th><th>User</th><th>Dateien</th><th>Speicher</th><th>Storage DB</th></tr></thead>
        <tbody>
          <?php foreach ($bucketStats as $b): ?>
          <tr>
            <td><?= htmlspecialchars($b['name']) ?></td>
            <td><?= !empty($b['active']) ? '<span class="badge badge-ok">aktiv</span>' : '<span class="badge badge-bad">deaktiviert</span>' ?></td>
            <td><?= $b['status'] === 'online' ? '<span class="badge badge-ok">online</span>' : '<span class="badge badge-bad">offline</span>' ?></td>
            <td><?= htmlspecialchars($b['host']) ?>:<?= htmlspecialchars((string)$b['port']) ?></td>
            <td><?= number_format((int)$b['user_count'], 0, ',', '.') ?></td>
            <td><?= number_format((int)$b['file_count'], 0, ',', '.') ?></td>
            <td><?= number_format((float)$b['total_storage_mb'], 2, ',', '.') ?> MB</td>
            <td><?= number_format((float)$b['storage_db_mb'], 2, ',', '.') ?> MB</td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($bucketStats)): ?>
          <tr><td colspan="8" class="muted">Keine Buckets konfiguriert</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
    <div class="foot">© Maxxcloud – Status wird bei Seitenaufruf live ermittelt.</div>
  </div>
</body>
</html>