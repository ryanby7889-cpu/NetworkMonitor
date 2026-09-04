<?php
declare(strict_types=1);

/**
 * NetMonitor - Sprint 2.7
 * Automatic invoice status updater.
 *
 * Database: network_monitor
 * Table: billing_invoices
 *
 * Rules:
 * - unpaid + due_date < today => overdue
 * - paid/cancelled are never changed
 * - overdue remains overdue
 * - script is safe to run repeatedly
 */

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';

function log_line(string $message): void
{
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $line;
    @file_put_contents(
        $dir . '/invoice-status-' . date('Y-m') . '.log',
        $line,
        FILE_APPEND
    );
}

try {
    $db = new Database();
    $pdo = $db->connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $today = date('Y-m-d');

    log_line('=== Sprint 2.7 Invoice Status Updater ===');
    log_line('Tanggal pemeriksaan: ' . $today);

    // Only unpaid invoices that have passed their due date.
    $select = $pdo->prepare("
        SELECT id, invoice_no, customer_id, due_date, status
        FROM billing_invoices
        WHERE status = 'unpaid'
          AND due_date IS NOT NULL
          AND due_date < ?
        ORDER BY due_date ASC, id ASC
    ");
    $select->execute([$today]);
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        log_line('Tidak ada invoice unpaid yang melewati jatuh tempo.');
        log_line('Selesai.');
        exit(0);
    }

    $update = $pdo->prepare("
        UPDATE billing_invoices
        SET status = 'overdue'
        WHERE id = ?
          AND status = 'unpaid'
    ");

    $changed = 0;

    foreach ($rows as $row) {
        $update->execute([(int)$row['id']]);

        if ($update->rowCount() > 0) {
            $changed++;
            $days = max(
                0,
                (int)((strtotime($today) - strtotime($row['due_date'])) / 86400)
            );

            log_line(
                "OVERDUE {$row['invoice_no']} | customer_id={$row['customer_id']} | " .
                "due={$row['due_date']} | terlambat={$days} hari"
            );
        }
    }

    log_line("Total ditemukan: " . count($rows));
    log_line("Total berubah menjadi overdue: {$changed}");
    log_line('Selesai.');
    exit(0);

} catch (Throwable $e) {
    log_line('FATAL ERROR: ' . $e->getMessage());
    exit(1);
}
