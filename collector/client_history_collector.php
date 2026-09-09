<?php
if(PHP_SAPI!=='cli' && !in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'], true)){http_response_code(403);exit('Local only');}
require_once __DIR__.'/../Config/mikrotik.php';require_once __DIR__.'/../Config/database.php';require_once __DIR__.'/../library/routeros_api.class.php';date_default_timezone_set('Asia/Jakarta');
try{
 $pdo=(new Database())->connect();$pdo->exec("SET time_zone='+07:00'");
 $pdo->exec("CREATE TABLE IF NOT EXISTS client_traffic_history (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,router_id INT NOT NULL,client_ip VARCHAR(64) NOT NULL,client_name VARCHAR(255) NOT NULL,source VARCHAR(64) NOT NULL,download_mbps DECIMAL(14,4) NOT NULL DEFAULT 0,upload_mbps DECIMAL(14,4) NOT NULL DEFAULT 0,snapshot_at DATETIME NOT NULL,PRIMARY KEY(id),KEY idx_router_client_time(router_id,client_ip,snapshot_at),KEY idx_snapshot(snapshot_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $router=(new MikroTikConfig())->getRouter();if(!$router)throw new RuntimeException('Router tidak tersedia');
 $api=new RouterosAPI();$api->debug=false;if(!$api->connect($router['ip_address'],$router['username'],$router['password'],$router['api_port']))throw new RuntimeException('Router tidak dapat terhubung');
 $clients=[];
 foreach($api->comm('/queue/simple/print',['?disabled'=>'no']) as $q){$target=trim((string)($q['target']??''));$raw=trim(explode('/',$target)[0]??'');$ip=preg_replace('/^<|>$/','',$raw);$rate=explode('/',(string)($q['rate']??'0/0'));if(filter_var($ip,FILTER_VALIDATE_IP)){$clients[$ip]=['ip'=>$ip,'name'=>trim((string)($q['name']??$ip)),'source'=>'Simple Queue','d'=>(float)($rate[0]??0)*8/1000000,'u'=>(float)($rate[1]??0)*8/1000000];}}
 foreach($api->comm('/ip/hotspot/active/print') as $x){$ip=trim((string)($x['address']??''));if(filter_var($ip,FILTER_VALIDATE_IP)){if(isset($clients[$ip])){$clients[$ip]['name']=trim((string)($x['user']??$clients[$ip]['name']));$clients[$ip]['source']='HotSpot + Queue';}else{$clients[$ip]=['ip'=>$ip,'name'=>trim((string)($x['user']??$ip)),'source'=>'HotSpot','d'=>0,'u'=>0];}}}
 foreach($api->comm('/ppp/active/print') as $x){$ip=trim((string)($x['address']??''));if(filter_var($ip,FILTER_VALIDATE_IP)){if(isset($clients[$ip])){$clients[$ip]['name']=trim((string)($x['name']??$clients[$ip]['name']));$clients[$ip]['source']='PPPoE + Queue';}else{$clients[$ip]=['ip'=>$ip,'name'=>trim((string)($x['name']??$ip)),'source'=>'PPPoE','d'=>0,'u'=>0];}}}
 $api->disconnect();
 $ins=$pdo->prepare('INSERT INTO client_traffic_history(router_id,client_ip,client_name,source,download_mbps,upload_mbps,snapshot_at) VALUES(?,?,?,?,?,?,NOW())');$count=0;foreach($clients as $c){$ins->execute([(int)$router['id'],$c['ip'],$c['name'],$c['source'],round($c['d'],4),round($c['u'],4)]);$count++;}
 $pdo->exec("DELETE FROM client_traffic_history WHERE snapshot_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");echo "Client history saved: {$count}";
}catch(Throwable $e){http_response_code(500);echo 'Client history error: '.$e->getMessage();}
