<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../library/routeros_api.class.php';

try {
    $pdo = (new Database())->connect();
    $router = $pdo->query("SELECT * FROM router ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$router) throw new RuntimeException('Konfigurasi router belum tersedia.');

    $api = new RouterosAPI();
    $api->debug = false;
    $ok = $api->connect($router['ip_address'], $router['username'], $router['password'], (int)$router['api_port']);
    if ($ok) {
        $api->write('/system/identity/print');
        $identity = $api->read();
        $api->disconnect();
        $pdo->prepare("UPDATE router SET status='ONLINE' WHERE id=?")->execute([$router['id']]);
        echo json_encode(['success'=>true,'status'=>'ONLINE','identity'=>$identity[0]['name'] ?? $router['router_name']]);
    } else {
        $pdo->prepare("UPDATE router SET status='OFFLINE' WHERE id=?")->execute([$router['id']]);
        echo json_encode(['success'=>false,'status'=>'OFFLINE','message'=>'Router tidak dapat terhubung.']);
    }
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'status'=>'ERROR','message'=>$e->getMessage()]);
}
