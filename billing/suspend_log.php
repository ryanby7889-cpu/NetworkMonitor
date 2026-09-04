<?php $activeMenu='billing'; ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Audit Suspend - NetMonitor</title>
<link rel="stylesheet" href="../assets/css/variables.css?v=10">
<link rel="stylesheet" href="../assets/css/common.css?v=10">
<link rel="stylesheet" href="../assets/css/theme.css?v=1">
<link rel="stylesheet" href="../assets/css/billing.css?v=21">
<link rel="stylesheet" href="../assets/css/billing-suspend-log.css?v=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<aside class="billing-sidebar">
  <div class="brand"><span class="brand-icon">◉</span><span>NetMonitor</span></div>
  <div class="menu-title">Monitoring</div>
  <a class="menu-item" href="../dashboard/">◉ <span>Dashboard</span></a>
  <a class="menu-item" href="../traffic/">⌁ <span>Traffic History</span></a>
  <div class="menu-title">System</div>
  <a class="menu-item" href="../router/">▣ <span>Router</span></a>
  <a class="menu-item" href="../pppoe/">◉ <span>PPPoE</span></a>
  <a class="menu-item active" href="../billing/">▣ <span>Billing</span></a>
  <a class="menu-item" href="../alarm/">△ <span>Alarm</span></a>
  <a class="menu-item" href="../settings/">⚙ <span>Settings</span></a>
</aside>

<main class="billing-page-shell">
<div class="billing-main suspend-log-page">
  <div class="suspend-log-header">
    <div>
      <h1>Audit Suspend PPPoE</h1>
      <p>Riwayat pemeriksaan dan perubahan status PPPoE dari sistem Billing.</p>
    </div>
    <div>
      <a class="billing-btn billing-btn-secondary" href="../billing/">
        <i class="bi bi-arrow-left"></i> Kembali
      </a>
      <button class="billing-btn billing-btn-primary" id="refreshLog">
        <i class="bi bi-arrow-clockwise"></i> Refresh
      </button>
    </div>
  </div>

  <section class="suspend-log-card">
    <div id="logMessage" class="billing-message" hidden></div>
    <div class="suspend-log-table-wrap">
      <table class="suspend-log-table">
        <thead>
          <tr>
            <th>Waktu</th><th>Mode</th><th>Action</th><th>Hasil</th>
            <th>Pelanggan</th><th>PPPoE</th><th>Invoice</th>
            <th>Terlambat</th><th>Toleransi</th><th>Keterangan</th>
          </tr>
        </thead>
        <tbody id="logBody">
          <tr><td colspan="10">Memuat...</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</div>
</main>

<script>
(async()=>{
 const $=id=>document.getElementById(id);
 const esc=v=>{const d=document.createElement('div');d.textContent=v??'-';return d.innerHTML};

 function badge(text, cls){
   return '<span class="log-badge '+cls+'">'+esc(text)+'</span>';
 }

 async function load(){
   $('logBody').innerHTML='<tr><td colspan="10">Memuat...</td></tr>';
   try{
     const r=await fetch('../api/billing_audit.php?limit=100&t='+Date.now(),{cache:'no-store'});
     const j=await r.json();
     if(!r.ok||!j.success) throw Error(j.message||'Gagal memuat audit log');

     const rows=j.logs||[];
     $('logBody').innerHTML=rows.length ? rows.map(x=>`
       <tr>
         <td>${esc(x.created_at)}</td>
         <td>${x.mode==='live'?badge('LIVE','log-live'):badge('TEST','log-test')}</td>
         <td>${esc(x.action)}</td>
         <td>${badge(x.result,x.result==='error'?'log-error':x.result==='success'?'log-success':'log-already')}</td>
         <td>${esc(x.customer_name)}</td>
         <td>${esc(x.pppoe_username)}</td>
         <td>${esc(x.invoice_no)}</td>
         <td>${x.overdue_days===null?'-':esc(x.overdue_days)+' hari'}</td>
         <td>${esc(x.grace_days)} hari</td>
         <td>${esc(x.message)}</td>
       </tr>`).join('') :
       '<tr><td colspan="10">Belum ada aktivitas suspend.</td></tr>';
   }catch(e){
     $('logBody').innerHTML='<tr><td colspan="10">'+esc(e.message)+'</td></tr>';
   }
 }
 $('refreshLog').onclick=load;
 load();
})();
</script>
    <script src="../assets/js/app.js?v=1"></script>
</body>
</html>
