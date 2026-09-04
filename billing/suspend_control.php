<?php
$activeMenu = 'billing';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kontrol Suspend - NetMonitor</title>

<link rel="stylesheet" href="../assets/css/variables.css?v=10">
<link rel="stylesheet" href="../assets/css/common.css?v=10">
<link rel="stylesheet" href="../assets/css/theme.css?v=1">
<link rel="stylesheet" href="../assets/css/billing.css?v=21">
<link rel="stylesheet" href="../assets/css/billing-suspend.css?v=1">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="main-content billing-suspend-page">
    <div class="billing-suspend-header">
        <div>
            <h1>Kontrol Suspend PPPoE</h1>
            <p>
                Kandidat suspend berdasarkan tagihan belum bayar,
                jatuh tempo, dan masa toleransi.
            </p>
        </div>
        <a class="billing-suspend-btn secondary" href="../billing/">
            <i class="bi bi-arrow-left"></i> Kembali ke Billing
        </a>
    </div>

    <section class="billing-suspend-warning">
        <div class="icon"><i class="bi bi-shield-check"></i></div>
        <div>
            <strong>Mode TEST aman digunakan.</strong>
            <p>
                Pemeriksaan kandidat tidak mengubah user PPPoE MikroTik.
                Suspend hanya dilakukan jika mode LIVE dipilih secara eksplisit.
            </p>
        </div>
    </section>

    <section class="billing-suspend-card">
        <div class="card-title">
            <div>
                <h2>Parameter Suspend</h2>
                <small>Aturan yang digunakan saat mencari kandidat.</small>
            </div>
        </div>

        <div class="billing-suspend-controls">
            <label>
                Masa Toleransi (hari)
                <input id="graceDays" type="number" min="0" max="30" value="3">
            </label>

            <button id="previewBtn" class="billing-suspend-btn primary">
                <i class="bi bi-search"></i> Cek Kandidat
            </button>

            <button id="liveBtn" class="billing-suspend-btn danger" disabled>
                <i class="bi bi-wifi-off"></i> Proses Suspend LIVE
            </button>
        </div>

        <div id="message" class="billing-suspend-message" hidden></div>
    </section>

    <section class="billing-suspend-card">
        <div class="card-title">
            <div>
                <h2>Kandidat Suspend</h2>
                <small id="candidateInfo">Belum dilakukan pemeriksaan.</small>
            </div>
            <span id="modeBadge" class="mode-badge test">TEST</span>
        </div>

        <div class="billing-suspend-table-wrap">
            <table class="billing-suspend-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Pelanggan</th>
                        <th>PPPoE</th>
                        <th>Invoice</th>
                        <th>Jatuh Tempo</th>
                        <th>Terlambat</th>
                        <th>Jumlah</th>
                    </tr>
                </thead>
                <tbody id="candidateTable">
                    <tr>
                        <td colspan="7">Klik "Cek Kandidat".</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
(() => {
    const API = '../api/billing_suspend.php';
    const $ = id => document.getElementById(id);

    let candidates = [];

    const esc = value => {
        const d = document.createElement('div');
        d.textContent = value ?? '-';
        return d.innerHTML;
    };

    const money = value =>
        new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(Number(value) || 0);

    function message(text, error = false) {
        const el = $('message');
        el.textContent = text || '';
        el.hidden = !text;
        el.classList.toggle('error', error);
    }

    function render() {
        $('candidateTable').innerHTML = candidates.length
            ? candidates.map((x, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td><strong>${esc(x.name)}</strong></td>
                    <td>${esc(x.pppoe_username)}</td>
                    <td>${esc(x.invoice_no)}</td>
                    <td>${esc(x.due_date)}</td>
                    <td>
                        <span class="overdue-pill">
                            ${esc(x.overdue_days)} hari
                        </span>
                    </td>
                    <td>${money(x.amount)}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="7">Tidak ada kandidat suspend.</td></tr>';
    }

    async function preview() {
        message('Memeriksa kandidat...');
        $('liveBtn').disabled = true;

        try {
            const grace = Math.max(
                0,
                Math.min(30, Number($('graceDays').value) || 0)
            );

            const response = await fetch(
                API + '?action=preview&grace_days=' +
                encodeURIComponent(grace) +
                '&t=' + Date.now(),
                { cache: 'no-store' }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Gagal memeriksa kandidat.');
            }

            candidates = data.candidates || [];
            render();

            $('candidateInfo').textContent =
                candidates.length +
                ' kandidat ditemukan. Masa toleransi ' +
                data.grace_days + ' hari.';

            $('modeBadge').textContent = 'TEST';
            $('modeBadge').className = 'mode-badge test';

            $('liveBtn').disabled = candidates.length === 0;

            message(
                candidates.length
                    ? 'Mode TEST: kandidat ditemukan. Belum ada perubahan ke MikroTik.'
                    : 'Mode TEST: tidak ada pelanggan yang memenuhi batas suspend.'
            );
        } catch (error) {
            candidates = [];
            render();
            message(error.message, true);
        }
    }

    async function executeLive() {
        if (!candidates.length) {
            message('Tidak ada kandidat suspend.', true);
            return;
        }

        const grace = Math.max(
            0,
            Math.min(30, Number($('graceDays').value) || 0)
        );

        const confirmText =
            'PERINGATAN\\n\\n' +
            'Anda akan menonaktifkan user PPPoE di MikroTik untuk ' +
            candidates.length + ' pelanggan.\\n\\n' +
            'Lanjutkan mode LIVE?';

        if (!confirm(confirmText)) return;

        $('liveBtn').disabled = true;
        $('previewBtn').disabled = true;
        message('Memproses suspend LIVE...');

        try {
            const response = await fetch(
                API + '?action=execute&grace_days=' +
                encodeURIComponent(grace),
                {
                    method: 'POST',
                    body: new URLSearchParams({ mode: 'live' })
                }
            );

            const data = await response.json();

            if (!response.ok && response.status !== 207) {
                throw new Error(data.message || 'Suspend LIVE gagal.');
            }

            $('modeBadge').textContent = 'LIVE SELESAI';
            $('modeBadge').className = 'mode-badge live';

            message(
                (data.message || 'Proses selesai.') +
                ' Suspend berhasil: ' +
                (data.suspended || 0) +
                ', error: ' +
                (data.errors || 0)
            );

            await preview();
        } catch (error) {
            message(error.message, true);
        } finally {
            $('previewBtn').disabled = false;
        }
    }

    $('previewBtn').addEventListener('click', preview);
    $('liveBtn').addEventListener('click', executeLive);

    preview();
})();
</script>

    <script src="../assets/js/app.js?v=1"></script>
</body>
</html>
