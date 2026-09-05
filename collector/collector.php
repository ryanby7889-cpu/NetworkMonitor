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
$connected = $API->connect($router['ip_address'], $router['username'], $router['password']);
require_once __DIR__ . "/../alarm/alarm_engine.php";
$alarmEngine = new AlarmEngine();
if (!$connected) {
    $offlineConfirmed = $alarmEngine->routerOffline($router['id']);
    if ($offlineConfirmed) $config->updateStatus($router['id'], 'OFFLINE');
    fwrite(STDERR, "Router Offline\n");
    exit(1);
}
$alarmEngine->routerOnline($router['id']);
$config->updateStatus($router['id'], 'ONLINE');

/* PPPoE disconnect/reconnect state. */
$pdo->exec("CREATE TABLE IF NOT EXISTS pppoe_disconnect_state (
    router_id INT NOT NULL, session_key VARCHAR(255) NOT NULL, username VARCHAR(128) NOT NULL,
    address VARCHAR(64) NULL, profile VARCHAR(128) NULL, caller_id VARCHAR(128) NULL,
    uptime VARCHAR(128) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (router_id, session_key), KEY idx_router_updated(router_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS pppoe_user_presence_state (
    router_id INT NOT NULL, username VARCHAR(128) NOT NULL, status ENUM('online','offline') NOT NULL DEFAULT 'online',
    address VARCHAR(64) NULL, profile VARCHAR(128) NULL, caller_id VARCHAR(128) NULL,
    session_id VARCHAR(255) NULL, last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(router_id, username), KEY idx_router_status(router_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$activeRows = $API->comm('/ppp/active/print');
$activeRowsValid = is_array($activeRows);
$currentSessions = [];
$currentUsers = [];
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
        $sessionKey = $sessionId !== '' ? $sessionId : hash('sha256', $username.'|'.$address.'|'.$callerId);
        $session = ['name'=>$username,'address'=>$address,'profile'=>$profile,'caller_id'=>$callerId,'uptime'=>$uptime,'session_id'=>$sessionId !== '' ? $sessionId : $sessionKey];
        $currentSessions[$sessionKey] = $session;
        $currentUsers[$username] = $session;
    }
}

$goneSessions = [];
$connectEvents = [];
if ($activeRowsValid) {
    $stateStmt = $pdo->prepare("SELECT session_key, username, address, profile, caller_id, uptime FROM pppoe_disconnect_state WHERE router_id = ?");
    $stateStmt->execute([(int)$router['id']]);
    $previousSessions = [];
    foreach ($stateStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $previousSessions[(string)$row['session_key']] = [
            'name'=>$row['username'] ?: '-', 'address'=>$row['address'] ?: '-', 'profile'=>$row['profile'] ?: '-',
            'caller_id'=>$row['caller_id'] ?: '-', 'uptime'=>$row['uptime'] ?: '-', 'session_id'=>$row['session_key']
        ];
    }
    foreach ($previousSessions as $sessionKey => $old) {
        if (!isset($currentSessions[$sessionKey])) $goneSessions[] = $old;
    }

    /* Persistent username presence lets us distinguish a real reconnect from the first baseline. */
    $presenceStmt = $pdo->prepare("SELECT username,status FROM pppoe_user_presence_state WHERE router_id=?");
    $presenceStmt->execute([(int)$router['id']]);
    $presence = [];
    foreach ($presenceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $presence[(string)$row['username']] = (string)$row['status'];

    $allUsernames = array_unique(array_merge(array_keys($presence), array_keys($currentUsers)));
    foreach ($allUsernames as $username) {
        $isOnline = isset($currentUsers[$username]);
        $wasOnline = ($presence[$username] ?? null) === 'online';
        if ($isOnline && isset($presence[$username]) && !$wasOnline) {
            $connectEvents[] = $currentUsers[$username];
        }
    }

    $upsertPresence = $pdo->prepare("INSERT INTO pppoe_user_presence_state(router_id,username,status,address,profile,caller_id,session_id,last_seen_at) VALUES(?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),address=VALUES(address),profile=VALUES(profile),caller_id=VALUES(caller_id),session_id=VALUES(session_id),last_seen_at=NOW()");
    foreach ($currentUsers as $username => $session) {
        $upsertPresence->execute([(int)$router['id'],$username,'online',$session['address'],$session['profile'],$session['caller_id'],$session['session_id']]);
    }
    $markOffline = $pdo->prepare("UPDATE pppoe_user_presence_state SET status='offline',last_seen_at=NOW() WHERE router_id=? AND username=?");
    foreach ($presence as $username => $status) {
        if (!isset($currentUsers[$username]) && $status === 'online') $markOffline->execute([(int)$router['id'],$username]);
    }

    $pdo->prepare("DELETE FROM pppoe_disconnect_state WHERE router_id = ?")->execute([(int)$router['id']]);
    $insertState = $pdo->prepare("INSERT INTO pppoe_disconnect_state (router_id,session_key,username,address,profile,caller_id,uptime,updated_at) VALUES (?,?,?,?,?,?,?,NOW())");
    foreach ($currentSessions as $sessionKey => $session) {
        $insertState->execute([(int)$router['id'],$sessionKey,$session['name'],$session['address'],$session['profile'],$session['caller_id'],$session['uptime']]);
    }
}

if (($goneSessions || $connectEvents) && function_exists('curl_init')) {
    $payload = json_encode([
        'router_id'=>(int)$router['id'],
        'router_name'=>(string)($router['name'] ?? ('Router '.$router['id'])),
        'events'=>array_slice($goneSessions,0,50),
        'connect_events'=>array_slice($connectEvents,0,50)
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $ch = curl_init('http://127.0.0.1/api/pppoe_disconnect_notify.php');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>8]);
    curl_exec($ch); curl_close($ch);
}

$resource = $API->comm("/system/resource/print");
$resourceData = $resource[0] ?? [];
$traffic = $API->comm("/interface/monitor-traffic",["interface"=>"ether1","once"=>""]);
$trafficData = $traffic[0] ?? [];
$cpu = isset($resourceData['cpu-load']) ? (float)$resourceData['cpu-load'] : 0;
$totalMemory = isset($resourceData['total-memory']) ? (float)$resourceData['total-memory'] : 0;
$freeMemory = isset($resourceData['free-memory']) ? (float)$resourceData['free-memory'] : 0;
$memory = $totalMemory > 0 ? (($totalMemory-$freeMemory)/$totalMemory)*100 : 0;
$totalDisk = isset($resourceData['total-hdd-space']) ? (float)$resourceData['total-hdd-space'] : 0;
$freeDisk = isset($resourceData['free-hdd-space']) ? (float)$resourceData['free-hdd-space'] : 0;
$disk = $totalDisk > 0 ? (($totalDisk-$freeDisk)/$totalDisk)*100 : 0;
$rxBits = isset($trafficData['rx-bits-per-second']) ? (float)$trafficData['rx-bits-per-second'] : 0;
$txBits = isset($trafficData['tx-bits-per-second']) ? (float)$trafficData['tx-bits-per-second'] : 0;
$download = round($rxBits/1000000,2); $upload = round($txBits/1000000,2);
$alarmEngine->checkTraffic($router['id'],"ether1",$download,$upload);
$rxPacket = isset($trafficData['rx-packets-per-second']) ? (int)$trafficData['rx-packets-per-second'] : 0;
$txPacket = isset($trafficData['tx-packets-per-second']) ? (int)$trafficData['tx-packets-per-second'] : 0;
$stmt=$pdo->prepare("INSERT INTO traffic_history (router_id,interface_name,download_mbps,upload_mbps,rx_packet,tx_packet,cpu,memory,disk,created_at) VALUES (:router_id,:interface_name,:download,:upload,:rx_packet,:tx_packet,:cpu,:memory,:disk,NOW())");
$stmt->execute([':router_id'=>$router['id'],':interface_name'=>'ether1',':download'=>$download,':upload'=>$upload,':rx_packet'=>$rxPacket,':tx_packet'=>$txPacket,':cpu'=>round($cpu,2),':memory'=>round($memory,2),':disk'=>round($disk,2)]);
$API->disconnect();
echo "History saved: ".date("Y-m-d H:i:s")."<br>Download: ".$download." Mbps<br>Upload: ".$upload." Mbps<br>CPU: ".$cpu." %<br>Memory: ".$memory." %<br>Disk: ".$disk." %";
