<?php
require_once __DIR__ . '/../Config/database.php';
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
function out($ok,$data=[],$msg=''){echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 $db=(new Database())->connect();
 $db->exec("CREATE TABLE IF NOT EXISTS pppoe_traffic_history (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, username VARCHAR(128) NOT NULL, session_id VARCHAR(128) NULL, ip_address VARCHAR(64) NULL, interface_name VARCHAR(128) NULL, profile VARCHAR(128) NULL, bytes_in BIGINT UNSIGNED NOT NULL DEFAULT 0, bytes_out BIGINT UNSIGNED NOT NULL DEFAULT 0, packets_in BIGINT UNSIGNED NOT NULL DEFAULT 0, packets_out BIGINT UNSIGNED NOT NULL DEFAULT 0, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_user_time(username,recorded_at), KEY idx_session_time(session_id,recorded_at), KEY idx_time(recorded_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $username=trim((string)($_GET['username']??''));$range=strtolower((string)($_GET['range']??'24h'));$allowed=['1h'=>1,'6h'=>6,'24h'=>24,'7d'=>168,'30d'=>720];if(!isset($allowed[$range]))$range='24h';
 if($username==='')out(false,[],'Username wajib diisi.');
 $hours=$allowed[$range];$q=$db->prepare("SELECT username,ip_address,interface_name,profile,bytes_in,bytes_out,packets_in,packets_out,recorded_at FROM pppoe_traffic_history WHERE username=? AND recorded_at>=DATE_SUB(NOW(),INTERVAL {$hours} HOUR) ORDER BY recorded_at ASC LIMIT 2000");$q->execute([$username]);$rows=$q->fetchAll();
 $labels=[];$rx=[];$tx=[];$lastRx=null;$lastTx=null;$firstAt=null;$lastAt=null;
 foreach($rows as $r){$at=$r['recorded_at'];$labels[]=date('H:i:s',strtotime($at));$rx[]=(int)$r['bytes_in'];$tx[]=(int)$r['bytes_out'];$lastRx=(int)$r['bytes_in'];$lastTx=(int)$r['bytes_out'];$firstAt??=$at;$lastAt=$at;}
 $meta=[];if($rows){$last=end($rows);$meta=['username'=>$username,'ip_address'=>$last['ip_address'],'interface'=>$last['interface_name'],'profile'=>$last['profile']];}
 out(true,['range'=>$range,'hours'=>$hours,'records'=>count($rows),'labels'=>$labels,'rx'=>$rx,'tx'=>$tx,'meta'=>$meta,'first_at'=>$firstAt,'last_at'=>$lastAt,'latest_rx'=>$lastRx??0,'latest_tx'=>$lastTx??0]);
}catch(Throwable $e){http_response_code(500);out(false,[],$e->getMessage());}
