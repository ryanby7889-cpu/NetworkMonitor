// Shared Network Monitor UI behavior.
// Global light/dark theme + Traffic History + Dashboard PRO v3.
(function () {
    const THEME_KEY = 'netmonitor_theme';

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
        const next = document.body.classList.contains('dark') ? 'light' : 'dark';
        localStorage.setItem(THEME_KEY, next);
        applyTheme(next);
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
        const saved = localStorage.getItem(THEME_KEY);
        applyTheme(saved === 'dark' || saved === 'light' ? saved : 'light');
        initTrafficHistory();
        initDashboardProV3();
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
        let timer = null;
        let busy = false;
        let trafficChart = null;

        function createChart(labels = [], downloads = [], uploads = []) {
            if (trafficChart) trafficChart.destroy();
            trafficChart = new Chart(canvas, {
                type: 'line',
                data: { labels, datasets: [
                    { label: 'Download', data: downloads, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .35, pointRadius: 0, pointHoverRadius: 5, borderWidth: 3 },
                    { label: 'Upload', data: uploads, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.10)', fill: true, tension: .35, pointRadius: 0, pointHoverRadius: 5, borderWidth: 3 }
                ]},
                options: { responsive: true, maintainAspectRatio: false, animation: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: c => c.dataset.label + ' : ' + Number(c.parsed.y).toFixed(2) + ' Mbps' } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, title: { display: true, text: 'Traffic (Mbps)' } } } }
            });
        }
        function apiUrl(page = currentPage) {
            const p = new URLSearchParams({ range: currentRange, page, per_page: currentPerPage });
            if (currentRange === 'custom' && currentFrom && currentTo) { p.set('from', currentFrom); p.set('to', currentTo); }
            return 'data.php?' + p.toString();
        }
        async function refreshTraffic(page = currentPage) {
            if (busy || document.visibilityState !== 'visible') return;
            busy = true;
            try {
                const r = await fetch(apiUrl(page), { cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const result = await r.json();
                if (!result.success) throw new Error(result.message || 'Traffic data error');
                currentPage = Number(result.page) || 1;
                updateStats(result.stats || {});
                updateTable(result.data || []);
                updatePagination(result.page, result.totalPages, result.total);
                updateRangeText(result.from, result.to, result.total);
                updateChart(result.labels || [], result.downloads || [], result.uploads || []);
                updateRangeButtons();
                updateExportLink();
            } catch (e) { console.warn('Traffic History refresh gagal:', e.message); }
            finally { busy = false; }
        }
        function updateStats(s) {
            const ids = { records: 'statRecords', maxDownload: 'statMaxDownload', maxUpload: 'statMaxUpload', avgDownload: 'statAvgDownload' };
            if (document.getElementById(ids.records)) document.getElementById(ids.records).textContent = Number(s.records || 0).toLocaleString('id-ID');
            if (document.getElementById(ids.maxDownload)) document.getElementById(ids.maxDownload).textContent = Number(s.maxDownload || 0).toFixed(2) + ' Mbps';
            if (document.getElementById(ids.maxUpload)) document.getElementById(ids.maxUpload).textContent = Number(s.maxUpload || 0).toFixed(2) + ' Mbps';
            if (document.getElementById(ids.avgDownload)) document.getElementById(ids.avgDownload).textContent = Number(s.avgDownload || 0).toFixed(2) + ' Mbps';
        }
        function updateTable(rows) {
            const tbody = document.getElementById('trafficTableBody'); if (!tbody) return;
            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted">Tidak ada data pada periode tersebut.</td></tr>'; return; }
            tbody.innerHTML = rows.map(row => `<tr><td>${esc(row.created_at)}</td><td><span class="badge-interface">${esc(row.interface_name)}</span></td><td class="download">${Number(row.download_mbps || 0).toFixed(2)} Mbps</td><td class="upload">${Number(row.upload_mbps || 0).toFixed(2)} Mbps</td><td>${Number(row.rx_packet || 0).toLocaleString('id-ID')} pkt/s</td><td>${Number(row.tx_packet || 0).toLocaleString('id-ID')} pkt/s</td><td>${Number(row.cpu || 0).toFixed(1)} %</td><td>${Number(row.memory || 0).toFixed(1)} %</td><td>${Number(row.disk || 0).toFixed(1)} %</td></tr>`).join('');
        }
        function updateChart(labels, downloads, uploads) {
            if (!trafficChart) { createChart(labels, downloads, uploads); return; }
            trafficChart.data.labels = labels; trafficChart.data.datasets[0].data = downloads; trafficChart.data.datasets[1].data = uploads; trafficChart.update('none');
        }
        function updatePagination(page, totalPages, total) {
            const info = document.getElementById('paginationInfo'), container = document.getElementById('paginationButtons'); if (!container) return;
            const tp = Math.max(1, Number(totalPages || 1)), pg = Math.max(1, Number(page || 1)), t = Number(total || 0);
            if (info) info.textContent = t ? `Menampilkan ${((pg - 1) * currentPerPage) + 1}–${Math.min(pg * currentPerPage, t)} dari ${t.toLocaleString('id-ID')} data` : '0 data';
            const b = [`<button class="page-btn" data-page="${pg - 1}" ${pg <= 1 ? 'disabled' : ''}>‹</button>`];
            const start = Math.max(1, pg - 2), end = Math.min(tp, pg + 2);
            if (start > 1) b.push('<button class="page-btn" data-page="1">1</button>'); if (start > 2) b.push('<span class="history-meta">…</span>');
            for (let i = start; i <= end; i++) b.push(`<button class="page-btn ${i === pg ? 'active' : ''}" data-page="${i}">${i}</button>`);
            if (end < tp - 1) b.push('<span class="history-meta">…</span>'); if (end < tp) b.push(`<button class="page-btn" data-page="${tp}">${tp}</button>`);
            b.push(`<button class="page-btn" data-page="${pg + 1}" ${pg >= tp ? 'disabled' : ''}>›</button>`);
            container.innerHTML = b.join(''); container.querySelectorAll('.page-btn:not(:disabled)').forEach(x => x.addEventListener('click', () => refreshTraffic(Number(x.dataset.page))));
        }
        function updateRangeButtons() { document.querySelectorAll('[data-range]').forEach(b => b.classList.toggle('active', b.dataset.range === currentRange)); }
        function updateRangeText(from, to, total) { const text = `${formatDateTime(from)} sampai ${formatDateTime(to)} • ${Number(total || 0).toLocaleString('id-ID')} record`; const a = document.getElementById('rangeInfo'), c = document.getElementById('chartPeriod'); if (a) a.textContent = text; if (c) c.textContent = text; }
        function updateExportLink() { const link = document.getElementById('exportTraffic'); if (!link) return; const p = new URLSearchParams({ range: currentRange }); if (currentRange === 'custom') { p.set('from', currentFrom); p.set('to', currentTo); } link.href = 'export.php?' + p.toString(); }
        function formatDateTime(v) { if (!v) return '-'; const d = new Date(String(v).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? v : d.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
        function esc(v) { return String(v ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' })[c]); }
        document.querySelectorAll('[data-range]').forEach(b => b.addEventListener('click', () => { currentRange = b.dataset.range; currentPage = 1; refreshTraffic(1); }));
        const pp = document.getElementById('perPageSelect'); if (pp) pp.addEventListener('change', () => { currentPerPage = Number(pp.value) || 25; currentPage = 1; refreshTraffic(1); });
        const form = document.getElementById('historyFilterForm'); if (form) form.addEventListener('submit', e => { e.preventDefault(); const s = document.getElementById('historyStart')?.value, t = document.getElementById('historyEnd')?.value; if (s && t) { currentRange = 'custom'; currentFrom = s + ' 00:00:00'; currentTo = t + ' 23:59:59'; currentPage = 1; refreshTraffic(1); } });
        createChart([], [], []); refreshTraffic(1);
        function schedule() { clearTimeout(timer); timer = setTimeout(async () => { await refreshTraffic(currentPage); schedule(); }, 10000); }
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') { refreshTraffic(currentPage); schedule(); } else clearTimeout(timer); });
        schedule();
    }

    function initDashboardProV3() {
        if (!window.location.pathname.toLowerCase().includes('/dashboard/')) return;
        const canvas = document.getElementById('trafficChart');
        if (!canvas || typeof Chart === 'undefined') return;

        const style = document.createElement('style');
        style.id = 'dashboardProV3Style';
        style.textContent = `
            .dashboard-pro-v3{margin-top:1rem}.dashboard-pro-v3 .range-toolbar{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:14px}.dashboard-pro-v3 .range-btn{border:1px solid var(--border-color,#dbe3ef);background:var(--card-bg,#fff);color:var(--text-color,#334155);border-radius:9px;padding:7px 12px;font-size:.78rem;font-weight:700;cursor:pointer}.dashboard-pro-v3 .range-btn.active{background:#2563eb;color:#fff;border-color:#2563eb}.dashboard-pro-v3 .collector{margin-left:auto;font-size:.76rem;font-weight:700;padding:7px 11px;border-radius:999px;background:#f1f5f9;color:#475569}.dashboard-pro-v3 .collector.healthy{background:#dcfce7;color:#166534}.dashboard-pro-v3 .collector.delayed{background:#fef3c7;color:#92400e}.dashboard-pro-v3 .collector.offline{background:#fee2e2;color:#991b1b}.dashboard-pro-v3 .pro-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px;margin-top:14px}.dashboard-pro-v3 .pro-card{border:1px solid var(--border-color,#e5e7eb);border-radius:16px;padding:16px 18px;background:var(--card-bg,#fff);box-shadow:0 8px 24px rgba(15,23,42,.05)}.dashboard-pro-v3 .pro-label{font-size:.69rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#64748b;margin-bottom:7px}.dashboard-pro-v3 .pro-value{font-size:1.25rem;font-weight:800;line-height:1.15;color:#0f172a}.dashboard-pro-v3 .pro-meta{margin-top:6px;font-size:.7rem;color:#64748b}.dashboard-pro-v3 .current .pro-value{color:#2563eb}.dashboard-pro-v3 .avg .pro-value{color:#0f766e}.dashboard-pro-v3 .peak .pro-value{color:#dc2626}body.dark .dashboard-pro-v3 .pro-card{background:#111827;border-color:#253044;box-shadow:0 8px 24px rgba(0,0,0,.18)}body.dark .dashboard-pro-v3 .range-btn{background:#111827;border-color:#334155;color:#cbd5e1}body.dark .dashboard-pro-v3 .collector{background:#1e293b;color:#cbd5e1}
            @media(max-width:1200px){.dashboard-pro-v3 .pro-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:700px){.dashboard-pro-v3 .pro-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dashboard-pro-v3 .collector{margin-left:0;width:100%}}
        `;
        document.head.appendChild(style);

        const card = canvas.closest('.monitor-card') || canvas.parentElement;
        const wrap = document.createElement('div');
        wrap.id = 'dashboardProV3';
        wrap.className = 'dashboard-pro-v3';
        wrap.innerHTML = `
            <div class="range-toolbar">
                <button class="range-btn active" data-dashboard-range="10m">10 Menit</button>
                <button class="range-btn" data-dashboard-range="1h">1 Jam</button>
                <button class="range-btn" data-dashboard-range="6h">6 Jam</button>
                <button class="range-btn" data-dashboard-range="24h">24 Jam</button>
                <span class="collector" id="dashboardCollector"><i class="bi bi-activity"></i> Collector: Checking...</span>
            </div>
            <div class="pro-grid">
                <div class="pro-card current"><div class="pro-label">Current Download</div><div class="pro-value" id="proCurrentDownload">0.00 Mbps</div><div class="pro-meta">Live ether1</div></div>
                <div class="pro-card current"><div class="pro-label">Current Upload</div><div class="pro-value" id="proCurrentUpload">0.00 Mbps</div><div class="pro-meta">Live ether1</div></div>
                <div class="pro-card avg"><div class="pro-label">Average Download</div><div class="pro-value" id="proAvgDownload">0.00 Mbps</div><div class="pro-meta" id="proAvgPeriod">Rolling 1 jam</div></div>
                <div class="pro-card avg"><div class="pro-label">Average Upload</div><div class="pro-value" id="proAvgUpload">0.00 Mbps</div><div class="pro-meta" id="proAvgPeriod2">Rolling 1 jam</div></div>
                <div class="pro-card peak"><div class="pro-label">Peak Download</div><div class="pro-value" id="proPeakDownload">0.00 Mbps</div><div class="pro-meta" id="proPeakPeriod">Periode aktif</div></div>
                <div class="pro-card peak"><div class="pro-label">Peak Upload</div><div class="pro-value" id="proPeakUpload">0.00 Mbps</div><div class="pro-meta" id="proPeakPeriod2">Periode aktif</div></div>
            </div>`;
        card.insertAdjacentElement('afterend', wrap);

        let range = '10m', timer = null, busy = false, chart = null;
        const colors = { download: '#0d6efd', upload: '#20c997' };

        // Reuse the dashboard's existing Chart instance when available.
        try { if (typeof trafficChart !== 'undefined') chart = trafficChart; } catch (_) {}
        if (!chart) {
            chart = new Chart(canvas.getContext('2d'), { type:'line', data:{labels:[],datasets:[{label:'Download',data:[],borderColor:colors.download,backgroundColor:'rgba(13,110,253,.12)',fill:true,tension:.4,pointRadius:0},{label:'Upload',data:[],borderColor:colors.upload,backgroundColor:'rgba(32,201,151,.10)',fill:true,tension:.4,pointRadius:0}]}, options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false}} });
        }

        function setRange(next) { range = next; wrap.querySelectorAll('.range-btn').forEach(b => b.classList.toggle('active', b.dataset.dashboardRange === range)); refresh(); }
        function currentValues() {
            const d = document.getElementById('download')?.textContent || '0';
            const u = document.getElementById('upload')?.textContent || '0';
            return { download: parseFloat(d.replace(',', '.')) || 0, upload: parseFloat(u.replace(',', '.')) || 0 };
        }
        async function refresh() {
            if (busy || document.visibilityState !== 'visible') return;
            busy = true;
            try {
                const r = await fetch('../api/dashboard_history.php?range=' + encodeURIComponent(range) + '&nocache=' + Date.now(), { cache:'no-store', headers:{'X-Requested-With':'XMLHttpRequest'} });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const result = await r.json(); if (!result.success) throw new Error(result.message || 'Dashboard history error');
                chart.data.labels = result.labels || [];
                chart.data.datasets[0].data = result.downloads || [];
                chart.data.datasets[1].data = result.uploads || [];
                chart.update('none');
                const live = currentValues();
                document.getElementById('proCurrentDownload').textContent = live.download.toFixed(2) + ' Mbps';
                document.getElementById('proCurrentUpload').textContent = live.upload.toFixed(2) + ' Mbps';
                document.getElementById('proAvgDownload').textContent = Number(result.avg_download || 0).toFixed(2) + ' Mbps';
                document.getElementById('proAvgUpload').textContent = Number(result.avg_upload || 0).toFixed(2) + ' Mbps';
                document.getElementById('proPeakDownload').textContent = Number(result.peak_download || 0).toFixed(2) + ' Mbps';
                document.getElementById('proPeakUpload').textContent = Number(result.peak_upload || 0).toFixed(2) + ' Mbps';
                const labels = { '10m':'10 menit', '1h':'1 jam', '6h':'6 jam', '24h':'24 jam' };
                document.getElementById('proAvgPeriod').textContent = 'Rata-rata ' + labels[range];
                document.getElementById('proAvgPeriod2').textContent = 'Rata-rata ' + labels[range];
                document.getElementById('proPeakPeriod').textContent = 'Maksimum ' + labels[range];
                document.getElementById('proPeakPeriod2').textContent = 'Maksimum ' + labels[range];
                const c = document.getElementById('dashboardCollector');
                const status = String(result.collector_status || 'OFFLINE').toLowerCase();
                c.className = 'collector ' + status;
                const age = result.collector_age === null || result.collector_age === undefined ? '-' : result.collector_age + ' dtk lalu';
                c.innerHTML = '<i class="bi bi-activity"></i> Collector: ' + String(result.collector_status || 'OFFLINE') + ' • ' + age;
            } catch (e) { console.warn('Dashboard PRO v3 refresh gagal:', e.message); }
            finally { busy = false; }
        }
        wrap.querySelectorAll('.range-btn').forEach(b => b.addEventListener('click', () => setRange(b.dataset.dashboardRange)));
        refresh();
        function schedule() { clearTimeout(timer); timer = setTimeout(async () => { await refresh(); schedule(); }, 10000); }
        schedule();
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') { refresh(); schedule(); } else clearTimeout(timer); });
    }
})();
