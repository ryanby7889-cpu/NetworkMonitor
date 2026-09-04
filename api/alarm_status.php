<?php

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

try {

    $db = new Database();

    $pdo = $db->connect();


    $sql = "
        SELECT COUNT(*) AS total
        FROM alarms
        WHERE status = 'active'
    ";


    $stmt = $pdo->query($sql);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);


    echo json_encode([

        "success" => true,

        "active_alarm" =>
            (int)$row["total"]

    ]);

}

catch (Exception $e) {

    http_response_code(500);

    echo json_encode([

        "success" => false,

        "active_alarm" => 0,

        "error" => $e->getMessage()

    ]);

}