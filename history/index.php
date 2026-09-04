<?php
/*
 * URL lama untuk Traffic History.
 * Halaman resmi berada di ../traffic/ agar semua pengguna mendapat
 * filter tanggal, ekspor CSV, tabel lengkap, dan navigasi yang sama.
 */
header('Location: ../traffic/index.php', true, 302);
exit;
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Traffic History - NetMonitor</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<script
src="https://cdn.jsdelivr.net/npm/chart.js">
</script>

<link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/theme.css?v=1">
<link rel="stylesheet" href="../assets/css/history.css">

</head>


<body>


<div class="container-fluid p-4">


<!-- =========================================================
     HEADER
========================================================= -->

<div class="d-flex
            justify-content-between
            align-items-center
            mb-4">

    <div>

        <h2 class="page-title mb-1">

            Traffic History

        </h2>

        <div class="text-muted">

            MikroTik Ether1 traffic history

        </div>

    </div>


    <a
    href="../dashboard/index.php"
    class="btn btn-outline-primary">

        ← Dashboard

    </a>

</div>



<!-- =========================================================
     RANGE
========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-body">

<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-2">

    <strong>

        Time Range

    </strong>


    <div
    class="btn-group">

        <button
        class="btn btn-outline-primary range-btn"
        data-range="5m">

            5 Min

        </button>


        <button
        class="btn btn-outline-primary range-btn active"
        data-range="1h">

            1 Hour

        </button>


        <button
        class="btn btn-outline-primary range-btn"
        data-range="6h">

            6 Hours

        </button>


        <button
        class="btn btn-outline-primary range-btn"
        data-range="12h">

            12 Hours

        </button>


        <button
        class="btn btn-outline-primary range-btn"
        data-range="24h">

            24 Hours

        </button>


        <button
        class="btn btn-outline-primary range-btn"
        data-range="7d">

            7 Days

        </button>


        <button
        class="btn btn-outline-primary range-btn"
        data-range="30d">

            30 Days

        </button>

    </div>

</div>

</div>

</div>



<!-- =========================================================
     DOWNLOAD STATISTICS
========================================================= -->

<div class="row g-3 mb-3">


<div class="col-md-3">

<div class="card shadow-sm stat-card">

<div class="card-body">

<div class="stat-title">

    Average Download

</div>

<div
id="avgDownload"
class="stat-value">

    0 Mbps

</div>

</div>

</div>

</div>



<div class="col-md-3">

<div class="card shadow-sm stat-card">

<div class="card-body">

<div class="stat-title">

    Maximum Download

</div>

<div
id="maxDownload"
class="stat-value">

    0 Mbps

</div>

<div
id="peakDownloadTime"
class="stat-time">

    -

</div>

</div>

</div>

</div>



<div class="col-md-3">

<div class="card shadow-sm stat-card">

<div class="card-body">

<div class="stat-title">

    Minimum Download

</div>

<div
id="minDownload"
class="stat-value">

    0 Mbps

</div>

</div>

</div>

</div>



<div class="col-md-3">

<div class="card shadow-sm stat-card">

<div class="card-body">

<div class="stat-title">

    Peak Download Time

</div>

<div
id="peakDownloadClock"
class="stat-value">

    --

</div>

</div>

</div>

</div>

</div>



<!-- =========================================================
     UPLOAD STATISTICS
========================================================= -->

<div class="row g-3 mb-4">


<div class="col-md-3">

<div class="card shadow-sm stat-card">

<div class="card-body">

<div class="stat-title">

    Average Upload

</div>

<div
id="avgUpload"
class="stat-value">

    0 Mbps

</div>

</div>

</div>

</div>



<div class="col-md-3">

<div class="card shadow-sm stat-card">

<div class="card-body">

<div class="stat-title">

    Maximum Upload

</div>

<div
id="maxUpload"
class="stat-value">

    0 Mbps

</div>

<div
id="peakUploadTime"
class="stat-time">

    -

</div>

</div>

</div>

</div>



<div class="col-md-3">

<div class="card shadow-sm stat-card">

<div class="card-body">

<div class="stat-title">

    Minimum Upload

</div>

<div
id="minUpload"
class="stat-value">

    0 Mbps

</div>

</div>

</div>

</div>



<div class="col-md-3">

<div class="card shadow-sm stat-card">

<div class="card-body">

<div class="stat-title">

    Peak Upload Time

</div>

<div
id="peakUploadClock"
class="stat-value">

    --

</div>

</div>

</div>

</div>

</div>



<!-- =========================================================
     GRAPH
========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-body">

<div class="d-flex
            justify-content-between
            align-items-center
            mb-3">

    <div>

        <h5 class="mb-1">

            Ether1 Traffic

        </h5>

        <small class="text-muted">

            Download & Upload

        </small>

    </div>


    <span
    id="sampleCount"
    class="badge text-bg-primary">

        0 samples

    </span>

</div>


<div class="chart-container">

    <canvas id="historyChart"></canvas>

</div>

</div>

</div>



<!-- =========================================================
     TABLE
========================================================= -->

<div class="card shadow-sm">

<div class="card-body">

<div class="d-flex
            justify-content-between
            align-items-center
            mb-3">

    <h5 class="mb-0">

        Latest Traffic Data

    </h5>

    <span class="text-muted">

        Latest 50 records

    </span>

</div>


<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Time</th>

<th>Download</th>

<th>Upload</th>

<th>RX Packet</th>

<th>TX Packet</th>

<th>CPU</th>

</tr>

</thead>


<tbody id="historyTable">

</tbody>

</table>

</div>

</div>

</div>


</div>



<script>


/*
|--------------------------------------------------------------------------
| CHART
|--------------------------------------------------------------------------
*/

const labels = [];

const downloadData = [];

const uploadData = [];


const chart =
new Chart(

    document.getElementById(
        "historyChart"
    ),

    {

        type: "line",

        data: {

            labels: labels,

            datasets: [

                {

                    label: "Download",

                    data: downloadData,

                    borderColor: "#0d6efd",

                    backgroundColor:
                    "rgba(13,110,253,0.10)",

                    borderWidth: 3,

                    fill: true,

                    tension: 0.35,

                    pointRadius: 0,

                    pointHoverRadius: 5

                },


                {

                    label: "Upload",

                    data: uploadData,

                    borderColor: "#20c997",

                    backgroundColor:
                    "rgba(32,201,151,0.10)",

                    borderWidth: 3,

                    fill: true,

                    tension: 0.35,

                    pointRadius: 0,

                    pointHoverRadius: 5

                }

            ]

        },


        options: {

            responsive: true,

            maintainAspectRatio: false,

            interaction: {

                mode: "index",

                intersect: false

            },

            plugins: {

                legend: {

                    position: "top"

                },

                tooltip: {

                    callbacks: {

                        label: function(context) {

                            return (

                                context.dataset.label
                                + ": "
                                + context.parsed.y.toFixed(2)
                                + " Mbps"

                            );

                        }

                    }

                }

            },


            scales: {

                x: {

                    grid: {

                        display: false

                    }

                },


                y: {

                    beginAtZero: true,

                    title: {

                        display: true,

                        text: "Mbps"

                    }

                }

            }

        }

    }

);



/*
|--------------------------------------------------------------------------
| FORMAT TIME
|--------------------------------------------------------------------------
*/

function formatTime(value)
{

    if(!value) {

        return "-";

    }


    const date =
    new Date(
        value.replace(" ", "T")
    );


    return date.toLocaleTimeString();

}



/*
|--------------------------------------------------------------------------
| LOAD HISTORY
|--------------------------------------------------------------------------
*/

async function loadHistory(range)
{

    try {

        const response =
        await fetch(
            "../api/history.php?range="
            + range
        );


        const result =
        await response.json();


        if(
            result.status !==
            "success"
        ) {

            throw new Error(
                "API Error"
            );

        }


        /*
        |--------------------------------------------------------------------------
        | STATISTICS
        |--------------------------------------------------------------------------
        */

        const stats =
        result.statistics;


        document
        .getElementById("avgDownload")
        .innerText =
        stats.avg_download
        + " Mbps";


        document
        .getElementById("maxDownload")
        .innerText =
        stats.max_download
        + " Mbps";


        document
        .getElementById("minDownload")
        .innerText =
        stats.min_download
        + " Mbps";


        document
        .getElementById("avgUpload")
        .innerText =
        stats.avg_upload
        + " Mbps";


        document
        .getElementById("maxUpload")
        .innerText =
        stats.max_upload
        + " Mbps";


        document
        .getElementById("minUpload")
        .innerText =
        stats.min_upload
        + " Mbps";



        /*
        |--------------------------------------------------------------------------
        | PEAK TIME
        |--------------------------------------------------------------------------
        */

        if(stats.peak_download) {

            document
            .getElementById(
                "peakDownloadTime"
            )
            .innerText =
            stats.peak_download.value
            + " Mbps";


            document
            .getElementById(
                "peakDownloadClock"
            )
            .innerText =
            formatTime(
                stats.peak_download.time
            );

        }


        if(stats.peak_upload) {

            document
            .getElementById(
                "peakUploadTime"
            )
            .innerText =
            stats.peak_upload.value
            + " Mbps";


            document
            .getElementById(
                "peakUploadClock"
            )
            .innerText =
            formatTime(
                stats.peak_upload.time
            );

        }



        /*
        |--------------------------------------------------------------------------
        | SAMPLE COUNT
        |--------------------------------------------------------------------------
        */

        document
        .getElementById(
            "sampleCount"
        )
        .innerText =
        result.count
        + " samples";



        /*
        |--------------------------------------------------------------------------
        | CHART
        |--------------------------------------------------------------------------
        */

        labels.length = 0;

        downloadData.length = 0;

        uploadData.length = 0;


        /*
        |--------------------------------------------------------------------------
        | BATASI DATA GRAFIK
        |--------------------------------------------------------------------------
        |
        | Maksimal 500 titik agar grafik tetap ringan.
        |
        */

        let chartRows =
        result.data;


        if(chartRows.length > 500) {

            const step =
            Math.ceil(
                chartRows.length / 500
            );


            chartRows =
            chartRows.filter(
                (row, index) =>
                index % step === 0
            );

        }


        chartRows.forEach(row => {

            labels.push(
                formatTime(row.time)
            );


            downloadData.push(
                Number(row.download)
            );


            uploadData.push(
                Number(row.upload)
            );

        });


        chart.update();



        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        const table =
        document.getElementById(
            "historyTable"
        );


        table.innerHTML = "";


        const tableRows =
        result.data
        .slice()
        .reverse()
        .slice(0, 50);


        tableRows.forEach(row => {


            const tr =
            document.createElement(
                "tr"
            );


            tr.innerHTML = `

                <td>
                    ${row.time}
                </td>

                <td>
                    <strong>
                        ${Number(row.download).toFixed(2)}
                    </strong>
                    Mbps
                </td>

                <td>
                    ${Number(row.upload).toFixed(2)}
                    Mbps
                </td>

                <td>
                    ${Number(row.rx_packet).toLocaleString()}
                </td>

                <td>
                    ${Number(row.tx_packet).toLocaleString()}
                </td>

                <td>
                    ${Number(row.cpu).toFixed(0)} %
                </td>

            `;


            table.appendChild(tr);

        });


        /*
        |--------------------------------------------------------------------------
        | EMPTY DATA
        |--------------------------------------------------------------------------
        */

        if(tableRows.length === 0) {

            table.innerHTML = `

                <tr>

                    <td
                    colspan="6"
                    class="text-center text-muted">

                        Belum ada data history.

                    </td>

                </tr>

            `;

        }

    }


    catch(error) {

        console.error(error);

        alert(
            "Gagal mengambil data history."
        );

    }

}



/*
|--------------------------------------------------------------------------
| RANGE BUTTON
|--------------------------------------------------------------------------
*/

document
.querySelectorAll(".range-btn")
.forEach(button => {


    button.addEventListener(
        "click",
        function() {


            document
            .querySelectorAll(".range-btn")
            .forEach(btn => {

                btn.classList.remove(
                    "active"
                );

            });


            this.classList.add(
                "active"
            );


            loadHistory(
                this.dataset.range
            );

        }
    );

});



/*
|--------------------------------------------------------------------------
| INITIAL
|--------------------------------------------------------------------------
*/

loadHistory("1h");


</script>


    <script src="../assets/js/app.js?v=1"></script>
</body>

</html>
