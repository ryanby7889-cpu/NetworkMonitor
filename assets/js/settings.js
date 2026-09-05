document.addEventListener('DOMContentLoaded', () => {
  const layout = document.querySelector('.settings-layout');
  if (!layout) return;

  const nav = layout.querySelector('.settings-nav');
  const section = layout.querySelector('.settings-content');
  if (!nav || !section) return;

  const tabs = [...nav.querySelectorAll('[data-settings-tab]')];
  const panes = [...section.querySelectorAll('[data-settings-pane]')];
  const valid = ['general', 'mikrotik', 'alarm', 'system', 'billing'];

  function activate(target, writeHash = true) {
    if (!valid.includes(target)) target = 'general';
    tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.settingsTab === target));
    panes.forEach(pane => pane.classList.toggle('active', pane.dataset.settingsPane === target));
    if (writeHash) history.replaceState(null, '', '#' + target);
  }

  tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.settingsTab)));
  activate((location.hash || '').replace('#', ''), false);

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
