<?php
$pageTitle = 'Traffic Monitoring';
$activeMenu = 'traffic';
$pageCss = ['../assets/css/traffic.css'];
?>
<?php

header("Content-Type: application/json");

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$db = new Database();

$pdo = $db->connect();


/*
|--------------------------------------------------------------------------
| RANGE
|--------------------------------------------------------------------------
*/

$range = $_GET['range'] ?? '1h';


switch ($range) {

    case '5m':
        $minutes = 5;
        break;

    case '1h':
        $minutes = 60;
        break;

    case '6h':
        $minutes = 360;
        break;

    case '12h':
        $minutes = 720;
        break;

    case '24h':
        $minutes = 1440;
        break;

    case '7d':
        $minutes = 10080;
        break;

    case '30d':
        $minutes = 43200;
        break;

    default:
        $minutes = 60;
}


/*
|--------------------------------------------------------------------------
| AMBIL DATA
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    created_at,
    download_mbps,
    upload_mbps,
    rx_packet,
    tx_packet,
    cpu,
    memory,
    disk

FROM traffic_history

WHERE router_id = 1

AND interface_name = 'ether1'

AND created_at >= DATE_SUB(
    NOW(),
    INTERVAL :minutes MINUTE
)

ORDER BY created_at ASC

";


$stmt = $pdo->prepare($sql);

$stmt->bindValue(
    ':minutes',
    $minutes,
    PDO::PARAM_INT
);

$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$downloadValues = [];

$uploadValues = [];

$peakDownload = null;

$peakUpload = null;


foreach ($rows as $row) {

    $download =
        (float)$row['download_mbps'];

    $upload =
        (float)$row['upload_mbps'];


    $downloadValues[] =
        $download;

    $uploadValues[] =
        $upload;


    /*
    |--------------------------------------------------------------------------
    | PEAK DOWNLOAD
    |--------------------------------------------------------------------------
    */

    if (
        $peakDownload === null ||
        $download > $peakDownload['value']
    ) {

        $peakDownload = [

            'value' => $download,

            'time' =>
                $row['created_at']

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | PEAK UPLOAD
    |--------------------------------------------------------------------------
    */

    if (
        $peakUpload === null ||
        $upload > $peakUpload['value']
    ) {

        $peakUpload = [

            'value' => $upload,

            'time' =>
                $row['created_at']

        ];

    }

}


/*
|--------------------------------------------------------------------------
| CALCULATION
|--------------------------------------------------------------------------
*/

$count =
    count($rows);


if ($count > 0) {

    $avgDownload =
        array_sum($downloadValues) / $count;

    $avgUpload =
        array_sum($uploadValues) / $count;


    $maxDownload =
        max($downloadValues);

    $minDownload =
        min($downloadValues);


    $maxUpload =
        max($uploadValues);

    $minUpload =
        min($uploadValues);

} else {

    $avgDownload = 0;
    $avgUpload = 0;

    $maxDownload = 0;
    $minDownload = 0;

    $maxUpload = 0;
    $minUpload = 0;

}


/*
|--------------------------------------------------------------------------
| FORMAT DATA
|--------------------------------------------------------------------------
*/

$data = [];


foreach ($rows as $row) {

    $data[] = [

        "time" =>
            $row['created_at'],

        "download" =>
            (float)$row['download_mbps'],

        "upload" =>
            (float)$row['upload_mbps'],

        "rx_packet" =>
            (int)$row['rx_packet'],

        "tx_packet" =>
            (int)$row['tx_packet'],

        "cpu" =>
            (float)$row['cpu'],

        "memory" =>
            (float)$row['memory'],

        "disk" =>
            (float)$row['disk']

    ];

}


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([

    "status" => "success",

    "range" => $range,

    "count" => $count,

    "statistics" => [

        "avg_download" =>
            round($avgDownload, 2),

        "max_download" =>
            round($maxDownload, 2),

        "min_download" =>
            round($minDownload, 2),

        "peak_download" =>
            $peakDownload,

        "avg_upload" =>
            round($avgUpload, 2),

        "max_upload" =>
            round($maxUpload, 2),

        "min_upload" =>
            round($minUpload, 2),

        "peak_upload" =>
            $peakUpload

    ],

    "data" => $data

], JSON_PRETTY_PRINT);