<?php
$activeMenu = 'hotspot';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hotspot - NetMonitor</title>
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/common.css">
<link rel="stylesheet" href="../assets/css/theme.css?v=1">
<link rel="stylesheet" href="../assets/css/hotspot.css">
<link rel="stylesheet" href="../assets/css/hotspot_router.css?v=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content hotspot-page">
  <header class="hotspot-header">
    <div>
      <h1>Hotspot MikroTik</h1>
      <p>Manajemen user, profile, dan active session Hotspot secara live.</p>
    </div>
    <button class="hs-btn hs-primary" id="refreshBtn"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
  </header>

  <div id="message" class="hs-message" hidden></div>

  <section class="hs-stats">
    <div class="hs-stat"><span>Total User</span><strong id="usersTotal">0</strong></div>
    <div class="hs-stat"><span>User Enabled</span><strong id="usersEnabled">0</strong></div><div class="hs-stat"><span>User Online</span><strong id="usersOnline">0</strong></div>
    <div class="hs-stat"><span>Profile</span><strong id="profilesTotal">0</strong></div>
    <div class="hs-stat"><span>Active Session</span><strong id="activeTotal">0</strong></div>
  </section>

  <section class="hs-card">
    <div class="hs-card-head">
      <div><h2>Live Traffic Hotspot</h2><small>Akumulasi traffic dari active session</small></div>
      <div class="hs-tools"><input id="trafficSearch" placeholder="Cari user / IP..."><select id="trafficSort"><option value="rx">RX terbesar</option><option value="tx">TX terbesar</option><option value="total">Total terbesar</option><option value="name">Username A-Z</option></select></div>
    </div>
    <div class="hs-traffic-summary"><div><span>Total RX</span><strong id="trafficIn">0 B</strong></div><div><span>Total TX</span><strong id="trafficOut">0 B</strong></div><div><span>Session Terbesar</span><strong id="trafficPeak">-</strong></div></div>
    <div class="hs-table-wrap"><table><thead><tr><th>#</th><th>User</th><th>IP</th><th>Uptime</th><th>RX</th><th>TX</th></tr></thead><tbody id="trafficTable"><tr><td colspan="6">Memuat...</td></tr></tbody></table></div>
  </section>

  <section class="hs-card">
    <div class="hs-card-head"><div><h2>User Hotspot</h2><small>Account Hotspot dari MikroTik</small></div><div class="hs-tools"><input id="userSearch" placeholder="Cari username / profile..."><button class="hs-btn hs-primary" id="addUserBtn"><i class="bi bi-person-plus"></i> Tambah User</button></div></div>
    <div class="hs-table-wrap"><table><thead><tr><th>#</th><th>Username</th><th>Profile</th><th>Status</th><th>Billing</th><th>Limit Uptime</th><th>Traffic</th><th>Comment</th><th>Action</th></tr></thead><tbody id="userTable"><tr><td colspan="9">Memuat...</td></tr></tbody></table></div>
  </section>

  <section class="hs-card">
    <div class="hs-card-head"><div><h2>Active Session</h2><small>Client Hotspot yang sedang online</small></div><input id="activeSearch" placeholder="Cari user / IP / MAC..."></div>
    <div class="hs-table-wrap"><table><thead><tr><th>#</th><th>User</th><th>IP</th><th>MAC</th><th>Login By</th><th>Uptime</th><th>Traffic</th><th>Action</th></tr></thead><tbody id="activeTable"><tr><td colspan="8">Memuat...</td></tr></tbody></table></div>
  </section>

  <section class="hs-card">
    <div class="hs-card-head"><div><h2>Hotspot Profile</h2><small>Profile bandwidth dan session dari MikroTik</small></div><button class="hs-btn hs-primary" id="addProfileBtn"><i class="bi bi-plus-circle"></i> Tambah Profile</button></div>
    <div class="hs-table-wrap"><table><thead><tr><th>#</th><th>Profile</th><th>Rate Limit</th><th>Shared Users</th><th>Session Timeout</th><th>Idle Timeout</th><th>Action</th></tr></thead><tbody id="profileTable"><tr><td colspan="7">Memuat...</td></tr></tbody></table></div>
  </section>
</main>

<div class="hs-modal" id="userModal" hidden style="display:none!important;"><div class="hs-modal-box"><div class="hs-modal-head"><h2 id="modalTitle">Tambah User Hotspot</h2><button type="button" id="closeModal">×</button></div><form id="userForm"><input type="hidden" name="id"><div class="hs-form-grid"><label>Username<input name="name" maxlength="64" required></label><label>Password<input name="password" type="password" maxlength="128"></label><label>Profile<select name="profile" id="profileSelect"></select></label><label>Server<input name="server" placeholder="all"></label><label>Limit Uptime<input name="limit_uptime" placeholder="Contoh: 1h"></label><label>Limit Bytes In<input name="limit_bytes_in" placeholder="Contoh: 1G"></label><label>Limit Bytes Out<input name="limit_bytes_out" placeholder="Contoh: 1G"></label></div><div id="formMessage" class="hs-message" hidden></div><div class="hs-modal-actions"><button type="button" class="hs-btn" id="cancelBtn">Batal</button><button class="hs-btn hs-primary" type="submit">Simpan</button></div></form></div></div>

<div class="hs-modal" id="billingLinkModal" hidden style="display:none!important;"><div class="hs-modal-box"><div class="hs-modal-head"><div><h2>Hubungkan ke Pelanggan</h2><small id="linkHotspotName">-</small></div><button type="button" id="closeBillingLinkModal">×</button></div><form id="billingLinkForm"><label>Pelanggan Billing<select id="billingCustomerSelect" name="customer_id" required><option value="">Memuat...</option></select></label><div id="billingLinkMessage" class="hs-message" hidden></div><div class="hs-modal-actions"><button type="button" class="hs-btn" id="cancelBillingLinkBtn">Batal</button><button type="submit" class="hs-btn hs-primary" id="saveBillingLinkBtn">Simpan Hubungan</button></div></form></div></div>

<div class="hs-modal" id="detailModal" hidden style="display:none!important;"><div class="hs-modal-box hs-detail-box"><div class="hs-modal-head"><div><h2>Detail User Hotspot</h2><small id="detailSubtitle">-</small></div><button type="button" id="closeDetailModal" aria-label="Tutup">×</button></div><div id="billingLinkControls" class="hs-detail-billing-controls"><button type="button" class="hs-btn hs-primary" id="linkBillingBtn">Hubungkan ke Pelanggan</button><button type="button" class="hs-btn" id="unlinkBillingBtn" hidden>Lepas Hubungan</button></div><div id="detailBilling" class="hs-detail-billing"><span>Status Billing</span><strong>Memuat...</strong></div><div class="hs-detail-grid"><div><span>Username</span><strong id="detailName">-</strong></div><div><span>Profile</span><strong id="detailProfile">-</strong></div><div><span>Status Account</span><strong id="detailAccount">-</strong></div><div><span>Status Live</span><strong id="detailLive">-</strong></div><div><span>IP Address</span><strong id="detailIp">-</strong></div><div><span>MAC Address</span><strong id="detailMac">-</strong></div><div><span>Login By</span><strong id="detailLogin">-</strong></div><div><span>Uptime</span><strong id="detailUptime">-</strong></div><div><span>Traffic RX / TX</span><strong id="detailTraffic">-</strong></div><div><span>Limit Uptime</span><strong id="detailLimitUptime">-</strong></div><div><span>Limit Bytes In</span><strong id="detailLimitIn">-</strong></div><div><span>Limit Bytes Out</span><strong id="detailLimitOut">-</strong></div><div class="wide"><span>Comment</span><strong id="detailComment">-</strong></div></div><div class="hs-modal-actions"><button type="button" class="hs-btn" id="closeDetailBtn">Tutup</button></div></div></div>

<div class="hs-modal" id="profileModal" hidden style="display:none!important;"><div class="hs-modal-box"><div class="hs-modal-head"><h2 id="profileModalTitle">Tambah Profile Hotspot</h2><button type="button" id="closeProfileModal" aria-label="Tutup">×</button></div><form id="profileForm"><input type="hidden" name="id"><div class="hs-form-grid"><label>Nama Profile<input name="name" maxlength="64" required></label><label>Rate Limit<input name="rate_limit" placeholder="Contoh: 5M/5M"></label><label>Shared Users<input name="shared_users" type="number" min="1" value="1"></label><label>Session Timeout<input name="session_timeout" placeholder="Contoh: 1h"></label><label>Idle Timeout<input name="idle_timeout" placeholder="Contoh: 10m"></label><label>Keepalive Timeout<input name="keepalive_timeout" placeholder="Contoh: 2m"></label><label>Status Autorefresh<input name="status_autorefresh" placeholder="Contoh: 1m"></label><label>Transparent Proxy<select name="transparent_proxy"><option value="">Default</option><option value="yes">Yes</option><option value="no">No</option></select></label></div><div id="profileFormMessage" class="hs-message" hidden></div><div class="hs-modal-actions"><button type="button" class="hs-btn" id="cancelProfileBtn">Batal</button><button class="hs-btn hs-primary" type="submit">Simpan</button></div></form></div></div>

<script src="../assets/js/hotspot.js?v=20260903-ready"></script>
<script src="../assets/js/hotspot_router_selector.js?v=1"></script>
<script src="../assets/js/app.js?v=1"></script>
</body>
</html>
