<?php
require_once "../Config/database.php";

$db = new Database();
$pdo = $db->connect();

$range = $_GET['range'] ?? '24h';
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $from = date('Y-m-d H:i:s', strtotime($_GET['from']));
    $to = date('Y-m-d H:i:s', strtotime($_GET['to']));
    $label = 'custom';
} else {
    $to = date('Y-m-d H:i:s');
    switch ($range) {
        case '1h': $seconds = 3600; break;
        case '6h': $seconds = 21600; break;
        case '7d': $seconds = 604800; break;
        default: $range = '24h'; $seconds = 86400; break;
    }
    $from = date('Y-m-d H:i:s', time() - $seconds);
    $label = $range;
}

if (strtotime($from) > strtotime($to)) {
    [$from, $to] = [$to, $from];
}

$stmt = $pdo->prepare("SELECT interface_name, download_mbps, upload_mbps,
    rx_packet, tx_packet, cpu, memory, disk, created_at
    FROM traffic_history
    WHERE created_at BETWEEN :from AND :to
    ORDER BY created_at ASC");
$stmt->execute([':from' => $from, ':to' => $to]);

$filename = "traffic_history_" . $label . "_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($output, [
    'Time', 'Interface', 'Download (Mbps)', 'Upload (Mbps)',
    'RX Packet (pkt/s)', 'TX Packet (pkt/s)', 'CPU (%)', 'Memory (%)', 'Disk (%)'
], ';');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['created_at'], $row['interface_name'], $row['download_mbps'],
        $row['upload_mbps'], $row['rx_packet'], $row['tx_packet'],
        $row['cpu'], $row['memory'], $row['disk']
    ], ';');
}

fclose($output);
exit;
