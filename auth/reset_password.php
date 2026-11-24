<?php
require_once '../config/db.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
if (empty($_SESSION['reset_user_id'])) { header('Location: ' . BASE_URL . '/auth/forgot_password.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw  = trim($_POST['password'] ?? '');
    $con = trim($_POST['confirm_password'] ?? '');
    if (strlen($pw) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pw !== $con) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo = getDB();
        $pdo->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?")->execute([$hash, $_SESSION['reset_user_id']]);
        unset($_SESSION['reset_user_id']);
        header('Location: ' . BASE_URL . '/auth/login.php?reset_success=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password | AgriConnect</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-banner">
      <div class="auth-logo"><i class="fas fa-seedling"></i></div>
      <h1>AgriConnect</h1>
      <p>Set a new password</p>
    </div>
    <div class="auth-body">
      <h2>Reset Password</h2>
      <p class="subtitle">Enter your new password below.</p>
      <?php if ($error): ?>
      <div class="auth-alert auth-alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 characters" required style="padding-right:40px;">
            <button type="button" class="toggle-pw" tabindex="-1"><i class="fas fa-eye-slash"></i></button>
          </div>
          <div class="pw-strength">
            <div class="pw-strength-bar"><div class="pw-strength-fill"></div></div>
            <div class="pw-strength-text">Enter a password</div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required style="padding-right:40px;">
            <button type="button" class="toggle-pw" tabindex="-1"><i class="fas fa-eye-slash"></i></button>
          </div>
        </div>
        <button type="submit" class="btn-auth"><i class="fas fa-save"></i> Save New Password</button>
      </form>
    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
