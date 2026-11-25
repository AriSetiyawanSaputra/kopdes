<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Batas Maksimal Hari Retur & Pembatalan Transaksi (FR-P2-05)
    |--------------------------------------------------------------------------
    | Transaksi berstatus 'paid' hanya dapat diretur atau di-void maksimal
    | sebanyak jumlah hari kalender sejak waktu pembayaran (paid_at).
    */
    'return_max_days' => (int) env('KOPDES_RETURN_MAX_DAYS', 7),
];
