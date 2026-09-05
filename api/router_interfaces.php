<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Config/mikrotik.php';
require_once __DIR__ . '/../library/routeros_api.class.php';
try {
  $config=new MikroTikConfig();
  $routerId=(int)($_GET['router_id']??0);
  if($routerId>0){require_once __DIR__ . '/../Config/database.php';$pdo=(new Database())->connect();$s=$pdo->prepare('SELECT * FROM router WHERE id=? LIMIT 1');$s->execute([$routerId]);$router=$s->fetch(PDO::FETCH_ASSOC);}else{$router=$config->getRouter();}
  if(!$router){echo json_encode(['status'=>'offline','message'=>'Router tidak tersedia']);exit;}
  $api=new RouterosAPI();$api->debug=false;
  if(!$api->connect($router['ip_address'],$router['username'],$router['password'],$router['api_port'])){echo json_encode(['status'=>'offline','router_id'=>(int)$router['id'],'message'=>'Router tidak dapat terhubung']);exit;}
  $items=$api->comm('/interface/print');$api->disconnect();$rows=[];
  foreach($items as $x){$rx=(float)($x['rx-byte']??0);$tx=(float)($x['tx-byte']??0);$rows[]=['id'=>$x['.id']??'','name'=>$x['name']??'-','type'=>$x['type']??'-','running'=>isset($x['running']),'disabled'=>isset($x['disabled']),'mtu'=>$x['mtu']??'-','mac'=>$x['mac-address']??'-','rx_bytes'=>$rx,'tx_bytes'=>$tx,'rx_bps'=>0,'tx_bps'=>0];}
  echo json_encode(['status'=>'online','router_id'=>(int)$router['id'],'router'=>$router['router_name'],'interfaces'=>$rows]);
}catch(Throwable $e){http_response_code(500);echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}