document.addEventListener('DOMContentLoaded',()=>{
 const table=document.getElementById('hotspotCustomerTable');
 if(!table)return;
 const esc=v=>{const d=document.createElement('div');d.textContent=v??'';return d.innerHTML};
 const historyUrl=username=>'../hotspot/history.php?username='+encodeURIComponent(username);
 function addHistoryButton(username){
  const actions=document.querySelector('#detailModal .hs-modal-actions');
  if(!actions)return;
  let btn=document.getElementById('hotspotHistoryBtn');
  if(!btn){btn=document.createElement('a');btn.id='hotspotHistoryBtn';btn.className='hs-btn hs-primary';btn.target='_self';actions.insertBefore(btn,actions.firstChild);}
  btn.href=historyUrl(username);btn.innerHTML='<i class="bi bi-graph-up"></i> History Traffic';btn.title='Lihat histori traffic '+esc(username);
 }
 table.addEventListener('click',async e=>{
  const btn=e.target.closest('[data-customer-detail]');
  if(!btn)return;
  const username=String(btn.dataset.customerDetail||'').trim();
  const item=(window.hotspotUsers||[]).find(x=>String(x.name||'').trim().toLowerCase()===username.toLowerCase());
  const fallback=(typeof users!=='undefined'?users:[]).find(x=>String(x.name||'').trim().toLowerCase()===username.toLowerCase());
  const target=item||fallback;
  if(typeof openDetail==='function'&&target){await openDetail(target);addHistoryButton(username);}
 });
});