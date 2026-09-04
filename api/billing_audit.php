<?php
declare(strict_types=1);

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

try {
    $pdo = (new Database())->connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));

    $stmt = $pdo->prepare("
        SELECT
            l.id,
            l.customer_id,
            l.invoice_id,
            l.pppoe_username,
            l.action,
            l.mode,
            l.result,
            l.message,
            l.overdue_days,
            l.grace_days,
            l.created_at,
            c.name AS customer_name,
            i.invoice_no
        FROM billing_suspend_logs l
        LEFT JOIN billing_customers c ON c.id=l.customer_id
        LEFT JOIN billing_invoices i ON i.id=l.invoice_id
        ORDER BY l.id DESC
        LIMIT $limit
    ");
    $stmt->execute();

    out_json(true, '', [
        'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (Throwable $e) {
    out_json(false, $e->getMessage(), [], 500);
}
