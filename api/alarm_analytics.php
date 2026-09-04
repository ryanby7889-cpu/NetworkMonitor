<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
try {
 require_once __DIR__.'/../Config/database.php'; $pdo=(new Database())->connect();
 $rows=$pdo->query("SELECT alarm_type,severity,status,interface_name,value,threshold,created_at,resolved_at FROM alarms WHERE created_at >= DATE_SUB(NOW(),INTERVAL 30 DAY) ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
 $summary=['total'=>count($rows),'critical'=>0,'warning'=>0,'active'=>0,'resolved'=>0,'pppoe'=>0,'ether1'=>0,'download'=>0,'upload'=>0];$users=[];$daily=[];$durations=[];
 foreach($rows as $r){$sev=strtolower((string)$r['severity']);$type=strtolower((string)$r['alarm_type']);$iface=(string)$r['interface_name'];$status=strtolower((string)$r['status']);if($sev==='critical')$summary['critical']++;if($sev==='warning')$summary['warning']++;if($status==='active')$summary['active']++;else $summary['resolved']++;$isP=strpos($type,'pppoe')!==false||stripos($iface,'pppoe-')===0||stripos($iface,'pppoe')===0;if($isP)$summary['pppoe']++;else $summary['ether1']++;if(strpos($type,'download')!==false)$summary['download']++;if(strpos($type,'upload')!==false)$summary['upload']++;$day=date('Y-m-d',strtotime($r['created_at']));if(!isset($daily[$day]))$daily[$day]=['date'=>$day,'critical'=>0,'warning'=>0];$daily[$day][$sev]=($daily[$day][$sev]??0)+1;
  if($isP){$u=null;$clean=trim($iface," <>\t\n\r\0\x0B");if(preg_match('/^pppoe-(.+)$/i',$clean,$m))$u=$m[1];if(!$u&&preg_match('/^PPPoE\s+(.+?)\s+(?:download|upload)\s+/i',(string)$r['message'],$m))$u=trim($m[1]);if($u){if(!isset($users[$u]))$users[$u]=['username'=>$u,'total'=>0,'critical'=>0,'warning'=>0,'download'=>0,'upload'=>0,'active'=>0];$users[$u]['total']++;$users[$u][$sev]++;if(strpos($type,'download')!==false)$users[$u]['download']++;if(strpos($type,'upload')!==false)$users[$u]['upload']++;if($status==='active')$users[$u]['active']++;}}
  if($status!=='active'&&$r['resolved_at']){$a=strtotime($r['created_at']);$b=strtotime($r['resolved_at']);if($a&&$b&&$b>=$a)$durations[]=($b-$a)/60;}
 }
 usort($users,fn($a,$b)=>$b['total']<=>$a['total']);$summary['avg_duration_min']=$durations?round(array_sum($durations)/count($durations),1):0;$summary['max_duration_min']=$durations?round(max($durations),1):0;
 $daily=array_values($daily);$labels=[];$critical=[];$warning=[];foreach($daily as $d){$labels[]=$d['date'];$critical[]=$d['critical'];$warning[]=$d['warning'];}
 echo json_encode(['success'=>true,'summary'=>$summary,'users'=>array_slice($users,0,10),'daily'=>['labels'=>$labels,'critical'=>$critical,'warning'=>$warning]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>'Analytics unavailable']);}
