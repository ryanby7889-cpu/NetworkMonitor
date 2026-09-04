/* NetMonitor Billing Smart Filter - Sprint 2.1 */
(() => {
  const params = new URLSearchParams(window.location.search);
  const status = params.get('status');
  const customer = params.get('customer');
  const invoice = params.get('invoice');
  const month = params.get('month');

  const search = document.getElementById('customerSearch');
  const paymentSearch = document.getElementById('paymentSearch');

  const apply = () => {
    if (search && customer) {
      search.value = customer;
      search.dispatchEvent(new Event('input', {bubbles:true}));
    }

    if (paymentSearch && invoice) {
      paymentSearch.value = invoice;
      paymentSearch.dispatchEvent(new Event('input', {bubbles:true}));
    }

    // Optional status/month filtering hooks for existing Billing page.
    if (status && window.billingSmartFilterStatus) {
      window.billingSmartFilterStatus(status, month);
    }
    if (invoice && window.billingSmartFilterInvoice) {
      window.billingSmartFilterInvoice(invoice);
    }
    if (customer && window.billingSmartFilterCustomer) {
      window.billingSmartFilterCustomer(customer);
    }

    const filter = document.getElementById('smartFilterBanner');
    if (!filter || !(status || customer || invoice)) return;

    const parts = [];
    if (status) parts.push('Status: ' + status);
    if (customer) parts.push('Pelanggan: ' + customer);
    if (invoice) parts.push('Invoice: ' + invoice);
    if (month) parts.push('Periode: ' + month);

    filter.hidden = false;
    const label = filter.querySelector('[data-smart-filter-text]');
    if (label) label.textContent = parts.join(' • ');
  };

  document.addEventListener('DOMContentLoaded', apply);
})();
