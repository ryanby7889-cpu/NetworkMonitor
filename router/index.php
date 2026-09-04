<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Router - NetMonitor</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/common.css">
<link rel="stylesheet" href="../assets/css/router.css">

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


<!-- =====================================================
     SIDEBAR
===================================================== -->

<?php
$activeMenu = 'router';
require_once "../includes/sidebar.php";
?>

<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


    <!-- HEADER -->

    <div class="page-header">

        <div>

            <h1 class="page-title">

                Router Management

            </h1>

            <div class="page-subtitle">

                MikroTik router information

            </div>

        </div>


        <div class="refresh-info">

            <div>

                Auto refresh 10 seconds

            </div>

            <div class="mt-2">

                <button
                type="button"
                class="btn btn-primary btn-sm btn-refresh"
                id="refreshButton">

                    <i class="bi bi-arrow-clockwise"></i>

                    Refresh

                </button>

            </div>

        </div>

    </div>


    <!-- =================================================
         TOP SECTION
    ================================================= -->

    <div class="row g-4 mb-4">


        <!-- STATUS -->

        <div class="col-lg-4">

            <div class="card status-card p-4">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="status-label">

                            Router Status

                        </div>

                        <div
                        class="status"
                        id="status">

                            Checking...

                        </div>

                    </div>


                    <div class="status-icon">

                        <i class="bi bi-wifi"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- BASIC INFORMATION -->

        <div class="col-lg-8">

            <div class="card info-card">

                <div class="info-title">

                    <i class="bi bi-router"></i>

                    Router Information

                </div>


                <div class="info-row">

                    <span class="info-label">

                        Router

                    </span>

                    <span
                    class="info-value"
                    id="router">

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

            </div>

        </div>

    </div>


    <!-- =================================================
         SYSTEM INFORMATION
    ================================================= -->

    <div class="row g-4">


        <div class="col-lg-6">

            <div class="card extra-card">

                <div class="extra-title">

                    <i class="bi bi-hdd-network"></i>

                    Network Information

                </div>


                <div class="extra-row">

                    <span class="extra-label">

                        Monitoring Interface

                    </span>

                    <span
                    class="extra-value">

                        ether1

                    </span>

                </div>


                <div class="extra-row">

                    <span class="extra-label">

                        Connection

                    </span>

                    <span
                    class="extra-value"
                    id="connection">

                        Checking...

                    </span>

                </div>


                <div class="extra-row">

                    <span class="extra-label">

                        Last Update

                    </span>

                    <span
                    class="extra-value"
                    id="lastUpdate">

                        -

                    </span>

                </div>

            </div>

        </div>


        <div class="col-lg-6">

            <div class="card extra-card">

                <div class="extra-title">

                    <i class="bi bi-info-circle"></i>

                    Monitoring Information

                </div>


                <div class="extra-row">

                    <span class="extra-label">

                        Data Source

                    </span>

                    <span class="extra-value">

                        MikroTik API

                    </span>

                </div>


                <div class="extra-row">

                    <span class="extra-label">

                        Monitoring Interval

                    </span>

                    <span class="extra-value">

                        10 seconds

                    </span>

                </div>


                <div class="extra-row">

                    <span class="extra-label">

                        Interface

                    </span>

                    <span class="extra-value">

                        ether1

                    </span>

                </div>

            </div>

        </div>


    </div>

</div>


<script>

/* =====================================================
   ELEMENT
===================================================== */

const statusElement =
    document.getElementById("status");

const routerElement =
    document.getElementById("router");

const boardElement =
    document.getElementById("board");

const architectureElement =
    document.getElementById("architecture");

const versionElement =
    document.getElementById("version");

const uptimeElement =
    document.getElementById("uptime");

const connectionElement =
    document.getElementById("connection");

const lastUpdateElement =
    document.getElementById("lastUpdate");

const refreshButton =
    document.getElementById("refreshButton");


/* =====================================================
   LOAD ROUTER
===================================================== */

async function loadRouter(){

    try{

        refreshButton.disabled = true;

        refreshButton.innerHTML =
            '<span class="spinner-border spinner-border-sm"></span> Loading';


        console.log(
            "Mengambil data MikroTik..."
        );


        const response =
            await fetch(
                "../api/traffic.php?nocache="
                + Date.now(),
                {
                    cache:"no-store"
                }
            );


        if(!response.ok){

            throw new Error(
                "HTTP Error "
                + response.status
            );

        }


        const data =
            await response.json();


        console.log(
            "Data Router:",
            data
        );


        /* =============================================
           STATUS
        ============================================= */

        if(data.status === "online"){

            statusElement.innerHTML =
                '<span class="status-dot"></span>ONLINE';

            statusElement.classList.remove(
                "offline"
            );

            connectionElement.innerHTML =
                '<span class="text-success">Connected</span>';

        }

        else{

            statusElement.innerHTML =
                '<span class="status-dot offline"></span>OFFLINE';

            statusElement.classList.add(
                "offline"
            );

            connectionElement.innerHTML =
                '<span class="text-danger">Disconnected</span>';

        }


        /* =============================================
           ROUTER INFORMATION
        ============================================= */

        routerElement.innerHTML =
            data.router || "-";


        boardElement.innerHTML =
            data.board || "-";


        architectureElement.innerHTML =
            data.architecture || "-";


        versionElement.innerHTML =
            data.version || "-";


        uptimeElement.innerHTML =
            data.uptime || "-";


        /* =============================================
           LAST UPDATE
        ============================================= */

        const now =
            new Date();


        lastUpdateElement.innerHTML =
            now.toLocaleTimeString(
                "id-ID"
            );


        console.log(
            "Router berhasil diperbarui."
        );

    }


    catch(error){

        console.error(
            "Router Monitor Error:",
            error
        );


        statusElement.innerHTML =
            '<span class="status-dot offline"></span>OFFLINE';


        statusElement.classList.add(
            "offline"
        );


        connectionElement.innerHTML =
            '<span class="text-danger">Connection Error</span>';

    }


    finally{

        refreshButton.disabled = false;

        refreshButton.innerHTML =
            '<i class="bi bi-arrow-clockwise"></i> Refresh';

    }

}


/* =====================================================
   FIRST LOAD
===================================================== */

loadRouter();


/* =====================================================
   AUTO REFRESH
===================================================== */

setInterval(
    loadRouter,
    10000
);


/* =====================================================
   MANUAL REFRESH
===================================================== */

refreshButton.addEventListener(
    "click",
    function(){

        loadRouter();

    }
);

</script>


</body>

</html>