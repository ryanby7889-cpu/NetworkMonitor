<?php
// Kompatibel dengan link lama (?invoice=) dan link modul Billing PRO (?id=).
$invoiceId = $_GET['id'] ?? ($_GET['invoice'] ?? '');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Detail Invoice - NetMonitor</title>
<link rel="stylesheet" href="../assets/css/variables.css?v=10">
<link rel="stylesheet" href="../assets/css/common.css?v=10">
<link rel="stylesheet" href="../assets/css/theme.css?v=1">
<link rel="stylesheet" href="../assets/css/billing.css?v=21">
<link rel="stylesheet" href="../assets/css/invoice-detail.css?v=23">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<div class="invoice-page">
  <div class="invoice-topbar">
    <div><h1><i class="bi bi-receipt"></i> Detail Invoice</h1><small>NetMonitor Billing</small></div>
    <div class="billing-actions"><a href="./" class="billing-btn billing-btn-secondary">Billing</a><a href="../billing-dashboard/" class="billing-btn billing-btn-primary">Dashboard</a><button id="printBtn" class="billing-btn billing-btn-secondary"><i class="bi bi-printer"></i> Cetak</button></div>
  </div>
  <div id="message" class="billing-message" hidden></div>
  <section class="invoice-summary"><div><span class="invoice-label">NO. INVOICE</span><strong id="invoiceNo">-</strong></div><div><span class="invoice-label">STATUS</span><span id="statusBadge" class="billing-badge badge-unpaid">-</span></div><div><span class="invoice-label">PERIODE</span><strong id="period">-</strong></div><div><span class="invoice-label">JATUH TEMPO</span><strong id="dueDate">-</strong></div></section>
  <section class="invoice-grid"><div class="billing-card"><div class="billing-card-header"><div><h2>Pelanggan</h2><small>Data pelanggan terkait invoice.</small></div></div><div class="invoice-info"><div><small>Nama</small><strong id="customerName">-</strong></div><div><small>PPPoE</small><strong id="pppoe">-</strong></div><div><small>Paket</small><strong id="package">-</strong></div><div><small>No. HP</small><strong id="phone">-</strong></div></div><a id="customerLink" class="billing-btn billing-btn-secondary" href="./">Buka Detail Pelanggan</a></div><div class="billing-card invoice-amount-card"><div class="billing-card-header"><div><h2>Total Invoice</h2><small>Jumlah yang harus dibayar.</small></div></div><div id="amount" class="invoice-amount">Rp 0</div><div id="paymentInfo" class="invoice-payment-info">Belum ada pembayaran.</div><div class="billing-actions invoice-actions"><button id="payBtn" class="billing-btn billing-btn-success"><i class="bi bi-check2-circle"></i> Tandai Lunas</button><button id="cancelBtn" class="billing-btn billing-btn-danger"><i class="bi bi-x-circle"></i> Batalkan</button></div></div></section>
  <section class="billing-card"><div class="billing-card-header"><div><h2>Informasi Tagihan</h2></div></div><div class="invoice-info invoice-info-wide"><div><small>Dibuat</small><strong id="createdAt">-</strong></div><div><small>Tanggal Bayar</small><strong id="paidAt">-</strong></div><div><small>Metode Pembayaran</small><strong id="method">-</strong></div><div><small>Catatan</small><strong id="notes">-</strong></div></div></section>
</div>
<script>
(()=>{
 const id=<?=json_encode((string)$invoiceId,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
 const API='../api/billing.php', $=x=>document.getElementById(x);
 const money=v=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v)||0);
 let invoice=null;
 async function req(action,data=null){const r=await fetch(API+'?action='+encodeURIComponent(action)+'&t='+Date.now(),data?{method:'POST',body:new URLSearchParams(data),cache:'no-store'}:{cache:'no-store'});const t=await r.text();let j;try{j=JSON.parse(t)}catch{throw Error('Response API tidak valid.')};if(!r.ok||!j.success)throw Error(j.message||'Request gagal.');return j}
 function msg(t,error=false){$('message').textContent=t;$('message').hidden=!t;$('message').classList.toggle('error',error)}
 function currentStatus(x){if(x.status==='unpaid'&&x.due_date&&x.due_date<new Date().toISOString().slice(0,10))return'overdue';return x.display_status||x.status||''}
 function badge(s){const m={paid:['LUNAS','badge-paid'],unpaid:['BELUM BAYAR','badge-unpaid'],overdue:['TERLAMBAT','badge-overdue'],cancelled:['DIBATALKAN','badge-cancelled']},x=m[s]||[String(s).toUpperCase(),'badge-unpaid'];return `<span class="billing-badge ${x[1]}">${x[0]}</span>`}
 async function load(){try{if(!id)throw Error('ID invoice tidak diberikan.');const d=await req('invoices');invoice=(d.invoices||[]).find(x=>String(x.id)===String(id)||String(x.invoice_no)===String(id));if(!invoice)throw Error('Invoice tidak ditemukan.');let customer=null;try{const c=await req('customers');customer=(c.customers||[]).find(x=>String(x.id)===String(invoice.customer_id))}catch(_){}render(customer)}catch(e){msg(e.message,true)}}
 function render(c){const s=currentStatus(invoice);$('invoiceNo').textContent=invoice.invoice_no||'-';$('statusBadge').outerHTML=badge(s).replace('billing-badge','billing-badge" id="statusBadge');$('period').textContent=invoice.period||'-';$('dueDate').textContent=invoice.due_date||'-';$('amount').textContent=money(invoice.amount);$('paymentInfo').textContent=invoice.status==='paid'?'Pembayaran sudah tercatat.':'Belum ada pembayaran.';$('customerName').textContent=invoice.customer_name||c?.name||'-';$('pppoe').textContent=invoice.pppoe_username||c?.pppoe_username||'-';$('package').textContent=invoice.package_name||c?.package_name||'-';$('phone').textContent=invoice.phone||c?.phone||'-';$('createdAt').textContent=invoice.created_at||'-';$('paidAt').textContent=invoice.paid_at||'-';$('method').textContent=invoice.payment_method||'-';$('notes').textContent=invoice.notes||'-';$('customerLink').href='customer_detail_pro.php?id='+encodeURIComponent(invoice.customer_id||'');$('payBtn').hidden=invoice.status!=='unpaid';$('cancelBtn').hidden=invoice.status!=='unpaid'}
 $('payBtn').onclick=async()=>{if(!confirm('Tandai invoice sebagai LUNAS?'))return;try{await req('pay_invoice',{id:invoice.id,payment_method:'Cash'});await load();msg('Pembayaran berhasil dicatat.')}catch(e){msg(e.message,true)}};
 $('cancelBtn').onclick=async()=>{if(!confirm('Batalkan invoice ini?'))return;try{await req('cancel_invoice',{id:invoice.id});await load();msg('Invoice berhasil dibatalkan.')}catch(e){msg(e.message,true)}};
 $('printBtn').onclick=()=>window.print();load();
})();
</script><script src="../assets/js/app.js?v=1"></script>
</body></html>
