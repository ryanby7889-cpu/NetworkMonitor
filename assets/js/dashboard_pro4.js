// Network Monitor Dashboard PRO v4 enhancements.
(function () {
    function init() {
        if (!window.location.pathname.toLowerCase().includes('/dashboard/')) return;
        const canvas = document.getElementById('trafficChart');
        if (!canvas || typeof Chart === 'undefined') return;
        if (document.getElementById('dashboardProV4')) return;

        const chartCard = canvas.closest('.chart-card') || canvas.closest('.monitor-card');
        if (!chartCard) return;

        const style = document.createElement('style');
        style.id = 'dashboardProV4Style';
        style.textContent = `
            .dashboard-pro-v4{margin:0 0 14px}.dashboard-pro-v4 .v4-toolbar{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.dashboard-pro-v4 .v4-range{border:1px solid var(--border-color,#dbe3ef);background:var(--card-bg,#fff);color:var(--text-color,#334155);border-radius:9px;padding:6px 11px;font-size:.75rem;font-weight:800;cursor:pointer}.dashboard-pro-v4 .v4-range.active{background:#2563eb;color:#fff;border-color:#2563eb}.dashboard-pro-v4 .v4-refresh{margin-left:auto;font-size:.72rem;color:#64748b}.dashboard-pro-v4 .v4-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:11px}.dashboard-pro-v4 .v4-box{border:1px solid var(--border-color,#e5e7eb);border-radius:12px;padding:11px 13px;background:var(--card-bg,#fff)}.dashboard-pro-v4 .v4-label{font-size:.64rem;text-transform:uppercase;letter-spacing:.04em;font-weight:800;color:#64748b}.dashboard-pro-v4 .v4-value{font-size:1rem;font-weight:800;margin-top:3px;color:#0f172a}.dashboard-pro-v4 .v4-meter{height:5px;background:#e2e8f0;border-radius:99px;overflow:hidden;margin-top:7px}.dashboard-pro-v4 .v4-meter>span{display:block;height:100%;width:0;background:#2563eb;border-radius:99px;transition:width .3s ease}.dashboard-pro-v4 .healthy{color:#15803d}.dashboard-pro-v4 .delayed{color:#b45309}.dashboard-pro-v4 .offline{color:#dc2626}body.dark .dashboard-pro-v4 .v4-range,body.dark .dashboard-pro-v4 .v4-box{background:#111827;border-color:#253044;color:#cbd5e1}body.dark .dashboard-pro-v4 .v4-meter{background:#334155}body.dark .dashboard-pro-v4 .v4-value{color:#f8fafc}@media(max-width:900px){.dashboard-pro-v4 .v4-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.dashboard-pro-v4 .v4-grid{grid-template-columns:1fr}.dashboard-pro-v4 .v4-refresh{margin-left:0;width:100%}}
        `;
        document.head.appendChild(style);

        const panel = document.createElement('div');
        panel.id = 'dashboardProV4';
        panel.className = 'dashboard-pro-v4';
        panel.innerHTML = `
            <div class="v4-toolbar">
                <span style="font-size:.72rem;font-weight:800;color:#64748b;margin-right:2px">PERIODE</span>
                <button class="v4-range active" data-v4-range="10m">10M</button>
                <button class="v4-range" data-v4-range="1h">1H</button>
                <button class="v4-range" data-v4-range="6h">6H</button>
                <button class="v4-range" data-v4-range="24h">24H</button>
                <span class="v4-refresh" id="v4RefreshInfo">Menunggu data...</span>
            </div>
            <div class="v4-grid">
                <div class="v4-box"><div class="v4-label">Peak Download</div><div class="v4-value" id="v4PeakDown">0.00 Mbps</div><div class="v4-meter"><span id="v4DownMeter"></span></div></div>
                <div class="v4-box"><div class="v4-label">Peak Upload</div><div class="v4-value" id="v4PeakUp">0.00 Mbps</div><div class="v4-meter"><span id="v4UpMeter"></span></div></div>
                <div class="v4-box"><div class="v4-label">Collector</div><div class="v4-value" id="v4Collector">Checking...</div><div class="v4-label" id="v4CollectorAge">Sample: -</div></div>
                <div class="v4-box"><div class="v4-label">Data Coverage</div><div class="v4-value" id="v4Coverage">0 points</div><div class="v4-label" id="v4Records">0 records</div></div>
            </div>`;
        chartCard.insertBefore(panel, chartCard.querySelector('.chart-container'));

        let range = '10m';
        let timer = null;
        let busy = false;

        function setActive() {
            panel.querySelectorAll('[data-v4-range]').forEach(b => b.classList.toggle('active', b.dataset.v4Range === range));
        }

        function setText(id, value) {
            const el = document.getElementById(id); if (el) el.textContent = value;
        }

        function formatAge(age) {
            if (age === null || age === undefined || Number.isNaN(Number(age))) return '-';
            const n = Math.max(0, Math.round(Number(age)));
            if (n < 60) return n + ' detik';
            return Math.floor(n / 60) + 'm ' + (n % 60) + 's';
        }

        function updateChart(result) {
            const chart = Chart.getChart(canvas);
            if (!chart) return;
            chart.data.labels = result.labels || [];
            if (chart.data.datasets[0]) chart.data.datasets[0].data = result.downloads || [];
            if (chart.data.datasets[1]) chart.data.datasets[1].data = result.uploads || [];
            chart.update('none');
        }

        async function refresh() {
            if (busy || document.visibilityState !== 'visible') return;
            busy = true;
            try {
                const r = await fetch('../api/dashboard_history.php?range=' + encodeURIComponent(range) + '&nocache=' + Date.now(), { cache: 'no-store', headers: {'X-Requested-With':'XMLHttpRequest'} });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const result = await r.json();
                if (!result.success) throw new Error(result.message || 'Dashboard history error');
                updateChart(result);

                const peakDown = Number(result.peak_download || 0);
                const peakUp = Number(result.peak_upload || 0);
                const avgDown = Number(result.avg_download || 0);
                const avgUp = Number(result.avg_upload || 0);
                setText('v4PeakDown', peakDown.toFixed(2) + ' Mbps');
                setText('v4PeakUp', peakUp.toFixed(2) + ' Mbps');
                setText('v4Coverage', Number(result.points || 0).toLocaleString('id-ID') + ' points');
                setText('v4Records', Number(result.records || 0).toLocaleString('id-ID') + ' records');
                setText('v4CollectorAge', 'Sample: ' + formatAge(result.collector_age) + ' lalu');

                const status = String(result.collector_status || 'OFFLINE').toUpperCase();
                const collector = document.getElementById('v4Collector');
                if (collector) { collector.textContent = status; collector.className = 'v4-value ' + status.toLowerCase(); }

                const currentDown = Number(document.getElementById('download')?.textContent.replace(/[^0-9.]/g, '') || 0);
                const currentUp = Number(document.getElementById('upload')?.textContent.replace(/[^0-9.]/g, '') || 0);
                setText('v4RefreshInfo', 'Avg ' + avgDown.toFixed(2) + ' / ' + avgUp.toFixed(2) + ' Mbps • update 10s');
                const downPct = peakDown > 0 ? Math.min(100, currentDown / peakDown * 100) : 0;
                const upPct = peakUp > 0 ? Math.min(100, currentUp / peakUp * 100) : 0;
                const dm = document.getElementById('v4DownMeter'), um = document.getElementById('v4UpMeter');
                if (dm) dm.style.width = downPct.toFixed(1) + '%';
                if (um) um.style.width = upPct.toFixed(1) + '%';
            } catch (e) {
                setText('v4RefreshInfo', 'Gagal refresh: ' + e.message);
            } finally { busy = false; }
        }

        panel.querySelectorAll('[data-v4-range]').forEach(btn => btn.addEventListener('click', () => { range = btn.dataset.v4Range; setActive(); refresh(); }));
        function schedule() { clearTimeout(timer); timer = setTimeout(async () => { await refresh(); schedule(); }, 10000); }
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') { refresh(); schedule(); } else clearTimeout(timer); });
        refresh();
        schedule();
    }

    document.addEventListener('DOMContentLoaded', () => setTimeout(init, 100));
})();
