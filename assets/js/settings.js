document.addEventListener('DOMContentLoaded', () => {
  const tabs = document.querySelectorAll('[data-settings-tab]');
  const panes = document.querySelectorAll('[data-settings-pane]');
  tabs.forEach(tab => tab.addEventListener('click', () => {
    const target = tab.dataset.settingsTab;
    tabs.forEach(x => x.classList.toggle('active', x === tab));
    panes.forEach(x => x.classList.toggle('active', x.dataset.settingsPane === target));
    history.replaceState(null, '', '#' + target);
  }));

  const hash = location.hash.replace('#','');
  if (hash && document.querySelector(`[data-settings-tab="${CSS.escape(hash)}"]`)) {
    document.querySelector(`[data-settings-tab="${CSS.escape(hash)}"]`).click();
  }

  const testButtons = document.querySelectorAll('.test-router-connection');
  testButtons.forEach(test => test.addEventListener('click', async () => {
    const routerId = test.dataset.routerId;
    const result = document.querySelector(`.router-connection-result[data-router-id="${CSS.escape(routerId)}"]`);
    const old = test.innerHTML;
    test.disabled = true;
    test.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Tes...';
    if (result) { result.className = 'router-connection-result text-secondary'; result.textContent = 'Menghubungkan...'; }
    try {
      const r = await fetch(`../api/settings_router.php?router_id=${encodeURIComponent(routerId)}`, {cache:'no-store'});
      const d = await r.json();
      const ok = !!d.success;
      if (result) {
        result.className = 'router-connection-result ' + (ok ? 'text-success' : 'text-danger');
        result.textContent = ok ? `ONLINE • ${d.identity || 'Terhubung'}` : (d.message || 'Koneksi gagal');
      }
      const rowBadge = document.querySelector(`.router-status-badge[data-router-id="${CSS.escape(routerId)}"]`);
      if (rowBadge) {
        rowBadge.textContent = d.status || (ok ? 'ONLINE' : 'OFFLINE');
        rowBadge.className = 'status-badge ' + (ok ? 'online' : 'offline') + ' router-status-badge';
      }
    } catch (e) {
      if (result) { result.className = 'router-connection-result text-danger'; result.textContent = 'API error: ' + e.message; }
    } finally {
      test.disabled = false;
      test.innerHTML = old;
    }
  }));

  const test = document.getElementById('testRouterConnection');
  const result = document.getElementById('routerConnectionResult');
  if (test) test.addEventListener('click', async () => {
    const old = test.innerHTML;
    test.disabled = true;
    test.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menguji...';
    result.className = 'settings-status';
    result.textContent = 'Menghubungkan ke MikroTik...';
    try {
      const r = await fetch('../api/settings_router.php', {cache:'no-store'});
      const d = await r.json();
      result.className = 'settings-status ' + (d.success ? 'success' : 'danger');
      result.textContent = d.success ? `ONLINE • ${d.identity || 'Router terhubung'}` : (d.message || 'Koneksi gagal');
      const badge = document.getElementById('routerStatusBadge');
      if (badge) { badge.textContent = d.status; badge.className = 'status-badge ' + (d.success ? 'online' : 'offline'); }
    } catch(e) {
      result.className = 'settings-status danger';
      result.textContent = 'API error: ' + e.message;
    } finally { test.disabled = false; test.innerHTML = old; }
  });
});
