<?php
/*
 * NetMonitor - WhatsApp Billing API
 * Lokasi: /NetworkMonitor/api/whatsapp.php
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

function normalize_phone(string $phone): string {
    $phone = preg_replace('/\D+/', '', $phone) ?? '';
    if ($phone === '') return '';
    if (str_starts_with($phone, '0')) return '62' . substr($phone, 1);
    if (str_starts_with($phone, '8')) return '62' . $phone;
    return $phone;
}

function render_template(string $tpl, array $v): string {
    foreach ($v as $k => $value) {
        $tpl = str_replace('{' . $k . '}', (string)$value, $tpl);
    }
    return $tpl;
}

function invoice_data(PDO $pdo, int $invoiceId): array {
    $stmt = $pdo->prepare("
        SELECT i.*, c.id AS customer_id, c.name AS customer_name, c.phone,
               c.package_name, c.pppoe_username
        FROM billing_invoices i
        INNER JOIN billing_customers c ON c.id=i.customer_id
        WHERE i.id=?
        LIMIT 1
    ");
    $stmt->execute([$invoiceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) out_json(false, 'Invoice tidak ditemukan.', [], 404);
    return $row;
}

try {
    $pdo = (new Database())->connect();
    $action = $_GET['action'] ?? 'templates';

    if ($action === 'templates') {
        $rows = $pdo->query("
            SELECT id,event_key,template_name,message_template,enabled
            FROM billing_whatsapp_templates
            ORDER BY id
        ")->fetchAll(PDO::FETCH_ASSOC);
        out_json(true, '', ['templates' => $rows]);
    }

    if ($action === 'save_template') {
        $event = trim((string)($_POST['event_key'] ?? ''));
        $name = trim((string)($_POST['template_name'] ?? ''));
        $template = trim((string)($_POST['message_template'] ?? ''));
        $enabled = (int)($_POST['enabled'] ?? 1) ? 1 : 0;

        if ($event === '' || $name === '' || $template === '') {
            out_json(false, 'Event, nama template dan pesan wajib diisi.', [], 422);
        }

        $stmt = $pdo->prepare("
            INSERT INTO billing_whatsapp_templates
            (event_key,template_name,message_template,enabled)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE
              template_name=VALUES(template_name),
              message_template=VALUES(message_template),
              enabled=VALUES(enabled)
        ");
        $stmt->execute([$event,$name,$template,$enabled]);
        out_json(true, 'Template WhatsApp disimpan.');
    }

    if ($action === 'preview') {
        $invoiceId = (int)($_GET['invoice_id'] ?? 0);
        $event = trim((string)($_GET['event_key'] ?? 'invoice'));
        if ($invoiceId <= 0) out_json(false, 'Invoice tidak valid.', [], 422);

        $x = invoice_data($pdo, $invoiceId);

        $tplStmt = $pdo->prepare("
            SELECT message_template FROM billing_whatsapp_templates
            WHERE event_key=? AND enabled=1 LIMIT 1
        ");
        $tplStmt->execute([$event]);
        $tpl = $tplStmt->fetchColumn();
        if ($tpl === false) out_json(false, 'Template event tidak ditemukan.', [], 404);

        $overdueDays = 0;
        if (!empty($x['due_date'])) {
            $overdueDays = max(0, (int)$pdo->query(
                "SELECT DATEDIFF(CURDATE(), " . $pdo->quote($x['due_date']) . ")"
            )->fetchColumn());
        }

        $message = render_template($tpl, [
            'name' => $x['customer_name'],
            'invoice_no' => $x['invoice_no'],
            'period' => $x['period'],
            'package' => $x['package_name'],
            'amount' => 'Rp ' . number_format((float)$x['amount'], 0, ',', '.'),
            'due_date' => $x['due_date'],
            'overdue_days' => $overdueDays,
            'paid_at' => $x['paid_at'] ?? '-',
            'payment_method' => $x['payment_method'] ?? '-'
        ]);

        $phone = normalize_phone((string)$x['phone']);
        out_json(true, '', [
            'phone' => $phone,
            'message' => $message,
            'url' => $phone !== ''
                ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($message)
                : ''
        ]);
    }

    if ($action === 'prepare') {
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $event = trim((string)($_POST['event_key'] ?? 'invoice'));
        if ($invoiceId <= 0) out_json(false, 'Invoice tidak valid.', [], 422);

        $x = invoice_data($pdo, $invoiceId);

        $tplStmt = $pdo->prepare("
            SELECT message_template FROM billing_whatsapp_templates
            WHERE event_key=? AND enabled=1 LIMIT 1
        ");
        $tplStmt->execute([$event]);
        $tpl = $tplStmt->fetchColumn();
        if ($tpl === false) out_json(false, 'Template event tidak ditemukan.', [], 404);

        $overdueDays = max(0, (int)$pdo->query(
            "SELECT DATEDIFF(CURDATE(), " . $pdo->quote($x['due_date']) . ")"
        )->fetchColumn());

        $message = render_template($tpl, [
            'name' => $x['customer_name'],
            'invoice_no' => $x['invoice_no'],
            'period' => $x['period'],
            'package' => $x['package_name'],
            'amount' => 'Rp ' . number_format((float)$x['amount'], 0, ',', '.'),
            'due_date' => $x['due_date'],
            'overdue_days' => $overdueDays,
            'paid_at' => $x['paid_at'] ?? '-',
            'payment_method' => $x['payment_method'] ?? '-'
        ]);

        $phone = normalize_phone((string)$x['phone']);
        if ($phone === '') out_json(false, 'Nomor WhatsApp pelanggan belum diisi.', [], 422);

        $stmt = $pdo->prepare("
            INSERT INTO billing_whatsapp_logs
            (invoice_id,customer_id,event_key,phone,message,status,provider)
            VALUES (?,?,?,?,?,'prepared','wa.me')
        ");
        $stmt->execute([
            $invoiceId,
            (int)$x['customer_id'],
            $event,
            $phone,
            $message
        ]);

        $logId = (int)$pdo->lastInsertId();

        out_json(true, 'Pesan WhatsApp siap dikirim.', [
            'log_id' => $logId,
            'phone' => $phone,
            'message' => $message,
            'url' => 'https://wa.me/' . $phone . '?text=' . rawurlencode($message)
        ]);
    }

    if ($action === 'logs') {
        $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
        $stmt = $pdo->query("
            SELECT l.*, c.name AS customer_name, i.invoice_no
            FROM billing_whatsapp_logs l
            LEFT JOIN billing_customers c ON c.id=l.customer_id
            LEFT JOIN billing_invoices i ON i.id=l.invoice_id
            ORDER BY l.id DESC
            LIMIT $limit
        ");
        out_json(true, '', ['logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    out_json(false, 'Action tidak dikenal.', [], 404);

} catch (Throwable $e) {
    out_json(false, $e->getMessage(), [], 500);
}
