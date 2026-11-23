<?php
// ============================================================
// AgriConnect – Database Setup & Diagnostics Utility
// Can be run via browser: http://localhost/AgriConnect/database/setup.php
// Or via CLI: php database/setup.php
// ============================================================

$isCli = (php_sapi_name() === 'cli');

define('SETUP_DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('SETUP_DB_PORT', getenv('DB_PORT') ?: '3306');
define('SETUP_DB_NAME', getenv('DB_NAME') ?: 'agriconnect');
define('SETUP_DB_USER', getenv('DB_USER') ?: 'root');
define('SETUP_DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

$step = $_GET['action'] ?? ($argv[1] ?? ($isCli ? 'reset' : 'status'));
$message = '';
$messageType = 'info';
$tablesInfo = [];
$serverOk = false;
$dbOk = false;

// 1. Test Server Connection
try {
    $dsnNoDb = "mysql:host=" . SETUP_DB_HOST . ";port=" . SETUP_DB_PORT . ";charset=utf8mb4";
    $pdoServer = new PDO($dsnNoDb, SETUP_DB_USER, SETUP_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    $serverOk = true;
} catch (PDOException $e) {
    $serverError = $e->getMessage();
}

// 2. Action: Run Import / Setup
if ($serverOk && in_array($step, ['run', 'migrate', 'init'])) {
    try {
        $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `" . SETUP_DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $pdoDb = new PDO(
            "mysql:host=" . SETUP_DB_HOST . ";port=" . SETUP_DB_PORT . ";dbname=" . SETUP_DB_NAME . ";charset=utf8mb4",
            SETUP_DB_USER,
            SETUP_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sqlFile = __DIR__ . '/agriconnect.sql';
        if (file_exists($sqlFile)) {
            $rawSql = file_get_contents($sqlFile);
            $cleanLines = [];
            foreach (explode("\n", $rawSql) as $line) {
                $trimmed = trim($line);
                if (empty($trimmed) || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                    continue;
                }
                $cleanLines[] = $line;
            }
            $cleanSql = implode("\n", $cleanLines);
            $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
            foreach ($statements as $st) {
                if (!empty($st)) {
                    $pdoDb->exec($st);
                }
            }
        }

        // Ensure is_2fa_enabled column exists
        try {
            $colCheck = $pdoDb->query("SHOW COLUMNS FROM users LIKE 'is_2fa_enabled'")->fetch();
            if (!$colCheck) {
                $pdoDb->exec("ALTER TABLE users ADD COLUMN is_2fa_enabled TINYINT(1) DEFAULT 0 AFTER is_verified");
            }
        } catch (Exception $ex) {}

        $message = "Database `" . SETUP_DB_NAME . "` has been successfully created and initialized with all tables and sample seed data!";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Setup Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// 3. Inspect Database if server is accessible
if ($serverOk) {
    try {
        $pdoDb = new PDO(
            "mysql:host=" . SETUP_DB_HOST . ";port=" . SETUP_DB_PORT . ";dbname=" . SETUP_DB_NAME . ";charset=utf8mb4",
            SETUP_DB_USER,
            SETUP_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $dbOk = true;

        // Fetch tables and counts
        $tables = $pdoDb->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $count = $pdoDb->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            $tablesInfo[$t] = $count;
        }
    } catch (Exception $e) {
        $dbOk = false;
        if (empty($message)) {
            $message = "Database `" . SETUP_DB_NAME . "` does not exist yet. Click 'Run Database Setup' below to create and seed it.";
            $messageType = 'warning';
        }
    }
}

if ($isCli) {
    echo "========================================================\n";
    echo "AgriConnect Database Setup Tool (CLI)\n";
    echo "========================================================\n";
    if ($serverOk) {
        echo "MySQL Server: Connected (" . SETUP_DB_HOST . ":" . SETUP_DB_PORT . ")\n";
        echo "Database Status: " . ($dbOk ? "Ready" : "Not Initialized") . "\n";
        if (!empty($message)) echo "Message: $message\n";
        if (!empty($tablesInfo)) {
            echo "Tables & Row Counts:\n";
            foreach ($tablesInfo as $tbl => $cnt) {
                echo "  - $tbl: $cnt rows\n";
            }
        }
    } else {
        echo "MySQL Server: FAILED TO CONNECT\n";
        echo "Error: " . ($serverError ?? 'Unknown') . "\n";
        echo "Please ensure MySQL is running (e.g. via Laragon, XAMPP, or mysqld.exe).\n";
    }
    echo "========================================================\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Setup & Diagnostics | AgriConnect</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    body { background: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .setup-container { width: 100%; max-width: 780px; }
    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px; font-weight: 600; font-size: 12px; }
    .status-online { background: #dcfce7; color: #15803d; }
    .status-offline { background: #fee2e2; color: #b91c1c; }
    .table-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 14px; }
    .table-item { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
  </style>
</head>
<body>
<div class="setup-container">
  <div class="card" style="box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);">
    <div class="card-header" style="background: linear-gradient(135deg, var(--secondary), var(--primary)); color: white; padding: 24px;">
      <div class="d-flex align-center gap-12">
        <div style="width:42px;height:42px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-database" style="font-size:20px;"></i>
        </div>
        <div>
          <h1 style="font-size: 20px; font-weight: 700; margin: 0;">AgriConnect Database Setup</h1>
          <p style="font-size: 13px; opacity: 0.85; margin: 0;">MySQL Database Connectivity, Migration & Verification</p>
        </div>
      </div>
    </div>

    <div class="card-body" style="padding: 24px;">
      <?php if (!empty($message)): ?>
      <div class="alert alert-<?= $messageType ?> mb-24">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-circle' : 'info-circle') ?>"></i>
        <div><?= htmlspecialchars($message) ?></div>
      </div>
      <?php endif; ?>

      <!-- Connection Status Overview -->
      <div class="card mb-24" style="background:#f9fafb;border:1px solid #e5e7eb;">
        <div class="card-body">
          <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;">Connection Parameters</h3>
          <div class="grid-2 gap-12" style="font-size:13px;">
            <div><strong>Host:</strong> <code><?= htmlspecialchars(SETUP_DB_HOST) ?></code></div>
            <div><strong>Port:</strong> <code><?= htmlspecialchars(SETUP_DB_PORT) ?></code></div>
            <div><strong>Database:</strong> <code><?= htmlspecialchars(SETUP_DB_NAME) ?></code></div>
            <div><strong>Username:</strong> <code><?= htmlspecialchars(SETUP_DB_USER) ?></code></div>
          </div>
          <hr class="divider" style="margin:14px 0;">
          <div class="d-flex justify-between align-center">
            <div>
              <strong>MySQL Server:</strong>
              <?php if ($serverOk): ?>
                <span class="status-badge status-online"><i class="fas fa-circle" style="font-size:8px;"></i> Online</span>
              <?php else: ?>
                <span class="status-badge status-offline"><i class="fas fa-circle" style="font-size:8px;"></i> Offline / Unreachable</span>
              <?php endif; ?>
            </div>
            <div>
              <strong>Database Ready:</strong>
              <?php if ($dbOk): ?>
                <span class="status-badge status-online"><i class="fas fa-check"></i> Initialized (<?= count($tablesInfo) ?> tables)</span>
              <?php else: ?>
                <span class="status-badge status-offline"><i class="fas fa-times"></i> Not Ready</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <?php if (!$serverOk): ?>
      <div class="alert alert-danger mb-24">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
          <strong>Cannot connect to MySQL server:</strong><br>
          <?= htmlspecialchars($serverError ?? 'Connection refused') ?><br><br>
          <strong>Troubleshooting steps:</strong>
          <ul style="margin: 8px 0 0 16px; list-style: disc;">
            <li>Open Laragon and click <strong>Start All</strong>, or open XAMPP and start <strong>MySQL</strong>.</li>
            <li>Verify MySQL is listening on port <code>3306</code> with user <code>root</code>.</li>
            <li>Refresh this page once MySQL is running.</li>
          </ul>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($serverOk && !empty($tablesInfo)): ?>
      <h3 style="font-size:15px;font-weight:600;margin-bottom:8px;">Database Tables & Records</h3>
      <div class="table-list mb-24">
        <?php foreach ($tablesInfo as $tbl => $cnt): ?>
        <div class="table-item">
          <span><i class="fas fa-table" style="color:var(--primary);margin-right:6px;"></i><?= htmlspecialchars($tbl) ?></span>
          <span class="badge badge-primary"><?= number_format($cnt) ?> rows</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Actions -->
      <div class="d-flex gap-12" style="flex-wrap:wrap;margin-top:20px;">
        <?php if ($serverOk): ?>
        <a href="setup.php?action=run" class="btn btn-primary">
          <i class="fas fa-sync-alt"></i> <?= $dbOk ? 'Verify & Update Schema' : 'Initialize Database Now' ?>
        </a>
        <?php endif; ?>
        <a href="../auth/login.php" class="btn btn-secondary">
          <i class="fas fa-sign-in-alt"></i> Go to Login
        </a>
        <a href="../dashboard.php" class="btn btn-outline-primary">
          <i class="fas fa-th-large"></i> Go to Dashboard
        </a>
      </div>

      <!-- Demo Credentials Reference -->
      <hr class="divider" style="margin:24px 0 16px;">
      <div style="font-size:12px;color:var(--gray-500);background:#f9fafb;padding:12px 16px;border-radius:6px;">
        <strong>Demo Login Credentials:</strong><br>
        Admin: <code>admin@agriconnect.com</code> &nbsp;|&nbsp; Password: <code>password</code> (or <code>Admin@123</code>)<br>
        Farmer: <code>rajesh@example.com</code> or <code>farmer@agriconnect.com</code> &nbsp;|&nbsp; Password: <code>password</code><br>
        Buyer: <code>suresh@example.com</code> &nbsp;|&nbsp; Password: <code>password</code>
      </div>
    </div>
  </div>
</div>
</body>
</html>
