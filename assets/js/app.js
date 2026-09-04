// Shared Network Monitor UI behavior.
// Global light/dark theme is persisted across every page.
(function () {
    const THEME_KEY = 'netmonitor_theme';

    function isDark() {
        return document.body.classList.contains('dark');
    }

    function applyTheme(theme) {
        const dark = theme === 'dark';
        document.body.classList.toggle('dark', dark);
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';

        document.querySelectorAll('[data-theme-toggle], #darkMode').forEach((button) => {
            const icon = button.querySelector('i');
            if (icon) icon.className = dark ? 'bi bi-sun' : 'bi bi-moon';
            button.setAttribute('aria-label', dark ? 'Aktifkan mode siang' : 'Aktifkan mode malam');
            button.setAttribute('title', dark ? 'Mode siang' : 'Mode malam');
        });
    }

    function toggleTheme() {
        const next = isDark() ? 'light' : 'dark';
        localStorage.setItem(THEME_KEY, next);
        applyTheme(next);
    }

    const saved = localStorage.getItem(THEME_KEY);
    document.addEventListener('DOMContentLoaded', () => applyTheme(
        saved === 'dark' || saved === 'light'
            ? saved
            : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    ), { once: true });

    document.addEventListener('DOMContentLoaded', () => {
        document.documentElement.classList.add('app-ready');

        let buttons = document.querySelectorAll('[data-theme-toggle], #darkMode');
        if (!buttons.length) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'global-theme-toggle';
            button.setAttribute('data-theme-toggle', '1');
            button.innerHTML = '<i class="bi bi-moon" aria-hidden="true"></i>';
            document.body.appendChild(button);
            buttons = document.querySelectorAll('[data-theme-toggle], #darkMode');
        }

        buttons.forEach((button) => {
            if (button.dataset.themeBound === '1') return;
            button.dataset.themeBound = '1';
            button.addEventListener('click', toggleTheme);
        });

        applyTheme(localStorage.getItem(THEME_KEY) || 'light');
        initTrafficHistory();
        initDashboardHistory();
    });

    function initTrafficHistory() {
        if (!window.location.pathname.toLowerCase().includes('/traffic/')) return;
        const canvas = document.getElementById('trafficChart');
        const config = window.TRAFFIC_HISTORY_CONFIG;
        if (!canvas || !config || typeof Chart === 'undefined') return;

        let currentRange = config.range || '24h';
        let currentFrom = config.from || '';
        let currentTo = config.to || '';
        let currentPage = 1;
        let currentPerPage = 25;
        let timer;
        let busy = false;
        let trafficChart = null;

        function createChart(labels = [], downloads = [], uploads = []) {
            if (trafficChart) trafficChart.destroy();
            trafficChart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'Download', data: downloads, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .35, pointRadius: 0, pointHoverRadius: 5, borderWidth: 3 },
                        { label: 'Upload', data: uploads, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.10)', fill: true, tension: .35, pointRadius: 0, pointHoverRadius: 5, borderWidth: 3 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { callbacks: { label: c => c.dataset.label + ' : ' + Number(c.parsed.y).toFixed(2) + ' Mbps' } }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, title: { display: true, text: 'Traffic (Mbps)' } }
                    }
                }
            });
        }

        function apiUrl(page = currentPage) {
            const params = new URLSearchParams();
            params.set('range', currentRange);
            params.set('page', page);
            params.set('per_page', currentPerPage);
            if (currentRange === 'custom' && currentFrom && currentTo) {
                params.set('from', currentFrom);
                params.set('to', currentTo);
            }
            return 'data.php?' + params.toString();
        }

        async function refreshTraffic(page = currentPage) {
            if (busy || document.visibilityState !== 'visible') return;
            busy = true;
            try {
                const response = await fetch(apiUrl(page), {
                    cache: 'no-store',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Traffic data error');

                currentPage = Number(result.page) || 1;
                updateStats(result.stats || {});
                updateTable(result.data || []);
                updatePagination(result.page, result.totalPages, result.total);
                updateRangeText(result.from, result.to, result.total);
                updateChart(result.labels || [], result.downloads || [], result.uploads || []);
                updateRangeButtons();
                updateExportLink();
            } catch (error) {
                console.warn('Traffic History refresh gagal:', error.message);
            } finally {
                busy = false;
            }
        }

        function updateStats(stats) {
            const records = document.getElementById('statRecords');
            const maxDownload = document.getElementById('statMaxDownload');
            const maxUpload = document.getElementById('statMaxUpload');
            const avgDownload = document.getElementById('statAvgDownload');
            if (records) records.textContent = Number(stats.records || 0).toLocaleString('id-ID');
            if (maxDownload) maxDownload.textContent = Number(stats.maxDownload || 0).toFixed(2) + ' Mbps';
            if (maxUpload) maxUpload.textContent = Number(stats.maxUpload || 0).toFixed(2) + ' Mbps';
            if (avgDownload) avgDownload.textContent = Number(stats.avgDownload || 0).toFixed(2) + ' Mbps';
        }

        function updateTable(rows) {
            const tbody = document.getElementById('trafficTableBody');
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted">Tidak ada data pada periode tersebut.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.created_at)}</td>
                    <td><span class="badge-interface">${escapeHtml(row.interface_name)}</span></td>
                    <td class="download">${Number(row.download_mbps || 0).toFixed(2)} Mbps</td>
                    <td class="upload">${Number(row.upload_mbps || 0).toFixed(2)} Mbps</td>
                    <td>${Number(row.rx_packet || 0).toLocaleString('id-ID')} pkt/s</td>
                    <td>${Number(row.tx_packet || 0).toLocaleString('id-ID')} pkt/s</td>
                    <td>${Number(row.cpu || 0).toFixed(1)} %</td>
                    <td>${Number(row.memory || 0).toFixed(1)} %</td>
                    <td>${Number(row.disk || 0).toFixed(1)} %</td>
                </tr>`).join('');
        }

        function updateChart(labels, downloads, uploads) {
            if (!trafficChart) {
                createChart(labels, downloads, uploads);
                return;
            }
            trafficChart.data.labels = labels;
            trafficChart.data.datasets[0].data = downloads;
            trafficChart.data.datasets[1].data = uploads;
            trafficChart.update('none');
        }

        function updatePagination(page, totalPages, total) {
            const info = document.getElementById('paginationInfo');
            const container = document.getElementById('paginationButtons');
            if (!container) return;
            const safeTotalPages = Math.max(1, Number(totalPages || 1));
            const safePage = Math.max(1, Number(page || 1));
            const safeTotal = Number(total || 0);
            const first = safeTotal ? ((safePage - 1) * currentPerPage) + 1 : 0;
            const last = Math.min(safePage * currentPerPage, safeTotal);
            if (info) info.textContent = safeTotal ? `Menampilkan ${first}–${last} dari ${safeTotal.toLocaleString('id-ID')} data` : '0 data';

            const buttons = [];
            buttons.push(`<button class="page-btn" data-page="${safePage - 1}" ${safePage <= 1 ? 'disabled' : ''} aria-label="Sebelumnya">‹</button>`);
            const start = Math.max(1, safePage - 2);
            const end = Math.min(safeTotalPages, safePage + 2);
            if (start > 1) buttons.push('<button class="page-btn" data-page="1">1</button>');
            if (start > 2) buttons.push('<span class="history-meta">…</span>');
            for (let i = start; i <= end; i++) buttons.push(`<button class="page-btn ${i === safePage ? 'active' : ''}" data-page="${i}">${i}</button>`);
            if (end < safeTotalPages - 1) buttons.push('<span class="history-meta">…</span>');
            if (end < safeTotalPages) buttons.push(`<button class="page-btn" data-page="${safeTotalPages}">${safeTotalPages}</button>`);
            buttons.push(`<button class="page-btn" data-page="${safePage + 1}" ${safePage >= safeTotalPages ? 'disabled' : ''} aria-label="Berikutnya">›</button>`);
            container.innerHTML = buttons.join('');
            container.querySelectorAll('.page-btn:not(:disabled)').forEach(btn => btn.addEventListener('click', () => refreshTraffic(Number(btn.dataset.page))));
        }

        function updateRangeButtons() {
            document.querySelectorAll('[data-range]').forEach(btn => btn.classList.toggle('active', btn.dataset.range === currentRange));
        }

        function updateRangeText(from, to, total) {
            const info = document.getElementById('rangeInfo');
            const chartPeriod = document.getElementById('chartPeriod');
            const text = `${formatDateTime(from)} sampai ${formatDateTime(to)} • ${Number(total || 0).toLocaleString('id-ID')} record`;
            if (info) info.textContent = text;
            if (chartPeriod) chartPeriod.textContent = text;
        }

        function updateExportLink() {
            const link = document.getElementById('exportTraffic');
            if (!link) return;
            const params = new URLSearchParams();
            params.set('range', currentRange);
            if (currentRange === 'custom') {
                params.set('from', currentFrom);
                params.set('to', currentTo);
            }
            link.href = 'export.php?' + params.toString();
        }

        function setPreset(range) {
            currentRange = range;
            currentPage = 1;
            refreshTraffic(1);
        }

        function setCustomRange(start, end) {
            currentRange = 'custom';
            currentFrom = start + ' 00:00:00';
            currentTo = end + ' 23:59:59';
            currentPage = 1;
            refreshTraffic(1);
        }

        function formatDateTime(value) {
            if (!value) return '-';
            const d = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' })[c]);
        }

        document.querySelectorAll('[data-range]').forEach(btn => btn.addEventListener('click', () => setPreset(btn.dataset.range)));

        const perPage = document.getElementById('perPageSelect');
        if (perPage) perPage.addEventListener('change', () => {
            currentPerPage = Number(perPage.value) || 25;
            currentPage = 1;
            refreshTraffic(1);
        });

        const form = document.getElementById('historyFilterForm');
        if (form) form.addEventListener('submit', event => {
            event.preventDefault();
            const start = document.getElementById('historyStart')?.value;
            const end = document.getElementById('historyEnd')?.value;
            if (start && end) setCustomRange(start, end);
        });

        createChart([], [], []);
        refreshTraffic(1);

        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                await refreshTraffic(currentPage);
                schedule();
            }, 10000);
        }

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                refreshTraffic(currentPage);
                schedule();
            } else {
                clearTimeout(timer);
            }
        });

        updateRangeButtons();
        schedule();
    }

    // Dashboard chart/statistics are sourced from traffic_history so they do
    // not reset when the page is reloaded and stay consistent with History.
    function initDashboardHistory() {
        if (!window.location.pathname.toLowerCase().includes('/dashboard/')) return;
        if (typeof Chart === 'undefined' || !document.getElementById('trafficChart')) return;

        let timer = null;
        let busy = false;

        async function refreshDashboardHistory() {
            if (busy || document.visibilityState !== 'visible') return;
            busy = true;

            try {
                const response = await fetch('../api/dashboard_history.php?nocache=' + Date.now(), {
                    cache: 'no-store',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) throw new Error('HTTP ' + response.status);
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Dashboard history error');

                if (typeof labels !== 'undefined' && typeof downloadData !== 'undefined' && typeof uploadData !== 'undefined') {
                    labels.splice(0, labels.length, ...(result.labels || []));
                    downloadData.splice(0, downloadData.length, ...(result.downloads || []));
                    uploadData.splice(0, uploadData.length, ...(result.uploads || []));
                }

                if (typeof trafficChart !== 'undefined' && trafficChart) {
                    trafficChart.data.labels = result.labels || [];
                    trafficChart.data.datasets[0].data = result.downloads || [];
                    trafficChart.data.datasets[1].data = result.uploads || [];
                    trafficChart.update('none');
                }

                const peakDownloadElement = document.getElementById('peakDownload');
                const peakUploadElement = document.getElementById('peakUpload');
                if (peakDownloadElement) peakDownloadElement.textContent = Number(result.peak_download || 0).toFixed(2) + ' Mbps';
                if (peakUploadElement) peakUploadElement.textContent = Number(result.peak_upload || 0).toFixed(2) + ' Mbps';
            } catch (error) {
                console.warn('Dashboard history sync gagal:', error.message);
            } finally {
                busy = false;
            }
        }

        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                await refreshDashboardHistory();
                schedule();
            }, 10000);
        }

        // Give the live dashboard request a moment to populate first, then
        // replace the chart with the persisted collector samples.
        setTimeout(() => {
            refreshDashboardHistory();
            schedule();
        }, 500);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                refreshDashboardHistory();
                schedule();
            } else {
                clearTimeout(timer);
            }
        });
    }
})();
