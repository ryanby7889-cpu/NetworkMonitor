<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
try{
 require_once __DIR__.'/../Config/database.php';
 $pdo=(new Database())->connect();
 $u=trim((string)($_GET['username']??''));
 if($u===''){http_response_code(400);throw new Exception('Username wajib diisi');}
 $like='%'.$u.'%';
 $st=$pdo->prepare("SELECT id,alarm_type,severity,status,interface_name,message,value,threshold,created_at,resolved_at FROM alarms WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND (interface_name LIKE ? OR message LIKE ? OR (alarm_type LIKE 'pppoe%' AND message LIKE ?)) ORDER BY created_at DESC LIMIT 1000");
 $st->execute(['%pppoe-'.$u.'%',$like,$like]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
 $a=['total'=>0,'critical'=>0,'warning'=>0,'active'=>0,'download'=>0,'upload'=>0,'peak_mbps'=>0,'avg_mbps'=>0,'durations'=>[],'last'=>null];$sum=0;$n=0;
 foreach($rows as $r){$a['total']++;$sev=strtolower((string)$r['severity']);if($sev==='critical')$a['critical']++;if($sev==='warning')$a['warning']++;if(strtolower((string)$r['status'])==='active')$a['active']++;$type=strtolower((string)$r['alarm_type']);if(strpos($type,'download')!==false)$a['download']++;if(strpos($type,'upload')!==false)$a['upload']++;$v=(float)$r['value'];$a['peak_mbps']=max($a['peak_mbps'],$v);if($v>0){$sum+=$v;$n++;}if(!empty($r['resolved_at'])){$x=strtotime($r['created_at']);$y=strtotime($r['resolved_at']);if($x&&$y&&$y>=$x)$a['durations'][]=($y-$x)/60;}if($a['last']===null)$a['last']=$r['created_at'];}
 $a['avg_mbps']=$n?round($sum/$n,2):0;$a['avg_duration_min']=$a['durations']?round(array_sum($a['durations'])/count($a['durations']),1):0;unset($a['durations']);
 $score=100-min(35,$a['total']*4)-min(25,$a['critical']*8)-min(15,$a['active']*10);$health=$score>=80?'HEALTHY':($score>=60?'WARNING':'CRITICAL');
 echo json_encode(['success'=>true,'username'=>$u,'health'=>['score'=>max(0,$score),'status'=>$health],'alarms'=>$a,'recent'=>array_slice($rows,0,10)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
