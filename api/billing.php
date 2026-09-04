<?php
/*
 * NetMonitor Billing API - Sprint Integrasi Billing <-> PPPoE
 *
 * Fitur:
 * summary, pppoe_accounts, customers, invoices, save_customer,
 * delete_customer, generate_invoice, generate_batch, pay_invoice,
 * cancel_invoice, billing_control, overdue_candidates,
 * process_auto_suspend, set_customer_status.
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

function post_string(string $key, string $default = ''): string {
    return trim((string)($_POST[$key] ?? $default));
}

function valid_period(string $value): string {
    $value = trim($value);
    if (preg_match('/^\d{4}-\d{2}$/', $value)) $value .= '-01';

    $d = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$d ||
        ($errors !== false && ($errors['warning_count'] ?? 0) > 0) ||
        ($errors !== false && ($errors['error_count'] ?? 0) > 0) ||
        $d->format('Y-m-d') !== $value
    ) {
        out_json(false, 'Periode tidak valid. Gunakan format YYYY-MM atau YYYY-MM-DD.', [], 422);
    }

    return $d->format('Y-m-01');
}

function due_date_for_period(string $period, int $billingDay): string {
    $d = new DateTime($period);
    $lastDay = (int)$d->format('t');
    $day = max(1, min(28, $billingDay));
    $day = min($day, $lastDay);
    return $d->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
}

function make_invoice_no(PDO $pdo, string $period, int $customerId): string {
    $base = 'INV-' . date('Ym', strtotime($period)) . '-' .
        str_pad((string)$customerId, 5, '0', STR_PAD_LEFT);

    $no = $base;
    $n = 2;
    $stmt = $pdo->prepare("SELECT id FROM billing_invoices WHERE invoice_no=? LIMIT 1");

    while (true) {
        $stmt->execute([$no]);
        if (!$stmt->fetchColumn()) return $no;
        $no = $base . '-' . $n++;
    }
}

function is_duplicate_exception(Throwable $e): bool {
    return $e instanceof PDOException
        && (string)$e->getCode() === '23000'
        && strpos(strtolower($e->getMessage()), 'duplicate') !== false;
}

/* ==========================================================
 * PPPoE API BRIDGE
 * ========================================================== */

function pppoe_api_call(string $action, array $post = []): array {
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
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $error !== '') {
        throw new RuntimeException(
            'Koneksi API PPPoE gagal: ' . ($error ?: 'unknown error')
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

function get_pppoe_secrets(): array {
    $data = pppoe_api_call('secrets');
    return is_array($data['secrets'] ?? null) ? $data['secrets'] : [];
}

function find_pppoe_secret_by_name(array $secrets, string $username): ?array {
    foreach ($secrets as $secret) {
        if (!is_array($secret)) continue;
        if ((string)($secret['name'] ?? '') === $username) return $secret;
    }
    return null;
}

function set_pppoe_secret_status(string $username, bool $disabled): array {
    $secrets = get_pppoe_secrets();
    $secret = find_pppoe_secret_by_name($secrets, $username);

    if (!$secret) {
        throw new RuntimeException(
            "Username PPPoE '$username' tidak ditemukan di MikroTik."
        );
    }

    $action = $disabled ? 'disable_secret' : 'enable_secret';
    return pppoe_api_call($action, ['id' => (string)($secret['id'] ?? '')]);
}

/* ==========================================================
 * MAIN
 * ========================================================== */

try {
    $db = new Database();
    $pdo = $db->connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = strtolower((string)($_GET['action'] ?? 'summary'));

    /* SUMMARY */
    if ($action === 'summary') {
        $customers = (int)$pdo->query("SELECT COUNT(*) FROM billing_customers")->fetchColumn();
        $active = (int)$pdo->query("SELECT COUNT(*) FROM billing_customers WHERE status='active'")->fetchColumn();
        $unpaid = (int)$pdo->query("SELECT COUNT(*) FROM billing_invoices WHERE status='unpaid'")->fetchColumn();
        $paidMonth = (float)$pdo->query("
            SELECT COALESCE(SUM(amount),0)
            FROM billing_invoices
            WHERE status='paid'
              AND YEAR(period)=YEAR(CURDATE())
              AND MONTH(period)=MONTH(CURDATE())
        ")->fetchColumn();

        out_json(true, '', [
            'customers' => $customers,
            'active_customers' => $active,
            'unpaid_invoices' => $unpaid,
            'paid_month' => $paidMonth
        ]);
    }

    /* PPPoE ACCOUNTS FOR BILLING */
    if ($action === 'pppoe_accounts') {
        try {
            $secrets = get_pppoe_secrets();

            $usedRows = $pdo->query("
                SELECT id, name, pppoe_username, status
                FROM billing_customers
                WHERE pppoe_username IS NOT NULL
                  AND TRIM(pppoe_username) <> ''
                ORDER BY pppoe_username ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $usedMap = [];
            foreach ($usedRows as $row) {
                $usedMap[(string)$row['pppoe_username']] = [
                    'customer_id' => (int)$row['id'],
                    'customer_name' => (string)$row['name'],
                    'customer_status' => (string)$row['status']
                ];
            }

            $accounts = [];
            foreach ($secrets as $secret) {
                if (!is_array($secret)) continue;

                $username = trim((string)($secret['name'] ?? ''));
                if ($username === '') continue;

                $used = $usedMap[$username] ?? null;

                $accounts[] = [
                    'id' => (string)($secret['id'] ?? ''),
                    'name' => $username,
                    'service' => (string)($secret['service'] ?? 'pppoe'),
                    'profile' => (string)($secret['profile'] ?? 'default'),
                    'disabled' => (bool)($secret['disabled'] ?? false),
                    'comment' => (string)($secret['comment'] ?? ''),
                    'billing_linked' => $used !== null,
                    'customer_id' => $used['customer_id'] ?? null,
                    'customer_name' => $used['customer_name'] ?? null,
                    'customer_status' => $used['customer_status'] ?? null
                ];
            }

            out_json(true, 'Daftar akun PPPoE berhasil diambil.', [
                'total' => count($accounts),
                'accounts' => $accounts
            ]);
        } catch (Throwable $e) {
            out_json(false, 'Gagal mengambil akun PPPoE: ' . $e->getMessage(), [], 502);
        }
    }

    /* CUSTOMERS */
    if ($action === 'customers') {
        $q = trim((string)($_GET['q'] ?? ''));

        if ($q !== '') {
            $stmt = $pdo->prepare("
                SELECT *
                FROM billing_customers
                WHERE name LIKE :q
                   OR phone LIKE :q
                   OR pppoe_username LIKE :q
                   OR package_name LIKE :q
                ORDER BY name ASC
            ");
            $stmt->execute([':q' => "%$q%"]);
        } else {
            $stmt = $pdo->query("SELECT * FROM billing_customers ORDER BY id DESC");
        }

        $customerRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        try {
            $secrets = get_pppoe_secrets();
            foreach ($customerRows as &$row) {
                $live = find_pppoe_secret_by_name(
                    $secrets,
                    (string)($row['pppoe_username'] ?? '')
                );
                $row['pppoe_found'] = $live !== null;
                $row['pppoe_disabled'] = $live
                    ? (bool)($live['disabled'] ?? false)
                    : null;
            }
            unset($row);
        } catch (Throwable $e) {
            foreach ($customerRows as &$row) {
                $row['pppoe_found'] = null;
                $row['pppoe_disabled'] = null;
            }
            unset($row);
        }

        out_json(true, '', ['customers' => $customerRows]);
    }

    /* INVOICES */
    if ($action === 'invoices') {
        $stmt = $pdo->query("
            SELECT
                i.*,
                c.name AS customer_name,
                c.pppoe_username,
                c.phone
            FROM billing_invoices i
            INNER JOIN billing_customers c ON c.id=i.customer_id
            ORDER BY i.period DESC, i.due_date ASC, i.id DESC
        ");

        out_json(true, '', [
            'invoices' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    /* SAVE CUSTOMER */
    if ($action === 'save_customer') {
        $id = (int)($_POST['id'] ?? 0);
        $name = post_string('name');
        $phone = post_string('phone');
        $address = post_string('address');
        $pppoe = post_string('pppoe_username');
        $package = post_string('package_name');
        $price = (float)($_POST['monthly_price'] ?? 0);
        $billingDay = max(1, min(28, (int)($_POST['billing_day'] ?? 10)));
        $status = (($_POST['status'] ?? 'active') === 'suspended')
            ? 'suspended' : 'active';
        $notes = post_string('notes');

        if ($name === '' || $pppoe === '' || $package === '') {
            out_json(false, 'Nama, username PPPoE dan paket wajib diisi.', [], 422);
        }

        if ($price < 0) {
            out_json(false, 'Harga bulanan tidak valid.', [], 422);
        }

        try {
            $secrets = get_pppoe_secrets();
        } catch (Throwable $e) {
            out_json(
                false,
                'Tidak dapat memverifikasi akun PPPoE MikroTik: ' . $e->getMessage(),
                [],
                502
            );
        }

        $secret = find_pppoe_secret_by_name($secrets, $pppoe);

        if (!$secret) {
            out_json(false, "Username PPPoE '$pppoe' tidak ditemukan di MikroTik.", [], 422);
        }

        $duplicate = $pdo->prepare("
            SELECT id, name
            FROM billing_customers
            WHERE pppoe_username = ?
              AND id <> ?
            LIMIT 1
        ");
        $duplicate->execute([$pppoe, $id]);
        $dup = $duplicate->fetch(PDO::FETCH_ASSOC);

        if ($dup) {
            out_json(
                false,
                "Username PPPoE '$pppoe' sudah terhubung ke pelanggan " . $dup['name'] . ".",
                ['customer_id' => (int)$dup['id']],
                409
            );
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE billing_customers
                SET name=?, phone=?, address=?, pppoe_username=?,
                    package_name=?, monthly_price=?, billing_day=?,
                    status=?, notes=?
                WHERE id=?
            ");
            $stmt->execute([
                $name, $phone, $address, $pppoe, $package,
                $price, $billingDay, $status, $notes, $id
            ]);

            out_json(true, 'Data pelanggan berhasil diperbarui.');
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO billing_customers
                (name,phone,address,pppoe_username,package_name,
                 monthly_price,billing_day,status,notes)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $name, $phone, $address, $pppoe, $package,
                $price, $billingDay, $status, $notes
            ]);
        } catch (Throwable $e) {
            if (is_duplicate_exception($e)) {
                out_json(false, "Username PPPoE '$pppoe' sudah digunakan.", [], 409);
            }
            throw $e;
        }

        out_json(true, 'Pelanggan berhasil ditambahkan.', [
            'customer_id' => (int)$pdo->lastInsertId()
        ]);
    }

    /* DELETE CUSTOMER */
    if ($action === 'delete_customer') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) out_json(false, 'ID pelanggan tidak valid.', [], 422);

        $pdo->beginTransaction();
        try {
            $check = $pdo->prepare("SELECT id FROM billing_customers WHERE id=?");
            $check->execute([$id]);

            if (!$check->fetchColumn()) {
                $pdo->rollBack();
                out_json(false, 'Pelanggan tidak ditemukan.', [], 404);
            }

            $pdo->prepare("DELETE FROM billing_invoices WHERE customer_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM billing_customers WHERE id=?")->execute([$id]);

            $pdo->commit();
            out_json(true, 'Pelanggan dan seluruh tagihannya berhasil dihapus.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /* SET CUSTOMER STATUS + PPPoE */
    if ($action === 'set_customer_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');

        if ($id <= 0 || !in_array($status, ['active', 'suspended'], true)) {
            out_json(false, 'Data status pelanggan tidak valid.', [], 422);
        }

        $stmt = $pdo->prepare("
            SELECT id, name, pppoe_username, status
            FROM billing_customers
            WHERE id=?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) out_json(false, 'Pelanggan tidak ditemukan.', [], 404);

        try {
            $pppoeResult = set_pppoe_secret_status(
                (string)$customer['pppoe_username'],
                $status === 'suspended'
            );
        } catch (Throwable $e) {
            out_json(false, 'Status PPPoE tidak dapat diubah: ' . $e->getMessage(), [], 502);
        }

        $update = $pdo->prepare("
            UPDATE billing_customers
            SET status=?
            WHERE id=?
        ");
        $update->execute([$status, $id]);

        out_json(
            true,
            $status === 'suspended'
                ? 'Pelanggan ditangguhkan dan PPPoE dinonaktifkan.'
                : 'Pelanggan diaktifkan dan PPPoE diaktifkan kembali.',
            [
                'customer_id' => $id,
                'customer_status' => $status,
                'pppoe' => $pppoeResult
            ]
        );
    }

    /* GENERATE ONE INVOICE */
    if ($action === 'generate_invoice') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $period = valid_period(post_string('period', date('Y-m-01')));

        if ($customerId <= 0) out_json(false, 'ID pelanggan tidak valid.', [], 422);

        $stmt = $pdo->prepare("SELECT * FROM billing_customers WHERE id=? LIMIT 1");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) out_json(false, 'Pelanggan tidak ditemukan.', [], 404);

        if (($customer['status'] ?? '') !== 'active') {
            out_json(true, 'Pelanggan tidak aktif, invoice dilewati.', [
                'result' => 'skipped',
                'customer_id' => $customerId,
                'period' => $period,
                'reason' => 'Pelanggan tidak aktif'
            ]);
        }

        $check = $pdo->prepare("
            SELECT id, invoice_no, status, amount, due_date
            FROM billing_invoices
            WHERE customer_id=? AND period=?
            LIMIT 1
        ");
        $check->execute([$customerId, $period]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            out_json(true, 'Tagihan untuk periode tersebut sudah ada.', [
                'result' => 'skipped',
                'customer_id' => $customerId,
                'period' => $period,
                'invoice_id' => (int)$existing['id'],
                'invoice_no' => $existing['invoice_no'],
                'reason' => 'Invoice periode sudah ada'
            ]);
        }

        $invoiceNo = make_invoice_no($pdo, $period, $customerId);
        $due = due_date_for_period($period, (int)($customer['billing_day'] ?? 10));
        $amount = (float)($customer['monthly_price'] ?? 0);

        try {
            $insert = $pdo->prepare("
                INSERT INTO billing_invoices
                (customer_id, invoice_no, period, amount, due_date, status)
                VALUES (?,?,?,?,?,'unpaid')
            ");
            $insert->execute([$customerId, $invoiceNo, $period, $amount, $due]);

            out_json(true, 'Tagihan berhasil dibuat.', [
                'result' => 'created',
                'customer_id' => $customerId,
                'invoice_id' => (int)$pdo->lastInsertId(),
                'invoice_no' => $invoiceNo,
                'period' => $period,
                'due_date' => $due,
                'amount' => $amount
            ]);
        } catch (Throwable $e) {
            if (is_duplicate_exception($e)) {
                $q = $pdo->prepare("
                    SELECT id, invoice_no FROM billing_invoices
                    WHERE customer_id=? AND period=? LIMIT 1
                ");
                $q->execute([$customerId, $period]);
                $row = $q->fetch(PDO::FETCH_ASSOC);

                out_json(true, 'Invoice sudah dibuat oleh proses lain.', [
                    'result' => 'skipped',
                    'customer_id' => $customerId,
                    'period' => $period,
                    'invoice_id' => $row ? (int)$row['id'] : null,
                    'invoice_no' => $row['invoice_no'] ?? null
                ]);
            }
            throw $e;
        }
    }

    /* GENERATE BATCH */
    if ($action === 'generate_batch') {
        $period = valid_period(post_string('period', date('Y-m-01')));

        $customers = $pdo->query("
            SELECT * FROM billing_customers
            WHERE status='active'
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $items = [];

        foreach ($customers as $customer) {
            $cid = (int)$customer['id'];

            $check = $pdo->prepare("
                SELECT id, invoice_no FROM billing_invoices
                WHERE customer_id=? AND period=? LIMIT 1
            ");
            $check->execute([$cid, $period]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $skipped++;
                $items[] = [
                    'customer_id' => $cid,
                    'status' => 'skipped',
                    'reason' => 'Invoice periode sudah ada',
                    'invoice_id' => (int)$existing['id'],
                    'invoice_no' => $existing['invoice_no']
                ];
                continue;
            }

            $invoiceNo = make_invoice_no($pdo, $period, $cid);
            $due = due_date_for_period($period, (int)($customer['billing_day'] ?? 10));
            $amount = (float)($customer['monthly_price'] ?? 0);

            try {
                $insert = $pdo->prepare("
                    INSERT INTO billing_invoices
                    (customer_id, invoice_no, period, amount, due_date, status)
                    VALUES (?,?,?,?,?,'unpaid')
                ");
                $insert->execute([$cid, $invoiceNo, $period, $amount, $due]);

                $created++;
                $items[] = [
                    'customer_id' => $cid,
                    'status' => 'created',
                    'invoice_id' => (int)$pdo->lastInsertId(),
                    'invoice_no' => $invoiceNo,
                    'due_date' => $due,
                    'amount' => $amount
                ];
            } catch (Throwable $e) {
                if (is_duplicate_exception($e)) {
                    $skipped++;
                    $items[] = [
                        'customer_id' => $cid,
                        'status' => 'skipped',
                        'reason' => 'UNIQUE customer_id + period'
                    ];
                } else {
                    $failed++;
                    $items[] = [
                        'customer_id' => $cid,
                        'status' => 'failed',
                        'reason' => $e->getMessage()
                    ];
                }
            }
        }

        out_json(true, "Generate invoice selesai: $created dibuat, $skipped dilewati, $failed gagal.", [
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'period' => $period,
            'items' => $items
        ]);
    }

    /* PAY INVOICE + AUTO REACTIVATE */
    if ($action === 'pay_invoice') {
        $id = (int)($_POST['id'] ?? 0);
        $method = post_string('payment_method', 'Cash');

        if ($id <= 0) out_json(false, 'ID tagihan tidak valid.', [], 422);

        $info = $pdo->prepare("
            SELECT
                i.id AS invoice_id,
                i.invoice_no,
                i.status AS invoice_status,
                i.amount,
                i.period,
                i.due_date,
                c.id AS customer_id,
                c.name AS customer_name,
                c.pppoe_username,
                c.status AS customer_status
            FROM billing_invoices i
            INNER JOIN billing_customers c ON c.id=i.customer_id
            WHERE i.id=? LIMIT 1
        ");
        $info->execute([$id]);
        $invoice = $info->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) out_json(false, 'Tagihan tidak ditemukan.', [], 404);

        if ($invoice['invoice_status'] !== 'unpaid') {
            out_json(false, 'Tagihan tidak ditemukan atau sudah dibayar.', [
                'invoice_id' => (int)$invoice['invoice_id'],
                'invoice_no' => $invoice['invoice_no'],
                'status' => $invoice['invoice_status']
            ], 409);
        }

        $stmt = $pdo->prepare("
            UPDATE billing_invoices
            SET status='paid', paid_at=NOW(), payment_method=?
            WHERE id=? AND status='unpaid'
        ");
        $stmt->execute([$method, $id]);

        if ($stmt->rowCount() === 0) {
            out_json(false, 'Pembayaran gagal dicatat.', [], 409);
        }

        $reactivation = [
            'attempted' => false,
            'success' => false,
            'result' => 'not_required',
            'message' => 'Reaktivasi tidak diperlukan.'
        ];

        if ($invoice['customer_status'] === 'suspended') {
            $reactivation['attempted'] = true;

            try {
                $r = set_pppoe_secret_status(
                    (string)$invoice['pppoe_username'],
                    false
                );

                $updateCustomer = $pdo->prepare("
                    UPDATE billing_customers
                    SET status='active'
                    WHERE id=? AND status='suspended'
                ");
                $updateCustomer->execute([(int)$invoice['customer_id']]);

                $reactivation = [
                    'attempted' => true,
                    'success' => true,
                    'result' => 'reactivated',
                    'message' => 'PPPoE diaktifkan dan pelanggan kembali aktif.',
                    'pppoe' => $r
                ];
            } catch (Throwable $e) {
                $reactivation['success'] = false;
                $reactivation['result'] = 'error';
                $reactivation['message'] = $e->getMessage();
            }
        } elseif ($invoice['customer_status'] === 'active') {
            $reactivation = [
                'attempted' => false,
                'success' => true,
                'result' => 'already_active',
                'message' => 'Pelanggan sudah aktif.'
            ];
        }

        out_json(true, 'Pembayaran berhasil dicatat.', [
            'invoice_id' => (int)$invoice['invoice_id'],
            'invoice_no' => $invoice['invoice_no'],
            'customer_id' => (int)$invoice['customer_id'],
            'customer_name' => $invoice['customer_name'],
            'amount' => (float)$invoice['amount'],
            'payment_method' => $method,
            'reactivation' => $reactivation
        ]);
    }

    /* CANCEL INVOICE */
    if ($action === 'cancel_invoice') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) out_json(false, 'ID tagihan tidak valid.', [], 422);

        $stmt = $pdo->prepare("
            UPDATE billing_invoices
            SET status='cancelled'
            WHERE id=? AND status='unpaid'
        ");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            out_json(false, 'Tagihan tidak ditemukan atau bukan BELUM BAYAR.', [], 409);
        }

        out_json(true, 'Tagihan dibatalkan.');
    }

    /* BILLING CONTROL */
    if ($action === 'billing_control') {
        $customers = $pdo->query("
            SELECT
                COUNT(*) AS total_customers,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_customers,
                SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END) AS suspended_customers
            FROM billing_customers
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        $invoices = $pdo->query("
            SELECT
                COUNT(*) AS total_invoices,
                SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_invoices,
                SUM(CASE WHEN status='unpaid' THEN 1 ELSE 0 END) AS unpaid_invoices,
                SUM(CASE WHEN status='unpaid' AND due_date<CURDATE() THEN 1 ELSE 0 END) AS overdue_invoices,
                COALESCE(SUM(CASE WHEN status='unpaid' THEN amount ELSE 0 END),0) AS unpaid_amount,
                COALESCE(SUM(CASE WHEN status='unpaid' AND due_date<CURDATE() THEN amount ELSE 0 END),0) AS overdue_amount
            FROM billing_invoices
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        out_json(true, 'Data kontrol billing berhasil diambil.', [
            'customers' => [
                'total' => (int)($customers['total_customers'] ?? 0),
                'active' => (int)($customers['active_customers'] ?? 0),
                'suspended' => (int)($customers['suspended_customers'] ?? 0)
            ],
            'invoices' => [
                'total' => (int)($invoices['total_invoices'] ?? 0),
                'paid' => (int)($invoices['paid_invoices'] ?? 0),
                'unpaid' => (int)($invoices['unpaid_invoices'] ?? 0),
                'overdue' => (int)($invoices['overdue_invoices'] ?? 0)
            ],
            'amount' => [
                'unpaid' => (float)($invoices['unpaid_amount'] ?? 0),
                'overdue' => (float)($invoices['overdue_amount'] ?? 0)
            ]
        ]);
    }

    /* OVERDUE CANDIDATES */
    if ($action === 'overdue_candidates') {
        $grace = max(0, min(30, (int)($_POST['grace_days'] ?? $_GET['grace_days'] ?? 3)));

        $stmt = $pdo->prepare("
            SELECT
                c.id, c.name, c.pppoe_username,
                i.id AS invoice_id, i.invoice_no, i.period,
                i.due_date, i.amount,
                DATEDIFF(CURDATE(),i.due_date) AS overdue_days
            FROM billing_customers c
            INNER JOIN billing_invoices i ON i.customer_id=c.id
            WHERE c.status='active'
              AND i.status='unpaid'
              AND i.due_date<CURDATE()
              AND DATEDIFF(CURDATE(),i.due_date)>=?
            ORDER BY i.due_date ASC,c.name ASC
        ");
        $stmt->execute([$grace]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        out_json(true, '', [
            'grace_days' => $grace,
            'customers' => $rows,
            'count' => count($rows)
        ]);
    }

    /* PROCESS AUTO SUSPEND + MIKROTIK */
    if ($action === 'process_auto_suspend') {
        $grace = max(0, min(30, (int)($_POST['grace_days'] ?? 3)));

        $stmt = $pdo->prepare("
            SELECT DISTINCT c.id,c.name,c.pppoe_username
            FROM billing_customers c
            INNER JOIN billing_invoices i ON i.customer_id=c.id
            WHERE c.status='active'
              AND i.status='unpaid'
              AND i.due_date<CURDATE()
              AND DATEDIFF(CURDATE(),i.due_date)>=?
        ");
        $stmt->execute([$grace]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            out_json(true, 'Tidak ada pelanggan yang memenuhi batas suspend.', [
                'suspended' => 0,
                'failed' => 0,
                'items' => []
            ]);
        }

        $update = $pdo->prepare("
            UPDATE billing_customers SET status='suspended'
            WHERE id=? AND status='active'
        ");

        $suspended = 0;
        $failed = 0;
        $items = [];

        foreach ($rows as $row) {
            try {
                $pppoe = set_pppoe_secret_status(
                    (string)$row['pppoe_username'],
                    true
                );

                $update->execute([(int)$row['id']]);

                if ($update->rowCount() > 0) {
                    $suspended++;
                    $items[] = [
                        'customer_id' => (int)$row['id'],
                        'name' => $row['name'],
                        'pppoe_username' => $row['pppoe_username'],
                        'status' => 'suspended',
                        'pppoe' => $pppoe
                    ];
                }
            } catch (Throwable $e) {
                $failed++;
                $items[] = [
                    'customer_id' => (int)$row['id'],
                    'name' => $row['name'],
                    'pppoe_username' => $row['pppoe_username'],
                    'status' => 'failed',
                    'reason' => $e->getMessage()
                ];
            }
        }

        out_json(true,
            "Proses suspend selesai: $suspended berhasil, $failed gagal.",
            [
                'suspended' => $suspended,
                'failed' => $failed,
                'items' => $items,
                'grace_days' => $grace
            ]
        );
    }

    out_json(false, 'Action tidak dikenal.', [], 404);

} catch (Throwable $e) {
    out_json(false, $e->getMessage(), [], 500);
}
