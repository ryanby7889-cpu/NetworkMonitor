<?php

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

require_once "../Config/database.php";

try {
    $db = new Database();
    $pdo = $db->connect();

    $allowedRanges = [
        '10m' => 10,
        '1h'  => 60,
        '6h'  => 360,
        '24h' => 1440
    ];

    $range = strtolower($_GET['range'] ?? '10m');
    if (!isset($allowedRanges[$range])) {
        $range = '10m';
    }

    $minutes = $allowedRanges[$range];
    $limit = $range === '24h' ? 500 : ($range === '6h' ? 360 : ($range === '1h' ? 180 : 120));

    // Use MySQL NOW() so Dashboard uses exactly the same clock as Traffic History.
    $rangeStmt = $pdo->prepare("SELECT DATE_SUB(NOW(), INTERVAL :minutes MINUTE) AS range_from, NOW() AS range_to");
    $rangeStmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
    $rangeStmt->execute();
    $rangeInfo = $rangeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $from = $rangeInfo['range_from'] ?? null;
    $to = $rangeInfo['range_to'] ?? null;

    // Pull the newest samples first, then reverse them for a chronological chart.
    $stmt = $pdo->prepare("SELECT created_at, download_mbps, upload_mbps FROM traffic_history WHERE interface_name = 'ether1' AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE) AND created_at <= NOW() ORDER BY created_at DESC LIMIT :limit");
    $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    $statsStmt = $pdo->prepare("SELECT COALESCE(MAX(download_mbps),0) peak_download, COALESCE(MAX(upload_mbps),0) peak_upload, COALESCE(AVG(download_mbps),0) avg_download, COALESCE(AVG(upload_mbps),0) avg_upload, COUNT(*) records FROM traffic_history WHERE interface_name = 'ether1' AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE) AND created_at <= NOW()");
    $statsStmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $latestStmt = $pdo->query("SELECT created_at FROM traffic_history WHERE interface_name = 'ether1' ORDER BY created_at DESC LIMIT 1");
    $latest = $latestStmt->fetchColumn();
    $latestTimestamp = $latest ? strtotime($latest) : null;
    $nowTimestamp = time();
    $collectorAge = $latestTimestamp !== false && $latestTimestamp !== null ? max(0, $nowTimestamp - $latestTimestamp) : null;
    if ($collectorAge === null) {
        $collectorStatus = 'OFFLINE';
    } elseif ($collectorAge <= 30) {
        $collectorStatus = 'HEALTHY';
    } elseif ($collectorAge <= 60) {
        $collectorStatus = 'DELAYED';
    } else {
        $collectorStatus = 'OFFLINE';
    }

    $labels = [];
    $downloads = [];
    $uploads = [];
    foreach ($rows as $row) {
        $ts = strtotime($row['created_at'] ?? '');
        $labels[] = $ts !== false ? date('H:i:s', $ts) : ($row['created_at'] ?? '');
        $downloads[] = (float)($row['download_mbps'] ?? 0);
        $uploads[] = (float)($row['upload_mbps'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'range' => $range,
        'from' => $from,
        'to' => $to,
        'labels' => $labels,
        'downloads' => $downloads,
        'uploads' => $uploads,
        'peak_download' => (float)($stats['peak_download'] ?? 0),
        'peak_upload' => (float)($stats['peak_upload'] ?? 0),
        'avg_download' => (float)($stats['avg_download'] ?? 0),
        'avg_upload' => (float)($stats['avg_upload'] ?? 0),
        'records' => (int)($stats['records'] ?? 0),
        'points' => count($rows),
        'latest_sample' => $latest,
        'collector_age' => $collectorAge,
        'collector_status' => $collectorStatus
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
