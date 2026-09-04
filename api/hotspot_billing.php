<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');

function out(bool $ok,string $message='',array $data=[],int $code=200):void{
    http_response_code($code);
    echo json_encode(array_merge(['success'=>$ok,'message'=>$message],$data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
try{
    $username=trim((string)($_GET['username']??''));
    if($username==='') out(false,'Username wajib diisi.',[],422);
    $pdo=(new Database())->connect();
    $st=$pdo->prepare("SELECT * FROM billing_customers WHERE LOWER(TRIM(pppoe_username))=LOWER(TRIM(:u)) LIMIT 1");
    $st->execute([':u'=>$username]);
    $customer=$st->fetch(PDO::FETCH_ASSOC);
    if(!$customer) out(true,'Pelanggan billing tidak ditemukan.',['linked'=>false]);
    $iv=$pdo->prepare("SELECT invoice_no,period,amount,due_date,status,paid_at,payment_method FROM billing_invoices WHERE customer_id=:id ORDER BY due_date DESC,id DESC LIMIT 5");
    $iv->execute([':id'=>$customer['id']]);
    out(true,'',['linked'=>true,'customer'=>[
        'id'=>(int)$customer['id'],'name'=>$customer['name'],'phone'=>$customer['phone'],
        'package_name'=>$customer['package_name'],'monthly_price'=>$customer['monthly_price'],
        'billing_day'=>$customer['billing_day'],'status'=>$customer['status'],'notes'=>$customer['notes']
    ],'invoices'=>$iv->fetchAll(PDO::FETCH_ASSOC)]);
}catch(Throwable $e){
    out(false,'Gagal membaca data billing.',[],500);
}
