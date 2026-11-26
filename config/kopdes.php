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

    /*
    |--------------------------------------------------------------------------
    | Mode Demo Portofolio & Simulator Hardware (FR-P3-13)
    |--------------------------------------------------------------------------
    | Default WAJIB false demi keamanan produksi. Jika true, tombol Quick-Fill
    | Login dan Simulator Hardware POS/RFID diaktifkan di frontend & endpoint
    | sampel demo dibuka untuk role kasir.
    */
    'demo_mode' => (bool) env('APP_DEMO_MODE', false),
];
