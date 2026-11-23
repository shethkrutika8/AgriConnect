<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'profile';
$page_title  = 'User Profile & Security';
$breadcrumb  = [['label' => 'Settings', 'url' => BASE_URL . '/settings/general.php'], ['label' => 'Profile']];

$pdo  = getDB();
$user = currentUser();

// Fetch fresh user data from database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

// If farmer, fetch farmer profile
$farmerData = null;
if ($userData['role'] === 'farmer') {
    $fStmt = $pdo->prepare("SELECT * FROM farmers WHERE user_id = ?");
    $fStmt->execute([$user['id']]);
    $farmerData = $fStmt->fetch();
}

$errors = [];
$successMsg = '';

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name)) {
        $errors['profile'] = 'Full Name cannot be empty.';
    } else {
        $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?")
            ->execute([$name, $phone, $user['id']]);
        $_SESSION['user_name'] = $name;

        // If farmer, update farm details
        if ($userData['role'] === 'farmer') {
            $farmName  = trim($_POST['farm_name'] ?? '');
            $location  = trim($_POST['location'] ?? '');
            $farmSize  = (float)($_POST['farm_size'] ?? 0);
            $sizeUnit  = $_POST['size_unit'] ?? 'acres';
            $soilType  = trim($_POST['soil_type'] ?? '');
            $irrigType = trim($_POST['irrigation_type'] ?? '');
            $bio       = trim($_POST['bio'] ?? '');

            if ($farmerData) {
                $pdo->prepare("
                    UPDATE farmers 
                    SET farm_name = ?, location = ?, farm_size = ?, size_unit = ?, soil_type = ?, irrigation_type = ?, bio = ?
                    WHERE user_id = ?
                ")->execute([$farmName, $location, $farmSize ?: null, $sizeUnit, $soilType, $irrigType, $bio, $user['id']]);
            } else {
                $pdo->prepare("
                    INSERT INTO farmers (user_id, farm_name, location, farm_size, size_unit, soil_type, irrigation_type, bio, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ")->execute([$user['id'], $farmName ?: "$name's Farm", $location, $farmSize ?: null, $sizeUnit, $soilType, $irrigType, $bio]);
            }
        }

        logActivity($user['id'], 'profile_updated', 'Updated personal profile details');
        $successMsg = 'Profile details updated successfully!';
        
        // Refresh local data
        $userData['name']  = $name;
        $userData['phone'] = $phone;
        if ($farmerData) {
            $farmerData['farm_name'] = $_POST['farm_name'] ?? '';
            $farmerData['location']  = $_POST['location'] ?? '';
            $farmerData['bio']       = $_POST['bio'] ?? '';
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $curPass = trim($_POST['current_password'] ?? '');
    $newPass = trim($_POST['new_password'] ?? '');
    $conPass = trim($_POST['confirm_password'] ?? '');

    if (!password_verify($curPass, $userData['password'])) {
        $errors['password'] = 'Current password is incorrect.';
    } elseif (strlen($newPass) < 6) {
        $errors['password'] = 'New password must be at least 6 characters.';
    } elseif ($newPass !== $conPass) {
        $errors['password'] = 'New password confirmation does not match.';
    } else {
        $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
        logActivity($user['id'], 'password_changed', 'Changed account login password');
        $successMsg = 'Password successfully updated!';
    }
}

// Handle 2FA Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_2fa') {
    $new2faState = !empty($_POST['enable_2fa']) ? 1 : 0;
    
    // Ensure column exists
    try {
        $pdo->prepare("UPDATE users SET is_2fa_enabled = ? WHERE id = ?")->execute([$new2faState, $user['id']]);
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_2fa_enabled TINYINT(1) DEFAULT 0 AFTER is_verified");
        $pdo->prepare("UPDATE users SET is_2fa_enabled = ? WHERE id = ?")->execute([$new2faState, $user['id']]);
    }
    
    $userData['is_2fa_enabled'] = $new2faState;
    logActivity($user['id'], '2fa_toggled', 'Two-Factor Authentication ' . ($new2faState ? 'enabled' : 'disabled'));
    $successMsg = 'Two-factor authentication (2FA) ' . ($new2faState ? 'enabled' : 'disabled') . ' successfully!';
}

// Recent user activity
$recentActs = $pdo->prepare("SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
$recentActs->execute([$user['id']]);
$userActivities = $recentActs->fetchAll();

include '../includes/header.php';
?>

<div class="page-content">
  <!-- Page Header -->
  <div class="page-header">
    <div>
      <div class="page-title">Profile & Security Settings</div>
      <div class="page-subtitle">Manage your personal credentials, farm data & account security</div>
    </div>
    <div class="d-flex gap-8">
      <a href="../dashboard.php" class="btn btn-secondary"><i class="fas fa-th-large"></i> Dashboard</a>
    </div>
  </div>

  <?php if (!empty($successMsg)): ?>
  <div class="alert alert-success mb-24">
    <i class="fas fa-check-circle"></i>
    <div><?= htmlspecialchars($successMsg) ?></div>
  </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger mb-24">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php foreach ($errors as $e) echo "<div>" . htmlspecialchars($e) . "</div>"; ?></div>
  </div>
  <?php endif; ?>

  <div class="grid-2 mb-24">
    
    <!-- Left Column: Personal Profile & Farm Data -->
    <div style="display:flex;flex-direction:column;gap:24px;">
      <!-- Profile Card -->
      <form method="POST" class="card">
        <input type="hidden" name="action" value="update_profile">
        <div class="card-header d-flex justify-between align-center">
          <div class="card-title"><i class="fas fa-user-edit" style="color:var(--primary);margin-right:8px;"></i>Personal Information</div>
          <span class="badge badge-primary"><?= ucfirst($userData['role']) ?></span>
        </div>
        <div class="card-body">
          <div class="d-flex align-center gap-16 mb-20">
            <div class="avatar-initials avatar-lg" style="font-size:22px;">
              <?= strtoupper(substr($userData['name'], 0, 1)) ?>
            </div>
            <div>
              <div style="font-size:16px;font-weight:700;"><?= htmlspecialchars($userData['name']) ?></div>
              <div class="text-muted text-sm"><?= htmlspecialchars($userData['email']) ?></div>
              <div class="text-muted text-sm" style="margin-top:2px;">Member since <?= date('M Y', strtotime($userData['created_at'])) ?></div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Full Name <span class="req">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($userData['name']) ?>" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email Address (Registered)</label>
              <input type="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" readonly style="background:var(--gray-100);cursor:not-allowed;">
              <div class="text-sm text-muted" style="margin-top:3px;">Email address cannot be changed directly.</div>
            </div>
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" placeholder="+91-9876543210">
            </div>
          </div>

          <?php if ($userData['role'] === 'farmer'): ?>
          <hr class="divider" style="margin:20px 0;">
          <h4 style="font-size:15px;font-weight:700;margin-bottom:14px;color:var(--gray-900);"><i class="fas fa-tractor" style="color:var(--primary);margin-right:6px;"></i>Farm Profile</h4>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Farm / Business Name</label>
              <input type="text" name="farm_name" class="form-control" value="<?= htmlspecialchars($farmerData['farm_name'] ?? '') ?>" placeholder="e.g. Green Valley Farm">
            </div>
            <div class="form-group">
              <label class="form-label">Farm Location (State, District)</label>
              <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($farmerData['location'] ?? '') ?>" placeholder="e.g. Amritsar, Punjab">
            </div>
          </div>

          <div class="form-row-3">
            <div class="form-group">
              <label class="form-label">Farm Size</label>
              <input type="number" name="farm_size" class="form-control" value="<?= htmlspecialchars((string)($farmerData['farm_size'] ?? '')) ?>" step="0.5" min="0">
            </div>
            <div class="form-group">
              <label class="form-label">Unit</label>
              <select name="size_unit" class="form-control">
                <option value="acres" <?= ($farmerData['size_unit'] ?? '') === 'acres' ? 'selected' : '' ?>>Acres</option>
                <option value="hectares" <?= ($farmerData['size_unit'] ?? '') === 'hectares' ? 'selected' : '' ?>>Hectares</option>
                <option value="sq_meters" <?= ($farmerData['size_unit'] ?? '') === 'sq_meters' ? 'selected' : '' ?>>Sq. Meters</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Soil Type</label>
              <input type="text" name="soil_type" class="form-control" value="<?= htmlspecialchars($farmerData['soil_type'] ?? '') ?>" placeholder="e.g. Alluvial">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Irrigation System</label>
            <input type="text" name="irrigation_type" class="form-control" value="<?= htmlspecialchars($farmerData['irrigation_type'] ?? '') ?>" placeholder="e.g. Drip Irrigation / Sprinkler">
          </div>

          <div class="form-group">
            <label class="form-label">About the Farm / Bio</label>
            <textarea name="bio" class="form-control" rows="3" placeholder="Tell buyers about your agricultural background and farming practices..."><?= htmlspecialchars($farmerData['bio'] ?? '') ?></textarea>
          </div>
          <?php endif; ?>

        </div>
        <div class="card-footer d-flex justify-between">
          <span></span>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Profile Details</button>
        </div>
      </form>
    </div>

    <!-- Right Column: Security (Password & 2FA) & Activity Log -->
    <div style="display:flex;flex-direction:column;gap:24px;">
      
      <!-- Two-Factor Authentication (2FA) Card -->
      <div class="card" style="border: 2px solid <?= !empty($userData['is_2fa_enabled']) ? 'var(--primary)' : 'var(--gray-300)' ?>;">
        <div class="card-header d-flex justify-between align-center" style="<?= !empty($userData['is_2fa_enabled']) ? 'background:var(--primary-pale);' : '' ?>">
          <div class="card-title" style="color:var(--secondary);">
            <i class="fas fa-shield-alt" style="color:var(--primary);margin-right:8px;"></i>
            Two-Factor Authentication (2FA)
          </div>
          <?php if (!empty($userData['is_2fa_enabled'])): ?>
          <span class="badge badge-success"><i class="fas fa-check"></i> Enabled & Protected</span>
          <?php else: ?>
          <span class="badge badge-secondary">Disabled</span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <p style="font-size:13px;color:var(--gray-600);line-height:1.6;margin-bottom:16px;">
            Two-Factor Authentication adds an extra layer of security to your AgriConnect account. 
            When enabled, signing in requires both your password and a one-time 6-digit verification code (OTP).
          </p>

          <form method="POST">
            <input type="hidden" name="action" value="toggle_2fa">
            
            <div style="background:var(--gray-50);padding:14px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
              <div>
                <strong style="font-size:14px;">2FA Verification Code on Login</strong>
                <div class="text-muted text-sm">Sends 6-digit OTP code to verify identity during sign-in</div>
              </div>
              <label class="switch" style="position:relative;display:inline-block;width:44px;height:24px;">
                <input type="checkbox" name="enable_2fa" value="1" <?= !empty($userData['is_2fa_enabled']) ? 'checked' : '' ?> onchange="this.form.submit()">
                <span class="slider round" style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:<?= !empty($userData['is_2fa_enabled']) ? 'var(--primary)' : '#ccc' ?>;border-radius:24px;transition:.4s;"></span>
              </label>
            </div>

            <button type="submit" class="btn <?= !empty($userData['is_2fa_enabled']) ? 'btn-outline-danger' : 'btn-primary' ?> btn-block btn-sm">
              <i class="fas fa-<?= !empty($userData['is_2fa_enabled']) ? 'lock-open' : 'lock' ?>"></i>
              <?= !empty($userData['is_2fa_enabled']) ? 'Disable Two-Factor Authentication' : 'Enable Two-Factor Authentication' ?>
            </button>
          </form>
        </div>
      </div>

      <!-- Change Password Card -->
      <form method="POST" class="card">
        <input type="hidden" name="action" value="change_password">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-key" style="color:var(--primary);margin-right:8px;"></i>Change Password</div>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Current Password <span class="req">*</span></label>
            <div class="input-group">
              <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
              <button type="button" class="toggle-pw" tabindex="-1"><i class="fas fa-eye-slash"></i></button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">New Password (Min. 6 characters) <span class="req">*</span></label>
            <div class="input-group">
              <input type="password" id="password" name="new_password" class="form-control" placeholder="••••••••" required>
              <button type="button" class="toggle-pw" tabindex="-1"><i class="fas fa-eye-slash"></i></button>
            </div>
            <div class="pw-strength" style="margin-top:6px;">
              <div class="pw-strength-bar"><div class="pw-strength-fill"></div></div>
              <div class="pw-strength-text">Enter a strong password</div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm New Password <span class="req">*</span></label>
            <div class="input-group">
              <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
              <button type="button" class="toggle-pw" tabindex="-1"><i class="fas fa-eye-slash"></i></button>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-lock"></i> Update Account Password
          </button>
        </div>
      </form>

      <!-- Recent Activity Log Card -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-history" style="color:var(--primary);margin-right:8px;"></i>Recent Account Activity</div>
        </div>
        <div class="card-body" style="padding:0;">
          <div class="table-wrapper">
            <table class="table" style="margin:0;">
              <tbody>
                <?php if (empty($userActivities)): ?>
                <tr><td class="text-center text-muted" style="padding:16px;">No recent logs.</td></tr>
                <?php else: foreach ($userActivities as $act): ?>
                <tr>
                  <td style="font-size:12px;width:110px;color:var(--gray-500);"><?= timeAgo($act['created_at']) ?></td>
                  <td style="font-size:13px;font-weight:500;"><?= htmlspecialchars($act['description']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
