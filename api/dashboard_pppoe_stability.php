<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__.'/../Config/database.php';
try{
 $pdo=(new Database())->connect();try{$pdo->exec("SET time_zone = '+07:00'");}catch(Throwable $ignore){}
 $rid=(int)($_GET['router_id']??0);$filter=$rid>0?' AND router_id=:rid':'';$p=$rid>0?[':rid'=>$rid]:[];
 try{$pdo->exec("ALTER TABLE pppoe_disconnect_history ADD COLUMN uptime_seconds BIGINT NULL AFTER uptime");}catch(Throwable $ignore){}
 $q=$pdo->prepare("SELECT username,COUNT(*) disconnects,AVG(NULLIF(uptime_seconds,0)) avg_uptime_seconds,MAX(disconnected_at) last_disconnect FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)".$filter." GROUP BY username ORDER BY disconnects DESC,username LIMIT 100");$q->execute($p);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 $counts=['normal'=>0,'attention'=>0,'warning'=>0,'critical'=>0];$users=[];
 foreach($rows as $r){$d=(int)$r['disconnects'];$avg=$r['avg_uptime_seconds']!==null?(int)round((float)$r['avg_uptime_seconds']):null;$score=100-min(70,$d*7);if($avg!==null&&$avg<3600)$score-=20;elseif($avg!==null&&$avg<7200)$score-=10;$score=max(0,$score);if($score<40||$d>=10)$level='critical';elseif($score<70||$d>=5)$level='warning';elseif($d>=2)$level='attention';else $level='normal';$counts[$level]++;$users[]=['username'=>(string)$r['username'],'disconnects'=>$d,'avg_uptime_seconds'=>$avg,'last_disconnect'=>$r['last_disconnect'],'score'=>$score,'level'=>$level];}
 usort($users,function($a,$b){$rank=['critical'=>0,'warning'=>1,'attention'=>2,'normal'=>3];return ($rank[$a['level']]<=>$rank[$b['level']])?:($b['disconnects']<=>$a['disconnects'])?:($a['score']<=>$b['score']);});$users=array_slice($users,0,5);
 echo json_encode(['success'=>true,'router_id'=>$rid,'summary'=>$counts,'total_users'=>array_sum($counts),'users'=>$users],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
