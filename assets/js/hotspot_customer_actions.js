document.addEventListener('DOMContentLoaded',()=>{
 const table=document.getElementById('hotspotCustomerTable');
 if(!table)return;
 table.addEventListener('click',async e=>{
  const btn=e.target.closest('[data-customer-detail]');
  if(!btn)return;
  const username=String(btn.dataset.customerDetail||'').trim();
  const item=(window.hotspotUsers||[]).find(x=>String(x.name||'').trim().toLowerCase()===username.toLowerCase());
  if(typeof openDetail==='function'&&item){await openDetail(item);return;}
  const fallback=(typeof users!=='undefined'?users:[]).find(x=>String(x.name||'').trim().toLowerCase()===username.toLowerCase());
  if(typeof openDetail==='function'&&fallback)await openDetail(fallback);
 });
});