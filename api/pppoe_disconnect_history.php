<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/../Config/database.php';
try {
  $pdo=(new Database())->connect();
  $pdo->exec("CREATE TABLE IF NOT EXISTS pppoe_disconnect_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    router_id INT NOT NULL,
    router_name VARCHAR(128) NULL,
    username VARCHAR(128) NOT NULL,
    address VARCHAR(64) NULL,
    profile VARCHAR(128) NULL,
    caller_id VARCHAR(128) NULL,
    uptime VARCHAR(64) NULL,
    disconnected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id), KEY idx_router_time(router_id,disconnected_at), KEY idx_user_time(username,disconnected_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $limit=min(200,(int)($_GET['limit']??50));
  $routerId=(int)($_GET['router_id']??0);
  $sql="SELECT id,router_id,router_name,username,address,profile,caller_id,uptime,disconnected_at FROM pppoe_disconnect_history";
  $params=[];
  if($routerId>0){$sql.=' WHERE router_id=:router_id';$params[':router_id']=$routerId;}
  $sql.=' ORDER BY disconnected_at DESC,id DESC LIMIT '.$limit;
  $st=$pdo->prepare($sql);$st->execute($params);
  echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
