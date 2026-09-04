<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/mikrotik.php';
require_once __DIR__ . '/../library/routeros_api.class.php';

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function json_out(bool $success, string $message = '', array $data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'status' => $success ? 'online' : 'offline'
    ], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function router_error($result): string {
    $messages = [];
    $walk = function($value) use (&$walk, &$messages) {
        if (!is_array($value)) return;
        foreach ($value as $key => $item) {
            if (in_array((string)$key, ['message','error'], true) && is_string($item) && trim($item) !== '') {
                $messages[] = trim($item);
            }
            if (is_array($item)) $walk($item);
        }
    };
    $walk($result);
    return implode('; ', array_values(array_unique($messages)));
}

function get_id(): string {
    return trim((string)($_POST['id'] ?? $_GET['id'] ?? ''));
}

try {
    $config = new MikroTikConfig();
    $router = $config->getRouter();
    if (!$router) {
        json_out(false, 'Konfigurasi MikroTik tidak ditemukan.', [], 500);
    }

    $api = new RouterosAPI();
    $api->debug = false;

    if (!$api->connect(
        $router['ip_address'],
        $router['username'],
        $router['password'],
        $router['api_port']
    )) {
        json_out(false, 'Gagal terhubung ke MikroTik.', [], 503);
    }

    $action = trim((string)($_GET['action'] ?? 'summary'));

    if ($action === 'snapshot') {
        $usersRaw = (array)$api->comm('/ip/hotspot/user/print');
        $profilesRaw = (array)$api->comm('/ip/hotspot/user/profile/print');
        $activeRaw = (array)$api->comm('/ip/hotspot/active/print');

        $users = [];
        $enabled = 0;
        foreach ($usersRaw as $r) {
            if (!is_array($r)) continue;
            $disabled = (($r['disabled'] ?? 'false') === 'true');
            if (!$disabled) $enabled++;
            $users[] = [
                'id' => $r['.id'] ?? '',
                'name' => $r['name'] ?? '',
                'password' => $r['password'] ?? '',
                'profile' => $r['profile'] ?? 'default',
                'server' => $r['server'] ?? 'all',
                'disabled' => $disabled,
                'limit_uptime' => $r['limit-uptime'] ?? '',
                'limit_bytes_in' => $r['limit-bytes-in'] ?? '',
                'limit_bytes_out' => $r['limit-bytes-out'] ?? '',
                'bytes_in' => $r['bytes-in'] ?? '0',
                'bytes_out' => $r['bytes-out'] ?? '0',
                'comment' => $r['comment'] ?? ''
            ];
        }

        $profiles = [];
        foreach ($profilesRaw as $r) {
            if (!is_array($r)) continue;
            $profiles[] = [
                'id' => $r['.id'] ?? '',
                'name' => $r['name'] ?? '',
                'rate_limit' => $r['rate-limit'] ?? '',
                'shared_users' => $r['shared-users'] ?? '',
                'session_timeout' => $r['session-timeout'] ?? '',
                'idle_timeout' => $r['idle-timeout'] ?? '',
                'keepalive_timeout' => $r['keepalive-timeout'] ?? '',
                'status_autorefresh' => $r['status-autorefresh'] ?? '',
                'transparent_proxy' => $r['transparent-proxy'] ?? ''
            ];
        }

        $active = [];
        $totalIn = 0;
        $totalOut = 0;
        foreach ($activeRaw as $r) {
            if (!is_array($r)) continue;
            $in = (int)($r['bytes-in'] ?? 0);
            $out = (int)($r['bytes-out'] ?? 0);
            $totalIn += $in;
            $totalOut += $out;
            $active[] = [
                'id' => $r['.id'] ?? '',
                'user' => $r['user'] ?? '',
                'address' => $r['address'] ?? '',
                'mac_address' => $r['mac-address'] ?? '',
                'login_by' => $r['login-by'] ?? '',
                'uptime' => $r['uptime'] ?? '',
                'session_id' => $r['session-id'] ?? '',
                'idle_time' => $r['idle-time'] ?? '',
                'bytes_in' => (string)$in,
                'bytes_out' => (string)$out
            ];
        }

        $api->disconnect();
        json_out(true, '', [
            'users_total' => count($users),
            'users_enabled' => $enabled,
            'profiles_total' => count($profiles),
            'active_total' => count($active),
            'users' => $users,
            'profiles' => $profiles,
            'active' => $active,
            'total_bytes_in' => (string)$totalIn,
            'total_bytes_out' => (string)$totalOut,
            'traffic' => $active
        ]);
    }

    if ($action === 'summary') {
        $users = (array)$api->comm('/ip/hotspot/user/print');
        $profiles = (array)$api->comm('/ip/hotspot/user/profile/print');
        $active = (array)$api->comm('/ip/hotspot/active/print');

        $enabled = 0;
        foreach ($users as $u) {
            if (is_array($u) && (($u['disabled'] ?? 'false') !== 'true')) $enabled++;
        }

        $api->disconnect();
        json_out(true, '', [
            'users_total' => count($users),
            'users_enabled' => $enabled,
            'profiles_total' => count($profiles),
            'active_total' => count($active)
        ]);
    }

    if ($action === 'users') {
        $rows = $api->comm('/ip/hotspot/user/print');
        $items = [];
        foreach ((array)$rows as $r) {
            if (!is_array($r)) continue;
            $items[] = [
                'id' => $r['.id'] ?? '',
                'name' => $r['name'] ?? '',
                'password' => $r['password'] ?? '',
                'profile' => $r['profile'] ?? 'default',
                'server' => $r['server'] ?? 'all',
                'disabled' => (($r['disabled'] ?? 'false') === 'true'),
                'limit_uptime' => $r['limit-uptime'] ?? '',
                'limit_bytes_in' => $r['limit-bytes-in'] ?? '',
                'limit_bytes_out' => $r['limit-bytes-out'] ?? '',
                'bytes_in' => $r['bytes-in'] ?? '0',
                'bytes_out' => $r['bytes-out'] ?? '0',
                'comment' => $r['comment'] ?? ''
            ];
        }
        $api->disconnect();
        json_out(true, '', ['total' => count($items), 'users' => $items]);
    }

    if ($action === 'profiles') {
        $rows = $api->comm('/ip/hotspot/user/profile/print');
        $items = [];
        foreach ((array)$rows as $r) {
            if (!is_array($r)) continue;
            $items[] = [
                'id' => $r['.id'] ?? '',
                'name' => $r['name'] ?? '',
                'rate_limit' => $r['rate-limit'] ?? '',
                'shared_users' => $r['shared-users'] ?? '',
                'session_timeout' => $r['session-timeout'] ?? '',
                'idle_timeout' => $r['idle-timeout'] ?? '',
                'keepalive_timeout' => $r['keepalive-timeout'] ?? '',
                'status_autorefresh' => $r['status-autorefresh'] ?? '',
                'transparent_proxy' => $r['transparent-proxy'] ?? '',
                'comment' => $r['comment'] ?? ''
            ];
        }
        $api->disconnect();
        json_out(true, '', ['total' => count($items), 'profiles' => $items]);
    }

    if ($action === 'create_profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $rate = trim((string)($_POST['rate_limit'] ?? ''));
        $shared = trim((string)($_POST['shared_users'] ?? '1'));
        $session = trim((string)($_POST['session_timeout'] ?? ''));
        $idle = trim((string)($_POST['idle_timeout'] ?? ''));
        $keepalive = trim((string)($_POST['keepalive_timeout'] ?? ''));
        $autorefresh = trim((string)($_POST['status_autorefresh'] ?? ''));
        $transparent = trim((string)($_POST['transparent_proxy'] ?? ''));

        if ($name === '') {
            $api->disconnect();
            json_out(false, 'Nama profile wajib diisi.', [], 422);
        }

        foreach ((array)$api->comm('/ip/hotspot/user/profile/print') as $row) {
            if (is_array($row) && (string)($row['name'] ?? '') === $name) {
                $api->disconnect();
                json_out(false, 'Profile Hotspot sudah ada.', [], 409);
            }
        }

        $params = ['name' => $name];
        foreach ([
            'rate-limit' => $rate,
            'shared-users' => $shared,
            'session-timeout' => $session,
            'idle-timeout' => $idle,
            'keepalive-timeout' => $keepalive,
            'status-autorefresh' => $autorefresh,
            'transparent-proxy' => $transparent
        ] as $key => $value) {
            if ($value !== '') $params[$key] = $value;
        }

        $result = $api->comm('/ip/hotspot/user/profile/add', $params);
        $error = router_error($result);
        $api->disconnect();
        if ($error !== '') json_out(false, 'MikroTik menolak profile: '.$error, ['router_response' => $result], 400);
        json_out(true, 'Profile Hotspot berhasil ditambahkan.');
    }

    if ($action === 'edit_profile') {
        if ($id === '') {
            $api->disconnect();
            json_out(false, 'ID profile wajib diisi.', [], 400);
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $rate = trim((string)($_POST['rate_limit'] ?? ''));
        $shared = trim((string)($_POST['shared_users'] ?? ''));
        $session = trim((string)($_POST['session_timeout'] ?? ''));
        $idle = trim((string)($_POST['idle_timeout'] ?? ''));
        $keepalive = trim((string)($_POST['keepalive_timeout'] ?? ''));
        $autorefresh = trim((string)($_POST['status_autorefresh'] ?? ''));
        $transparent = trim((string)($_POST['transparent_proxy'] ?? ''));

        if ($name === '') {
            $api->disconnect();
            json_out(false, 'Nama profile wajib diisi.', [], 422);
        }

        $params = ['.id' => $id, 'name' => $name];
        foreach ([
            'rate-limit' => $rate,
            'shared-users' => $shared,
            'session-timeout' => $session,
            'idle-timeout' => $idle,
            'keepalive-timeout' => $keepalive,
            'status-autorefresh' => $autorefresh,
            'transparent-proxy' => $transparent
        ] as $key => $value) {
            if ($value !== '') $params[$key] = $value;
        }

        $result = $api->comm('/ip/hotspot/user/profile/set', $params);
        $error = router_error($result);
        $api->disconnect();
        if ($error !== '') json_out(false, 'Gagal mengubah profile: '.$error, ['router_response' => $result], 400);
        json_out(true, 'Profile Hotspot berhasil diubah.');
    }

    if ($action === 'remove_profile') {
        if ($id === '') {
            $api->disconnect();
            json_out(false, 'ID profile wajib diisi.', [], 400);
        }

        $result = $api->comm('/ip/hotspot/user/profile/remove', ['.id' => $id]);
        $error = router_error($result);
        $api->disconnect();
        if ($error !== '') json_out(false, 'Gagal menghapus profile: '.$error, [], 400);
        json_out(true, 'Profile Hotspot berhasil dihapus.');
    }

    if ($action === 'traffic') {
        $rows = $api->comm('/ip/hotspot/active/print');
        $items = [];
        $totalIn = 0;
        $totalOut = 0;
        foreach ((array)$rows as $r) {
            if (!is_array($r)) continue;
            $in = (int)($r['bytes-in'] ?? 0);
            $out = (int)($r['bytes-out'] ?? 0);
            $totalIn += $in;
            $totalOut += $out;
            $items[] = [
                'id' => $r['.id'] ?? '',
                'user' => $r['user'] ?? '',
                'address' => $r['address'] ?? '',
                'mac_address' => $r['mac-address'] ?? '',
                'uptime' => $r['uptime'] ?? '',
                'bytes_in' => (string)$in,
                'bytes_out' => (string)$out
            ];
        }
        $api->disconnect();
        json_out(true, '', [
            'active_total' => count($items),
            'total_bytes_in' => (string)$totalIn,
            'total_bytes_out' => (string)$totalOut,
            'traffic' => $items
        ]);
    }

    if ($action === 'active') {
        $rows = $api->comm('/ip/hotspot/active/print');
        $items = [];
        foreach ((array)$rows as $r) {
            if (!is_array($r)) continue;
            $items[] = [
                'id' => $r['.id'] ?? '',
                'user' => $r['user'] ?? '',
                'address' => $r['address'] ?? '',
                'mac_address' => $r['mac-address'] ?? '',
                'login_by' => $r['login-by'] ?? '',
                'uptime' => $r['uptime'] ?? '',
                'session_id' => $r['session-id'] ?? '',
                'idle_time' => $r['idle-time'] ?? '',
                'bytes_in' => $r['bytes-in'] ?? '0',
                'bytes_out' => $r['bytes-out'] ?? ''
            ];
        }
        $api->disconnect();
        json_out(true, '', ['total' => count($items), 'active' => $items]);
    }

    $id = get_id();
    if ($id !== '' && !preg_match('/^\*?[0-9A-Fa-f]+$/', $id)) {
        $api->disconnect();
        json_out(false, 'ID MikroTik tidak valid.', [], 400);
    }

    if ($action === 'create_user') {
        $name = trim((string)($_POST['name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $profile = trim((string)($_POST['profile'] ?? 'default'));
        $server = trim((string)($_POST['server'] ?? ''));
        $limitUptime = trim((string)($_POST['limit_uptime'] ?? ''));
        $limitBytesIn = trim((string)($_POST['limit_bytes_in'] ?? ''));
        $limitBytesOut = trim((string)($_POST['limit_bytes_out'] ?? ''));
        $comment = trim((string)($_POST['comment'] ?? ''));

        if ($name === '' || $password === '') {
            $api->disconnect();
            json_out(false, 'Username dan password wajib diisi.', [], 422);
        }

        foreach ((array)$api->comm('/ip/hotspot/user/print') as $row) {
            if (is_array($row) && (string)($row['name'] ?? '') === $name) {
                $api->disconnect();
                json_out(false, 'Username Hotspot sudah ada.', [], 409);
            }
        }

        $params = [
            'name' => $name,
            'password' => $password,
            'profile' => $profile
        ];
        foreach ([
            'server' => $server,
            'limit-uptime' => $limitUptime,
            'limit-bytes-in' => $limitBytesIn,
            'limit-bytes-out' => $limitBytesOut,
            'comment' => $comment
        ] as $key => $value) {
            if ($value !== '') $params[$key] = $value;
        }

        $result = $api->comm('/ip/hotspot/user/add', $params);
        $error = router_error($result);
        if ($error !== '') {
            $api->disconnect();
            json_out(false, 'MikroTik menolak pembuatan user: '.$error, ['router_response' => $result], 400);
        }

        $api->disconnect();
        json_out(true, 'User Hotspot berhasil ditambahkan.');
    }

    if ($action === 'edit_user') {
        if ($id === '') {
            $api->disconnect();
            json_out(false, 'ID user wajib diisi.', [], 400);
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $profile = trim((string)($_POST['profile'] ?? 'default'));
        $server = trim((string)($_POST['server'] ?? ''));
        $limitUptime = trim((string)($_POST['limit_uptime'] ?? ''));
        $limitBytesIn = trim((string)($_POST['limit_bytes_in'] ?? ''));
        $limitBytesOut = trim((string)($_POST['limit_bytes_out'] ?? ''));
        $comment = trim((string)($_POST['comment'] ?? ''));

        if ($name === '') {
            $api->disconnect();
            json_out(false, 'Username wajib diisi.', [], 422);
        }

        $params = [
            '.id' => $id,
            'name' => $name,
            'profile' => $profile,
            'comment' => $comment
        ];
        if ($password !== '') $params['password'] = $password;
        if ($server !== '') $params['server'] = $server;
        if ($limitUptime !== '') $params['limit-uptime'] = $limitUptime;
        if ($limitBytesIn !== '') $params['limit-bytes-in'] = $limitBytesIn;
        if ($limitBytesOut !== '') $params['limit-bytes-out'] = $limitBytesOut;

        $result = $api->comm('/ip/hotspot/user/set', $params);
        $error = router_error($result);
        $api->disconnect();

        if ($error !== '') json_out(false, 'Gagal mengubah user: '.$error, ['router_response' => $result], 400);
        json_out(true, 'User Hotspot berhasil diubah.');
    }

    if ($action === 'remove_user') {
        if ($id === '') {
            $api->disconnect();
            json_out(false, 'ID user wajib diisi.', [], 400);
        }
        $result = $api->comm('/ip/hotspot/user/remove', ['.id' => $id]);
        $error = router_error($result);
        $api->disconnect();
        if ($error !== '') json_out(false, 'Gagal menghapus user: '.$error, [], 400);
        json_out(true, 'User Hotspot berhasil dihapus.');
    }

    if ($action === 'toggle_user') {
        if ($id === '') {
            $api->disconnect();
            json_out(false, 'ID user wajib diisi.', [], 400);
        }
        $disabled = ((string)($_POST['disabled'] ?? '0') === '1');
        $result = $api->comm('/ip/hotspot/user/set', [
            '.id' => $id,
            'disabled' => $disabled ? 'yes' : 'no'
        ]);
        $error = router_error($result);
        $api->disconnect();
        if ($error !== '') json_out(false, 'Gagal mengubah status user: '.$error, [], 400);
        json_out(true, $disabled ? 'User Hotspot dinonaktifkan.' : 'User Hotspot diaktifkan.');
    }

    if ($action === 'disconnect') {
        if ($id === '') {
            $api->disconnect();
            json_out(false, 'ID session wajib diisi.', [], 400);
        }
        $result = $api->comm('/ip/hotspot/active/remove', ['.id' => $id]);
        $error = router_error($result);
        $api->disconnect();
        if ($error !== '') json_out(false, 'Gagal disconnect session: '.$error, [], 400);
        json_out(true, 'Session Hotspot berhasil diputus.');
    }

    $api->disconnect();
    json_out(false, 'Action Hotspot tidak dikenal.', [], 400);

} catch (Throwable $e) {
    if (isset($api) && $api instanceof RouterosAPI) {
        try { $api->disconnect(); } catch (Throwable $ignore) {}
    }
    json_out(false, $e->getMessage(), [], 500);
}
