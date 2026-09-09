<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Config/database.php';
try {
    $pdo=(new Database())->connect();
    $routerId=(int)($_GET['router_id']??0);
    $range=$_GET['range']??'7d';
    $hours=$range==='30d'?720:($range==='24h'?24:168);
    $limit=max(1,min(100,(int)($_GET['limit']??10)));
    $where='snapshot_at >= DATE_SUB(NOW(), INTERVAL '.$hours.' HOUR)'; $params=[];
    if($routerId>0){$where.=' AND router_id=?';$params[]=$routerId;}
    $sql="SELECT client_ip, MAX(client_name) client_name, MAX(source) source, COUNT(*) samples,
        AVG(download_mbps) avg_download, AVG(upload_mbps) avg_upload,
        MAX(download_mbps) peak_download, MAX(upload_mbps) peak_upload,
        SUM((download_mbps+upload_mbps)*60)/8/1024 total_gb_approx,
        MAX(CASE WHEN download_mbps=(SELECT MAX(x.download_mbps) FROM client_traffic_history x WHERE x.client_ip=h.client_ip AND x.router_id=h.router_id AND x.snapshot_at >= DATE_SUB(NOW(), INTERVAL $hours HOUR)) THEN snapshot_at END) peak_download_at,
        MAX(CASE WHEN upload_mbps=(SELECT MAX(y.upload_mbps) FROM client_traffic_history y WHERE y.client_ip=h.client_ip AND y.router_id=h.router_id AND y.snapshot_at >= DATE_SUB(NOW(), INTERVAL $hours HOUR)) THEN snapshot_at END) peak_upload_at
        FROM client_traffic_history h WHERE $where GROUP BY router_id,client_ip ORDER BY (AVG(download_mbps)+AVG(upload_mbps)) DESC LIMIT $limit";
    $s=$pdo->prepare($sql);$s->execute($params);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
    $total=$pdo->prepare("SELECT COUNT(DISTINCT client_ip) FROM client_traffic_history WHERE $where");$total->execute($params);
    $totalClients=(int)$total->fetchColumn();
    foreach($rows as &$r){foreach(['avg_download','avg_upload','peak_download','peak_upload','total_gb_approx'] as $k)$r[$k]=(float)$r[$k];$r['samples']=(int)$r['samples'];}
    unset($r);
    $top=$rows[0]??null;
    echo json_encode(['success'=>true,'range'=>$range,'router_id'=>$routerId,'total_clients'=>$totalClients,'returned'=>count($rows),'top_client'=>$top,'clients'=>$rows],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
