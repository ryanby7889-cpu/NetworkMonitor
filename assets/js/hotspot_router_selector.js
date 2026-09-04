document.addEventListener('DOMContentLoaded',()=>{
 const header=document.querySelector('.hotspot-header'); if(!header)return;
 const box=document.createElement('div'); box.className='hotspot-router-selector';
 box.innerHTML='<label><i class="bi bi-router"></i><span>Router</span><select id="hotspotRouterSelect"><option>Memuat...</option></select><small id="hotspotRouterStatus"></small></label>';
 const actions=header.querySelector('.hotspot-header-actions')||header;
 actions.appendChild(box);
 const select=box.querySelector('#hotspotRouterSelect'), status=box.querySelector('#hotspotRouterStatus');
 async function load(){
  try{
   const r=await fetch('../api/routers.php?t='+Date.now(),{cache:'no-store'}); const d=await r.json();
   if(!d.success)throw Error(d.message||'Gagal memuat router');
   const rows=d.routers||[], active=Number(d.active_id||0);
   select.innerHTML=rows.map(x=>`<option value="${x.id}">${escapeHtml(x.name||('Router #'+x.id))} — ${escapeHtml(x.ip_address||'')}</option>`).join('');
   if(active)select.value=String(active); else if(rows[0])select.value=String(rows[0].id);
   updateStatus(rows.find(x=>Number(x.id)===Number(select.value)));
  }catch(e){select.innerHTML='<option>Gagal memuat router</option>';status.textContent='';}
 }
 function updateStatus(x){if(!x){status.textContent='';return} status.textContent=String(x.status||'').toUpperCase(); status.className=String(x.status||'').toLowerCase()==='online'?'online':'offline';}
 select.addEventListener('change',async()=>{
  const id=Number(select.value); if(!id)return;
  select.disabled=true; status.textContent='Menghubungkan...';
  try{
   const r=await fetch('../api/hotspot_router.php',{method:'POST',body:new URLSearchParams({router_id:id})}); const d=await r.json();
   if(!d.success)throw Error(d.message||'Gagal mengganti router');
   localStorage.setItem('netmonitor_active_router',String(id));
   window.location.reload();
  }catch(e){alert(e.message);select.disabled=false;load();}
 });
 function escapeHtml(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML}
 load();
});
