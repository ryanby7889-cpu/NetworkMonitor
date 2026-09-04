<?php

header('Content-Type: application/json');

require_once "../config/database.php";

try {

    $db = new Database();

    $pdo = $db->connect();

    $stmt = $pdo->query("
        SELECT setting_name, setting_value
        FROM settings
    ");

    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Dashboard and collector both use a 10-second monitoring cadence.
    // Clamp older/smaller values so the live dashboard stays consistent
    // with traffic_history collection frequency.
    $refreshInterval = intval($settings['refresh_interval'] ?? 10000);
    if ($refreshInterval < 10000) {
        $refreshInterval = 10000;
    }

    echo json_encode([
        'success' => true,
        'company_name' => $settings['company_name'] ?? 'Network Monitor',
        'refresh_interval' => $refreshInterval,
        'timezone' => $settings['timezone'] ?? 'Asia/Jakarta'
    ]);

}
catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}
