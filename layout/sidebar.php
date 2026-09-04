<?php
// Shared sidebar.
// Set $activeMenu to the current menu key before including this file.
$activeMenu = $activeMenu ?? '';
?>
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <span class="brand-mark">NM</span>
        <span class="brand-text">Network Monitor</span>
    </div>

    <nav class="sidebar-nav" aria-label="Navigasi utama">
        <a class="menu-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="../dashboard/">
            <span>Dashboard</span>
        </a>
        <a class="menu-item <?= $activeMenu === 'router' ? 'active' : '' ?>" href="../router/">
            <span>Router</span>
        </a>
        <a class="menu-item <?= $activeMenu === 'pppoe' ? 'active' : '' ?>" href="../pppoe/">
            <span>PPPoE</span>
        </a>
        <a class="menu-item <?= $activeMenu === 'traffic' ? 'active' : '' ?>" href="../traffic/">
            <span>Traffic</span>
        </a>
        <a class="menu-item <?= $activeMenu === 'alarm' ? 'active' : '' ?>" href="../alarm/">
            <span>Alarm</span>
        </a>
        <a class="menu-item <?= $activeMenu === 'report' ? 'active' : '' ?>" href="../report/">
            <span>Report</span>
        </a>
        <a class="menu-item <?= $activeMenu === 'settings' ? 'active' : '' ?>" href="../settings/">
            <span>Settings</span>
        </a>
    </nav>
</aside>
