<?php

$activeMenu = 'alarm';

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/mikrotik.php";
require_once __DIR__ . "/../library/routeros_api.class.php";
require_once __DIR__ . "/alarm_engine.php";

$db = new Database();
$pdo = $db->connect();

/* Reconcile router status. */
$mikrotikConfig = new MikroTikConfig();
$router = $mikrotikConfig->getRouter();

if ($router) {
    $routerApi = new RouterosAPI();
    $routerApi->debug = false;
    $routerApi->port = (int)$router['api_port'];

    if ($routerApi->connect($router['ip_address'], $router['username'], $router['password'])) {
        $alarmEngine = new AlarmEngine();
        $alarmEngine->routerOnline($router['id']);
        $mikrotikConfig->updateStatus($router['id'], 'ONLINE');
        $routerApi->disconnect();
    }
}

$sqlActive = "SELECT id, router_id, interface_name, alarm_type, severity, message, value, threshold, status, created_at
              FROM alarms WHERE status = 'active' ORDER BY created_at DESC";
$stmtActive = $pdo->prepare($sqlActive);
$stmtActive->execute();
$activeAlarms = $stmtActive->fetchAll(PDO::FETCH_ASSOC);

$sqlHistory = "SELECT id, router_id, interface_name, alarm_type, severity, message, value, threshold, status, created_at, resolved_at
               FROM alarms ORDER BY created_at DESC LIMIT 50";
$stmtHistory = $pdo->prepare($sqlHistory);
$stmtHistory->execute();
$alarmHistory = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

$totalActive = count($activeAlarms);
$totalHistory = count($alarmHistory);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Alarm Monitoring</title>
<link rel="stylesheet" href="../assets/css/variables.css?v=4">
<link rel="stylesheet" href="../assets/css/common.css?v=4">
<link rel="stylesheet" href="../assets/css/theme.css?v=1">
<link rel="stylesheet" href="../assets/css/alarm.css?v=4">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
<?php require_once "../includes/sidebar.php"; ?>

<main class="main-content alarm-page">
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="page-title">Alarm Monitoring</div>
            <div class="subtitle">Real-time MikroTik Ether1 &amp; PPPoE bandwidth alarm monitoring</div>
        </div>
        <div class="text-end">
            <div class="refresh-text">Auto refresh 10 seconds</div>
            <div><span class="badge bg-primary">NETWORK ALARM</span></div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-title">Active Alarm</div>
                <div class="stat-value text-danger"><?= $totalActive ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-title">Alarm History</div>
                <div class="stat-value"><?= $totalHistory ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-title">Monitoring</div>
                <div id="monitoringStatus" class="stat-value text-success">CHECKING...</div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white border-0 p-4"><h5 class="mb-0">🔴 Active Alarm</h5></div>
        <div class="card-body pt-0">
<?php if ($totalActive == 0): ?>
            <div class="alert alert-success">🟢 Tidak ada alarm aktif. Semua kondisi jaringan normal.</div>
<?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Interface</th><th>Alarm</th><th>Severity</th><th>Value</th><th>Threshold</th><th>Message</th><th>Waktu</th></tr></thead>
                    <tbody>
<?php foreach ($activeAlarms as $alarm): ?>
                        <tr class="alarm-card">
                            <td><strong><?= htmlspecialchars($alarm['interface_name']) ?></strong></td>
                            <td><?= htmlspecialchars($alarm['alarm_type']) ?></td>
                            <td>
<?php if ($alarm['severity'] === 'critical'): ?>
                                <span class="badge badge-critical">CRITICAL</span>
<?php else: ?>
                                <span class="badge badge-warning">WARNING</span>
<?php endif; ?>
                            </td>
                            <td><span class="value"><?= number_format((float)$alarm['value'], 2) ?> Mbps</span></td>
                            <td><?= number_format((float)$alarm['threshold'], 2) ?> Mbps</td>
                            <td class="message"><?= htmlspecialchars($alarm['message']) ?></td>
                            <td><?= htmlspecialchars($alarm['created_at']) ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
<?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white border-0 p-4"><h5 class="mb-0">📋 Alarm History</h5></div>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Interface</th><th>Alarm</th><th>Severity</th><th>Value</th><th>Threshold</th><th>Status</th><th>Created</th><th>Resolved</th></tr></thead>
                    <tbody>
<?php foreach ($alarmHistory as $alarm): ?>
                        <tr>
                            <td><?= htmlspecialchars($alarm['interface_name']) ?></td>
                            <td><?= htmlspecialchars($alarm['alarm_type']) ?></td>
                            <td>
<?php if ($alarm['severity'] === 'critical'): ?>
                                <span class="badge badge-critical">CRITICAL</span>
<?php else: ?>
                                <span class="badge badge-warning">WARNING</span>
<?php endif; ?>
                            </td>
                            <td><?= number_format((float)$alarm['value'], 2) ?> Mbps</td>
                            <td><?= number_format((float)$alarm['threshold'], 2) ?> Mbps</td>
                            <td>
<?php if ($alarm['status'] === 'active'): ?>
                                <span class="badge badge-active">ACTIVE</span>
<?php else: ?>
                                <span class="badge badge-resolved">RESOLVED</span>
<?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($alarm['created_at']) ?></td>
                            <td><?= !empty($alarm['resolved_at']) ? htmlspecialchars($alarm['resolved_at']) : '-' ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>

<script>
async function checkMonitoringStatus() {
    const statusElement = document.getElementById('monitoringStatus');
    if (!statusElement) return;
    try {
        const response = await fetch('../api/traffic.php?nocache=' + Date.now(), {cache:'no-store'});
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();
        const online = data.status === 'online';
        statusElement.textContent = online ? 'ONLINE' : 'OFFLINE';
        statusElement.classList.toggle('text-success', online);
        statusElement.classList.toggle('text-danger', !online);
    } catch (error) {
        console.error('Monitoring Status Error:', error);
        statusElement.textContent = 'OFFLINE';
        statusElement.classList.remove('text-success');
        statusElement.classList.add('text-danger');
    }
}
checkMonitoringStatus();
setInterval(checkMonitoringStatus, 10000);
setInterval(function(){ location.reload(); }, 10000);
</script>
<script src="../assets/js/pppoe_alarm_center.js?v=1"></script>
<script src="../assets/js/app.js?v=1"></script>
</body>
</html>
