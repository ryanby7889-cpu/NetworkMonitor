/* NetMonitor PPPoE Management PRO — pagination, CRUD modals and safe actions */
(function(){
'use strict';
if(!location.pathname.toLowerCase().includes('/pppoe/')) return;
const api='../api/pppoe.php';
const $=id=>document.getElementById(id);
const state={active:[],secrets:[],profiles:[],activePage:1,secretPage:1,profilePage:1,perPage:10};
const esc=v=>{const d=document.createElement('div');d.textContent=v??'';return d.innerHTML};
async function request(action, data){
 const opt={method:data?'POST':'GET',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}};
 if(data) opt.body=data;
 const r=await fetch(api+'?action='+encodeURIComponent(action)+'&t='+Date.now(),opt);
 let d={}; try{d=await r.json()}catch(e){}
 if(!r.ok||!d.success) throw Error(d.message||'Request gagal');
 return d;
}
function pager(id,page,total,cb){
 const pages=Math.max(1,Math.ceil(total/state.perPage));
 page=Math.min(page,pages);
 let el=$(id); if(!el)return;
 el.innerHTML=`<div class="ppp-mgmt-pager"><span>${total} data • Halaman ${page}/${pages}</span><div><button type="button" class="btn btn-small btn-secondary" data-page="prev" ${page<=1?'disabled':''}>‹</button><button type="button" class="btn btn-small btn-secondary" data-page="next" ${page>=pages?'disabled':''}>›</button></div></div>`;
 el.querySelector('[data-page="prev"]')?.addEventListener('click',()=>cb(page-1));
 el.querySelector('[data-page="next"]')?.addEventListener('click',()=>cb(page+1));
}
function pageSlice(a,p){return a.slice((p-1)*state.perPage,p*state.perPage)}
function addPagerStyles(){
 const s=document.createElement('style');s.textContent=`
 .ppp-mgmt-pager{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 14px;border-top:1px solid var(--border);color:var(--muted);font-size:10px}
 .ppp-mgmt-pager>div{display:flex;gap:5px}.ppp-mgmt-pager button{min-width:30px}.ppp-mgmt-pager button:disabled{opacity:.45;cursor:not-allowed}
 .ppp-mgmt-tools{display:flex;align-items:center;gap:6px}.ppp-mgmt-select{height:32px;padding:4px 8px;border:1px solid var(--border);border-radius:7px;background:var(--card);color:var(--text);font-size:11px}
 .pppoe-modal-dialog{z-index:2}.pppoe-modal-backdrop{z-index:1}
 `;document.head.appendChild(s);
}
function close(id){$(id)?.setAttribute('hidden','')}
function open(id){$(id)?.removeAttribute('hidden')}
function fillProfileSelect(selected){const s=$('profileSelect');if(!s)return;s.innerHTML=state.profiles.map(p=>`<option value="${esc(p.name)}">${esc(p.name)}</option>`).join('')||'<option value="default">default</option>';if(selected)s.value=selected}
function formData(form){return new FormData(form)}
async function saveUser(e){e.preventDefault();const f=e.currentTarget;const id=f.elements.id.value.trim();const action=id?'edit_secret':'create_secret';const btn=$('saveUserBtn');btn.disabled=true;try{await request(action,formData(f));close('userModal');await window.pppoeRefresh?.();}catch(err){showForm('formMessage',err.message,true)}finally{btn.disabled=false}}
async function saveProfile(e){e.preventDefault();const f=e.currentTarget;const id=f.elements.id.value.trim();const action=id?'edit_profile':'create_profile';const btn=$('saveProfileBtn');btn.disabled=true;try{await request(action,formData(f));close('profileModal');await window.pppoeRefresh?.();}catch(err){showForm('profileFormMessage',err.message,true)}finally{btn.disabled=false}}
function showForm(id,text,error){const el=$(id);if(!el)return;el.textContent=text;el.hidden=!text;el.classList.toggle('error',!!error)}
function resetUser(x){const f=$('userForm');if(!f)return;f.reset();f.elements.id.value=x?.id||'';f.elements.name.value=x?.name||'';f.elements.password.value='';f.elements.service.value=x?.service||'pppoe';fillProfileSelect(x?.profile||'default');f.elements.local_address.value=x?.local_address||'';f.elements.remote_address.value=x?.remote_address||'';f.elements.comment.value=x?.comment||'';$('userModalTitle').textContent=x?'Edit User PPPoE':'Tambah User PPPoE';$('saveUserBtn').textContent=x?'Simpan Perubahan':'Simpan User';showForm('formMessage','',false);open('userModal');setTimeout(()=>f.elements.name.focus(),50)}
function resetProfile(x){const f=$('profileForm');if(!f)return;f.reset();f.elements.id.value=x?.id||'';f.elements.name.value=x?.name||'';f.elements.rate_limit.value=x?.rate_limit||'';f.elements.local_address.value=x?.local_address||'';f.elements.remote_address.value=x?.remote_address||'';f.elements.only_one.value=x?.only_one||'';f.elements.comment.value=x?.comment||'';$('profileModalTitle').textContent=x?'Edit Profile PPPoE':'Tambah Profile PPPoE';$('saveProfileBtn').textContent=x?'Simpan Perubahan':'Simpan Profile';showForm('profileFormMessage','',false);open('profileModal');setTimeout(()=>f.elements.name.focus(),50)}
async function actionSecret(action,id,label){if(!id)return;if(!confirm(label+'?'))return;try{await request(action,new URLSearchParams({id}));await window.pppoeRefresh?.()}catch(e){alert(e.message)}}
async function actionProfile(action,id,label){if(!id)return;if(!confirm(label+'?'))return;try{await request(action,new URLSearchParams({id}));await window.pppoeRefresh?.()}catch(e){alert(e.message)}}
function attach(){
 addPagerStyles();
 $('addUserBtn')?.addEventListener('click',()=>resetUser());$('addProfileBtn')?.addEventListener('click',()=>resetProfile());
 $('userForm')?.addEventListener('submit',saveUser);$('profileForm')?.addEventListener('submit',saveProfile);
 document.addEventListener('click',e=>{
  const closeBtn=e.target.closest('[data-close-modal]');if(closeBtn){close('userModal');return}
  const closeProfile=e.target.closest('[data-close-profile]');if(closeProfile){close('profileModal');return}
  const ed=e.target.closest('[data-edit-secret]');if(ed){const x=state.secrets.find(v=>String(v.id)===String(ed.dataset.editSecret));if(x)resetUser(x);return}
  const ep=e.target.closest('[data-edit-profile]');if(ep){const x=state.profiles.find(v=>String(v.id)===String(ep.dataset.editProfile));if(x)resetProfile(x);return}
  const dis=e.target.closest('[data-disconnect]');if(dis){actionSecret('disconnect',dis.dataset.disconnect,'Disconnect session');return}
  const tog=e.target.closest('[data-toggle]');if(tog){actionSecret(tog.dataset.disabled==='1'?'enable_secret':'disable_secret',tog.dataset.toggle,tog.dataset.disabled==='1'?'Enable user':'Disable user');return}
  const rem=e.target.closest('[data-remove]');if(rem){actionSecret('delete_secret',rem.dataset.remove,'Hapus user PPPoE');return}
  const rp=e.target.closest('[data-remove-profile]');if(rp){actionProfile('delete_profile',rp.dataset.removeProfile,'Hapus profile PPPoE');return}
 });
}
function installRefreshHook(){
 const original=window.pppoeRefresh;
 window.pppoeRefresh=async function(){
  if(typeof original==='function') await original();
  state.active=window.pppoeData?.active||state.active;state.secrets=window.pppoeData?.secrets||state.secrets;state.profiles=window.pppoeData?.profiles||state.profiles;
  fillProfileSelect();
  setTimeout(ensurePagers,30);
 };
}
function ensurePagers(){
 ['activeTable','secretTable','profileTable'].forEach((id,i)=>{const tbody=$(id);if(!tbody||!tbody.parentElement)return;let p=tbody.parentElement.parentElement.querySelector('.ppp-mgmt-pager-wrap');if(!p){p=document.createElement('div');p.className='ppp-mgmt-pager-wrap';tbody.parentElement.parentElement.appendChild(p)}const arr=i===0?state.active:i===1?state.secrets:state.profiles;const pg=i===0?state.activePage:i===1?state.secretPage:state.profilePage;pager(p,pg,arr.length,n=>{if(i===0)state.activePage=n;else if(i===1)state.secretPage=n;else state.profilePage=n;renderPaged()})});}
function renderPaged(){
 const render=(id,arr,page)=>{const tbody=$(id);if(!tbody)return;const rows=pageSlice(arr,page);tbody.querySelectorAll('tr').forEach(r=>r.style.display='');/* Pagination is applied by hiding rows already rendered by the page script. */const all=Array.from(tbody.children);all.forEach((r,i)=>r.style.display=(i>=0&&i<rows.length)?'':'none');};
 render('activeTable',state.active,state.activePage);render('secretTable',state.secrets,state.secretPage);render('profileTable',state.profiles,state.profilePage);ensurePagers();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',attach);else attach();
})();
