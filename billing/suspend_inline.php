<?php
/*
 * Sprint 3.0
 * Inline panel untuk billing/index.php
 *
 * Tempatkan setelah card Statistik Billing dan sebelum
 * bagian "Pelanggan PPPoE" atau "Tagihan PPPoE".
 *
 * CSS dan JS dipanggil dari billing/index.php, bukan di file ini.
 */
?>
<section class="billing-s30-panel" data-billing-s30>
    <div class="billing-s30-head">
        <div>
            <h3><i class="bi bi-shield-lock"></i> Kontrol Suspend Otomatis</h3>
            <p>
                Sistem mencari invoice BELUM BAYAR yang melewati
                jatuh tempo + masa toleransi.
            </p>
        </div>
        <span class="billing-s30-badge" data-s30-badge>TEST</span>
    </div>

    <div class="billing-s30-controls">
        <label class="billing-s30-field">
            Masa Toleransi (hari)
            <input type="number" min="0" max="30"
                   value="3" data-s30-grace>
        </label>

        <button type="button"
                class="billing-s30-btn check"
                data-s30-check>
            <i class="bi bi-search"></i>
            Cek Terlambat
        </button>

        <button type="button"
                class="billing-s30-btn live"
                data-s30-live
                disabled>
            <i class="bi bi-wifi-off"></i>
            Proses Suspend LIVE
        </button>

        <a class="billing-s30-btn audit"
           href="../billing/suspend_log.php">
            <i class="bi bi-clock-history"></i>
            Audit Log
        </a>
    </div>

    <div class="billing-s30-info"
         data-s30-info>
        Pemeriksaan awal berjalan dalam mode TEST.
    </div>

    <div class="billing-s30-table-wrap"
         data-s30-table-wrap>
        <table class="billing-s30-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Pelanggan</th>
                    <th>PPPoE</th>
                    <th>Invoice</th>
                    <th>Jatuh Tempo</th>
                    <th>Terlambat</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody data-s30-body></tbody>
        </table>
    </div>
</section>
