<?php
header('Content-Type: application/json; charset=utf-8');

require_once "../Config/database.php";

try {
    $db = new Database();
    $pdo = $db->connect();

    $start = $_GET['start'] ?? date('Y-m-d');
    $end   = $_GET['end'] ?? date('Y-m-d');

    $sql = "
        SELECT interface_name, download_mbps, upload_mbps,
               rx_packet, tx_packet, cpu, memory, disk, created_at
        FROM traffic_history
        WHERE DATE(created_at) BETWEEN :start AND :end
        ORDER BY created_at ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $downloads = [];
    $uploads = [];
    $labels = [];

    foreach ($data as $row) {
        $labels[] = date('H:i:s', strtotime($row['created_at']));
        $downloads[] = (float)$row['download_mbps'];
        $uploads[] = (float)$row['upload_mbps'];
    }

    $downloadValues = array_map('floatval', array_column($data, 'download_mbps'));
    $uploadValues = array_map('floatval', array_column($data, 'upload_mbps'));

    echo json_encode([
        'success' => true,
        'data' => $data,
        'labels' => $labels,
        'downloads' => $downloads,
        'uploads' => $uploads,
        'stats' => [
            'records' => count($data),
            'maxDownload' => count($downloadValues) ? max($downloadValues) : 0,
            'maxUpload' => count($uploadValues) ? max($uploadValues) : 0,
            'avgDownload' => count($downloadValues) ? array_sum($downloadValues) / count($downloadValues) : 0,
            'avgUpload' => count($uploadValues) ? array_sum($uploadValues) / count($uploadValues) : 0
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data traffic.']);
}
