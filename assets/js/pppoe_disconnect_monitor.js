/* NetMonitor — PPPoE disconnect Telegram monitor PRO v2 */
(function(){'use strict';
if(!location.pathname.toLowerCase().includes('/pppoe/'))return;
const notifyApi='../api/pppoe_disconnect_notify.php';
let previousByRouter={},busy=false;
function key(x){return String(x.session_id||'')+'|'+String(x.name||'');}
function clean(x){return {name:x.name||'-',address:x.address||'-',profile:x.profile||'-',caller_id:x.caller_id||'-',uptime:x.uptime||'-',session_id:x.session_id||'-'};}
async function processStats(d){
 if(busy||!d||!d.success)return;
 const routerId=Number(d.router_id||0);if(routerId<=0)return;
 const current=Array.isArray(d.active)?d.active.map(clean):[];
 const previous=previousByRouter[routerId];
 previousByRouter[routerId]=current;
 if(!Array.isArray(previous)||!previous.length)return;
 const currentKeys=new Set(current.map(key));
 const gone=previous.filter(x=>!currentKeys.has(key(x)));
 if(!gone.length)return;
 busy=true;
 try{await fetch(notifyApi,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({router_id:routerId,router_name:d.router_name||'Router',events:gone})});}
 catch(_e){}
 finally{busy=false;}
}
window.addEventListener('pppoe:stats',e=>processStats(e.detail&&e.detail.data));
})();
