<?php
$activeMenu = $activeMenu ?? '';
?>

<div class="sidebar" id="netSidebar">
    <div class="logo">
        <i class="bi bi-router"></i>
        <span>NetMonitor</span>
    </div>

    <button type="button" class="sidebar-toggle" id="sidebarToggle" title="Show / Hide Sidebar" aria-label="Show / Hide Sidebar" aria-expanded="true">
        <i class="bi bi-chevron-left"></i>
    </button>

    <div class="menu-title"><span>Monitoring</span></div>

    <a href="../dashboard/index.php" class="menu-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
        <i class="bi bi-speedometer2"></i>
        <span>Dashboard</span>
    </a>

    <a href="../traffic/index.php" class="menu-item <?= $activeMenu === 'traffic' ? 'active' : '' ?>" title="Traffic History">
        <i class="bi bi-graph-up"></i>
        <span>Traffic History</span>
    </a>

    <div class="menu-title"><span>System</span></div>

    <a href="../router/index.php" class="menu-item <?= $activeMenu === 'router' ? 'active' : '' ?>" title="Router">
        <i class="bi bi-router"></i>
        <span>Router</span>
    </a>

    <a href="../pppoe/index.php" class="menu-item <?= $activeMenu === 'pppoe' ? 'active' : '' ?>" title="PPPoE">
        <i class="bi bi-globe2"></i>
        <span>PPPoE</span>
    </a>

    <a href="../hotspot/" class="menu-item <?= $activeMenu === 'hotspot' ? 'active' : '' ?>" title="Hotspot">
        <i class="bi bi-wifi"></i>
        <span>Hotspot</span>
    </a>

    <a href="../billing/index.php" class="menu-item <?= $activeMenu === 'billing' ? 'active' : '' ?>" title="Billing">
        <i class="bi bi-receipt"></i>
        <span>Billing</span>
    </a>

    <a href="../alarm/index.php" class="menu-item <?= $activeMenu === 'alarm' ? 'active' : '' ?>" title="Alarm">
        <i class="bi bi-exclamation-triangle"></i>
        <span>Alarm</span>
    </a>

    <a href="../settings/index.php" class="menu-item <?= $activeMenu === 'settings' ? 'active' : '' ?>" title="Settings">
        <i class="bi bi-gear"></i>
        <span>Settings</span>
    </a>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.getElementById("netSidebar");
    const toggle = document.getElementById("sidebarToggle");
    if (!sidebar || !toggle) return;

    const STORAGE_KEY = "netmonitor_sidebar";

    function setSidebarState(collapsed) {
        sidebar.classList.toggle("collapsed", collapsed);
        toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
        localStorage.setItem(STORAGE_KEY, collapsed ? "collapsed" : "expanded");
    }

    const savedState = localStorage.getItem(STORAGE_KEY);
    if (savedState === "collapsed") {
        sidebar.classList.add("collapsed");
        toggle.setAttribute("aria-expanded", "false");
    }

    toggle.addEventListener("click", function () {
        setSidebarState(!sidebar.classList.contains("collapsed"));
    });
});
</script>

<!-- Global alarm notification + Dashboard PRO v4. -->
<script src="../assets/js/alarm_notification.js?v=1"></script>
<script src="../assets/js/dashboard_pro4.js?v=4"></script>
