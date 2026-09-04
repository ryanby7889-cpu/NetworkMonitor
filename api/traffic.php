<?php

header('Content-Type: application/json; charset=utf-8');

require_once "../Config/mikrotik.php";
require_once "../library/routeros_api.class.php";

$config = new MikroTikConfig();

$router = $config->getRouter();

$API = new RouterosAPI();

$API->debug = false;


/*
|--------------------------------------------------------------------------
| CONNECT MIKROTIK
|--------------------------------------------------------------------------
*/

if (!$API->connect(
    $router['ip_address'],
    $router['username'],
    $router['password'],
    $router['api_port']
)) {

    echo json_encode([
        "status" => "offline",
        "message" => "Router tidak dapat terhubung"
    ]);

    exit;
}

// A successful web API connection is authoritative: clear stale offline
// alarms and keep the persisted router status in sync with the live status.
require_once "../alarm/alarm_engine.php";

$alarmEngine = new AlarmEngine();
$alarmEngine->routerOnline($router['id']);
$config->updateStatus($router['id'], 'ONLINE');


/*
|--------------------------------------------------------------------------
| SYSTEM RESOURCE
|--------------------------------------------------------------------------
*/

$resource = $API->comm(
    "/system/resource/print"
);


/*
|--------------------------------------------------------------------------
| IDENTITY
|--------------------------------------------------------------------------
*/

$identity = $API->comm(
    "/system/identity/print"
);


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


/*
|--------------------------------------------------------------------------
| RESOURCE DATA
|--------------------------------------------------------------------------
*/

$resourceData = $resource[0] ?? [];

$identityData = $identity[0] ?? [];

$trafficData = $traffic[0] ?? [];


/*
|--------------------------------------------------------------------------
| ROUTER INFORMATION
|--------------------------------------------------------------------------
*/

$routerName = $identityData['name']
    ?? 'Unknown';

$version = $resourceData['version']
    ?? '-';

$uptime = $resourceData['uptime']
    ?? '-';

$board = $resourceData['board-name']
    ?? '-';

$architecture = $resourceData['architecture-name']
    ?? '-';


/*
|--------------------------------------------------------------------------
| CPU
|--------------------------------------------------------------------------
*/

$cpu = isset($resourceData['cpu-load'])
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


/*
|--------------------------------------------------------------------------
| CALCULATE MEMORY
|--------------------------------------------------------------------------
*/

$usedMemory = $totalMemory - $freeMemory;

$memoryUsage = 0;

if ($totalMemory > 0) {

    $memoryUsage =
        ($usedMemory / $totalMemory) * 100;

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


$usedDisk = $totalDisk - $freeDisk;

$diskUsage = 0;

if ($totalDisk > 0) {

    $diskUsage =
        ($usedDisk / $totalDisk) * 100;

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


$download = round(
    $rxBits / 1000000,
    2
);


$upload = round(
    $txBits / 1000000,
    2
);


/*
|--------------------------------------------------------------------------
| PACKETS
|--------------------------------------------------------------------------
*/

$rxPacket = isset(
    $trafficData['rx-packets-per-second']
)
    ? (float)$trafficData['rx-packets-per-second']
    : 0;


$txPacket = isset(
    $trafficData['tx-packets-per-second']
)
    ? (float)$trafficData['tx-packets-per-second']
    : 0;


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

$data = [

    "status" => "online",

    "router" => $routerName,

    "version" => $version,

    "board" => $board,

    "architecture" => $architecture,

    "uptime" => $uptime,

    "cpu" => round($cpu, 1),

    "memory" => round($memoryUsage, 1),

    "memory_total" => $totalMemory,

    "memory_free" => $freeMemory,

    "disk" => round($diskUsage, 1),

    "disk_total" => $totalDisk,

    "disk_free" => $freeDisk,

    "download" => $download,

    "upload" => $upload,

    "rx_packet" => $rxPacket,

    "tx_packet" => $txPacket

];


echo json_encode(
    $data,
    JSON_PRETTY_PRINT
);


/*
|--------------------------------------------------------------------------
| DISCONNECT
|--------------------------------------------------------------------------
*/

$API->disconnect();
