<?php
require_once __DIR__ . '/../config/mikrotik.php';
require_once __DIR__ . '/../library/routeros_api.class.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
function pppoe_json($success,$message='',$data=[],$code=200){http_response_code($code);echo json_encode(array_merge(['success'=>$success,'message'=>$message],$data),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
function numv($v){return is_numeric($v)?(float)$v:0.0;}
function active_print($api){
    $props='.id,name,address,caller-id,uptime,service,bytes-in,bytes-out,packets-in,packets-out,session-id';
    $rows=(array)$api->comm('/ppp/active/print',['.proplist'=>$props,'stats'=>'']);
    $has=false;foreach($rows as $r){if(is_array($r)&&((isset($r['bytes-in'])&&is_numeric($r['bytes-in']))||(isset($r['bytes-out'])&&is_numeric($r['bytes-out'])))){ $has=true;break; }}
    if(!$has){$rows=(array)$api->comm('/ppp/active/print',['stats'=>'']);}
    return $rows;
}
try{
 $config=new MikroTikConfig();$router=$config->getRouter();if(!$router)throw new Exception('Konfigurasi MikroTik tidak ditemukan.');
 $api=new RouterosAPI();$api->debug=false;if(!$api->connect($router['ip_address'],$router['username'],$router['password'],$router['api_port']))throw new Exception('Gagal terhubung ke MikroTik.');
 $activeRows=active_print($api);$secretRows=(array)$api->comm('/ppp/secret/print');$profileRows=(array)$api->comm('/ppp/profile/print');
 $secretMap=[];foreach($secretRows as $r){if(is_array($r))$secretMap[(string)($r['name']??'')]=['profile'=>$r['profile']??'default','service'=>$r['service']??'pppoe'];}
 $active=[];$totalRx=0;$totalTx=0;
 foreach($activeRows as $r){if(!is_array($r))continue;$name=(string)($r['name']??'-');$rx=numv($r['bytes-in']??($r['rx-byte']??0));$tx=numv($r['bytes-out']??($r['tx-byte']??0));$totalRx+=$rx;$totalTx+=$tx;$active[]=['id'=>$r['.id']??'','name'=>$name,'address'=>$r['address']??'-','caller_id'=>$r['caller-id']??'-','uptime'=>$r['uptime']??'-','service'=>$r['service']??($secretMap[$name]['service']??'pppoe'),'profile'=>$secretMap[$name]['profile']??'default','bytes_in'=>$rx,'bytes_out'=>$tx,'total_bytes'=>$rx+$tx,'packets_in'=>numv($r['packets-in']??0),'packets_out'=>numv($r['packets-out']??0),'session_id'=>$r['session-id']??'-'];}
 $enabled=0;$disabled=0;$services=[];$profileUsage=[];foreach($secretRows as $r){if(!is_array($r))continue;$dis=(($r['disabled']??'false')==='true');$dis?$disabled++:$enabled++;$service=$r['service']??'pppoe';$services[$service]=($services[$service]??0)+1;$profile=$r['profile']??'default';$profileUsage[$profile]=($profileUsage[$profile]??0)+1;}
 usort($active,function($a,$b){return $b['total_bytes']<=>$a['total_bytes'];});$topUsers=array_slice($active,0,5);
 $api->disconnect();
 pppoe_json(true,'',['active_count'=>count($active),'account_count'=>count($secretRows),'profile_count'=>count($profileRows),'enabled_accounts'=>$enabled,'disabled_accounts'=>$disabled,'total_rx_bytes'=>$totalRx,'total_tx_bytes'=>$totalTx,'total_traffic_bytes'=>$totalRx+$totalTx,'active'=>$active,'top_users'=>$topUsers,'services'=>$services,'profile_usage'=>$profileUsage,'updated_at'=>date('Y-m-d H:i:s')]);
}catch(Throwable $e){pppoe_json(false,$e->getMessage(),[],500);}
