<?php

header('Content-Type: application/json');

require_once "../config/database.php";

try {

    $db = new Database();
    $pdo = $db->connect();

    $stmt = $pdo->query("
        SELECT setting_name, setting_value
        FROM settings
        WHERE setting_name IN (
            'download_warning',
            'download_critical',
            'upload_warning',
            'upload_critical'
        )
    ");

    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        "success" => true,

        "download_warning" =>
            floatval($settings["download_warning"] ?? 80),

        "download_critical" =>
            floatval($settings["download_critical"] ?? 90),

        "upload_warning" =>
            floatval($settings["upload_warning"] ?? 20),

        "upload_critical" =>
            floatval($settings["upload_critical"] ?? 30)
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}