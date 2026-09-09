<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Config/mikrotik.php';
require_once __DIR__ . '/../library/routeros_api.class.php';
require_once __DIR__ . '/../Config/database.php';

function clientBytes($v){$p=explode('/',(string)$v);return ['rx'=>(float)($p[0]??0),'tx'=>(float)($p[1]??0)];}
function rateMbps($v){$p=explode('/',(string)$v);return ['rx'=>(float)($p[0]??0)*8/1000000,'tx'=>(float)($p[1]??0)*8/1000000];}
function cleanClientName($v){$v=trim((string)$v);if(strlen($v)>=2 && $v[0]==='<' && substr($v,-1)==='>')$v=substr($v,1,-1);return $v;}
function isIpTarget($v){return (bool)filter_var(trim($v),FILTER_VALIDATE_IP);}
try{
    $routerId=(int)($_GET['router_id']??0);$pdo=(new Database())->connect();
    if($routerId>0){$s=$pdo->prepare('SELECT * FROM router WHERE id=? LIMIT 1');$s->execute([$routerId]);$router=$s->fetch(PDO::FETCH_ASSOC);}else{$router=(new MikroTikConfig())->getRouter();}
    if(!$router){echo json_encode(['success'=>false,'message'=>'Router tidak tersedia']);exit;}
    $api=new RouterosAPI();$api->debug=false;
    if(!$api->connect($router['ip_address'],$router['username'],$router['password'],$router['api_port'])){echo json_encode(['success'=>false,'message'=>'Router tidak dapat terhubung']);exit;}
    $clients=[];$pppActive=[];$hotspotActive=[];

    // Collect active sessions first so dynamic PPPoE queue targets such as <pppoe-user> can be resolved to their real IP.
    foreach($api->comm('/ppp/active/print') as $x){$ip=(string)($x['address']??'');$name=cleanClientName($x['name']??$ip);if($ip)$pppActive[strtolower($name)]=['ip'=>$ip,'name'=>$name];}
    foreach($api->comm('/ip/hotspot/active/print') as $x){$ip=(string)($x['address']??'');$name=cleanClientName($x['user']??$ip);if($ip)$hotspotActive[$ip]=['name'=>$name,'bytes_in'=>(float)($x['bytes-in']??0),'bytes_out'=>(float)($x['bytes-out']??0)];}

    // Preferred source: Simple Queue statistics. Resolve IP targets directly; resolve dynamic PPPoE interface targets by active username.
    $queues=$api->comm('/queue/simple/print',['?disabled'=>'no']);
    foreach($queues as $q){
        $target=trim((string)($q['target']??''));$queueName=cleanClientName($q['name']??'');$address='';$displayName=$queueName;
        $targetBase=trim(explode('/',$target)[0]??'');
        if(isIpTarget($targetBase)){$address=$targetBase;}elseif($targetBase!=='' && isset($pppActive[strtolower(cleanClientName($targetBase))])){$address=$pppActive[strtolower(cleanClientName($targetBase))]['ip'];$displayName=$pppActive[strtolower(cleanClientName($targetBase))]['name'];}
        if(!$address && $targetBase==='0.0.0.0')continue;
        $key=$address!==''?$address:'queue:'.strtolower($targetBase?:$queueName);
        $b=clientBytes($q['bytes']??'0/0');$r=rateMbps($q['rate']??'0/0');
        $clients[$key]=['ip'=>$address,'name'=>$displayName?:($address?:$targetBase),'source'=>'Simple Queue','rx_bytes'=>$b['rx'],'tx_bytes'=>$b['tx'],'rx_mbps'=>$r['rx'],'tx_mbps'=>$r['tx'],'connections'=>0];
    }

    foreach($hotspotActive as $ip=>$x){
        if(isset($clients[$ip])){$clients[$ip]['name']=$x['name'];$clients[$ip]['source']='HotSpot + Queue';}
        else $clients[$ip]=['ip'=>$ip,'name'=>$x['name'],'source'=>'HotSpot','rx_bytes'=>$x['bytes_in'],'tx_bytes'=>$x['bytes_out'],'rx_mbps'=>0,'tx_mbps'=>0,'connections'=>0];
    }
    foreach($pppActive as $x){
        $ip=$x['ip'];if(isset($clients[$ip])){$clients[$ip]['name']=$x['name'];$clients[$ip]['source']='PPPoE + Queue';}
        else $clients[$ip]=['ip'=>$ip,'name'=>$x['name'],'source'=>'PPPoE','rx_bytes'=>0,'tx_bytes'=>0,'rx_mbps'=>0,'tx_mbps'=>0,'connections'=>0];
    }

    try{foreach($api->comm('/ip/firewall/connection/print',['.proplist'=>'src-address,dst-address']) as $c){$src=(string)($c['src-address']??'');$ip=preg_replace('/:\d+$/','',$src);if(isset($clients[$ip]))$clients[$ip]['connections']++;}}catch(Throwable $ignore){}
    $api->disconnect();$rows=array_values($clients);
    foreach($rows as &$r){$r['total_mbps']=$r['rx_mbps']+$r['tx_mbps'];$r['total_bytes']=$r['rx_bytes']+$r['tx_bytes'];}unset($r);
    usort($rows,function($a,$b){return $b['total_mbps']<=>$a['total_mbps'];});
    echo json_encode(['success'=>true,'router_id'=>(int)($router['id']??$routerId),'router'=>$router['router_name']??'Router','snapshot_at'=>date('Y-m-d H:i:s'),'client_count'=>count($rows),'clients'=>$rows],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
