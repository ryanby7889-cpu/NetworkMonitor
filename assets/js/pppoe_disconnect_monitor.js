/* NetMonitor — PPPoE disconnect Telegram monitor */
(function(){'use strict';
if(!location.pathname.toLowerCase().includes('/pppoe/'))return;
const statsApi='../api/pppoe_stats.php';
const notifyApi='../api/pppoe_disconnect_notify.php';
let previous=null, busy=false;
function key(x){return String(x.session_id||'')+'|'+String(x.name||'');}
function clean(x){return {name:x.name||'-',address:x.address||'-',profile:x.profile||'-',caller_id:x.caller_id||'-',uptime:x.uptime||'-',session_id:x.session_id||'-'};}
async function poll(){
 if(busy||document.visibilityState!=='visible')return;
 busy=true;
 try{
  const r=await fetch(statsApi+'?tg='+Date.now(),{cache:'no-store'}),d=await r.json();
  if(!r.ok||!d.success)throw Error(d.message||'PPPoE stats gagal');
  const current=Array.isArray(d.active)?d.active.map(clean):[];
  if(Array.isArray(previous)){
   const currentKeys=new Set(current.map(key));
   const gone=previous.filter(x=>!currentKeys.has(key(x)));
   if(gone.length){
    try{await fetch(notifyApi,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({router_id:d.router_id,router_name:d.router_name,events:gone})});}catch(_e){}
   }
  }
  previous=current;
 }catch(_e){}
 finally{busy=false;}
}
function init(){poll();setInterval(poll,10000)}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
