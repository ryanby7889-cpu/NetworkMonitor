<?php
require_once __DIR__ . '/../config/mikrotik.php';
require_once __DIR__ . '/../library/routeros_api.class.php';
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
function pppoe_json($ok,$msg='',$data=[],$code=200){http_response_code($code);echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function numv($v){return is_numeric($v)?(float)$v:0.0;}
function pairv($v){
    if(is_array($v)) return [numv($v[0]??0),numv($v[1]??0)];
    $v=trim((string)$v);
    if($v==='') return [0.0,0.0];
    $parts=preg_split('/[\\/]/',$v);
    return [numv($parts[0]??0),numv($parts[1]??0)];
}
function firstv($r,$keys,$default=0){foreach($keys as $k){if(isset($r[$k])&&$r[$k]!==''&&$r[$k]!==null)return $r[$k];}return $default;}
function active_print($api){
    // Do NOT send the CLI "stats" modifier here. Some RouterOS versions
    // reject it through the legacy PHP API and can return an incomplete/trap response.
    // The current PPP active record exposes bytes/packets as paired values.
    return (array)$api->comm('/ppp/active/print',[
        '.proplist'=>'.id,name,address,caller-id,uptime,service,session-id,bytes,packets,bytes-in,bytes-out,packets-in,packets-out'
    ]);
}
try{
    $config=new MikroTikConfig();
    $router=$config->getRouter();
    if(!$router) throw new Exception('Konfigurasi MikroTik tidak ditemukan.');
    $api=new RouterosAPI();
    $api->debug=false;
    if(!$api->connect($router['ip_address'],$router['username'],$router['password'],$router['api_port'])) throw new Exception('Gagal terhubung ke MikroTik.');

    $activeRows=active_print($api);
    $secretRows=(array)$api->comm('/ppp/secret/print');
    $profileRows=(array)$api->comm('/ppp/profile/print');

    $secretMap=[];
    foreach($secretRows as $r){
        if(is_array($r)) $secretMap[(string)($r['name']??'')]=[
            'profile'=>$r['profile']??'default',
            'service'=>$r['service']??'pppoe'
        ];
    }

    $active=[];$totalRx=0.0;$totalTx=0.0;
    foreach($activeRows as $r){
        if(!is_array($r)) continue;
        $name=(string)($r['name']??'-');

        // RouterOS reports PPP active traffic as bytes="tx/rx" and packets="tx/rx".
        [$txPair,$rxPair]=pairv($r['bytes']??'');
        $rx=numv(firstv($r,['bytes-in','rx-byte','rx_bytes'],0));
        $tx=numv(firstv($r,['bytes-out','tx-byte','tx_bytes'],0));
        if($rx<=0 && $rxPair>0) $rx=$rxPair;
        if($tx<=0 && $txPair>0) $tx=$txPair;

        [$pktTx,$pktRx]=pairv($r['packets']??'');
        $pin=numv(firstv($r,['packets-in','rx-packet'],0));
        $pout=numv(firstv($r,['packets-out','tx-packet'],0));
        if($pin<=0 && $pktRx>0) $pin=$pktRx;
        if($pout<=0 && $pktTx>0) $pout=$pktTx;

        $totalRx+=$rx;$totalTx+=$tx;
        $active[]=[
            'id'=>$r['.id']??'',
            'name'=>$name,
            'address'=>$r['address']??'-',
            'caller_id'=>$r['caller-id']??'-',
            'uptime'=>$r['uptime']??'-',
            'service'=>$r['service']??($secretMap[$name]['service']??'pppoe'),
            'profile'=>$secretMap[$name]['profile']??'default',
            'bytes_in'=>$rx,
            'bytes_out'=>$tx,
            'total_bytes'=>$rx+$tx,
            'packets_in'=>$pin,
            'packets_out'=>$pout,
            'session_id'=>$r['session-id']??'-'
        ];
    }

    $enabled=0;$disabled=0;$services=[];$profileUsage=[];
    foreach($secretRows as $r){
        if(!is_array($r)) continue;
        $d=(($r['disabled']??'false')==='true');
        if($d)$disabled++;else$enabled++;
        $s=$r['service']??'pppoe';$services[$s]=($services[$s]??0)+1;
        $p=$r['profile']??'default';$profileUsage[$p]=($profileUsage[$p]??0)+1;
    }

    usort($active,fn($a,$b)=>$b['total_bytes']<=>$a['total_bytes']);
    $api->disconnect();
    pppoe_json(true,'',[
        'active_count'=>count($active),
        'account_count'=>count($secretRows),
        'profile_count'=>count($profileRows),
        'enabled_accounts'=>$enabled,
        'disabled_accounts'=>$disabled,
        'total_rx_bytes'=>$totalRx,
        'total_tx_bytes'=>$totalTx,
        'total_traffic_bytes'=>$totalRx+$totalTx,
        'active'=>$active,
        'top_users'=>array_slice($active,0,5),
        'services'=>$services,
        'profile_usage'=>$profileUsage,
        'updated_at'=>date('Y-m-d H:i:s')
    ]);
}catch(Throwable $e){pppoe_json(false,$e->getMessage(),[],500);}
