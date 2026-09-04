<?php
$pageTitle = 'Alarm';
$activeMenu = 'alarm';
$pageCss = ['../assets/css/alarm.css'];
?>
<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/alarm.php";


class AlarmEngine
{

    private $pdo;


    public function __construct()
    {

        $db = new Database();

        $this->pdo = $db->connect();

    }


    /*
    |--------------------------------------------------------------------------
    | CHECK TRAFFIC
    |--------------------------------------------------------------------------
    */

    public function checkTraffic(
        $routerId,
        $interface,
        $download,
        $upload
    )
    {

        /*
        |--------------------------------------------------------------------------
        | DOWNLOAD
        |--------------------------------------------------------------------------
        */

        if (
            $download >= AlarmConfig::downloadCritical()
        ) {

            $this->createAlarm(
                $routerId,
                $interface,
                "download_high",
                "critical",
                "Download Ether1 sangat tinggi",
                $download,
                AlarmConfig::downloadCritical()
            );

        }

        elseif (
            $download >= AlarmConfig::downloadWarning()
        ) {

            $this->createAlarm(
                $routerId,
                $interface,
                "download_high",
                "warning",
                "Download Ether1 tinggi",
                $download,
                AlarmConfig::downloadWarning()
            );

        }

        else {

            $this->resolveAlarm(
                $routerId,
                $interface,
                "download_high"
            );

        }


        /*
        |--------------------------------------------------------------------------
        | UPLOAD
        |--------------------------------------------------------------------------
        */

        if (
            $upload >= AlarmConfig::uploadCritical()
        ) {

            $this->createAlarm(
                $routerId,
                $interface,
                "upload_high",
                "critical",
                "Upload Ether1 sangat tinggi",
                $upload,
                AlarmConfig::uploadCritical()
            );

        }

        elseif (
            $upload >= AlarmConfig::uploadWarning()
        ) {

            $this->createAlarm(
                $routerId,
                $interface,
                "upload_high",
                "warning",
                "Upload Ether1 tinggi",
                $upload,
                AlarmConfig::uploadWarning()
            );

        }

        else {

            $this->resolveAlarm(
                $routerId,
                $interface,
                "upload_high"
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | CREATE ALARM
    |--------------------------------------------------------------------------
    */

    private function createAlarm(
        $routerId,
        $interface,
        $type,
        $severity,
        $message,
        $value,
        $threshold
    )
    {

        $sql = "

        SELECT id

        FROM alarms

        WHERE router_id = :router_id

        AND interface_name = :interface

        AND alarm_type = :type

        AND status = 'active'

        LIMIT 1

        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([

            ':router_id' => $routerId,

            ':interface' => $interface,

            ':type' => $type

        ]);


        /*
        |--------------------------------------------------------------------------
        | JIKA ALARM SUDAH ADA
        |--------------------------------------------------------------------------
        */

        if ($stmt->fetch()) {

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | INSERT ALARM
        |--------------------------------------------------------------------------
        */

        $sql = "

        INSERT INTO alarms

        (

            router_id,

            interface_name,

            alarm_type,

            severity,

            message,

            value,

            threshold,

            status

        )

        VALUES

        (

            :router_id,

            :interface,

            :type,

            :severity,

            :message,

            :value,

            :threshold,

            'active'

        )

        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([

            ':router_id' => $routerId,

            ':interface' => $interface,

            ':type' => $type,

            ':severity' => $severity,

            ':message' => $message,

            ':value' => $value,

            ':threshold' => $threshold

        ]);

    }


    /*
    |--------------------------------------------------------------------------
    | RESOLVE TRAFFIC ALARM
    |--------------------------------------------------------------------------
    */

    private function resolveAlarm(
        $routerId,
        $interface,
        $type
    )
    {

        $sql = "

        UPDATE alarms

        SET

            status = 'resolved',

            resolved_at = NOW()

        WHERE router_id = :router_id

        AND interface_name = :interface

        AND alarm_type = :type

        AND status = 'active'

        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([

            ':router_id' => $routerId,

            ':interface' => $interface,

            ':type' => $type

        ]);

    }


    /*
    |--------------------------------------------------------------------------
    | ROUTER OFFLINE
    |--------------------------------------------------------------------------
    */

    public function routerOffline($routerId)
    {

        /*
        |------------------------------------------------------------------
        | AVOID FALSE OFFLINE ALARMS
        |------------------------------------------------------------------
        |
        | A single RouterOS API timeout does not mean the router is offline.
        | If another successful collector cycle has stored traffic within the
        | last minute, keep the router online and clear any stale alarm.
        |
        */
        $recentSample = $this->pdo->prepare(
            "SELECT 1
             FROM traffic_history
             WHERE router_id = :router_id
               AND interface_name = 'ether1'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
             LIMIT 1"
        );

        $recentSample->execute([
            ':router_id' => $routerId
        ]);

        if ($recentSample->fetchColumn()) {
            $this->routerOnline($routerId);
            return false;
        }

        $sql = "

        SELECT id

        FROM alarms

        WHERE router_id = :router_id

        AND alarm_type = 'router_offline'

        AND status = 'active'

        LIMIT 1

        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([

            ':router_id' => $routerId

        ]);


        $existing = $stmt->fetch(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | JIKA ALARM OFFLINE SUDAH AKTIF
        |--------------------------------------------------------------------------
        */

        if ($existing) {

            return false;

        }


        /*
        |--------------------------------------------------------------------------
        | INSERT OFFLINE ALARM
        |--------------------------------------------------------------------------
        */

        $sql = "

        INSERT INTO alarms

        (

            router_id,

            interface_name,

            alarm_type,

            severity,

            message,

            value,

            threshold,

            status,

            created_at

        )

        VALUES

        (

            :router_id,

            'ether1',

            'router_offline',

            'critical',

            'Router MikroTik Offline',

            0,

            0,

            'active',

            NOW()

        )

        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([

            ':router_id' => $routerId

        ]);


        return true;

    }


    /*
    |--------------------------------------------------------------------------
    | ROUTER ONLINE
    |--------------------------------------------------------------------------
    */

    public function routerOnline($routerId)
    {

        $sql = "

        UPDATE alarms

        SET

            status = 'resolved',

            resolved_at = NOW()

        WHERE router_id = :router_id

        AND alarm_type = 'router_offline'

        AND status = 'active'

        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([

            ':router_id' => $routerId

        ]);


        return $stmt->rowCount();

    }

}
