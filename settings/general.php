<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'settings';
$page_title  = 'System Settings';
$breadcrumb  = [['label' => 'Settings']];

$pdo  = getDB();
$user = currentUser();

$successMsg = '';
$errorMsg   = '';

// Save settings (only admins can modify global settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    if ($user['role'] !== 'admin') {
        $errorMsg = 'Administrative permissions are required to modify system settings.';
    } else {
        $settingsToUpdate = [
            'site_name'           => trim($_POST['site_name'] ?? 'AgriConnect'),
            'site_tagline'        => trim($_POST['site_tagline'] ?? 'Connecting Farmers to Markets'),
            'currency'            => trim($_POST['currency'] ?? 'INR'),
            'currency_symbol'     => trim($_POST['currency_symbol'] ?? '₹'),
            'items_per_page'      => (string)max(5, (int)($_POST['items_per_page'] ?? 10)),
            'otp_expiry_minutes'  => (string)max(1, (int)($_POST['otp_expiry_minutes'] ?? 10)),
            'admin_email'         => trim($_POST['admin_email'] ?? 'admin@agriconnect.com'),
            'weather_city'        => trim($_POST['weather_city'] ?? 'New Delhi'),
        ];

        foreach ($settingsToUpdate as $key => $val) {
            updateSetting($key, $val);
        }

        logActivity($user['id'], 'settings_updated', 'Updated system general configuration settings');
        $successMsg = 'System settings have been successfully updated!';
    }
}

// Current setting values
$siteName       = getSetting('site_name', 'AgriConnect');
$siteTagline    = getSetting('site_tagline', 'Connecting Farmers to Markets');
$currency       = getSetting('currency', 'INR');
$currencySymbol = getSetting('currency_symbol', '₹');
$itemsPerPage   = getSetting('items_per_page', '10');
$otpExpiry      = getSetting('otp_expiry_minutes', '10');
$adminEmail     = getSetting('admin_email', 'admin@agriconnect.com');
$weatherCity    = getSetting('weather_city', 'New Delhi');

include '../includes/header.php';
?>

<div class="page-content">
  <!-- Header -->
  <div class="page-header">
    <div>
      <div class="page-title">System Settings & Configuration</div>
      <div class="page-subtitle">Configure application defaults, currency, regional data and policies</div>
    </div>
    <div class="d-flex gap-8">
      <a href="../database/setup.php" class="btn btn-outline-primary"><i class="fas fa-database"></i> Database Diagnostics</a>
      <a href="profile.php" class="btn btn-secondary"><i class="fas fa-user-cog"></i> My Profile</a>
    </div>
  </div>

  <?php if (!empty($successMsg)): ?>
  <div class="alert alert-success mb-24">
    <i class="fas fa-check-circle"></i>
    <div><?= htmlspecialchars($successMsg) ?></div>
  </div>
  <?php endif; ?>

  <?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger mb-24">
    <i class="fas fa-exclamation-circle"></i>
    <div><?= htmlspecialchars($errorMsg) ?></div>
  </div>
  <?php endif; ?>

  <div class="grid-2 mb-24">
    
    <!-- General Settings Form -->
    <form method="POST" class="card">
      <input type="hidden" name="action" value="save_settings">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-sliders-h" style="color:var(--primary);margin-right:8px;"></i>General Application Settings</div>
      </div>
      <div class="card-body">
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Platform Name <span class="req">*</span></label>
            <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($siteName) ?>" required <?= ($user['role'] !== 'admin') ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label class="form-label">Platform Tagline</label>
            <input type="text" name="site_tagline" class="form-control" value="<?= htmlspecialchars($siteTagline) ?>" <?= ($user['role'] !== 'admin') ? 'readonly' : '' ?>>
          </div>
        </div>

        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Currency Symbol <span class="req">*</span></label>
            <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($currencySymbol) ?>" required <?= ($user['role'] !== 'admin') ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label class="form-label">Currency Code</label>
            <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($currency) ?>" required <?= ($user['role'] !== 'admin') ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label class="form-label">Pagination Items/Page</label>
            <input type="number" name="items_per_page" class="form-control" value="<?= htmlspecialchars($itemsPerPage) ?>" min="5" max="100" <?= ($user['role'] !== 'admin') ? 'readonly' : '' ?>>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">OTP Expiry (Minutes)</label>
            <input type="number" name="otp_expiry_minutes" class="form-control" value="<?= htmlspecialchars($otpExpiry) ?>" min="1" max="60" <?= ($user['role'] !== 'admin') ? 'readonly' : '' ?>>
            <div class="text-sm text-muted" style="margin-top:3px;">Validity period for login & password reset verification codes.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Default Weather Location</label>
            <input type="text" name="weather_city" class="form-control" value="<?= htmlspecialchars($weatherCity) ?>" <?= ($user['role'] !== 'admin') ? 'readonly' : '' ?>>
            <div class="text-sm text-muted" style="margin-top:3px;">Default regional city shown on dashboard & weather page.</div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">System Admin Contact Email</label>
          <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($adminEmail) ?>" <?= ($user['role'] !== 'admin') ? 'readonly' : '' ?>>
        </div>

      </div>
      <div class="card-footer d-flex justify-between align-center">
        <?php if ($user['role'] === 'admin'): ?>
        <span class="text-sm text-muted">Changes apply globally across the platform.</span>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Global Settings</button>
        <?php else: ?>
        <span class="text-sm text-muted"><i class="fas fa-lock"></i> Only administrators can modify global platform parameters.</span>
        <span></span>
        <?php endif; ?>
      </div>
    </form>

    <!-- Right Column: System Environment Diagnostics -->
    <div style="display:flex;flex-direction:column;gap:24px;">
      
      <!-- System Info Card -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-server" style="color:var(--primary);margin-right:8px;"></i>System Environment</div>
        </div>
        <div class="card-body" style="font-size:13px;display:flex;flex-direction:column;gap:12px;">
          <div class="d-flex justify-between">
            <span class="text-muted">PHP Version:</span>
            <strong><?= PHP_VERSION ?></strong>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">Database Engine:</span>
            <strong>MySQL / PDO MySQL (utf8mb4)</strong>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">MySQL Host / Port:</span>
            <strong><?= htmlspecialchars(DB_HOST) ?>:<?= htmlspecialchars(DB_PORT) ?></strong>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">Database Name:</span>
            <strong><code><?= htmlspecialchars(DB_NAME) ?></code></strong>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">Web Server / SAPI:</span>
            <strong><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name()) ?></strong>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">Base Application URL:</span>
            <strong><code><?= htmlspecialchars(BASE_URL) ?></code></strong>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">Operating System:</span>
            <strong><?= PHP_OS_FAMILY ?> (<?= PHP_OS ?>)</strong>
          </div>
        </div>
      </div>

      <!-- Quick Maintenance Tools -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-tools" style="color:var(--primary);margin-right:8px;"></i>Platform Maintenance</div>
        </div>
        <div class="card-body">
          <p style="font-size:13px;color:var(--gray-600);margin-bottom:16px;">
            Quick links to run diagnostic checks, verify MySQL tables, seed data, and view registered accounts.
          </p>
          <div class="d-flex gap-8" style="flex-direction:column;">
            <a href="../database/setup.php" class="btn btn-outline-primary btn-block">
              <i class="fas fa-database"></i> Database Migration & Setup Tool
            </a>
            <?php if ($user['role'] === 'admin'): ?>
            <a href="users.php" class="btn btn-secondary btn-block">
              <i class="fas fa-users-cog"></i> Manage Platform Users
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
