<?php
$activeMenu = 'settings';
require_once "../config/database.php";

$db = new Database();
$pdo = $db->connect();

$message = "";
$messageType = "success";


/* ==========================================
   SAVE SETTINGS
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $companyName =
        trim($_POST["company_name"] ?? "");

    $refreshInterval =
        intval($_POST["refresh_interval"] ?? 1000);

    $timezone =
        trim($_POST["timezone"] ?? "Asia/Jakarta");


    /*
    |--------------------------------------------------------------------------
    | ALARM THRESHOLD
    |--------------------------------------------------------------------------
    */

    $downloadWarning =
        floatval($_POST["download_warning"] ?? 80);

    $downloadCritical =
        floatval($_POST["download_critical"] ?? 90);

    $uploadWarning =
        floatval($_POST["upload_warning"] ?? 20);

    $uploadCritical =
        floatval($_POST["upload_critical"] ?? 30);


    /*
    |--------------------------------------------------------------------------
    | DEFAULT COMPANY
    |--------------------------------------------------------------------------
    */

    if ($companyName === "") {

        $companyName =
            "Network Monitor";

    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE REFRESH
    |--------------------------------------------------------------------------
    */

    if ($refreshInterval < 500) {

        $refreshInterval = 500;

    }


    if ($refreshInterval > 60000) {

        $refreshInterval = 60000;

    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE ALARM
    |--------------------------------------------------------------------------
    */

    if ($downloadWarning < 0) {

        $downloadWarning = 0;

    }


    if ($downloadCritical <= $downloadWarning) {

        $message =
            "Download Critical harus lebih besar dari Download Warning.";

        $messageType = "danger";

    }


    elseif ($uploadWarning < 0) {

        $uploadWarning = 0;

    }


    elseif ($uploadCritical <= $uploadWarning) {

        $message =
            "Upload Critical harus lebih besar dari Upload Warning.";

        $messageType = "danger";

    }


    else {

        /*
        |--------------------------------------------------------------------------
        | SAVE ALL SETTINGS
        |--------------------------------------------------------------------------
        */

        $settings = [

            "company_name" =>
                $companyName,

            "refresh_interval" =>
                $refreshInterval,

            "timezone" =>
                $timezone,

            "download_warning" =>
                $downloadWarning,

            "download_critical" =>
                $downloadCritical,

            "upload_warning" =>
                $uploadWarning,

            "upload_critical" =>
                $uploadCritical

        ];


        foreach ($settings as $name => $value) {

            $stmt = $pdo->prepare("
                UPDATE settings
                SET setting_value = ?
                WHERE setting_name = ?
            ");


            $stmt->execute([

                $value,

                $name

            ]);

        }


        $message =
            "Settings berhasil disimpan.";

        $messageType =
            "success";

    }

}


/* ==========================================
   LOAD SETTINGS
========================================== */

$stmt = $pdo->query("
    SELECT setting_name, setting_value
    FROM settings
");

$rows =
    $stmt->fetchAll(PDO::FETCH_KEY_PAIR);


/*
|--------------------------------------------------------------------------
| GENERAL SETTINGS
|--------------------------------------------------------------------------
*/

$companyName =
    $rows["company_name"]
    ?? "Network Monitor";


$refreshInterval =
    $rows["refresh_interval"]
    ?? 1000;


$timezone =
    $rows["timezone"]
    ?? "Asia/Jakarta";


/*
|--------------------------------------------------------------------------
| ALARM SETTINGS
|--------------------------------------------------------------------------
*/

$downloadWarning =
    $rows["download_warning"]
    ?? 80;


$downloadCritical =
    $rows["download_critical"]
    ?? 90;


$uploadWarning =
    $rows["upload_warning"]
    ?? 20;


$uploadCritical =
    $rows["upload_critical"]
    ?? 30;

?>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Settings - NetMonitor</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/common.css">
<link rel="stylesheet" href="../assets/css/settings.css">

<script>
(function () {
    try {
        if (localStorage.getItem('netmonitor_theme') === 'dark') {
            document.documentElement.classList.add('theme-dark');
        }
    } catch (e) {}
})();
</script>
</head>


<body>


<!-- ==========================================
     SIDEBAR
========================================== -->

<?php require_once "../includes/sidebar.php"; ?>

<!-- ==========================================
     MAIN
========================================== -->

<div class="main">


    <div class="page-title">

        Settings

    </div>


    <div class="page-subtitle">

        Network Monitor application settings

    </div>


    <?php if ($message): ?>

        <div
        class="alert alert-<?=
            htmlspecialchars($messageType)
        ?>">

            <i class="bi bi-check-circle"></i>

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <div class="settings-card">


        <h5 class="mb-4">

            <i class="bi bi-sliders"></i>

            Monitoring Settings

        </h5>


        <form method="POST">

 
            <!-- COMPANY NAME -->

            <div class="setting-row">

                <div class="setting-label">

                    Company / Application Name

                </div>


                <div class="setting-description">

                    Nama yang digunakan oleh aplikasi
                    Network Monitor.

                </div>


                <input
                type="text"
                name="company_name"
                class="form-control"
                value="<?=
                    htmlspecialchars($companyName)
                ?>"
                required>

            </div>


            <!-- REFRESH -->

            <div class="setting-row">

                <div class="setting-label">

                    Refresh Interval

                </div>


                <div class="setting-description">

                    Interval pengambilan data monitoring
                    dalam milliseconds.

                </div>


                <select
                name="refresh_interval"
                class="form-select">

                    <option
                    value="1000"
                    <?= $refreshInterval == 1000
                        ? "selected"
                        : "" ?>>

                        1 detik

                    </option>


                    <option
                    value="2000"
                    <?= $refreshInterval == 2000
                        ? "selected"
                        : "" ?>>

                        2 detik

                    </option>


                    <option
                    value="5000"
                    <?= $refreshInterval == 5000
                        ? "selected"
                        : "" ?>>

                        5 detik

                    </option>


                    <option
                    value="10000"
                    <?= $refreshInterval == 10000
                        ? "selected"
                        : "" ?>>

                        10 detik

                    </option>


                    <option
                    value="30000"
                    <?= $refreshInterval == 30000
                        ? "selected"
                        : "" ?>>

                        30 detik

                    </option>

                </select>

            </div>


            <!-- TIMEZONE -->

            <div class="setting-row">

                <div class="setting-label">

                    Timezone

                </div>


                <div class="setting-description">

                    Zona waktu yang digunakan
                    oleh sistem monitoring.

                </div>


                <select
                name="timezone"
                class="form-select">


                    <option
                    value="Asia/Jakarta"
                    <?= $timezone === "Asia/Jakarta"
                        ? "selected"
                        : "" ?>>

                        Asia/Jakarta (WIB)

                    </option>


                    <option
                    value="Asia/Makassar"
                    <?= $timezone === "Asia/Makassar"
                        ? "selected"
                        : "" ?>>

                        Asia/Makassar (WITA)

                    </option>


                    <option
                    value="Asia/Jayapura"
                    <?= $timezone === "Asia/Jayapura"
                        ? "selected"
                        : "" ?>>

                        Asia/Jayapura (WIT)

                    </option>

                </select>

            </div>

<!-- ==========================================
     ALARM THRESHOLD
========================================== -->

<div class="setting-row">

    <div class="setting-label">

        Alarm Threshold

    </div>


    <div class="setting-description">

        Batas traffic yang digunakan untuk
        menghasilkan alarm Warning dan Critical.

    </div>


    <!-- DOWNLOAD WARNING -->

    <label class="form-label">

        Download Warning (Mbps)

    </label>

    <input
        type="number"
        name="download_warning"
        class="form-control"
        value="<?= htmlspecialchars($downloadWarning) ?>"
        min="0"
        step="0.01"
        required
    >


    <!-- DOWNLOAD CRITICAL -->

    <label class="form-label mt-3">

        Download Critical (Mbps)

    </label>

    <input
        type="number"
        name="download_critical"
        class="form-control"
        value="<?= htmlspecialchars($downloadCritical) ?>"
        min="0"
        step="0.01"
        required
    >


    <!-- UPLOAD WARNING -->

    <label class="form-label mt-3">

        Upload Warning (Mbps)

    </label>

    <input
        type="number"
        name="upload_warning"
        class="form-control"
        value="<?= htmlspecialchars($uploadWarning) ?>"
        min="0"
        step="0.01"
        required
    >


    <!-- UPLOAD CRITICAL -->

    <label class="form-label mt-3">

        Upload Critical (Mbps)

    </label>

    <input
        type="number"
        name="upload_critical"
        class="form-control"
        value="<?= htmlspecialchars($uploadCritical) ?>"
        min="0"
        step="0.01"
        required
    >

</div>
            <button
            type="submit"
            class="btn btn-primary save-button">

                <i class="bi bi-save"></i>

                Simpan Settings

            </button>


        </form>


    </div>
<!-- WHATSAPP BILLING -->
<div class="settings-card whatsapp-settings-card">

    <div class="settings-card-header">
        <div>
            <h2>WhatsApp Billing</h2>

            <p>
                Pengaturan notifikasi WhatsApp untuk pelanggan PPPoE,
                tagihan, jatuh tempo, pembayaran, dan suspend.
            </p>
        </div>

        <a href="whatsapp.php" class="btn btn-primary">
            WhatsApp Billing
        </a>
    </div>

</div>

</div>
