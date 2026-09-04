<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
try{
 $pdo=(new Database())->connect();
 $st=$pdo->query("SELECT l.hotspot_username,c.id AS customer_id,c.name AS customer_name,c.package_name,c.status
 FROM hotspot_billing_links l JOIN billing_customers c ON c.id=l.customer_id
 ORDER BY c.name ASC");
 echo json_encode(['success'=>true,'rows'=>$st->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['success'=>false,'message'=>'Gagal membaca mapping Billing.'],JSON_UNESCAPED_UNICODE);
}
