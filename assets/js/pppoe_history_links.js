/* NetMonitor PPPoE History — native links, no JS modal dependency */
(function(){'use strict';
if(!location.pathname.toLowerCase().includes('/pppoe/'))return;
const esc=v=>{const d=document.createElement('div');d.textContent=v??'';return d.innerHTML};
function convert(){
 document.querySelectorAll('[data-history-user]').forEach(b=>{
   if(b.tagName==='A')return;
   const user=b.getAttribute('data-history-user')||'';
   if(!user)return;
   const a=document.createElement('a');
   a.href='./history.php?username='+encodeURIComponent(user);
   a.className=b.className;
   a.title='Traffic history '+user;
   a.textContent='↗';
   a.setAttribute('aria-label','Traffic history '+user);
   a.style.display='inline-grid';a.style.placeItems='center';a.style.textDecoration='none';a.style.position='relative';a.style.zIndex='100';a.style.pointerEvents='auto';
   b.replaceWith(a);
 });
 document.querySelectorAll('.ppp-history-btn').forEach(b=>{
   if(b.tagName==='A')return;
   const m=(b.getAttribute('onclick')||'').match(/pppOpenHistory\((['\"])(.*?)\1\)/);
   if(!m)return;
   const user=m[2],a=document.createElement('a');
   a.href='./history.php?username='+encodeURIComponent(user);
   a.className=b.className;a.title='Traffic history '+user;a.textContent='↗';
   a.style.display='inline-grid';a.style.placeItems='center';a.style.textDecoration='none';a.style.position='relative';a.style.zIndex='100';a.style.pointerEvents='auto';
   b.replaceWith(a);
 });
}
function init(){convert();new MutationObserver(convert).observe(document.body,{childList:true,subtree:true});}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
