<?php
/**
 * AgriConnect - Root Entry Point
 * Redirects authenticated users to the dashboard, and guests to the login page.
 */
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
} else {
    redirect(BASE_URL . '/auth/login.php');
}
