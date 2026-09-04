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
    $pdo=(new Database())->connect();
    $action=$_GET['action']??'';
    if($action==='customers'){
        $st=$pdo->query("SELECT id,name,pppoe_username,package_name,status FROM billing_customers ORDER BY name ASC");
        out(true,'',['customers'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if($action==='lookup'){
        $u=trim((string)($_GET['username']??''));
        if($u==='') out(false,'Username wajib diisi.',[],422);
        $st=$pdo->prepare("SELECT l.id,l.hotspot_username,c.id AS customer_id,c.name,c.pppoe_username,c.package_name,c.status
          FROM hotspot_billing_links l JOIN billing_customers c ON c.id=l.customer_id
          WHERE LOWER(TRIM(l.hotspot_username))=LOWER(TRIM(:u)) LIMIT 1");
        $st->execute([':u'=>$u]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        out(true,'',['linked'=>(bool)$row,'link'=>$row?:null]);
    }
    if($_SERVER['REQUEST_METHOD']!=='POST') out(false,'Action tidak dikenal.',[],404);
    $body=$_POST;
    if($action==='link'){
        $u=trim((string)($body['hotspot_username']??''));
        $cid=(int)($body['customer_id']??0);
        if($u===''||$cid<=0) out(false,'Username Hotspot dan pelanggan wajib diisi.',422);
        $st=$pdo->prepare("SELECT id FROM billing_customers WHERE id=:id LIMIT 1");$st->execute([':id'=>$cid]);
        if(!$st->fetch()) out(false,'Pelanggan Billing tidak ditemukan.',404);
        $st=$pdo->prepare("SELECT id,customer_id FROM hotspot_billing_links WHERE LOWER(TRIM(hotspot_username))=LOWER(TRIM(:u)) LIMIT 1");
        $st->execute([':u'=>$u]);$old=$st->fetch(PDO::FETCH_ASSOC);
        if($old && (int)$old['customer_id']!==$cid){
            $up=$pdo->prepare("UPDATE hotspot_billing_links SET customer_id=:c,updated_at=NOW() WHERE id=:id");
            $up->execute([':c'=>$cid,':id'=>$old['id']]);
        } elseif(!$old){
            $st=$pdo->prepare("SELECT id FROM hotspot_billing_links WHERE customer_id=:c LIMIT 1");$st->execute([':c'=>$cid]);
            if($st->fetch()) out(false,'Pelanggan tersebut sudah terhubung ke username Hotspot lain.',409);
            $ins=$pdo->prepare("INSERT INTO hotspot_billing_links(hotspot_username,customer_id) VALUES(:u,:c)");
            $ins->execute([':u'=>$u,':c'=>$cid]);
        }
        out(true,'Pelanggan berhasil dihubungkan.');
    }
    if($action==='unlink'){
        $u=trim((string)($body['hotspot_username']??''));
        if($u==='') out(false,'Username Hotspot wajib diisi.',422);
        $st=$pdo->prepare("DELETE FROM hotspot_billing_links WHERE LOWER(TRIM(hotspot_username))=LOWER(TRIM(:u))");
        $st->execute([':u'=>$u]);
        out(true,'Hubungan Billing dilepas.');
    }
    out(false,'Action tidak dikenal.',[],404);
}catch(Throwable $e){ out(false,'Gagal memproses hubungan Hotspot-Billing.',[],500); }
