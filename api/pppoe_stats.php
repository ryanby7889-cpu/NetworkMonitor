<?php
require_once __DIR__ . '/../config/mikrotik.php';
require_once __DIR__ . '/../library/routeros_api.class.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function pppoe_json($success, $message = '', $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success'=>$success,'message'=>$message],$data), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
function bytes_num($v) {
    if (is_numeric($v)) return (float)$v;
    return 0.0;
}
try {
    $config = new MikroTikConfig();
    $router = $config->getRouter();
    if (!$router) throw new Exception('Konfigurasi MikroTik tidak ditemukan.');
    $api = new RouterosAPI();
    $api->debug = false;
    if (!$api->connect($router['ip_address'],$router['username'],$router['password'],$router['api_port'])) {
        throw new Exception('Gagal terhubung ke MikroTik.');
    }
    $activeRows = (array)$api->comm('/ppp/active/print');
    $secretRows = (array)$api->comm('/ppp/secret/print');
    $profileRows = (array)$api->comm('/ppp/profile/print');

    $active = [];
    $totalRx = 0.0; $totalTx = 0.0;
    foreach ($activeRows as $r) {
        if (!is_array($r)) continue;
        $rx = bytes_num($r['bytes-in'] ?? ($r['rx-byte'] ?? 0));
        $tx = bytes_num($r['bytes-out'] ?? ($r['tx-byte'] ?? 0));
        $totalRx += $rx; $totalTx += $tx;
        $active[] = [
            'id'=>$r['.id']??'', 'name'=>$r['name']??'-', 'address'=>$r['address']??'-',
            'caller_id'=>$r['caller-id']??'-', 'uptime'=>$r['uptime']??'-', 'service'=>$r['service']??'-',
            'bytes_in'=>$rx, 'bytes_out'=>$tx, 'packets_in'=>bytes_num($r['packets-in']??0),
            'packets_out'=>bytes_num($r['packets-out']??0), 'session_id'=>$r['session-id']??'-'
        ];
    }
    $enabled=0; $disabled=0; $services=[]; $profileUsage=[];
    foreach ($secretRows as $r) {
        if (!is_array($r)) continue;
        $isDisabled=(($r['disabled']??'false')==='true');
        $isDisabled?$disabled++:$enabled++;
        $service=$r['service']??'pppoe'; $services[$service]=($services[$service]??0)+1;
        $profile=$r['profile']??'default'; $profileUsage[$profile]=($profileUsage[$profile]??0)+1;
    }
    $api->disconnect();
    pppoe_json(true,'',['active_count'=>count($active),'account_count'=>count($secretRows),'profile_count'=>count($profileRows),'enabled_accounts'=>$enabled,'disabled_accounts'=>$disabled,'total_rx_bytes'=>$totalRx,'total_tx_bytes'=>$totalTx,'active'=>$active,'services'=>$services,'profile_usage'=>$profileUsage,'updated_at'=>date('Y-m-d H:i:s')]);
} catch (Throwable $e) {
    pppoe_json(false,$e->getMessage(),[],500);
}
