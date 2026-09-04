/* NetMonitor PPPoE Stats Bus PRO v1 — one router/API poll shared by all PPPoE widgets */
(function(){'use strict';
if(!location.pathname.toLowerCase().includes('/pppoe/'))return;
const api='../api/pppoe_stats.php';
let timer=null,loading=false,lastAt=0;
window.pppoeStatsSnapshot=window.pppoeStatsSnapshot||null;
async function poll(){
  if(loading||document.visibilityState!=='visible')return;
  loading=true;
  try{
    const r=await fetch(api+'?t='+Date.now(),{cache:'no-store'}),d=await r.json();
    if(!r.ok||!d.success)throw Error(d.message||'PPPoE stats gagal');
    const now=Date.now(),dt=lastAt?Math.max(.1,(now-lastAt)/1000):0;
    window.pppoeStatsSnapshot={data:d,now:now,dt:dt};
    lastAt=now;
    window.dispatchEvent(new CustomEvent('pppoe:stats',{detail:{data:d,dt:dt,now:now}}));
  }catch(e){window.dispatchEvent(new CustomEvent('pppoe:stats-error',{detail:{message:e.message||'PPPoE stats gagal'}}))}
  finally{loading=false}
}
window.pppoeStatsRefresh=poll;
function init(){poll();timer=setInterval(poll,10000)}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
