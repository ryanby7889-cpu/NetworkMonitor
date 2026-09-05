document.addEventListener('DOMContentLoaded', () => {
  const layout = document.querySelector('.settings-layout');
  const nav = layout?.querySelector('.settings-nav');
  const section = layout?.querySelector(':scope > section');
  if (!layout || !nav || !section) return;

  // Normalize panes that were nested by the older compact Settings markup.
  [...section.querySelectorAll('.settings-pane')].forEach(pane => {
    if (pane.parentElement !== section) section.appendChild(pane);
  });

  const tabs = [...nav.querySelectorAll('[data-settings-tab]')];
  const panes = [...section.querySelectorAll(':scope > .settings-pane')];
  const valid = ['general', 'mikrotik', 'alarm', 'system', 'billing'];

  function activate(target, writeHash = true) {
    if (!valid.includes(target)) target = 'general';
    tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.settingsTab === target));
    panes.forEach(pane => pane.classList.toggle('active', pane.dataset.settingsPane === target));
    if (writeHash) history.replaceState(null, '', '#' + target);
  }

  tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.settingsTab)));
  activate((location.hash || '').replace('#', ''), false);

  // Alarm Settings = absolute Ether1 traffic thresholds in Mbps.
  const alarmPane = section.querySelector('[data-settings-pane="alarm"]');
  if (alarmPane) {
    const heading = alarmPane.querySelector('h5');
    if (heading) heading.innerHTML = '<i class="bi bi-bell me-2"></i>Ether1 Traffic Alarm Threshold';
    const desc = alarmPane.querySelector('.text-secondary.small');
    if (desc) desc.textContent = 'Atur ambang batas traffic interface ether1. Nilai dibandingkan langsung dengan traffic Mbps saat monitoring.';
    alarmPane.querySelectorAll('.form-label').forEach(label => {
      const text = label.textContent.trim();
      if (text.includes('Download Warning')) label.textContent = 'Ether1 Download Warning (Mbps)';
      if (text.includes('Download Critical')) label.textContent = 'Ether1 Download Critical (Mbps)';
      if (text.includes('Upload Warning')) label.textContent = 'Ether1 Upload Warning (Mbps)';
      if (text.includes('Upload Critical')) label.textContent = 'Ether1 Upload Critical (Mbps)';
    });
    alarmPane.querySelectorAll('input[type="number"]').forEach(input => {
      input.min = '0';
      input.max = '100000';
      input.step = '0.01';
    });
  }

  document.querySelectorAll('.test-router-connection').forEach(test => {
    test.addEventListener('click', async () => {
      const routerId = test.dataset.routerId;
      const result = document.querySelector(`.router-connection-result[data-router-id="${CSS.escape(routerId)}"]`);
      const badge = document.querySelector(`.router-status-badge[data-router-id="${CSS.escape(routerId)}"]`);
      const old = test.innerHTML;
      test.disabled = true;
      test.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Tes...';
      if (result) {
        result.className = 'router-connection-result text-secondary';
        result.textContent = 'Menghubungkan...';
      }
      try {
        const response = await fetch(`../api/settings_router.php?router_id=${encodeURIComponent(routerId)}`, { cache: 'no-store' });
        const data = await response.json();
        const ok = !!data.success;
        if (result) {
          result.className = 'router-connection-result ' + (ok ? 'text-success' : 'text-danger');
          result.textContent = ok ? `ONLINE • ${data.identity || 'Terhubung'}` : (data.message || 'Koneksi gagal');
        }
        if (badge) {
          badge.textContent = data.status || (ok ? 'ONLINE' : 'OFFLINE');
          badge.className = 'status-badge ' + (ok ? 'online' : 'offline') + ' router-status-badge';
        }
      } catch (error) {
        if (result) {
          result.className = 'router-connection-result text-danger';
          result.textContent = 'API error: ' + error.message;
        }
      } finally {
        test.disabled = false;
        test.innerHTML = old;
      }
    });
  });
});
