<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../Config/database.php';
try {
  $range=$_GET['range']??'24h';
  $allowed=['1h'=>'1 HOUR','6h'=>'6 HOUR','24h'=>'24 HOUR','7d'=>'7 DAY','30d'=>'30 DAY'];
  if(!isset($allowed[$range]))$range='24h';
  $pdo=(new Database())->connect();
  $pdo->exec("CREATE TABLE IF NOT EXISTS pppoe_traffic_history (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, username VARCHAR(128) NOT NULL, session_id VARCHAR(128) NULL, ip_address VARCHAR(64) NULL, interface_name VARCHAR(128) NULL, profile VARCHAR(128) NULL, bytes_in BIGINT UNSIGNED NOT NULL DEFAULT 0, bytes_out BIGINT UNSIGNED NOT NULL DEFAULT 0, packets_in BIGINT UNSIGNED NOT NULL DEFAULT 0, packets_out BIGINT UNSIGNED NOT NULL DEFAULT 0, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_user_time(username,recorded_at), KEY idx_session_time(session_id,recorded_at), KEY idx_time(recorded_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $stmt=$pdo->query("SELECT username,MAX(profile) profile,MIN(recorded_at) first_at,MAX(recorded_at) last_at,MAX(bytes_in)-MIN(bytes_in) upload_bytes,MAX(bytes_out)-MIN(bytes_out) download_bytes,COUNT(*) records FROM pppoe_traffic_history WHERE recorded_at>=DATE_SUB(NOW(), INTERVAL {$allowed[$range]}) GROUP BY username ORDER BY ((MAX(bytes_out)-MIN(bytes_out))+(MAX(bytes_in)-MIN(bytes_in))) DESC");
  $rows=[];
  foreach($stmt as $r){
    $download=max(0,(float)$r['download_bytes']);
    $upload=max(0,(float)$r['upload_bytes']);
    $rows[]=['username'=>$r['username'],'profile'=>$r['profile']??'','download_bytes'=>$download,'upload_bytes'=>$upload,'total_bytes'=>$download+$upload,'records'=>(int)$r['records'],'first_at'=>$r['first_at'],'last_at'=>$r['last_at']];
  }
  usort($rows,function($a,$b){return $b['total_bytes']<=>$a['total_bytes'];});
  echo json_encode(['success'=>true,'range'=>$range,'users'=>$rows,'timestamp'=>date('c')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
