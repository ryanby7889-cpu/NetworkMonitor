/* NetMonitor — PPPoE disconnect/reconnect Telegram monitor PRO v4 */
(function(){'use strict';
if(!location.pathname.toLowerCase().includes('/pppoe/'))return;
const notifyApi='../api/pppoe_disconnect_notify.php';
let previousByRouter={},busy=false;
function clean(x){return {name:x.name||'-',address:x.address||'-',profile:x.profile||'-',caller_id:x.caller_id||'-',uptime:x.uptime||'-',session_id:x.session_id||'-'};}
function userKey(x){return String(x.name||'').trim().toLowerCase();}
async function processStats(d){
 if(busy||!d||!d.success)return;
 const routerId=Number(d.router_id||0);if(routerId<=0)return;
 const current=Array.isArray(d.active)?d.active.map(clean):[];
 const previous=previousByRouter[routerId];
 previousByRouter[routerId]=current;
 if(!Array.isArray(previous))return;
 const currentUsers=new Map(current.filter(x=>userKey(x)).map(x=>[userKey(x),x]));
 const previousUsers=new Map(previous.filter(x=>userKey(x)).map(x=>[userKey(x),x]));
 const gone=[];const connected=[];
 previousUsers.forEach((old,k)=>{if(!currentUsers.has(k))gone.push(old);});
 currentUsers.forEach((now,k)=>{if(!previousUsers.has(k))connected.push(now);});
 if(!gone.length&&!connected.length)return;
 busy=true;
 try{
   const r=await fetch(notifyApi,{method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',body:JSON.stringify({router_id:routerId,router_name:d.router_name||'Router',events:gone,connect_events:connected})});
   try{const result=await r.json();if(!result.success)console.warn('PPPoE notify:',result.message||result);}catch(_e){}
 }catch(e){console.warn('PPPoE notify gagal:',e);}
 finally{busy=false;}
}
window.addEventListener('pppoe:stats',e=>processStats(e.detail&&e.detail.data));
})();
