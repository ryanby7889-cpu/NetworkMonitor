<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

function fetchCount(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

$today = fetchCount($conn, "SELECT COUNT(*) AS total FROM alarm_history WHERE DATE(created_at)=CURDATE()");
$active = fetchCount($conn, "SELECT COUNT(*) AS total FROM alarm_history WHERE is_active=1");
$ack = fetchCount($conn, "SELECT COUNT(*) AS total FROM alarm_history WHERE DATE(created_at)=CURDATE() AND status='ACKNOWLEDGED'");

$latest = null;
$result = $conn->query("SELECT power, voltage, current, energy, frequency, pf, created_at FROM sensor_data ORDER BY id DESC LIMIT 1");
if ($result && ($row = $result->fetch_assoc())) {
    $latest = [
        'power' => (float)$row['power'],
        'voltage' => (float)$row['voltage'],
        'current' => (float)$row['current'],
        'energy' => (float)$row['energy'],
        'frequency' => (float)$row['frequency'],
        'pf' => (float)$row['pf'],
        'created_at' => $row['created_at'],
    ];
}

echo json_encode([
    'alarm_today' => $today,
    'alarm_active' => $active,
    'alarm_ack' => $ack,
    'latest' => $latest,
], JSON_UNESCAPED_UNICODE);

$conn->close();
