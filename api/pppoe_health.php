<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
try {
 require_once __DIR__.'/../Config/database.php';
 $pdo=(new Database())->connect();
 $rows=$pdo->query("SELECT alarm_type,severity,status,interface_name,message,value,threshold,created_at,resolved_at FROM alarms WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND (LOWER(alarm_type) LIKE 'pppoe%' OR LOWER(interface_name) LIKE '%pppoe%') ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
 $users=[];
 foreach($rows as $r){$iface=trim((string)$r['interface_name']," <>\t\n\r\0\x0B");$u=null;if(preg_match('/^pppoe-(.+)$/i',$iface,$m))$u=$m[1];elseif(preg_match('/^PPPoE\s+(.+?)\s+(?:download|upload)\s+/i',(string)$r['message'],$m))$u=trim($m[1]);if(!$u)continue;if(!isset($users[$u]))$users[$u]=['username'=>$u,'total'=>0,'critical'=>0,'warning'=>0,'active'=>0,'download'=>0,'upload'=>0,'sum_ratio'=>0,'peak_ratio'=>0,'durations'=>[],'last_alarm'=>$r['created_at']];$x=&$users[$u];$x['total']++;$sev=strtolower((string)$r['severity']);if(isset($x[$sev]))$x[$sev]++;if(strtolower((string)$r['status'])==='active')$x['active']++;$type=strtolower((string)$r['alarm_type']);$dir=strpos($type,'download')!==false?'download':(strpos($type,'upload')!==false?'upload':null);if($dir)$x[$dir]++;$v=(float)$r['value'];$t=(float)$r['threshold'];$ratio=$t>0?$v/$t:0;$x['sum_ratio']+=$ratio;$x['peak_ratio']=max($x['peak_ratio'],$ratio);if(!empty($r['resolved_at'])){$a=strtotime($r['created_at']);$b=strtotime($r['resolved_at']);if($a&&$b&&$b>=$a)$x['durations'][]=($b-$a)/60;}}
 unset($x);
 foreach($users as &$x){$avgRatio=$x['total']?$x['sum_ratio']/$x['total']:0;$avgDur=$x['durations']?array_sum($x['durations'])/count($x['durations']):0;$score=100;$score-=min(35,$x['total']*4);$score-=min(25,$x['critical']*8);$score-=min(15,$x['active']*10);$score-=min(15,$avgRatio*15);$score-=min(10,$avgDur/10);$score=max(0,min(100,round($score)));$x['score']=$score;$x['health']=$score>=80?'HEALTHY':($score>=60?'WARNING':'CRITICAL');$x['avg_usage_percent']=round($avgRatio*100,1);$x['peak_usage_percent']=round($x['peak_ratio']*100,1);$x['avg_duration_min']=round($avgDur,1);unset($x['sum_ratio'],$x['peak_ratio'],$x['durations']);}
unset($x);usort($users,fn($a,$b)=>$a['score']<=>$b['score']);echo json_encode(['success'=>true,'period'=>'30d','users'=>array_values($users)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>'Health score unavailable']);}
