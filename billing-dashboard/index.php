<?php $activeMenu='billing-dashboard'; ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Billing - NetMonitor</title>
<link rel="stylesheet" href="../assets/css/variables.css?v=10">
<link rel="stylesheet" href="../assets/css/common.css?v=10">
<link rel="stylesheet" href="../assets/css/billing_dashboard.css?v=15">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
$activeMenu = 'billing-dashboard';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="bd-shell">
<main class="bd-main">

  <header class="bd-header">
    <div>
      <h1 class="bd-title">Dashboard Billing</h1>
      <div class="bd-subtitle">Ringkasan pendapatan, tagihan, pelanggan, piutang, dan jatuh tempo PPPoE</div>
    </div>
    <div class="bd-actions">
      <span id="liveIndicator" class="bd-live-indicator"><span class="bd-live-dot"></span> LIVE</span>
      <select id="refreshInterval" class="bd-refresh-select" title="Interval pembaruan">
        <option value="10000">10 detik</option>
        <option value="30000" selected>30 detik</option>
        <option value="60000">1 menit</option>
        <option value="300000">5 menit</option>
        <option value="0">Manual</option>
      </select>
      <input type="month" id="month" class="bd-month">
      <button id="refreshBtn" class="bd-btn bd-btn-secondary"><i class="bi bi-arrow-clockwise"></i>&nbsp; Refresh</button>
      <button id="printBtn" class="bd-btn bd-btn-secondary"><i class="bi bi-printer"></i>&nbsp; Cetak</button>
      <a href="../billing/" class="bd-btn bd-btn-secondary"><i class="bi bi-receipt"></i>&nbsp; Billing</a>
      <a href="../report/" class="bd-btn bd-btn-primary"><i class="bi bi-file-earmark-text"></i>&nbsp; Laporan</a>
    </div>
  </header>

  <div id="notice" class="bd-notice" hidden></div>

  <div id="detailFilter" class="bd-detail-filter" hidden>
    <div>
      <strong id="detailFilterTitle">Detail Billing</strong>
      <small id="detailFilterInfo">Filter dari Dashboard</small>
    </div>
    <button type="button" id="clearDetailFilter" class="bd-btn bd-btn-secondary bd-small-btn">
      <i class="bi bi-x-circle"></i>&nbsp; Hapus Filter
    </button>
  </div>
  <div id="liveMessage" class="bd-live-message">
    <i class="bi bi-broadcast"></i>
    <span>Dashboard diperbarui otomatis setiap 30 detik.</span>
    <small id="lastUpdated">Belum diperbarui</small>
  </div>

  <div class="bd-quick-actions">
    <a class="bd-quick-card" href="../billing/">
      <span class="bd-quick-icon"><i class="bi bi-receipt"></i></span>
      <span><strong>Kelola Tagihan</strong><small>Buat, bayar, dan kelola invoice</small></span>
      <i class="bi bi-chevron-right"></i>
    </a>
    <a class="bd-quick-card" href="../pppoe/">
      <span class="bd-quick-icon"><i class="bi bi-people"></i></span>
      <span><strong>Pelanggan PPPoE</strong><small>Kelola pelanggan dan status layanan</small></span>
      <i class="bi bi-chevron-right"></i>
    </a>
    <a class="bd-quick-card" href="../report/">
      <span class="bd-quick-icon"><i class="bi bi-file-earmark-bar-graph"></i></span>
      <span><strong>Laporan Billing</strong><small>Lihat laporan dan rekap pembayaran</small></span>
      <i class="bi bi-chevron-right"></i>
    </a>
  </div>

  <section class="bd-stats">
    <div class="bd-stat bd-stat-clickable" data-target="revenueSection">
      <div class="bd-stat-top"><div class="bd-stat-label">Total Tagihan</div><div class="bd-stat-icon"><i class="bi bi-receipt"></i></div></div>
      <div id="billed" class="bd-stat-value">Rp 0</div>
      <div id="invoiceCount" class="bd-stat-hint">0 invoice</div>
    </div>
    <div class="bd-stat bd-stat-clickable" data-target="revenueSection">
      <div class="bd-stat-top"><div class="bd-stat-label">Pendapatan Diterima</div><div class="bd-stat-icon"><i class="bi bi-cash-stack"></i></div></div>
      <div id="paid" class="bd-stat-value">Rp 0</div>
      <div id="paidCount" class="bd-stat-hint">0 lunas</div>
    </div>
    <div class="bd-stat bd-stat-clickable" data-target="arrearsSection">
      <div class="bd-stat-top"><div class="bd-stat-label">Piutang</div><div class="bd-stat-icon"><i class="bi bi-wallet2"></i></div></div>
      <div id="unpaid" class="bd-stat-value">Rp 0</div>
      <div id="unpaidHint" class="bd-stat-hint">0 belum bayar</div>
    </div>
    <div class="bd-stat bd-stat-clickable" data-target="upcomingSection">
      <div class="bd-stat-top"><div class="bd-stat-label">Invoice Terlambat</div><div class="bd-stat-icon"><i class="bi bi-clock-history"></i></div></div>
      <div id="overdue" class="bd-stat-value">0</div>
      <div class="bd-stat-hint">melewati jatuh tempo</div>
    </div>
    <div class="bd-stat bd-stat-clickable" data-target="customerSection">
      <div class="bd-stat-top"><div class="bd-stat-label">Pelanggan Aktif</div><div class="bd-stat-icon"><i class="bi bi-people"></i></div></div>
      <div id="active" class="bd-stat-value">0</div>
      <div id="customerHint" class="bd-stat-hint">0 total pelanggan</div>
    </div>
  </section>

  <section class="bd-card bd-action-panel" id="actionPanel">
    <div class="bd-card-header">
      <div>
        <h2><i class="bi bi-lightning-charge"></i> Perlu Tindakan</h2>
        <div class="bd-muted">Prioritas operasional yang perlu diperiksa admin</div>
      </div>
      <span id="actionCount" class="bd-action-count">0 tindakan</span>
    </div>
    <div id="actionItems" class="bd-action-items">
      <div class="bd-action-empty">Memuat status operasional...</div>
    </div>
  </section>

  <div class="bd-grid-main">
    <section id="revenueSection" class="bd-card">
      <div class="bd-card-header">
        <div>
          <h2>Grafik Pendapatan 12 Bulan</h2>
          <div class="bd-muted">Perbandingan tagihan, pembayaran diterima, dan piutang</div>
        </div>
      </div>
      <div class="bd-chart-lg"><canvas id="revenueChart"></canvas></div>
      <div class="bd-kpi-row">
        <div class="bd-kpi"><div class="bd-kpi-label">Rata-rata pendapatan diterima</div><div id="avgPaid" class="bd-kpi-value">Rp 0</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Bulan terbaik</div><div id="bestMonth" class="bd-kpi-value">-</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Collection rate</div><div id="collectionRate" class="bd-kpi-value">0%</div></div>
      </div>
    </section>

    <section id="statusSection" class="bd-card">
      <div class="bd-card-header">
        <div>
          <h2>Status Tagihan</h2>
          <div id="statusSubtitle" class="bd-muted">Komposisi invoice bulan terpilih</div>
          <button id="clearStatusFilter" class="bd-btn bd-btn-secondary bd-small-btn" type="button" hidden>Reset filter</button>
        </div>
      </div>
      <div class="bd-chart-md"><canvas id="statusChart"></canvas></div>
      <div id="statusList" class="bd-status-list"></div>
    </section>
  </div>

  <div class="bd-grid-bottom">
    <section id="customerSection" class="bd-card">
      <div class="bd-card-header">
        <div>
          <h2>Pelanggan Aktif & Ditangguhkan</h2>
          <div class="bd-muted">Status pelanggan saat ini</div>
        </div>
      </div>
      <div class="bd-chart-md"><canvas id="customerChart"></canvas></div>
    </section>

    <section id="upcomingSection" class="bd-card">
      <div class="bd-card-header">
        <div>
          <h2>Jatuh Tempo 7 Hari</h2>
          <div class="bd-muted">Invoice belum bayar yang segera jatuh tempo</div>
        </div>
      </div>
      <div class="bd-table-wrap">
        <table class="bd-table">
          <thead><tr><th>Invoice</th><th>Pelanggan</th><th>Jatuh Tempo</th><th>Jumlah</th><th>H-</th><th></th></tr></thead>
          <tbody id="upcoming"><tr><td colspan="5" class="bd-empty">Memuat data...</td></tr></tbody>
        </table>
      </div>
    </section>
  </div>

  <section id="arrearsSection" class="bd-card" style="margin-top:16px">
    <div class="bd-card-header">
      <div>
        <h2>Top Piutang Pelanggan</h2>
        <div class="bd-muted">10 pelanggan dengan tunggakan terbesar</div>
      </div>
    </div>
    <div class="bd-table-wrap">
      <table class="bd-table">
        <thead><tr><th>#</th><th>Pelanggan</th><th>PPPoE</th><th>Invoice</th><th>Piutang</th><th>Terlama</th><th></th></tr></thead>
        <tbody id="arrears"><tr><td colspan="6" class="bd-empty">Memuat data...</td></tr></tbody>
      </table>
    </div>
  </section>

  <section class="bd-card" style="margin-top:16px">
    <div class="bd-card-header">
      <div>
        <h2>Ringkasan Bulanan</h2>
        <div class="bd-muted">Rekap 12 bulan terakhir dari data invoice</div>
      </div>
    </div>
    <div class="bd-monthly-wrap">
      <table class="bd-monthly">
        <thead><tr><th>Bulan</th><th class="num">Invoice</th><th class="num">Tagihan</th><th class="num">Dibayar</th><th class="num">Piutang</th><th class="num">Collection</th></tr></thead>
        <tbody id="monthlySummary"><tr><td colspan="6" class="bd-empty">Memuat data...</td></tr></tbody>
      </table>
    </div>
  </section>

  <div class="bd-footer-note">Data dashboard dibaca langsung dari billing_invoices dan billing_customers.</div>

</main>
</div>

<script>
(() => {
  const API = '../api/billing_dashboard.php';
  const $ = id => document.getElementById(id);
  let revenueChart, statusChart, customerChart;

  const money = v => 'Rp ' + new Intl.NumberFormat('id-ID',{maximumFractionDigits:0}).format(Number(v||0));
  const number = v => Number(v||0).toLocaleString('id-ID');
  const esc = v => String(v ?? '').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  const monthLabel = s => {
    const [y,m]=String(s).split('-');
    return new Date(Number(y),Number(m)-1,1).toLocaleDateString('id-ID',{month:'short',year:'2-digit'});
  };

  function setNotice(text,error=false){
    const n=$('notice');
    n.textContent=text||'';
    n.hidden=!text;
    n.classList.toggle('error',error);
  }

  function defaultMonth(){
    const d=new Date();
    return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0');
  }

  function makeChartDefaults(){
    return {
      responsive:true,
      maintainAspectRatio:false,
      animation:{duration:250},
      interaction:{mode:'index',intersect:false},
      plugins:{
        legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:7,font:{size:11}}},
        tooltip:{callbacks:{label:c=>c.dataset.label+': '+money(c.raw)}}
      },
      scales:{
        x:{grid:{display:false},ticks:{font:{size:10},color:'#718198'}},
        y:{beginAtZero:true,grid:{color:'#e8edf4'},ticks:{font:{size:10},color:'#718198',callback:v=>money(v).replace('Rp ','')}}
      }
    };
  }



  function showDetailFilter(title, info){
    const box=$('detailFilter');
    $('detailFilterTitle').textContent=title;
    $('detailFilterInfo').textContent=info||'';
    box.hidden=false;
    box.scrollIntoView({behavior:'smooth',block:'start'});
  }

  function clearDetailFilter(){
    $('detailFilter').hidden=true;
  }

  function daysUntil(dateString){
    if(!dateString) return null;
    const d=new Date(dateString+'T00:00:00');
    const now=new Date();
    const today=new Date(now.getFullYear(),now.getMonth(),now.getDate());
    return Math.round((d-today)/86400000);
  }

  function renderActions(data){
    const s=data.summary||{}, c=data.customers||{};
    const actions=[];
    const overdue=Number(s.overdue_count||0);
    const waiting=Number(s.waiting_count||0);
    const suspended=Number(c.suspended||0);
    const upcoming=(data.upcoming||[]).length;
    const arrears=(data.arrears||[]).length;

    if(overdue>0){
      actions.push({
        type:'danger', icon:'bi-exclamation-octagon',
        title:`${number(overdue)} invoice terlambat`,
        text:'Periksa dan proses pembayaran atau suspend sesuai kebijakan.',
        target:'statusSection'
      });
    }
    if(waiting>0){
      actions.push({
        type:'warning', icon:'bi-clock-history',
        title:`${number(waiting)} invoice belum lunas`,
        text:'Masih ada tagihan yang belum menerima pembayaran.',
        target:'statusSection'
      });
    }
    if(upcoming>0){
      actions.push({
        type:'info', icon:'bi-calendar-event',
        title:`${number(upcoming)} jatuh tempo dalam 7 hari`,
        text:'Periksa pelanggan dan lakukan penagihan lebih awal.',
        target:'upcomingSection'
      });
    }
    if(arrears>0){
      actions.push({
        type:'danger', icon:'bi-wallet2',
        title:`${number(arrears)} pelanggan memiliki piutang`,
        text:'Lihat daftar piutang terbesar untuk prioritas penagihan.',
        target:'arrearsSection'
      });
    }
    if(suspended>0){
      actions.push({
        type:'warning', icon:'bi-person-slash',
        title:`${number(suspended)} pelanggan ditangguhkan`,
        text:'Periksa status pelanggan dan aktifkan kembali setelah pembayaran.',
        target:'customerSection'
      });
    }

    if(!actions.length){
      $('actionCount').textContent='0 tindakan';
      $('actionItems').innerHTML=`<div class="bd-action-empty success">
        <i class="bi bi-check-circle"></i>
        <div><strong>Tidak ada tindakan mendesak</strong><small>Status billing terlihat normal untuk periode terpilih.</small></div>
      </div>`;
      return;
    }

    $('actionCount').textContent=actions.length+' tindakan';
    $('actionItems').innerHTML=actions.map(a=>`
      <button type="button" class="bd-action-item ${a.type}" data-action-target="${a.target}">
        <span class="bd-action-icon"><i class="bi ${a.icon}"></i></span>
        <span class="bd-action-copy"><strong>${esc(a.title)}</strong><small>${esc(a.text)}</small></span>
        <i class="bi bi-chevron-right bd-action-arrow"></i>
      </button>
    `).join('');
  }

  function drawRevenue(rows){
    const labels=rows.map(r=>monthLabel(r.month));
    const billed=rows.map(r=>+r.billed);
    const paid=rows.map(r=>+r.paid);
    const unpaid=rows.map(r=>+r.unpaid);
    const ctx=$('revenueChart').getContext('2d');
    if(revenueChart) revenueChart.destroy();
    const opts=makeChartDefaults();
    revenueChart=new Chart(ctx,{
      type:'line',
      data:{labels,datasets:[
        {label:'Tagihan',data:billed,borderColor:'#087cff',backgroundColor:'rgba(8,124,255,.10)',fill:true,tension:.35,pointRadius:2,borderWidth:2},
        {label:'Dibayar',data:paid,borderColor:'#159c68',backgroundColor:'rgba(21,156,104,.08)',fill:true,tension:.35,pointRadius:2,borderWidth:2},
        {label:'Piutang',data:unpaid,borderColor:'#f2ad00',backgroundColor:'rgba(242,173,0,.05)',fill:false,tension:.35,pointRadius:2,borderWidth:2,borderDash:[5,4]}
      ]},
      options:opts
    });

    const totalPaid=paid.reduce((a,b)=>a+b,0);
    $('avgPaid').textContent=money(paid.length?totalPaid/paid.length:0);
    let bestIndex=0;
    billed.forEach((v,i)=>{if(v>billed[bestIndex])bestIndex=i});
    $('bestMonth').textContent=rows.length?monthLabel(rows[bestIndex].month):'-';
    const current=rows[rows.length-1]||{};
    const rate=+current.billed>0?(+current.paid/+current.billed*100):0;
    $('collectionRate').textContent=rate.toFixed(1)+'%';
  }

  function drawStatus(rows){
    const labelsMap={paid:'LUNAS',unpaid:'BELUM BAYAR',cancelled:'DIBATALKAN'};
    const data=rows.map(r=>+r.total);
    const labels=rows.map(r=>labelsMap[r.status]||String(r.status).toUpperCase());
    const ctx=$('statusChart').getContext('2d');
    if(statusChart) statusChart.destroy();
    statusChart=new Chart(ctx,{
      type:'doughnut',
      data:{labels,datasets:[{data,backgroundColor:['#159c68','#f2ad00','#8b97a8'],borderWidth:2,borderColor:'#fff'}]},
      options:{responsive:true,maintainAspectRatio:false,cutout:'68%',
        plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8,font:{size:10}}},
        tooltip:{callbacks:{label:c=>c.label+': '+number(c.raw)+' invoice'}}},
        onClick:(event,elements)=>{
          if(!elements.length) return;
          const idx=elements[0].index;
          const raw=rows[idx]?.status;
          if(raw) {
            highlightStatus(raw);
            const label=labelsMap[raw]||raw;
            showDetailFilter('Status: '+label, 'Gunakan tombol di bawah untuk membuka daftar invoice di Billing.');
            const box=$('detailFilter');
            let action=box.querySelector('[data-status-action]');
            if(!action){
              action=document.createElement('a');
              action.className='bd-btn bd-btn-primary';
              action.dataset.statusAction='1';
              box.appendChild(action);
            }
            action.href='../billing/?status='+encodeURIComponent(raw)+'&month='+encodeURIComponent($('month').value);
            action.innerHTML='<i class="bi bi-receipt"></i>&nbsp; Buka Invoice di Billing';
          }
        }
      }
    });

    const total=data.reduce((a,b)=>a+b,0);
    const colors={paid:'bd-green',unpaid:'bd-yellow',cancelled:'bd-gray'};
    $('statusList').innerHTML=rows.length?rows.map(r=>{
      const pct=total?(+r.total/total*100):0;
      return `<div class="bd-status-row" data-status="${esc(r.status)}">
        <div class="bd-status-label">${esc(labelsMap[r.status]||r.status)}</div>
        <div class="bd-progress"><span class="${colors[r.status]||'bd-blue'}" style="width:${pct}%"></span></div>
        <div>${number(r.total)}</div>
      </div>`;
    }).join(''):'<div class="bd-muted">Belum ada invoice pada bulan ini.</div>';
  }

  function drawCustomers(c){
    const ctx=$('customerChart').getContext('2d');
    if(customerChart) customerChart.destroy();
    customerChart=new Chart(ctx,{
      type:'bar',
      data:{labels:['Aktif','Ditangguhkan'],datasets:[{
        label:'Pelanggan',
        data:[+c.active||0,+c.suspended||0],
        backgroundColor:['#159c68','#e3344b'],
        borderRadius:7,
        barThickness:55
      }]},
      options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:false},tooltip:{callbacks:{label:x=>number(x.raw)+' pelanggan'}}},
        scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}}}}
    });
  }

  function drawUpcoming(rows){
    $('upcoming').innerHTML=rows.length?rows.map(x=>`<tr>
      <td><strong>${esc(x.invoice_no)}</strong></td>
      <td>${esc(x.customer_name)}<br><small>${esc(x.pppoe_username)}</small></td>
      <td>${esc(x.due_date)}</td>
      <td>${money(x.amount)}</td>
      <td><span class="bd-badge ${+x.days_left===0?'bd-badge-overdue':'bd-badge-unpaid'}">${+x.days_left===0?'HARI INI':'H-'+esc(x.days_left)}</span></td>
      <td><a class="bd-table-link" href="../billing/?invoice=${encodeURIComponent(x.id)}">Buka</a></td>
    </tr>`).join(''):'<tr><td colspan="6" class="bd-empty">Tidak ada tagihan yang jatuh tempo dalam 7 hari.</td></tr>';
  }

  function drawArrears(rows){
    $('arrears').innerHTML=rows.length?rows.map((x,i)=>`<tr>
      <td>${i+1}</td><td><strong>${esc(x.name)}</strong></td><td>${esc(x.pppoe_username)}</td>
      <td>${number(x.overdue_invoices)}</td><td>${money(x.arrears)}</td>
      <td>${number(x.oldest_days)} hari</td>
      <td><a class="bd-table-link" href="../billing/?customer=${encodeURIComponent(x.customer_id||'')}">Buka</a></td>
    </tr>`).join(''):'<tr><td colspan="7" class="bd-empty">Tidak ada piutang terlambat.</td></tr>';
  }

  function drawMonthly(rows){
    $('monthlySummary').innerHTML=rows.length?rows.map(r=>{
      const rate=+r.billed>0?(+r.paid/+r.billed*100):0;
      return `<tr><td><strong>${esc(monthLabel(r.month))}</strong></td>
        <td class="num">${number(r.invoice_count)}</td>
        <td class="num">${money(r.billed)}</td>
        <td class="num">${money(r.paid)}</td>
        <td class="num">${money(r.unpaid)}</td>
        <td class="num">${rate.toFixed(1)}%</td></tr>`;
    }).join(''):'<tr><td colspan="6" class="bd-empty">Belum ada data.</td></tr>';
  }

  async function load(){
    const month=$('month').value||defaultMonth();
    $('refreshBtn').disabled=true;
    try{
      const r=await fetch(API+'?month='+encodeURIComponent(month)+'&t='+Date.now(),{cache:'no-store'});
      const j=await r.json();
      if(!r.ok||!j.success) throw new Error(j.message||'Gagal memuat Dashboard Billing.');

      const s=j.summary||{},c=j.customers||{};
      $('billed').textContent=money(s.billed);
      $('paid').textContent=money(s.paid);
      $('unpaid').textContent=money(s.unpaid);
      $('overdue').textContent=number(s.overdue_count);
      $('active').textContent=number(c.active);
      $('invoiceCount').textContent=number(s.invoice_count)+' invoice';
      $('paidCount').textContent=number(s.paid_count)+' lunas';
      $('unpaidHint').textContent=number(s.waiting_count+s.overdue_count)+' belum bayar';
      $('customerHint').textContent=number(c.total)+' total pelanggan';
      $('statusSubtitle').textContent='Komposisi invoice '+monthLabel(j.month);

      renderActions(j);
      drawRevenue(j.trend||[]);
      drawStatus(j.statuses||[]);
      drawCustomers(c);
      drawUpcoming(j.upcoming||[]);
      drawArrears(j.arrears||[]);
      drawMonthly(j.trend||[]);
      setNotice('');
    }catch(e){
      setNotice(e.message,true);
    }finally{
      $('refreshBtn').disabled=false;
    }
  }


  // Sprint 1.6: interaksi dashboard

  document.addEventListener('click', e => {
    const action=e.target.closest('[data-action-target]');
    if(!action) return;
    const target=$(action.dataset.actionTarget);
    if(target) target.scrollIntoView({behavior:'smooth',block:'start'});
  });

  document.querySelectorAll('.bd-stat-clickable').forEach(card => {
    card.addEventListener('click', () => {
      const target = document.getElementById(card.dataset.target);
      if (target) target.scrollIntoView({behavior:'smooth', block:'start'});
      if(card.dataset.target==='arrearsSection') showDetailFilter('Piutang Pelanggan','Daftar pelanggan dengan tunggakan terbesar.');
      if(card.dataset.target==='upcomingSection') showDetailFilter('Jatuh Tempo','Invoice yang mendekati jatuh tempo.');
    });
  });

  const clearStatusFilter = $('clearStatusFilter');

  function highlightStatus(status){
    document.querySelectorAll('#statusList .bd-status-row').forEach(row => {
      row.classList.toggle('is-filtered', row.dataset.status === status);
    });
    clearStatusFilter.hidden = false;
  }

  clearStatusFilter.addEventListener('click', () => {
    document.querySelectorAll('#statusList .bd-status-row').forEach(row => row.classList.remove('is-filtered'));
    clearStatusFilter.hidden = true;
  });

  $('clearDetailFilter').addEventListener('click', clearDetailFilter);


  const printBtn = $('printBtn');
  if (printBtn) {
    printBtn.addEventListener('click', () => window.print());
  }

  $('month').value=defaultMonth();
  // Sprint 1.8: auto refresh
  let refreshTimer = null;
  const refreshInterval = $('refreshInterval');

  function setupAutoRefresh(){
    if (refreshTimer) {
      clearInterval(refreshTimer);
      refreshTimer = null;
    }
    const ms = Number(refreshInterval?.value || 0);
    if (ms > 0) {
      refreshTimer = setInterval(() => load(), ms);
    }
    if ($('liveMessage')) {
      $('liveMessage').querySelector('span').textContent =
        ms > 0 ? `Dashboard diperbarui otomatis setiap ${ms >= 60000 ? (ms/60000)+' menit' : (ms/1000)+' detik'}.`
               : 'Mode manual — dashboard hanya diperbarui saat Refresh ditekan.';
    }
  }

  refreshInterval?.addEventListener('change', setupAutoRefresh);

  setupAutoRefresh();
  $('month').addEventListener('change',load);
  $('refreshBtn').addEventListener('click',load);
  load();
  setInterval(load,30000);
})();
</script>
</body>
</html>
