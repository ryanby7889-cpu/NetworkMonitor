<?php
header('Content-Type: application/json; charset=utf-8');

require_once "../Config/database.php";

try {
    $db = new Database();
    $pdo = $db->connect();

    $range = $_GET['range'] ?? '24h';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 25)));

    // Custom date/time range has priority over preset range.
    if (!empty($_GET['from']) && !empty($_GET['to'])) {
        $from = date('Y-m-d H:i:s', strtotime($_GET['from']));
        $to   = date('Y-m-d H:i:s', strtotime($_GET['to']));
        $range = 'custom';
    } else {
        $to = date('Y-m-d H:i:s');
        $seconds = match ($range) {
            '1h' => 3600,
            '6h' => 21600,
            '24h' => 86400,
            '7d' => 604800,
            default => 86400,
        };
        $from = date('Y-m-d H:i:s', time() - $seconds);
    }

    if (strtotime($from) > strtotime($to)) {
        [$from, $to] = [$to, $from];
    }

    $where = 'WHERE created_at BETWEEN :from AND :to';
    $baseParams = [':from' => $from, ':to' => $to];

    // Total records and aggregate statistics are calculated from the whole range,
    // while only one page of log rows is returned to keep the page lightweight.
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
    $stmt->bindValue(':from', $from);
    $stmt->bindValue(':to', $to);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Chart is capped at 500 points and uses the newest samples only.
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
