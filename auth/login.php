<?php
require_once '../config/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status != 'suspended'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify the supplied password against the stored bcrypt hash
        $passwordMatches = $user ? password_verify($password, $user['password']) : false;

        if ($user && $passwordMatches) {
            // Transparently upgrade legacy hashes to the current BCRYPT cost
            if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
                logActivity($user['id'], 'password_rehash', 'Password hash upgraded to current BCRYPT cost');
            }

            // Check if Two-Factor Authentication (2FA) is enabled for this account
            if (!empty($user['is_2fa_enabled'])) {
                $otp = generateOTP(6);
                $otpExp = date('Y-m-d H:i:s', strtotime('+' . getSetting('otp_expiry_minutes', '10') . ' minutes'));
                
                $pdo->prepare("UPDATE users SET otp = ?, otp_expires = ? WHERE id = ?")
                    ->execute([$otp, $otpExp, $user['id']]);

                $_SESSION['otp_user_id'] = $user['id'];
                $_SESSION['otp_type']    = 'login_2fa';
                $_SESSION['otp']         = $otp;
                if ($remember) {
                    $_SESSION['remember_on_login'] = true;
                }

                header('Location: ' . BASE_URL . '/auth/otp_verify.php?type=login_2fa');
                exit;
            }

            // Normal login (2FA not required)
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_name']   = $user['name'];
            $_SESSION['user_email']  = $user['email'];
            $_SESSION['user_role']   = $user['role'];
            $_SESSION['user_avatar'] = $user['avatar'] ?: 'default.png';

            // Update last login timestamp
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            // Remember me cookie
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                setcookie('remember_token', $token, time() + 30 * 24 * 3600, '/');
            }

            logActivity($user['id'], 'login', 'User logged in from ' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));

            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | AgriConnect</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <!-- Banner -->
    <div class="auth-banner">
      <div class="auth-logo">
        <i class="fas fa-seedling"></i>
      </div>
      <h1>AgriConnect</h1>
      <p>Smart Agriculture Management System</p>
    </div>

    <!-- Body -->
    <div class="auth-body">
      <h2>Welcome back!</h2>
      <p class="subtitle">Sign in to your account to continue</p>

      <?php if ($error): ?>
      <div class="auth-alert auth-alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <?php if (isset($_GET['registered'])): ?>
      <div class="auth-alert auth-alert-success">
        <i class="fas fa-check-circle"></i>
        Registration successful! Please sign in with your credentials.
      </div>
      <?php endif; ?>

      <?php if (isset($_GET['reset_success'])): ?>
      <div class="auth-alert auth-alert-success">
        <i class="fas fa-check-circle"></i>
        Password reset successful. You can login with your new password.
      </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" name="email" id="emailInput" class="form-control"
                   placeholder="admin@agriconnect.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autocomplete="email">
          </div>
        </div>

        <div class="form-group">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <label class="form-label" style="margin-bottom:0;">Password</label>
            <a href="forgot_password.php" class="auth-link">Forgot password?</a>
          </div>
          <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="••••••••" required autocomplete="current-password"
                   style="padding-right:40px;">
            <button type="button" class="toggle-pw" tabindex="-1">
              <i class="fas fa-eye-slash"></i>
            </button>
          </div>
        </div>

        <div class="form-check">
          <input type="checkbox" id="remember" name="remember">
          <label for="remember">Remember me for 30 days</label>
        </div>

        <button type="submit" class="btn-auth">
          <i class="fas fa-sign-in-alt"></i>
          Sign In
        </button>
      </form>
    </div>

    <!-- Footer -->
    <div class="auth-footer">
      Don't have an account? <a href="register.php">Create one</a>
    </div>

    <!-- Demo Quick-Fill Bar -->
    <div style="background:var(--gray-50);padding:14px 24px;border-top:1px solid var(--gray-200);font-size:12px;color:var(--gray-600);line-height:1.6;">
      <strong>Quick Demo Fill:</strong><br>
      <a href="javascript:void(0)" onclick="fillDemo('admin@agriconnect.com','password')" style="color:var(--primary);font-weight:600;margin-right:10px;">Admin Account</a> |
      <a href="javascript:void(0)" onclick="fillDemo('rajesh@example.com','password')" style="color:var(--primary);font-weight:600;margin:0 10px;">Farmer Account</a> |
      <a href="javascript:void(0)" onclick="fillDemo('suresh@example.com','password')" style="color:var(--primary);font-weight:600;margin-left:10px;">Buyer Account</a>
    </div>
  </div>
</div>

<script>
function fillDemo(email, pass) {
    document.getElementById('emailInput').value = email;
    document.getElementById('password').value = pass;
}
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
