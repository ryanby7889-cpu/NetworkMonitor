<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Config/database.php';
try {
 $pdo=(new Database())->connect(); $routerId=(int)($_GET['router_id']??0); $range=$_GET['range']??'7d'; $hours=$range==='30d'?720:($range==='24h'?24:168); $limit=max(1,min(100,(int)($_GET['limit']??20)));
 $where='snapshot_at >= DATE_SUB(NOW(), INTERVAL '.$hours.' HOUR)'; $params=[]; if($routerId){$where.=' AND router_id=?';$params[]=$routerId;}
 $sql="SELECT router_id, COALESCE(NULLIF(TRIM(client_name),''),client_ip) customer, COUNT(DISTINCT client_ip) ip_count, GROUP_CONCAT(DISTINCT client_ip ORDER BY client_ip SEPARATOR ', ') ips, COUNT(*) samples, AVG(download_mbps) avg_download, AVG(upload_mbps) avg_upload, MAX(download_mbps) peak_download, MAX(upload_mbps) peak_upload, SUM((download_mbps+upload_mbps)*60)/8/1024 total_gb_approx FROM client_traffic_history WHERE $where GROUP BY router_id,customer ORDER BY (AVG(download_mbps)+AVG(upload_mbps)) DESC LIMIT $limit";
 $s=$pdo->prepare($sql);$s->execute($params);$rows=$s->fetchAll(PDO::FETCH_ASSOC); foreach($rows as &$r){foreach(['avg_download','avg_upload','peak_download','peak_upload','total_gb_approx'] as $k)$r[$k]=(float)$r[$k];$r['ip_count']=(int)$r['ip_count'];$r['samples']=(int)$r['samples'];} unset($r);
 $c=$pdo->prepare("SELECT COUNT(*) FROM (SELECT router_id,COALESCE(NULLIF(TRIM(client_name),''),client_ip) customer FROM client_traffic_history WHERE $where GROUP BY router_id,customer) z");$c->execute($params); $total=(int)$c->fetchColumn();
 echo json_encode(['success'=>true,'range'=>$range,'total_customers'=>$total,'returned'=>count($rows),'customers'=>$rows],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
