/* NetMonitor Global Alarm Notification */
(function () {
    'use strict';

    const API_URL = '../api/alarm_status.php';
    const POLL_MS = 10000;
    const STORAGE_KEY = 'netmonitor_last_alarm_id';
    let previousCritical = null;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, function (char) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[char];
        });
    }

    function injectStyles() {
        if (document.getElementById('netAlarmNotificationStyle')) return;
        const style = document.createElement('style');
        style.id = 'netAlarmNotificationStyle';
        style.textContent = `
            .net-alarm-notify{position:fixed;top:16px;right:76px;z-index:1080}
            .net-alarm-btn{position:relative;width:42px;height:42px;border:1px solid var(--border,#e2e8f0);border-radius:50%;background:var(--card,#fff);color:var(--text,#334155);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(0,0,0,.10);cursor:pointer;transition:.2s}
            .net-alarm-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,0,0,.15)}
            .net-alarm-btn i{font-size:19px}
            .net-alarm-btn.has-warning{color:#d97706;border-color:#fbbf24}
            .net-alarm-btn.has-critical{color:#dc2626;border-color:#f87171;animation:netAlarmPulse 1.4s infinite}
            .net-alarm-badge{position:absolute;top:-5px;right:-5px;min-width:20px;height:20px;padding:0 5px;border-radius:10px;background:#dc2626;color:#fff;font-size:10px;font-weight:800;line-height:20px;text-align:center;border:2px solid var(--card,#fff)}
            .net-alarm-pop{position:absolute;right:0;top:50px;width:330px;max-width:calc(100vw - 24px);background:var(--card,#fff);border:1px solid var(--border,#e2e8f0);border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.16);padding:12px;display:none;color:var(--text,#0f172a)}
            .net-alarm-pop.show{display:block}
            .net-alarm-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:9px}
            .net-alarm-head strong{font-size:13px}.net-alarm-head span{font-size:9px;color:var(--muted,#64748b)}
            .net-alarm-item{padding:8px 9px;border:1px solid var(--border,#e2e8f0);border-radius:8px;margin-bottom:6px;font-size:10px}
            .net-alarm-item:last-child{margin-bottom:0}.net-alarm-item.critical{border-left:3px solid #dc2626}.net-alarm-item.warning{border-left:3px solid #f59e0b}
            .net-alarm-item b{display:block;font-size:10px}.net-alarm-item small{display:block;color:var(--muted,#64748b);margin-top:2px;line-height:1.35}
            .net-alarm-open{display:block;width:100%;margin-top:9px;border:0;border-radius:8px;padding:8px;background:#2563eb;color:#fff;font-size:10px;font-weight:700;cursor:pointer}
            .net-alarm-empty{font-size:10px;color:var(--muted,#64748b);padding:8px 2px}
            @keyframes netAlarmPulse{0%,100%{box-shadow:0 4px 14px rgba(0,0,0,.10)}50%{box-shadow:0 0 0 7px rgba(220,38,38,.12),0 6px 18px rgba(220,38,38,.18)}}
            @media(max-width:600px){.net-alarm-notify{right:64px}.net-alarm-pop{right:-45px}}
        `;
        document.head.appendChild(style);
    }

    function ensureUI() {
        let root = document.getElementById('netAlarmNotification');
        if (root) return root;

        root = document.createElement('div');
        root.id = 'netAlarmNotification';
        root.className = 'net-alarm-notify';
        root.innerHTML = `
            <button type="button" class="net-alarm-btn" id="netAlarmButton" title="Alarm aktif" aria-label="Alarm aktif">
                <i class="bi bi-bell"></i>
                <span class="net-alarm-badge" id="netAlarmBadge" hidden>0</span>
            </button>
            <div class="net-alarm-pop" id="netAlarmPopup" role="status" aria-live="polite">
                <div class="net-alarm-head"><strong>Alarm Aktif</strong><span id="netAlarmSummary">Normal</span></div>
                <div id="netAlarmItems" class="net-alarm-empty">Memuat status alarm...</div>
                <button type="button" class="net-alarm-open" id="netAlarmOpen">Buka Alarm Monitoring</button>
            </div>`;
        document.body.appendChild(root);

        document.getElementById('netAlarmButton').addEventListener('click', function (event) {
            event.stopPropagation();
            document.getElementById('netAlarmPopup').classList.toggle('show');
        });
        document.getElementById('netAlarmOpen').addEventListener('click', function () {
            window.location.href = '../alarm/index.php';
        });
        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) document.getElementById('netAlarmPopup').classList.remove('show');
        });
        return root;
    }

    function formatValue(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n.toFixed(2) + ' Mbps' : '-';
    }

    function render(data) {
        const button = document.getElementById('netAlarmButton');
        const badge = document.getElementById('netAlarmBadge');
        const summary = document.getElementById('netAlarmSummary');
        const items = document.getElementById('netAlarmItems');
        if (!button || !badge || !summary || !items) return;

        const active = Number(data.active || 0);
        const critical = Number(data.critical || 0);
        const warning = Number(data.warning || 0);
        const alarms = Array.isArray(data.alarms) ? data.alarms : [];

        button.classList.toggle('has-critical', critical > 0);
        button.classList.toggle('has-warning', critical === 0 && warning > 0);
        badge.hidden = active <= 0;
        badge.textContent = active > 99 ? '99+' : String(active);
        button.title = active > 0 ? `Alarm aktif: ${active} (Critical ${critical}, Warning ${warning})` : 'Tidak ada alarm aktif';
        button.setAttribute('aria-label', button.title);
        summary.textContent = active > 0 ? `${active} aktif • ${critical} critical • ${warning} warning` : 'Normal';

        if (!alarms.length) {
            items.className = 'net-alarm-empty';
            items.textContent = 'Tidak ada alarm aktif.';
        } else {
            items.className = '';
            items.innerHTML = alarms.slice(0, 5).map(function (alarm) {
                const severity = String(alarm.severity || 'warning').toLowerCase();
                return `<div class="net-alarm-item ${severity}"><b>${escapeHtml(alarm.alarm_type || 'Alarm')}</b><small>${escapeHtml(alarm.message || '')}</small><small>${escapeHtml(alarm.interface_name || '-')} • ${formatValue(alarm.value)}</small></div>`;
            }).join('');
        }

        const latestCritical = alarms.find(a => String(a.severity || '').toLowerCase() === 'critical');
        const latestId = latestCritical ? String(latestCritical.id) : '';
        const storedId = localStorage.getItem(STORAGE_KEY);
        if (latestCritical && latestId && latestId !== storedId && previousCritical !== null) {
            button.classList.add('has-critical');
        }
        if (latestId) localStorage.setItem(STORAGE_KEY, latestId);
        previousCritical = critical;
    }

    async function poll() {
        try {
            const response = await fetch(API_URL + '?nocache=' + Date.now(), {cache: 'no-store'});
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            if (data && data.success !== false) render(data);
        } catch (error) {
            console.warn('Global alarm notification:', error);
        }
    }

    function init() {
        if (!document.body) return;
        injectStyles();
        ensureUI();
        poll();
        setInterval(poll, POLL_MS);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
