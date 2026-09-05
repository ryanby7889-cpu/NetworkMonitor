<?php
$activeMenu = $activeMenu ?? '';
$hotspotView = $hotspotView ?? '';
?>

<div class="sidebar" id="netSidebar">
    <div class="logo"><i class="bi bi-router"></i><span>NetMonitor</span></div>
    <button type="button" class="sidebar-toggle" id="sidebarToggle" title="Show / Hide Sidebar" aria-label="Show / Hide Sidebar" aria-expanded="true"><i class="bi bi-chevron-left"></i></button>
    <div class="menu-title"><span>Monitoring</span></div>
    <a href="../dashboard/index.php" class="menu-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" title="Dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
    <a href="../traffic/index.php" class="menu-item <?= $activeMenu === 'traffic' ? 'active' : '' ?>" title="Traffic History"><i class="bi bi-graph-up"></i><span>Traffic History</span></a>
    <div class="menu-title"><span>System</span></div>
    <a href="../router/index.php" class="menu-item <?= $activeMenu === 'router' ? 'active' : '' ?>" title="Router"><i class="bi bi-router"></i><span>Router</span></a>
    <a href="../pppoe/index.php" class="menu-item <?= $activeMenu === 'pppoe' ? 'active' : '' ?>" title="PPPoE"><i class="bi bi-globe2"></i><span>PPPoE</span></a>
    <div class="hotspot-menu <?= $activeMenu === 'hotspot' ? 'open' : '' ?>">
        <a href="../hotspot/" class="menu-item hotspot-parent <?= $activeMenu === 'hotspot' ? 'active' : '' ?>" title="Hotspot"><i class="bi bi-wifi"></i><span>Hotspot</span><i class="bi bi-chevron-down hotspot-chevron"></i></a>
        <div class="hotspot-submenu">
            <a href="../hotspot/" class="<?= $hotspotView === 'dashboard' || $hotspotView === '' ? 'active' : '' ?>"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a>
            <a href="../hotspot/users.php" class="<?= $hotspotView === 'users' ? 'active' : '' ?>"><i class="bi bi-people"></i><span>User Hotspot</span></a>
            <a href="../hotspot/customers.php" class="<?= $hotspotView === 'customers' ? 'active' : '' ?>"><i class="bi bi-person-vcard"></i><span>Pelanggan</span></a>
            <a href="../hotspot/active.php" class="<?= $hotspotView === 'active' ? 'active' : '' ?>"><i class="bi bi-broadcast-pin"></i><span>Active Session</span></a>
            <a href="../hotspot/profiles.php" class="<?= $hotspotView === 'profiles' ? 'active' : '' ?>"><i class="bi bi-diagram-3"></i><span>Profile</span></a>
            <a href="../hotspot/traffic.php" class="<?= $hotspotView === 'traffic' ? 'active' : '' ?>"><i class="bi bi-speedometer"></i><span>Live Traffic</span></a>
            <a href="../hotspot/history.php" class="<?= $hotspotView === 'history' ? 'active' : '' ?>"><i class="bi bi-clock-history"></i><span>Traffic History</span></a>
        </div>
    </div>
    <a href="../billing/index.php" class="menu-item <?= $activeMenu === 'billing' ? 'active' : '' ?>" title="Billing"><i class="bi bi-receipt"></i><span>Billing</span></a>
    <a href="../alarm/index.php" class="menu-item <?= $activeMenu === 'alarm' ? 'active' : '' ?>" title="Alarm"><i class="bi bi-exclamation-triangle"></i><span>Alarm</span></a>
    <a href="../settings/index.php" class="menu-item <?= $activeMenu === 'settings' ? 'active' : '' ?>" title="Settings"><i class="bi bi-gear"></i><span>Settings</span></a>
</div>

<script>
window.HOTSPOT_VIEW=<?= json_encode($hotspotView,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
document.addEventListener("DOMContentLoaded",function(){const sidebar=document.getElementById("netSidebar"),toggle=document.getElementById("sidebarToggle");if(!sidebar||!toggle)return;const key="netmonitor_sidebar";function setState(c){sidebar.classList.toggle("collapsed",c);toggle.setAttribute("aria-expanded",c?"false":"true");localStorage.setItem(key,c?"collapsed":"expanded")}if(localStorage.getItem(key)==="collapsed"){sidebar.classList.add("collapsed");toggle.setAttribute("aria-expanded","false")}toggle.addEventListener("click",()=>setState(!sidebar.classList.contains("collapsed")));const parent=sidebar.querySelector(".hotspot-parent");if(parent)parent.addEventListener("click",function(e){if(sidebar.classList.contains("collapsed")){e.preventDefault();sidebar.classList.remove("collapsed");setState(false)}})});
</script>

<script src="../assets/js/alarm_notification.js?v=1"></script>
<script src="../assets/js/dashboard_pro4.js?v=4"></script>
