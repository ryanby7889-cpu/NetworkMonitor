/* NetMonitor PPPoE LIVE — per-session traffic overlay */
(function(){
'use strict';
if(!location.pathname.toLowerCase().includes('/pppoe/')) return;
const api='../api/pppoe_stats.php';
let latest=[], busy=false;
const $=id=>document.getElementById(id);
function esc(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML}
function bytes(n){n=Number(n)||0;if(n<1024)return Math.round(n)+' B';const u=['KB','MB','GB','TB'];let i=-1;do{n/=1024;i++}while(n>=1024&&i<u.length-1);return n.toFixed(n>=100?0:1)+' '+u[i]}
function decorate(){
 const tbody=$('activeTable'); if(!tbody||!latest.length)return;
 const rows=[...tbody.querySelectorAll('tr')];
 rows.forEach(row=>{
  const user=row.querySelector('.pppoe-user-name'); if(!user)return;
  const name=(user.textContent||'').trim();
  const item=latest.find(x=>String(x.name)===name); if(!item)return;
  let cell=row.querySelector('.pppoe-live-traffic');
  if(!cell){cell=document.createElement('td');cell.className='pppoe-live-traffic';const action=row.lastElementChild;if(action)row.insertBefore(cell,action);else row.appendChild(cell)}
  cell.innerHTML=`<div class="ppp-live-main"><span class="ppp-rx">↓ ${esc(bytes(item.bytes_in))}</span><span class="ppp-tx">↑ ${esc(bytes(item.bytes_out))}</span></div><small>${esc(item.profile||'default')}</small>`;
 });
 const table=tbody.closest('table'); if(table){const heads=table.querySelectorAll('thead th');const action=[...heads].find(h=>(h.textContent||'').trim().toLowerCase()==='action');if(action&&!table.querySelector('.pppoe-live-head')){const th=document.createElement('th');th.className='pppoe-live-head';th.textContent='TRAFFIC';action.parentNode.insertBefore(th,action)}}
}
function style(){const s=document.createElement('style');s.id='pppoeLiveStyle';s.textContent=`
.pppoe-live-head{white-space:nowrap}.pppoe-live-traffic{white-space:nowrap;min-width:125px}.ppp-live-main{display:flex;gap:8px;align-items:center;font-weight:600;font-size:10px}.ppp-live-main .ppp-rx{color:#0a9f72}.ppp-live-main .ppp-tx{color:#3568e8}.pppoe-live-traffic small{display:block;margin-top:3px;color:var(--muted);font-size:9px;max-width:120px;overflow:hidden;text-overflow:ellipsis}
.pppoe-live-head,.pppoe-live-traffic{background:var(--card)}
@media(max-width:900px){.pppoe-live-traffic{min-width:110px}.ppp-live-main{display:block}.ppp-live-main span{margin-right:6px}}
`;document.head.appendChild(s)}
async function refresh(){if(busy||document.visibilityState!=='visible')return;busy=true;try{const r=await fetch(api+'?t='+Date.now(),{cache:'no-store'});const d=await r.json();if(!r.ok||!d.success)throw Error(d.message||'');latest=d.active||[];decorate()}catch(e){}finally{busy=false}}
function init(){style();refresh();setInterval(refresh,10000);new MutationObserver(()=>decorate()).observe(document.body,{childList:true,subtree:true})}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
