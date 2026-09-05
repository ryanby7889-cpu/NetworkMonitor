<?php
$activeMenu = $activeMenu ?? '';
$hotspotView = $hotspotView ?? '';
$pppoeView = $pppoeView ?? '';
?>
<link rel="stylesheet" href="../assets/css/hotspot_sidebar.css?v=2">
<link rel="stylesheet" href="../assets/css/hotspot_subpage.css?v=1">
<link rel="stylesheet" href="../assets/css/pppoe_subnav.css?v=1">
<link rel="stylesheet" href="../assets/css/global_responsive.css?v=1">
<link rel="stylesheet" href="../assets/css/telegram_settings.css?v=1">
<div class="sidebar" id="netSidebar">
    <div class="logo"><i class="bi bi-router"></i><span>NetMonitor</span></div>
    <button type="button" class="sidebar-toggle" id="sidebarToggle" title="Show / Hide Sidebar" aria-label="Show / Hide Sidebar" aria-expanded="true"><i class="bi bi-chevron-left"></i></button>
    <div class="menu-title"><span>Monitoring</span></div>
    <a href="../dashboard/index.php" class="menu-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" title="Dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
    <a href="../traffic/index.php" class="menu-item <?= $activeMenu === 'traffic' ? 'active' : '' ?>" title="Traffic History"><i class="bi bi-graph-up"></i><span>Traffic History</span></a>
    <div class="menu-title"><span>System</span></div>
    <a href="../router/index.php" class="menu-item <?= $activeMenu === 'router' ? 'active' : '' ?>" title="Router"><i class="bi bi-router"></i><span>Router</span></a>
    <div class="pppoe-menu <?= $activeMenu === 'pppoe' ? 'open' : '' ?>">
        <a href="../pppoe/dashboard.php" class="menu-item pppoe-parent <?= $activeMenu === 'pppoe' ? 'active' : '' ?>" title="PPPoE"><i class="bi bi-globe2"></i><span>PPPoE</span><i class="bi bi-chevron-down pppoe-chevron"></i></a>
        <div class="pppoe-submenu">
            <a href="../pppoe/dashboard.php" class="<?= $pppoeView === 'dashboard' || $pppoeView === '' ? 'active' : '' ?>"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a>
            <a href="../pppoe/users.php" class="<?= $pppoeView === 'users' ? 'active' : '' ?>"><i class="bi bi-people"></i><span>User Accounts</span></a>
            <a href="../pppoe/active.php" class="<?= $pppoeView === 'active' ? 'active' : '' ?>"><i class="bi bi-broadcast-pin"></i><span>Active Session</span></a>
            <a href="../pppoe/profiles.php" class="<?= $pppoeView === 'profiles' ? 'active' : '' ?>"><i class="bi bi-diagram-3"></i><span>Profile</span></a>
            <a href="../pppoe/traffic.php" class="<?= $pppoeView === 'traffic' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i><span>Traffic Ranking</span></a>
            <a href="../pppoe/history_index.php" class="<?= $pppoeView === 'history' ? 'active' : '' ?>"><i class="bi bi-clock-history"></i><span>Traffic History</span></a>
            <a href="../pppoe/disconnect_history.php" class="<?= $pppoeView === 'disconnect_history' ? 'active' : '' ?>"><i class="bi bi-person-x"></i><span>Disconnect History</span></a>
            <a href="../pppoe/disconnect_analytics.php" class="<?= $pppoeView === 'disconnect_analytics' ? 'active' : '' ?>"><i class="bi bi-bar-chart-line"></i><span>Disconnect Analytics</span></a>
        </div>
    </div>
    <div class="hotspot-menu <?= $activeMenu === 'hotspot' ? 'open' : '' ?>">
        <a href="../hotspot/" class="menu-item hotspot-parent <?= $activeMenu === 'hotspot' ? 'active' : '' ?>" title="Hotspot"><i class="bi bi-wifi"></i><span>Hotspot</span><i class="bi bi-chevron-down hotspot-chevron"></i></a>
        <div class="hotspot-submenu">
            <a href="../hotspot/" class="<?= $hotspotView === 'dashboard' || $hotspotView === '' ? 'active' : '' ?>"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a>
            <a href="../hotspot/users.php" class="<?= $hotspotView === 'users' ? 'active' : '' ?>"><i class="bi bi-people"></i><span>User Hotspot</span></a>
            <a href="../hotspot/customers.php" class="<?= $hotspotView === 'customers' ? 'active' : '' ?>"><i class="bi bi-person-vcard"></i><span>Pelanggan</span></a>
            <a href="../hotspot/active.php" class="<?= $hotspotView === 'active' ? 'active' : '' ?>"><i class="bi bi-broadcast-pin"></i><span>Active Session</span></a>
            <a href="../hotspot/profiles.php" class="<?= $hotspotView === 'profiles' ? 'active' : '' ?>"><i class="bi bi-diagram-3"></i><span>Profile</span></a>
            <a href="../hotspot/traffic.php" class="<?= $hotspotView === 'traffic' ? 'active' : '' ?>"><i class="bi bi-speedometer"></i><span>Live Traffic</span></a>
        </div>
    </div>
    <a href="../billing/index.php" class="menu-item <?= $activeMenu === 'billing' ? 'active' : '' ?>" title="Billing"><i class="bi bi-receipt"></i><span>Billing</span></a>
    <a href="../alarm/index.php" class="menu-item <?= $activeMenu === 'alarm' ? 'active' : '' ?>" title="Alarm"><i class="bi bi-exclamation-triangle"></i><span>Alarm</span></a>
    <a href="../settings/index.php" class="menu-item <?= $activeMenu === 'settings' ? 'active' : '' ?>" title="Settings"><i class="bi bi-gear"></i><span>Settings</span></a>
</div>
<script>
window.HOTSPOT_VIEW=<?= json_encode($hotspotView,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
window.PPPOE_VIEW=<?= json_encode($pppoeView,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
document.addEventListener("DOMContentLoaded",function(){const s=document.getElementById("netSidebar"),t=document.getElementById("sidebarToggle");if(!s||!t)return;const k="netmonitor_sidebar";function set(c){s.classList.toggle("collapsed",c);t.setAttribute("aria-expanded",c?"false":"true");localStorage.setItem(k,c?"collapsed":"expanded")}if(localStorage.getItem(k)==="collapsed"){s.classList.add("collapsed");t.setAttribute("aria-expanded","false")}t.addEventListener("click",()=>set(!s.classList.contains("collapsed")));s.querySelectorAll(".hotspot-parent,.pppoe-parent").forEach(p=>p.addEventListener("click",e=>{if(s.classList.contains("collapsed")){e.preventDefault();set(false)}}));});
</script>
<script src="../assets/js/alarm_notification.js?v=1"></script><script src="../assets/js/dashboard_pro4.js?v=4"></script><script src="../assets/js/hotspot_subpage.js?v=1"></script><script src="../assets/js/app.js?v=6"></script><script src="../assets/js/telegram_settings.js?v=1"></script><script src="../assets/js/pppoe_disconnect_monitor.js?v=2"></script>
