# Page Override — Dashboard Keuangan Admin (`Dashboard/Index.vue`)

Dokumen ini melengkapi [MASTER.md](../MASTER.md) khusus untuk halaman **Dashboard Keuangan & KPI Berjenjang KOPDES** (`resources/js/Pages/Dashboard/Index.vue`).

---

## 1. Hierarki Visual Dashboard Keuangan

1. **Header Bar Halaman:**
   - Judul halaman + indikator periode aktif (`start_date` s.d. `end_date`) + penjelasan status data (`LUNAS / paid`, Snapshot HPP).
   - Grup tombol aksi cepat di sisi kanan:
     - **Export PDF** (`.btn-kopdes-outline`) -> `reports.sales.export` (`format: 'pdf'`)
     - **Export Excel** (`.btn-kopdes-emerald`) -> `reports.sales.export` (`format: 'excel'`)
     - **Laporan Penjualan** (`.btn-kopdes-secondary`) -> `reports.sales`
     - **Rekap Member (SHU)** (`.btn-kopdes-primary`) -> `reports.members`
2. **Panel Filter Periode & Drill-Down Wilayah:**
   - Menyediakan tombol preset cepat (*Bulan Ini*, *Hari Ini*, *7 Hari Terakhir*) di samping input `Tanggal Mulai`, `Tanggal Akhir`, `Metode Bayar`, dan dropdown drill-down dinamis sesuai role (`Provinsi` → `Kabupaten/Kota` → `Cabang KOPDES`).
3. **Baris 4 Kartu KPI Utama (`grid-cols-1 sm:grid-cols-2 xl:grid-cols-4`):**
   - **Total Omzet (`paid`):** Aksen `kopdes-700`, menampilkan nilai Rupiah + pembanding HPP Snapshot.
   - **Total Laba Kotor (Snapshot):** Aksen `emerald-600`, menampilkan nilai Rupiah (`SUM(total_amount - total_cogs)`).
   - **Margin Laba Kotor (%):** Aksen `amber-500`, menampilkan persentase + mini progress bar rasio margin.
   - **Total Transaksi Lunas:** Aksen `blue-600`, menampilkan jumlah transaksi + rata-rata nilai transaksi (*Rata-rata / Trx*).
4. **Baris Analitik Visual 2 Kolom (`grid-cols-1 lg:grid-cols-12`):**
   - **Kolom Kiri (`lg:col-span-7`): Distribusi Kanal Pembayaran (`Tunai` vs. `Midtrans Snap`)** dengan *stacked proportion bar* visual dan dua kartu rincian kanal.
   - **Kolom Kanan (`lg:col-span-5`): Komposisi Omzet (HPP Snapshot vs. Laba Kotor)** dengan *stacked bar* rasio HPP & Laba Kotor serta ringkasan cepat fondasi SHU.
5. **Tabel Ringkasan Kinerja Cabang KOPDES:**
   - Menampilkan performa per cabang dalam scope wilayah dengan kontribusi omzet visual, pecahan `Tunai` & `Midtrans`, `Total Omzet`, `Laba Kotor`, badge `Margin %`, dan baris **Total Keseluruhan** di footer tabel.
