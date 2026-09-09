<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Login sederhana untuk Network Monitor.
 * Ganti nilai di bawah ini untuk mengubah akun login default.
 */
const NETWORK_MONITOR_USERNAME = 'admin';
const NETWORK_MONITOR_PASSWORD = 'admin123';

function isLoggedIn(): bool
{
    return !empty($_SESSION['network_monitor_logged_in']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit;
    }
}

function loginUser(string $username, string $password): bool
{
    if (hash_equals(NETWORK_MONITOR_USERNAME, trim($username)) && hash_equals(NETWORK_MONITOR_PASSWORD, $password)) {
        session_regenerate_id(true);
        $_SESSION['network_monitor_logged_in'] = true;
        $_SESSION['network_monitor_username'] = NETWORK_MONITOR_USERNAME;
        $_SESSION['network_monitor_login_at'] = date('Y-m-d H:i:s');
        return true;
    }

    return false;
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
