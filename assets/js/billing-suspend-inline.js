/* NetMonitor Sprint 3.0
 * Inline billing suspend control.
 * TEST is always the first operation.
 */
(function () {
    'use strict';

    const API = '../api/billing_suspend.php';

    function esc(value) {
        const d = document.createElement('div');
        d.textContent = value == null ? '-' : String(value);
        return d.innerHTML;
    }

    function money(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(Number(value) || 0);
    }

    function init(root) {
        const grace = root.querySelector('[data-s30-grace]');
        const check = root.querySelector('[data-s30-check]');
        const live = root.querySelector('[data-s30-live]');
        const audit = root.querySelector('[data-s30-audit]');
        const info = root.querySelector('[data-s30-info]');
        const badge = root.querySelector('[data-s30-badge]');
        const wrap = root.querySelector('[data-s30-table-wrap]');
        const body = root.querySelector('[data-s30-body]');

        let candidates = [];

        function show(text, type) {
            info.hidden = !text;
            info.textContent = text || '';
            info.className = 'billing-s30-info' + (type ? ' ' + type : '');
        }

        function render() {
            wrap.style.display = candidates.length ? 'block' : 'none';

            body.innerHTML = candidates.length
                ? candidates.map((x, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td><strong>${esc(x.name)}</strong></td>
                        <td>${esc(x.pppoe_username)}</td>
                        <td>${esc(x.invoice_no)}</td>
                        <td>${esc(x.due_date)}</td>
                        <td><span class="billing-s30-overdue">
                            ${esc(x.overdue_days)} hari
                        </span></td>
                        <td>${money(x.amount)}</td>
                    </tr>
                `).join('')
                : '<tr><td colspan="7">Tidak ada kandidat.</td></tr>';
        }

        async function checkCandidates() {
            const days = Math.max(
                0,
                Math.min(30, Number(grace.value) || 0)
            );

            check.disabled = true;
            live.disabled = true;
            show('Memeriksa tagihan terlambat...', '');

            try {
                const response = await fetch(
                    API +
                    '?action=preview&grace_days=' +
                    encodeURIComponent(days) +
                    '&t=' + Date.now(),
                    { cache: 'no-store' }
                );

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Pemeriksaan gagal.');
                }

                candidates = data.candidates || [];
                render();

                badge.textContent = 'TEST';
                badge.className = 'billing-s30-badge';

                live.disabled = candidates.length === 0;

                if (candidates.length) {
                    show(
                        candidates.length +
                        ' kandidat ditemukan. Belum ada perubahan ke MikroTik.',
                        'success'
                    );
                } else {
                    show(
                        'Tidak ada pelanggan yang memenuhi batas suspend.',
                        'success'
                    );
                }
            } catch (error) {
                candidates = [];
                render();
                show(error.message, 'error');
            } finally {
                check.disabled = false;
            }
        }

        async function liveSuspend() {
            if (!candidates.length) {
                show('Tidak ada kandidat suspend.', 'error');
                return;
            }

            const days = Math.max(
                0,
                Math.min(30, Number(grace.value) || 0)
            );

            const names = candidates
                .slice(0, 5)
                .map(x => x.name + ' (' + x.pppoe_username + ')')
                .join('\n');

            const more = candidates.length > 5
                ? '\n... dan ' + (candidates.length - 5) + ' lainnya'
                : '';

            if (!confirm(
                'PERINGATAN — MODE LIVE\n\n' +
                'PPPoE yang akan dinonaktifkan:\n' +
                names + more +
                '\n\nLanjutkan?'
            )) {
                return;
            }

            check.disabled = true;
            live.disabled = true;
            show('Menjalankan suspend LIVE...', '');

            try {
                const response = await fetch(
                    API + '?action=execute&grace_days=' +
                    encodeURIComponent(days),
                    {
                        method: 'POST',
                        body: new URLSearchParams({ mode: 'live' })
                    }
                );

                const data = await response.json();

                if (!response.ok && response.status !== 207) {
                    throw new Error(data.message || 'Suspend LIVE gagal.');
                }

                badge.textContent = 'LIVE';
                badge.className = 'billing-s30-badge live';

                show(
                    (data.message || 'Proses selesai.') +
                    ' Berhasil: ' + (data.suspended || 0) +
                    ', sudah disabled: ' + (data.already_disabled || 0) +
                    ', error: ' + (data.errors || 0),
                    data.errors ? 'error' : 'success'
                );

                // Refresh candidate list after LIVE.
                await checkCandidates();
            } catch (error) {
                show(error.message, 'error');
                live.disabled = false;
                check.disabled = false;
            }
        }

        check.addEventListener('click', checkCandidates);
        live.addEventListener('click', liveSuspend);

        // First load = TEST/read-only.
        checkCandidates();
    }

    document.querySelectorAll('[data-billing-s30]').forEach(init);
})();
