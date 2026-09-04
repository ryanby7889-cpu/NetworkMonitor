<?php

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

require_once "../Config/database.php";

try {
    $db = new Database();
    $pdo = $db->connect();

    // Dashboard follows the same stored traffic_history source as Traffic History.
    // The latest 60 samples represent roughly the last 10 minutes with the
    // current 10-second collector interval.
    $stmt = $pdo->query("\n        SELECT created_at, download_mbps, upload_mbps\n        FROM traffic_history\n        WHERE interface_name = 'ether1'\n        ORDER BY created_at DESC\n        LIMIT 60\n    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rows = array_reverse($rows);

    $statsStmt = $pdo->query("\n        SELECT\n            COALESCE(MAX(download_mbps), 0) AS peak_download,\n            COALESCE(MAX(upload_mbps), 0) AS peak_upload\n        FROM traffic_history\n        WHERE interface_name = 'ether1'\n          AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)\n          AND created_at <= NOW()\n    ");

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
        'points' => count($rows)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
