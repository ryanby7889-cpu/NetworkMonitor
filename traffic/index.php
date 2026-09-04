<?php
require_once "../Config/database.php";

$db = new Database();
$pdo = $db->connect();

$range = $_GET['range'] ?? '24h';
$allowedRanges = ['1h', '6h', '24h', '7d'];
if (!in_array($range, $allowedRanges, true)) $range = '24h';

$to = date('Y-m-d H:i:s');
$seconds = ['1h' => 3600, '6h' => 21600, '24h' => 86400, '7d' => 604800][$range];
$from = date('Y-m-d H:i:s', time() - $seconds);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Traffic History - NetMonitor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/css/variables.css?v=4">
<link rel="stylesheet" href="../assets/css/common.css?v=4">
<link rel="stylesheet" href="../assets/css/theme.css?v=1">
<link rel="stylesheet" href="../assets/css/traffic.css?v=6">
<style>
.history-toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.history-range{border:1px solid #dbe2ea;background:#fff;color:#475569;border-radius:9px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;transition:.2s}
.history-range:hover{border-color:#2563eb;color:#2563eb}
.history-range.active{background:#2563eb;color:#fff;border-color:#2563eb}
.history-meta{font-size:12px;color:#64748b}
.history-live{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border:1px solid #dbe2ea;border-radius:9px;background:#fff;color:#475569;font-size:12px;font-weight:600;white-space:nowrap}
.history-live-dot{width:7px;height:7px;border-radius:50%;background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.12)}
.pagination-wrap{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px;flex-wrap:wrap}
.pagination-buttons{display:flex;gap:6px;align-items:center}
.page-btn{min-width:36px;height:34px;border:1px solid #dbe2ea;background:#fff;border-radius:8px;color:#475569;font-weight:600;cursor:pointer}
.page-btn:hover:not(:disabled){border-color:#2563eb;color:#2563eb}
.page-btn.active{background:#2563eb;color:#fff;border-color:#2563eb}
.page-btn:disabled{opacity:.45;cursor:not-allowed}
@media (max-width:768px){.history-toolbar{width:100%}.history-range{flex:1}.pagination-wrap{align-items:flex-start;flex-direction:column}.history-live{order:-1}}
body.dark .history-range,body.dark .page-btn,body.dark .history-live{background:#111827;color:#cbd5e1;border-color:#334155}
body.dark .history-range.active,body.dark .page-btn.active{background:#2563eb;color:#fff;border-color:#2563eb}
</style>
</head>
<body>
<?php
$activeMenu = 'traffic';
require_once "../includes/sidebar.php";
?>

<div class="content">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-1">
        <div>
            <div class="page-title">Traffic History</div>
            <div class="subtitle">Historical MikroTik Ether1 traffic monitoring</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="history-live" id="historyLiveStatus"><span class="history-live-dot"></span><span>Live • updating 10s</span></span>
            <div class="history-toolbar" id="rangeToolbar">
                <button type="button" class="history-range" data-range="1h">1 Jam</button>
                <button type="button" class="history-range" data-range="6h">6 Jam</button>
                <button type="button" class="history-range" data-range="24h">24 Jam</button>
                <button type="button" class="history-range" data-range="7d">7 Hari</button>
            </div>
        </div>
    </div>

    <div class="filter-box mt-3">
        <form method="GET" id="historyFilterForm">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start" id="historyStart" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end" id="historyEnd" class="form-control">
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">🔍 Tampilkan Data</button>
                        <a href="export.php" id="exportTraffic" class="btn btn-success flex-fill">📥 Export CSV</a>
                    </div>
                </div>
            </div>
        </form>
        <div class="history-meta mt-2" id="rangeInfo">Memuat periode...</div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="stat-card"><div class="stat-title">Total Records</div><div class="stat-value" id="statRecords">0</div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-title">Max Download</div><div class="stat-value text-primary" id="statMaxDownload">0.00 Mbps</div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-title">Max Upload</div><div class="stat-value text-success" id="statMaxUpload">0.00 Mbps</div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-title">Average Download</div><div class="stat-value" id="statAvgDownload">0.00 Mbps</div></div></div>
    </div>

    <div class="card-modern mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between mb-3">
                <div><h5 class="mb-1">Traffic Trend</h5><small class="text-muted" id="chartPeriod">Memuat...</small></div>
                <span class="badge bg-primary">ETHER1</span>
            </div>
            <div class="chart-container"><canvas id="trafficChart"></canvas></div>
        </div>
    </div>

    <div class="card-modern">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div><h5 class="mb-1">Traffic Log</h5><small class="text-muted">Menampilkan data terbaru sesuai periode</small></div>
                <select id="perPageSelect" class="form-select form-select-sm" style="width:auto">
                    <option value="25">25 / halaman</option>
                    <option value="50">50 / halaman</option>
                    <option value="100">100 / halaman</option>
                </select>
            </div>
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead><tr><th>Time</th><th>Interface</th><th>Download</th><th>Upload</th><th>RX Packet</th><th>TX Packet</th><th>CPU</th><th>Memory</th><th>Disk</th></tr></thead>
                    <tbody id="trafficTableBody"><tr><td colspan="9" class="text-center py-5 text-muted">Memuat data...</td></tr></tbody>
                </table>
            </div>
            <div class="pagination-wrap">
                <div class="history-meta" id="paginationInfo">0 data</div>
                <div class="pagination-buttons" id="paginationButtons"></div>
            </div>
        </div>
    </div>
</div>

<script>
window.TRAFFIC_HISTORY_CONFIG = {
    range: <?= json_encode($range) ?>,
    from: <?= json_encode($from) ?>,
    to: <?= json_encode($to) ?>,
    page: 1,
    perPage: 25
};
</script>
<script src="../assets/js/app.js?v=4"></script>
</body>
</html>
