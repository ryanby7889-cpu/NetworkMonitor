<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__.'/../Config/database.php';
try{
 $pdo=(new Database())->connect();
 $pdo->exec("CREATE TABLE IF NOT EXISTS pppoe_disconnect_history (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,event_key CHAR(64) NULL,router_id INT NOT NULL,router_name VARCHAR(128),username VARCHAR(128) NOT NULL,address VARCHAR(64),profile VARCHAR(128),caller_id VARCHAR(128),uptime VARCHAR(64),disconnected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_disconnect_event(event_key),KEY idx_router_time(router_id,disconnected_at),KEY idx_user_time(username,disconnected_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $rid=(int)($_GET['router_id']??0);
 $filter=$rid>0?' AND router_id=:rid':'';$p=$rid>0?[':rid'=>$rid]:[];
 $q=$pdo->prepare("SELECT COUNT(*) FROM pppoe_disconnect_history WHERE disconnected_at>=CURDATE()".$filter);$q->execute($p);$today=(int)$q->fetchColumn();
 $q=$pdo->prepare("SELECT COUNT(*) FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)".$filter);$q->execute($p);$week=(int)$q->fetchColumn();
 $q=$pdo->prepare("SELECT COUNT(*) FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)".$filter);$q->execute($p);$month=(int)$q->fetchColumn();
 $q=$pdo->prepare("SELECT username,COUNT(*) total FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)".$filter." GROUP BY username ORDER BY total DESC,username LIMIT 10");$q->execute($p);$users=$q->fetchAll(PDO::FETCH_ASSOC);
 $q=$pdo->prepare("SELECT router_name,router_id,COUNT(*) total FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)".$filter." GROUP BY router_id,router_name ORDER BY total DESC,router_id LIMIT 10");$q->execute($p);$routers=$q->fetchAll(PDO::FETCH_ASSOC);
 $q=$pdo->prepare("SELECT DATE_FORMAT(disconnected_at,'%Y-%m-%d %H:00:00') hour,COUNT(*) total FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)".$filter." GROUP BY DATE_FORMAT(disconnected_at,'%Y-%m-%d %H:00:00') ORDER BY hour");$q->execute($p);$raw=$q->fetchAll(PDO::FETCH_ASSOC);
 $counts=[];foreach($raw as $row)$counts[(string)$row['hour']]=(int)$row['total'];
 $hours=[];$base=new DateTime('now',new DateTimeZone('Asia/Jakarta'));$base->setTime((int)$base->format('H'),0,0);
 for($i=23;$i>=0;$i--){$t=clone $base;$t->modify('-'.$i.' hours');$key=$t->format('Y-m-d H:00:00');$hours[]=['hour'=>$t->format('H:00'),'total'=>$counts[$key]??0];}
 $topUnstable=[];
 $q=$pdo->prepare("SELECT username,COUNT(*) disconnects,MIN(disconnected_at) first_disconnect,MAX(disconnected_at) last_disconnect FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)".$filter." GROUP BY username HAVING COUNT(*)>=2 ORDER BY disconnects DESC,last_disconnect DESC,username LIMIT 10");$q->execute($p);$topUnstable=$q->fetchAll(PDO::FETCH_ASSOC);
 echo json_encode(['success'=>true,'summary'=>['today'=>$today,'week'=>$week,'month'=>$month],'top_users'=>$users,'top_routers'=>$routers,'hours'=>$hours,'top_unstable'=>$topUnstable],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
