<?php
require_once __DIR__ . '/../Config/database.php';
ini_set('display_errors','0');
$username=trim((string)($_GET['username']??''));
$range=strtolower((string)($_GET['range']??'24h'));
$allowed=['1h'=>1,'6h'=>6,'24h'=>24,'7d'=>168,'30d'=>720];
if(!isset($allowed[$range]))$range='24h';
if($username===''){http_response_code(400);exit('Username wajib diisi.');}
try{
 $db=(new Database())->connect();
 $q=$db->prepare("SELECT recorded_at,username,ip_address,interface_name,profile,bytes_in,bytes_out,packets_in,packets_out FROM pppoe_traffic_history WHERE username=? AND recorded_at>=DATE_SUB(NOW(),INTERVAL {$allowed[$range]} HOUR) ORDER BY recorded_at ASC LIMIT 10000");
 $q->execute([$username]);
 $rows=$q->fetchAll(PDO::FETCH_ASSOC);
 $filename='pppoe_history_'.preg_replace('/[^A-Za-z0-9_-]+/','_', $username).'_'.$range.'_'.date('Ymd_His').'.csv';
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');
 $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
 fputcsv($out,['Waktu','Username','IP Address','Interface','Profile','RX Cumulative (Bytes)','TX Cumulative (Bytes)','RX Packets','TX Packets','Download (bps)','Upload (bps)']);
 $prev=null;
 foreach($rows as $r){$ts=strtotime($r['recorded_at']);$rx=(int)$r['bytes_in'];$tx=(int)$r['bytes_out'];$down=0;$up=0;if($prev){$dt=max(1,$ts-$prev['ts']);$up=max(0,$rx-$prev['rx'])*8/$dt;$down=max(0,$tx-$prev['tx'])*8/$dt;}fputcsv($out,[date('Y-m-d H:i:s',$ts),$r['username'],$r['ip_address'],$r['interface_name'],$r['profile'],$rx,$tx,$r['packets_in'],$r['packets_out'],round($down,2),round($up,2)]);$prev=['ts'=>$ts,'rx'=>$rx,'tx'=>$tx];}
 fclose($out);
}catch(Throwable $e){http_response_code(500);echo 'Export gagal: '.$e->getMessage();}
