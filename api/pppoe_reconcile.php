<?php
require_once __DIR__ . '/../Config/mikrotik.php';
require_once __DIR__ . '/../library/routeros_api.class.php';
require_once __DIR__ . '/../Config/database.php';

ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');

function reconcile_json($ok,$message='',$data=[],$code=200){
    http_response_code($code);
    echo json_encode(array_merge(['success'=>$ok,'message'=>$message],$data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function norm_alarm_if($v){ return strtolower(trim(trim((string)$v),'<>')); }

try {
    $config = new MikroTikConfig();
    $router = $config->getRouter();
    if (!$router) throw new Exception('Konfigurasi MikroTik tidak ditemukan.');

    $api = new RouterosAPI();
    $api->debug = false;
    if (!$api->connect($router['ip_address'],$router['username'],$router['password'],$router['api_port'])) {
        throw new Exception('Gagal terhubung ke MikroTik.');
    }

    $rows = (array)$api->comm('/ppp/active/print',['.proplist'=>'name']);
    $active = [];
    foreach($rows as $row){
        if(!is_array($row)) continue;
        $name = trim((string)($row['name']??''));
        if($name==='') continue;
        $n = norm_alarm_if($name);
        $active[$n] = true;
        $active['pppoe-'.$n] = true;
        $active['<pppoe-'.$n.'>'] = true;
    }

    $api->disconnect();

    $db = (new Database())->connect();
    $stmt = $db->prepare("SELECT id,interface_name FROM alarms WHERE router_id=:router_id AND status='active' AND alarm_type IN ('pppoe_bandwidth','pppoe_bandwidth_download','pppoe_bandwidth_upload')");
    $stmt->execute([':router_id'=>$router['id']]);
    $resolve = $db->prepare("UPDATE alarms SET status='resolved',resolved_at=NOW() WHERE id=:id AND status='active'");
    $resolved = 0;
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $alarm){
        $key = norm_alarm_if($alarm['interface_name']??'');
        $isActive = isset($active[$key]) || (strpos($key,'pppoe-')===0 && isset($active[substr($key,6)]));
        if(!$isActive){
            $resolve->execute([':id'=>$alarm['id']]);
            $resolved += $resolve->rowCount();
        }
    }

    reconcile_json(true,'PPPoE alarm reconciliation selesai',['active_sessions'=>count($rows),'resolved_alarms'=>$resolved,'checked_at'=>date('Y-m-d H:i:s')]);
}catch(Throwable $e){
    reconcile_json(false,$e->getMessage(),[],500);
}
