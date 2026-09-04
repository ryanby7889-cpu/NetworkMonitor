<?php
$activeMenu = 'billing';
$customerId = $_GET['customer'] ?? '';
$invoiceId = $_GET['invoice'] ?? '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Detail Billing - NetMonitor</title>
<link rel="stylesheet" href="../assets/css/variables.css?v=10">
<link rel="stylesheet" href="../assets/css/common.css?v=10">
<link rel="stylesheet" href="../assets/css/billing.css?v=21">
<link rel="stylesheet" href="../assets/css/billing-detail.css?v=22">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
$activeMenu = 'billing';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="billing-page-shell"><main class="billing-main">
  <div class="billing-header">
    <div>
      <h1 class="billing-title">Detail Billing</h1>
      <div class="billing-subtitle">Detail pelanggan, invoice, pembayaran, dan status PPPoE</div>
    </div>
    <div class="billing-actions">
      <a href="./" class="billing-btn billing-btn-secondary"><i class="bi bi-arrow-left"></i>&nbsp; Kembali Billing</a>
      <a href="../billing-dashboard/" class="billing-btn billing-btn-primary"><i class="bi bi-bar-chart-line"></i>&nbsp; Dashboard</a>
    </div>
  </div>

  <div id="detailMessage" class="billing-message" hidden></div>

  <section class="detail-profile billing-card">
    <div class="detail-profile-icon"><i class="bi bi-person-circle"></i></div>
    <div class="detail-profile-main">
      <div class="detail-overline">PELANGGAN</div>
      <h2 id="customerName">Memuat...</h2>
      <div class="detail-meta">
        <span><i class="bi bi-person-badge"></i> <b id="pppoeUsername">-</b></span>
        <span><i class="bi bi-wifi"></i> <span id="packageName">-</span></span>
        <span><i class="bi bi-telephone"></i> <span id="phone">-</span></span>
      </div>
    </div>
    <div class="detail-profile-status">
      <div id="customerStatus" class="billing-badge badge-active">-</div>
      <small id="customerDue">Jatuh tempo: -</small>
    </div>
  </section>

  <section class="detail-kpis">
    <div class="billing-stat"><div class="billing-stat-title">Total Tagihan</div><div id="totalBilled" class="billing-stat-value">Rp 0</div></div>
    <div class="billing-stat"><div class="billing-stat-title">Total Dibayar</div><div id="totalPaid" class="billing-stat-value">Rp 0</div></div>
    <div class="billing-stat"><div class="billing-stat-title">Piutang</div><div id="totalArrears" class="billing-stat-value detail-danger">Rp 0</div></div>
    <div class="billing-stat"><div class="billing-stat-title">Invoice Terlambat</div><div id="overdueCount" class="billing-stat-value">0</div></div>
  </section>

  <section class="billing-card">
    <div class="billing-card-header">
      <div><h2>Data Pelanggan</h2><small>Informasi utama pelanggan billing.</small></div>
      <div class="billing-actions">
        <button id="editBtn" class="billing-btn billing-btn-primary"><i class="bi bi-pencil"></i>&nbsp; Edit</button>
        <button id="statusBtn" class="billing-btn billing-btn-warning">Ubah Status</button>
      </div>
    </div>
    <div class="detail-info-grid">
      <div><small>Nama</small><strong id="infoName">-</strong></div>
      <div><small>No. HP</small><strong id="infoPhone">-</strong></div>
      <div><small>Username PPPoE</small><strong id="infoPPPoE">-</strong></div>
      <div><small>Paket</small><strong id="infoPackage">-</strong></div>
      <div><small>Harga Bulanan</small><strong id="infoPrice">-</strong></div>
      <div><small>Tanggal Jatuh Tempo</small><strong id="infoBillingDay">-</strong></div>
      <div class="detail-full"><small>Alamat</small><strong id="infoAddress">-</strong></div>
      <div class="detail-full"><small>Catatan</small><strong id="infoNotes">-</strong></div>
    </div>
  </section>

  <section class="billing-card" id="invoiceSection">
    <div class="billing-card-header">
      <div><h2>Daftar Invoice</h2><small>Seluruh invoice pelanggan ini.</small></div>
      <button id="paySelectedBtn" class="billing-btn billing-btn-success" hidden><i class="bi bi-check2-circle"></i>&nbsp; Bayar Invoice Terpilih</button>
    </div>
    <div class="billing-table-wrap">
      <table class="billing-table">
        <thead><tr><th>#</th><th>Invoice</th><th>Periode</th><th>Jatuh Tempo</th><th>Jumlah</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody id="invoiceTable"><tr><td colspan="7">Memuat...</td></tr></tbody>
      </table>
    </div>
  </section>

  <section class="billing-card">
    <div class="billing-card-header">
      <div><h2>Riwayat Pembayaran</h2><small>Pembayaran yang tercatat untuk pelanggan ini.</small></div>
    </div>
    <div class="billing-table-wrap">
      <table class="billing-table">
        <thead><tr><th>#</th><th>Invoice</th><th>Periode</th><th>Tanggal Bayar</th><th>Jumlah</th><th>Metode</th><th>Kwitansi</th></tr></thead>
        <tbody id="paymentTable"><tr><td colspan="7">Memuat...</td></tr></tbody>
      </table>
    </div>
  </section>
</main></div>

<script>
(() => {
  const API='../api/billing.php';
  const idFromUrl=new URLSearchParams(location.search);
  const customerId=idFromUrl.get('customer')||'';
  const invoiceId=idFromUrl.get('invoice')||'';
  const $=id=>document.getElementById(id);
  const esc=v=>{const d=document.createElement('div');d.textContent=v??'-';return d.innerHTML};
  const money=v=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v)||0);

  let customer=null, invoices=[];

  async function request(action,data=null){
    const opt=data?{method:'POST',body:new URLSearchParams(data)}:{};
    const r=await fetch(API+'?action='+encodeURIComponent(action)+'&t='+Date.now(),opt);
    const text=await r.text(); let j;
    try{j=JSON.parse(text)}catch{throw Error('API Billing mengembalikan response yang tidak valid.')}
    if(!r.ok||!j.success)throw Error(j.message||'Request gagal.');
    return j;
  }
  function msg(t,error=false){$('detailMessage').textContent=t;$('detailMessage').hidden=!t;$('detailMessage').classList.toggle('error',error)}
  function invStatus(x){
    if(x.status==='unpaid'&&x.due_date&&x.due_date<new Date().toISOString().slice(0,10))return'overdue';
    return x.display_status||x.status||'';
  }
  function badge(s){
    const m={paid:['LUNAS','badge-paid'],unpaid:['BELUM BAYAR','badge-unpaid'],overdue:['TERLAMBAT','badge-overdue'],cancelled:['DIBATALKAN','badge-cancelled']};
    const x=m[s]||[String(s).toUpperCase(),'badge-unpaid'];
    return `<span class="billing-badge ${x[1]}">${x[0]}</span>`;
  }

  async function load(){
    try{
      const [c,i]=await Promise.all([
        request('customers'),
        request('invoices')
      ]);
      const allC=c.customers||[];
      const allI=i.invoices||[];

      if(customerId) customer=allC.find(x=>String(x.id)===String(customerId));
      if(!customer && invoiceId){
        const inv=allI.find(x=>String(x.id)===String(invoiceId));
        if(inv) customer=allC.find(x=>String(x.id)===String(inv.customer_id));
      }
      if(!customer)throw Error('Pelanggan tidak ditemukan.');

      invoices=allI.filter(x=>String(x.customer_id)===String(customer.id));
      render();
    }catch(e){msg(e.message,true)}
  }

  function render(){
    $('customerName').textContent=customer.name||'-';
    $('pppoeUsername').textContent=customer.pppoe_username||'-';
    $('packageName').textContent=customer.package_name||'-';
    $('phone').textContent=customer.phone||'-';
    $('customerStatus').textContent=customer.status==='active'?'AKTIF':'DITANGGUHKAN';
    $('customerStatus').className='billing-badge '+(customer.status==='active'?'badge-active':'badge-suspended');
    $('customerDue').textContent='Jatuh tempo: tanggal '+(customer.billing_day||'-');

    $('infoName').textContent=customer.name||'-';
    $('infoPhone').textContent=customer.phone||'-';
    $('infoPPPoE').textContent=customer.pppoe_username||'-';
    $('infoPackage').textContent=customer.package_name||'-';
    $('infoPrice').textContent=money(customer.monthly_price);
    $('infoBillingDay').textContent='Tanggal '+(customer.billing_day||'-')+' setiap bulan';
    $('infoAddress').textContent=customer.address||'-';
    $('infoNotes').textContent=customer.notes||'-';

    const billed=invoices.filter(x=>x.status!=='cancelled').reduce((s,x)=>s+(Number(x.amount)||0),0);
    const paid=invoices.filter(x=>x.status==='paid').reduce((s,x)=>s+(Number(x.amount)||0),0);
    const overdue=invoices.filter(x=>invStatus(x)==='overdue');
    $('totalBilled').textContent=money(billed);
    $('totalPaid').textContent=money(paid);
    $('totalArrears').textContent=money(Math.max(0,billed-paid));
    $('overdueCount').textContent=overdue.length;

    const rows=invoices.slice().sort((a,b)=>String(b.period).localeCompare(String(a.period)));
    $('invoiceTable').innerHTML=rows.length?rows.map((x,n)=>`<tr class="${String(x.id)===String(invoiceId)?'smart-detail-selected':''}">
      <td>${n+1}</td><td><strong>${esc(x.invoice_no)}</strong></td><td>${esc(x.period)}</td>
      <td>${esc(x.due_date)}</td><td>${money(x.amount)}</td><td>${badge(invStatus(x))}</td>
      <td><a class="billing-btn billing-btn-secondary billing-btn-small" href="invoice.php?invoice=${encodeURIComponent(x.id)}">Detail</a>
      ${x.status==='unpaid'?`<button class="billing-btn billing-btn-success billing-btn-small" data-pay="${esc(x.id)}">Bayar</button>`:'-'}</td>
    </tr>`).join(''):'<tr><td colspan="7">Belum ada invoice.</td></tr>';

    const payments=invoices.filter(x=>x.status==='paid').sort((a,b)=>String(b.paid_at).localeCompare(String(a.paid_at)));
    $('paymentTable').innerHTML=payments.length?payments.map((x,n)=>`<tr>
      <td>${n+1}</td><td>${esc(x.invoice_no)}</td><td>${esc(x.period)}</td><td>${esc(x.paid_at||'-')}</td>
      <td>${money(x.amount)}</td><td>${esc(x.payment_method||'-')}</td>
      <td><button class="billing-btn billing-btn-primary billing-btn-small" data-receipt="${esc(x.id)}">Kwitansi</button></td>
    </tr>`).join(''):'<tr><td colspan="7">Belum ada pembayaran.</td></tr>';

    $('statusBtn').textContent=customer.status==='active'?'Suspend Pelanggan':'Aktifkan Pelanggan';
    $('statusBtn').className='billing-btn '+(customer.status==='active'?'billing-btn-warning':'billing-btn-success');
  }

  async function changeStatus(){
    try{
      const suspend=customer.status==='active';
      if(!confirm((suspend?'Suspend ':'Aktifkan ')+customer.name+'?'))return;
      const r=await fetch('../api/pppoe.php?action=secrets&t='+Date.now());
      const d=await r.json();
      if(!d.success)throw Error(d.message||'Gagal membaca PPPoE.');
      const sec=(d.secrets||[]).find(x=>String(x.name)===String(customer.pppoe_username));
      if(!sec)throw Error('Username PPPoE tidak ditemukan di MikroTik.');
      const rr=await fetch('../api/pppoe.php?action='+(suspend?'disable_secret':'enable_secret'),{method:'POST',body:new URLSearchParams({id:sec.id})});
      const dd=await rr.json();
      if(!rr.ok||!dd.success)throw Error(dd.message||'Gagal mengubah PPPoE.');
      await request('set_customer_status',{id:customer.id,status:suspend?'suspended':'active'});
      customer.status=suspend?'suspended':'active';
      render(); msg(suspend?'Pelanggan ditangguhkan.':'Pelanggan diaktifkan kembali.');
    }catch(e){msg(e.message,true)}
  }

  $('statusBtn').onclick=changeStatus;
  $('editBtn').onclick=()=>location.href='./?customer='+encodeURIComponent(customer.id);

  document.addEventListener('click',async e=>{
    const pay=e.target.closest('[data-pay]');
    const rec=e.target.closest('[data-receipt]');
    if(pay){
      if(!confirm('Catat invoice ini sebagai LUNAS?'))return;
      try{await request('pay_invoice',{id:pay.dataset.pay,payment_method:'Cash'});await load();msg('Pembayaran berhasil dicatat.')}
      catch(x){msg(x.message,true)}
    }
    if(rec)window.open('receipt.php?id='+encodeURIComponent(rec.dataset.receipt),'_blank','noopener');
  });

  load();
})();
</script>
</body></html>
