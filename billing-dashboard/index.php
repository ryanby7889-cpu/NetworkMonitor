<?php
$pageTitle='Dashboard Billing';
$activeMenu='billing';
$billingView='dashboard';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Billing - NetMonitor</title>
<link rel="stylesheet" href="../assets/css/variables.css?v=12">
<link rel="stylesheet" href="../assets/css/common.css?v=12">
<link rel="stylesheet" href="../assets/css/theme.css?v=1">
<link rel="stylesheet" href="../assets/css/billing_dashboard.css?v=3">
<link rel="stylesheet" href="../assets/css/billing_dashboard_print.css?v=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>
<div class="main"><main class="bd-page">
<header class="bd-header"><div><h1>Dashboard Billing</h1><p>Monitoring pelanggan, tagihan, pembayaran, piutang, dan jatuh tempo PPPoE</p></div><div class="bd-actions"><input id="month" type="month" class="bd-input"><button id="refreshBtn" class="bd-btn"><i class="bi bi-arrow-clockwise"></i> Refresh</button><button id="printBtn" class="bd-btn"><i class="bi bi-printer"></i> Cetak</button></div></header>
<div id="notice" class="bd-notice" hidden></div>
<section class="bd-stats">
<div class="bd-stat"><span><i class="bi bi-people"></i> Total Pelanggan</span><strong id="customerTotal">0</strong><small id="customerStatus">Aktif 0 • Suspend 0</small></div>
<div class="bd-stat"><span><i class="bi bi-receipt"></i> Total Tagihan</span><strong id="billed">Rp 0</strong><small id="invoiceCount">0 invoice</small></div>
<div class="bd-stat"><span><i class="bi bi-cash-stack"></i> Pendapatan Diterima</span><strong id="paid">Rp 0</strong><small id="paidCount">0 lunas</small></div>
<div class="bd-stat"><span><i class="bi bi-wallet2"></i> Piutang</span><strong id="unpaid">Rp 0</strong><small id="unpaidCount">0 belum bayar</small></div>
<div class="bd-stat danger"><span><i class="bi bi-exclamation-triangle"></i> Overdue</span><strong id="overdue">0</strong><small id="overdueAmount">Rp 0 tunggakan</small></div>
<div class="bd-stat"><span><i class="bi bi-percent"></i> Collection Rate</span><strong id="collection">0%</strong><small>bulan terpilih</small></div>
</section>
<section class="bd-card bd-actions-card"><div><h2><i class="bi bi-lightning-charge"></i> Aksi Cepat</h2><p>Kelola billing tanpa meninggalkan dashboard.</p></div><div class="bd-quick"><a href="../billing/index.php#customerSection"><i class="bi bi-person-plus"></i> Pelanggan</a><a href="../billing/index.php#invoiceSection"><i class="bi bi-receipt"></i> Tagihan</a><a href="../billing/index.php#paymentSection"><i class="bi bi-credit-card"></i> Pembayaran</a><a href="../billing/invoice.php"><i class="bi bi-file-earmark-pdf"></i> Cetak Invoice</a></div></section>
<section class="bd-card bd-collection-card"><div class="bd-card-head"><div><h2><i class="bi bi-graph-up-arrow"></i> Collection & Pemasukan</h2><p>Realisasi pembayaran hari ini, minggu berjalan, dan bulan berjalan.</p></div><span class="bd-pill">LIVE 30s</span></div><div class="bd-collection-grid"><div><span>Hari Ini</span><strong id="collectToday">Rp 0</strong><small id="collectTodayCount">0 pembayaran</small></div><div><span>Minggu Ini</span><strong id="collectWeek">Rp 0</strong><small id="collectWeekCount">0 pembayaran</small></div><div><span>Bulan Ini</span><strong id="collectMonth">Rp 0</strong><small id="collectMonthCount">0 pembayaran</small></div></div><div class="bd-progress"><div class="bd-progress-head"><b>Realisasi periode terpilih</b><span id="collectionProgressText">0%</span></div><div class="bd-progress-track"><div id="collectionProgress" class="bd-progress-bar"></div></div></div></section>
<div class="bd-grid"><section class="bd-card"><div class="bd-card-head"><div><h2>Grafik Pendapatan 12 Bulan</h2><p>Tagihan dibandingkan dengan pembayaran diterima.</p></div></div><div class="bd-chart"><canvas id="revenueChart"></canvas></div></section><section class="bd-card"><div class="bd-card-head"><div><h2>Status Invoice</h2><p id="statusPeriod">Bulan terpilih</p></div></div><div class="bd-chart bd-chart-sm"><canvas id="statusChart"></canvas></div><div id="statusList" class="bd-status-list"></div></section></div>
<section class="bd-card"><div class="bd-card-head"><div><h2>Piutang Berdasarkan Umur</h2><p>Fokus penagihan berdasarkan usia invoice overdue.</p></div></div><div class="bd-grid bd-grid-tight"><div class="bd-chart bd-chart-aging"><canvas id="agingChart"></canvas></div><div id="agingList" class="bd-status-list"></div></div></section>
<section class="bd-card"><div class="bd-card-head"><div><h2>Integrasi PPPoE</h2><p>Sinkronisasi langsung dengan akun PPPoE MikroTik.</p></div><span id="pppoeState" class="bd-pill">Checking...</span></div><div class="bd-integration"><div><strong id="pppoeAccounts">0</strong><span>Akun PPPoE</span></div><div><strong id="pppoeEnabled">0</strong><span>Enabled</span></div><div><strong id="pppoeDisabled">0</strong><span>Disabled</span></div><div><strong id="pppoeActive">0</strong><span>Session Aktif</span></div><div><strong id="pppoeLinked">0</strong><span>Terhubung Billing</span></div><div><strong id="pppoeUnlinked">0</strong><span>Belum Terhubung</span></div></div><div id="pppoeError" class="bd-muted"></div></section>
<div class="bd-grid"><section class="bd-card"><div class="bd-card-head"><div><h2>Jatuh Tempo 7 Hari</h2><p>Prioritas penagihan berikutnya.</p></div><a class="bd-text-link" href="../billing/index.php#invoiceSection">Lihat Invoice</a></div><div class="bd-table-wrap"><table class="bd-table"><thead><tr><th>Invoice</th><th>Pelanggan</th><th>Jatuh Tempo</th><th>Jumlah</th><th>Hari</th></tr></thead><tbody id="upcoming"></tbody></table></div></section><section class="bd-card"><div class="bd-card-head"><div><h2>Top Piutang</h2><p>Pelanggan dengan tunggakan terbesar.</p></div><a class="bd-text-link" href="../billing/index.php#invoiceSection">Kelola</a></div><div class="bd-table-wrap"><table class="bd-table"><thead><tr><th>Pelanggan</th><th>PPPoE</th><th>Invoice</th><th>Piutang</th><th>Hari</th></tr></thead><tbody id="arrears"></tbody></table></div></section></div>
<section class="bd-card"><div class="bd-card-head"><div><h2>Ringkasan Bulanan</h2><p>Rekap 12 bulan terakhir.</p></div></div><div class="bd-table-wrap"><table class="bd-table monthly"><thead><tr><th>Bulan</th><th>Invoice</th><th>Tagihan</th><th>Dibayar</th><th>Piutang</th><th>Collection</th></tr></thead><tbody id="monthly"></tbody></table></div></section>
<div class="bd-foot">Data dibaca langsung dari <b>billing_customers</b> dan <b>billing_invoices</b>. Dashboard tidak menjalankan suspend otomatis.</div>
</main></div>
<script src="../assets/js/billing_dashboard.js?v=3"></script><script src="../assets/js/app.js?v=1"></script><script src="../assets/js/alarm_notification.js?v=3"></script>
</body></html>
