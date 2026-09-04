<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../Config/database.php';
try {
    $pdo=(new Database())->connect();
    $rows=$pdo->query("SELECT id,router_name,ip_address,api_port,status,is_active FROM router ORDER BY is_active DESC,id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $active=0; foreach($rows as $r){if((int)$r['is_active']===1){$active=(int)$r['id'];break;}}
    if(!$active && $rows) $active=(int)$rows[0]['id'];
    echo json_encode(['success'=>true,'data'=>$rows,'routers'=>$rows,'active_id'=>$active],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage(),'data'=>[],'routers'=>[]]);
}
