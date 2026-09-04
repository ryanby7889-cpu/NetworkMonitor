/* PPPoE PRO — compact professional overview, filtering and session insights */
(function(){
'use strict';
function init(){
 if(!location.pathname.toLowerCase().includes('/pppoe/')) return;
 if(document.getElementById('pppoePro')) return;
 const main=document.querySelector('.pppoe-page'); if(!main) return;
 const style=document.createElement('style'); style.id='pppoeProStyle'; style.textContent=`
 .pppoe-page{width:calc(100% - var(--sidebar-open));max-width:calc(100% - var(--sidebar-open));min-width:0;overflow-x:hidden;padding:24px 26px 32px}
 .sidebar.collapsed~.pppoe-page{width:calc(100% - var(--sidebar-closed));max-width:calc(100% - var(--sidebar-closed))}
 .pppoe-page-header{margin-bottom:16px;gap:14px}.pppoe-page-header .page-title{font-size:24px;letter-spacing:-.3px}.pppoe-page-header .page-subtitle{font-size:12px;margin-top:3px}
 #connectionStatus{min-height:32px;padding:0 10px;font-size:11px}#refreshBtn{min-height:34px;font-size:12px;padding:7px 11px}
 .pppoe-page>.pppoe-stats{display:none!important}#pppoePro{margin:0 0 16px}.ppp-pro-toolbar{margin-bottom:10px;gap:8px}.ppp-pro-title{font-size:13px}.ppp-pro-sub{font-size:10px}.ppp-pro-actions{gap:6px}.ppp-pro-btn{padding:6px 10px;border-radius:8px;font-size:10px}
 .ppp-pro-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.ppp-pro-card{min-height:84px;padding:12px 14px;border-radius:12px}.ppp-pro-label{font-size:9px}.ppp-pro-value{font-size:21px;margin-top:4px}.ppp-pro-meta{font-size:9px;margin-top:4px}.ppp-pro-panel{margin-top:10px;padding:12px 14px;border-radius:12px}.ppp-pro-panel-title{font-size:11px;margin-bottom:8px}.ppp-pro-bars{gap:6px}.ppp-pro-bar-row{grid-template-columns:110px 1fr 36px;gap:8px;font-size:9px}.ppp-pro-bar{height:6px}
 .pppoe-card{margin-bottom:12px;border-radius:12px}.pppoe-card-header{padding:13px 15px;gap:10px}.pppoe-card-heading h2{font-size:15px}.pppoe-card-heading small{font-size:10px;margin-top:3px}.pppoe-table th{padding:9px 11px;font-size:10px}.pppoe-table td{padding:9px 11px;font-size:12px}.pppoe-table .btn{min-height:29px}.btn-small{padding:5px 8px;font-size:10px}.pppoe-search{min-height:34px;font-size:12px}
 @media(max-width:1100px){.ppp-pro-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
 @media(max-width:700px){.pppoe-page{width:calc(100% - var(--sidebar-closed));max-width:calc(100% - var(--sidebar-closed));padding:18px 14px 24px}.pppoe-page-header{flex-direction:column;margin-bottom:14px}.pppoe-actions{width:100%;justify-content:flex-start}.ppp-pro-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
 @media(max-width:480px){.ppp-pro-grid{grid-template-columns:1fr}.ppp-pro-bar-row{grid-template-columns:90px 1fr 32px}}
 `; document.head.appendChild(style);
 const wrap=document.createElement('section'); wrap.id='pppoePro';
 wrap.innerHTML=`<div class="ppp-pro-toolbar"><div><div class="ppp-pro-title"><i class="bi bi-broadcast-pin"></i> PPPoE Live Overview</div><div class="ppp-pro-sub" id="pppProUpdated">Menunggu data...</div></div><div class="ppp-pro-actions"><button class="ppp-pro-btn active" data-pro-filter="all">Semua</button><button class="ppp-pro-btn" data-pro-filter="enabled">Enabled</button><button class="ppp-pro-btn" data-pro-filter="disabled">Disabled</button></div></div><div class="ppp-pro-grid"><div class="ppp-pro-card"><div class="ppp-pro-label">Active Sessions</div><div class="ppp-pro-value ppp-pro-online" id="pppProActive">0</div><div class="ppp-pro-meta">koneksi aktif saat ini</div></div><div class="ppp-pro-card"><div class="ppp-pro-label">Enabled Accounts</div><div class="ppp-pro-value" id="pppProEnabled">0</div><div class="ppp-pro-meta">akun siap digunakan</div></div><div class="ppp-pro-card"><div class="ppp-pro-label">Disabled Accounts</div><div class="ppp-pro-value ppp-pro-warning" id="pppProDisabled">0</div><div class="ppp-pro-meta">akun dinonaktifkan</div></div><div class="ppp-pro-card"><div class="ppp-pro-label">Traffic Sessions</div><div class="ppp-pro-value" id="pppProTraffic">0 B</div><div class="ppp-pro-meta">RX + TX cumulative</div></div></div><div class="ppp-pro-panel"><div class="ppp-pro-panel-title">Profile Distribution</div><div id="pppProBars" class="ppp-pro-bars"><div class="ppp-pro-empty">Memuat...</div></div></div>`;
 const first=main.querySelector('.pppoe-stats'); first?.before(wrap);
 const api='../api/pppoe_stats.php'; let filter='all',busy=false,timer; const $=id=>document.getElementById(id);
 function fmtBytes(n){n=Number(n)||0;if(n<1024)return n.toFixed(0)+' B';const u=['KB','MB','GB','TB'];let i=-1;do{n/=1024;i++}while(n>=1024&&i<u.length-1);return n.toFixed(n>=100?0:1)+' '+u[i]}
 function setFilter(f){filter=f;document.querySelectorAll('[data-pro-filter]').forEach(b=>b.classList.toggle('active',b.dataset.proFilter===f));const rows=document.querySelectorAll('#secretTable tr');rows.forEach(r=>{const pill=r.querySelector('.status-pill');if(!pill){r.style.display='';return}const dis=pill.classList.contains('disabled');r.style.display=f==='all'||(f==='disabled'&&dis)||(f==='enabled'&&!dis)?'':'none'})}
 function renderBars(usage){const el=$('pppProBars');const entries=Object.entries(usage||{}).sort((a,b)=>b[1]-a[1]).slice(0,8);if(!entries.length){el.innerHTML='<div class="ppp-pro-empty">Belum ada profile terpakai.</div>';return}const max=Math.max(...entries.map(x=>x[1]),1);el.innerHTML=entries.map(([name,count])=>`<div class="ppp-pro-bar-row"><span>${esc(name)}</span><div class="ppp-pro-bar"><i style="width:${Math.max(3,Math.round(count/max*100))}%"></i></div><strong>${count}</strong></div>`).join('')}
 function esc(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML}
 async function refresh(){if(busy||document.visibilityState!=='visible')return;busy=true;try{const r=await fetch(api+'?t='+Date.now(),{cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});const d=await r.json();if(!r.ok||!d.success)throw Error(d.message||'Gagal mengambil statistik PPPoE');$('pppProActive').textContent=d.active_count??0;$('pppProEnabled').textContent=d.enabled_accounts??0;$('pppProDisabled').textContent=d.disabled_accounts??0;$('pppProTraffic').textContent=fmtBytes((Number(d.total_rx_bytes)||0)+(Number(d.total_tx_bytes)||0));$('pppProUpdated').textContent='Last update: '+new Date().toLocaleTimeString('id-ID')+' • auto refresh 10 detik';renderBars(d.profile_usage);setFilter(filter)}catch(e){$('pppProUpdated').textContent='PPPoE stats: '+e.message}finally{busy=false}}
 document.querySelectorAll('[data-pro-filter]').forEach(b=>b.addEventListener('click',()=>setFilter(b.dataset.proFilter)));refresh();timer=setInterval(refresh,10000);document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible'){refresh()}else clearInterval(timer)});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();

/* Load the management layer after the overview is ready. */
(function(){
 if(!location.pathname.toLowerCase().includes('/pppoe/')) return;
 const load=()=>{if(document.querySelector('script[data-pppoe-management]'))return;const s=document.createElement('script');s.src='../assets/js/pppoe_management.js?v=2';s.dataset.pppoeManagement='1';document.body.appendChild(s)};
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(load,80));else setTimeout(load,80);
})();
