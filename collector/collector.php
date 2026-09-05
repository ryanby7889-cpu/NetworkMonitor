<?php

/*
|--------------------------------------------------------------------------
| LOCAL COLLECTOR ENDPOINT ONLY
|--------------------------------------------------------------------------
| The loop calls this file through Apache because the web runtime has the
| reliable RouterOS connection. Do not expose data collection to the LAN.
*/
if (PHP_SAPI !== 'cli') {
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('Collector hanya dapat dipanggil secara lokal.');
    }
}

require_once __DIR__ . "/../Config/mikrotik.php";
require_once __DIR__ . "/../Config/database.php";
require_once __DIR__ . "/../library/routeros_api.class.php";

date_default_timezone_set('Asia/Jakarta');

$db = new Database();
$pdo = $db->connect();
$pdo->exec("SET time_zone = '+07:00'");

$config = new MikroTikConfig();
$router = $config->getRouter();

$API = new RouterosAPI();
$API->debug = false;
$API->port = (int)$router['api_port'];

$connected = $API->connect(
    $router['ip_address'],
    $router['username'],
    $router['password']
);

require_once __DIR__ . "/../alarm/alarm_engine.php";
$alarmEngine = new AlarmEngine();

if (!$connected) {
    $offlineConfirmed = $alarmEngine->routerOffline($router['id']);

    if ($offlineConfirmed) {
        $config->updateStatus($router['id'], 'OFFLINE');
    }

    fwrite(STDERR, "Router Offline\n");
    exit(1);
}

$alarmEngine->routerOnline($router['id']);
$config->updateStatus($router['id'], 'ONLINE');

/*
|--------------------------------------------------------------------------
| PPPoE DISCONNECT DETECTION — SERVER SIDE
|--------------------------------------------------------------------------
| Never replace the previous snapshot when RouterOS returns a failed/invalid
| response. An empty active-session list is valid; a failed command is not.
| This prevents a temporary RouterOS/API error from generating fake
| disconnect events for every PPPoE user.
*/
$pdo->exec("CREATE TABLE IF NOT EXISTS pppoe_disconnect_state (
    router_id INT NOT NULL,
    session_key VARCHAR(255) NOT NULL,
    username VARCHAR(128) NOT NULL,
    address VARCHAR(64) NULL,
    profile VARCHAR(128) NULL,
    caller_id VARCHAR(128) NULL,
    uptime VARCHAR(128) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (router_id, session_key),
    KEY idx_router_updated(router_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$activeRows = $API->comm('/ppp/active/print');
$activeRowsValid = is_array($activeRows);
$currentSessions = [];

if ($activeRowsValid) {
    foreach ($activeRows as $row) {
        if (!is_array($row)) continue;
        $username = trim((string)($row['name'] ?? ''));
        if ($username === '') continue;

        $sessionId = trim((string)($row['.id'] ?? $row['id'] ?? ''));
        $address = trim((string)($row['address'] ?? '-'));
        $profile = trim((string)($row['profile'] ?? '-'));
        $callerId = trim((string)($row['caller-id'] ?? '-'));
        $uptime = trim((string)($row['uptime'] ?? '-'));

        $sessionKey = $sessionId !== ''
            ? $sessionId
            : hash('sha256', $username.'|'.$address.'|'.$callerId);

        $currentSessions[$sessionKey] = [
            'name' => $username,
            'address' => $address,
            'profile' => $profile,
            'caller_id' => $callerId,
            'uptime' => $uptime,
            'session_id' => $sessionId !== '' ? $sessionId : $sessionKey
        ];
    }
}

$goneSessions = [];

if ($activeRowsValid) {
    $stateStmt = $pdo->prepare("SELECT session_key, username, address, profile, caller_id, uptime
        FROM pppoe_disconnect_state WHERE router_id = ?");
    $stateStmt->execute([(int)$router['id']]);
    $previousSessions = [];

    foreach ($stateStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $previousSessions[(string)$row['session_key']] = [
            'name' => $row['username'] ?: '-',
            'address' => $row['address'] ?: '-',
            'profile' => $row['profile'] ?: '-',
            'caller_id' => $row['caller_id'] ?: '-',
            'uptime' => $row['uptime'] ?: '-',
            'session_id' => $row['session_key']
        ];
    }

    if ($previousSessions) {
        foreach ($previousSessions as $sessionKey => $old) {
            if (!isset($currentSessions[$sessionKey])) {
                $goneSessions[] = $old;
            }
        }
    }

    // Only replace this router's snapshot after a valid RouterOS response.
    $pdo->prepare("DELETE FROM pppoe_disconnect_state WHERE router_id = ?")
        ->execute([(int)$router['id']]);
    $insertState = $pdo->prepare("INSERT INTO pppoe_disconnect_state
        (router_id, session_key, username, address, profile, caller_id, uptime, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

    foreach ($currentSessions as $sessionKey => $session) {
        $insertState->execute([
            (int)$router['id'],
            $sessionKey,
            $session['name'],
            $session['address'],
            $session['profile'],
            $session['caller_id'],
            $session['uptime']
        ]);
    }
}

if ($goneSessions && function_exists('curl_init')) {
    $payload = json_encode([
        'router_id' => (int)$router['id'],
        'router_name' => (string)($router['name'] ?? ('Router '.$router['id'])),
        'events' => array_slice($goneSessions, 0, 50)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init('http://127.0.0.1/api/pppoe_disconnect_notify.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 8
    ]);
    curl_exec($ch);
    curl_close($ch);
}

$resource = $API->comm("/system/resource/print");
$resourceData = $resource[0] ?? [];

$traffic = $API->comm(
    "/interface/monitor-traffic",
    [
        "interface" => "ether1",
        "once" => ""
    ]
);
$trafficData = $traffic[0] ?? [];

$cpu = isset($resourceData['cpu-load']) ? (float)$resourceData['cpu-load'] : 0;
$totalMemory = isset($resourceData['total-memory']) ? (float)$resourceData['total-memory'] : 0;
$freeMemory = isset($resourceData['free-memory']) ? (float)$resourceData['free-memory'] : 0;
$memory = $totalMemory > 0 ? (($totalMemory - $freeMemory) / $totalMemory) * 100 : 0;

$totalDisk = isset($resourceData['total-hdd-space']) ? (float)$resourceData['total-hdd-space'] : 0;
$freeDisk = isset($resourceData['free-hdd-space']) ? (float)$resourceData['free-hdd-space'] : 0;
$disk = $totalDisk > 0 ? (($totalDisk - $freeDisk) / $totalDisk) * 100 : 0;

$rxBits = isset($trafficData['rx-bits-per-second']) ? (float)$trafficData['rx-bits-per-second'] : 0;
$txBits = isset($trafficData['tx-bits-per-second']) ? (float)$trafficData['tx-bits-per-second'] : 0;
$download = round($rxBits / 1000000, 2);
$upload = round($txBits / 1000000, 2);

$alarmEngine->checkTraffic($router['id'], "ether1", $download, $upload);

$rxPacket = isset($trafficData['rx-packets-per-second']) ? (int)$trafficData['rx-packets-per-second'] : 0;
$txPacket = isset($trafficData['tx-packets-per-second']) ? (int)$trafficData['tx-packets-per-second'] : 0;

$sql = "
INSERT INTO traffic_history
(
    router_id, interface_name, download_mbps, upload_mbps,
    rx_packet, tx_packet, cpu, memory, disk, created_at
)
VALUES
(
    :router_id, :interface_name, :download, :upload,
    :rx_packet, :tx_packet, :cpu, :memory, :disk, NOW()
)
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':router_id' => $router['id'],
    ':interface_name' => 'ether1',
    ':download' => $download,
    ':upload' => $upload,
    ':rx_packet' => $rxPacket,
    ':tx_packet' => $txPacket,
    ':cpu' => round($cpu, 2),
    ':memory' => round($memory, 2),
    ':disk' => round($disk, 2)
]);

$API->disconnect();

echo "History saved: ";
echo date("Y-m-d H:i:s");
echo "<br>";
echo "Download: ".$download." Mbps";
echo "<br>";
echo "Upload: ".$upload." Mbps";
echo "<br>";
echo "CPU: ".$cpu." %";
echo "<br>";
echo "Memory: ".$memory." %";
echo "<br>";
echo "Disk: ".$disk." %";
