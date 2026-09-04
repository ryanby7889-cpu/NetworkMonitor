<?php $activeMenu='report'; ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Laporan Billing - NetMonitor</title>
<link rel="stylesheet" href="../assets/css/variables.css?v=8">
<link rel="stylesheet" href="../assets/css/common.css?v=8">

<style>
*{box-sizing:border-box}
body{margin:0;background:#f4f7fb;color:#07142e;font-family:Arial,Helvetica,sans-serif}
.report-sidebar{
 position:fixed;left:0;top:0;bottom:0;width:220px;background:#0d172b;color:#fff;
 padding:26px 14px;z-index:1000;overflow-y:auto
}
.report-sidebar .brand{display:flex;align-items:center;gap:10px;font-size:20px;font-weight:700;padding:0 10px 28px}
.report-sidebar .brand-icon{color:#087cff}
.report-sidebar .menu-title{color:#91a0ba;text-transform:uppercase;font-size:10px;letter-spacing:1px;margin:12px 10px 8px}
.report-sidebar .menu-item{display:flex;align-items:center;gap:10px;width:100%;height:44px;padding:0 12px;margin:2px 0;border-radius:9px;color:#fff;text-decoration:none;font-size:14px}
.report-sidebar .menu-item:hover{background:#17243a}
.report-sidebar .menu-item.active{background:#1d2a40;box-shadow:inset 3px 0 0 #087cff}
.report-main{margin-left:220px;min-height:100vh;padding:34px 30px 50px;max-width:1720px}
.header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:22px}
.title{margin:0;font-size:30px}.subtitle{margin-top:6px;color:#60708c;font-size:14px}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{border:0;border-radius:7px;padding:10px 15px;font-size:13px;font-weight:600;cursor:pointer}
.btn-primary{background:#087cff;color:#fff}.btn-secondary{background:#e8eef7;color:#17233a}
.card{background:#fff;border:1px solid #dbe3ef;border-radius:15px;padding:20px;margin-top:18px;box-shadow:0 2px 8px rgba(20,40,70,.04)}
.filter{display:flex;gap:12px;align-items:end;flex-wrap:wrap}
.field{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:600}
.field input,.search{height:40px;border:1px solid #b9c5d6;border-radius:7px;padding:0 11px;font:inherit}
.field input{min-width:170px}
.search{width:300px;max-width:100%}
.stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-top:18px}
.stat{background:#fff;border:1px solid #dbe3ef;border-radius:14px;padding:17px}
.stat small{display:block;color:#60708c;font-size:12px;margin-bottom:9px}
.stat strong{font-size:22px}
.table-wrap{overflow:auto;border:1px solid #e2e8f1;border-radius:8px}
table{width:100%;border-collapse:collapse;min-width:1000px;font-size:12px}
th{background:#f5f8fc;color:#53657f;text-align:left}
th,td{padding:10px;border-bottom:1px solid #e6ebf2;white-space:nowrap}
tbody tr:last-child td{border-bottom:0}
.badge{display:inline-block;padding:5px 8px;border-radius:999px;font-size:10px;font-weight:700}
.paid{background:#d9f1e6;color:#08774e}.unpaid{background:#fff0c7;color:#986b00}
.overdue{background:#ffe1e5;color:#c6283d}.cancelled{background:#e9edf3;color:#5b677a}
.section-head{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:14px}
.section-head h2{margin:0;font-size:18px}
.muted{color:#60708c;font-size:12px}
.notice{margin-top:12px;padding:10px 12px;background:#eef5ff;border-radius:8px;color:#365b89;font-size:12px}
@media(max-width:1100px){.stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:760px){
 .report-sidebar{position:relative;width:100%;height:auto;padding:10px}
 .report-sidebar .brand{padding:5px 10px 12px}
 .report-sidebar .menu-title{display:none}
 .report-sidebar .menu-item{display:inline-flex;width:auto;margin:2px;padding:0 10px}
 .report-main{margin-left:0;padding:22px 14px 40px}
 .header{flex-direction:column}.actions{width:100%}.actions .btn{flex:1}
 .stats{grid-template-columns:1fr 1fr}
}
@media(max-width:480px){.stats{grid-template-columns:1fr}.filter{align-items:stretch}.field,.field input{width:100%}.search{width:100%}}
@media print{
 .report-sidebar,.actions,.filter button,.search{display:none!important}
 .report-main{margin:0;padding:10px}
 .card,.stat{box-shadow:none}
 body{background:#fff}
}
</style>
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

<?php
$activeMenu = 'report';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="report-main">
 <div class="header">
  <div>
   <h1 class="title">Laporan Billing</h1>
   <div class="subtitle">Rekap tagihan, pembayaran, piutang, dan pendapatan PPPoE</div>
  </div>
  <div class="actions">
   <button class="btn btn-secondary" id="refreshBtn">↻ Refresh</button>
   <button class="btn btn-primary" id="printBtn">🖨 Cetak Laporan</button>
  </div>
 </div>

 <section class="card">
  <div class="filter">
   <label class="field">Tanggal Mulai
    <input type="date" id="start">
   </label>
   <label class="field">Tanggal Akhir
    <input type="date" id="end">
   </label>
   <button class="btn btn-primary" id="applyBtn">Tampilkan</button>
  </div>
  <div class="notice" id="periodText">Memuat periode...</div>
 </section>

 <section class="stats">
  <div class="stat"><small>Total Tagihan</small><strong id="totalBilled">Rp 0</strong></div>
  <div class="stat"><small>Sudah Dibayar</small><strong id="totalPaid">Rp 0</strong></div>
  <div class="stat"><small>Belum Dibayar</small><strong id="totalUnpaid">Rp 0</strong></div>
  <div class="stat"><small>Terlambat</small><strong id="totalOverdue">0</strong></div>
  <div class="stat"><small>Pendapatan Bersih</small><strong id="netIncome">Rp 0</strong></div>
 </section>

 <section class="card">
  <div class="section-head">
   <div><h2>Detail Tagihan</h2><div class="muted">Status terlambat dihitung berdasarkan tanggal jatuh tempo</div></div>
   <input class="search" id="search" placeholder="Cari invoice / pelanggan / PPPoE...">
  </div>
  <div class="table-wrap">
   <table>
    <thead><tr>
     <th>#</th><th>Invoice</th><th>Pelanggan</th><th>PPPoE</th>
     <th>Periode</th><th>Jatuh Tempo</th><th>Jumlah</th><th>Status</th>
     <th>Tanggal Bayar</th><th>Metode</th>
    </tr></thead>
    <tbody id="invoiceTable"><tr><td colspan="10">Memuat data...</td></tr></tbody>
   </table>
  </div>
 </section>

 <section class="card">
  <div class="section-head">
   <div><h2>Rekap Metode Pembayaran</h2><div class="muted">Hanya pembayaran yang berstatus LUNAS</div></div>
  </div>
  <div class="table-wrap">
   <table style="min-width:500px">
    <thead><tr><th>#</th><th>Metode</th><th>Transaksi</th><th>Total</th></tr></thead>
    <tbody id="methodTable"><tr><td colspan="4">Memuat data...</td></tr></tbody>
   </table>
  </div>
 </section>
</main>

<script>
const $=id=>document.getElementById(id);
let invoices=[];

function money(v){
 const n=Number(v||0);
 return 'Rp '+new Intl.NumberFormat('id-ID',{maximumFractionDigits:0}).format(n);
}
function esc(v){
 return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}
function badge(status){
 const map={
  paid:['LUNAS','paid'],
  unpaid:['BELUM BAYAR','unpaid'],
  overdue:['TERLAMBAT','overdue'],
  cancelled:['DIBATALKAN','cancelled']
 };
 const x=map[status]||[status,status];
 return `<span class="badge ${x[1]}">${x[0]}</span>`;
}
async function get(action,extra={}){
 const p=new URLSearchParams({action,...extra});
 const r=await fetch('../api/report.php?'+p.toString());
 const j=await r.json();
 if(!j.success) throw new Error(j.message||'Gagal mengambil data');
 return j;
}
function renderInvoices(){
 const q=$('search').value.trim().toLowerCase();
 const rows=invoices.filter(x=>[
  x.invoice_no,x.customer_name,x.pppoe_username,x.package_name
 ].join(' ').toLowerCase().includes(q));
 $('invoiceTable').innerHTML=rows.length?rows.map((x,i)=>`
  <tr>
   <td>${i+1}</td>
   <td><strong>${esc(x.invoice_no)}</strong></td>
   <td>${esc(x.customer_name)}</td>
   <td>${esc(x.pppoe_username)}</td>
   <td>${esc(x.period)}</td>
   <td>${esc(x.due_date)}</td>
   <td>${money(x.amount)}</td>
   <td>${badge(x.display_status)}</td>
   <td>${esc(x.paid_at||'-')}</td>
   <td>${esc(x.payment_method||'-')}</td>
  </tr>`).join('')
  :'<tr><td colspan="10">Tidak ada data pada periode ini.</td></tr>';
}
async function load(){
 const start=$('start').value,end=$('end').value;
 if(!start||!end)return;
 $('periodText').textContent=`Periode laporan: ${start} s/d ${end}`;
 try{
  const [s,i]=await Promise.all([
   get('summary',{start,end}),
   get('invoices',{start,end})
  ]);
  const x=s.summary;
  $('totalBilled').textContent=money(x.total_billed);
  $('totalPaid').textContent=money(x.total_paid);
  $('totalUnpaid').textContent=money(x.total_unpaid);
  $('totalOverdue').textContent=Number(x.overdue_count||0).toLocaleString('id-ID');
  $('netIncome').textContent=money(x.total_paid);
  invoices=i.invoices||[];
  renderInvoices();

  const methods=s.methods||[];
  $('methodTable').innerHTML=methods.length?methods.map((x,n)=>`
   <tr><td>${n+1}</td><td>${esc(x.payment_method)}</td><td>${Number(x.total||0).toLocaleString('id-ID')}</td><td>${money(x.amount)}</td></tr>
  `).join(''):'<tr><td colspan="4">Belum ada pembayaran.</td></tr>';
 }catch(e){
  $('invoiceTable').innerHTML=`<tr><td colspan="10">${esc(e.message)}</td></tr>`;
 }
}
function initDates(){
 const now=new Date();
 const y=now.getFullYear(),m=String(now.getMonth()+1).padStart(2,'0');
 const last=new Date(y,now.getMonth()+1,0).getDate();
 $('start').value=`${y}-${m}-01`;
 $('end').value=`${y}-${m}-${String(last).padStart(2,'0')}`;
}
$('applyBtn').onclick=load;
$('refreshBtn').onclick=load;
$('search').oninput=renderInvoices;
$('printBtn').onclick=()=>window.print();
initDates();
load();
</script>
</body>
</html>
