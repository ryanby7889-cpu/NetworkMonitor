<?php
/*
 * NetMonitor - Billing Report API
 * Lokasi: /NetworkMonitor/api/report.php
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

function out_json(bool $ok, string $message = '', array $data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => $ok,
        'message' => $message
    ], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function valid_date(string $value, string $fallback): string {
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : $fallback;
}

try {
    $pdo = (new Database())->connect();

    $startDefault = date('Y-m-01');
    $endDefault = date('Y-m-t');

    $start = valid_date(trim((string)($_GET['start'] ?? '')), $startDefault);
    $end   = valid_date(trim((string)($_GET['end'] ?? '')), $endDefault);

    if ($start > $end) {
        out_json(false, 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.', [], 422);
    }

    $action = $_GET['action'] ?? 'summary';

    if ($action === 'summary') {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_invoices,
                COALESCE(SUM(amount),0) AS total_billed,
                COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS total_paid,
                COALESCE(SUM(CASE WHEN status='unpaid' THEN amount ELSE 0 END),0) AS total_unpaid,
                COALESCE(SUM(CASE WHEN status='cancelled' THEN amount ELSE 0 END),0) AS total_cancelled,
                SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN status='unpaid' AND due_date >= CURDATE() THEN 1 ELSE 0 END) AS unpaid_count,
                SUM(CASE WHEN status='unpaid' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_count
            FROM billing_invoices
            WHERE period BETWEEN ? AND ?
        ");
        $stmt->execute([$start, $end]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $customerStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT customer_id)
            FROM billing_invoices
            WHERE period BETWEEN ? AND ?
        ");
        $customerStmt->execute([$start, $end]);

        $summary['customer_count'] = (int)$customerStmt->fetchColumn();

        foreach ([
            'total_invoices','paid_count','unpaid_count',
            'overdue_count','cancelled_count','customer_count'
        ] as $k) {
            $summary[$k] = (int)($summary[$k] ?? 0);
        }

        foreach ([
            'total_billed','total_paid','total_unpaid','total_cancelled'
        ] as $k) {
            $summary[$k] = (float)($summary[$k] ?? 0);
        }

        $methodStmt = $pdo->prepare("
            SELECT
                COALESCE(NULLIF(payment_method,''),'Tidak dicatat') AS payment_method,
                COUNT(*) AS total,
                COALESCE(SUM(amount),0) AS amount
            FROM billing_invoices
            WHERE status='paid'
              AND period BETWEEN ? AND ?
            GROUP BY COALESCE(NULLIF(payment_method,''),'Tidak dicatat')
            ORDER BY amount DESC
        ");
        $methodStmt->execute([$start, $end]);

        out_json(true, '', [
            'start' => $start,
            'end' => $end,
            'summary' => $summary,
            'methods' => $methodStmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($action === 'invoices') {
        $q = trim((string)($_GET['q'] ?? ''));

        $sql = "
            SELECT
                i.id,
                i.invoice_no,
                i.period,
                i.amount,
                i.due_date,
                i.status,
                i.paid_at,
                i.payment_method,
                c.name AS customer_name,
                c.pppoe_username,
                c.package_name,
                CASE
                    WHEN i.status='paid' THEN 'paid'
                    WHEN i.status='cancelled' THEN 'cancelled'
                    WHEN i.due_date < CURDATE() THEN 'overdue'
                    ELSE 'unpaid'
                END AS display_status
            FROM billing_invoices i
            INNER JOIN billing_customers c ON c.id=i.customer_id
            WHERE i.period BETWEEN :start AND :end
        ";

        $params = [
            ':start' => $start,
            ':end' => $end
        ];

        if ($q !== '') {
            $sql .= "
                AND (
                    i.invoice_no LIKE :q
                    OR c.name LIKE :q
                    OR c.pppoe_username LIKE :q
                    OR c.package_name LIKE :q
                )
            ";
            $params[':q'] = '%' . $q . '%';
        }

        $sql .= " ORDER BY i.period DESC, i.due_date DESC, i.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        out_json(true, '', [
            'start' => $start,
            'end' => $end,
            'invoices' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($action === 'customers') {
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.name,
                c.pppoe_username,
                c.package_name,
                COUNT(i.id) AS invoice_count,
                COALESCE(SUM(i.amount),0) AS billed,
                COALESCE(SUM(CASE WHEN i.status='paid' THEN i.amount ELSE 0 END),0) AS paid,
                COALESCE(SUM(CASE WHEN i.status='unpaid' THEN i.amount ELSE 0 END),0) AS unpaid
            FROM billing_customers c
            INNER JOIN billing_invoices i ON i.customer_id=c.id
            WHERE i.period BETWEEN ? AND ?
            GROUP BY c.id, c.name, c.pppoe_username, c.package_name
            ORDER BY c.name ASC
        ");
        $stmt->execute([$start, $end]);

        out_json(true, '', [
            'start' => $start,
            'end' => $end,
            'customers' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    out_json(false, 'Action tidak dikenal.', [], 404);

} catch (Throwable $e) {
    out_json(false, $e->getMessage(), [], 500);
}
