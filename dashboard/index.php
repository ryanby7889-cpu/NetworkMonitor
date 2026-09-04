<?php
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$pageCss = ['../assets/css/dashboard.css'];
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Network Monitor PRO</title>

<!-- Bootstrap -->
<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<!-- Network Monitor shared CSS -->
<link rel="stylesheet" href="../assets/css/variables.css?v=2">
<link rel="stylesheet" href="../assets/css/common.css?v=2">
<link rel="stylesheet" href="../assets/css/theme.css?v=1">
<link rel="stylesheet" href="../assets/css/dashboard.css?v=2">

<!-- Bootstrap Icons -->
<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>


<body>


<!-- ================================
     SIDEBAR
================================ -->

<?php
$activeMenu = 'dashboard';
require_once "../includes/sidebar.php";
?>



<!-- ================================
     MAIN
================================ -->

<div class="main">


    <!-- TOP BAR -->

    <div class="topbar">

        <div>

            <div class="page-title">

                Network Monitoring

            </div>

            <div class="page-subtitle">

                Real-time MikroTik Ether1 traffic monitoring

            </div>

        </div>


        <button
        id="darkMode"
        class="btn btn-outline-secondary">

            <i class="bi bi-moon"></i>

        </button>

    </div>



    <!-- ================================
         STAT CARDS
    ================================= -->

    <div class="row g-4">


        <!-- STATUS -->

        <div class="col-xl col-lg-4 col-md-6">

            <div class="monitor-card stat-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="stat-title mt-0">

                            Router Status

                        </div>

                        <div
                        class="stat-value"
                        id="status">

                            Checking...

                        </div>

                    </div>


                    <div
                    class="stat-icon icon-green">

                        <i class="bi bi-wifi"></i>

                    </div>

                </div>

            </div>

        </div>



        <!-- DOWNLOAD -->

        <div class="col-xl col-lg-4 col-md-6">

            <div class="monitor-card stat-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="stat-title mt-0">

                            Download

                        </div>

                        <div
                        class="stat-value"
                        id="download">

                            0 Mbps

                        </div>

                    </div>


                    <div
                    class="stat-icon icon-blue">

                        <i class="bi bi-arrow-down"></i>

                    </div>

                </div>

            </div>

        </div>



        <!-- UPLOAD -->

       <div class="col-xl col-lg-4 col-md-6">

            <div class="monitor-card stat-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="stat-title mt-0">

                            Upload

                        </div>

                        <div
                        class="stat-value"
                        id="upload">

                            0 Mbps

                        </div>

                    </div>


                    <div
                    class="stat-icon icon-green">

                        <i class="bi bi-arrow-up"></i>

                    </div>

                </div>

            </div>

        </div>



        <!-- CPU -->

        <div class="col-xl col-lg-4 col-md-6">

            <div class="monitor-card stat-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="stat-title mt-0">

                            CPU Load

                        </div>

                        <div
                        class="stat-value"
                        id="cpu">

                            0 %

                        </div>

                    </div>


                    <div
                    class="stat-icon icon-red">

                        <i class="bi bi-cpu"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>

<!-- ALARM -->

<div class="col-xl col-lg-4 col-md-6">

    <div class="monitor-card stat-card">

        <div class="d-flex justify-content-between">

            <div>

                <div class="stat-title mt-0">
                    Active Alarm
                </div>

                <div
                    class="stat-value"
                    id="activeAlarm">
                    0
                </div>

            </div>

            <div
                class="stat-icon icon-red">

                <i class="bi bi-exclamation-triangle"></i>

            </div>

        </div>

    </div>

</div> 
    
<!-- ================================
     SYSTEM METRICS
================================ -->

<div class="row g-4 mt-1">

    <!-- MEMORY -->
    <div class="col-xl-3 col-md-6">

        <div class="monitor-card stat-card">

            <div class="d-flex justify-content-between">

                <div>

                    <div class="stat-title mt-0">
                        Memory Usage
                    </div>

                    <div
                        class="stat-value"
                        id="memory">
                        0 %
                    </div>

                </div>

                <div class="stat-icon icon-gray">

                    <i class="bi bi-memory"></i>

                </div>

            </div>

        </div>

    </div>


    <!-- DISK -->
    <div class="col-xl-3 col-md-6">

        <div class="monitor-card stat-card">

            <div class="d-flex justify-content-between">

                <div>

                    <div class="stat-title mt-0">
                        Disk Usage
                    </div>

                    <div
                        class="stat-value"
                        id="disk">
                        0 %
                    </div>

                </div>

                <div class="stat-icon icon-gray">

                    <i class="bi bi-device-hdd"></i>

                </div>

            </div>

        </div>

    </div>


    <!-- RX PACKET -->
    <div class="col-xl-3 col-md-6">

        <div class="monitor-card stat-card">

            <div class="d-flex justify-content-between">

                <div>

                    <div class="stat-title mt-0">
                        RX Packet
                    </div>

                    <div
                        class="stat-value"
                        id="rxPacket">
                        0 pkt/s
                    </div>

                </div>

                <div class="stat-icon icon-blue">

                    <i class="bi bi-box-arrow-in-down"></i>

                </div>

            </div>

        </div>

    </div>


    <!-- TX PACKET -->
    <div class="col-xl-3 col-md-6">

        <div class="monitor-card stat-card">

            <div class="d-flex justify-content-between">

                <div>

                    <div class="stat-title mt-0">
                        TX Packet
                    </div>

                    <div
                        class="stat-value"
                        id="txPacket">
                        0 pkt/s
                    </div>

                </div>

                <div class="stat-icon icon-green">

                    <i class="bi bi-box-arrow-up"></i>

                </div>

            </div>

        </div>

    </div>

</div>

    <!-- ================================
         CHART
    ================================= -->

    <div class="monitor-card chart-card mt-4">


        <div class="chart-header">

            <div>

                <div class="chart-title">

                    Traffic Ether1

                </div>

                <div class="chart-subtitle">

                    Real-time bandwidth monitoring

                </div>

            </div>


            <div>

                <span class="badge bg-primary">

                    <i class="bi bi-circle-fill"></i>

                    LIVE

                </span>

            </div>

        </div>


        <div class="chart-container">

            <canvas id="trafficChart"></canvas>

        </div>


    </div>



    <!-- ================================
         BOTTOM INFORMATION
    ================================= -->

    <div class="row g-4 mt-1">


        <!-- PEAK -->

        <div class="col-lg-4">


            <div class="monitor-card p-4">


                <h6 class="mb-4">

                    <i class="bi bi-speedometer2"></i>

                    Traffic Statistics

                </h6>


                <div class="row">


                    <div class="col-6">

                        <div class="peak-label">

                            Peak Download

                        </div>

                        <div
                        class="peak-value text-primary"
                        id="peakDownload">

                            0 Mbps

                        </div>

                    </div>


                    <div class="col-6">

                        <div class="peak-label">

                            Peak Upload

                        </div>

                        <div
                        class="peak-value text-success"
                        id="peakUpload">

                            0 Mbps

                        </div>

                    </div>

                </div>


            </div>

        </div>



        <!-- ROUTER -->

        <div class="col-lg-4">


            <div class="monitor-card p-4">


                <h6 class="mb-3">

                    <i class="bi bi-router"></i>

                    Router Information

                </h6>


                <div class="info-row">

                    <span class="info-label">

                        Router

                    </span>

                    <span
                    class="info-value"
                    id="routerName">

                        -

                    </span>

                </div>
                <div class="info-row">

    <span class="info-label">
        Board
    </span>

    <span
    class="info-value"
    id="board">

        -

    </span>

</div>


<div class="info-row">

    <span class="info-label">
        Architecture
    </span>

    <span
    class="info-value"
    id="architecture">

        -

    </span>

</div>


                <div class="info-row">

                    <span class="info-label">

                        RouterOS

                    </span>

                    <span
                    class="info-value"
                    id="version">

                        -

                    </span>

                </div>


            </div>

        </div>



        <!-- SYSTEM -->

        <div class="col-lg-4">


            <div class="monitor-card p-4">


                <h6 class="mb-3">

                    <i class="bi bi-clock-history"></i>

                    System Information

                </h6>


                <div class="info-row">

                    <span class="info-label">

                        Interface

                    </span>

                    <span class="info-value">

                        ether1

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">

                        Uptime

                    </span>

                    <span
                    class="info-value"
                    id="uptime">

                        -

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">

                        Last Update

                    </span>

                    <span
                    class="info-value"
                    id="lastUpdate">

                        -

                    </span>

                </div>


            </div>

        </div>


    </div>


</div>



<script>

/* =====================================================
   NETWORK MONITOR PRO
   REAL TIME DASHBOARD
===================================================== */


/* =====================================================
   CHART DATA
===================================================== */

const labels = [];

const downloadData = [];

const uploadData = [];

let peakDownload = 0;

let peakUpload = 0;


/* =====================================================
   ELEMENT
===================================================== */

const statusElement =
    document.getElementById("status");

const downloadElement =
    document.getElementById("download");

const uploadElement =
    document.getElementById("upload");

const cpuElement =
    document.getElementById("cpu");

const memoryElement =
    document.getElementById("memory");

const diskElement =
    document.getElementById("disk");

const rxPacketElement =
    document.getElementById("rxPacket");

const txPacketElement =
    document.getElementById("txPacket");

const activeAlarmElement =
    document.getElementById("activeAlarm");

const routerNameElement =
    document.getElementById("routerName");

const versionElement =
    document.getElementById("version");

const boardElement =
    document.getElementById("board");

const architectureElement =
    document.getElementById("architecture");

const uptimeElement =
    document.getElementById("uptime");

const lastUpdateElement =
    document.getElementById("lastUpdate");


/* =====================================================
   CHART
===================================================== */

const canvas =
    document.getElementById("trafficChart");


const ctx =
    canvas.getContext("2d");


const trafficChart = new Chart(ctx, {

    type: "line",

    data: {

        labels: labels,

        datasets: [

            {

                label: "Download",

                data: downloadData,

                borderColor: "#0d6efd",

                backgroundColor:
                    "rgba(13,110,253,0.12)",

                borderWidth: 3,

                fill: true,

                tension: 0.4,

                pointRadius: 0,

                pointHoverRadius: 6

            },

            {

                label: "Upload",

                data: uploadData,

                borderColor: "#20c997",

                backgroundColor:
                    "rgba(32,201,151,0.10)",

                borderWidth: 3,

                fill: true,

                tension: 0.4,

                pointRadius: 0,

                pointHoverRadius: 6

            }

        ]

    },


    options: {

        responsive: true,

        maintainAspectRatio: false,

        animation: false,


        interaction: {

            mode: "index",

            intersect: false

        },


        plugins: {

            legend: {

                position: "bottom",

                labels: {

                    usePointStyle: true,

                    padding: 20

                }

            },


            tooltip: {

                backgroundColor: "#111827",

                padding: 12,


                callbacks: {

                    label: function(context) {

                        return (

                            context.dataset.label +

                            " : " +

                            Number(
                                context.parsed.y
                            ).toFixed(2) +

                            " Mbps"

                        );

                    }

                }

            }

        },


        scales: {

            x: {

                grid: {

                    display: false

                },

                ticks: {

                    maxTicksLimit: 10,

                    maxRotation: 0

                }

            },


            y: {

                beginAtZero: true,

                title: {

                    display: true,

                    text: "Traffic (Mbps)"

                },

                grid: {

                    color:
                        "rgba(100,116,139,0.12)"

                }

            }

        }

    }

});


/* =====================================================
   LOAD DATA
===================================================== */

async function loadData() {

    try {

        console.log("Mengambil data MikroTik...");


        const response = await fetch(
            "../api/traffic.php?nocache=" +
            Date.now(),
            {
                cache: "no-store"
            }
        );


        if (!response.ok) {

            throw new Error(
                "HTTP Error: " +
                response.status
            );

        }


        const data =
            await response.json();


        console.log(
            "Data MikroTik:",
            data
        );


        /* =================================================
           STATUS
        ================================================= */

        if (data.status === "online") {

            statusElement.innerHTML =
                '<span class="status-dot"></span>ONLINE';

            statusElement.classList.remove(
                "text-danger"
            );

        }

        else {

            statusElement.innerHTML =
                '<span class="status-dot status-offline"></span>OFFLINE';

        }


        /* =================================================
           TRAFFIC
        ================================================= */

        const download =
            Number(data.download) || 0;


        const upload =
            Number(data.upload) || 0;


        const cpu =
            Number(data.cpu) || 0;


        const memory =
            Number(data.memory) || 0;


        const disk =
            Number(data.disk) || 0;


        const rxPacket =
            Number(data.rx_packet) || 0;


        const txPacket =
            Number(data.tx_packet) || 0;


        /* =================================================
           DISPLAY
        ================================================= */

        downloadElement.innerHTML =
            download.toFixed(2) +
            " Mbps";


        uploadElement.innerHTML =
            upload.toFixed(2) +
            " Mbps";


        cpuElement.innerHTML =
            cpu.toFixed(0) +
            " %";


        if (memoryElement) {

            memoryElement.innerHTML =
                memory.toFixed(1) +
                " %";

        }


        if (diskElement) {

            diskElement.innerHTML =
                disk.toFixed(1) +
                " %";

        }


        if (rxPacketElement) {

            rxPacketElement.innerHTML =
                rxPacket.toLocaleString() +
                " pkt/s";

        }


        if (txPacketElement) {

            txPacketElement.innerHTML =
                txPacket.toLocaleString() +
                " pkt/s";

        }


        /* =================================================
           ROUTER INFORMATION
        ================================================= */

        if (routerNameElement) {

            routerNameElement.innerHTML =
                data.router || "-";

        }


        if (versionElement) {

            versionElement.innerHTML =
                data.version || "-";

        }


        if (boardElement) {

            boardElement.innerHTML =
                data.board || "-";

        }


        if (architectureElement) {

            architectureElement.innerHTML =
                data.architecture || "-";

        }


        if (uptimeElement) {

            uptimeElement.innerHTML =
                data.uptime || "-";

        }


        /* =================================================
           LAST UPDATE
        ================================================= */

        const now =
            new Date();


        if (lastUpdateElement) {

            lastUpdateElement.innerHTML =
                now.toLocaleTimeString();

        }


        /* =================================================
           PEAK
        ================================================= */

        if (download > peakDownload) {

            peakDownload =
                download;

        }


        if (upload > peakUpload) {

            peakUpload =
                upload;

        }


        const peakDownloadElement =
            document.getElementById(
                "peakDownload"
            );


        const peakUploadElement =
            document.getElementById(
                "peakUpload"
            );


        if (peakDownloadElement) {

            peakDownloadElement.innerHTML =
                peakDownload.toFixed(2) +
                " Mbps";

        }


        if (peakUploadElement) {

            peakUploadElement.innerHTML =
                peakUpload.toFixed(2) +
                " Mbps";

        }


        /* =================================================
           CHART
        ================================================= */

        labels.push(
            now.toLocaleTimeString()
        );


        downloadData.push(
            download
        );


        uploadData.push(
            upload
        );


        /* =================================================
           LIMIT DATA
           MAX 60 POINT
        ================================================= */

        if (labels.length > 60) {

            labels.shift();

            downloadData.shift();

            uploadData.shift();

        }


        trafficChart.update(
            "none"
        );


        console.log(
            "Dashboard berhasil diperbarui."
        );

    }


    catch (error) {

        console.error(
            "Network Monitor Error:",
            error
        );


        statusElement.innerHTML =
            '<span class="status-dot status-offline"></span>OFFLINE';

    }

}

/* =====================================================
   LOAD ACTIVE ALARM
===================================================== */

async function loadActiveAlarm() {

    try {

        const response =
            await fetch(
                "../api/alarm_status.php?nocache=" +
                Date.now(),
                {
                    cache: "no-store"
                }
            );


        if (!response.ok) {

            throw new Error(
                "Alarm HTTP Error: " +
                response.status
            );

        }


        const data =
            await response.json();


        if (
            data.success &&
            activeAlarmElement
        ) {

            const total =
                Number(data.active_alarm) || 0;


            activeAlarmElement.innerHTML =
                total;


            /*
             * Warna indikator
             */

            if (total > 0) {

                activeAlarmElement.classList.remove(
                    "text-success"
                );

                activeAlarmElement.classList.add(
                    "text-danger"
                );

            }

            else {

                activeAlarmElement.classList.remove(
                    "text-danger"
                );

                activeAlarmElement.classList.add(
                    "text-success"
                );

            }

        }

    }

    catch (error) {

        console.error(
            "Active Alarm Error:",
            error
        );


        if (activeAlarmElement) {

            activeAlarmElement.innerHTML =
                "0";

        }

    }

}

/* =====================================================
   DASHBOARD REFRESH SETTINGS
===================================================== */

let refreshInterval = 1000;


/* =====================================================
   LOAD SETTINGS
===================================================== */

async function loadSettings() {

    try {

        const response =
            await fetch(
                "../api/settings.php?nocache=" +
                Date.now(),
                {
                    cache: "no-store"
                }
            );


        if (!response.ok) {

            throw new Error(
                "Settings HTTP Error: " +
                response.status
            );

        }


        const settings =
            await response.json();


        if (
            settings.success &&
            Number(settings.refresh_interval) > 0
        ) {

            refreshInterval =
                Number(
                    settings.refresh_interval
                );

        }


        console.log(
            "Refresh interval:",
            refreshInterval,
            "ms"
        );

    }


    catch (error) {

        console.error(
            "Settings Error:",
            error
        );


        /*
         * Jika Settings gagal dibaca,
         * gunakan default 1 detik.
         */

        refreshInterval = 1000;

    }

}


/* =====================================================
   REFRESH LOOP
===================================================== */

async function refreshDashboard() {

    await loadData();

    await loadActiveAlarm();


    setTimeout(
        refreshDashboard,
        refreshInterval
    );

}


/* =====================================================
   FIRST LOAD
===================================================== */

async function startDashboard() {

    await loadSettings();

    refreshDashboard();

}


startDashboard();

</script>
<script src="../assets/js/app.js?v=1"></script>
</body>
</html>
