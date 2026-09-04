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
    if (saved === 'dark' || saved === 'light') {
        document.addEventListener('DOMContentLoaded', () => applyTheme(saved), { once: true });
    } else {
        const preferred = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.addEventListener('DOMContentLoaded', () => applyTheme(preferred), { once: true });
    }

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
        initTrafficHistoryLiveRefresh();
    });

    function initTrafficHistoryLiveRefresh() {
        if (!window.location.pathname.toLowerCase().includes('/traffic/')) return;
        if (!document.getElementById('trafficChart')) return;

        const INTERVAL = 10000;
        let timer;
        let busy = false;

        async function refreshTraffic() {
            if (busy || document.visibilityState !== 'visible') return;
            busy = true;

            try {
                const params = new URLSearchParams(window.location.search);
                const start = params.get('start') || new Date().toISOString().slice(0, 10);
                const end = params.get('end') || start;
                const response = await fetch('data.php?start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end), {
                    cache: 'no-store',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Traffic data error');

                updateTrafficStats(result.stats);
                updateTrafficTable(result.data || []);

                const chart = Object.values(Chart.instances || {}).find(c => c.canvas && c.canvas.id === 'trafficChart');
                if (chart) {
                    chart.data.labels = result.labels || [];
                    if (chart.data.datasets[0]) chart.data.datasets[0].data = result.downloads || [];
                    if (chart.data.datasets[1]) chart.data.datasets[1].data = result.uploads || [];
                    chart.update('none');
                }
            } catch (error) {
                console.warn('Traffic History refresh gagal:', error.message);
            } finally {
                busy = false;
            }
        }

        function updateTrafficStats(stats) {
            const values = document.querySelectorAll('.stat-value');
            if (values[0]) values[0].textContent = Number(stats.records || 0).toLocaleString('en-US');
            if (values[1]) values[1].textContent = Number(stats.maxDownload || 0).toFixed(2) + ' Mbps';
            if (values[2]) values[2].textContent = Number(stats.maxUpload || 0).toFixed(2) + ' Mbps';
            if (values[3]) values[3].textContent = Number(stats.avgDownload || 0).toFixed(2) + ' Mbps';
        }

        function updateTrafficTable(rows) {
            const tbody = document.querySelector('.table-modern tbody');
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
                    <td>${Number(row.rx_packet || 0).toLocaleString('en-US')} pkt/s</td>
                    <td>${Number(row.tx_packet || 0).toLocaleString('en-US')} pkt/s</td>
                    <td>${Number(row.cpu || 0).toFixed(1)} %</td>
                    <td>${Number(row.memory || 0).toFixed(1)} %</td>
                    <td>${Number(row.disk || 0).toFixed(1)} %</td>
                </tr>`).join('');
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' })[c]);
        }

        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                await refreshTraffic();
                schedule();
            }, INTERVAL);
        }

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                refreshTraffic();
                schedule();
            } else {
                clearTimeout(timer);
            }
        });

        schedule();
    }
})();
