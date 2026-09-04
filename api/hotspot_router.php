<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/mikrotik.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $id = (int)($_POST['router_id'] ?? $_GET['router_id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('Router ID tidak valid.');
    $config = new MikroTikConfig();
    $router = $config->getRouterById($id);
    if (!$router) throw new RuntimeException('Router tidak ditemukan.');
    $config->setActive($id);
    echo json_encode(['success'=>true,'router_id'=>(int)$router['id'],'router_name'=>(string)$router['name']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
