<?php

require_once __DIR__ . "/database.php";


class AlarmConfig
{

    /*
    |--------------------------------------------------------------------------
    | GET SETTING
    |--------------------------------------------------------------------------
    */

    private static function getSetting($name, $default)
    {

        try {

            $db = new Database();

            $pdo = $db->connect();


            $stmt = $pdo->prepare("
                SELECT setting_value
                FROM settings
                WHERE setting_name = :name
                LIMIT 1
            ");


            $stmt->execute([
                ':name' => $name
            ]);


            $value = $stmt->fetchColumn();


            if ($value === false) {

                return $default;

            }


            return floatval($value);

        }

        catch (Exception $e) {

            return $default;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

    public static function downloadWarning()
    {

        return self::getSetting(
            'download_warning',
            80
        );

    }


    public static function downloadCritical()
    {

        return self::getSetting(
            'download_critical',
            90
        );

    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD
    |--------------------------------------------------------------------------
    */

    public static function uploadWarning()
    {

        return self::getSetting(
            'upload_warning',
            20
        );

    }


    public static function uploadCritical()
    {

        return self::getSetting(
            'upload_critical',
            30
        );

    }

}