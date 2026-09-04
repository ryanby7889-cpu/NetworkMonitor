<?php

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

require_once "../Config/database.php";

try {
    $db = new Database();
    $pdo = $db->connect();

    // Dashboard uses the same persisted collector data as Traffic History.
    // 60 samples at a 10-second collector interval represent about 10 minutes.
    $stmt = $pdo->query("
        SELECT created_at, download_mbps, upload_mbps
        FROM traffic_history
        WHERE interface_name = 'ether1'
        ORDER BY created_at DESC
        LIMIT 60
    ");

    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // PRO statistics use a rolling one-hour window.
    $statsStmt = $pdo->query("
        SELECT
            COALESCE(MAX(download_mbps), 0) AS peak_download,
            COALESCE(MAX(upload_mbps), 0) AS peak_upload,
            COALESCE(AVG(download_mbps), 0) AS avg_download,
            COALESCE(AVG(upload_mbps), 0) AS avg_upload,
            COUNT(*) AS records,
            COALESCE(SUM(download_mbps), 0) AS total_download_samples,
            COALESCE(SUM(upload_mbps), 0) AS total_upload_samples
        FROM traffic_history
        WHERE interface_name = 'ether1'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
          AND created_at <= NOW()
    ");

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $labels = [];
    $downloads = [];
    $uploads = [];

    foreach ($rows as $row) {
        $createdAt = $row['created_at'] ?? '';
        $timestamp = strtotime($createdAt);
        $labels[] = $timestamp !== false ? date('H:i:s', $timestamp) : $createdAt;
        $downloads[] = (float)($row['download_mbps'] ?? 0);
        $uploads[] = (float)($row['upload_mbps'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'downloads' => $downloads,
        'uploads' => $uploads,
        'peak_download' => (float)($stats['peak_download'] ?? 0),
        'peak_upload' => (float)($stats['peak_upload'] ?? 0),
        'avg_download' => (float)($stats['avg_download'] ?? 0),
        'avg_upload' => (float)($stats['avg_upload'] ?? 0),
        'records' => (int)($stats['records'] ?? 0),
        'points' => count($rows)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
