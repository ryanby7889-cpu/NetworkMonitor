const api='../api/hotspot.php';const billingApi='../api/hotspot_billing.php';const billingLinkApi='../api/hotspot_billing_link.php';
const $=id=>document.getElementById(id);
let users=[],active=[],profiles=[],lastTraffic=[],currentBillingUsername='',billingRows={},hotspotCustomers=[];

function formatBytes(v){let n=Number(v)||0;if(n<1024)return n+' B';const u=['KB','MB','GB','TB'];let i=-1;do{n/=1024;i++}while(n>=1024&&i<u.length-1);return n.toFixed(n>=100?0:1)+' '+u[i]}
function esc(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML}
function msg(text,error=false){const e=$('message');e.textContent=text;e.hidden=!text;e.classList.toggle('error',error)}
function fmsg(text,error=false){const e=$('formMessage');e.textContent=text;e.hidden=!text;e.classList.toggle('error',error)}
async function get(action){
 const r=await fetch(api+'?action='+encodeURIComponent(action)+'&t='+Date.now(),{cache:'no-store'});
 const text=await r.text();
 let d;
 try{d=JSON.parse(text)}catch(e){throw Error('API Hotspot mengembalikan respons tidak valid (HTTP '+r.status+')')}
 if(!r.ok||!d.success)throw Error(d.message||('Request gagal (HTTP '+r.status+')'));
 return d;
}
async function post(action,data){
 const r=await fetch(api+'?action='+encodeURIComponent(action),{method:'POST',body:new URLSearchParams(data)});
 const text=await r.text();
 let d;try{d=JSON.parse(text)}catch(e){throw Error('API Hotspot mengembalikan respons tidak valid (HTTP '+r.status+')')}
 if(!r.ok||!d.success)throw Error(d.message||('Action gagal (HTTP '+r.status+')'));
 return d;
}
function traffic(x){return `${esc(x.bytes_in||'0')} / ${esc(x.bytes_out||'0')}`}
function normalizeUser(name){return String(name??'').trim().toLowerCase()}
function getOnlineSet(){const set=new Set();active.forEach(x=>{const n=normalizeUser(x.user);if(n)set.add(n)});return set}
function userOnline(name){return getOnlineSet().has(normalizeUser(name))}
async function loadBillingMap(){
 billingRows={};
 try{
  const r=await fetch('../api/hotspot_billing_map.php?t='+Date.now(),{cache:'no-store'});
  const text=await r.text();let d;try{d=JSON.parse(text)}catch(e){return}
  if(d.success) (d.rows||[]).forEach(x=>billingRows[normalizeUser(x.hotspot_username)]=x);
 }catch(e){}
}

async function loadHotspotCustomers(){
 try{
  const r=await fetch('../api/hotspot_customers.php?t='+Date.now(),{cache:'no-store'});
  const text=await r.text();let d;try{d=JSON.parse(text)}catch(e){return}
  if(d.success){hotspotCustomers=d.rows||[];renderHotspotCustomers();}
 }catch(e){}
}
function renderHotspotCustomers(){
 const q=($('hotspotCustomerSearch')?.value||'').toLowerCase().trim();
 const rows=hotspotCustomers.filter(x=>
   String(x.hotspot_username||'').toLowerCase().includes(q)||
   String(x.name||'').toLowerCase().includes(q)||
   String(x.package_name||'').toLowerCase().includes(q));
 $('hotspotCustomerTable').innerHTML=rows.map((x,i)=>{
   const live=active.some(a=>normalizeUser(a.user)===normalizeUser(x.hotspot_username));
   const bill=String(x.status||'').toLowerCase()==='active';
   const inv=String(x.invoice_status||'').toLowerCase();
   const invText=inv==='paid'?'LUNAS':(inv==='unpaid'?'BELUM BAYAR':(x.invoice_status||'-').toUpperCase());
   return `<tr><td>${i+1}</td><td><strong>${esc(x.hotspot_username)}</strong></td><td>${esc(x.name||'-')}</td><td>${esc(x.package_name||'-')}</td><td><span class="hs-billing-badge ${bill?'linked':'unlinked'}">${bill?'AKTIF':'NONAKTIF'}</span><small class="hs-billing-name">${esc(invText)}</small></td><td><span class="hs-status ${live?'online':'offline'}">${live?'ONLINE':'OFFLINE'}</span></td><td><button type="button" class="hs-btn" data-customer-detail="${esc(x.hotspot_username)}">Detail</button></td></tr>`;
 }).join('')||'<tr><td colspan="7">Belum ada pelanggan Hotspot yang terhubung.</td></tr>';
}

function renderUsers(){
 const q=($('userSearch')?.value||'').toLowerCase();
 const rows=users.filter(x=>(x.name||'').toLowerCase().includes(q)||(x.profile||'').toLowerCase().includes(q));
 $('userTable').innerHTML=rows.map((x,i)=>{
  const b=billingRows[normalizeUser(x.name)];
  const bill=b
   ? `<span class="hs-billing-badge linked">TERHUBUNG</span><small class="hs-billing-name">${esc(b.customer_name||'-')}</small>`
   : `<span class="hs-billing-badge unlinked">BELUM TERHUBUNG</span>`;
  return `<tr><td>${i+1}</td><td><strong>${esc(x.name)}</strong></td><td>${esc(x.profile||'-')}</td><td><span class="hs-status enabled">ENABLED</span>${x.disabled?'<span class="hs-status disabled">DISABLED</span>':''}${x.online?'<span class="hs-status online">ONLINE</span>':'<span class="hs-status offline">OFFLINE</span>'}</td><td>${bill}</td><td>${esc(x.limit_uptime||'-')}</td><td>${esc(x.traffic||'0 / 0')}</td><td>${esc(x.comment||'-')}</td><td><button type="button" class="hs-btn" data-detail="${esc(x.id)}">Detail</button> <button type="button" class="hs-btn" data-edit="${esc(x.id)}">Edit</button> <button type="button" class="hs-btn warning" data-toggle="${esc(x.id)}" data-disabled="${x.disabled?1:0}">${x.disabled?'Enable':'Disable'}</button> <button type="button" class="hs-btn danger" data-remove="${esc(x.id)}">Hapus</button></td></tr>`;
 }).join('')||'<tr><td colspan="9">Tidak ada data.</td></tr>';
}

function renderActive(){
 const q=$('activeSearch').value.toLowerCase();
 const rows=active.filter(x=>[x.user,x.address,x.mac_address].join(' ').toLowerCase().includes(q));
 $('activeTable').innerHTML=rows.length?rows.map((x,i)=>`<tr><td>${i+1}</td><td><strong>${esc(x.user)}</strong></td><td>${esc(x.address)}</td><td>${esc(x.mac_address)}</td><td>${esc(x.login_by)}</td><td>${esc(x.uptime)}</td><td>${traffic(x)}</td><td><button type="button" class="hs-btn danger" data-disconnect="${esc(x.id)}">Disconnect</button></td></tr>`).join(''):'<tr><td colspan="8">Tidak ada active session.</td></tr>';
}

function renderTraffic(data){
 lastTraffic=[...(data||[])];
 const rows=[...(data||[])];
 const q=normalizeUser($('trafficSearch')?.value||'');
 const sort=$('trafficSort')?.value||'rx';
 const filtered=rows.filter(x=>[x.user,x.address,x.mac_address].join(' ').toLowerCase().includes(q));
 filtered.sort((a,b)=>{
   if(sort==='name') return normalizeUser(a.user).localeCompare(normalizeUser(b.user));
   const ar=Number(a.bytes_in)||0,at=Number(a.bytes_out)||0,br=Number(b.bytes_in)||0,bt=Number(b.bytes_out)||0;
   return sort==='tx'?bt-at:sort==='total'?(bt+br)-(at+ar):br-ar;
 });
 $('trafficTable').innerHTML=filtered.length?filtered.map((x,i)=>`<tr><td>${i+1}</td><td><strong>${esc(x.user)}</strong></td><td>${esc(x.address)}</td><td>${esc(x.uptime)}</td><td>${formatBytes(x.bytes_in)}</td><td>${formatBytes(x.bytes_out)}</td></tr>`).join(''):'<tr><td colspan="6">Tidak ada active session.</td></tr>';
}

function renderProfiles(){
 $('profileTable').innerHTML=profiles.length?profiles.map((x,i)=>`<tr><td>${i+1}</td><td><strong>${esc(x.name)}</strong></td><td>${esc(x.rate_limit||'-')}</td><td>${esc(x.shared_users||'-')}</td><td>${esc(x.session_timeout||'-')}</td><td>${esc(x.idle_timeout||'-')}</td><td><button type="button" class="hs-btn" data-profile-edit="${esc(x.id)}">Edit</button> <button type="button" class="hs-btn danger" data-profile-remove="${esc(x.id)}">Hapus</button></td></tr>`).join(''):'<tr><td colspan="8">Tidak ada profile.</td></tr>';
}


async function getBilling(username){
 const r=await fetch(billingApi+'?username='+encodeURIComponent(username)+'&t='+Date.now(),{cache:'no-store'});
 const text=await r.text();
 let d;
 try{d=JSON.parse(text)}catch(e){throw Error('Respons Billing tidak valid (HTTP '+r.status+')')}
 if(!r.ok||!d.success)throw Error(d.message||('Gagal membaca Billing (HTTP '+r.status+')'));
 return d;
}

async function billingLinkGet(action,username=''){
 const url=billingLinkApi+'?action='+encodeURIComponent(action)+(username?'&username='+encodeURIComponent(username):'')+'&t='+Date.now();
 const r=await fetch(url,{cache:'no-store'});const text=await r.text();let d;
 try{d=JSON.parse(text)}catch(e){throw Error('Respons hubungan Billing tidak valid (HTTP '+r.status+')')}
 if(!r.ok||!d.success)throw Error(d.message||('Gagal membaca hubungan Billing (HTTP '+r.status+')'));
 return d;
}
async function billingLinkPost(action,data){
 const r=await fetch(billingLinkApi+'?action='+encodeURIComponent(action),{method:'POST',body:new URLSearchParams(data)});
 const text=await r.text();let d;
 try{d=JSON.parse(text)}catch(e){throw Error('Respons hubungan Billing tidak valid (HTTP '+r.status+')')}
 if(!r.ok||!d.success)throw Error(d.message||('Gagal menyimpan hubungan Billing (HTTP '+r.status+')'));
 return d;
}

async function showBillingLinkModal(username){
 currentBillingUsername=username;
 const m=$('billingLinkModal'),sel=$('billingCustomerSelect'),box=$('billingLinkMessage');
 $('linkHotspotName').textContent=username;box.hidden=true;box.textContent='';
 sel.innerHTML='<option value="">Memuat pelanggan...</option>';
 m.hidden=false;m.style.setProperty('display','flex','important');m.style.visibility='visible';m.style.pointerEvents='auto';
 try{
  const list=await billingLinkGet('customers');
  const linked=await billingLinkGet('lookup',username);
  sel.innerHTML='<option value="">-- Pilih pelanggan --</option>'+(list.customers||[]).map(x=>`<option value="${esc(x.id)}">${esc(x.name)} — ${esc(x.pppoe_username||'-')} — ${esc(x.status||'-')}</option>`).join('');
  if(linked.linked)sel.value=String(linked.link.customer_id);
 }catch(e){sel.innerHTML='<option value="">Gagal memuat pelanggan</option>';box.hidden=false;box.textContent=e.message}
}
function closeBillingLinkModal(){
 const m=$('billingLinkModal');m.hidden=true;m.style.setProperty('display','none','important');m.style.visibility='hidden';m.style.pointerEvents='none';
}

function showDetailModal(){const m=$('detailModal');m.hidden=false;m.style.setProperty('display','flex','important');m.style.visibility='visible';m.style.pointerEvents='auto'}
function closeDetail(){const m=$('detailModal');m.hidden=true;m.style.setProperty('display','none','important');m.style.visibility='hidden';m.style.pointerEvents='none'}
async function openDetail(item){
 const sessions=active.filter(x=>normalizeUser(x.user)===normalizeUser(item.name));
 const first=sessions[0]||null;
 $('detailSubtitle').textContent=first?`${sessions.length} active session`:'Tidak ada active session';
 $('detailName').textContent=item.name||'-';
 $('detailProfile').textContent=item.profile||'-';
 $('detailAccount').textContent=item.disabled?'DISABLED':'ENABLED';
 $('detailLive').textContent=first?'ONLINE':'OFFLINE';
 $('detailIp').textContent=first?.address||'-';
 $('detailMac').textContent=first?.mac_address||'-';
 $('detailLogin').textContent=first?.login_by||'-';
 $('detailUptime').textContent=first?.uptime||'-';
 $('detailTraffic').textContent=first?`${first.bytes_in||'0'} / ${first.bytes_out||'0'}`:'-';
 $('detailLimitUptime').textContent=item.limit_uptime||'-';
 $('detailLimitIn').textContent=item.limit_bytes_in||'-';
 $('detailLimitOut').textContent=item.limit_bytes_out||'-';
 $('detailComment').textContent=item.comment||'-';

 const bill=$('detailBilling'); $('linkBillingBtn').dataset.username=item.name;$('unlinkBillingBtn').dataset.username=item.name;$('linkBillingBtn').hidden=false;$('unlinkBillingBtn').hidden=true;
 bill.innerHTML='<span>Status Billing</span><strong>Memuat...</strong>';
 showDetailModal();

 try{
   const mapped=await billingLinkGet('lookup',item.name); const b=mapped.linked ? {linked:true,customer:mapped.link,invoices:[]} : await getBilling(item.name);
   if(!b.linked){
     bill.innerHTML='<span>Status Billing</span><strong>Belum terhubung</strong><small>Username Hotspot ini belum terhubung ke pelanggan Billing.</small>';$('linkBillingBtn').textContent='Hubungkan ke Pelanggan';$('linkBillingBtn').hidden=false;$('unlinkBillingBtn').hidden=true;
     return;
   }
   const customer=b.customer||{};
   const invoices=b.invoices||[];
   const unpaid=invoices.find(x=>String(x.status).toLowerCase()==='unpaid');
   const customerStatus=String(customer.status||'').toLowerCase();
   const label=customerStatus==='active'?'AKTIF':(customer.status||'-').toUpperCase();
   const invoiceText=unpaid
      ? `Ada tagihan belum bayar: ${esc(unpaid.invoice_no||'-')}`
      : 'Tidak ada tagihan belum bayar pada 5 invoice terakhir';
   bill.innerHTML=`<span>Status Billing</span><strong>${esc(label)} — ${esc(customer.name||'-')}</strong><small>Paket: ${esc(customer.package_name||'-')} · ${invoiceText}</small>`;$('linkBillingBtn').textContent='Ubah Pelanggan';$('unlinkBillingBtn').hidden=false;
 }catch(e){
   bill.innerHTML='<span>Status Billing</span><strong>Tidak dapat dibaca</strong><small>'+esc(e.message)+'</small>';
 }
}
function showProfileModal(){
 const m=$('profileModal');m.hidden=false;m.style.setProperty('display','flex','important');m.style.visibility='visible';m.style.pointerEvents='auto';
}
function closeProfile(){
 const m=$('profileModal');m.hidden=true;m.style.setProperty('display','none','important');m.style.visibility='hidden';m.style.pointerEvents='none';
}
function openProfile(item=null){
 const f=$('profileForm');f.reset();
 f.elements.id.value=item?.id||'';
 f.elements.name.value=item?.name||'';
 f.elements.rate_limit.value=item?.rate_limit||'';
 f.elements.shared_users.value=item?.shared_users||'1';
 f.elements.session_timeout.value=item?.session_timeout||'';
 f.elements.idle_timeout.value=item?.idle_timeout||'';
 f.elements.keepalive_timeout.value=item?.keepalive_timeout||'';
 f.elements.status_autorefresh.value=item?.status_autorefresh||'';
 f.elements.transparent_proxy.value=item?.transparent_proxy||'';

 $('profileModalTitle').textContent=item?'Edit Profile Hotspot':'Tambah Profile Hotspot';
 $('profileFormMessage').hidden=true;showProfileModal();
}

function fillProfiles(current='default'){ $('profileSelect').innerHTML=profiles.map(x=>`<option value="${esc(x.name)}">${esc(x.name)}</option>`).join('')||'<option value="default">default</option>'; $('profileSelect').value=current;if(!$('profileSelect').value)$('profileSelect').value='default';}
function showUserModal(){const m=$('userModal');m.hidden=false;m.style.setProperty('display','flex','important');m.style.visibility='visible';m.style.pointerEvents='auto'}
function closeUser(){const m=$('userModal');m.hidden=true;m.style.setProperty('display','none','important');m.style.visibility='hidden';m.style.pointerEvents='none'}
function openUser(item=null){
 const f=$('userForm');f.reset();f.elements.id.value=item?.id||'';f.elements.name.value=item?.name||'';f.elements.password.value='';f.elements.server.value=item?.server||'';f.elements.limit_uptime.value=item?.limit_uptime||'';f.elements.limit_bytes_in.value=item?.limit_bytes_in||'';f.elements.limit_bytes_out.value=item?.limit_bytes_out||'';f.elements.comment.value=item?.comment||'';fillProfiles(item?.profile||'default');$('modalTitle').textContent=item?'Edit User Hotspot':'Tambah User Hotspot';fmsg('');showUserModal();
}
function setConnectionStatus(state,message){
 const el=$('mikrotikConnectionStatus'),tm=$('lastRefreshTime'); if(!el)return;
 el.className='hs-connection '+state;el.textContent=message;
 if(tm)tm.textContent='Update: '+new Date().toLocaleTimeString('id-ID');
}

async function refresh(){
 let ok=0, lastError='';
 try{
   const s=await get('summary');
   users.length = users.length; // preserve arrays until detailed data arrives
   $('usersTotal').textContent=s.users_total??users.length;
   $('usersEnabled').textContent=s.users_enabled??0;
   $('profilesTotal').textContent=s.profiles_total??profiles.length;
   $('activeTotal').textContent=s.active_total??active.length;
   ok++;
 }catch(e){lastError=e.message}

 try{await new Promise(r=>setTimeout(r,80));const u=await get('users');users=u.users||[];ok++;}catch(e){lastError=e.message}
 try{await new Promise(r=>setTimeout(r,80));const a=await get('active');active=a.active||[];ok++;}catch(e){lastError=e.message}
 try{await new Promise(r=>setTimeout(r,80));const p=await get('profiles');profiles=p.profiles||[];ok++;}catch(e){lastError=e.message}
 try{
   await new Promise(r=>setTimeout(r,80));
   const t=await get('traffic');
   lastTraffic=t.traffic||active;
   $('trafficIn').textContent=formatBytes(t.total_bytes_in);
   $('trafficOut').textContent=formatBytes(t.total_bytes_out);
   const peak=[...lastTraffic].sort((a,b)=>((Number(b.bytes_in)||0)+(Number(b.bytes_out)||0))-((Number(a.bytes_in)||0)+(Number(a.bytes_out)||0)))[0];
   $('trafficPeak').textContent=peak?`${peak.user} (${formatBytes((Number(peak.bytes_in)||0)+(Number(peak.bytes_out)||0))})`:'-';
   ok++;
 setConnectionStatus('online','MikroTik TERHUBUNG');
}catch(e){lastError=e.message}

 const onlineSet=getOnlineSet();
 $('usersTotal').textContent=users.length||$('usersTotal').textContent||'0';
 $('usersEnabled').textContent=users.filter(x=>!x.disabled).length;
 $('usersOnline').textContent=onlineSet.size;
 $('profilesTotal').textContent=profiles.length;
 $('activeTotal').textContent=active.length;
 renderTraffic(lastTraffic);await loadBillingMap();renderUsers();renderActive();renderProfiles();
await loadHotspotCustomers();

 if(ok>0){msg(lastError&&ok<5?'Sebagian data diperbarui. '+lastError:'');}
 else{msg('Gagal terhubung ke MikroTik. Periksa koneksi/API Router.',true);}
}
function bindUserActions(){
 const table=$('userTable'); if(!table)return;
 table.onclick=async e=>{
  const b=e.target.closest('button'); if(!b)return;
  e.preventDefault();
  const id=b.dataset.detail||b.dataset.edit||b.dataset.toggle||b.dataset.remove;
  if(!id)return;
  const item=users.find(x=>String(x.id)===String(id));
  try{
   if(b.dataset.detail){
    if(!item)throw Error('Data user tidak ditemukan. Silakan Refresh.');
    await openDetail(item); return;
   }
   if(b.dataset.edit){
    if(!item)throw Error('Data user tidak ditemukan. Silakan Refresh.');
    openUser(item); return;
   }
   if(b.dataset.toggle){
    const disabled=String(b.dataset.disabled)==='1';
    await post('toggle_user',{id:id,disabled:disabled?0:1});
    msg(disabled?'User berhasil di-enable.':'User berhasil di-disable.');
    await refresh(); return;
   }
   if(b.dataset.remove){
    if(!confirm('Hapus user Hotspot '+(item?.name||'ini')+'?'))return;
    await post('remove_user',{id:id});
    msg('User Hotspot berhasil dihapus.');
    await refresh(); return;
   }
  }catch(err){msg(err.message||'Aksi User Hotspot gagal.',true)}
 };
}

function bindHotspotUI(){
 bindUserActions();
 if($('hotspotCustomerSearch'))$('hotspotCustomerSearch').oninput=renderHotspotCustomers;
 if($('linkBillingBtn'))$('linkBillingBtn').onclick=()=>showBillingLinkModal($('linkBillingBtn').dataset.username);
 if($('unlinkBillingBtn'))$('unlinkBillingBtn').onclick=async()=>{
  const u=$('unlinkBillingBtn').dataset.username;
  if(!u||!confirm('Lepas hubungan Billing untuk '+u+'?'))return;
  try{await billingLinkPost('unlink',{hotspot_username:u});await openDetail(users.find(x=>normalizeUser(x.name)===normalizeUser(u)));await refresh()}catch(e){alert(e.message)}
 };
 if($('closeBillingLinkModal'))$('closeBillingLinkModal').onclick=closeBillingLinkModal;
 if($('cancelBillingLinkBtn'))$('cancelBillingLinkBtn').onclick=closeBillingLinkModal;
 if($('billingLinkForm'))$('billingLinkForm').onsubmit=async e=>{
  e.preventDefault();const u=currentBillingUsername,cid=$('billingCustomerSelect').value;
  if(!u||!cid)return;
  const btn=$('saveBillingLinkBtn'),box=$('billingLinkMessage');btn.disabled=true;box.hidden=true;
  try{await billingLinkPost('link',{hotspot_username:u,customer_id:cid});closeBillingLinkModal();await openDetail(users.find(x=>normalizeUser(x.name)===normalizeUser(u)));await refresh()}
  catch(err){box.hidden=false;box.textContent=err.message}
  finally{btn.disabled=false}
 };
 if($('refreshBtn'))$('refreshBtn').onclick=refresh;
 if($('trafficSearch'))$('trafficSearch').oninput=()=>renderTraffic(lastTraffic);
 if($('trafficSort'))$('trafficSort').onchange=()=>renderTraffic(lastTraffic);
 if($('userSearch'))$('userSearch').oninput=renderUsers;
 if($('activeSearch'))$('activeSearch').oninput=renderActive;
 if($('addUserBtn'))$('addUserBtn').onclick=()=>openUser();
 if($('closeModal'))$('closeModal').onclick=closeUser;
 if($('cancelBtn'))$('cancelBtn').onclick=closeUser;
 if($('addProfileBtn'))$('addProfileBtn').onclick=()=>openProfile();
 if($('closeProfileModal'))$('closeProfileModal').onclick=closeProfile;
 if($('cancelProfileBtn'))$('cancelProfileBtn').onclick=closeProfile;
 if($('closeDetailModal'))$('closeDetailModal').onclick=closeDetail;
 if($('closeDetailBtn'))$('closeDetailBtn').onclick=closeDetail;
 if($('userForm'))$('userForm').onsubmit=async e=>{e.preventDefault();fmsg('');const fd=new FormData(e.currentTarget);const id=fd.get('id');try{await post(id?'edit_user':'create_user',Object.fromEntries(fd.entries()));closeUser();msg(id?'User berhasil diubah.':'User berhasil ditambahkan.');await refresh()}catch(err){fmsg(err.message,true)}};
 if($('profileForm'))$('profileForm').onsubmit=async e=>{e.preventDefault();const fd=new FormData(e.currentTarget),id=fd.get('id'),fm=$('profileFormMessage');fm.hidden=true;try{await post(id?'edit_profile':'create_profile',Object.fromEntries(fd.entries()));closeProfile();msg(id?'Profile berhasil diubah.':'Profile berhasil ditambahkan.');await refresh()}catch(err){fm.textContent=err.message;fm.hidden=false}};
 if($('profileTable'))$('profileTable').onclick=async e=>{const edit=e.target.closest('[data-profile-edit]'),rem=e.target.closest('[data-profile-remove]');if(edit){const x=profiles.find(v=>String(v.id)===edit.dataset.profileEdit);if(x)openProfile(x)}else if(rem){if(!confirm('Hapus profile Hotspot ini?'))return;try{await post('remove_profile',{id:rem.dataset.profileRemove});msg('Profile berhasil dihapus.');await refresh()}catch(err){msg(err.message,true)}}};
 if($('activeTable'))$('activeTable').onclick=async e=>{const b=e.target.closest('[data-disconnect]');if(!b)return;if(!confirm('Putus session Hotspot ini?'))return;try{await post('disconnect',{id:b.dataset.disconnect});msg('Session berhasil diputus.');await refresh()}catch(err){msg(err.message,true)}};
}
document.addEventListener('DOMContentLoaded',async()=>{
 bindHotspotUI();
 await refresh();
});

setInterval(refresh,10000);


