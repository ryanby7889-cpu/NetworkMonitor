<?php
declare(strict_types=1);

/*
 * NetMonitor Sprint 3.1
 * Re-activate PPPoE after a paid invoice.
 *
 * IMPORTANT:
 * This endpoint does NOT mark an invoice paid.
 * Payment is still performed by api/billing.php.
 *
 * Flow:
 * 1. Find paid invoice.
 * 2. Find customer.
 * 3. Only suspended customer is eligible.
 * 4. Find PPPoE secret by username.
 * 5. Enable secret on MikroTik.
 * 6. Only after success, set customer status = active.
 * 7. Write audit log when the table exists.
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

function out_json(bool $ok, string $message = '', array $data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(
        array_merge(['success' => $ok, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function pppoe_call(string $action, array $post = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL belum aktif. Aktifkan extension=curl pada php.ini XAMPP.'
        );
    }

    $url = 'http://127.0.0.1/NetworkMonitor/api/pppoe.php?action=' .
        rawurlencode($action) . '&t=' . time();

    $ch = curl_init($url);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];

    if ($post) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($post);
    }

    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $error !== '') {
        throw new RuntimeException('Koneksi API PPPoE gagal: ' . $error);
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
        throw new RuntimeException(
            'Response API PPPoE tidak valid (HTTP ' . $http . ').'
        );
    }

    if (!(bool)($data['success'] ?? false)) {
        throw new RuntimeException(
            $data['message'] ?? 'API PPPoE menolak permintaan.'
        );
    }

    return $data;
}

function write_audit(
    PDO $pdo,
    int $customerId,
    int $invoiceId,
    string $username,
    string $result,
    string $message
): void {
    // Audit table is created by Sprint 2.9.
    $stmt = $pdo->prepare("
        INSERT INTO billing_suspend_logs
        (customer_id, invoice_id, pppoe_username, action, mode, result, message,
         overdue_days, grace_days)
        VALUES (?, ?, ?, 'activate', 'live', ?, ?, NULL, 0)
    ");

    $stmt->execute([
        $customerId,
        $invoiceId,
        $username,
        $result,
        mb_substr($message, 0, 500)
    ]);
}

try {
    $pdo = (new Database())->connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $invoiceId = (int)($_POST['invoice_id'] ?? $_GET['invoice_id'] ?? 0);

    if ($invoiceId <= 0) {
        out_json(false, 'ID invoice tidak valid.', [], 400);
    }

    /*
     * Do not activate based only on an invoice ID supplied by the browser.
     * Re-check status directly from database.
     */
    $stmt = $pdo->prepare("
        SELECT
            i.id AS invoice_id,
            i.invoice_no,
            i.status AS invoice_status,
            i.amount,
            i.paid_at,
            c.id AS customer_id,
            c.name AS customer_name,
            c.pppoe_username,
            c.status AS customer_status
        FROM billing_invoices i
        INNER JOIN billing_customers c ON c.id = i.customer_id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$invoiceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        out_json(false, 'Invoice tidak ditemukan.', [], 404);
    }

    if ($row['invoice_status'] !== 'paid') {
        out_json(false, 'Invoice belum berstatus paid. Reaktivasi dibatalkan.', [
            'invoice_no' => $row['invoice_no'],
            'status' => $row['invoice_status']
        ], 409);
    }

    /*
     * If already active, nothing should be changed on MikroTik.
     */
    if ($row['customer_status'] === 'active') {
        try {
            write_audit(
                $pdo,
                (int)$row['customer_id'],
                (int)$row['invoice_id'],
                (string)$row['pppoe_username'],
                'already',
                'Invoice sudah lunas dan pelanggan sudah active.'
            );
        } catch (Throwable $ignore) {}

        out_json(true, 'Pelanggan sudah active. Tidak ada perubahan.', [
            'result' => 'already_active',
            'customer_id' => (int)$row['customer_id'],
            'username' => $row['pppoe_username']
        ]);
    }

    if ($row['customer_status'] !== 'suspended') {
        out_json(false, 'Status pelanggan bukan suspended. Reaktivasi otomatis dibatalkan.', [
            'customer_status' => $row['customer_status']
        ], 409);
    }

    $username = (string)$row['pppoe_username'];

    /*
     * Locate MikroTik secret by PPPoE username.
     */
    $secretData = pppoe_call('secrets');
    $secret = null;

    foreach (($secretData['secrets'] ?? []) as $item) {
        if ((string)($item['name'] ?? '') === $username) {
            $secret = $item;
            break;
        }
    }

    if (!$secret || empty($secret['id'])) {
        try {
            write_audit(
                $pdo,
                (int)$row['customer_id'],
                (int)$row['invoice_id'],
                $username,
                'error',
                'Username PPPoE tidak ditemukan di MikroTik.'
            );
        } catch (Throwable $ignore) {}

        out_json(false, 'Username PPPoE tidak ditemukan di MikroTik.', [
            'username' => $username
        ], 404);
    }

    /*
     * Already enabled on MikroTik: only synchronize Billing.
     */
    if (empty($secret['disabled'])) {
        $pdo->prepare("
            UPDATE billing_customers
            SET status = 'active'
            WHERE id = ? AND status = 'suspended'
        ")->execute([(int)$row['customer_id']]);

        try {
            write_audit(
                $pdo,
                (int)$row['customer_id'],
                (int)$row['invoice_id'],
                $username,
                'already',
                'Secret MikroTik sudah enabled; status Billing disinkronkan menjadi active.'
            );
        } catch (Throwable $ignore) {}

        out_json(true, 'PPPoE sudah aktif. Status Billing disinkronkan.', [
            'result' => 'already_enabled',
            'customer_id' => (int)$row['customer_id'],
            'username' => $username
        ]);
    }

    /*
     * Enable MikroTik first. Billing changes only after success.
     */
    pppoe_call('enable_secret', ['id' => $secret['id']]);

    $pdo->prepare("
        UPDATE billing_customers
        SET status = 'active'
        WHERE id = ? AND status = 'suspended'
    ")->execute([(int)$row['customer_id']]);

    try {
        write_audit(
            $pdo,
            (int)$row['customer_id'],
            (int)$row['invoice_id'],
            $username,
            'success',
            'Pembayaran lunas; PPPoE diaktifkan dan customer menjadi active.'
        );
    } catch (Throwable $ignore) {}

    out_json(true, 'Pelanggan berhasil diaktifkan kembali.', [
        'result' => 'activated',
        'customer_id' => (int)$row['customer_id'],
        'invoice_id' => (int)$row['invoice_id'],
        'invoice_no' => $row['invoice_no'],
        'username' => $username
    ]);

} catch (Throwable $e) {
    out_json(false, $e->getMessage(), [], 500);
}
