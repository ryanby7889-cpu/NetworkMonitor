(() => {
  const API='../api/billing_dashboard.php';
  const $=id=>document.getElementById(id);
  let revenueChart=null,statusChart=null,refreshTimer=null;
  const money=v=>'Rp '+new Intl.NumberFormat('id-ID',{maximumFractionDigits:0}).format(Number(v)||0);
  const num=v=>Number(v||0).toLocaleString('id-ID');
  const esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  const monthLabel=s=>{const [y,m]=String(s).split('-');return new Date(Number(y),Number(m)-1,1).toLocaleDateString('id-ID',{month:'short',year:'numeric'});};
  const now=new Date();
  $('month').value=now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0');
  function notice(t,e=false){$('notice').textContent=t||'';$('notice').hidden=!t;$('notice').classList.toggle('error',e);}
  function charts(){
    revenueChart=new Chart($('revenueChart'),{type:'line',data:{labels:[],datasets:[{label:'Tagihan',data:[],tension:.35,borderWidth:2,fill:false},{label:'Dibayar',data:[],tension:.35,borderWidth:2,fill:false}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{tooltip:{callbacks:{label:c=>c.dataset.label+': '+money(c.raw)}}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{callback:v=>money(v).replace('Rp ','')}}}}});
    statusChart=new Chart($('statusChart'),{type:'doughnut',data:{labels:[],datasets:[{data:[],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom'}}}});
  }
  function render(d){
    const s=d.summary||{},c=d.customers||{},i=d.integration||{};
    $('customerTotal').textContent=num(c.total);$('customerStatus').textContent='Aktif '+num(c.active)+' • Suspend '+num(c.suspended);
    $('billed').textContent=money(s.billed);$('invoiceCount').textContent=num(s.invoice_count)+' invoice';$('paid').textContent=money(s.paid);$('paidCount').textContent=num(s.paid_count)+' lunas';$('unpaid').textContent=money(s.unpaid);$('unpaidCount').textContent=num((s.waiting_count||0)+(s.overdue_count||0))+' belum bayar';$('overdue').textContent=num(s.overdue_count);$('overdueAmount').textContent=money((d.arrears||[]).reduce((a,x)=>a+Number(x.arrears||0),0))+' tunggakan';$('collection').textContent=(Number(s.billed)>0?((Number(s.paid)/Number(s.billed))*100):0).toFixed(1)+'%';
    const tr=d.trend||[];revenueChart.data.labels=tr.map(x=>monthLabel(x.month));revenueChart.data.datasets[0].data=tr.map(x=>x.billed);revenueChart.data.datasets[1].data=tr.map(x=>x.paid);revenueChart.update();
    const st=d.statuses||[];statusChart.data.labels=st.map(x=>String(x.status).toUpperCase());statusChart.data.datasets[0].data=st.map(x=>Number(x.total));statusChart.update();$('statusPeriod').textContent='Periode '+monthLabel(d.month);$('statusList').innerHTML=st.length?st.map(x=>`<div class="bd-status-item"><span>${esc(String(x.status).toUpperCase())}</span><b>${num(x.total)} • ${money(x.amount)}</b></div>`).join(''):'<div class="bd-empty">Belum ada invoice.</div>';
    $('pppoeAccounts').textContent=num(i.accounts);$('pppoeEnabled').textContent=num(i.enabled_accounts);$('pppoeDisabled').textContent=num(i.disabled_accounts);$('pppoeActive').textContent=num(i.active_sessions);$('pppoeLinked').textContent=num(i.linked_accounts);$('pppoeUnlinked').textContent=num(i.unlinked_accounts);
    const state=$('pppoeState');state.textContent=i.api_online?'ONLINE':'OFFLINE';state.classList.toggle('offline',!i.api_online);$('pppoeError').textContent=i.api_online?'Pelanggan billing yang username-nya tidak ditemukan di MikroTik: '+num(i.unlinked_billing_customers):('PPPoE: '+(d.pppoe_error||'tidak dapat dihubungi'));
    $('upcoming').innerHTML=(d.upcoming||[]).length?d.upcoming.map(x=>`<tr><td>${esc(x.invoice_no)}</td><td>${esc(x.customer_name)}<br><small>${esc(x.pppoe_username)}</small></td><td>${esc(x.due_date)}</td><td class="amount">${money(x.amount)}</td><td>H-${num(x.days_left)}</td></tr>`).join(''):'<tr><td colspan="5" class="bd-empty">Tidak ada jatuh tempo dalam 7 hari.</td></tr>';
    $('arrears').innerHTML=(d.arrears||[]).length?d.arrears.map(x=>`<tr><td>${esc(x.name)}</td><td>${esc(x.pppoe_username)}</td><td>${num(x.overdue_invoices)}</td><td class="amount">${money(x.arrears)}</td><td>${num(x.oldest_days)} hari</td></tr>`).join(''):'<tr><td colspan="5" class="bd-empty">Tidak ada piutang overdue.</td></tr>';
    $('monthly').innerHTML=tr.map(x=>{const rate=Number(x.billed)>0?(Number(x.paid)/Number(x.billed)*100):0;return `<tr class="${x.month===d.month?'current':''}"><td>${monthLabel(x.month)}</td><td>${num(x.invoice_count)}</td><td>${money(x.billed)}</td><td>${money(x.paid)}</td><td>${money(x.unpaid)}</td><td>${rate.toFixed(1)}%</td></tr>`;}).join('');
  }
  async function load(){
    try{notice('');const month=encodeURIComponent($('month').value);const r=await fetch(API+'?month='+month+'&t='+Date.now(),{cache:'no-store'});const d=await r.json();if(!r.ok||!d.success)throw Error(d.message||'API Billing gagal');render(d);}catch(e){console.error(e);notice('Dashboard billing gagal dimuat: '+e.message,true);}
  }
  function schedule(){clearTimeout(refreshTimer);refreshTimer=setTimeout(async()=>{await load();schedule();},30000);}
  $('refreshBtn').addEventListener('click',async()=>{await load();schedule();});$('month').addEventListener('change',async()=>{await load();schedule();});$('printBtn').addEventListener('click',()=>window.print());
  charts();load().then(schedule);
})();
