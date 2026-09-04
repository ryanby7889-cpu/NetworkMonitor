<?php

$activeMenu = 'settings';

?>

<!doctype html>
<html lang="id">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>WhatsApp Billing - NetMonitor</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="../assets/css/variables.css"
>


<link
    rel="stylesheet"
    href="../assets/css/common.css"
>


<link
    rel="stylesheet"
    href="../assets/css/settings.css"
>

</head>


<body>


<?php require_once "../includes/sidebar.php"; ?>


<!-- ==========================================
     MAIN
========================================== -->

<div class="main">


    <!-- HEADER -->

    <div class="page-header">

        <div>

            <div class="page-title">

                WhatsApp Billing

            </div>


            <div class="page-subtitle">

                Pengaturan notifikasi WhatsApp pelanggan PPPoE

            </div>

        </div>


        <div>

            <button
                class="btn btn-secondary"
                id="reload"
            >

                <i class="bi bi-arrow-clockwise"></i>

                Refresh

            </button>

        </div>

    </div>


    <!-- MESSAGE -->

    <div
        id="msg"
        class="alert"
        style="display:none;"
    ></div>


    <!-- ==========================================
         NOTIFIKASI
    =========================================== -->

    <div class="settings-card">


        <h5 class="mb-2">

            <i class="bi bi-whatsapp"></i>

            Notifikasi

        </h5>


        <div class="setting-description mb-3">

            Aktifkan atau nonaktifkan event yang boleh
            masuk ke antrian WhatsApp.

        </div>


        <div id="events"></div>


    </div>


    <!-- ==========================================
         TEMPLATE PESAN
    =========================================== -->

    <div class="settings-card mt-3">


        <h5 class="mb-2">

            <i class="bi bi-chat-text"></i>

            Template Pesan

        </h5>


        <div class="setting-description mb-4">

            Pilih event untuk mengubah pesan.

            Variabel tersedia:

            <code>{name}</code>,

            <code>{invoice_no}</code>,

            <code>{period}</code>,

            <code>{package}</code>,

            <code>{amount}</code>,

            <code>{due_date}</code>,

            <code>{overdue_days}</code>,

            <code>{paid_at}</code>,

            <code>{payment_method}</code>.

        </div>


        <div class="row g-3">


            <!-- EVENT -->

            <div class="col-md-6">

                <label class="form-label">

                    Event

                </label>


                <select
                    id="eventSelect"
                    class="form-select"
                ></select>

            </div>


            <!-- NAMA TEMPLATE -->

            <div class="col-md-6">

                <label class="form-label">

                    Nama Template

                </label>


                <input
                    id="templateName"
                    class="form-control"
                >

            </div>


            <!-- PESAN -->

            <div class="col-12">

                <label class="form-label">

                    Isi Pesan

                </label>


                <textarea
                    id="templateText"
                    class="form-control"
                    rows="8"
                ></textarea>

            </div>


            <!-- SAVE -->

            <div class="col-12">

                <button
                    class="btn btn-primary"
                    id="saveTemplate"
                >

                    <i class="bi bi-save"></i>

                    Simpan Template

                </button>

            </div>


        </div>


    </div>


    <!-- ==========================================
         TEST WHATSAPP
    =========================================== -->

    <div class="settings-card mt-3">


        <h5 class="mb-2">

            <i class="bi bi-send"></i>

            Test WhatsApp

        </h5>


        <div class="setting-description mb-4">

            Test menggunakan invoice yang sudah ada.
            Sistem akan membuka WhatsApp dengan pesan
            hasil template.

        </div>


        <div class="row g-3">


            <div class="col-md-6">

                <label class="form-label">

                    ID Invoice

                </label>


                <input
                    id="invoiceId"
                    class="form-control"
                    type="number"
                    min="1"
                    placeholder="Contoh: 4"
                >

            </div>


            <div class="col-md-6">

                <label class="form-label">

                    Event

                </label>


                <select
                    id="testEvent"
                    class="form-select"
                ></select>

            </div>


            <div class="col-12">

                <button
                    class="btn btn-primary"
                    id="testWA"
                >

                    <i class="bi bi-whatsapp"></i>

                    Buka WhatsApp Test

                </button>

            </div>


        </div>


    </div>


    <!-- ==========================================
         LOG WHATSAPP
    =========================================== -->

    <div class="settings-card mt-3">


        <h5 class="mb-2">

            <i class="bi bi-clock-history"></i>

            Log WhatsApp

        </h5>


        <div class="setting-description mb-3">

            Riwayat pesan yang sudah disiapkan oleh sistem.

        </div>


        <div class="table-responsive">


            <table class="table table-sm">

                <thead>

                    <tr>

                        <th>Waktu</th>

                        <th>Pelanggan</th>

                        <th>Invoice</th>

                        <th>Event</th>

                        <th>No. WhatsApp</th>

                        <th>Status</th>

                        <th>Provider</th>

                    </tr>

                </thead>


                <tbody id="logs">

                    <tr>

                        <td colspan="7">

                            Memuat...

                        </td>

                    </tr>

                </tbody>

            </table>


        </div>


    </div>


</div>


<script>


const API = '../api/whatsapp.php';


const $ = id =>
    document.getElementById(id);


let templates = [];


const labels = {

    invoice:
        'Tagihan Baru',

    h3:
        'Pengingat H-3',

    h1:
        'Pengingat H-1',

    due:
        'Hari H',

    overdue:
        'Tagihan Terlambat',

    suspended:
        'Layanan Ditangguhkan',

    paid:
        'Pembayaran Diterima'

};


/* ==========================================
   MESSAGE
========================================== */

function showMessage(text, error = false)
{

    const msg = $('msg');


    msg.textContent = text;


    msg.className =
        'alert ' +
        (error
            ? 'alert-danger'
            : 'alert-success');


    msg.style.display =
        'block';

}


/* ==========================================
   GET API
========================================== */

async function get(url)
{

    const response =
        await fetch(
            url +
            '&t=' +
            Date.now(),
            {
                cache: 'no-store'
            }
        );


    const data =
        await response.json();


    if (
        !response.ok ||
        !data.success
    ) {

        throw new Error(
            data.message ||
            'Request gagal'
        );

    }


    return data;

}


/* ==========================================
   POST API
========================================== */

async function post(action, data)
{

    const response =
        await fetch(
            API +
            '?action=' +
            action,
            {
                method: 'POST',

                body:
                    new URLSearchParams(data)
            }
        );


    const result =
        await response.json();


    if (
        !response.ok ||
        !result.success
    ) {

        throw new Error(
            result.message ||
            'Request gagal'
        );

    }


    return result;

}


/* ==========================================
   SELECT EVENT
========================================== */

function fillSelects()
{

    $('eventSelect').innerHTML =

        templates
            .map(x => `

                <option value="${x.event_key}">

                    ${
                        labels[x.event_key]
                        ||
                        x.template_name
                    }

                </option>

            `)
            .join('');


    $('testEvent').innerHTML =
        $('eventSelect').innerHTML;


    renderEditor();

}


/* ==========================================
   EDITOR
========================================== */

function renderEditor()
{

    const selected =
        $('eventSelect').value;


    const template =
        templates.find(
            x =>
                x.event_key === selected
        )
        ||
        templates[0];


    if (!template) {

        return;

    }


    $('templateName').value =
        template.template_name
        ||
        '';


    $('templateText').value =
        template.message_template
        ||
        '';

}


/* ==========================================
   EVENTS
========================================== */

function renderEvents()
{

    $('events').innerHTML =

        templates
            .map(x => `

                <div
                    class="setting-row
                           d-flex
                           justify-content-between
                           align-items-center"
                >

                    <div>

                        <div
                            class="setting-label"
                        >

                            ${
                                labels[x.event_key]
                                ||
                                x.template_name
                            }

                        </div>


                        <div
                            class="setting-description"
                        >

                            ${x.event_key}

                        </div>

                    </div>


                    <div>

                        <label
                            class="form-check
                                   form-switch"
                        >

                            <input
                                class="form-check-input"
                                type="checkbox"
                                data-event="${x.event_key}"
                                ${
                                    Number(x.enabled)
                                    ? 'checked'
                                    : ''
                                }
                            >


                            <span class="ms-2">

                                Aktif

                            </span>

                        </label>

                    </div>

                </div>

            `)
            .join('');

}


/* ==========================================
   LOG
========================================== */

function renderLogs(rows)
{

    if (!rows.length) {

        $('logs').innerHTML = `

            <tr>

                <td colspan="7">

                    Belum ada log WhatsApp.

                </td>

            </tr>

        `;

        return;

    }


    $('logs').innerHTML =

        rows.map(x => `

            <tr>

                <td>
                    ${x.created_at || '-'}
                </td>

                <td>
                    ${x.customer_name || '-'}
                </td>

                <td>
                    ${x.invoice_no || '-'}
                </td>

                <td>
                    ${x.event_key || '-'}
                </td>

                <td>
                    ${x.phone || '-'}
                </td>

                <td>

                    <span
                        class="badge ${
                            x.status === 'failed'
                            ? 'bg-danger'
                            : 'bg-success'
                        }"
                    >

                        ${x.status || '-'}

                    </span>

                </td>

                <td>
                    ${x.provider || '-'}
                </td>

            </tr>

        `)
        .join('');

}


/* ==========================================
   LOAD
========================================== */

async function load()
{

    try {

        const data =
            await get(
                API +
                '?action=templates'
            );


        templates =
            data.templates || [];


        fillSelects();

        renderEvents();


        const logs =
            await get(
                API +
                '?action=logs&limit=100'
            );


        renderLogs(
            logs.logs || []
        );


        $('msg').style.display =
            'none';


    }
    catch (error) {

        showMessage(
            error.message,
            true
        );

    }

}


/* ==========================================
   EVENT CHANGE
========================================== */

$('eventSelect').onchange =
    renderEditor;


/* ==========================================
   REFRESH
========================================== */

$('reload').onclick =
    load;


/* ==========================================
   SAVE TEMPLATE
========================================== */

$('saveTemplate').onclick =
    async function()
{

    try {

        const event =
            $('eventSelect').value;


        await post(
            'save_template',
            {

                event_key:
                    event,

                template_name:
                    $('templateName').value,

                message_template:
                    $('templateText').value,

                enabled:
                    1

            }
        );


        showMessage(
            'Template berhasil disimpan.'
        );


        await load();

    }
    catch (error) {

        showMessage(
            error.message,
            true
        );

    }

};


/* ==========================================
   ENABLE / DISABLE EVENT
========================================== */

document.addEventListener(
    'change',
    async function(event)
    {

        if (
            !event.target.matches(
                '[data-event]'
            )
        ) {

            return;

        }


        const eventKey =
            event.target.dataset.event;


        const template =
            templates.find(
                x =>
                    x.event_key ===
                    eventKey
            );


        if (!template) {

            return;

        }


        try {

            await post(
                'save_template',
                {

                    event_key:
                        eventKey,

                    template_name:
                        template.template_name,

                    message_template:
                        template.message_template,

                    enabled:
                        event.target.checked
                        ? 1
                        : 0

                }
            );


            showMessage(
                'Status notifikasi diperbarui.'
            );

        }
        catch (error) {

            event.target.checked =
                !event.target.checked;


            showMessage(
                error.message,
                true
            );

        }

    }
);


/* ==========================================
   TEST WHATSAPP
========================================== */

$('testWA').onclick =
    async function()
{

    try {

        const invoiceId =
            Number(
                $('invoiceId').value
            );


        if (!invoiceId) {

            throw new Error(
                'Masukkan ID invoice.'
            );

        }


        const eventKey =
            $('testEvent').value;


        const data =
            await get(

                API +
                '?action=preview' +

                '&invoice_id=' +
                encodeURIComponent(
                    invoiceId
                ) +

                '&event_key=' +
                encodeURIComponent(
                    eventKey
                )

            );


        if (!data.url) {

            throw new Error(
                'Nomor WhatsApp pelanggan belum tersedia.'
            );

        }


        window.open(
            data.url,
            '_blank',
            'noopener'
        );


        showMessage(
            'WhatsApp test dibuka. ' +
            'Log test belum dianggap terkirim.'
        );

    }
    catch (error) {

        showMessage(
            error.message,
            true
        );

    }

};


/* ==========================================
   START
========================================== */

load();

</script>


    <script src="../assets/js/app.js?v=1"></script>
</body>

</html>