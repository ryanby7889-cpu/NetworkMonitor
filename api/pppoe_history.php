<?php
require_once __DIR__ . '/../Config/database.php';
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
function out($ok,$data=[],$msg=''){echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 $db=(new Database())->connect();
 $db->exec("CREATE TABLE IF NOT EXISTS pppoe_traffic_history (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, username VARCHAR(128) NOT NULL, session_id VARCHAR(128) NULL, ip_address VARCHAR(64) NULL, interface_name VARCHAR(128) NULL, profile VARCHAR(128) NULL, bytes_in BIGINT UNSIGNED NOT NULL DEFAULT 0, bytes_out BIGINT UNSIGNED NOT NULL DEFAULT 0, packets_in BIGINT UNSIGNED NOT NULL DEFAULT 0, packets_out BIGINT UNSIGNED NOT NULL DEFAULT 0, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_user_time(username,recorded_at), KEY idx_session_time(session_id,recorded_at), KEY idx_time(recorded_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $username=trim((string)($_GET['username']??''));$range=strtolower((string)($_GET['range']??'24h'));$allowed=['1h'=>1,'6h'=>6,'24h'=>24,'7d'=>168,'30d'=>720];if(!isset($allowed[$range]))$range='24h';
 if($username==='')out(false,[],'Username wajib diisi.');$hours=$allowed[$range];
 $q=$db->prepare("SELECT username,ip_address,interface_name,profile,bytes_in,bytes_out,packets_in,packets_out,recorded_at FROM pppoe_traffic_history WHERE username=? AND recorded_at>=DATE_SUB(NOW(),INTERVAL {$hours} HOUR) ORDER BY recorded_at ASC LIMIT 2000");$q->execute([$username]);$rows=$q->fetchAll();
 $labels=[];$downloadSpeed=[];$uploadSpeed=[];$details=[];$lastRx=null;$lastTx=null;$firstAt=null;$lastAt=null;$peakDownload=0;$peakUpload=0;$sumDownload=0;$sumUpload=0;$speedCount=0;$prev=null;
 foreach($rows as $r){
   $at=$r['recorded_at'];$ts=strtotime($at);$rx=(int)$r['bytes_in'];$tx=(int)$r['bytes_out'];$labels[]=date('H:i:s',$ts);$download=0;$upload=0;
   if($prev){$dt=max(1,$ts-$prev['ts']);
     /* RouterOS interface counters: TX = router -> customer (download), RX = customer -> router (upload). */
     $upload=max(0,$rx-$prev['rx'])*8/$dt;$download=max(0,$tx-$prev['tx'])*8/$dt;
     if($dt<=120){$peakDownload=max($peakDownload,$download);$peakUpload=max($peakUpload,$upload);$sumDownload+=$download;$sumUpload+=$upload;$speedCount++;}
   }
   $downloadSpeed[]=round($download,2);$uploadSpeed[]=round($upload,2);$details[]=['time'=>date('Y-m-d H:i:s',$ts),'rx'=>$rx,'tx'=>$tx,'download_bps'=>round($download,2),'upload_bps'=>round($upload,2)];$prev=['ts'=>$ts,'rx'=>$rx,'tx'=>$tx];$lastRx=$rx;$lastTx=$tx;$firstAt??=$at;$lastAt=$at;
 }
 $meta=[];if($rows){$last=end($rows);$meta=['username'=>$username,'ip_address'=>$last['ip_address'],'interface'=>$last['interface_name'],'profile'=>$last['profile']];}
 $avgDownload=$speedCount?$sumDownload/$speedCount:0;$avgUpload=$speedCount?$sumUpload/$speedCount:0;
 out(true,['range'=>$range,'hours'=>$hours,'records'=>count($rows),'labels'=>$labels,'download_speed'=>$downloadSpeed,'upload_speed'=>$uploadSpeed,'details'=>$details,'meta'=>$meta,'first_at'=>$firstAt,'last_at'=>$lastAt,'latest_rx'=>$lastRx??0,'latest_tx'=>$lastTx??0,'average_download_bps'=>round($avgDownload,2),'average_upload_bps'=>round($avgUpload,2),'peak_download_bps'=>round($peakDownload,2),'peak_upload_bps'=>round($peakUpload,2)]);
}catch(Throwable $e){http_response_code(500);out(false,[],$e->getMessage());}
