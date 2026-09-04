<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Config/database.php';

try {
    $pdo = (new Database())->connect();
    $range = $_GET['range'] ?? '24h';
    $allowed = ['1h'=>3600,'6h'=>21600,'24h'=>86400,'7d'=>604800];
    if (!isset($allowed[$range])) $range = '24h';

    $routerId = isset($_GET['router_id']) && ctype_digit((string)$_GET['router_id']) ? (int)$_GET['router_id'] : 0;
    if ($routerId <= 0) {
        $q = $pdo->query("SELECT id FROM router ORDER BY is_active DESC, CASE WHEN status='ONLINE' THEN 0 ELSE 1 END, id ASC LIMIT 1");
        $routerId = (int)($q->fetchColumn() ?: 0);
    }

    $from = date('Y-m-d H:i:s', time() - $allowed[$range]);
    $to = date('Y-m-d H:i:s');
    $where = 'created_at BETWEEN :from AND :to';
    $params = [':from'=>$from, ':to'=>$to];
    if ($routerId > 0) { $where .= ' AND router_id = :router_id'; $params[':router_id'] = $routerId; }

    $count = $pdo->prepare("SELECT COUNT(*) FROM traffic_history WHERE $where");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $stats = $pdo->prepare("SELECT COALESCE(MAX(download_mbps),0) max_download, COALESCE(MAX(upload_mbps),0) max_upload, COALESCE(AVG(download_mbps),0) avg_download FROM traffic_history WHERE $where");
    $stats->execute($params);
    $summary = $stats->fetch(PDO::FETCH_ASSOC) ?: [];

    $limit = min(500, max(1, (int)($_GET['limit'] ?? 500)));
    $stmt = $pdo->prepare("SELECT interface_name, download_mbps, upload_mbps, rx_packet, tx_packet, cpu, memory, disk, created_at FROM traffic_history WHERE $where ORDER BY created_at DESC LIMIT $limit");
    $stmt->execute($params);
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(['success'=>true,'router_id'=>$routerId,'range'=>$range,'from'=>$from,'to'=>$to,'total'=>$total,'summary'=>$summary,'data'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
