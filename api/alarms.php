<?php

header("Content-Type: application/json");

require_once "../config/database.php";


$db = new Database();

$pdo = $db->connect();


/*
|--------------------------------------------------------------------------
| ACTIVE ALARMS
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    id,

    router_id,

    interface_name,

    alarm_type,

    severity,

    message,

    value,

    threshold,

    created_at

FROM alarms

WHERE status = 'active'

ORDER BY created_at DESC

";


$stmt =
    $pdo->query($sql);


$alarms =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| COUNT
|--------------------------------------------------------------------------
*/

$warning = 0;

$critical = 0;

$offline = 0;


foreach ($alarms as $alarm) {

    if (
        $alarm['severity'] ===
        'warning'
    ) {

        $warning++;

    }

    elseif (
        $alarm['severity'] ===
        'critical'
    ) {

        $critical++;

    }

    elseif (
        $alarm['severity'] ===
        'offline'
    ) {

        $offline++;

    }

}


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([

    "status" => "success",

    "summary" => [

        "total" =>
            count($alarms),

        "warning" =>
            $warning,

        "critical" =>
            $critical,

        "offline" =>
            $offline

    ],

    "alarms" =>
        $alarms

], JSON_PRETTY_PRINT);