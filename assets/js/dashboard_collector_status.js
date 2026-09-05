/* Dashboard collector status badge */
(function(){'use strict';
if(!location.pathname.toLowerCase().includes('/dashboard/'))return;
function boot(){
 const el=document.getElementById('dashboardCollector');
 if(!el)return;
 const icon='<i class="bi bi-activity"></i>';
 function paint(state){
   el.classList.remove('healthy','delayed','offline');
   if(state==='online'){el.classList.add('healthy');el.innerHTML=icon+' Collector: Online';}
   else if(state==='offline'){el.classList.add('offline');el.innerHTML=icon+' Collector: Offline';}
   else {el.classList.add('delayed');el.innerHTML=icon+' Collector: Memeriksa';}
 }
 async function check(){
   try{
     const id=window.selectedRouterId||localStorage.getItem('netmonitor_selected_router')||'';
     const q=id?'&router_id='+encodeURIComponent(id):'';
     const r=await fetch('../api/traffic.php?nocache='+Date.now()+q,{cache:'no-store'});
     if(!r.ok)throw Error('HTTP '+r.status);
     const d=await r.json();
     if(!d.success)throw Error(d.message||'API error');
     paint(String(d.status||'').toLowerCase()==='online'?'online':'offline');
   }catch(e){paint('offline');}
 }
 paint('checking');check();
 setInterval(check,10000);
 window.addEventListener('router:changed',check);
 window.addEventListener('pppoe:router-changed',check);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
