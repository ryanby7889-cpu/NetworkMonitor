<?php

require_once "../Config/database.php";

$db = new Database();
$pdo = $db->connect();


// ===============================
// FILTER TANGGAL
// ===============================

$start = $_GET['start'] ?? date('Y-m-d');
$end   = $_GET['end'] ?? date('Y-m-d');


// ===============================
// DATA TRAFFIC
// ===============================

$sql = "
    SELECT
        interface_name,
        download_mbps,
        upload_mbps,
        rx_packet,
        tx_packet,
        cpu,
        memory,
        disk,
        created_at
    FROM traffic_history
    WHERE DATE(created_at) BETWEEN :start AND :end
    ORDER BY created_at ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':start' => $start,
    ':end'   => $end
]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ===============================
// DATA UNTUK GRAFIK
// ===============================

$labels = [];
$downloads = [];
$uploads = [];

foreach ($data as $row) {
    $labels[] = date("H:i", strtotime($row['created_at']));
    $downloads[] = (float)$row['download_mbps'];
    $uploads[] = (float)$row['upload_mbps'];
}


// ===============================
// STATISTIK
// ===============================

$maxDownload = 0;
$maxUpload = 0;
$avgDownload = 0;
$avgUpload = 0;

if (count($data) > 0) {
    $downloadValues = array_map('floatval', array_column($data, 'download_mbps'));
    $uploadValues = array_map('floatval', array_column($data, 'upload_mbps'));

    $maxDownload = max($downloadValues);
    $maxUpload = max($uploadValues);
    $avgDownload = array_sum($downloadValues) / count($downloadValues);
    $avgUpload = array_sum($uploadValues) / count($uploadValues);
}

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
</head>
<body>

<?php
$activeMenu = 'traffic';
require_once "../includes/sidebar.php";
?>

<div class="content">

    <div class="page-title">Traffic History</div>
    <div class="subtitle">Historical MikroTik Ether1 traffic monitoring</div>

    <div class="filter-box">
        <form method="GET">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">🔍 Tampilkan Data</button>
                        <a href="export.php?start=<?= urlencode($start) ?>&end=<?= urlencode($end) ?>" class="btn btn-success flex-fill">📥 Export CSV</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-title">Total Records</div>
                <div class="stat-value"><?= number_format(count($data)) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-title">Max Download</div>
                <div class="stat-value text-primary"><?= number_format($maxDownload, 2) ?> Mbps</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-title">Max Upload</div>
                <div class="stat-value text-success"><?= number_format($maxUpload, 2) ?> Mbps</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-title">Average Download</div>
                <div class="stat-value"><?= number_format($avgDownload, 2) ?> Mbps</div>
            </div>
        </div>
    </div>

    <div class="card-modern mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between mb-3">
                <div>
                    <h5 class="mb-1">Traffic Trend</h5>
                    <small class="text-muted"><?= htmlspecialchars($start, ENT_QUOTES, 'UTF-8') ?> sampai <?= htmlspecialchars($end, ENT_QUOTES, 'UTF-8') ?></small>
                </div>
                <span class="badge bg-primary">ETHER1</span>
            </div>
            <div class="chart-container">
                <canvas id="trafficChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card-modern">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">Traffic Log</h5>
                    <small class="text-muted">Data monitoring</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Interface</th>
                            <th>Download</th>
                            <th>Upload</th>
                            <th>RX Packet</th>
                            <th>TX Packet</th>
                            <th>CPU</th>
                            <th>Memory</th>
                            <th>Disk</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge-interface"><?= htmlspecialchars($row['interface_name'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td class="download"><?= number_format((float)$row['download_mbps'], 2) ?> Mbps</td>
                            <td class="upload"><?= number_format((float)$row['upload_mbps'], 2) ?> Mbps</td>
                            <td><?= number_format((int)$row['rx_packet']) ?> pkt/s</td>
                            <td><?= number_format((int)$row['tx_packet']) ?> pkt/s</td>
                            <td><?= number_format((float)$row['cpu'], 1) ?> %</td>
                            <td><?= number_format((float)$row['memory'], 1) ?> %</td>
                            <td><?= number_format((float)$row['disk'], 1) ?> %</td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($data) === 0): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">Tidak ada data pada periode tersebut.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const labels = <?= json_encode($labels) ?>;
const downloadData = <?= json_encode($downloads) ?>;
const uploadData = <?= json_encode($uploads) ?>;

const canvas = document.getElementById("trafficChart");

if (canvas) {
    new Chart(canvas, {
        type: "line",
        data: {
            labels: labels,
            datasets: [
                {
                    label: "Download",
                    data: downloadData,
                    borderColor: "#2563eb",
                    backgroundColor: "rgba(37,99,235,.12)",
                    fill: true,
                    tension: .35,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    borderWidth: 3
                },
                {
                    label: "Upload",
                    data: uploadData,
                    borderColor: "#10b981",
                    backgroundColor: "rgba(16,185,129,.10)",
                    fill: true,
                    tension: .35,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    borderWidth: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: "index", intersect: false },
            plugins: {
                legend: { position: "top" },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + " : " + Number(context.parsed.y).toFixed(2) + " Mbps";
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    title: { display: true, text: "Traffic (Mbps)" }
                }
            }
        }
    });
}
</script>

<script src="../assets/js/app.js?v=1"></script>
</body>
</html>
