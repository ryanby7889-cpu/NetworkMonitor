(() => {
  const cfg = window.TRAFFIC_HISTORY_CONFIG || {};
  let range = cfg.range || '24h';
  let routerId = Number(cfg.routerId || 0);
  let interfaceName = 'all';
  let chart = null;
  const $ = id => document.getElementById(id);
  const fmt = n => Number(n || 0).toFixed(2);
  const escapeHtml = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

  async function routers() {
    const r = await fetch('../api/routers.php?nocache=' + Date.now(), {cache:'no-store'});
    const d = await r.json();
    const sel = $('historyRouter');
    if (!d.success || !sel) return;
    sel.innerHTML = d.data.map(x => `<option value="${Number(x.id)}">${escapeHtml(x.router_name)} — ${escapeHtml(x.ip_address)}${Number(x.is_active)===1?' ★':''}</option>`).join('');
    if (!routerId) routerId = Number(d.active_id || d.data[0]?.id || 0);
    sel.value = String(routerId);
  }

  function ensureInterfaceOptions(data) {
    const sel = $('historyInterface');
    if (!sel) return;
    const names = [...new Set(data.map(x => x.interface_name).filter(Boolean))].sort();
    const current = interfaceName;
    sel.innerHTML = '<option value="all">Semua Interface</option>' + names.map(x => `<option value="${escapeHtml(x)}">${escapeHtml(x)}</option>`).join('');
    sel.value = names.includes(current) ? current : 'all';
    interfaceName = sel.value;
  }

  function aggregate(data) {
    if (range !== '7d') return data;
    const buckets = new Map();
    data.forEach(x => {
      const d = new Date(String(x.created_at).replace(' ','T'));
      if (Number.isNaN(d.getTime())) return;
      d.setMinutes(Math.floor(d.getMinutes() / 30) * 30, 0, 0);
      const key = d.toISOString();
      const b = buckets.get(key) || {created_at:d, download:0, upload:0, rx:0, tx:0, cpu:0, memory:0, disk:0, count:0, interface_name:interfaceName};
      b.download += Number(x.download_mbps)||0; b.upload += Number(x.upload_mbps)||0; b.rx += Number(x.rx_packet)||0; b.tx += Number(x.tx_packet)||0;
      b.cpu += Number(x.cpu)||0; b.memory += Number(x.memory)||0; b.disk += Number(x.disk)||0; b.count++;
      buckets.set(key,b);
    });
    return [...buckets.values()].sort((a,b)=>a.created_at-b.created_at).map(b => ({
      created_at:b.created_at.toLocaleString('sv-SE').replace('T',' '), interface_name:b.interface_name,
      download_mbps:b.count?b.download/b.count:0, upload_mbps:b.count?b.upload/b.count:0,
      rx_packet:b.rx, tx_packet:b.tx, cpu:b.count?b.cpu/b.count:0, memory:b.count?b.memory/b.count:0, disk:b.count?b.disk/b.count:0
    }));
  }

  function render(data) {
    ensureInterfaceOptions(data);
    const filtered = interfaceName === 'all' ? data : data.filter(x => x.interface_name === interfaceName);
    const chartData = aggregate(filtered);
    const labels = chartData.map(x => new Date(String(x.created_at).replace(' ','T')).toLocaleString('id-ID',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}));
    const down = chartData.map(x => Number(x.download_mbps)||0);
    const up = chartData.map(x => Number(x.upload_mbps)||0);
    const ctx = $('trafficChart')?.getContext('2d');
    if (!ctx) return;
    if (chart) chart.destroy();
    chart = new Chart(ctx,{type:'line',data:{labels,datasets:[{label:'Download',data:down,borderWidth:2,pointRadius:0,fill:false,tension:.3},{label:'Upload',data:up,borderWidth:2,pointRadius:0,fill:false,tension:.3}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{tooltip:{callbacks:{label:c=>`${c.dataset.label}: ${fmt(c.parsed.y)} Mbps`}}},scales:{x:{ticks:{maxTicksLimit:12,maxRotation:0}},y:{beginAtZero:true,title:{display:true,text:'Mbps'}}}}});
    const maxDown = filtered.reduce((m,x)=>Math.max(m,Number(x.download_mbps)||0),0);
    const maxUp = filtered.reduce((m,x)=>Math.max(m,Number(x.upload_mbps)||0),0);
    const avgDown = filtered.length ? filtered.reduce((s,x)=>s+(Number(x.download_mbps)||0),0)/filtered.length : 0;
    $('statRecords').textContent=filtered.length.toLocaleString();
    $('statMaxDownload').textContent=fmt(maxDown)+' Mbps';
    $('statMaxUpload').textContent=fmt(maxUp)+' Mbps';
    $('statAvgDownload').textContent=fmt(avgDown)+' Mbps';
    const body=$('trafficTableBody');
    if(body) body.innerHTML=filtered.slice().reverse().map(x=>`<tr><td>${escapeHtml(x.created_at)}</td><td>${escapeHtml(x.interface_name||'-')}</td><td>${fmt(x.download_mbps)} Mbps</td><td>${fmt(x.upload_mbps)} Mbps</td><td>${Number(x.rx_packet||0).toLocaleString()}</td><td>${Number(x.tx_packet||0).toLocaleString()}</td><td>${fmt(x.cpu)}%</td><td>${fmt(x.memory)}%</td><td>${fmt(x.disk)}%</td></tr>`).join('') || '<tr><td colspan="9" class="text-center py-5 text-muted">Tidak ada data untuk interface/periode ini.</td></tr>';
    $('rangeInfo').textContent=`Router ID ${routerId} • Interface ${interfaceName === 'all' ? 'Semua' : interfaceName} • ${filtered.length.toLocaleString()} records • ${range}${range==='7d'?' • grafik diagregasi 30 menit':''}`;
  }

  async function load() {
    try {
      const r=await fetch(`../api/traffic_history.php?range=${encodeURIComponent(range)}&router_id=${routerId}&limit=500&nocache=${Date.now()}`,{cache:'no-store'});
      const d=await r.json(); if(!d.success) throw Error(d.message||'Gagal memuat histori');
      $('chartPeriod').textContent=`${d.from} s/d ${d.to}`;
      render(d.data||[]);
      const ex=$('exportTraffic'); if(ex) ex.href=`export.php?range=${range}&router_id=${routerId}`;
    } catch(e){ console.error(e); }
  }

  async function init(){
    await routers();
    document.querySelectorAll('[data-range]').forEach(b=>b.addEventListener('click',()=>{range=b.dataset.range;document.querySelectorAll('[data-range]').forEach(x=>x.classList.remove('active'));b.classList.add('active');load();}));
    const sel=$('historyRouter'); if(sel) sel.addEventListener('change',()=>{routerId=Number(sel.value);interfaceName='all';load();});
    const intSel=$('historyInterface'); if(intSel) intSel.addEventListener('change',()=>{interfaceName=intSel.value;load();});
    document.querySelector(`[data-range="${range}"]`)?.classList.add('active');
    load(); setInterval(load,10000);
  }
  init();
})();
