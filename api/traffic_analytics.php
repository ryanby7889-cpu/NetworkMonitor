<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Config/database.php';

try {
    $pdo = (new Database())->connect();
    $range = $_GET['range'] ?? '24h';
    $allowed = ['24h' => 86400, '7d' => 604800];
    if (!isset($allowed[$range])) $range = '24h';

    $routerId = isset($_GET['router_id']) && ctype_digit((string)$_GET['router_id']) ? (int)$_GET['router_id'] : 0;
    if ($routerId <= 0) {
        $q = $pdo->query("SELECT id FROM router ORDER BY is_active DESC, CASE WHEN status='ONLINE' THEN 0 ELSE 1 END, id ASC LIMIT 1");
        $routerId = (int)($q->fetchColumn() ?: 0);
    }

    $interface = trim((string)($_GET['interface'] ?? ''));
    $from = date('Y-m-d H:i:s', time() - $allowed[$range]);
    $to = date('Y-m-d H:i:s');

    $where = 'created_at BETWEEN :from AND :to';
    $params = [':from' => $from, ':to' => $to];
    if ($routerId > 0) {
        $where .= ' AND router_id = :router_id';
        $params[':router_id'] = $routerId;
    }
    if ($interface !== '') {
        $where .= ' AND interface_name = :interface_name';
        $params[':interface_name'] = $interface;
    }

    $summaryStmt = $pdo->prepare("SELECT
        COUNT(*) AS sample_count,
        COALESCE(AVG(download_mbps),0) AS avg_download,
        COALESCE(AVG(upload_mbps),0) AS avg_upload,
        COALESCE(MAX(download_mbps),0) AS peak_download,
        COALESCE(MAX(upload_mbps),0) AS peak_upload,
        COALESCE(SUM(download_mbps),0) AS sum_download,
        COALESCE(SUM(upload_mbps),0) AS sum_upload
        FROM traffic_history WHERE $where");
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $peakStmt = $pdo->prepare("SELECT download_mbps, upload_mbps, created_at, interface_name
        FROM traffic_history WHERE $where
        ORDER BY download_mbps DESC, created_at DESC LIMIT 1");
    $peakStmt->execute($params);
    $peak = $peakStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $ifStmt = $pdo->prepare("SELECT DISTINCT interface_name FROM traffic_history
        WHERE created_at BETWEEN :from AND :to
        AND router_id = :router_id
        AND interface_name IS NOT NULL AND interface_name <> ''
        ORDER BY interface_name ASC");
    $ifStmt->execute([':from' => $from, ':to' => $to, ':router_id' => $routerId]);
    $interfaces = array_values(array_filter(array_map('strval', $ifStmt->fetchAll(PDO::FETCH_COLUMN))));

    $trendStmt = $pdo->prepare("SELECT
        DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS bucket,
        COALESCE(AVG(download_mbps),0) AS download_mbps,
        COALESCE(AVG(upload_mbps),0) AS upload_mbps,
        COUNT(*) AS sample_count
        FROM traffic_history WHERE $where
        GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')
        ORDER BY bucket ASC");
    $trendStmt->execute($params);
    $trend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

    $hourStmt = $pdo->prepare("SELECT
        HOUR(created_at) AS hour_of_day,
        COALESCE(AVG(download_mbps),0) AS download_mbps,
        COALESCE(AVG(upload_mbps),0) AS upload_mbps,
        COUNT(*) AS sample_count
        FROM traffic_history WHERE $where
        GROUP BY HOUR(created_at)
        ORDER BY (COALESCE(AVG(download_mbps),0) + COALESCE(AVG(upload_mbps),0)) DESC
        LIMIT 8");
    $hourStmt->execute($params);
    $busyHours = $hourStmt->fetchAll(PDO::FETCH_ASSOC);

    // V3: interface-level ranking and traffic distribution over the full period.
    $interfaceStmt = $pdo->prepare("SELECT
        interface_name,
        COUNT(*) AS sample_count,
        COALESCE(AVG(download_mbps),0) AS avg_download,
        COALESCE(AVG(upload_mbps),0) AS avg_upload,
        COALESCE(MAX(download_mbps),0) AS peak_download,
        COALESCE(MAX(upload_mbps),0) AS peak_upload,
        COALESCE(SUM(download_mbps),0) AS traffic_download,
        COALESCE(SUM(upload_mbps),0) AS traffic_upload
        FROM traffic_history WHERE $where
        AND interface_name IS NOT NULL AND interface_name <> ''
        GROUP BY interface_name
        ORDER BY (COALESCE(AVG(download_mbps),0) + COALESCE(AVG(upload_mbps),0)) DESC");
    $interfaceStmt->execute($params);
    $interfaceRows = $interfaceStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalTraffic = 0.0;
    foreach ($interfaceRows as $row) {
        $totalTraffic += (float)$row['traffic_download'] + (float)$row['traffic_upload'];
    }
    foreach ($interfaceRows as &$row) {
        $row['traffic_total'] = (float)$row['traffic_download'] + (float)$row['traffic_upload'];
        $row['share_percent'] = $totalTraffic > 0 ? ($row['traffic_total'] / $totalTraffic) * 100 : 0;
    }
    unset($row);

    $topInterface = $interfaceRows[0] ?? null;

    echo json_encode([
        'success' => true,
        'version' => '3',
        'router_id' => $routerId,
        'range' => $range,
        'from' => $from,
        'to' => $to,
        'summary' => $summary,
        'peak' => $peak,
        'interfaces' => $interfaces,
        'interface_stats' => $interfaceRows,
        'top_interface' => $topInterface,
        'traffic_total' => $totalTraffic,
        'trend' => $trend,
        'busy_hours' => $busyHours
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
