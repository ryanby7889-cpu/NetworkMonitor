<?php
$pageTitle = 'Router';
$activeMenu = 'router';
$pageCss = ['../assets/css/router.css'];
?>
<?php

require_once "config/mikrotik.php";

$config = new MikroTikConfig();

$router = $config->getRouter();

echo "<h2>Data Router</h2>";

echo "<table border='1' cellpadding='10'>";

echo "<tr>
<th>Field</th>
<th>Value</th>
</tr>";

foreach($router as $key=>$value){

    echo "<tr>";

    echo "<td>".$key."</td>";

    echo "<td>".$value."</td>";

    echo "</tr>";

}

echo "</table>";
require_once "library/routeros_api.class.php";
$API = new RouterosAPI();
$API->debug = false;
$connected = $API->connect(
    $router['ip_address'],
    $router['username'],
    $router['password'],
    $router['api_port']
);
if($connected){

    echo "<h2 style='color:green'>🟢 Router Connected</h2>";

    $resource = $API->comm("/system/resource/print");
    $identity = $API->comm("/system/identity/print");

$routerName = $identity[0]['name'];
$version    = $resource[0]['version'];
$uptime     = $resource[0]['uptime'];
$cpuLoad    = $resource[0]['cpu-load'];
echo "<h2>Informasi Router</h2>";

echo "<table border='1' cellpadding='10'>";

echo "<tr><td>Router</td><td>$routerName</td></tr>";

echo "<tr><td>Version</td><td>$version</td></tr>";

echo "<tr><td>CPU Load</td><td>$cpuLoad %</td></tr>";

echo "<tr><td>Uptime</td><td>$uptime</td></tr>";

echo "</table>";
   
    // Traffic
    $traffic = $API->comm("/interface/monitor-traffic", [
        "interface" => "ether1",
        "once" => ""
    ]);

    echo "<h2>Traffic Ether1</h2>";
    $rx = $traffic[0]['rx-bits-per-second'];
    $tx = $traffic[0]['tx-bits-per-second'];

$download = round($rx / 1000000, 2);
$upload   = round($tx / 1000000, 2);

echo "<table border='1' cellpadding='10'>";

echo "<tr>";
echo "<th>Parameter</th>";
echo "<th>Value</th>";
echo "</tr>";

echo "<tr>";
echo "<td>Interface</td>";
echo "<td>".$traffic[0]['name']."</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Download</td>";
echo "<td>".$download." Mbps</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Upload</td>";
echo "<td>".$upload." Mbps</td>";
echo "</tr>";

echo "<tr>";
echo "<td>RX Packet</td>";
echo "<td>".$traffic[0]['rx-packets-per-second']." pkt/s</td>";
echo "</tr>";

echo "<tr>";
echo "<td>TX Packet</td>";
echo "<td>".$traffic[0]['tx-packets-per-second']." pkt/s</td>";
echo "</tr>";
echo "</table>";
    $API->disconnect();

}else{

    echo "<h2 style='color:red'>🔴 Router Offline</h2>";
}