/* NetMonitor — PPPoE disconnect/reconnect Telegram monitor PRO v3 */
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
 if(!Array.isArray(previous))return;
 const currentKeys=new Set(current.map(key));
 const gone=previous.filter(x=>!currentKeys.has(key(x)));
 const previousUsers=new Map();previous.forEach(x=>previousUsers.set(String(x.name||''),x));
 const currentUsers=new Map();current.forEach(x=>currentUsers.set(String(x.name||''),x));
 const reconnect=[];
 currentUsers.forEach((x,name)=>{if(name&&previousUsers.has(name)===false)return;});
 /* Browser fallback: only notify a username returning after it was absent.
    The API's reconnect log prevents duplicate sends when the server collector
    has already reported the same reconnect. */
 const lastOffline=previousByRouter.__offline||{};
 const offlineForRouter=lastOffline[routerId]||new Set();
 currentUsers.forEach((x,name)=>{if(offlineForRouter.has(name))reconnect.push(x);});
 const nextOffline=new Set(offlineForRouter);gone.forEach(x=>{if(x.name)nextOffline.add(String(x.name));});currentUsers.forEach((x,name)=>nextOffline.delete(name));
 if(!lastOffline[routerId])lastOffline[routerId]=nextOffline;else lastOffline[routerId]=nextOffline;
 previousByRouter.__offline=lastOffline;
 if(!gone.length&&!reconnect.length)return;
 busy=true;
 try{await fetch(notifyApi,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({router_id:routerId,router_name:d.router_name||'Router',events:gone,connect_events:reconnect})});}
 catch(_e){}
 finally{busy=false;}
}
window.addEventListener('pppoe:stats',e=>processStats(e.detail&&e.detail.data));
})();
