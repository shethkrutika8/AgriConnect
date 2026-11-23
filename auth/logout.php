<?php
require_once '../config/db.php';
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
}
session_destroy();
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
