<?php
$activeMenu = 'billing';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Billing PPPoE - NetMonitor</title>
<link rel="stylesheet" href="../assets/css/variables.css?v=11">
<link rel="stylesheet" href="../assets/css/common.css?v=11">
<link rel="stylesheet" href="../assets/css/billing.css?v=22">
<link rel="stylesheet" href="../assets/css/billing-smart-filter.css?v=22">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>
(function () {
    try {
        if (localStorage.getItem('netmonitor_theme') === 'dark') {
            document.documentElement.classList.add('theme-dark');
        }
    } catch (e) {}
})();
</script>
</head>
<body>

<?php
$activeMenu = 'billing';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="billing-page-shell">
<main class="billing-main">

<div class="billing-header">
  <div>
    <h1 class="billing-title">Billing PPPoE</h1>
    <div class="billing-subtitle">Manajemen pelanggan, tagihan, pembayaran, dan jatuh tempo</div>
  </div>
  <div class="billing-actions">
    <a href="../billing-dashboard/" class="billing-btn billing-btn-secondary"><i class="bi bi-bar-chart-line"></i>&nbsp; Dashboard</a>
    <button class="billing-btn billing-btn-primary" id="addCustomerBtn"><i class="bi bi-person-plus"></i>&nbsp; Tambah Pelanggan</button>
    <button class="billing-btn billing-btn-secondary" id="refreshBtn"><i class="bi bi-arrow-clockwise"></i>&nbsp; Refresh</button>
  </div>
</div>

<div id="message" class="billing-message" hidden></div>

<section class="billing-stats">
  <div class="billing-stat"><div class="billing-stat-title">Total Pelanggan</div><div id="totalCustomers" class="billing-stat-value">0</div></div>
  <div class="billing-stat"><div class="billing-stat-title">Pelanggan Aktif</div><div id="activeCustomers" class="billing-stat-value">0</div></div>
  <div class="billing-stat"><div class="billing-stat-title">Ditangguhkan</div><div id="suspendedCustomers" class="billing-stat-value">0</div></div>
  <div class="billing-stat"><div class="billing-stat-title">Belum Bayar</div><div id="unpaidInvoices" class="billing-stat-value">0</div></div>
  <div class="billing-stat"><div class="billing-stat-title">Terlambat</div><div id="overdueInvoices" class="billing-stat-value">0</div></div>
  <div class="billing-stat"><div class="billing-stat-title">Pembayaran Bulan Ini</div><div id="paidMonth" class="billing-stat-value">Rp 0</div></div>
  <div class="billing-stat"><div class="billing-stat-title">Piutang Overdue</div><div id="overdueAmount" class="billing-stat-value">Rp 0</div></div>
</section>

<section class="billing-card">
  <div class="billing-card-header">
    <div><h2>Kontrol Penagihan</h2><small>Cek keterlambatan dan proses suspend berdasarkan masa toleransi.</small></div>
  </div>
  <div class="billing-collection">
    <label>Masa Toleransi <input id="graceDays" class="billing-input" type="number" min="0" max="30" value="3"></label>
    <div class="billing-help">Suspend akan menonaktifkan akun PPPoE di MikroTik setelah melewati masa toleransi.</div>
    <button class="billing-btn billing-btn-warning" id="checkOverdueBtn">Cek Terlambat</button>
    <button class="billing-btn billing-btn-danger" id="autoSuspendBtn">Proses Suspend</button>
    <button class="billing-btn billing-btn-secondary" id="generateMissingBtn">Cari Invoice Belum Dibuat</button>
  </div>
  <div id="collectionResult" class="billing-mini-result" hidden></div>
</section>

<section class="billing-card" id="customerSection">
  <div class="billing-card-header">
    <div><h2>Pelanggan PPPoE</h2><small>Data pelanggan billing yang terhubung langsung dengan akun PPPoE MikroTik.</small></div>
    <input id="customerSearch" class="billing-search" placeholder="Cari nama / username / paket...">
  </div>
  <div class="billing-table-wrap">
    <table class="billing-table">
      <thead><tr><th>#</th><th>Pelanggan</th><th>PPPoE</th><th>Paket</th><th>Harga/Bulan</th><th>Jatuh Tempo</th><th>Status</th><th>Action</th></tr></thead>
      <tbody id="customerTable"><tr><td colspan="8">Memuat data...</td></tr></tbody>
    </table>
  </div>
</section>

<section class="billing-card" id="invoiceSection">
  <div class="billing-card-header">
    <div><h2>Tagihan PPPoE</h2><small>Invoice dan pembayaran pelanggan.</small></div>
    <div class="billing-actions">
      <input id="invoiceSearch" class="billing-search" placeholder="Cari invoice / pelanggan / PPPoE...">
      <input id="invoicePeriod" class="billing-search billing-month-input" type="month">
      <button class="billing-btn billing-btn-primary" id="generateAllBtn"><i class="bi bi-plus-lg"></i>&nbsp; Buat Tagihan Periode</button>
    </div>
  </div>
  <div id="generationResult" class="billing-generation-result" hidden></div>
  <div class="billing-table-wrap">
    <table class="billing-table">
      <thead><tr><th>#</th><th>No. Invoice</th><th>Pelanggan</th><th>Periode</th><th>Jatuh Tempo</th><th>Jumlah</th><th>Status</th><th>Action</th></tr></thead>
      <tbody id="invoiceTable"><tr><td colspan="8">Memuat data...</td></tr></tbody>
    </table>
  </div>
</section>

<section class="billing-card" id="paymentSection">
  <div class="billing-card-header">
    <div><h2>Riwayat Pembayaran</h2><small>Daftar invoice yang sudah dibayar.</small></div>
    <input id="paymentSearch" class="billing-search" placeholder="Cari invoice / pelanggan / metode...">
  </div>
  <div class="billing-table-wrap">
    <table class="billing-table">
      <thead><tr><th>#</th><th>Invoice</th><th>Pelanggan</th><th>Periode</th><th>Tanggal Bayar</th><th>Jumlah</th><th>Metode</th><th>Action</th></tr></thead>
      <tbody id="paymentHistoryTable"><tr><td colspan="8">Memuat data...</td></tr></tbody>
    </table>
  </div>
</section>

</main>
</div>

<div class="billing-modal" id="customerModal" hidden>
  <div class="billing-backdrop" data-close></div>
  <div class="billing-dialog">
    <div class="billing-modal-header">
      <div><h2 id="modalTitle">Tambah Pelanggan</h2><small>Hubungkan pelanggan dengan akun PPPoE MikroTik.</small></div>
      <button type="button" class="modal-close" data-close>×</button>
    </div>

    <form id="customerForm" class="billing-form">
      <input type="hidden" name="id">

      <div class="billing-form-grid">
        <label>Nama Pelanggan<input class="billing-input" name="name" required></label>
        <label>No. HP<input class="billing-input" name="phone" placeholder="08xxxxxxxxxx"></label>

        <label>
          Username PPPoE
          <select class="billing-input" name="pppoe_username" id="pppoeUsernameSelect" required>
            <option value="">Memuat akun PPPoE...</option>
          </select>
          <small id="pppoeHint" style="display:block;margin-top:5px;">Daftar diambil langsung dari MikroTik.</small>
        </label>

        <label>Nama Paket<input class="billing-input" name="package_name" required></label>
        <label>Harga Bulanan<input class="billing-input" type="number" min="0" step="1000" name="monthly_price" required></label>
        <label>Tanggal Jatuh Tempo<input class="billing-input" type="number" min="1" max="28" name="billing_day" value="10" required></label>

        <label>Status
          <select class="billing-input" name="status">
            <option value="active">Aktif</option>
            <option value="suspended">Ditangguhkan</option>
          </select>
        </label>

        <label class="full">Alamat<textarea class="billing-input" name="address" rows="2"></textarea></label>
        <label class="full">Catatan<textarea class="billing-input" name="notes" rows="2"></textarea></label>
      </div>

      <div id="formMessage" class="billing-message" hidden></div>

      <div class="billing-form-actions">
        <button type="button" class="billing-btn billing-btn-secondary" data-close>Batal</button>
        <button class="billing-btn billing-btn-primary" id="saveCustomerBtn">Simpan Pelanggan</button>
      </div>
    </form>
  </div>
</div>

<script>
(() => {
  const API = '../api/billing.php';
  const $ = id => document.getElementById(id);

  let customers = [];
  let invoices = [];
  let pppoeAccounts = [];
  let editingCustomer = null;

  const params = new URLSearchParams(location.search);
  const initial = {
    status: (params.get('status') || '').toLowerCase(),
    customer: params.get('customer') || '',
    invoice: params.get('invoice') || '',
    month: params.get('month') || ''
  };

  const esc = v => {
    const d = document.createElement('div');
    d.textContent = v ?? '-';
    return d.innerHTML;
  };

  const clean = v => String(v ?? '').toLowerCase().trim();

  const money = v => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0
  }).format(Number(v) || 0);

  function showMessage(text, error=false) {
    const e = $('message');
    e.textContent = text || '';
    e.hidden = !text;
    e.classList.toggle('error', error);
  }

  async function request(action, data=null) {
    const url = API + '?action=' + encodeURIComponent(action) + '&t=' + Date.now();
    const opt = data ? {
      method:'POST',
      body:new URLSearchParams(data)
    } : {cache:'no-store'};

    const r = await fetch(url, opt);
    const text = await r.text();
    let j;
    try { j = JSON.parse(text); }
    catch { throw Error('Response API tidak valid. Periksa api/billing.php.'); }

    if (!r.ok || !j.success) throw Error(j.message || 'Request gagal.');
    return j;
  }

  async function loadPppoeAccounts() {
    const r = await request('pppoe_accounts');
    pppoeAccounts = r.accounts || [];
    return pppoeAccounts;
  }

  function renderPppoeSelect(selected='') {
    const select = $('pppoeUsernameSelect');
    select.innerHTML = '<option value="">-- Pilih akun PPPoE MikroTik --</option>';

    if (!pppoeAccounts.length) {
      select.innerHTML += '<option value="" disabled>Tidak ada akun PPPoE</option>';
      $('pppoeHint').textContent = 'Tidak ada akun PPPoE yang dapat dipilih.';
      return;
    }

    let selectedFound = false;

    for (const a of pppoeAccounts) {
      const opt = document.createElement('option');
      opt.value = a.name;

      let label = a.name;
      if (a.profile) label += ' — ' + a.profile;
      if (a.disabled) label += ' — DISABLED';
      if (a.billing_linked && String(a.name) !== String(selected)) {
        label += ' — SUDAH TERHUBUNG: ' + a.customer_name;
        opt.disabled = true;
      }

      opt.textContent = label;

      if (String(a.name) === String(selected)) {
        opt.selected = true;
        selectedFound = true;
      }

      select.appendChild(opt);
    }

    if (selected && !selectedFound) {
      const opt = document.createElement('option');
      opt.value = selected;
      opt.textContent = selected + ' — TIDAK DITEMUKAN DI MIKROTIK';
      opt.selected = true;
      select.appendChild(opt);
    }

    $('pppoeHint').textContent =
      pppoeAccounts.length + ' akun PPPoE ditemukan dari MikroTik.';
  }

  $('pppoeUsernameSelect').addEventListener('change', () => {
    const value = $('pppoeUsernameSelect').value;
    const a = pppoeAccounts.find(x => String(x.name) === String(value));

    if (a) {
      $('pppoeHint').textContent =
        'Profile: ' + (a.profile || '-') +
        ' • Service: ' + (a.service || '-') +
        (a.disabled ? ' • STATUS: DISABLED' : ' • STATUS: ENABLED');
    }
  });

  function invoiceStatus(x) {
    if (x.status === 'unpaid' && x.due_date &&
        x.due_date < new Date().toISOString().slice(0,10)) return 'overdue';
    return x.display_status || x.status || '';
  }

  function statusBadge(st) {
    const map = {
      paid:['LUNAS','badge-paid'],
      unpaid:['BELUM BAYAR','badge-unpaid'],
      overdue:['TERLAMBAT','badge-overdue'],
      cancelled:['DIBATALKAN','badge-cancelled']
    };
    const m = map[st] || [String(st).toUpperCase(),'badge-unpaid'];
    return `<span class="billing-badge ${m[1]}">${m[0]}</span>`;
  }

  function pppoeBadge(x) {
    if (x.pppoe_found === true) {
      return x.pppoe_disabled
        ? '<span class="billing-badge badge-suspended">PPPoE DISABLED</span>'
        : '<span class="billing-badge badge-active">PPPoE TERHUBUNG</span>';
    }
    if (x.pppoe_found === false) {
      return '<span class="billing-badge badge-overdue">PPPoE TIDAK DITEMUKAN</span>';
    }
    return '<span class="billing-badge badge-unpaid">PPPoE UNKNOWN</span>';
  }

  function filteredCustomers() {
    const q = clean($('customerSearch').value);
    return customers.filter(x => {
      if (initial.customer &&
          String(x.id) !== String(initial.customer) &&
          !clean(x.name).includes(clean(initial.customer)) &&
          !clean(x.pppoe_username).includes(clean(initial.customer))) return false;

      if (!q) return true;
      return [x.name,x.phone,x.pppoe_username,x.package_name]
        .map(clean).join(' ').includes(q);
    });
  }

  function filteredInvoices() {
    const q = clean($('invoiceSearch').value);
    return invoices.filter(x => {
      if (initial.invoice &&
          String(x.id) !== String(initial.invoice) &&
          !clean(x.invoice_no).includes(clean(initial.invoice))) return false;

      if (initial.status && invoiceStatus(x) !== initial.status) return false;
      if (initial.month && String(x.period || '').slice(0,7) !== initial.month) return false;

      if (!q) return true;
      return [x.invoice_no,x.customer_name,x.pppoe_username,x.period,x.due_date]
        .map(clean).join(' ').includes(q);
    });
  }

  function renderCustomers() {
    const rows = filteredCustomers();
    $('customerTable').innerHTML = rows.length ? rows.map((x,i) => `
      <tr>
        <td>${i+1}</td>
        <td><strong>${esc(x.name)}</strong><br><small>${esc(x.phone)}</small></td>
        <td>
          <strong>${esc(x.pppoe_username)}</strong><br>
          ${pppoeBadge(x)}
        </td>
        <td>${esc(x.package_name)}</td>
        <td>${money(x.monthly_price)}</td>
        <td>Tgl ${esc(x.billing_day)}</td>
        <td>
          <span class="billing-badge ${x.status === 'active' ? 'badge-active' : 'badge-suspended'}">
            ${x.status === 'active' ? 'AKTIF' : 'DITANGGUHKAN'}
          </span>
        </td>
        <td>
          <a class="billing-btn billing-btn-secondary billing-btn-small"
             href="detail.php?customer=${encodeURIComponent(x.id)}">Detail</a>
          <button class="billing-btn billing-btn-primary billing-btn-small" data-edit="${esc(x.id)}">Edit</button>
          ${x.status === 'active'
            ? `<button class="billing-btn billing-btn-warning billing-btn-small" data-suspend="${esc(x.id)}">Suspend</button>`
            : `<button class="billing-btn billing-btn-success billing-btn-small" data-activate="${esc(x.id)}">Aktifkan</button>`}
          <button class="billing-btn billing-btn-danger billing-btn-small" data-delete="${esc(x.id)}">Delete</button>
        </td>
      </tr>
    `).join('') : '<tr><td colspan="8">Tidak ada pelanggan yang sesuai filter.</td></tr>';
  }

  function renderInvoices() {
    const rows = filteredInvoices();
    $('invoiceTable').innerHTML = rows.length ? rows.map((x,i) => {
      const st = invoiceStatus(x);
      return `
        <tr>
          <td>${i+1}</td>
          <td><strong>${esc(x.invoice_no)}</strong></td>
          <td>${esc(x.customer_name)}<br><small>${esc(x.pppoe_username)}</small></td>
          <td>${esc(x.period)}</td>
          <td>${esc(x.due_date)}</td>
          <td>${money(x.amount)}</td>
          <td>${statusBadge(st)}</td>
          <td>
            <a class="billing-btn billing-btn-secondary billing-btn-small"
               href="invoice.php?invoice=${encodeURIComponent(x.id)}">Detail</a>
            ${x.status === 'unpaid'
              ? `<button class="billing-btn billing-btn-success billing-btn-small" data-pay="${esc(x.id)}">Bayar</button>
                 <button class="billing-btn billing-btn-danger billing-btn-small" data-cancel="${esc(x.id)}">Batal</button>`
              : '-'}
          </td>
        </tr>`;
    }).join('') : '<tr><td colspan="8">Tidak ada invoice yang sesuai filter.</td></tr>';
  }

  function renderPayments() {
    const q = clean($('paymentSearch').value);
    let rows = invoices.filter(x => x.status === 'paid');

    if (initial.invoice)
      rows = rows.filter(x => String(x.id) === String(initial.invoice) ||
                              clean(x.invoice_no).includes(clean(initial.invoice)));

    if (initial.customer)
      rows = rows.filter(x => String(x.customer_id) === String(initial.customer) ||
                              clean(x.customer_name).includes(clean(initial.customer)));

    if (initial.month)
      rows = rows.filter(x => String(x.period || '').slice(0,7) === initial.month);

    if (q)
      rows = rows.filter(x => [x.invoice_no,x.customer_name,x.pppoe_username,x.payment_method,x.period]
        .map(clean).join(' ').includes(q));

    $('paymentHistoryTable').innerHTML = rows.length ? rows.map((x,i) => `
      <tr>
        <td>${i+1}</td>
        <td><strong>${esc(x.invoice_no)}</strong></td>
        <td>${esc(x.customer_name)}<br><small>${esc(x.pppoe_username)}</small></td>
        <td>${esc(x.period)}</td>
        <td>${esc(x.paid_at || '-')}</td>
        <td>${money(x.amount)}</td>
        <td>${esc(x.payment_method || '-')}</td>
        <td><button class="billing-btn billing-btn-primary billing-btn-small" data-receipt="${esc(x.id)}">Kwitansi</button></td>
      </tr>
    `).join('') : '<tr><td colspan="8">Tidak ada pembayaran yang sesuai filter.</td></tr>';
  }

  function renderSummary() {
    const active = customers.filter(x => x.status === 'active').length;
    const suspended = customers.filter(x => x.status === 'suspended').length;
    const overdue = invoices.filter(x => invoiceStatus(x) === 'overdue').length;
    const month = $('invoicePeriod').value || new Date().toISOString().slice(0,7);
    const monthInvoices = invoices.filter(x => String(x.period || '').slice(0,7) === month);
    const paidMonth = monthInvoices.filter(x => x.status === 'paid')
      .reduce((s,x) => s + (Number(x.amount) || 0), 0);
    const overdueAmount = invoices.filter(x => invoiceStatus(x) === 'overdue')
      .reduce((s,x) => s + (Number(x.amount) || 0), 0);

    $('totalCustomers').textContent = customers.length;
    $('activeCustomers').textContent = active;
    $('suspendedCustomers').textContent = suspended;
    $('unpaidInvoices').textContent = invoices.filter(x => x.status === 'unpaid').length;
    $('overdueInvoices').textContent = overdue;
    $('paidMonth').textContent = money(paidMonth);
    $('overdueAmount').textContent = money(overdueAmount);
  }

  async function refresh() {
    try {
      const [c,i] = await Promise.all([request('customers'),request('invoices')]);
      customers = c.customers || [];
      invoices = i.invoices || [];
      renderSummary();
      renderCustomers();
      renderInvoices();
      renderPayments();
      showMessage('');
    } catch(e) {
      showMessage(e.message,true);
    }
  }

  async function openCustomer(item=null) {
    editingCustomer = item;
    const f = $('customerForm');
    f.reset();

    f.elements.id.value = item?.id || '';
    f.elements.name.value = item?.name || '';
    f.elements.phone.value = item?.phone || '';
    f.elements.package_name.value = item?.package_name || '';
    f.elements.monthly_price.value = item?.monthly_price || 0;
    f.elements.billing_day.value = item?.billing_day || 10;
    f.elements.status.value = item?.status || 'active';
    f.elements.address.value = item?.address || '';
    f.elements.notes.value = item?.notes || '';

    $('modalTitle').textContent = item ? 'Edit Pelanggan' : 'Tambah Pelanggan';
    $('formMessage').hidden = true;
    $('customerModal').hidden = false;

    try {
      await loadPppoeAccounts();
      renderPppoeSelect(item?.pppoe_username || '');
    } catch(e) {
      renderPppoeSelect(item?.pppoe_username || '');
      $('pppoeHint').textContent = 'Gagal membaca PPPoE: ' + e.message;
    }
  }

  function closeCustomer() {
    $('customerModal').hidden = true;
    editingCustomer = null;
  }

  async function checkOverdue() {
    const box = $('collectionResult');
    box.hidden = false;
    box.textContent = 'Memeriksa tagihan terlambat...';

    try {
      const grace = Math.max(0,Math.min(30,Number($('graceDays').value)||0));
      const d = await request('overdue_candidates',{grace_days:grace});
      const list = d.customers || [];
      box.innerHTML = list.length
        ? 'Ditemukan <strong>' + list.length + '</strong> pelanggan: ' +
          list.map(x => esc(x.pppoe_username) + ' (' + esc(x.overdue_days) + ' hari)').join(', ') + '.'
        : 'Tidak ada pelanggan yang memenuhi batas suspend.';
    } catch(e) {
      box.textContent = e.message;
    }
  }

  async function processAutoSuspend() {
    if (!confirm('Proses suspend pelanggan yang terlambat sesuai masa toleransi?')) return;

    const box = $('collectionResult');
    box.hidden = false;
    box.textContent = 'Memproses suspend dan menonaktifkan PPPoE...';

    try {
      const grace = Math.max(0,Math.min(30,Number($('graceDays').value)||0));
      const d = await request('process_auto_suspend',{grace_days:grace});
      box.textContent = d.message || 'Proses suspend selesai.';
      await refresh();
    } catch(e) {
      box.textContent = e.message;
    }
  }

  async function generatePeriod() {
    const selected = $('invoicePeriod').value || defaultPeriod();
    const period = selected + '-01';
    const result = $('generationResult');
    result.hidden = false;
    result.textContent = 'Memproses invoice periode ' + selected + '...';
    $('generateAllBtn').disabled = true;

    let made=0, skipped=0, failed=0, details=[];

    try {
      for (const c of customers.filter(x => x.status === 'active')) {
        try {
          const r = await request('generate_invoice',{customer_id:c.id,period});
          if (r.result === 'skipped') skipped++; else made++;
        } catch(e) {
          failed++;
          details.push((c.name || c.pppoe_username) + ': ' + e.message);
        }
      }

      await refresh();

      result.innerHTML = '<strong>Generate selesai.</strong> ' + made +
        ' invoice dibuat, ' + skipped + ' dilewati, ' + failed + ' gagal.' +
        (details.length ? '<br><small>' + details.map(esc).join('<br>') + '</small>' : '');
    } catch(e) {
      result.textContent = 'Gagal generate invoice: ' + e.message;
    } finally {
      $('generateAllBtn').disabled = false;
    }
  }

  function defaultPeriod() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
  }

  $('addCustomerBtn').onclick = () => openCustomer();
  $('refreshBtn').onclick = refresh;
  $('customerSearch').oninput = renderCustomers;
  $('invoiceSearch').oninput = renderInvoices;
  $('paymentSearch').oninput = renderPayments;
  $('checkOverdueBtn').onclick = checkOverdue;
  $('autoSuspendBtn').onclick = processAutoSuspend;
  $('generateAllBtn').onclick = generatePeriod;

  $('generateMissingBtn').onclick = async () => {
    const month = $('invoicePeriod').value || defaultPeriod();
    const existing = new Set(invoices.filter(x => String(x.period||'').slice(0,7) === month)
      .map(x => String(x.customer_id)));
    const missing = customers.filter(c => c.status === 'active' && !existing.has(String(c.id)));

    const box = $('collectionResult');
    box.hidden = false;

    if (!missing.length) {
      box.textContent = 'Semua pelanggan aktif sudah memiliki invoice untuk ' + month + '.';
      return;
    }

    box.textContent = 'Ditemukan ' + missing.length + ' pelanggan yang belum memiliki invoice untuk ' + month + '.';

    if (confirm('Buat invoice yang belum ada untuk ' + month + '?')) {
      await generatePeriod();
    }
  };

  document.querySelectorAll('[data-close]').forEach(e => e.onclick = closeCustomer);

  $('customerForm').onsubmit = async e => {
    e.preventDefault();

    const btn = $('saveCustomerBtn');
    const fm = $('formMessage');
    btn.disabled = true;
    fm.hidden = true;

    try {
      await request('save_customer',Object.fromEntries(new FormData(e.currentTarget)));
      closeCustomer();
      await refresh();
      showMessage('Pelanggan berhasil disimpan dan terhubung dengan PPPoE.');
    } catch(err) {
      fm.textContent = err.message;
      fm.hidden = false;
    } finally {
      btn.disabled = false;
    }
  };

  document.addEventListener('click', async e => {
    try {
      const edit = e.target.closest('[data-edit]');
      const del = e.target.closest('[data-delete]');
      const pay = e.target.closest('[data-pay]');
      const cancel = e.target.closest('[data-cancel]');
      const receipt = e.target.closest('[data-receipt]');
      const suspend = e.target.closest('[data-suspend]');
      const activate = e.target.closest('[data-activate]');

      if (receipt) {
        window.open('receipt.php?id=' + encodeURIComponent(receipt.dataset.receipt),'_blank','noopener');
        return;
      }

      if (pay && confirm('Catat tagihan ini sebagai LUNAS?')) {
        const r = await request('pay_invoice',{id:pay.dataset.pay,payment_method:'Cash'});
        await refresh();

        if (r.reactivation?.attempted && !r.reactivation?.success) {
          showMessage('Pembayaran berhasil, tetapi reaktivasi PPPoE gagal: ' +
            (r.reactivation.message || 'Unknown error'),true);
        } else {
          showMessage('Pembayaran berhasil dicatat.');
        }
        return;
      }

      if (cancel && confirm('Batalkan tagihan ini?')) {
        await request('cancel_invoice',{id:cancel.dataset.cancel});
        await refresh();
        showMessage('Tagihan dibatalkan.');
        return;
      }

      if (edit) {
        const x = customers.find(v => String(v.id) === String(edit.dataset.edit));
        if (x) await openCustomer(x);
        return;
      }

      if (del && confirm('Hapus pelanggan dan seluruh tagihannya?')) {
        await request('delete_customer',{id:del.dataset.delete});
        await refresh();
        showMessage('Pelanggan berhasil dihapus.');
        return;
      }

      if (suspend) {
        const x = customers.find(v => String(v.id) === String(suspend.dataset.suspend));
        if (x && confirm('Suspend pelanggan ' + x.name + '?')) {
          await request('set_customer_status',{id:x.id,status:'suspended'});
          await refresh();
          showMessage('Pelanggan ditangguhkan dan PPPoE dinonaktifkan.');
        }
        return;
      }

      if (activate) {
        const x = customers.find(v => String(v.id) === String(activate.dataset.activate));
        if (x && confirm('Aktifkan kembali pelanggan ' + x.name + '?')) {
          await request('set_customer_status',{id:x.id,status:'active'});
          await refresh();
          showMessage('Pelanggan diaktifkan dan PPPoE diaktifkan kembali.');
        }
      }
    } catch(err) {
      showMessage(err.message,true);
    }
  });

  $('invoicePeriod').value = initial.month || defaultPeriod();

  refresh();
  setInterval(refresh,30000);
})();
</script>
</body>
</html>
