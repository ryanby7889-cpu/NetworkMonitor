<?php

require_once "../config/database.php";

$db = new Database();
$pdo = $db->connect();


// =====================================
// FILTER
// =====================================

$period = $_GET['period'] ?? 'today';


// =====================================
// TANGGAL
// =====================================

switch ($period) {

    case 'week':

        $start = date(
            'Y-m-d',
            strtotime('-6 days')
        );

        $end = date('Y-m-d');

        break;


    case 'month':

        $start = date('Y-m-01');

        $end = date('Y-m-d');

        break;


    case 'custom':

        $start = $_GET['start']
            ?? date('Y-m-d');

        $end = $_GET['end']
            ?? date('Y-m-d');

        break;


    default:

        $start = date('Y-m-d');

        $end = date('Y-m-d');

        break;
}


// =====================================
// QUERY DATA
// =====================================

$sql = "

    SELECT

        created_at,

        interface_name,

        download_mbps,

        upload_mbps,

        rx_packet,

        tx_packet,

        cpu,

        memory,

        disk

    FROM traffic_history

    WHERE DATE(created_at)
    BETWEEN :start AND :end

    ORDER BY created_at ASC

";

$stmt = $pdo->prepare($sql);

$stmt->execute([

    ':start' => $start,

    ':end' => $end

]);


// =====================================
// CSV HEADER
// =====================================

$filename =
    'traffic_history_' .
    $start .
    '_' .
    $end .
    '.csv';


header(
    'Content-Type: text/csv; charset=utf-8'
);

header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);


// =====================================
// OPEN CSV
// =====================================

$output = fopen('php://output', 'w');


// =====================================
// COLUMN HEADER
// =====================================

fputcsv($output, [

    'Time',

    'Interface',

    'Download (Mbps)',

    'Upload (Mbps)',

    'RX Packet (pkt/s)',

    'TX Packet (pkt/s)',

    'CPU (%)',

    'Memory (%)',

    'Disk (%)'

]);


// =====================================
// DATA
// =====================================

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    fputcsv($output, [

        $row['created_at'],

        $row['interface_name'],

        $row['download_mbps'],

        $row['upload_mbps'],

        $row['rx_packet'],

        $row['tx_packet'],

        $row['cpu'],

        $row['memory'],

        $row['disk']

    ]);

}


fclose($output);

exit;