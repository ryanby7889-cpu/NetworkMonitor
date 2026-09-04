(() => {
  const cfg = window.TRAFFIC_HISTORY_CONFIG || {};
  let range = cfg.range || '24h';
  let routerId = Number(cfg.routerId || 0);
  let chart = null;
  const $ = id => document.getElementById(id);
  const fmt = n => Number(n || 0).toFixed(2);

  async function routers() {
    const r = await fetch('../api/routers.php?nocache=' + Date.now(), {cache:'no-store'});
    const d = await r.json();
    const sel = $('historyRouter');
    if (!d.success || !sel) return;
    sel.innerHTML = d.data.map(x => `<option value="${x.id}">${x.router_name} — ${x.ip_address}${Number(x.is_active)===1?' ★':''}</option>`).join('');
    if (!routerId) routerId = Number(d.active_id || d.data[0]?.id || 0);
    sel.value = String(routerId);
  }

  function render(data) {
    const labels = data.map(x => new Date(x.created_at.replace(' ','T')).toLocaleTimeString());
    const down = data.map(x => Number(x.download_mbps)||0);
    const up = data.map(x => Number(x.upload_mbps)||0);
    const ctx = $('trafficChart')?.getContext('2d');
    if (!ctx) return;
    if (chart) chart.destroy();
    chart = new Chart(ctx,{type:'line',data:{labels,datasets:[{label:'Download',data:down,borderWidth:2,fill:false,tension:.35},{label:'Upload',data:up,borderWidth:2,fill:false,tension:.35}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},scales:{y:{beginAtZero:true,title:{display:true,text:'Mbps'}}}}});
    const body=$('trafficTableBody');
    if(body) body.innerHTML=data.slice().reverse().map(x=>`<tr><td>${x.created_at}</td><td>${x.interface_name||'-'}</td><td>${fmt(x.download_mbps)} Mbps</td><td>${fmt(x.upload_mbps)} Mbps</td><td>${Number(x.rx_packet||0).toLocaleString()}</td><td>${Number(x.tx_packet||0).toLocaleString()}</td><td>${fmt(x.cpu)}%</td><td>${fmt(x.memory)}%</td><td>${fmt(x.disk)}%</td></tr>`).join('') || '<tr><td colspan="9" class="text-center py-5 text-muted">Tidak ada data untuk router/periode ini.</td></tr>';
  }

  async function load() {
    try {
      const r=await fetch(`../api/traffic_history.php?range=${encodeURIComponent(range)}&router_id=${routerId}&limit=500&nocache=${Date.now()}`,{cache:'no-store'});
      const d=await r.json(); if(!d.success) throw Error(d.message||'Gagal memuat histori');
      $('statRecords').textContent=Number(d.total||0).toLocaleString(); $('statMaxDownload').textContent=fmt(d.summary?.max_download)+' Mbps'; $('statMaxUpload').textContent=fmt(d.summary?.max_upload)+' Mbps'; $('statAvgDownload').textContent=fmt(d.summary?.avg_download)+' Mbps';
      $('chartPeriod').textContent=`${d.from} s/d ${d.to}`; $('rangeInfo').textContent=`Router ID ${d.router_id} • ${Number(d.total||0).toLocaleString()} records • ${range}`;
      render(d.data||[]);
      const ex=$('exportTraffic'); if(ex) ex.href=`export.php?range=${range}&router_id=${routerId}`;
    } catch(e){ console.error(e); }
  }

  async function init(){
    await routers();
    document.querySelectorAll('[data-range]').forEach(b=>b.addEventListener('click',()=>{range=b.dataset.range;document.querySelectorAll('[data-range]').forEach(x=>x.classList.remove('active'));b.classList.add('active');load();}));
    const sel=$('historyRouter'); if(sel) sel.addEventListener('change',()=>{routerId=Number(sel.value);load();});
    document.querySelector(`[data-range="${range}"]`)?.classList.add('active');
    load(); setInterval(load,10000);
  }
  init();
})();
