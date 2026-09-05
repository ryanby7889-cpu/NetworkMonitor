document.addEventListener('DOMContentLoaded', () => {
  const layout = document.querySelector('.settings-layout');
  if (!layout) return;
  const nav = layout.querySelector('.settings-nav');
  const section = layout.querySelector(':scope > section');
  if (!nav || !section) return;

  const panes = [...layout.querySelectorAll('.settings-pane')];
  panes.forEach(pane => { if (pane.parentElement !== section) section.appendChild(pane); });
  const tabs = [...nav.querySelectorAll('[data-settings-tab]')];
  const directPanes = [...section.querySelectorAll(':scope > .settings-pane')];

  function activate(target, writeHash = true) {
    const allowed = directPanes.map(p => p.dataset.settingsPane);
    if (!allowed.includes(target)) target = 'general';
    tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.settingsTab === target));
    directPanes.forEach(pane => {
      const active = pane.dataset.settingsPane === target;
      pane.classList.toggle('active', active);
      pane.hidden = !active;
    });
    if (writeHash) history.replaceState(null, '', '#' + target);
  }
  tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.settingsTab)));
  activate((location.hash || '').replace(/^#/, ''), false);

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
    alarmPane.querySelectorAll('input[type="number"]').forEach(input => { input.min = '0'; input.max = '100000'; input.step = '0.01'; });
  }

  document.querySelectorAll('.test-router-connection').forEach(test => {
    test.addEventListener('click', async () => {
      const routerId = test.dataset.routerId;
      const result = document.querySelector(`.router-connection-result[data-router-id="${CSS.escape(routerId)}"]`);
      const badge = document.querySelector(`.router-status-badge[data-router-id="${CSS.escape(routerId)}"]`);
      const old = test.innerHTML;
      test.disabled = true; test.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Tes...';
      if (result) { result.className = 'router-connection-result text-secondary'; result.textContent = 'Menghubungkan...'; }
      try {
        const response = await fetch(`../api/settings_router.php?router_id=${encodeURIComponent(routerId)}`, { cache: 'no-store' });
        const data = await response.json(); const ok = !!data.success;
        if (result) { result.className = 'router-connection-result ' + (ok ? 'text-success' : 'text-danger'); result.textContent = ok ? `ONLINE • ${data.identity || 'Terhubung'}` : (data.message || 'Koneksi gagal'); }
        if (badge) { badge.textContent = data.status || (ok ? 'ONLINE' : 'OFFLINE'); badge.className = 'status-badge ' + (ok ? 'online' : 'offline') + ' router-status-badge'; }
      } catch (error) { if (result) { result.className = 'router-connection-result text-danger'; result.textContent = 'API error: ' + error.message; } }
      finally { test.disabled = false; test.innerHTML = old; }
    });
  });
});
