<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__.'/../Config/database.php';
try{
 $pdo=(new Database())->connect();
 try{$pdo->exec("ALTER TABLE pppoe_disconnect_history ADD COLUMN uptime_seconds INT NULL AFTER uptime");}catch(Throwable $ignore){}
 $rid=(int)($_GET['router_id']??0);
 $filter=$rid>0?' AND router_id=:rid':'';$p=$rid>0?[':rid'=>$rid]:[];
 $sql="SELECT username,COUNT(*) disconnects,AVG(NULLIF(uptime_seconds,0)) avg_uptime_seconds,MAX(disconnected_at) last_disconnect FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)".$filter." GROUP BY username ORDER BY disconnects DESC,username";
 $q=$pdo->prepare($sql);$q->execute($p);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 $out=[];
 foreach($rows as $r){
  $d=(int)$r['disconnects'];$avg=$r['avg_uptime_seconds']!==null?(int)round((float)$r['avg_uptime_seconds']):null;
  $score=100-min(70,$d*7);
  if($avg!==null&&$avg<3600)$score-=20;
  elseif($avg!==null&&$avg<7200)$score-=10;
  $score=max(0,$score);
  if($score<40||$d>=10)$level='critical';elseif($score<70||$d>=5)$level='warning';elseif($d>=2)$level='attention';else $level='normal';
  $out[]=['username'=>(string)$r['username'],'disconnects'=>$d,'avg_uptime_seconds'=>$avg,'last_disconnect'=>$r['last_disconnect'],'score'=>$score,'level'=>$level];
 }
 echo json_encode(['success'=>true,'router_id'=>$rid,'users'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
