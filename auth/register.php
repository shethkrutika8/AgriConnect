<?php
require_once '../config/db.php';

if (isLoggedIn()) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = trim($_POST['role'] ?? 'farmer');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    if (empty($name))     $errors['name']     = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
    if (empty($password) || strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';
    if (!in_array($role, ['admin','farmer','buyer'])) $role = 'farmer';

    if (empty($errors)) {
        $pdo  = getDB();
        $exists = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $errors['email'] = 'This email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $otp  = generateOTP(6);
            $otp_exp = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, role, password, otp, otp_expires, is_verified) VALUES (?,?,?,?,?,?,?,0)");
            $stmt->execute([$name, $email, $phone, $role, $hash, $otp, $otp_exp]);
            $userId = $pdo->lastInsertId();

            // If farmer, create farmer profile
            if ($role === 'farmer') {
                $pdo->prepare("INSERT INTO farmers (user_id, farm_name, status) VALUES (?, ?, 'active')")->execute([$userId, "$name's Farm"]);
            }

            logActivity($userId, 'registered', "New user registered: $email");

            // Store for OTP page
            $_SESSION['otp_user_id'] = $userId;
            $_SESSION['otp_type']    = 'verify';
            $_SESSION['otp']         = $otp;

            header('Location: ' . BASE_URL . '/auth/otp_verify.php?type=verify&registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | AgriConnect</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body>
<div class="auth-wrapper" style="max-width:520px;">
  <div class="auth-card">
    <div class="auth-banner">
      <div class="auth-logo"><i class="fas fa-seedling"></i></div>
      <h1>AgriConnect</h1>
      <p>Create your account today</p>
    </div>

    <div class="auth-body">
      <h2>Create Account</h2>
      <p class="subtitle">Fill in your details to get started</p>

      <?php if (!empty($errors)): ?>
      <div class="auth-alert auth-alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div>
          <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <div class="input-group">
            <i class="fas fa-user input-icon"></i>
            <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                   placeholder="John Farmer" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                   placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>
          <?php if (isset($errors['email'])): ?><div class="invalid-feedback" style="display:block;"><?= $errors['email'] ?></div><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <div class="input-group">
            <i class="fas fa-phone input-icon"></i>
            <input type="tel" name="phone" class="form-control"
                   placeholder="+91-9876543210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Account Type</label>
          <div class="input-group">
            <i class="fas fa-user-tag input-icon"></i>
            <select name="role" class="form-control" style="padding-left:40px;">
              <option value="farmer" <?= ($_POST['role'] ?? '') === 'farmer' ? 'selected' : '' ?>>🌾 Farmer – Sell my produce</option>
              <option value="buyer"  <?= ($_POST['role'] ?? '') === 'buyer'  ? 'selected' : '' ?>>🛒 Buyer  – Buy from farmers</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                   placeholder="Min. 6 characters" required style="padding-right:40px;">
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
            <input type="password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                   placeholder="Repeat password" required style="padding-right:40px;">
            <button type="button" class="toggle-pw" tabindex="-1"><i class="fas fa-eye-slash"></i></button>
          </div>
          <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback" style="display:block;"><?= $errors['confirm_password'] ?></div><?php endif; ?>
        </div>

        <button type="submit" class="btn-auth">
          <i class="fas fa-user-plus"></i> Create Account
        </button>
      </form>
    </div>

    <div class="auth-footer">
      Already have an account? <a href="login.php">Sign in</a>
    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
