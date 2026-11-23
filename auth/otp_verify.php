<?php
require_once '../config/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$type   = $_GET['type'] ?? ($_SESSION['otp_type'] ?? 'verify');
$userId = $_SESSION['otp_user_id'] ?? 0;
$error  = '';
$success = '';

$pdo = getDB();

// Handle Resend OTP request
if (isset($_GET['resend']) && $userId > 0) {
    $newOtp = generateOTP(6);
    $newExp = date('Y-m-d H:i:s', strtotime('+' . getSetting('otp_expiry_minutes', '10') . ' minutes'));
    $pdo->prepare("UPDATE users SET otp = ?, otp_expires = ? WHERE id = ?")->execute([$newOtp, $newExp, $userId]);
    $_SESSION['otp'] = $newOtp;
    $success = 'A fresh 6-digit OTP verification code has been generated.';
}

// Handle OTP Verification submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = implode('', array_map(fn($i) => trim($_POST["otp$i"] ?? ''), range(1, 6)));

    if (!$userId) {
        $error = 'Your verification session has expired. Please sign in or register again.';
    } elseif (strlen($entered_otp) < 6) {
        $error = 'Please enter all 6 digits of the OTP code.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'User account not found.';
        } elseif ($user['otp'] !== $entered_otp) {
            $error = 'Invalid OTP code. Please verify and try again.';
        } elseif (strtotime($user['otp_expires']) < time()) {
            $error = 'The verification code has expired. Please click "Resend Code" below.';
        } else {
            // OTP valid! Clear the OTP fields
            $pdo->prepare("UPDATE users SET is_verified = 1, otp = NULL, otp_expires = NULL WHERE id = ?")->execute([$userId]);

            if ($type === 'reset') {
                $_SESSION['reset_user_id'] = $userId;
                unset($_SESSION['otp_user_id'], $_SESSION['otp'], $_SESSION['otp_type']);
                header('Location: ' . BASE_URL . '/auth/reset_password.php');
                exit;
            } elseif ($type === 'login_2fa' || $type === 'verify') {
                // Log the user in
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['user_name']   = $user['name'];
                $_SESSION['user_email']  = $user['email'];
                $_SESSION['user_role']   = $user['role'];
                $_SESSION['user_avatar'] = $user['avatar'] ?: 'default.png';

                // Check remember me flag
                if (!empty($_SESSION['remember_on_login'])) {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                    setcookie('remember_token', $token, time() + 30 * 24 * 3600, '/');
                    unset($_SESSION['remember_on_login']);
                }

                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                logActivity($user['id'], '2fa_login', 'Completed OTP 2FA login verification');

                unset($_SESSION['otp_user_id'], $_SESSION['otp'], $_SESSION['otp_type']);
                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verification Code (OTP) | AgriConnect</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-banner" style="background:linear-gradient(135deg,#064e3b,#16a34a);">
      <div class="auth-logo"><i class="fas fa-shield-alt"></i></div>
      <h1>AgriConnect</h1>
      <p>Identity Verification & Security</p>
    </div>
    
    <div class="auth-body">
      <h2>
        <?= ($type === 'login_2fa') ? 'Two-Factor Authentication' : (($type === 'reset') ? 'Reset Code Verification' : 'Verify Your Account') ?>
      </h2>
      <p class="subtitle">
        Enter the 6-digit code sent to verify your identity. Valid for <?= htmlspecialchars(getSetting('otp_expiry_minutes', '10')) ?> minutes.
      </p>

      <?php if ($error): ?>
      <div class="auth-alert auth-alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="auth-alert auth-alert-success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($success) ?>
      </div>
      <?php endif; ?>

      <!-- Demo OTP Auto-Display for seamless testing -->
      <?php if (isset($_SESSION['otp'])): ?>
      <div class="auth-alert auth-alert-info" style="margin-bottom:16px;">
        <i class="fas fa-key"></i>
        <div>
          <strong>Demo Verification Code:</strong> 
          <span style="font-size:16px;letter-spacing:2px;font-family:monospace;font-weight:700;color:var(--primary-dark);margin-left:4px;">
            <?= htmlspecialchars($_SESSION['otp']) ?>
          </span>
          <br><span style="font-size:11px;opacity:0.85;">(Auto-filled or type digits below)</span>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" id="otpForm">
        <div class="otp-group">
          <?php for ($i = 1; $i <= 6; $i++): ?>
          <input type="text" class="otp-input" name="otp<?= $i ?>" maxlength="1" inputmode="numeric" pattern="\d" required autocomplete="off">
          <?php endfor; ?>
        </div>

        <button type="submit" class="btn-auth" style="margin-top:20px;">
          <i class="fas fa-check-circle"></i> 
          <?= ($type === 'login_2fa') ? 'Confirm & Sign In' : 'Verify Code' ?>
        </button>
      </form>

      <div style="text-align:center;margin-top:20px;font-size:13px;color:var(--gray-600);">
        Didn't receive the code?
        <a href="otp_verify.php?type=<?= htmlspecialchars($type) ?>&resend=1" class="auth-link" style="margin-left:4px;font-weight:600;">
          <i class="fas fa-redo"></i> Resend Code
        </a>
      </div>
    </div>

    <div class="auth-footer">
      <a href="login.php"><i class="fas fa-arrow-left"></i> Return to Sign In</a>
    </div>
  </div>
</div>

<script>
// Auto-fill demo OTP if available for smooth experience
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['otp'])): ?>
    const demoOtp = "<?= htmlspecialchars($_SESSION['otp']) ?>";
    const inputs = document.querySelectorAll('.otp-input');
    if (demoOtp.length === 6 && inputs.length === 6) {
        inputs.forEach((inp, idx) => {
            inp.value = demoOtp[idx];
            inp.classList.add('filled');
        });
    }
    <?php endif; ?>
});
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
