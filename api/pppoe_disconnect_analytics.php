<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/../Config/database.php';
try {
    $pdo=(new Database())->connect();
    $pdo->exec("CREATE TABLE IF NOT EXISTS pppoe_disconnect_history (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,router_id INT NOT NULL,router_name VARCHAR(128),username VARCHAR(128) NOT NULL,address VARCHAR(64),profile VARCHAR(128),caller_id VARCHAR(128),uptime VARCHAR(64),disconnected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_router_time(router_id,disconnected_at),KEY idx_user_time(username,disconnected_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $rid=(int)($_GET['router_id']??0);
    $filter=$rid>0?' AND router_id=:rid':'';
    $p=$rid>0?[':rid'=>$rid]:[];
    $today=$pdo->prepare("SELECT COUNT(*) FROM pppoe_disconnect_history WHERE disconnected_at>=CURDATE()".$filter);$today->execute($p);$today=(int)$today->fetchColumn();
    $week=$pdo->prepare("SELECT COUNT(*) FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)".$filter);$week->execute($p);$week=(int)$week->fetchColumn();
    $month=$pdo->prepare("SELECT COUNT(*) FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)".$filter);$month->execute($p);$month=(int)$month->fetchColumn();
    $u=$pdo->prepare("SELECT username,COUNT(*) total FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)".$filter." GROUP BY username ORDER BY total DESC,username LIMIT 10");$u->execute($p);$users=$u->fetchAll(PDO::FETCH_ASSOC);
    $r=$pdo->prepare("SELECT router_name,router_id,COUNT(*) total FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)".$filter." GROUP BY router_id,router_name ORDER BY total DESC,router_id LIMIT 10");$r->execute($p);$routers=$r->fetchAll(PDO::FETCH_ASSOC);
    $h=$pdo->prepare("SELECT DATE_FORMAT(disconnected_at,'%Y-%m-%d %H:00:00') hour,COUNT(*) total FROM pppoe_disconnect_history WHERE disconnected_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)".$filter." GROUP BY DATE_FORMAT(disconnected_at,'%Y-%m-%d %H:00:00') ORDER BY hour");$h->execute($p);$raw=$h->fetchAll(PDO::FETCH_ASSOC);
    $counts=[];foreach($raw as $row)$counts[(string)$row['hour']=(int)$row['total'];
    $hours=[];$base=new DateTime('now',new DateTimeZone('Asia/Jakarta'));$base->setTime((int)$base->format('H'),0,0);
    for($i=23;$i>=0;$i--){$t=clone $base;$t->modify('-'.$i.' hours');$key=$t->format('Y-m-d H:00:00');$hours[]=['hour'=>$t->format('H:00'),'total'=>$counts[$key]??0];}
    echo json_encode(['success'=>true,'summary'=>['today'=>$today,'week'=>$week,'month'=>$month],'top_users'=>$users,'top_routers'=>$routers,'hours'=>$hours],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
