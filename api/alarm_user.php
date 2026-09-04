<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
try {
 require_once __DIR__.'/../Config/database.php';
 $pdo=(new Database())->connect();
 $username=trim((string)($_GET['username']??''));
 if($username===''){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Username wajib diisi']);exit;}
 $st=$pdo->prepare("SELECT id,interface_name,alarm_type,severity,message,value,threshold,status,created_at,resolved_at FROM alarms WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND (interface_name=? OR interface_name=? OR message LIKE ?) ORDER BY created_at DESC");
 $st->execute(['<pppoe-'.$username.'>','pppoe-'.$username.'','%'.$username.'%']);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
 $stats=['total'=>0,'critical'=>0,'warning'=>0,'active'=>0,'download'=>0,'upload'=>0,'peak_mbps'=>0,'avg_mbps'=>0,'avg_duration_min'=>0,'max_duration_min'=>0];$dur=[];$sum=0;
 foreach($rows as &$r){$stats['total']++;$sev=strtolower((string)$r['severity']);if(isset($stats[$sev]))$stats[$sev]++;if(strtolower((string)$r['status'])==='active')$stats['active']++;$type=strtolower((string)$r['alarm_type']);$msg=(string)$r['message'];if(strpos($type,'download')!==false||preg_match('/\bdownload\b/i',$msg))$stats['download']++;if(strpos($type,'upload')!==false||preg_match('/\bupload\b/i',$msg))$stats['upload']++;$value=(float)$r['value'];$sum+=$value;$stats['peak_mbps']=max($stats['peak_mbps'],$value);if(!empty($r['resolved_at'])){$a=strtotime($r['created_at']);$b=strtotime($r['resolved_at']);if($a&&$b&&$b>=$a){$m=($b-$a)/60;$dur[]=$m;}}$r['direction']=strpos($type,'download')!==false?'DOWNLOAD':(strpos($type,'upload')!==false?'UPLOAD':'-');}
 unset($r);$stats['avg_mbps']=$rows?round($sum/count($rows),2):0;$stats['avg_duration_min']=$dur?round(array_sum($dur)/count($dur),1):0;$stats['max_duration_min']=$dur?round(max($dur),1):0;
 echo json_encode(['success'=>true,'username'=>$username,'stats'=>$stats,'alarms'=>array_slice($rows,0,30)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>'User alarm intelligence unavailable']);}
