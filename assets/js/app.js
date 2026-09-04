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

    // Apply before DOMContentLoaded where possible to minimize flashing.
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

        const current = localStorage.getItem(THEME_KEY) || 'light';
        applyTheme(current);

        // Traffic History: refresh automatically so newly collected records
        // appear without requiring the user to click "Tampilkan Data".
        // Keep the current URL (including selected date filters) on reload.
        if (window.location.pathname.toLowerCase().includes('/traffic/')) {
            const AUTO_REFRESH_MS = 10000;
            let refreshTimer = null;

            const scheduleRefresh = () => {
                if (refreshTimer) clearTimeout(refreshTimer);
                refreshTimer = setTimeout(() => {
                    if (document.visibilityState === 'visible') {
                        window.location.reload();
                    } else {
                        scheduleRefresh();
                    }
                }, AUTO_REFRESH_MS);
            };

            scheduleRefresh();
        }
    });
})();
