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

require_once __DIR__ . "/../config/mikrotik.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../library/routeros_api.class.php";

date_default_timezone_set('Asia/Jakarta');


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$db = new Database();

$pdo = $db->connect();


/*
|--------------------------------------------------------------------------
| MIKROTIK CONFIG
|--------------------------------------------------------------------------
*/

$config = new MikroTikConfig();

$router = $config->getRouter();


/*
|--------------------------------------------------------------------------
| CONNECT MIKROTIK
|--------------------------------------------------------------------------
*/

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


// ==========================================
// ROUTER OFFLINE
// ==========================================

if (!$connected) {

    $offlineConfirmed = $alarmEngine->routerOffline(
        $router['id']
    );

    // Set database status to OFFLINE only after the grace period confirms it.
    if ($offlineConfirmed) {
        $config->updateStatus(
            $router['id'],
            'OFFLINE'
        );
    }

    fwrite(STDERR, "Router Offline\n");

    exit(1);
}


// ==========================================
// ROUTER ONLINE
// ==========================================

$alarmEngine->routerOnline(
    $router['id']
);

$config->updateStatus(
    $router['id'],
    'ONLINE'
);


/*
|--------------------------------------------------------------------------
| SYSTEM RESOURCE
|--------------------------------------------------------------------------
*/

$resource = $API->comm(
    "/system/resource/print"
);


$resourceData =
    $resource[0] ?? [];


/*
|--------------------------------------------------------------------------
| TRAFFIC ETHER1
|--------------------------------------------------------------------------
*/

$traffic = $API->comm(
    "/interface/monitor-traffic",
    [
        "interface" => "ether1",
        "once" => ""
    ]
);


$trafficData =
    $traffic[0] ?? [];


/*
|--------------------------------------------------------------------------
| CPU
|--------------------------------------------------------------------------
*/

$cpu = isset(
    $resourceData['cpu-load']
)
    ? (float)$resourceData['cpu-load']
    : 0;


/*
|--------------------------------------------------------------------------
| MEMORY
|--------------------------------------------------------------------------
*/

$totalMemory = isset(
    $resourceData['total-memory']
)
    ? (float)$resourceData['total-memory']
    : 0;


$freeMemory = isset(
    $resourceData['free-memory']
)
    ? (float)$resourceData['free-memory']
    : 0;


$memory = 0;


if ($totalMemory > 0) {

    $memory =
        (($totalMemory - $freeMemory)
        / $totalMemory) * 100;

}


/*
|--------------------------------------------------------------------------
| DISK
|--------------------------------------------------------------------------
*/

$totalDisk = isset(
    $resourceData['total-hdd-space']
)
    ? (float)$resourceData['total-hdd-space']
    : 0;


$freeDisk = isset(
    $resourceData['free-hdd-space']
)
    ? (float)$resourceData['free-hdd-space']
    : 0;


$disk = 0;


if ($totalDisk > 0) {

    $disk =
        (($totalDisk - $freeDisk)
        / $totalDisk) * 100;

}


/*
|--------------------------------------------------------------------------
| TRAFFIC
|--------------------------------------------------------------------------
*/

$rxBits = isset(
    $trafficData['rx-bits-per-second']
)
    ? (float)$trafficData['rx-bits-per-second']
    : 0;


$txBits = isset(
    $trafficData['tx-bits-per-second']
)
    ? (float)$trafficData['tx-bits-per-second']
    : 0;


$download =
    round($rxBits / 1000000, 2);


$upload =
    round($txBits / 1000000, 2);
/*
|--------------------------------------------------------------------------
| ALARM TRAFFIC
|--------------------------------------------------------------------------
*/

$alarmEngine->checkTraffic(
    $router['id'],
    "ether1",
    $download,
    $upload
);
/*
|--------------------------------------------------------------------------
| PACKET
|--------------------------------------------------------------------------
*/

$rxPacket = isset(
    $trafficData['rx-packets-per-second']
)
    ? (int)$trafficData['rx-packets-per-second']
    : 0;


$txPacket = isset(
    $trafficData['tx-packets-per-second']
)
    ? (int)$trafficData['tx-packets-per-second']
    : 0;


/*
|--------------------------------------------------------------------------
| SAVE DATABASE
|--------------------------------------------------------------------------
*/

$sql = "

INSERT INTO traffic_history
(
    router_id,
    interface_name,
    download_mbps,
    upload_mbps,
    rx_packet,
    tx_packet,
    cpu,
    memory,
    disk,
    created_at
)

VALUES
(
    :router_id,
    :interface_name,
    :download,
    :upload,
    :rx_packet,
    :tx_packet,
    :cpu,
    :memory,
    :disk,
    NOW()
)

";


$stmt = $pdo->prepare($sql);


$stmt->execute([

    ':router_id'
        => $router['id'],

    ':interface_name'
        => 'ether1',

    ':download'
        => $download,

    ':upload'
        => $upload,

    ':rx_packet'
        => $rxPacket,

    ':tx_packet'
        => $txPacket,

    ':cpu'
        => round($cpu, 2),

    ':memory'
        => round($memory, 2),

    ':disk'
        => round($disk, 2)

]);


/*
|--------------------------------------------------------------------------
| DISCONNECT
|--------------------------------------------------------------------------
*/

$API->disconnect();


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

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
