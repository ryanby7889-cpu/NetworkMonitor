<?php
/**
 * NetMonitor - lightweight global alarm status endpoint.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    require_once __DIR__ . '/../Config/database.php';

    $db = new Database();
    $pdo = $db->connect();

    $countStmt = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) AS critical,
        SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) AS warning
        FROM alarms
        WHERE status = 'active'");
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->query("SELECT
        id, interface_name, alarm_type, severity, message, value, threshold, created_at
        FROM alarms
        WHERE status = 'active'
        ORDER BY created_at DESC
        LIMIT 10");
    $alarms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'active' => (int)($counts['total'] ?? 0),
        'critical' => (int)($counts['critical'] ?? 0),
        'warning' => (int)($counts['warning'] ?? 0),
        'alarms' => $alarms,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'active' => 0,
        'critical' => 0,
        'warning' => 0,
        'alarms' => []
    ], JSON_UNESCAPED_UNICODE);
}
