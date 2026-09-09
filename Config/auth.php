<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

const NETWORK_MONITOR_USERNAME = 'admin';
const NETWORK_MONITOR_PASSWORD = 'admin123';
const NETWORK_MONITOR_SESSION_TIMEOUT = 28800;

function isLocalCollectorRequest(): bool
{
    if (PHP_SAPI === 'cli') return true;
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    return in_array($remote, ['127.0.0.1', '::1'], true) && strpos($uri, '/collector/') !== false;
}

function isLoggedIn(): bool
{
    // Collector dijalankan oleh collector_loop.php melalui localhost tanpa sesi browser.
    if (isLocalCollectorRequest()) return true;
    if (empty($_SESSION['network_monitor_logged_in'])) return false;
    $last = (int)($_SESSION['network_monitor_last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > NETWORK_MONITOR_SESSION_TIMEOUT) {
        logoutUser();
        return false;
    }
    $_SESSION['network_monitor_last_activity'] = time();
    return true;
}

function requireLogin(): void
{
    if (isLoggedIn()) return;
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($uri, '/api/') !== false) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'authenticated'=>false,'message'=>'Sesi login diperlukan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ../auth/login.php');
    exit;
}

function loginUser(string $username, string $password): bool
{
    if (!hash_equals(NETWORK_MONITOR_USERNAME, trim($username)) || !hash_equals(NETWORK_MONITOR_PASSWORD, $password)) return false;
    session_regenerate_id(true);
    $_SESSION['network_monitor_logged_in'] = true;
    $_SESSION['network_monitor_username'] = NETWORK_MONITOR_USERNAME;
    $_SESSION['network_monitor_login_at'] = date('Y-m-d H:i:s');
    $_SESSION['network_monitor_last_activity'] = time();
    return true;
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
