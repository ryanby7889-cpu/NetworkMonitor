<?php
declare(strict_types=1);

/*
 * NetMonitor - Sprint 3.2
 * Auto Suspend Billing -> MikroTik PPPoE
 *
 * Lokasi:
 * /NetworkMonitor/api/billing_suspend.php
 *
 * Fungsi:
 * 1. preview  : mencari pelanggan yang memenuhi syarat suspend.
 * 2. process  : disable PPPoE di MikroTik + update billing status.
 *
 * Aturan:
 * - customer harus masih ACTIVE.
 * - invoice harus UNPAID.
 * - due_date sudah lewat.
 * - DATEDIFF(CURDATE(), due_date) >= grace_days.
 * - Username PPPoE harus ditemukan di MikroTik.
 * - MikroTik di-disable TERLEBIH DAHULU.
 * - Billing baru menjadi suspended setelah MikroTik berhasil.
 * - Session aktif username tersebut juga diputus.
 *
 * Endpoint:
 * GET  ?action=preview&grace_days=3
 * POST ?action=process&grace_days=3
 * POST ?action=process&customer_id=4&grace_days=3
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

function out_json(
    bool $ok,
    string $message = '',
    array $data = [],
    int $code = 200
): void {
    http_response_code($code);

    echo json_encode(
        array_merge([
            'success' => $ok,
            'message' => $message
        ], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * Memanggil API PPPoE yang sudah dipakai Sprint 3.1.
 */
function pppoe_call(string $action, array $post = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL belum aktif. Aktifkan extension=curl pada php.ini XAMPP.'
        );
    }

    $url =
        'http://127.0.0.1/NetworkMonitor/api/pppoe.php?action=' .
        rawurlencode($action) .
        '&t=' . time();

    $ch = curl_init($url);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ],
    ];

    if ($post) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($post);
    }

    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($body === false || $error !== '') {
        throw new RuntimeException(
            'Koneksi API PPPoE gagal: ' . $error
        );
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
        throw new RuntimeException(
            'Response API PPPoE tidak valid. HTTP ' . $http
        );
    }

    if (!(bool)($data['success'] ?? false)) {
        throw new RuntimeException(
            (string)($data['message'] ?? 'API PPPoE menolak permintaan.')
        );
    }

    return $data;
}

/**
 * Ambil semua secret PPPoE MikroTik.
 */
function find_pppoe_secret(array $secrets, string $username): ?array
{
    foreach ($secrets as $secret) {
        if ((string)($secret['name'] ?? '') === $username) {
            return $secret;
        }
    }

    return null;
}

/**
 * Putus session aktif berdasarkan username.
 *
 * Tidak gagal total jika session tidak ada.
 */
function disconnect_user_sessions(
    array $activeUsers,
    string $username
): int {
    $disconnected = 0;

    foreach ($activeUsers as $user) {
        if ((string)($user['name'] ?? '') !== $username) {
            continue;
        }

        $id = (string)($user['id'] ?? '');

        if ($id === '') {
            continue;
        }

        try {
            pppoe_call('disconnect', ['id' => $id]);
            $disconnected++;
        } catch (Throwable $ignore) {
            // Disable secret tetap dianggap sukses.
        }
    }

    return $disconnected;
}

/**
 * Audit log dibuat hanya jika tabel billing_suspend_logs tersedia.
 *
 * Ini sengaja dibuat toleran agar proses suspend tidak gagal
 * hanya karena tabel audit belum tersedia.
 */
function write_audit(
    PDO $pdo,
    int $customerId,
    int $invoiceId,
    string $username,
    string $action,
    string $mode,
    string $result,
    string $message,
    ?int $overdueDays,
    int $graceDays
): void {
    try {
        $check = $pdo->query("
            SHOW TABLES LIKE 'billing_suspend_logs'
        ");

        if (!$check || !$check->fetchColumn()) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO billing_suspend_logs
            (
                customer_id,
                invoice_id,
                pppoe_username,
                action,
                mode,
                result,
                message,
                overdue_days,
                grace_days
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $customerId,
            $invoiceId,
            $username,
            $action,
            $mode,
            $result,
            mb_substr($message, 0, 500),
            $overdueDays,
            $graceDays
        ]);
    } catch (Throwable $ignore) {
        // Audit bukan alasan proses utama gagal.
    }
}

try {
    $pdo = (new Database())->connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = strtolower(
        trim((string)($_GET['action'] ?? 'preview'))
    );

    if (!in_array($action, ['preview', 'process'], true)) {
        out_json(
            false,
            'Action tidak dikenal. Gunakan preview atau process.',
            [],
            400
        );
    }

    $graceDays = (int)(
        $_POST['grace_days']
        ?? $_GET['grace_days']
        ?? 3
    );

    $graceDays = max(0, min(30, $graceDays));

    /*
     * Untuk testing satu pelanggan:
     * ?customer_id=4
     */
    $customerId = (int)(
        $_POST['customer_id']
        ?? $_GET['customer_id']
        ?? 0
    );

    /*
     * Ambil kandidat suspend.
     *
     * MAX overdue invoice dipakai agar satu customer
     * hanya diproses satu kali.
     */
    $sql = "
        SELECT
            c.id AS customer_id,
            c.name AS customer_name,
            c.pppoe_username,
            i.id AS invoice_id,
            i.invoice_no,
            i.period,
            i.amount,
            i.due_date,
            DATEDIFF(CURDATE(), i.due_date) AS overdue_days
        FROM billing_customers c
        INNER JOIN billing_invoices i
            ON i.customer_id = c.id
        INNER JOIN (
            SELECT
                customer_id,
                MAX(due_date) AS latest_due_date
            FROM billing_invoices
            WHERE status = 'unpaid'
              AND due_date < CURDATE()
            GROUP BY customer_id
        ) latest
            ON latest.customer_id = i.customer_id
           AND latest.latest_due_date = i.due_date
        WHERE c.status = 'active'
          AND i.status = 'unpaid'
          AND i.due_date < CURDATE()
          AND DATEDIFF(CURDATE(), i.due_date) >= :grace
    ";

    $params = [
        ':grace' => $graceDays
    ];

    if ($customerId > 0) {
        $sql .= " AND c.id = :customer_id ";
        $params[':customer_id'] = $customerId;
    }

    $sql .= "
        ORDER BY
            i.due_date ASC,
            c.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * =========================================================
     * PREVIEW
     * =========================================================
     */
    if ($action === 'preview') {
        out_json(true, 'Pemeriksaan kandidat suspend selesai.', [
            'mode' => 'preview',
            'grace_days' => $graceDays,
            'count' => count($candidates),
            'customers' => $candidates
        ]);
    }

    /*
     * =========================================================
     * PROCESS
     * =========================================================
     */

    if (!$candidates) {
        out_json(
            true,
            'Tidak ada pelanggan yang memenuhi batas suspend.',
            [
                'mode' => 'live',
                'grace_days' => $graceDays,
                'processed' => 0,
                'suspended' => 0,
                'failed' => 0,
                'items' => []
            ]
        );
    }

    /*
     * Ambil secret MikroTik SATU KALI.
     */
    $secretResponse = pppoe_call('secrets');
    $secrets = $secretResponse['secrets'] ?? [];

    /*
     * Ambil active session SATU KALI.
     */
    $activeResponse = [];
    try {
        $activeResponse = pppoe_call('active');
    } catch (Throwable $ignore) {
        // Tidak ada session / API active gagal.
    }

    $activeUsers = $activeResponse['users'] ?? [];

    $suspended = 0;
    $failed = 0;
    $alreadyDisabled = 0;
    $disconnected = 0;
    $items = [];

    foreach ($candidates as $row) {
        $cid = (int)$row['customer_id'];
        $invoiceId = (int)$row['invoice_id'];
        $username = trim((string)$row['pppoe_username']);
        $overdueDays = (int)$row['overdue_days'];

        if ($username === '') {
            $failed++;

            write_audit(
                $pdo,
                $cid,
                $invoiceId,
                '',
                'suspend',
                'live',
                'error',
                'Username PPPoE kosong.',
                $overdueDays,
                $graceDays
            );

            $items[] = [
                'customer_id' => $cid,
                'invoice_id' => $invoiceId,
                'result' => 'error',
                'message' => 'Username PPPoE kosong.'
            ];

            continue;
        }

        try {
            /*
             * Re-check status database sebelum melakukan perubahan.
             * Ini mencegah suspend jika status sudah berubah
             * karena proses lain.
             */
            $check = $pdo->prepare("
                SELECT status
                FROM billing_customers
                WHERE id = ?
                LIMIT 1
            ");
            $check->execute([$cid]);

            $currentStatus = $check->fetchColumn();

            if ($currentStatus !== 'active') {
                $items[] = [
                    'customer_id' => $cid,
                    'invoice_id' => $invoiceId,
                    'username' => $username,
                    'result' => 'skipped',
                    'message' => 'Status pelanggan sudah bukan active.'
                ];

                continue;
            }

            /*
             * Cari secret MikroTik.
             */
            $secret = find_pppoe_secret($secrets, $username);

            if (!$secret || empty($secret['id'])) {
                $failed++;

                write_audit(
                    $pdo,
                    $cid,
                    $invoiceId,
                    $username,
                    'suspend',
                    'live',
                    'error',
                    'Username PPPoE tidak ditemukan di MikroTik.',
                    $overdueDays,
                    $graceDays
                );

                $items[] = [
                    'customer_id' => $cid,
                    'invoice_id' => $invoiceId,
                    'username' => $username,
                    'result' => 'error',
                    'message' => 'Username PPPoE tidak ditemukan di MikroTik.'
                ];

                continue;
            }

            /*
             * =====================================================
             * 1. DISABLE MIKROTIK
             * =====================================================
             */
            if (!empty($secret['disabled'])) {
                $alreadyDisabled++;
            } else {
                pppoe_call(
                    'disable_secret',
                    ['id' => $secret['id']]
                );
            }

            /*
             * =====================================================
             * 2. PUTUS SESSION AKTIF
             * =====================================================
             */
            $countDisconnected = disconnect_user_sessions(
                $activeUsers,
                $username
            );

            $disconnected += $countDisconnected;

            /*
             * =====================================================
             * 3. UPDATE BILLING
             * =====================================================
             *
             * Hanya dilakukan setelah MikroTik berhasil.
             */
            $update = $pdo->prepare("
                UPDATE billing_customers
                SET status = 'suspended'
                WHERE id = ?
                  AND status = 'active'
            ");

            $update->execute([$cid]);

            if ($update->rowCount() === 0) {
                $items[] = [
                    'customer_id' => $cid,
                    'invoice_id' => $invoiceId,
                    'username' => $username,
                    'result' => 'skipped',
                    'message' => 'Status billing sudah berubah.',
                    'disconnected' => $countDisconnected
                ];

                continue;
            }

            $suspended++;

            $message =
                'Pelanggan otomatis ditangguhkan. ' .
                'Tagihan ' . $row['invoice_no'] .
                ' terlambat ' . $overdueDays .
                ' hari.';

            write_audit(
                $pdo,
                $cid,
                $invoiceId,
                $username,
                'suspend',
                'live',
                'success',
                $message,
                $overdueDays,
                $graceDays
            );

            $items[] = [
                'customer_id' => $cid,
                'customer_name' => $row['customer_name'],
                'invoice_id' => $invoiceId,
                'invoice_no' => $row['invoice_no'],
                'username' => $username,
                'due_date' => $row['due_date'],
                'overdue_days' => $overdueDays,
                'result' => 'suspended',
                'mikrotik' => !empty($secret['disabled'])
                    ? 'already_disabled'
                    : 'disabled',
                'disconnected' => $countDisconnected
            ];

        } catch (Throwable $e) {
            $failed++;

            $errorMessage = $e->getMessage();

            write_audit(
                $pdo,
                $cid,
                $invoiceId,
                $username,
                'suspend',
                'live',
                'error',
                $errorMessage,
                $overdueDays,
                $graceDays
            );

            $items[] = [
                'customer_id' => $cid,
                'invoice_id' => $invoiceId,
                'invoice_no' => $row['invoice_no'],
                'username' => $username,
                'result' => 'error',
                'message' => $errorMessage
            ];
        }
    }

    $processed = count($candidates);

    $message =
        "Proses suspend selesai. " .
        "Diproses: {$processed}, " .
        "berhasil suspend: {$suspended}, " .
        "gagal: {$failed}.";

    out_json(true, $message, [
        'mode' => 'live',
        'grace_days' => $graceDays,
        'processed' => $processed,
        'suspended' => $suspended,
        'failed' => $failed,
        'already_disabled' => $alreadyDisabled,
        'disconnected' => $disconnected,
        'items' => $items
    ]);

} catch (Throwable $e) {
    out_json(false, $e->getMessage(), [], 500);
}
?>
