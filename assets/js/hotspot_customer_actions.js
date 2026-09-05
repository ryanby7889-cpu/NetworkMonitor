document.addEventListener('DOMContentLoaded',()=>{
 const table=document.getElementById('hotspotCustomerTable');
 if(!table)return;
 const historyUrl=username=>'../hotspot/history.php?username='+encodeURIComponent(username);
 const dashboardUrl=username=>'../hotspot/user.php?username='+encodeURIComponent(username);
 function addActionButtons(username){
  const actions=document.querySelector('#detailModal .hs-modal-actions');if(!actions)return;
  let dash=document.getElementById('hotspotDashboardBtn');
  if(!dash){dash=document.createElement('a');dash.id='hotspotDashboardBtn';dash.className='hs-btn hs-primary';dash.target='_self';actions.insertBefore(dash,actions.firstChild)}
  dash.href=dashboardUrl(username);dash.innerHTML='<i class="bi bi-speedometer2"></i> Dashboard User';
  let history=document.getElementById('hotspotHistoryBtn');
  if(!history){history=document.createElement('a');history.id='hotspotHistoryBtn';history.className='hs-btn';history.target='_self';actions.insertBefore(history,dash.nextSibling)}
  history.href=historyUrl(username);history.innerHTML='<i class="bi bi-graph-up"></i> History Traffic';
 }
 table.addEventListener('click',async e=>{
  const btn=e.target.closest('[data-customer-detail]');if(!btn)return;
  const username=String(btn.dataset.customerDetail||'').trim();
  const item=(window.hotspotUsers||[]).find(x=>String(x.name||'').trim().toLowerCase()===username.toLowerCase());
  const fallback=(typeof users!=='undefined'?users:[]).find(x=>String(x.name||'').trim().toLowerCase()===username.toLowerCase());
  const target=item||fallback;
  if(typeof openDetail==='function'&&target){await openDetail(target);addActionButtons(username)}
 });
});