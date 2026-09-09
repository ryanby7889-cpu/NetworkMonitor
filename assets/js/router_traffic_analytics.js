document.addEventListener('DOMContentLoaded',()=>{
const router=document.getElementById('analyticsRouter'),iface=document.getElementById('analyticsInterface'),status=document.getElementById('analyticsStatus'),chartEl=document.getElementById('analyticsChart');
let range='24h',chart=null,busy=false,interfaces=[];
const $=id=>document.getElementById(id);
const num=v=>Number(v||0);
const esc=v=>String(v??'').replace(/[&<>\'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const mb=v=>num(v).toFixed(2)+' Mbps';
const time=v=>{if(!v)return '-';const d=new Date(String(v).replace(' ','T'));return Number.isNaN(d.getTime())?v:d.toLocaleString('id-ID',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})};
const shortTime=v=>{const d=new Date(String(v).replace(' ','T'));return Number.isNaN(d.getTime())?v:d.toLocaleString('id-ID',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})};
function setStatus(t,c=''){status.className='analytics-live '+c;status.innerHTML='<i class="bi bi-activity"></i> '+t}
async function loadRouters(){
 const r=await fetch('../api/routers.php?nocache='+Date.now(),{cache:'no-store'});if(!r.ok)throw Error('HTTP '+r.status);
 const j=await r.json();const rows=j.data||j.routers||[];
 router.innerHTML=rows.length?rows.map((x,i)=>`<option value="${esc(x.id)}">${esc(x.router_name||x.name||x.ip_address||('Router '+(i+1)))}</option>`).join(''):'<option value="">Tidak ada router</option>';
 return rows;
}
function populateInterfaces(list){
 const old=iface.value;interfaces=Array.isArray(list)?list:[];
 iface.innerHTML='<option value="all">Semua Interface</option>'+interfaces.map(n=>`<option value="${esc(n)}">${esc(n)}</option>`).join('');
 if(interfaces.includes(old))iface.value=old;
}
async function refresh(){
 if(busy||!router.value)return;busy=true;setStatus('Memuat analytics...');
 try{
  const u='../api/traffic_analytics.php?range='+encodeURIComponent(range)+'&router_id='+encodeURIComponent(router.value)+(iface.value&&iface.value!=='all'?'&interface='+encodeURIComponent(iface.value):'')+'&nocache='+Date.now();
  const r=await fetch(u,{cache:'no-store'});if(!r.ok)throw Error('HTTP '+r.status);
  const j=await r.json();if(j.success===false)throw Error(j.message||'Gagal mengambil data');
  populateInterfaces(j.interfaces||[]);render(j);
  setStatus('Live • diperbarui '+new Date().toLocaleTimeString('id-ID'),'ok');
 }catch(e){clearRender();setStatus('Data gagal dimuat','error');console.warn(e)}finally{busy=false}
}
function clearRender(){$('avgDownload').textContent='0.00 Mbps';$('avgUpload').textContent='0.00 Mbps';$('peakDownload').textContent='0.00 Mbps';$('peakUpload').textContent='0.00 Mbps';$('peakTime').textContent='-';$('sampleCount').textContent='0';$('chartInfo').textContent='0 sample';$('busyHoursBody').innerHTML='<tr><td colspan="4" class="text-center py-4 text-muted">Belum ada data.</td></tr>';if(chart){chart.destroy();chart=null}$('analyticsInsight').textContent='Belum ada data traffic pada periode dan interface yang dipilih.'}
function render(j){
 const s=j.summary||{};const peak=j.peak||null;
 $('avgDownload').textContent=mb(s.avg_download);$('avgUpload').textContent=mb(s.avg_upload);$('peakDownload').textContent=mb(s.peak_download);$('peakUpload').textContent=mb(s.peak_upload);$('peakTime').textContent=peak?time(peak.created_at):'-';$('sampleCount').textContent=num(s.sample_count).toLocaleString('id-ID');
 const trend=Array.isArray(j.trend)?j.trend:[];$('chartInfo').textContent=trend.length+' interval';renderChart(trend);renderHours(j.busy_hours||[]);renderInsight(s,peak,j.range,trend.length);
}
function renderChart(data){
 if(chart)chart.destroy();
 const labels=data.map(x=>shortTime(x.bucket)),dl=data.map(x=>num(x.download_mbps)),ul=data.map(x=>num(x.upload_mbps));
 chart=new Chart(chartEl,{type:'line',data:{labels,datasets:[{label:'Download',data:dl,tension:.3,pointRadius:0,borderWidth:2},{label:'Upload',data:ul,tension:.3,pointRadius:0,borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top'},tooltip:{callbacks:{label:c=>c.dataset.label+' : '+num(c.parsed.y).toFixed(2)+' Mbps'}}},scales:{x:{grid:{display:false},ticks:{maxTicksLimit:12}},y:{beginAtZero:true,title:{display:true,text:'Traffic (Mbps)'}}}}});
}
function renderHours(rows){
 $('busyHoursBody').innerHTML=rows.length?rows.map(x=>{const h=String(x.hour_of_day).padStart(2,'0');const total=num(x.download_mbps)+num(x.upload_mbps);return `<tr><td><strong>${h}:00</strong></td><td>${mb(x.download_mbps)}</td><td>${mb(x.upload_mbps)}</td><td><strong>${mb(total)}</strong></td></tr>`}).join(''):'<tr><td colspan="4" class="text-center py-4 text-muted">Belum ada data.</td></tr>';
}
function renderInsight(s,peak,r,n){
 const ad=num(s.avg_download),au=num(s.avg_upload),pd=num(s.peak_download);let text='Rata-rata traffic adalah '+mb(ad)+' download dan '+mb(au)+' upload.';
 if(peak)text+=' Peak download '+mb(pd)+' terjadi sekitar '+time(peak.created_at)+'.';
 if(ad+au>0){if(pd/(ad+au)>3)text+=' Download jauh lebih dominan daripada upload.';else if(au>ad)text+=' Upload lebih dominan pada periode ini.';else text+=' Download menjadi traffic utama pada periode ini.'}
 text+=' Analisis dihitung server-side untuk seluruh data '+(r==='7d'?'7 hari':'24 jam')+', dengan '+num(s.sample_count).toLocaleString('id-ID')+' sample.';
 $('analyticsInsight').textContent=text;
}
document.querySelectorAll('[data-range]').forEach(b=>b.addEventListener('click',()=>{range=b.dataset.range;document.querySelectorAll('[data-range]').forEach(x=>x.classList.toggle('active',x===b));refresh()}));
router.addEventListener('change',()=>{iface.value='all';refresh()});iface.addEventListener('change',refresh);
(async()=>{try{await loadRouters();await refresh()}catch(e){setStatus('Router tidak dapat dimuat','error');console.warn(e)}})();
setInterval(()=>{if(document.visibilityState==='visible')refresh()},10000);
});
