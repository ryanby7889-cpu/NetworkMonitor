<?php
require_once __DIR__ . '/../Config/auth.php';
requireLogin();

$activeMenu = 'router';
$routerView = 'traffic_analytics';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Traffic Analytics - NetMonitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/theme.css?v=1">
    <link rel="stylesheet" href="../assets/css/router_subnav.css?v=2">
    <link rel="stylesheet" href="../assets/css/global_responsive.css?v=1">
    <link rel="stylesheet" href="../assets/css/router_traffic_analytics.css?v=3">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<main class="main-content analytics-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Traffic Analytics</h1>
            <div class="page-subtitle">Analisis pola penggunaan bandwidth berdasarkan data traffic history.</div>
        </div>
        <span class="analytics-live" id="analyticsStatus"><i class="bi bi-activity"></i> Memuat data...</span>
    </div>

    <section class="analytics-toolbar card-modern">
        <div class="toolbar-field"><label for="analyticsRouter">Router</label><select id="analyticsRouter" class="form-select form-select-sm"></select></div>
        <div class="toolbar-field"><label for="analyticsInterface">Interface</label><select id="analyticsInterface" class="form-select form-select-sm"><option value="all">Semua Interface</option></select></div>
        <div class="toolbar-field toolbar-period"><label>Periode</label><div class="period-buttons"><button type="button" class="period-btn active" data-range="24h">24 Jam</button><button type="button" class="period-btn" data-range="7d">7 Hari</button></div></div>
    </section>

    <section class="analytics-stats">
        <div class="stat-card"><div class="stat-title">Rata-rata Download</div><div class="stat-value" id="avgDownload">0.00 Mbps</div></div>
        <div class="stat-card"><div class="stat-title">Rata-rata Upload</div><div class="stat-value" id="avgUpload">0.00 Mbps</div></div>
        <div class="stat-card"><div class="stat-title">Peak Download</div><div class="stat-value" id="peakDownload">0.00 Mbps</div></div>
        <div class="stat-card"><div class="stat-title">Peak Upload</div><div class="stat-value" id="peakUpload">0.00 Mbps</div></div>
        <div class="stat-card"><div class="stat-title">Peak Time</div><div class="stat-value" id="peakTime">-</div></div>
        <div class="stat-card"><div class="stat-title">Total Sample</div><div class="stat-value" id="sampleCount">0</div></div>
    </section>

    <section class="analytics-grid">
        <div class="card-modern analytics-chart-card"><div class="card-heading"><div><h2>Traffic Trend</h2><p>Download dan upload rata-rata per jam.</p></div><span id="chartInfo">-</span></div><div class="chart-wrap"><canvas id="analyticsChart"></canvas></div></div>
        <div class="card-modern analytics-hour-card"><div class="card-heading"><div><h2>Jam Sibuk</h2><p>Rata-rata traffic menurut jam.</p></div></div><div class="table-responsive"><table class="table table-modern"><thead><tr><th>Jam</th><th>Download</th><th>Upload</th><th>Total</th></tr></thead><tbody id="busyHoursBody"><tr><td colspan="4" class="text-center py-4 text-muted">Belum ada data.</td></tr></tbody></table></div></div>
    </section>

    <section class="v3-grid">
        <div class="card-modern interface-ranking-card">
            <div class="card-heading"><div><h2>Ranking Interface</h2><p>Urutan interface berdasarkan rata-rata traffic.</p></div><span id="interfaceCount">0 interface</span></div>
            <div class="table-responsive"><table class="table table-modern interface-table"><thead><tr><th>#</th><th>Interface</th><th>Avg Down</th><th>Avg Up</th><th>Peak Down</th><th>Peak Up</th><th>Share</th></tr></thead><tbody id="interfaceStatsBody"><tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data.</td></tr></tbody></table></div>
        </div>
        <div class="card-modern top-interface-card">
            <div class="card-heading"><div><h2>Top Traffic</h2><p>Interface paling dominan pada periode terpilih.</p></div></div>
            <div class="top-interface-content" id="topInterfaceContent"><div class="top-empty">Belum ada data.</div></div>
            <div class="interface-bars" id="interfaceBars"></div>
        </div>
    </section>

    <section class="card-modern analytics-insight">
        <div class="insight-icon"><i class="bi bi-lightbulb"></i></div>
        <div><h2>Insight Traffic</h2><p id="analyticsInsight">Menunggu data traffic...</p></div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="../assets/js/router_traffic_analytics.js?v=3"></script>
</body>
</html>
