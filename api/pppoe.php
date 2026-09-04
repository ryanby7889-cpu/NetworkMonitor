<?php
require_once __DIR__ . '/../config/mikrotik.php';
require_once __DIR__ . '/../library/routeros_api.class.php';

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function response_json($success, $message = '', $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'status' => $success ? 'online' : 'offline'
    ], $data), JSON_UNESCAPED_SLASHES);
    exit;
}

/*
 * Normalize optional MikroTik address fields.
 * Prevents calling an undefined/null helper when the form sends
 * an empty value or placeholders such as "Optional"/"Opsional".
 */
$normalize_optional = static function ($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $lower = strtolower($value);
    if (in_array($lower, ['optional', 'opsional', '-', 'none', 'null'], true)) {
        return '';
    }

    return $value;
};

try {
    $config = new MikroTikConfig();
    $router = $config->getRouter();

    if (!$router) {
        throw new Exception('Konfigurasi MikroTik tidak ditemukan.');
    }

    $API = new RouterosAPI();
    $API->debug = false;

    if (!$API->connect(
        $router['ip_address'],
        $router['username'],
        $router['password'],
        $router['api_port']
    )) {
        throw new Exception('Gagal terhubung ke MikroTik.');
    }


    /*
     * ================================================================
     * PPPoE MANAGEMENT HELPERS
     * ================================================================
     */
    $router_error_message = static function ($result) {
        $messages = [];

        $walk = static function ($value) use (&$walk, &$messages) {
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $key => $item) {
                if (in_array((string)$key, ['message', 'error'], true) && is_string($item)) {
                    $item = trim($item);
                    if ($item !== '') {
                        $messages[] = $item;
                    }
                }

                if (is_array($item)) {
                    $walk($item);
                }
            }
        };

        $walk($result);

        $messages = array_values(array_unique($messages));
        return implode('; ', $messages);
    };

    $post_id = static function () {
        return trim((string)($_POST['id'] ?? $_GET['id'] ?? ''));
    };

    $action = $_GET['action'] ?? 'active';

    if ($action === 'active') {
        $rows = $API->comm('/ppp/active/print');
        $users = [];
        foreach ((array)$rows as $r) {
            $users[] = [
                'id' => $r['.id'] ?? '',
                'name' => $r['name'] ?? '-',
                'service' => $r['service'] ?? '-',
                'address' => $r['address'] ?? '-',
                'caller_id' => $r['caller-id'] ?? '-',
                'uptime' => $r['uptime'] ?? '-',
                'session_id' => $r['session-id'] ?? '-'
            ];
        }
        $API->disconnect();
        response_json(true, '', ['total' => count($users), 'users' => $users]);
    }

    if ($action === 'secrets') {
        $rows = $API->comm('/ppp/secret/print');
        $items = [];
        foreach ((array)$rows as $r) {
            $items[] = [
                'id' => $r['.id'] ?? '',
                'name' => $r['name'] ?? '-',
                'service' => $r['service'] ?? 'pppoe',
                'profile' => $r['profile'] ?? 'default',
                'local_address' => $r['local-address'] ?? '',
                'remote_address' => $r['remote-address'] ?? '',
                'disabled' => (($r['disabled'] ?? 'false') === 'true'),
                'comment' => $r['comment'] ?? ''
            ];
        }
        $API->disconnect();
        response_json(true, '', ['total' => count($items), 'secrets' => $items]);
    }

    if ($action === 'profiles') {
        $rows = $API->comm('/ppp/profile/print');
        $items = [];
        foreach ((array)$rows as $r) {
            $items[] = [
                'id' => $r['.id'] ?? '',
                'name' => $r['name'] ?? '-',
                'local_address' => $r['local-address'] ?? '',
                'remote_address' => $r['remote-address'] ?? '',
                'rate_limit' => $r['rate-limit'] ?? '',
                'only_one' => $r['only-one'] ?? '',
                'comment' => $r['comment'] ?? ''
            ];
        }
        $API->disconnect();
        response_json(true, '', ['total' => count($items), 'profiles' => $items]);
    }

    /*
     * CREATE PPPoE SECRET
     * This action does not use a MikroTik .id.
     * It must therefore be handled before the generic ID validation below.
     */
    if ($action === 'create_secret') {
        $name = trim($_POST['name'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $service = trim($_POST['service'] ?? 'pppoe');
        $profile = trim($_POST['profile'] ?? 'default');
        $localAddress = $normalize_optional($_POST['local_address'] ?? '');
        $remoteAddress = $normalize_optional($_POST['remote_address'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        if ($name === '') {
            $API->disconnect();
            response_json(false, 'Username wajib diisi.', [], 400);
        }

        if ($password === '') {
            $API->disconnect();
            response_json(false, 'Password wajib diisi.', [], 400);
        }

        if (!in_array($service, ['pppoe', 'any'], true)) {
            $API->disconnect();
            response_json(false, 'Service tidak valid.', [], 400);
        }

        if ($profile === '') {
            $profile = 'default';
        }

        /*
         * Cek duplikasi dengan PRINT penuh.
         * Beberapa versi RouterOS/API class menangani query `?name`
         * secara berbeda. PRINT penuh lebih kompatibel dan sekaligus
         * kita pakai lagi untuk verifikasi setelah ADD.
         */
        $existing = $API->comm('/ppp/secret/print');

        foreach ((array)$existing as $row) {
            if (is_array($row) && isset($row['name']) && (string)$row['name'] === $name) {
                $API->disconnect();
                response_json(false, 'Username PPPoE sudah ada.', [], 409);
            }
        }

        $params = [
            'name' => $name,
            'password' => $password,
            'service' => $service,
            'profile' => $profile
        ];

        if ($localAddress !== '') {
            $params['local-address'] = $localAddress;
        }

        if ($remoteAddress !== '') {
            $params['remote-address'] = $remoteAddress;
        }

        if ($comment !== '') {
            $params['comment'] = $comment;
        }

        /*
         * The bundled classic RouterosAPI::comm() automatically converts
         * ordinary keys to =key=value. Do NOT add a leading '=' here.
         */
        /*
         * Create the secret.
         * The classic RouterosAPI class returns !trap information in
         * different shapes depending on the library version, so we keep
         * the raw response and normalize errors below.
         */
        $result = $API->comm('/ppp/secret/add', $params);

        $routerErrors = [];

        $collectRouterErrors = static function ($value) use (&$collectRouterErrors, &$routerErrors) {
            if (!is_array($value)) {
                return;
            }

            // Top-level RouterOS response.
            foreach (['message', '!trap', '!fatal', 'error'] as $key) {
                if (isset($value[$key]) && is_string($value[$key]) && trim($value[$key]) !== '') {
                    $routerErrors[] = trim($value[$key]);
                }
            }

            foreach ($value as $entry) {
                if (is_array($entry)) {
                    $type = (string)($entry['!type'] ?? '');
                    $message = trim((string)($entry['message'] ?? ''));
                    $trap = trim((string)($entry['!trap'] ?? ''));
                    $fatal = trim((string)($entry['!fatal'] ?? ''));

                    if ($message !== '') $routerErrors[] = $message;
                    if ($trap !== '') $routerErrors[] = $trap;
                    if ($fatal !== '') $routerErrors[] = $fatal;
                    if ($type === '!trap' && $message === '' && $trap === '') {
                        $routerErrors[] = 'RouterOS API trap response';
                    }
                }
            }
        };

        $collectRouterErrors($result);

        /*
         * If RouterOS returned a trap, do not run verification as if the
         * operation succeeded.
         */
        if ($routerErrors) {
            $API->disconnect();
            $routerErrors = array_values(array_unique($routerErrors));
            response_json(false,
                'MikroTik menolak pembuatan user PPPoE: ' . implode('; ', $routerErrors),
                ['router_response' => $result],
                400
            );
        }

        /*
         * RouterOS /ppp/secret/add normalnya mengembalikan .id pada
         * response !done. Namun beberapa versi/class hanya mengembalikan
         * array kosong setelah command selesai. Karena itu verifikasi
         * dilakukan dengan PRINT penuh, bukan query ?name.
         */
        $verify = $API->comm('/ppp/secret/print');

        $verifyItem = null;
        foreach ((array)$verify as $row) {
            if (is_array($row) && isset($row['name']) && (string)$row['name'] === $name) {
                $verifyItem = $row;
                break;
            }
        }

        if ($verifyItem === null) {
            $collectRouterErrors($verify);
            $API->disconnect();

            $addDebug = is_scalar($result)
                ? (string)$result
                : json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $verifyDebug = json_encode($verify, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $detail = $routerErrors
                ? ' Detail MikroTik: ' . implode('; ', array_unique($routerErrors))
                : ' ADD_RESPONSE=' . $addDebug . ' | VERIFY_RESPONSE=' . $verifyDebug;

            response_json(false,
                'User PPPoE belum berhasil dibuat.' . $detail,
                ['add_response' => $result, 'verify_response' => $verify],
                400
            );
        }

        $API->disconnect();
        response_json(true, 'User PPPoE berhasil ditambahkan.', [
            'username' => $name,
            'profile' => $profile,
            'id' => $verifyItem['.id'] ?? ''
        ]);
    }


    /*
     * ================================================================
     * EDIT PPPoE SECRET
     * ================================================================
     */
    if ($action === 'edit_secret') {
        $id = $post_id();
        $name = trim((string)($_POST['name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $service = trim((string)($_POST['service'] ?? 'pppoe'));
        $profile = trim((string)($_POST['profile'] ?? 'default'));
        $localAddress = $normalize_optional($_POST['local_address'] ?? '');
        $remoteAddress = $normalize_optional($_POST['remote_address'] ?? '');
        $comment = trim((string)($_POST['comment'] ?? ''));

        if (!preg_match('/^\*?[0-9A-Fa-f]+$/', $id)) {
            $API->disconnect();
            response_json(false, 'ID MikroTik tidak valid.', [], 400);
        }

        if ($name === '') {
            $API->disconnect();
            response_json(false, 'Username wajib diisi.', [], 400);
        }

        if (!in_array($service, ['pppoe', 'any'], true)) {
            $API->disconnect();
            response_json(false, 'Service tidak valid.', [], 400);
        }

        if ($profile === '') {
            $profile = 'default';
        }

        /*
         * Password kosong berarti pertahankan password lama.
         */
        $params = [
            '.id' => $id,
            'name' => $name,
            'service' => $service,
            'profile' => $profile,
            'comment' => $comment
        ];

        if ($localAddress !== '') {
            $params['local-address'] = $localAddress;
        }

        if ($remoteAddress !== '') {
            $params['remote-address'] = $remoteAddress;
        }

        if ($password !== '') {
            $params['password'] = $password;
        }

        $result = $API->comm('/ppp/secret/set', $params);
        $error = $router_error_message($result);

        if ($error !== '') {
            $API->disconnect();
            response_json(false, 'Gagal mengubah user PPPoE: ' . $error, [
                'router_response' => $result
            ], 400);
        }

        $API->disconnect();
        response_json(true, 'User PPPoE berhasil diubah.', [
            'id' => $id,
            'username' => $name,
            'profile' => $profile
        ]);
    }

    /*
     * ================================================================
     * CREATE PPPoE PROFILE
     * ================================================================
     */
    if ($action === 'create_profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $localAddress = $normalize_optional($_POST['local_address'] ?? '');
        $remoteAddress = $normalize_optional($_POST['remote_address'] ?? '');
        $rateLimit = trim((string)($_POST['rate_limit'] ?? ''));
        $onlyOne = trim((string)($_POST['only_one'] ?? ''));
        $comment = trim((string)($_POST['comment'] ?? ''));

        if ($name === '') {
            $API->disconnect();
            response_json(false, 'Nama profile wajib diisi.', [], 400);
        }

        $existing = $API->comm('/ppp/profile/print');
        foreach ((array)$existing as $row) {
            if (is_array($row) && isset($row['name']) && (string)$row['name'] === $name) {
                $API->disconnect();
                response_json(false, 'Profile PPPoE sudah ada.', [], 409);
            }
        }

        $params = ['name' => $name];

        if ($localAddress !== '') $params['local-address'] = $localAddress;
        if ($remoteAddress !== '') $params['remote-address'] = $remoteAddress;
        if ($rateLimit !== '') $params['rate-limit'] = $rateLimit;
        if ($onlyOne !== '') $params['only-one'] = $onlyOne;
        if ($comment !== '') $params['comment'] = $comment;

        $result = $API->comm('/ppp/profile/add', $params);
        $error = $router_error_message($result);

        if ($error !== '') {
            $API->disconnect();
            response_json(false, 'Gagal membuat profile PPPoE: ' . $error, [
                'router_response' => $result
            ], 400);
        }

        $verify = $API->comm('/ppp/profile/print');
        $created = null;

        foreach ((array)$verify as $row) {
            if (is_array($row) && isset($row['name']) && (string)$row['name'] === $name) {
                $created = $row;
                break;
            }
        }

        if ($created === null) {
            $API->disconnect();
            response_json(false, 'Profile belum ditemukan setelah proses add.', [
                'add_response' => $result,
                'verify_response' => $verify
            ], 400);
        }

        $API->disconnect();
        response_json(true, 'Profile PPPoE berhasil dibuat.', [
            'id' => $created['.id'] ?? '',
            'name' => $name
        ]);
    }

    /*
     * ================================================================
     * EDIT PPPoE PROFILE
     * ================================================================
     */
    if ($action === 'edit_profile') {
        $id = $post_id();
        $name = trim((string)($_POST['name'] ?? ''));
        $localAddress = $normalize_optional($_POST['local_address'] ?? '');
        $remoteAddress = $normalize_optional($_POST['remote_address'] ?? '');
        $rateLimit = trim((string)($_POST['rate_limit'] ?? ''));
        $onlyOne = trim((string)($_POST['only_one'] ?? ''));
        $comment = trim((string)($_POST['comment'] ?? ''));

        if (!preg_match('/^\*?[0-9A-Fa-f]+$/', $id)) {
            $API->disconnect();
            response_json(false, 'ID MikroTik tidak valid.', [], 400);
        }

        if ($name === '') {
            $API->disconnect();
            response_json(false, 'Nama profile wajib diisi.', [], 400);
        }

        // Jangan kirim local/remote address kosong atau placeholder
        // karena RouterOS dapat menafsirkannya sebagai nama address-pool.
        $params = [
            '.id' => $id,
            'name' => $name
        ];
        if ($localAddress !== '') $params['local-address'] = $localAddress;
        if ($remoteAddress !== '') $params['remote-address'] = $remoteAddress;
        if ($rateLimit !== '') $params['rate-limit'] = $rateLimit;
        if ($onlyOne !== '') $params['only-one'] = $onlyOne;
        if ($comment !== '') $params['comment'] = $comment;

        $result = $API->comm('/ppp/profile/set', $params);
        $error = $router_error_message($result);

        if ($error !== '') {
            $API->disconnect();
            response_json(false, 'Gagal mengubah profile PPPoE: ' . $error, [
                'router_response' => $result
            ], 400);
        }

        $API->disconnect();
        response_json(true, 'Profile PPPoE berhasil diubah.', [
            'id' => $id,
            'name' => $name
        ]);
    }

    /*
     * ================================================================
     * DELETE PPPoE PROFILE
     * ================================================================
     */
    if ($action === 'remove_profile') {
        $id = $post_id();

        if (!preg_match('/^\*?[0-9A-Fa-f]+$/', $id)) {
            $API->disconnect();
            response_json(false, 'ID MikroTik tidak valid.', [], 400);
        }

        $result = $API->comm('/ppp/profile/remove', ['.id' => $id]);
        $error = $router_error_message($result);

        if ($error !== '') {
            $API->disconnect();
            response_json(false, 'Gagal menghapus profile PPPoE: ' . $error, [
                'router_response' => $result
            ], 400);
        }

        $API->disconnect();
        response_json(true, 'Profile PPPoE berhasil dihapus.', [
            'id' => $id
        ]);
    }

    $id = trim($_POST['id'] ?? $_GET['id'] ?? '');
    if (!preg_match('/^\*?[0-9A-Fa-f]+$/', $id)) {
        $API->disconnect();
        response_json(false, 'ID MikroTik tidak valid.', [], 400);
    }

    if ($action === 'disconnect') {
        $API->comm('/ppp/active/remove', ['.id' => $id]);
        $API->disconnect();
        response_json(true, 'Session PPPoE berhasil diputus.');
    }

    if ($action === 'enable_secret' || $action === 'disable_secret') {
        $API->comm('/ppp/secret/set', [
            '.id' => $id,
            'disabled' => $action === 'disable_secret' ? 'yes' : 'no'
        ]);
        $API->disconnect();
        response_json(true, $action === 'disable_secret'
            ? 'User PPPoE berhasil dinonaktifkan.'
            : 'User PPPoE berhasil diaktifkan.');
    }

    if ($action === 'remove_secret') {
        $API->comm('/ppp/secret/remove', ['.id' => $id]);
        $API->disconnect();
        response_json(true, 'User PPPoE berhasil dihapus.');
    }

    $API->disconnect();
    response_json(false, 'Action tidak dikenal.', [], 400);

} catch (Throwable $e) {
    if (isset($API) && $API instanceof RouterosAPI) {
        @$API->disconnect();
    }
    response_json(false, $e->getMessage(), [], 500);
}
