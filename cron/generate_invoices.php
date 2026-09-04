<?php
/*
 * NetMonitor - Sprint 2.6
 * Automatic Monthly Invoice Generator
 *
 * Jalankan:
 *   php cron/generate_invoices.php
 *
 * Atau:
 *   php cron/generate_invoices.php 2026-09
 *
 * Database:
 *   billing_customers
 *   billing_invoices
 *
 * Sifat:
 * - Hanya pelanggan ACTIVE
 * - Satu invoice per customer per periode
 * - Aman dijalankan berulang kali
 * - Mengandalkan UNIQUE(customer_id, period) sebagai proteksi terakhir
 */

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';

function log_line(string $text): void
{
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $text . PHP_EOL;
    echo $line;
    @file_put_contents($dir . '/invoice-generator-' . date('Y-m') . '.log', $line, FILE_APPEND);
}

function valid_period(string $value): string
{
    $value = trim($value);

    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        $value .= '-01';
    }

    $d = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$d ||
        ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) ||
        $d->format('Y-m-d') !== $value
    ) {
        throw new RuntimeException('Periode tidak valid. Gunakan YYYY-MM atau YYYY-MM-DD.');
    }

    return $d->format('Y-m-01');
}

function due_date_for_period(string $period, int $billingDay): string
{
    $d = new DateTime($period);
    $lastDay = (int)$d->format('t');

    // Billing day pada aplikasi dibatasi 1-28.
    $day = max(1, min(28, $billingDay));
    $day = min($day, $lastDay);

    return $d->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
}

function make_invoice_no(PDO $pdo, string $period, int $customerId): string
{
    $base = 'INV-' . date('Ym', strtotime($period)) . '-' .
        str_pad((string)$customerId, 5, '0', STR_PAD_LEFT);

    $no = $base;
    $n = 2;

    $stmt = $pdo->prepare(
        'SELECT id FROM billing_invoices WHERE invoice_no=? LIMIT 1'
    );

    while (true) {
        $stmt->execute([$no]);

        if (!$stmt->fetchColumn()) {
            return $no;
        }

        $no = $base . '-' . $n++;
    }
}

function is_duplicate_exception(Throwable $e): bool
{
    return $e instanceof PDOException
        && (string)$e->getCode() === '23000'
        && strpos(strtolower($e->getMessage()), 'duplicate') !== false;
}

try {
    $periodInput = $argv[1] ?? date('Y-m-01');
    $period = valid_period($periodInput);

    $db = new Database();
    $pdo = $db->connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    log_line('=== NetMonitor Automatic Invoice Generator ===');
    log_line('Periode: ' . $period);

    $customers = $pdo->query("
        SELECT
            id,
            name,
            pppoe_username,
            monthly_price,
            billing_day,
            status
        FROM billing_customers
        WHERE status='active'
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    log_line('Pelanggan aktif: ' . count($customers));

    $check = $pdo->prepare("
        SELECT id, invoice_no
        FROM billing_invoices
        WHERE customer_id=? AND period=?
        LIMIT 1
    ");

    $insert = $pdo->prepare("
        INSERT INTO billing_invoices
        (customer_id, invoice_no, period, amount, due_date, status)
        VALUES (?,?,?,?,?,'unpaid')
    ");

    $created = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($customers as $customer) {
        $customerId = (int)$customer['id'];

        try {
            // Idempotency check.
            $check->execute([$customerId, $period]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $skipped++;
                log_line(
                    "SKIP  #{$customerId} {$customer['name']} - {$existing['invoice_no']} sudah ada"
                );
                continue;
            }

            $amount = (float)($customer['monthly_price'] ?? 0);

            if ($amount < 0) {
                throw new RuntimeException('Harga bulanan tidak valid.');
            }

            $dueDate = due_date_for_period(
                $period,
                (int)($customer['billing_day'] ?? 10)
            );

            $invoiceNo = make_invoice_no($pdo, $period, $customerId);

            try {
                $insert->execute([
                    $customerId,
                    $invoiceNo,
                    $period,
                    $amount,
                    $dueDate
                ]);

                $created++;

                log_line(
                    "CREATE #{$customerId} {$customer['name']} - {$invoiceNo} - due {$dueDate} - Rp " .
                    number_format($amount, 0, ',', '.')
                );
            } catch (Throwable $insertError) {
                // Concurrent scheduler/manual generation can hit the UNIQUE key.
                if (is_duplicate_exception($insertError)) {
                    $skipped++;
                    log_line(
                        "SKIP  #{$customerId} {$customer['name']} - invoice sudah dibuat proses lain"
                    );
                } else {
                    throw $insertError;
                }
            }
        } catch (Throwable $e) {
            $failed++;
            log_line(
                "ERROR #{$customerId} {$customer['name']} - " . $e->getMessage()
            );
        }
    }

    log_line("SELESAI created={$created}, skipped={$skipped}, failed={$failed}");
    log_line('==============================================');

    exit($failed > 0 ? 1 : 0);

} catch (Throwable $e) {
    log_line('FATAL: ' . $e->getMessage());
    exit(1);
}
