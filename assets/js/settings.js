document.addEventListener('DOMContentLoaded',()=>{
  const layout=document.querySelector('.settings-layout');
  const nav=layout?.querySelector('.settings-nav');
  const section=layout?.querySelector(':scope > section');
  if(!layout||!nav||!section)return;
  Array.from(section.querySelectorAll('.settings-pane')).forEach(p=>{if(p.parentElement!==section)section.appendChild(p);});
  const tabs=nav.querySelectorAll('[data-settings-tab]');
  const panes=section.querySelectorAll('[data-settings-pane]');
  const valid=['general','mikrotik','alarm','system','billing'];
  function activate(target,writeHash=true){if(!valid.includes(target))target='general';tabs.forEach(x=>x.classList.toggle('active',x.dataset.settingsTab===target));panes.forEach(x=>x.classList.toggle('active',x.dataset.settingsPane===target));if(writeHash)history.replaceState(null,'','#'+target)}
  tabs.forEach(tab=>tab.addEventListener('click',()=>activate(tab.dataset.settingsTab)));
  activate((location.hash||'').replace('#','')||'general',false);
  const alarmPane=section.querySelector('[data-settings-pane="alarm"]');
  if(alarmPane){const desc=alarmPane.querySelector('.text-secondary.small');if(desc)desc.textContent='Atur batas pemakaian bandwidth PPPoE. Nilai adalah persentase dari rate-limit user.';alarmPane.querySelectorAll('.form-label').forEach(label=>{const t=label.textContent.trim();if(t.includes('Download Warning'))label.textContent='Download Warning (%)';if(t.includes('Download Critical'))label.textContent='Download Critical (%)';if(t.includes('Upload Warning'))label.textContent='Upload Warning (%)';if(t.includes('Upload Critical'))label.textContent='Upload Critical (%)'});alarmPane.querySelectorAll('input[type="number"]').forEach(i=>{i.min='0';i.max='100';i.step='1'})}
  document.querySelectorAll('.test-router-connection').forEach(test=>test.addEventListener('click',async()=>{const routerId=test.dataset.routerId,result=document.querySelector(`.router-connection-result[data-router-id="${CSS.escape(routerId)}"]`),old=test.innerHTML;test.disabled=true;test.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Tes...';if(result){result.className='router-connection-result text-secondary';result.textContent='Menghubungkan...'}try{const r=await fetch(`../api/settings_router.php?router_id=${encodeURIComponent(routerId)}`,{cache:'no-store'}),d=await r.json(),ok=!!d.success;if(result){result.className='router-connection-result '+(ok?'text-success':'text-danger');result.textContent=ok?`ONLINE • ${d.identity||'Terhubung'}`:(d.message||'Koneksi gagal')}const badge=document.querySelector(`.router-status-badge[data-router-id="${CSS.escape(routerId)}"]`);if(badge){badge.textContent=d.status||(ok?'ONLINE':'OFFLINE');badge.className='status-badge '+(ok?'online':'offline')+' router-status-badge'}}catch(e){if(result){result.className='router-connection-result text-danger';result.textContent='API error: '+e.message}}finally{test.disabled=false;test.innerHTML=old}}));
});
