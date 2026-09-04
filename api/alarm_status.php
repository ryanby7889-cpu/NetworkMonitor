<?php
/** NetMonitor - global alarm status endpoint. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
try {
    require_once __DIR__ . '/../Config/database.php';
    $pdo = (new Database())->connect();
    $counts = $pdo->query("SELECT COUNT(*) total,SUM(status='active') active,SUM(status='active' AND severity='critical') critical,SUM(status='active' AND severity='warning') warning FROM alarms")->fetch(PDO::FETCH_ASSOC) ?: [];
    $alarms = $pdo->query("SELECT id,router_id,interface_name,alarm_type,severity,message,value,threshold,created_at FROM alarms WHERE status='active' ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'active'=>(int)($counts['active']??0),'critical'=>(int)($counts['critical']??0),'warning'=>(int)($counts['warning']??0),'history'=>(int)($counts['total']??0),'alarms'=>$alarms,'timestamp'=>date('c')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'active'=>0,'critical'=>0,'warning'=>0,'history'=>0,'alarms'=>[]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
