<?php
require_once '../config/db.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $error = 'No account found with this email address.';
        } else {
            $otp = generateOTP(6);
            $exp = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $pdo->prepare("UPDATE users SET otp=?, otp_expires=? WHERE email=?")->execute([$otp, $exp, $email]);
            $_SESSION['otp_user_id'] = $user['id'];
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_type'] = 'reset';
            $success = "OTP sent to <strong>$email</strong>. Check your email (or use demo OTP below).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | AgriConnect</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-banner">
      <div class="auth-logo"><i class="fas fa-seedling"></i></div>
      <h1>AgriConnect</h1>
      <p>Reset your password</p>
    </div>
    <div class="auth-body">
      <h2>Forgot Password?</h2>
      <p class="subtitle">Enter your registered email and we'll send you a reset OTP.</p>

      <?php if ($error): ?>
      <div class="auth-alert auth-alert-danger"><i class="fas fa-exclamation-circle"></i><?= $error ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
      <div class="auth-alert auth-alert-success"><i class="fas fa-check-circle"></i><?= $success ?>
        <?php if (isset($_SESSION['otp'])): ?>
        <br><strong>Demo OTP: <?= $_SESSION['otp'] ?></strong>
        <?php endif; ?>
      </div>
      <a href="otp_verify.php?type=reset" class="btn-auth" style="margin-top:10px;text-decoration:none;">
        <i class="fas fa-key"></i> Enter OTP
      </a>
      <?php else: ?>
      <form method="POST">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
          </div>
        </div>
        <button type="submit" class="btn-auth"><i class="fas fa-paper-plane"></i> Send OTP</button>
      </form>
      <?php endif; ?>
    </div>
    <div class="auth-footer"><a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a></div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
