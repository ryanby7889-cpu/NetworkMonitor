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
 $hours=$allowed[$range];
 $q=$db->prepare("SELECT username,ip_address,interface_name,profile,bytes_in,bytes_out,packets_in,packets_out,recorded_at FROM pppoe_traffic_history WHERE username=? AND recorded_at>=DATE_SUB(NOW(),INTERVAL {$hours} HOUR) ORDER BY recorded_at ASC LIMIT 2000");$q->execute([$username]);$rows=$q->fetchAll();
 $labels=[];$rx=[];$tx=[];$rxSpeed=[];$txSpeed=[];$details=[];$lastRx=null;$lastTx=null;$firstAt=null;$lastAt=null;$peakRx=0;$peakTx=0;$sumRx=0;$sumTx=0;$speedCount=0;$prev=null;
 foreach($rows as $r){
   $at=$r['recorded_at'];$ts=strtotime($at);$in=(int)$r['bytes_in'];$outb=(int)$r['bytes_out'];$labels[]=date('H:i:s',$ts);$rx[]=$in;$tx[]=$outb;
   $rin=0;$rout=0;$dt=0;
   if($prev){$dt=max(1,$ts-$prev['ts']);$rin=max(0,$in-$prev['in'])*8/$dt;$rout=max(0,$outb-$prev['out'])*8/$dt;if($dt<=120){$peakRx=max($peakRx,$rin);$peakTx=max($peakTx,$rout);$sumRx+=$rin;$sumTx+=$rout;$speedCount++;}}
   $rxSpeed[]=round($rin,2);$txSpeed[]=round($rout,2);$details[]=['time'=>date('Y-m-d H:i:s',$ts),'rx'=>$in,'tx'=>$outb,'rx_bps'=>round($rin,2),'tx_bps'=>round($rout,2)];$prev=['ts'=>$ts,'in'=>$in,'out'=>$outb];$lastRx=$in;$lastTx=$outb;$firstAt??=$at;$lastAt=$at;
 }
 $meta=[];if($rows){$last=end($rows);$meta=['username'=>$username,'ip_address'=>$last['ip_address'],'interface'=>$last['interface_name'],'profile'=>$last['profile']];}
 $avgRx=$speedCount?$sumRx/$speedCount:0;$avgTx=$speedCount?$sumTx/$speedCount:0;
 out(true,['range'=>$range,'hours'=>$hours,'records'=>count($rows),'labels'=>$labels,'rx'=>$rx,'tx'=>$tx,'rx_speed'=>$rxSpeed,'tx_speed'=>$txSpeed,'details'=>$details,'meta'=>$meta,'first_at'=>$firstAt,'last_at'=>$lastAt,'latest_rx'=>$lastRx??0,'latest_tx'=>$lastTx??0,'average_rx_bps'=>round($avgRx,2),'average_tx_bps'=>round($avgTx,2),'peak_rx_bps'=>round($peakRx,2),'peak_tx_bps'=>round($peakTx,2)]);
}catch(Throwable $e){http_response_code(500);out(false,[],$e->getMessage());}
