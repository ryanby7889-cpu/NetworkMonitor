<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../Config/mikrotik.php";
require_once "../library/routeros_api.class.php";
$config=new MikroTikConfig();
$routerId=(int)($_GET['router_id']??0);
if($routerId>0){require_once "../Config/database.php";$pdo=(new Database())->connect();$stmt=$pdo->prepare('SELECT * FROM router WHERE id=? LIMIT 1');$stmt->execute([$routerId]);$router=$stmt->fetch(PDO::FETCH_ASSOC);}else{$router=$config->getRouter();}
if(!$router){echo json_encode(["status"=>"offline","message"=>"Router tidak tersedia"]);exit;}
$API=new RouterosAPI();$API->debug=false;
if(!$API->connect($router['ip_address'],$router['username'],$router['password'],$router['api_port'])){$config->updateStatus($router['id'],'OFFLINE');echo json_encode(["status"=>"offline","router_id"=>(int)$router['id'],"router"=>$router['router_name'],"message"=>"Router tidak dapat terhubung"]);exit;}
require_once "../alarm/alarm_engine.php";$alarmEngine=new AlarmEngine();$alarmEngine->routerOnline($router['id']);$config->updateStatus($router['id'],'ONLINE');
$resource=$API->comm("/system/resource/print");$identity=$API->comm("/system/identity/print");$traffic=$API->comm("/interface/monitor-traffic",["interface"=>"ether1","once"=>""]);
$r=$resource[0]??[];$i=$identity[0]??[];$t=$traffic[0]??[];$totalMemory=(float)($r['total-memory']??0);$freeMemory=(float)($r['free-memory']??0);$totalDisk=(float)($r['total-hdd-space']??0);$freeDisk=(float)($r['free-hdd-space']??0);$memory=$totalMemory>0?(($totalMemory-$freeMemory)/$totalMemory)*100:0;$disk=$totalDisk>0?(($totalDisk-$freeDisk)/$totalDisk)*100:0;$rx=(float)($t['rx-bits-per-second']??0);$tx=(float)($t['tx-bits-per-second']??0);$download=round($rx/1000000,2);$upload=round($tx/1000000,2);
// Alarm threshold for ether1 is an absolute Mbps limit configured in Settings > Alarm.
$alarmEngine->checkTraffic($router['id'],'ether1',$download,$upload);
$API->disconnect();
echo json_encode(["status"=>"online","router_id"=>(int)$router['id'],"router"=>$i['name']??$router['router_name'],"version"=>$r['version']??'-',"board"=>$r['board-name']??'-',"architecture"=>$r['architecture-name']??'-',"uptime"=>$r['uptime']??'-',"cpu"=>round((float)($r['cpu-load']??0),1),"memory"=>round($memory,1),"memory_total"=>$totalMemory,"memory_free"=>$freeMemory,"disk"=>round($disk,1),"disk_total"=>$totalDisk,"disk_free"=>$freeDisk,"download"=>$download,"upload"=>$upload,"rx_packet"=>(float)($t['rx-packets-per-second']??0),"tx_packet"=>(float)($t['tx-packets-per-second']??0)]);
