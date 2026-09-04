<?php
$activeMenu = $activeMenu ?? '';
?>

<div class="sidebar" id="netSidebar">

    <!-- LOGO -->
    <div class="logo">

        <i class="bi bi-router"></i>

        <span>NetMonitor</span>

    </div>


    <!-- TOGGLE SHOW / HIDE -->
    <button
        type="button"
        class="sidebar-toggle"
        id="sidebarToggle"
        title="Show / Hide Sidebar"
        aria-label="Show / Hide Sidebar"
        aria-expanded="true"
    >
        <i class="bi bi-chevron-left"></i>
    </button>


    <!-- =========================
         MONITORING
    ========================== -->

    <div class="menu-title">
        <span>Monitoring</span>
    </div>


    <a
        href="../dashboard/index.php"
        class="menu-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>"
        title="Dashboard"
    >
        <i class="bi bi-speedometer2"></i>
        <span>Dashboard</span>
    </a>


    <a
        href="../traffic/index.php"
        class="menu-item <?= $activeMenu === 'traffic' ? 'active' : '' ?>"
        title="Traffic History"
    >
        <i class="bi bi-graph-up"></i>
        <span>Traffic History</span>
    </a>


    <!-- =========================
         SYSTEM
    ========================== -->

    <div class="menu-title">
        <span>System</span>
    </div>


    <a
        href="../router/index.php"
        class="menu-item <?= $activeMenu === 'router' ? 'active' : '' ?>"
        title="Router"
    >
        <i class="bi bi-router"></i>
        <span>Router</span>
    </a>


    <a
        href="../pppoe/index.php"
        class="menu-item <?= $activeMenu === 'pppoe' ? 'active' : '' ?>"
        title="PPPoE"
    >
        <i class="bi bi-globe2"></i>
        <span>PPPoE</span>
    </a>


    <a
        href="../hotspot/"
        class="menu-item <?= $activeMenu === 'hotspot' ? 'active' : '' ?>"
        title="Hotspot"
    >
        <i class="bi bi-wifi"></i>
        <span>Hotspot</span>
    </a>


    <a
        href="../billing/index.php"
        class="menu-item <?= $activeMenu === 'billing' ? 'active' : '' ?>"
        title="Billing"
    >
        <i class="bi bi-receipt"></i>
        <span>Billing</span>
    </a>


    <a
        href="../alarm/index.php"
        class="menu-item <?= $activeMenu === 'alarm' ? 'active' : '' ?>"
        title="Alarm"
    >
        <i class="bi bi-exclamation-triangle"></i>
        <span>Alarm</span>
    </a>


    <!-- THEME TOGGLE -->
    <button
        type="button"
        class="sidebar-theme-toggle"
        id="themeToggle"
        title="Ganti mode siang / malam"
        aria-label="Ganti mode siang / malam"
    >
        <i class="bi bi-moon"></i>
        <span>Mode malam</span>
    </button>

    <a
        href="../settings/index.php"
        class="menu-item <?= $activeMenu === 'settings' ? 'active' : '' ?>"
        title="Settings"
    >
        <i class="bi bi-gear"></i>
        <span>Settings</span>
    </a>

</div>


<script>
document.addEventListener("DOMContentLoaded", function () {

    const sidebar = document.getElementById("netSidebar");
    const toggle = document.getElementById("sidebarToggle");

    if (!sidebar || !toggle) {
        return;
    }

    const STORAGE_KEY = "netmonitor_sidebar";

    function setSidebarState(collapsed) {

        sidebar.classList.toggle("collapsed", collapsed);

        toggle.setAttribute(
            "aria-expanded",
            collapsed ? "false" : "true"
        );

        localStorage.setItem(
            STORAGE_KEY,
            collapsed ? "collapsed" : "expanded"
        );
    }

    // Load previous state
    const savedState = localStorage.getItem(STORAGE_KEY);

    if (savedState === "collapsed") {
        sidebar.classList.add("collapsed");
        toggle.setAttribute("aria-expanded", "false");
    }

    // Toggle
    toggle.addEventListener("click", function () {

        const collapsed =
            !sidebar.classList.contains("collapsed");

        setSidebarState(collapsed);

    });

    // Global light / dark mode. Preference is shared by every page.
    const themeToggle = document.getElementById("themeToggle");
    const THEME_KEY = "netmonitor_theme";

    function setTheme(dark) {
        document.body.classList.toggle("dark", dark);
        document.documentElement.classList.toggle("theme-dark", dark);

        if (themeToggle) {
            const icon = themeToggle.querySelector("i");
            const label = themeToggle.querySelector("span");
            if (icon) icon.className = dark ? "bi bi-sun" : "bi bi-moon";
            if (label) label.textContent = dark ? "Mode siang" : "Mode malam";
            themeToggle.setAttribute("aria-pressed", dark ? "true" : "false");
        }

        localStorage.setItem(THEME_KEY, dark ? "dark" : "light");
    }

    const savedTheme = localStorage.getItem(THEME_KEY);
    setTheme(savedTheme === "dark");

    if (themeToggle) {
        themeToggle.addEventListener("click", function () {
            setTheme(!document.body.classList.contains("dark"));
        });
    }

});
</script>
