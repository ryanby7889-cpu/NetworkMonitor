<?php
/*
 * Sprint 3.1 — tombol / status reaktivasi pada Billing.
 *
 * Include setelah billing-reactivate.js dipanggil.
 * Bisa ditempatkan di bagian invoice atau customer action.
 */
?>
<button
    type="button"
    class="billing-btn billing-btn-success billing-btn-small"
    data-reactivate-invoice="<?= (int)($invoice['id'] ?? 0) ?>"
>
    <i class="bi bi-wifi"></i> Aktifkan PPPoE
</button>
