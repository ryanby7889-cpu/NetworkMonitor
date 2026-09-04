/* NetMonitor PPPoE Alert Center */
(function(){'use strict';
if(!location.pathname.toLowerCase().includes('/alarm/'))return;
function init(){
 const tables=[...document.querySelectorAll('.alarm-page table')]; if(!tables.length)return;
 const activeTable=tables[0], historyTable=tables[1];
 const host=document.querySelector('.alarm-page .container-fluid'); if(!host)return;
 const box=document.createElement('div'); box.className='pppoe-alert-center'; box.innerHTML='<div class="pac-head"><div><strong>PPPoE Alert Center</strong><span>Alarm bandwidth pelanggan terintegrasi dengan Alarm Monitoring</span></div><div class="pac-filters"><button type="button" class="pac-btn active" data-filter="all">Semua</button><button type="button" class="pac-btn" data-filter="pppoe">PPPoE</button><button type="button" class="pac-btn" data-filter="ether1">Ether1</button></div></div><div class="pac-kpis"><div><b id="pacPppoe">0</b><span>PPPoE Active Alert</span></div><div><b id="pacCritical">0</b><span>Critical</span></div><div><b id="pacWarning">0</b><span>Warning</span></div></div>';
 const style=document.createElement('style');style.textContent='.pppoe-alert-center{background:var(--card,#fff);border:1px solid var(--border,#e2e8f0);border-radius:12px;padding:13px 15px;margin-bottom:16px;box-shadow:var(--shadow-card,0 2px 10px rgba(0,0,0,.04));color:var(--text,#0f172a)}.pac-head{display:flex;justify-content:space-between;align-items:center;gap:12px}.pac-head strong{display:block;font-size:13px}.pac-head span{display:block;color:var(--muted,#64748b);font-size:9px;margin-top:3px}.pac-filters{display:flex;gap:6px}.pac-btn{border:1px solid var(--border,#e2e8f0);background:var(--card,#fff);color:var(--text,#0f172a);border-radius:7px;padding:6px 10px;font-size:9px;font-weight:700;cursor:pointer}.pac-btn.active{background:var(--primary,#2563eb);border-color:var(--primary,#2563eb);color:#fff}.pac-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-top:10px}.pac-kpis>div{border:1px solid var(--border,#e2e8f0);border-radius:8px;padding:8px 10px}.pac-kpis b{display:block;font-size:16px}.pac-kpis span{font-size:8px;color:var(--muted,#64748b)}.pac-pppoe-row{box-shadow:inset 3px 0 #f59e0b}@media(max-width:650px){.pac-head{align-items:flex-start;flex-direction:column}.pac-filters{width:100%}.pac-btn{flex:1}.pac-kpis{grid-template-columns:1fr}}';document.head.appendChild(style);
 const cards=[...host.children]; const firstRow=cards.findIndex(x=>x.classList&&x.classList.contains('row')); host.insertBefore(box,firstRow>=0?host.children[firstRow]:host.firstChild);
 function apply(filter){
   document.querySelectorAll('.pac-btn').forEach(b=>b.classList.toggle('active',b.dataset.filter===filter));
   let p=0,c=0,w=0;
   [activeTable,historyTable].forEach((table,ti)=>{if(!table)return;table.querySelectorAll('tbody tr').forEach(tr=>{const cells=[...tr.children];const text=tr.textContent.toLowerCase();const isP=text.includes('pppoe')||text.includes('pppoe_bandwidth');const isE=text.includes('ether1');const show=filter==='all'||(filter==='pppoe'&&isP)||(filter==='ether1'&&isE);tr.style.display=show?'':'none';if(ti===0&&show&&isP){p++;if(text.includes('critical'))c++;else if(text.includes('warning'))w++;if(isP)tr.classList.add('pac-pppoe-row')}})});
   document.getElementById('pacPppoe').textContent=p;document.getElementById('pacCritical').textContent=c;document.getElementById('pacWarning').textContent=w;
 }
 box.querySelectorAll('.pac-btn').forEach(b=>b.addEventListener('click',()=>apply(b.dataset.filter))); apply('all');
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
