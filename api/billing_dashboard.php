<?php
/*
 * NetMonitor - Dashboard Billing API
 * Sprint B5 - Unified Billing <-> PPPoE Dashboard
 *
 * Sumber data:
 *   - billing_customers
 *   - billing_invoices
 *   - MikroTik PPPoE melalui api/pppoe.php
 *
 * Tujuan:
 *   Dashboard Billing dan halaman Billing membaca angka yang konsisten.
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

function out_json(bool $ok, string $message = '', array $data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(
        array_merge(['success' => $ok, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function valid_month(string $value, string $fallback): string {
    return preg_match('/^\d{4}-\d{2}$/', $value) ? $value : $fallback;
}

/* Baca data live PPPoE melalui API yang sama dengan menu PPPoE/Billing. */
function pppoe_api_call(string $action): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL belum aktif.');
    }

    $url = 'http://127.0.0.1/NetworkMonitor/api/pppoe.php?action=' .
        rawurlencode($action) . '&t=' . time();

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $error !== '') {
        throw new RuntimeException('API PPPoE gagal: ' . ($error ?: 'unknown error'));
    }

    $data = json_decode($body, true);

    if (!is_array($data) || !($data['success'] ?? false)) {
        throw new RuntimeException(
            (string)($data['message'] ?? ('Response API PPPoE tidak valid. HTTP ' . $http))
        );
    }

    return $data;
}

try {
    $pdo = (new Database())->connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $currentMonth = date('Y-m');
    $month = valid_month(trim((string)($_GET['month'] ?? '')), $currentMonth);
    $first = $month . '-01';
    $last = date('Y-m-t', strtotime($first));

    /* =========================================================
       INVOICE BULAN TERPILIH
       ========================================================= */
    $summaryStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS invoice_count,
            COALESCE(SUM(amount),0) AS billed,
            COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS paid,
            COALESCE(SUM(CASE WHEN status='unpaid' THEN amount ELSE 0 END),0) AS unpaid,
            COALESCE(SUM(CASE WHEN status='cancelled' THEN amount ELSE 0 END),0) AS cancelled_amount,
            SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN status='unpaid' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count,
            SUM(CASE WHEN status='unpaid' AND due_date >= CURDATE() THEN 1 ELSE 0 END) AS waiting_count
        FROM billing_invoices
        WHERE period BETWEEN ? AND ?
    ");
    $summaryStmt->execute([$first, $last]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    /* =========================================================
       CUSTOMER BILLING
       ========================================================= */
    $customerStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_customers,
            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_customers,
            SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END) AS suspended_customers
        FROM billing_customers
    ");
    $customers = $customerStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    /* =========================================================
       LIVE PPPoE
       ========================================================= */
    $integration = [
        'api_online' => false,
        'accounts' => 0,
        'enabled_accounts' => 0,
        'disabled_accounts' => 0,
        'linked_accounts' => 0,
        'unlinked_accounts' => 0,
        'active_sessions' => 0,
        'unlinked_billing_customers' => 0
    ];

    $pppoeError = '';

    try {
        $secretData = pppoe_api_call('secrets');
        $activeData = pppoe_api_call('active');

        $secrets = is_array($secretData['secrets'] ?? null)
            ? $secretData['secrets'] : [];
        $activeUsers = is_array($activeData['users'] ?? null)
            ? $activeData['users'] : [];

        $billingRows = $pdo->query("
            SELECT pppoe_username
            FROM billing_customers
            WHERE pppoe_username IS NOT NULL
              AND TRIM(pppoe_username) <> ''
        ")->fetchAll(PDO::FETCH_COLUMN);

        $linkedMap = [];
        foreach ($billingRows as $username) {
            $linkedMap[(string)$username] = true;
        }

        $linked = 0;
        $enabled = 0;
        $disabled = 0;

        foreach ($secrets as $secret) {
            $username = (string)($secret['name'] ?? '');
            $isDisabled = (bool)($secret['disabled'] ?? false);

            if ($isDisabled) $disabled++;
            else $enabled++;

            if ($username !== '' && isset($linkedMap[$username])) {
                $linked++;
            }
        }

        $integration['api_online'] = true;
        $integration['accounts'] = count($secrets);
        $integration['enabled_accounts'] = $enabled;
        $integration['disabled_accounts'] = $disabled;
        $integration['linked_accounts'] = $linked;
        $integration['unlinked_accounts'] = max(0, count($secrets) - $linked);
        $integration['active_sessions'] = count($activeUsers);

        $pppoeNames = [];
        foreach ($secrets as $secret) {
            $name = trim((string)($secret['name'] ?? ''));
            if ($name !== '') $pppoeNames[$name] = true;
        }

        $missingBilling = 0;
        foreach ($billingRows as $username) {
            if (!isset($pppoeNames[(string)$username])) $missingBilling++;
        }
        $integration['unlinked_billing_customers'] = $missingBilling;

    } catch (Throwable $e) {
        $pppoeError = $e->getMessage();
    }

    /* =========================================================
       TREND 12 BULAN
       ========================================================= */
    $trendStmt = $pdo->query("
        SELECT
            DATE_FORMAT(period,'%Y-%m') AS month_key,
            COALESCE(SUM(amount),0) AS billed,
            COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS paid,
            COALESCE(SUM(CASE WHEN status='unpaid' THEN amount ELSE 0 END),0) AS unpaid,
            COUNT(*) AS invoice_count
        FROM billing_invoices
        WHERE period >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH),'%Y-%m-01')
          AND period <= DATE_FORMAT(CURDATE(),'%Y-%m-01')
        GROUP BY DATE_FORMAT(period,'%Y-%m')
        ORDER BY month_key ASC
    ");
    $trendRows = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

    $trendMap = [];
    foreach ($trendRows as $r) {
        $trendMap[$r['month_key']] = $r;
    }

    $trend = [];
    $cursor = new DateTime(date('Y-m-01', strtotime('-11 months')));
    $endCursor = new DateTime(date('Y-m-01'));

    while ($cursor <= $endCursor) {
        $key = $cursor->format('Y-m');
        $trend[] = [
            'month' => $key,
            'billed' => (float)($trendMap[$key]['billed'] ?? 0),
            'paid' => (float)($trendMap[$key]['paid'] ?? 0),
            'unpaid' => (float)($trendMap[$key]['unpaid'] ?? 0),
            'invoice_count' => (int)($trendMap[$key]['invoice_count'] ?? 0)
        ];
        $cursor->modify('+1 month');
    }

    /* =========================================================
       STATUS INVOICE
       ========================================================= */
    $statusStmt = $pdo->prepare("
        SELECT
            status,
            COUNT(*) AS total,
            COALESCE(SUM(amount),0) AS amount
        FROM billing_invoices
        WHERE period BETWEEN ? AND ?
        GROUP BY status
        ORDER BY total DESC
    ");
    $statusStmt->execute([$first, $last]);

    /* =========================================================
       PIUTANG OVERDUE
       ========================================================= */
    $arrearsStmt = $pdo->query("
        SELECT
            c.id,
            c.name,
            c.pppoe_username,
            c.status AS customer_status,
            COALESCE(SUM(i.amount),0) AS arrears,
            COUNT(i.id) AS overdue_invoices,
            MAX(i.due_date) AS latest_due_date,
            DATEDIFF(CURDATE(), MIN(i.due_date)) AS oldest_days
        FROM billing_customers c
        INNER JOIN billing_invoices i ON i.customer_id=c.id
        WHERE i.status='unpaid'
          AND i.due_date < CURDATE()
        GROUP BY c.id, c.name, c.pppoe_username, c.status
        ORDER BY arrears DESC, oldest_days DESC
        LIMIT 10
    ");

    /* =========================================================
       JATUH TEMPO 7 HARI
       ========================================================= */
    $upcomingStmt = $pdo->query("
        SELECT
            i.id,
            i.invoice_no,
            i.amount,
            i.due_date,
            c.id AS customer_id,
            c.name AS customer_name,
            c.pppoe_username,
            DATEDIFF(i.due_date, CURDATE()) AS days_left
        FROM billing_invoices i
        INNER JOIN billing_customers c ON c.id=i.customer_id
        WHERE i.status='unpaid'
          AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY i.due_date ASC, i.id ASC
        LIMIT 10
    ");

    /* =========================================================
       RESPONSE
       ========================================================= */
    out_json(true, '', [
        'month' => $month,

        'summary' => [
            'invoice_count' => (int)($summary['invoice_count'] ?? 0),
            'billed' => (float)($summary['billed'] ?? 0),
            'paid' => (float)($summary['paid'] ?? 0),
            'unpaid' => (float)($summary['unpaid'] ?? 0),
            'cancelled_amount' => (float)($summary['cancelled_amount'] ?? 0),
            'paid_count' => (int)($summary['paid_count'] ?? 0),
            'overdue_count' => (int)($summary['overdue_count'] ?? 0),
            'waiting_count' => (int)($summary['waiting_count'] ?? 0)
        ],

        'customers' => [
            'total' => (int)($customers['total_customers'] ?? 0),
            'active' => (int)($customers['active_customers'] ?? 0),
            'suspended' => (int)($customers['suspended_customers'] ?? 0)
        ],

        'integration' => $integration,
        'pppoe_error' => $pppoeError,

        'trend' => $trend,
        'statuses' => $statusStmt->fetchAll(PDO::FETCH_ASSOC),
        'arrears' => $arrearsStmt->fetchAll(PDO::FETCH_ASSOC),
        'upcoming' => $upcomingStmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {
    out_json(false, $e->getMessage(), [], 500);
}
