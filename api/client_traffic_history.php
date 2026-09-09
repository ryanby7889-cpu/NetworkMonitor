<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Config/database.php';
try {
    $pdo=(new Database())->connect();
    $routerId=(int)($_GET['router_id']??0);
    $ip=trim((string)($_GET['ip']??''));
    $range=$_GET['range']??'24h';
    $hours=$range==='7d'?168:($range==='30d'?720:24);
    $params=[];
    $where='snapshot_at >= DATE_SUB(NOW(), INTERVAL '.$hours.' HOUR)';
    if($routerId>0){$where.=' AND router_id=?';$params[]=$routerId;}
    if($ip!==''){$where.=' AND client_ip=?';$params[]=$ip;}
    $stmt=$pdo->prepare("SELECT client_ip,client_name,source,download_mbps,upload_mbps,snapshot_at FROM client_traffic_history WHERE $where ORDER BY snapshot_at ASC");
    $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $clients=[];
    foreach($rows as $r){$clients[$r['client_ip']]=['ip'=>$r['client_ip'],'name'=>$r['client_name'],'source'=>$r['source']];}
    $stats=['avg_download'=>0,'avg_upload'=>0,'peak_download'=>0,'peak_upload'=>0,'peak_download_at'=>null,'peak_upload_at'=>null,'total_sample'=>count($rows)];
    if($rows){$sd=$su=0;foreach($rows as $r){$d=(float)$r['download_mbps'];$u=(float)$r['upload_mbps'];$sd+=$d;$su+=$u;if($d>$stats['peak_download']){$stats['peak_download']=$d;$stats['peak_download_at']=$r['snapshot_at'];}if($u>$stats['peak_upload']){$stats['peak_upload']=$u;$stats['peak_upload_at']=$r['snapshot_at'];}}$stats['avg_download']=$sd/count($rows);$stats['avg_upload']=$su/count($rows);}
    echo json_encode(['success'=>true,'range'=>$range,'router_id'=>$routerId,'client'=>$ip,'stats'=>$stats,'clients'=>array_values($clients),'history'=>$rows],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
