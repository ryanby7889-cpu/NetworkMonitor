<?php
require_once __DIR__.'/../Config/mikrotik.php';
header('Content-Type: application/json; charset=utf-8');
try{
 $config=new MikroTikConfig();
 $id=(int)($_POST['router_id']??$_GET['router_id']??0);
 if($id<=0) throw new Exception('router_id wajib diisi.');
 $router=$config->getRouterById($id);
 if(!$router) throw new Exception('Router tidak ditemukan.');
 $config->setActive($id);
 echo json_encode(['success'=>true,'router_id'=>$id,'router_name'=>$router['router_name'],'message'=>'Router aktif berhasil diubah.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
