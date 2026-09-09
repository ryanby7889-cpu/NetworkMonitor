/* Dashboard collector/router status badge */
(function(){'use strict';
if(!location.pathname.toLowerCase().includes('/dashboard/'))return;
function boot(){
 const el=document.getElementById('dashboardCollector');
 const routerStatus=document.getElementById('status');
 let lastGoodState='checking';
 function paint(state,age){
   if(el){
     const icon='<i class="bi bi-activity"></i>';
     el.classList.remove('healthy','delayed','offline');
     if(state==='online'){
       el.classList.add('healthy');
       el.innerHTML=icon+' Collector: Healthy'+(age!==null?' • '+age+' dtk lalu':'');
     } else if(state==='offline'){
       el.classList.add('offline');
       el.innerHTML=icon+' Collector: Offline';
     } else {
       el.classList.add('delayed');
       el.innerHTML=icon+' Collector: Memeriksa...';
     }
   }
 }
 if(routerStatus && /checking\.\.\./i.test(routerStatus.textContent.trim())){
   routerStatus.innerHTML='<span class="status-dot"></span>MEMERIKSA...';
 }
 async function check(){
   try{
     const id=window.selectedRouterId||localStorage.getItem('netmonitor_selected_router')||'';
     const q=id?'&router_id='+encodeURIComponent(id):'';
     const r=await fetch('../api/dashboard_history.php?range=10m&nocache='+Date.now()+q,{cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});
     if(!r.ok)throw Error('HTTP '+r.status);
     const d=await r.json();
     if(!d.success)throw Error(d.message||'API error');
     const collectorStatus=String(d.collector_status||'OFFLINE').toUpperCase();
     const age=d.collector_age===null||d.collector_age===undefined?null:Math.max(0,Math.round(Number(d.collector_age)));
     if(collectorStatus==='HEALTHY'){
       lastGoodState='online';
       paint('online',age);
     }else if(collectorStatus==='DELAYED'){
       lastGoodState='delayed';
       paint('delayed',age);
     }else{
       lastGoodState='offline';
       paint('offline',age);
     }
   }catch(e){
     // Keep the last known collector state during a temporary API/network delay.
     if(lastGoodState==='online') return;
     if(lastGoodState==='delayed') return;
     paint('offline');
     console.error('Collector status:',e);
   }
 }
 paint('checking');
 check();
 setInterval(check,10000);
 window.addEventListener('router:changed',check);
 window.addEventListener('pppoe:router-changed',check);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
