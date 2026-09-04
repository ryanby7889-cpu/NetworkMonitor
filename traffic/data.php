<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

require_once "../Config/database.php";

try {
    $db = new Database();
    $pdo = $db->connect();
    // Keep the API and collector on the same WIB clock.
    $pdo->exec("SET time_zone = '+07:00'");

    $range = $_GET['range'] ?? '24h';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 25)));

    $allowedRanges = ['1h' => 1, '6h' => 6, '24h' => 24, '7d' => 168];

    if (isset($_GET['from'], $_GET['to']) && $_GET['from'] !== '' && $_GET['to'] !== '') {
        $from = date('Y-m-d H:i:s', strtotime($_GET['from']));
        $to = date('Y-m-d H:i:s', strtotime($_GET['to']));
        $range = 'custom';
        $where = 'WHERE created_at BETWEEN :from AND :to';
        $baseParams = [':from' => $from, ':to' => $to];
    } else {
        if (!isset($allowedRanges[$range])) $range = '24h';
        $hours = $allowedRanges[$range];
        $where = 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL ' . $hours . ' HOUR) AND created_at <= NOW()';
        $timeStmt = $pdo->query('SELECT DATE_SUB(NOW(), INTERVAL ' . $hours . ' HOUR) AS range_from, NOW() AS range_to');
        $time = $timeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $from = $time['range_from'] ?? '';
        $to = $time['range_to'] ?? '';
        $baseParams = [];
    }

    $statsStmt = $pdo->prepare("SELECT COUNT(*) records,
        COALESCE(MAX(download_mbps),0) maxDownload,
        COALESCE(MAX(upload_mbps),0) maxUpload,
        COALESCE(AVG(download_mbps),0) avgDownload,
        COALESCE(AVG(upload_mbps),0) avgUpload
        FROM traffic_history $where");
    $statsStmt->execute($baseParams);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total = (int)($stats['records'] ?? 0);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("SELECT interface_name, download_mbps, upload_mbps,
        rx_packet, tx_packet, cpu, memory, disk, created_at
        FROM traffic_history $where
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset");
    foreach ($baseParams as $key => $value) $stmt->bindValue($key, $value);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chartStmt = $pdo->prepare("SELECT interface_name, download_mbps, upload_mbps, created_at
        FROM traffic_history $where
        ORDER BY created_at DESC
        LIMIT 500");
    $chartStmt->execute($baseParams);
    $chartRows = array_reverse($chartStmt->fetchAll(PDO::FETCH_ASSOC));

    $labels = [];
    $downloads = [];
    $uploads = [];
    foreach ($chartRows as $row) {
        $labels[] = date('H:i:s', strtotime($row['created_at']));
        $downloads[] = (float)$row['download_mbps'];
        $uploads[] = (float)$row['upload_mbps'];
    }

    echo json_encode([
        'success' => true,
        'range' => $range,
        'from' => $from,
        'to' => $to,
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'totalPages' => $totalPages,
        'data' => $rows,
        'labels' => $labels,
        'downloads' => $downloads,
        'uploads' => $uploads,
        'stats' => [
            'records' => $total,
            'maxDownload' => (float)($stats['maxDownload'] ?? 0),
            'maxUpload' => (float)($stats['maxUpload'] ?? 0),
            'avgDownload' => (float)($stats['avgDownload'] ?? 0),
            'avgUpload' => (float)($stats['avgUpload'] ?? 0)
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data traffic.']);
}
