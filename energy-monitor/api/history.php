<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 60;
$sql = "SELECT created_at, power, voltage, current, energy, frequency, pf FROM sensor_data ORDER BY id DESC LIMIT ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $limit);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'created_at' => $row['created_at'],
        'power' => (float)$row['power'],
        'voltage' => (float)$row['voltage'],
        'current' => (float)$row['current'],
        'energy' => (float)$row['energy'],
        'frequency' => (float)$row['frequency'],
        'pf' => (float)$row['pf'],
    ];
}

$stmt->close();
$conn->close();
echo json_encode(array_reverse($data), JSON_UNESCAPED_UNICODE);
