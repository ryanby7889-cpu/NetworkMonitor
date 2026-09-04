<?php $activeMenu='pppoe'; ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PPPoE Management - NetMonitor</title>
<link rel="stylesheet" href="../assets/css/variables.css?v=8">
<link rel="stylesheet" href="../assets/css/common.css?v=8">
<link rel="stylesheet" href="../assets/css/theme.css?v=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/pppoe.css?v=13">
</head>
<body>
<?php
$activeMenu = 'pppoe';
require_once "../includes/sidebar.php";
?>

<main class="main-content pppoe-page">
  <div class="page-header pppoe-page-header">
    <div>
      <h1 class="page-title">PPPoE Management</h1>
      <div class="page-subtitle">Monitor dan kelola user PPPoE MikroTik</div>
    </div>
    <div class="pppoe-actions">
      <span id="connectionStatus" class="badge-status">● Checking...</span>
      <button id="refreshBtn" class="btn btn-primary">↻ Refresh</button>
    </div>
  </div>

  <section class="pppoe-stats" aria-label="Ringkasan PPPoE">
    <div class="pppoe-stat">
      <div class="pppoe-stat-icon">⌁</div>
      <div class="pppoe-stat-copy">
        <div class="pppoe-stat-label">Active Sessions</div>
        <div id="activeCount" class="pppoe-stat-value">0</div>
      </div>
    </div>
    <div class="pppoe-stat">
      <div class="pppoe-stat-icon">◉</div>
      <div class="pppoe-stat-copy">
        <div class="pppoe-stat-label">PPPoE Accounts</div>
        <div id="secretCount" class="pppoe-stat-value">0</div>
      </div>
    </div>
    <div class="pppoe-stat">
      <div class="pppoe-stat-icon">▤</div>
      <div class="pppoe-stat-copy">
        <div class="pppoe-stat-label">Profiles</div>
        <div id="profileCount" class="pppoe-stat-value">0</div>
      </div>
    </div>
  </section>

  <section class="card-modern pppoe-card">
    <div class="pppoe-card-header">
      <div class="pppoe-card-heading">
        <h2>Active PPPoE Sessions</h2>
        <small id="lastUpdate">Belum ada update</small>
      </div>
      <div class="pppoe-card-tools">
        <div class="pppoe-search-wrap">
          <input id="sessionSearch" class="form-control pppoe-search" placeholder="Cari username, IP, caller ID..." aria-label="Cari active session">
        </div>
      </div>
    </div>
    <div id="messageBox" class="alert-app pppoe-message" hidden></div>
    <div class="table-responsive">
      <table class="table-modern pppoe-table">
        <thead><tr><th>#</th><th>Username</th><th>IP Address</th><th>Caller ID</th><th>Uptime</th><th>Service</th><th>Action</th></tr></thead>
        <tbody id="activeTable"><tr><td colspan="7">Memuat data...</td></tr></tbody>
      </table>
    </div>
  </section>

  <section class="card-modern pppoe-card">
    <div class="pppoe-card-header">
      <div class="pppoe-card-heading">
        <h2>PPPoE Accounts</h2>
        <small>Kelola akun PPPoE langsung pada MikroTik</small>
      </div>
      <div class="pppoe-card-tools">
        <div class="pppoe-search-wrap">
          <input id="secretSearch" class="form-control pppoe-search" placeholder="Cari username / profile..." aria-label="Cari akun PPPoE">
        </div>
        <button type="button" class="btn btn-primary" id="addUserBtn">＋ Tambah User</button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table-modern pppoe-table">
        <thead><tr><th>#</th><th>Username</th><th>Service</th><th>Profile</th><th>Status</th><th>Local Address</th><th>Remote Address</th><th>Comment</th><th>Action</th></tr></thead>
        <tbody id="secretTable"><tr><td colspan="9">Memuat data...</td></tr></tbody>
      </table>
    </div>
  </section>

  <section class="card-modern pppoe-card">
    <div class="pppoe-card-header">
      <div class="pppoe-card-heading">
        <h2>PPPoE Profiles</h2>
        <small>Profile bandwidth dan policy koneksi PPPoE</small>
      </div>
      <div class="pppoe-card-tools">
        <button type="button" class="btn btn-primary" id="addProfileBtn">＋ Tambah Profile</button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table-modern pppoe-table">
        <thead><tr><th>#</th><th>Profile</th><th>Local Address</th><th>Remote Address</th><th>Rate Limit</th><th>Only One</th><th>Action</th></tr></thead>
        <tbody id="profileTable"><tr><td colspan="7">Memuat data...</td></tr></tbody>
      </table>
    </div>
  </section>

  <div class="pppoe-modal" id="userModal" hidden>
    <div class="pppoe-modal-backdrop" data-close-modal></div>
    <div class="pppoe-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
      <div class="pppoe-modal-header">
        <div><h2 id="userModalTitle">Tambah User PPPoE</h2><small>Buat account baru langsung di MikroTik</small></div>
        <button type="button" class="modal-close" data-close-modal>×</button>
      </div>
      <form id="userForm">
        <input type="hidden" name="id">
        <div class="form-grid">
          <label>Username<input class="form-control" name="name" maxlength="64" required autocomplete="off"></label>
          <label>Password<input class="form-control" name="password" maxlength="128" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah"></label>
          <label>Service<select class="form-control" name="service"><option value="pppoe">PPPoE</option><option value="any">Any</option></select></label>
          <label>Profile<select class="form-control" name="profile" id="profileSelect"><option value="default">default</option></select></label>
          <label>Local Address<input class="form-control" name="local_address" maxlength="64" placeholder="Contoh: 192.168.3.1"></label>
          <label>Remote Address<input class="form-control" name="remote_address" maxlength="64" placeholder="Contoh: 192.168.3.10"></label>
          <label class="form-full">Comment<input class="form-control" name="comment" maxlength="128" placeholder="Opsional"></label>
        </div>
        <div id="formMessage" class="alert-app pppoe-message" hidden></div>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
          <button type="submit" class="btn btn-primary" id="saveUserBtn">Simpan User</button>
        </div>
      </form>
    </div>
  </div>

  <div class="pppoe-modal" id="profileModal" hidden>
    <div class="pppoe-modal-backdrop" data-close-profile></div>
    <div class="pppoe-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
      <div class="pppoe-modal-header">
        <div><h2 id="profileModalTitle">Tambah Profile PPPoE</h2><small>Atur bandwidth dan parameter profile MikroTik</small></div>
        <button type="button" class="modal-close" data-close-profile>×</button>
      </div>
      <form id="profileForm">
        <input type="hidden" name="id">
        <div class="form-grid">
          <label>Nama Profile<input class="form-control" name="name" maxlength="64" required></label>
          <label>Rate Limit<input class="form-control" name="rate_limit" placeholder="Contoh: 10M/10M"></label>
          <label>Local Address<input class="form-control" name="local_address" placeholder="Opsional"></label>
          <label>Remote Address<input class="form-control" name="remote_address" placeholder="Opsional"></label>
          <label>Only One<select class="form-control" name="only_one"><option value="">Default</option><option value="yes">Yes</option><option value="no">No</option></select></label>
          <label>Comment<input class="form-control" name="comment" maxlength="128"></label>
        </div>
        <div id="profileFormMessage" class="alert-app pppoe-message" hidden></div>
        <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close-profile>Batal</button><button type="submit" class="btn btn-primary" id="saveProfileBtn">Simpan Profile</button></div>
      </form>
    </div>
  </div>
</main>

<script>
(()=>{
const api='../api/pppoe.php';
let active=[],secrets=[],profiles=[];
const $=x=>document.getElementById(x);
const esc=v=>{let d=document.createElement('div');d.textContent=v??'-';return d.innerHTML};

async function get(a){
  let r=await fetch(api+'?action='+encodeURIComponent(a)+'&t='+Date.now(),{cache:'no-store'});
  let d=await r.json();
  if(!r.ok||!d.success)throw Error(d.message||'Request gagal');
  return d;
}
function message(t,e=false){
  let b=$('messageBox');b.textContent=t;b.hidden=!t;b.classList.toggle('error',e);
}
function render(){
  let q=$('sessionSearch').value.toLowerCase();
  let a=active.filter(x=>[x.name,x.address,x.caller_id].join(' ').toLowerCase().includes(q));
  $('activeTable').innerHTML=a.length?a.map((x,i)=>`<tr><td>${i+1}</td><td><span class="pppoe-user-name"><span class="pppoe-user-dot"></span>${esc(x.name)}</span></td><td class="pppoe-mono">${esc(x.address)}</td><td class="pppoe-mono">${esc(x.caller_id)}</td><td>${esc(x.uptime)}</td><td>${esc(x.service)}</td><td><button class="btn btn-danger btn-small" data-disconnect="${esc(x.id)}">Disconnect</button></td></tr>`).join(''):'<tr><td colspan="7" class="pppoe-table-empty">Tidak ada active session.</td></tr>';

  q=$('secretSearch').value.toLowerCase();
  let s=secrets.filter(x=>[x.name,x.profile,x.service,x.local_address,x.remote_address,x.comment].join(' ').toLowerCase().includes(q));
  $('secretTable').innerHTML=s.length?s.map((x,i)=>`<tr><td>${i+1}</td><td><span class="pppoe-user-name"><span class="pppoe-user-dot"></span>${esc(x.name)}</span></td><td>${esc(x.service)}</td><td><span class="pppoe-profile-pill">${esc(x.profile)}</span></td><td><span class="status-pill ${x.disabled?'disabled':'enabled'}">${x.disabled?'DISABLED':'ENABLED'}</span></td><td class="pppoe-mono">${esc(x.local_address)}</td><td class="pppoe-mono">${esc(x.remote_address)}</td><td class="pppoe-muted">${esc(x.comment)}</td><td><span class="action-group"><button class="btn btn-small btn-primary" data-edit-secret="${esc(x.id)}">Edit</button><button class="btn btn-small ${x.disabled?'btn-success':'btn-warning'}" data-toggle="${esc(x.id)}" data-disabled="${x.disabled?1:0}">${x.disabled?'Enable':'Disable'}</button><button class="btn btn-danger btn-small" data-remove="${esc(x.id)}">Delete</button></span></td></tr>`).join(''):'<tr><td colspan="9" class="pppoe-table-empty">Tidak ada account PPPoE.</td></tr>';

  $('profileTable').innerHTML=profiles.length?profiles.map((x,i)=>`<tr><td>${i+1}</td><td><span class="pppoe-profile-pill">${esc(x.name)}</span></td><td class="pppoe-mono">${esc(x.local_address)}</td><td class="pppoe-mono">${esc(x.remote_address)}</td><td><strong>${esc(x.rate_limit||'-')}</strong></td><td>${esc(x.only_one||'default')}</td><td><span class="action-group"><button class="btn btn-small btn-primary" data-edit-profile="${esc(x.id)}">Edit</button><button class="btn btn-danger btn-small" data-remove-profile="${esc(x.id)}">Delete</button></span></td></tr>`).join(''):'<tr><td colspan="7" class="pppoe-table-empty">Tidak ada profile PPPoE.</td></tr>';
}
async function refresh(){
  try{
    let [a,s,p]=await Promise.all([get('active'),get('secrets'),get('profiles')]);
    active=a.users||[];secrets=s.secrets||[];profiles=p.profiles||[];
    $('activeCount').textContent=active.length;$('secretCount').textContent=secrets.length;$('profileCount').textContent=profiles.length;
    $('connectionStatus').textContent='● ONLINE';$('connectionStatus').className='badge-status status-online';
    $('lastUpdate').textContent='Last update: '+new Date().toLocaleTimeString('id-ID');
    message('');render();
  }catch(e){
    $('connectionStatus').textContent='● OFFLINE';$('connectionStatus').className='badge-status status-offline';message(e.message,true);
  }
}
async function act(a,id){
  let r=await fetch(api+'?action='+encodeURIComponent(a),{method:'POST',body:new URLSearchParams({id})});
  let d=await r.json();
  if(!r.ok||!d.success)throw Error(d.message||'Action gagal');
  message(d.message);await refresh();
}
function cleanOptional(v){
  v=String(v??'').trim();
  return ['','optional','opsional','-','none'].includes(v.toLowerCase())?'':v;
}
function fillProfileOptions(select,current='default'){
  select.innerHTML=profiles.map(x=>`<option value="${esc(x.name)}">${esc(x.name)}</option>`).join('')||'<option value="default">default</option>';
  select.value=current;if(!select.value)select.value='default';
}
function openUser(mode='add',item=null){
  let f=$('userForm'),fm=$('formMessage');
  f.reset();fm.hidden=true;fm.classList.remove('error');
  $('userModalTitle').textContent=mode==='edit'?'Edit User PPPoE':'Tambah User PPPoE';
  $('saveUserBtn').textContent=mode==='edit'?'Simpan Perubahan':'Simpan User';
  f.elements.id.value=item?.id||'';f.elements.name.value=item?.name||'';f.elements.password.value='';
  f.elements.service.value=item?.service||'pppoe';
  fillProfileOptions($('profileSelect'),item?.profile||'default');
  f.elements.local_address.value=item?.local_address||'';
  f.elements.remote_address.value=item?.remote_address||'';
  f.elements.comment.value=item?.comment||'';
  $('userModal').hidden=false;f.elements.name.focus();
}
function closeUser(){$('userModal').hidden=true}
function openProfile(mode='add',item=null){
  let f=$('profileForm'),fm=$('profileFormMessage');
  f.reset();fm.hidden=true;fm.classList.remove('error');
  $('profileModalTitle').textContent=mode==='edit'?'Edit Profile PPPoE':'Tambah Profile PPPoE';
  $('saveProfileBtn').textContent=mode==='edit'?'Simpan Perubahan':'Simpan Profile';
  f.elements.id.value=item?.id||'';f.elements.name.value=item?.name||'';f.elements.rate_limit.value=item?.rate_limit||'';
  f.elements.local_address.value=item?.local_address||'';f.elements.remote_address.value=item?.remote_address||'';
  f.elements.only_one.value=item?.only_one||'';f.elements.comment.value=item?.comment||'';
  $('profileModal').hidden=false;f.elements.name.focus();
}
function closeProfile(){$('profileModal').hidden=true}

$('addUserBtn').onclick=()=>openUser('add');
$('addProfileBtn').onclick=()=>openProfile('add');
document.querySelectorAll('[data-close-modal]').forEach(x=>x.onclick=closeUser);
document.querySelectorAll('[data-close-profile]').forEach(x=>x.onclick=closeProfile);

document.addEventListener('click',e=>{
  let d=e.target.closest('[data-disconnect]'),t=e.target.closest('[data-toggle]'),x=e.target.closest('[data-remove]');
  let es=e.target.closest('[data-edit-secret]'),ep=e.target.closest('[data-edit-profile]'),rp=e.target.closest('[data-remove-profile]');
  (async()=>{
    try{
      if(es){let item=secrets.find(v=>v.id===es.dataset.editSecret);if(item)openUser('edit',item)}
      else if(ep){let item=profiles.find(v=>v.id===ep.dataset.editProfile);if(item)openProfile('edit',item)}
      else if(d&&confirm('Putus session PPPoE ini?'))await act('disconnect',d.dataset.disconnect);
      else if(t)await act(t.dataset.disabled==='1'?'enable_secret':'disable_secret',t.dataset.toggle);
      else if(x&&confirm('Hapus user PPPoE ini dari MikroTik?'))await act('remove_secret',x.dataset.remove);
      else if(rp&&confirm('Hapus profile PPPoE ini dari MikroTik?'))await act('remove_profile',rp.dataset.removeProfile);
    }catch(err){message(err.message,true)}
  })();
});

$('userForm').onsubmit=async e=>{
  e.preventDefault();
  let f=e.currentTarget,btn=$('saveUserBtn'),fm=$('formMessage');
  btn.disabled=true;fm.hidden=true;fm.classList.remove('error');
  try{
    let action=f.elements.id.value?'edit_secret':'create_secret';
    let data=new FormData(f);
    ['local_address','remote_address'].forEach(k=>{
      let v=cleanOptional(data.get(k));if(v)data.set(k,v);else data.delete(k);
    });
    if(action==='edit_secret'&&!f.elements.password.value)data.delete('password');
    let r=await fetch(api+'?action='+action,{method:'POST',body:new URLSearchParams(data)});
    let d=await r.json();
    if(!r.ok||!d.success)throw Error(d.message||'Gagal menyimpan user');
    closeUser();message(d.message);await refresh();
  }catch(err){fm.textContent=err.message;fm.classList.add('error');fm.hidden=false}
  finally{btn.disabled=false}
};

$('profileForm').onsubmit=async e=>{
  e.preventDefault();
  let f=e.currentTarget,btn=$('saveProfileBtn'),fm=$('profileFormMessage');
  btn.disabled=true;fm.hidden=true;fm.classList.remove('error');
  try{
    let action=f.elements.id.value?'edit_profile':'create_profile';
    let data=new FormData(f);
    ['local_address','remote_address'].forEach(k=>{
      let v=cleanOptional(data.get(k));if(v)data.set(k,v);else data.delete(k);
    });
    let r=await fetch(api+'?action='+action,{method:'POST',body:new URLSearchParams(data)});
    let d=await r.json();
    if(!r.ok||!d.success)throw Error(d.message||'Gagal menyimpan profile');
    closeProfile();message(d.message);await refresh();
  }catch(err){fm.textContent=err.message;fm.classList.add('error');fm.hidden=false}
  finally{btn.disabled=false}
};

$('refreshBtn').onclick=refresh;
$('sessionSearch').oninput=render;
$('secretSearch').oninput=render;
refresh();
setInterval(refresh,10000);
})();
</script>
    <script src="../assets/js/app.js?v=1"></script>
</body>
</html>
