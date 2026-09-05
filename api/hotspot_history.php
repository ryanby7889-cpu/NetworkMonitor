<?php
declare(strict_types=1);
require_once __DIR__ . '/../Config/database.php';
header('Content-Type: application/json; charset=utf-8');
function hh_json(bool $ok,string $msg='',array $data=[]):void{echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 $user=trim((string)($_GET['username']??'')); if($user==='') hh_json(false,'Username Hotspot wajib diisi.');
 $range=(string)($_GET['range']??'24h'); $allowed=['1h','6h','24h','7d','30d']; if(!in_array($range,$allowed,true))$range='24h';
 $pdo=(new Database())->connect();
 $pdo->exec("CREATE TABLE IF NOT EXISTS hotspot_traffic_user_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,router_id INT NULL,username VARCHAR(100) NOT NULL,session_id VARCHAR(120) NOT NULL DEFAULT '',recorded_at DATETIME NOT NULL,bytes_in BIGINT UNSIGNED NOT NULL DEFAULT 0,bytes_out BIGINT UNSIGNED NOT NULL DEFAULT 0,upload_bps DECIMAL(20,2) NOT NULL DEFAULT 0,download_bps DECIMAL(20,2) NOT NULL DEFAULT 0,address VARCHAR(64) NOT NULL DEFAULT '',interface_name VARCHAR(150) NOT NULL DEFAULT '',KEY idx_huh_router_user_time(router_id,username,recorded_at),KEY idx_huh_user_session_time(username,session_id,recorded_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $minutes=['1h'=>60,'6h'=>360,'24h'=>1440,'7d'=>10080,'30d'=>43200][$range];
 $q=$pdo->prepare("SELECT recorded_at,bytes_in,bytes_out,upload_bps,download_bps,address,interface_name,session_id FROM hotspot_traffic_user_history WHERE username=? AND recorded_at>=DATE_SUB(NOW(),INTERVAL {$minutes} MINUTE) ORDER BY recorded_at ASC LIMIT 5000");$q->execute([$user]);$rows=$q->fetchAll();
 $latest=end($rows)?:null;$downs=array_map(fn($r)=>(float)$r['download_bps'],$rows);$ups=array_map(fn($r)=>(float)$r['upload_bps'],$rows);
 $avgD=$downs?array_sum($downs)/count($downs):0;$avgU=$ups?array_sum($ups)/count($ups):0;$peakD=$downs?max($downs):0;$peakU=$ups?max($ups):0;
 $details=[];$labels=[];$d=[];$u=[];
 foreach($rows as $r){$labels[]=date('H:i:s',strtotime($r['recorded_at']));$d[]=(float)$r['download_bps'];$u[]=(float)$r['upload_bps'];$details[]=['time'=>$r['recorded_at'],'rx'=>$r['bytes_in'],'tx'=>$r['bytes_out'],'download_bps'=>(float)$r['download_bps'],'upload_bps'=>(float)$r['upload_bps'],'address'=>$r['address'],'interface'=>$r['interface_name'],'session_id'=>$r['session_id']];}
 hh_json(true,'',['username'=>$user,'range'=>$range,'records'=>count($rows),'latest_rx'=>$latest['bytes_in']??'0','latest_tx'=>$latest['bytes_out']??'0','average_download_bps'=>$avgD,'average_upload_bps'=>$avgU,'peak_download_bps'=>$peakD,'peak_upload_bps'=>$peakU,'labels'=>$labels,'download_speed'=>$d,'upload_speed'=>$u,'details'=>$details]);
}catch(Throwable $e){hh_json(false,$e->getMessage());}
