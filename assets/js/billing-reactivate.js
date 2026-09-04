/* NetMonitor Sprint 3.1 FINAL
 * Auto reactivation is executed server-side by api/billing.php
 * immediately after a successful payment.
 *
 * This helper is kept for optional manual/retry actions.
 */
(function () {
    'use strict';

    window.billingReactivate = async function (invoiceId) {
        if (!invoiceId) {
            throw new Error('ID invoice tidak valid.');
        }

        const response = await fetch('../api/billing_reactivate.php', {
            method: 'POST',
            headers: {
                'Accept': 'application/json'
            },
            body: new URLSearchParams({
                invoice_id: String(invoiceId)
            })
        });

        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Response reaktivasi tidak valid.');
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Reaktivasi PPPoE gagal.');
        }

        return data;
    };
})();
