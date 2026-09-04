/* PPPoE Traffic Ranking + Realtime Graph PRO */
(function(){
  'use strict';
  if(window.__pppoeTrafficRankingLoaded)return;
  window.__pppoeTrafficRankingLoaded=true;
  const state={prev:new Map(), rates:new Map(), samples:[], maxSamples:30, lastTs:0};
  const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const mbps=(bytes,dt)=>dt>0?Math.max(0,(Number(bytes)||0)*8/dt/1000000):0;
  const fmt=v=>{v=Number(v)||0;return v>=1000?(v/1000).toFixed(2)+' Gbps':v.toFixed(v>=100?0:2)+' Mbps'};
  function rateRows(users,ts){
    const next=new Map(), rows=[];
    (users||[]).forEach(u=>{
      const key=String(u.session_id||u.interface||u.name||u.username||'');
      if(!key)return;
      const curIn=Number(u.bytes_in)||0,curOut=Number(u.bytes_out)||0;
      const old=state.prev.get(key); let up=0,down=0;
      if(old){const dt=(ts-old.ts)/1000;if(dt>0&&dt<60){up=mbps(curIn-old.in,dt);down=mbps(curOut-old.out,dt)}}
      next.set(key,{ts,in:curIn,out:curOut,name:String(u.name||u.username||key),profile:String(u.profile||'')});
      rows.push({name:String(u.name||u.username||key),profile:String(u.profile||''),up,down});
    });
    state.prev=next;
    return rows;
  }
  function inject(){
    if(document.getElementById('pppoeTrafficRanking'))return;
    const anchor=document.getElementById('pppAnalyticsPro')||document.querySelector('.pppoe-page .pppoe-card:last-of-type');
    if(!anchor)return;
    const sec=document.createElement('section');sec.id='pppoeTrafficRanking';sec.className='card-modern pppoe-card';
    sec.innerHTML='<div class="pppoe-card-header"><div class="pppoe-card-heading"><h2>PPPoE Traffic Ranking PRO</h2><small>Realtime rate per user · update mengikuti polling PPPoE 10 detik</small></div><div class="pppoe-card-tools"><span id="pppoeTrafficUpdated" class="badge-status">● Menunggu data</span></div></div><div class="ptr-grid"><div class="ptr-chart-wrap"><canvas id="pppoeTrafficChart" height="230"></canvas></div><div class="ptr-rank-grid"><div class="ptr-rank"><h3>Top Download</h3><div id="ptrDown"></div></div><div class="ptr-rank"><h3>Top Upload</h3><div id="ptrUp"></div></div></div></div>';
    anchor.insertAdjacentElement('afterend',sec);
    const style=document.createElement('style');style.textContent='.ptr-grid{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(300px,1fr);gap:16px}.ptr-chart-wrap,.ptr-rank{border:1px solid var(--border-color,#ddd);border-radius:14px;padding:14px;background:var(--card-bg,#fff)}.ptr-rank-grid{display:grid;grid-template-columns:1fr;gap:16px}.ptr-rank h3{font-size:14px;margin:0 0 10px}.ptr-item{display:grid;grid-template-columns:24px 1fr auto;gap:8px;align-items:center;padding:7px 0;border-bottom:1px solid var(--border-color,#eee)}.ptr-item:last-child{border-bottom:0}.ptr-name{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ptr-meta{font-size:11px;opacity:.65}.ptr-rate{font-weight:700;white-space:nowrap}@media(max-width:900px){.ptr-grid{grid-template-columns:1fr}}@media(max-width:600px){.ptr-rank-grid{grid-template-columns:1fr}.ptr-chart-wrap{padding:8px}}';document.head.appendChild(style);
  }
  let chart=null;
  function render(rows){
    inject();
    const down=[...rows].sort((a,b)=>b.down-a.down).slice(0,10),up=[...rows].sort((a,b)=>b.up-a.up).slice(0,10);
    const list=(arr,field)=>arr.length?arr.map((x,i)=>'<div class="ptr-item"><span>'+(i+1)+'</span><div><div class="ptr-name" title="'+esc(x.name)+'">'+esc(x.name)+'</div><div class="ptr-meta">'+esc(x.profile||'PPPoE')+'</div></div><span class="ptr-rate">'+fmt(x[field])+'</span></div>').join(''):'<div class="ptr-meta">Belum ada sample rate.</div>';
    const d=document.getElementById('ptrDown'),u=document.getElementById('ptrUp');if(d)d.innerHTML=list(down,'down');if(u)u.innerHTML=list(up,'up');
    const totalDown=rows.reduce((s,x)=>s+x.down,0),totalUp=rows.reduce((s,x)=>s+x.up,0);
    state.samples.push({t:new Date(),down:totalDown,up:totalUp});if(state.samples.length>state.maxSamples)state.samples.shift();
    const cv=document.getElementById('pppoeTrafficChart');
    if(cv&&window.Chart){
      const labels=state.samples.map(x=>x.t.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'}));
      if(!chart){chart=new Chart(cv,{type:'line',data:{labels,datasets:[{label:'Download Mbps',data:state.samples.map(x=>x.down),tension:.3,fill:false},{label:'Upload Mbps',data:state.samples.map(x=>x.up),tension:.3,fill:false}]},options:{responsive:true,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true,title:{display:true,text:'Mbps'}},x:{ticks:{maxTicksLimit:8}}}}});}
      else{chart.data.labels=labels;chart.data.datasets[0].data=state.samples.map(x=>x.down);chart.data.datasets[1].data=state.samples.map(x=>x.up);chart.update('none')}
    }
    const b=document.getElementById('pppoeTrafficUpdated');if(b)b.textContent='● '+new Date().toLocaleTimeString('id-ID');
  }
  function onStats(e){const snap=e.detail||window.pppoeStatsSnapshot||{};const users=snap.active||snap.users||[];const ts=Date.now();const rows=rateRows(users,ts);if(state.lastTs)render(rows);state.lastTs=ts;}
  document.addEventListener('DOMContentLoaded',inject);
  window.addEventListener('pppoe:stats',onStats);
})();
