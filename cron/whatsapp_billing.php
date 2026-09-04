<?php
/*
 * NetMonitor - WhatsApp Billing Automation
 * Lokasi: /NetworkMonitor/cron/whatsapp_billing.php
 *
 * Fungsi:
 * - Membaca invoice aktif
 * - Menentukan event invoice / H-3 / H-1 / Hari H / overdue / paid
 * - Membuat antrean di billing_whatsapp_logs
 * - Anti duplikat: 1 invoice + 1 event hanya dibuat sekali per periode yang relevan
 *
 * Catatan:
 * File ini BELUM mengirim WhatsApp secara otomatis.
 * Status yang dibuat adalah "prepared" dan nanti diproses oleh sender/provider.
 */

require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Asia/Jakarta');

function normalize_phone(string $phone): string
{
    $phone = preg_replace('/\D+/', '', $phone) ?? '';

    if ($phone === '') {
        return '';
    }

    if (str_starts_with($phone, '0')) {
        return '62' . substr($phone, 1);
    }

    if (str_starts_with($phone, '8')) {
        return '62' . $phone;
    }

    return $phone;
}

function render_template(string $tpl, array $v): string
{
    foreach ($v as $key => $value) {
        $tpl = str_replace('{' . $key . '}', (string)$value, $tpl);
    }

    return $tpl;
}

function log_line(string $text): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $text . PHP_EOL;
}

try {
    $pdo = (new Database())->connect();

    /*
     * Jika dijalankan melalui browser, tetap bisa digunakan untuk testing.
     * Jika melalui CLI, output akan tampil di console.
     */
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: text/plain; charset=utf-8');
    }

    $today = new DateTimeImmutable('today');

    $stats = [
        'invoice'   => 0,
        'h3'        => 0,
        'h1'        => 0,
        'due'       => 0,
        'overdue'   => 0,
        'paid'      => 0,
        'suspended' => 0,
        'skip'      => 0,
        'error'     => 0
    ];

    /*
     * Ambil invoice yang mempunyai customer.
     * Status cancelled tidak diproses.
     */
    $stmt = $pdo->query("
        SELECT
            i.id,
            i.customer_id,
            i.invoice_no,
            i.period,
            i.amount,
            i.due_date,
            i.status,
            i.paid_at,
            i.payment_method,
            i.created_at,
            c.name AS customer_name,
            c.phone,
            c.package_name,
            c.pppoe_username,
            c.status AS customer_status
        FROM billing_invoices i
        INNER JOIN billing_customers c ON c.id = i.customer_id
        WHERE i.status <> 'cancelled'
        ORDER BY i.due_date ASC, i.id ASC
    ");

    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Template aktif berdasarkan event.
     */
    $tplStmt = $pdo->query("
        SELECT event_key, message_template
        FROM billing_whatsapp_templates
        WHERE enabled = 1
    ");

    $templates = [];
    foreach ($tplStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $templates[$row['event_key']] = $row['message_template'];
    }

    if (!$templates) {
        throw new RuntimeException('Tidak ada template WhatsApp yang aktif.');
    }

    /*
     * Mengecek apakah event sudah pernah dibuat.
     *
     * Untuk event harian seperti overdue:
     * - satu invoice hanya dibuat sekali untuk event tersebut.
     *
     * Untuk paid:
     * - satu invoice + event paid hanya sekali.
     */
    $existsStmt = $pdo->prepare("
        SELECT id
        FROM billing_whatsapp_logs
        WHERE invoice_id = ?
          AND customer_id = ?
          AND event_key = ?
        LIMIT 1
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO billing_whatsapp_logs
        (invoice_id, customer_id, event_key, phone, message, status, provider)
        VALUES (?, ?, ?, ?, ?, 'prepared', 'wa.me')
    ");

    foreach ($invoices as $invoice) {
        $invoiceId  = (int)$invoice['id'];
        $customerId = (int)$invoice['customer_id'];
        $phone      = normalize_phone((string)$invoice['phone']);

        if ($phone === '') {
            $stats['skip']++;
            log_line("SKIP {$invoice['invoice_no']} - nomor WhatsApp kosong.");
            continue;
        }

        if (empty($invoice['due_date'])) {
            $stats['skip']++;
            log_line("SKIP {$invoice['invoice_no']} - due_date kosong.");
            continue;
        }

        try {
            $dueDate = new DateTimeImmutable($invoice['due_date']);
        } catch (Throwable $e) {
            $stats['error']++;
            log_line("ERROR {$invoice['invoice_no']} - due_date tidak valid.");
            continue;
        }

        $daysToDue = (int)$today->diff($dueDate)->format('%r%a');
        $overdueDays = max(0, -$daysToDue);

        /*
         * Tentukan event.
         *
         * Prioritas:
         * 1. paid
         * 2. overdue
         * 3. due
         * 4. H-1
         * 5. H-3
         * 6. invoice baru
         *
         * Karena satu invoice bisa memenuhi lebih dari satu kondisi,
         * kita proses semua event yang memang jatuh pada hari ini.
         */
        $events = [];

        if ($invoice['status'] === 'paid') {
            if (!empty($invoice['paid_at'])) {
                $paidDate = substr((string)$invoice['paid_at'], 0, 10);

                if ($paidDate === $today->format('Y-m-d')) {
                    $events[] = 'paid';
                }
            }
        } else {
            if ($daysToDue === 3) {
                $events[] = 'h3';
            }

            if ($daysToDue === 1) {
                $events[] = 'h1';
            }

            if ($daysToDue === 0) {
                $events[] = 'due';
            }

            /*
             * Tagihan terlambat diproses pada hari pertama keterlambatan.
             * Jika ingin mengubahnya menjadi setiap hari, logika ini
             * dapat diubah kemudian.
             */
            if ($daysToDue === -1) {
                $events[] = 'overdue';
            }

            /*
             * Tagihan baru:
             * hanya invoice yang dibuat hari ini.
             */
            if (!empty($invoice['created_at'])) {
                $createdDate = substr((string)$invoice['created_at'], 0, 10);

                if ($createdDate === $today->format('Y-m-d')) {
                    $events[] = 'invoice';
                }
            }
        }

        /*
         * Status suspended:
         * hanya dibuat jika customer sudah berstatus suspended.
         * Anti-duplikat menjaga agar tidak dibuat berulang.
         */
        if ($invoice['customer_status'] === 'suspended') {
            $events[] = 'suspended';
        }

        $events = array_values(array_unique($events));

        foreach ($events as $event) {
            if (empty($templates[$event])) {
                $stats['skip']++;
                log_line("SKIP {$invoice['invoice_no']} / {$event} - template tidak aktif.");
                continue;
            }

            $existsStmt->execute([$invoiceId, $customerId, $event]);

            if ($existsStmt->fetchColumn()) {
                $stats['skip']++;
                continue;
            }

            $message = render_template($templates[$event], [
                'name'            => $invoice['customer_name'],
                'invoice_no'      => $invoice['invoice_no'],
                'period'          => $invoice['period'],
                'package'         => $invoice['package_name'],
                'amount'          => 'Rp ' . number_format((float)$invoice['amount'], 0, ',', '.'),
                'due_date'        => $invoice['due_date'],
                'overdue_days'    => $overdueDays,
                'paid_at'         => $invoice['paid_at'] ?? '-',
                'payment_method'  => $invoice['payment_method'] ?? '-'
            ]);

            $insertStmt->execute([
                $invoiceId,
                $customerId,
                $event,
                $phone,
                $message
            ]);

            $stats[$event]++;
            log_line("QUEUE {$invoice['invoice_no']} / {$event} / {$phone}");
        }
    }

    log_line('----------------------------------------');
    log_line('WhatsApp Billing selesai.');
    log_line('Invoice baru   : ' . $stats['invoice']);
    log_line('H-3            : ' . $stats['h3']);
    log_line('H-1            : ' . $stats['h1']);
    log_line('Hari H         : ' . $stats['due']);
    log_line('Terlambat      : ' . $stats['overdue']);
    log_line('Pembayaran     : ' . $stats['paid']);
    log_line('Suspended      : ' . $stats['suspended']);
    log_line('Dilewati       : ' . $stats['skip']);
    log_line('Error          : ' . $stats['error']);
    log_line('----------------------------------------');

} catch (Throwable $e) {
    http_response_code(500);
    log_line('FATAL ERROR: ' . $e->getMessage());
    exit(1);
}
