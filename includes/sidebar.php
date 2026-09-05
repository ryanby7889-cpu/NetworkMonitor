<?php
$activeMenu = $activeMenu ?? '';
$hotspotView = $hotspotView ?? '';
?>
<link rel="stylesheet" href="../assets/css/hotspot_sidebar.css?v=2">
<link rel="stylesheet" href="../assets/css/hotspot_subpage.css?v=1">
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
        </div>
    </div>
    <a href="../billing/index.php" class="menu-item <?= $activeMenu === 'billing' ? 'active' : '' ?>" title="Billing"><i class="bi bi-receipt"></i><span>Billing</span></a>
    <a href="../alarm/index.php" class="menu-item <?= $activeMenu === 'alarm' ? 'active' : '' ?>" title="Alarm"><i class="bi bi-exclamation-triangle"></i><span>Alarm</span></a>
    <a href="../settings/index.php" class="menu-item <?= $activeMenu === 'settings' ? 'active' : '' ?>" title="Settings"><i class="bi bi-gear"></i><span>Settings</span></a>
</div>
<script>
window.HOTSPOT_VIEW=<?= json_encode($hotspotView,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
document.addEventListener("DOMContentLoaded",function(){const s=document.getElementById("netSidebar"),t=document.getElementById("sidebarToggle");if(!s||!t)return;const k="netmonitor_sidebar";function set(c){s.classList.toggle("collapsed",c);t.setAttribute("aria-expanded",c?"false":"true");localStorage.setItem(k,c?"collapsed":"expanded")}if(localStorage.getItem(k)==="collapsed"){s.classList.add("collapsed");t.setAttribute("aria-expanded","false")}t.addEventListener("click",()=>set(!s.classList.contains("collapsed")));const p=s.querySelector(".hotspot-parent");if(p)p.addEventListener("click",e=>{if(s.classList.contains("collapsed")){e.preventDefault();set(false)}})});
</script>
<script src="../assets/js/alarm_notification.js?v=1"></script><script src="../assets/js/dashboard_pro4.js?v=4"></script><script src="../assets/js/hotspot_subpage.js?v=1"></script>
