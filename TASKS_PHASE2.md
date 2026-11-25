# TASKS_PHASE2.md — Daftar Tugas Implementasi KOPDES (Fase 2)

Daftar tugas ini memecah seluruh 4 Milestone Fase 2 pada [PRD_PHASE2.md](./PRD_PHASE2.md) (melanjutkan [TASKS.md](./TASKS.md) & modul Biaya Operasional/SHU/Ekspor) menjadi tugas-tugas kecil yang spesifik. **Setiap tugas hanya menyentuh 1 lapisan kerja** (konfigurasi, migrasi, model, middleware/policy, resource/request, service, controller/routes, Vue UI, atau feature test) agar implementasi rapi, terukur, dan bebas regresi terhadap MVP.

---

## Milestone 6: Harga Khusus Anggota Member (`member_price` & Snapshot Diskon)

> **Tujuan:** Menambahkan pengaturan harga khusus member pada katalog produk terpusat, menerapkan harga member secara otomatis lintas cabang di layar POS Vue, menyimpan snapshot diskon pada transaksi, dan menampilkan "Total Hemat Member" pada struk thermal.

### Migrasi Database & Backfill
- [x] **Task 6.1 (Migrasi — Alter Produk & Transaksi untuk Harga Member):** Buat migrasi `add_member_pricing_columns` untuk menambahkan:
  - `products.member_price` (`DECIMAL(15,2)`, `NULL`, setelah `selling_price`);
  - `transactions.regular_subtotal` (`DECIMAL(15,2)`, `DEFAULT 0.00`) dan `transactions.member_discount_total` (`DECIMAL(15,2)`, `DEFAULT 0.00`);
  - `transaction_items.regular_price_snapshot` (`DECIMAL(15,2)`, `DEFAULT 0.00`), `transaction_items.member_discount_snapshot` (`DECIMAL(15,2)`, `DEFAULT 0.00`), dan `transaction_items.is_member_price_applied` (`BOOLEAN`, `DEFAULT FALSE`);
  - Sertakan query *backfill* di akhir `up()`: `UPDATE transactions SET regular_subtotal = subtotal` dan `UPDATE transaction_items SET regular_price_snapshot = unit_price_snapshot`.

### Model & Validasi Master Produk (`FR-P2-09`)
- [x] **Task 6.2 (Model — Pembaruan Casts & Fillable):** Perbarui `$fillable` dan `$casts` pada model `Product` (`member_price => 'decimal:2'`), `Transaction` (`regular_subtotal => 'decimal:2'`, `member_discount_total => 'decimal:2'`), serta `TransactionItem` (`regular_price_snapshot => 'decimal:2'`, `member_discount_snapshot => 'decimal:2'`, `is_member_price_applied => 'boolean'`).
- [x] **Task 6.3 (Form Request & Controller — Produk):** Perbarui `StoreProductRequest` dan `UpdateProductRequest` agar memvalidasi `member_price` (`nullable`, `numeric`, `min:0`, `lte:selling_price`) serta pastikan `ProductController` meneruskan dan menyimpan `member_price`.
- [x] **Task 6.4 (Vue — Halaman Master Produk):** Perbarui `resources/js/Pages/Products/Form.vue` (input Harga Member opsional + banner peringatan visual kuning *non-blocking* bila `member_price < cost_price`) dan `resources/js/Pages/Products/Index.vue` (tampilkan kolom Harga Normal dan Harga Member, tampilkan `-` jika `null`).

### Lookup POS, Checkout Service & Frontend POS (`FR-P2-10`)
- [x] **Task 6.5 (Controller & Service — Lookup & Checkout Harga Member):**
  - Perbarui `PosLookupController::productByBarcode` agar turut mengembalikan `member_price`.
  - Perbarui `CheckoutService` agar menentukan harga aktual di server: jika `member_id` valid & berstatus `'active'` (lintas `home_branch_id`) dan `product.member_price !== null`, gunakan `member_price` (`is_member_price_applied = true`); jika tidak, gunakan `selling_price`. Simpan `regular_price_snapshot`, `unit_price_snapshot`, `member_discount_snapshot`, `regular_subtotal`, `member_discount_total`, dan `total_amount`.
- [x] **Task 6.6 (Vue — Kalkulasi Dinamis Harga Member di POS):** Perbarui `resources/js/Pages/Pos/Index.vue` dan `resources/js/Pages/Pos/Partials/PaymentModal.vue` agar:
  - Keranjang langsung menghitung ulang harga seluruh item begitu `selectedMember` di-tap (`F2`) atau dilepas;
  - Baris item dengan harga member menampilkan harga normal dicoret + harga member + badge hemat;
  - Ringkasan keranjang menampilkan baris **"Total Hemat Member"**;
  - `idempotency_key` otomatis di-regenerate setiap kali `selectedMember` atau komposisi/kuantitas keranjang berubah sebelum checkout.
- [x] **Task 6.7 (Vue — Struk Thermal Hemat Member):** Perbarui `resources/js/Pages/Pos/Partials/ReceiptThermal.vue` untuk mencetak baris **"Total Hemat Member"** pada ringkasan struk apabila `transaction.member_discount_total > 0`.

### Pengujian Milestone 6
- [x] **Task 6.8 (Feature Test — Milestone 6):** Buat `tests/Feature/Milestone6MemberPricingTest.php` yang menguji: (a) `member_price > selling_price` ditolak HTTP `422`, sedangkan `member_price < cost_price` tetap diperbolehkan tersimpan; (b) transaksi member lintas cabang otomatis memakai `member_price` dan mencatat `regular_subtotal`, `member_discount_total`, serta kolom snapshot item secara akurat; (c) produk tanpa `member_price` otomatis memakai `selling_price`; (d) pembeli non-member tetap dikenakan `selling_price`; (e) perubahan `products.member_price` setelah transaksi tidak mengubah snapshot transaksi historis.

---

## Milestone 7: Manajemen Shift Kasir (Buka/Tutup Blind Count, Mutasi Kas Laci & Force-Close)

> **Tujuan:** Mengontrol penuh siklus operasional kasir per shift (1 kasir maksimal 1 shift `open`, paralel per cabang), mutasi kas laci terpisah dari biaya operasional, tutup shift metode **Blind Count** dengan pemblokiran transaksi Midtrans `pending`, serta monitoring & *force-close* oleh Admin Cabang.

### Migrasi Database Shift Kasir
- [x] **Task 7.1 (Migrasi — Tabel Shift & Alter Transactions):** Buat migrasi untuk:
  - Tabel `cashier_shifts` (`shift_code` UNIQUE, `branch_id`, `cashier_id`, `closed_by_user_id` nullable, `status` ENUM `'open'/'closed'`, `is_force_closed`, `opening_cash`, `cash_sales_total`, `midtrans_sales_total`, `cash_in_total`, `cash_out_total`, `cash_refund_total`, `expected_closing_cash` nullable, `actual_closing_cash` nullable, `variance` nullable, `opening_notes`, `closing_notes`, `opened_at`, `closed_at`, composite index `(branch_id, status, opened_at)` dan `(cashier_id, status)`);
  - Tabel `shift_cash_movements` (immutable tanpa `updated_at`, `cashier_shift_id`, `branch_id`, `user_id`, `type` ENUM `'cash_in'/'cash_out'`, `amount`, `notes`, `created_at`);
  - Alter `transactions`: tambahkan `cashier_shift_id` (`BIGINT UNSIGNED`, nullable, FK `-> cashier_shifts(id) ON DELETE RESTRICT`, indexed, setelah `cashier_id`).

### Model, Middleware, Policy & Resource Blind Count
- [x] **Task 7.2 (Model — Shift & Mutasi Kas):** Buat model `CashierShift` (dengan `HasRegionScope`, `$casts`, relasi `branch`, `cashier`, `closedBy`, `cashMovements`, `transactions`, `cashReturns`) dan `ShiftCashMovement` (`public const UPDATED_AT = null;`, `HasRegionScope`, relasi `shift`, `branch`, `user`), serta tambahkan relasi `cashierShift()` pada model `Transaction` dan `shifts()` pada `User` & `Branch`.
- [x] **Task 7.3 (Middleware & Policy — Shift):**
  - Buat middleware `App\Http\Middleware\EnsureActiveShift` (daftarkan alias `pos.shift` di `bootstrap/app.php`) yang menolak request checkout dengan HTTP `422` jika kasir belum memiliki `CashierShift` berstatus `'open'`.
  - Buat `CashierShiftPolicy` sesuai Matriks RBAC Fase 2 (`kasir` hanya boleh mengelola shift miliknya di cabangnya; `admin_cabang` boleh melihat & *force-close* shift di cabangnya; `admin_provinsi`/`admin_kabupaten` read-only sesuai wilayah; `admin_nasional` akses penuh).
- [x] **Task 7.4 (Resource — Keamanan Blind Count):** Buat `App\Http\Resources\CashierShiftPosResource` yang secara ketat **menghapus/tidak menyertakan** kolom `cash_sales_total`, `midtrans_sales_total`, `cash_in_total`, `cash_out_total`, `cash_refund_total`, `expected_closing_cash`, dan `variance` selama `$shift->status === 'open'`, dan baru mengembalikannya setelah `status === 'closed'`.

### Form Request & Service Shift (`FR-P2-01` s.d. `FR-P2-04`)
- [x] **Task 7.5 (Form Request — Shift):** Buat `OpenShiftRequest` (`opening_cash` numeric `>= 0`, `opening_notes` nullable), `StoreShiftCashMovementRequest` (`type` in `cash_in,cash_out`, `amount` numeric `> 0`, `notes` required string), `CloseShiftRequest` (`actual_closing_cash` numeric `>= 0`, `closing_notes` nullable), dan `ForceCloseShiftRequest` (`actual_closing_cash` numeric `>= 0`, `closing_notes` required string).
- [x] **Task 7.6 (Service — Siklus Hidup Shift):** Buat `App\Services\CashierShiftService`:
  - `openShift(User $cashier, array $data)`: di dalam `DB::transaction()`, cegah duplikasi shift `'open'` untuk kasir yang sama (`lockForUpdate()`), generate `shift_code` (`SHF-{BRANCH_CODE}-{YYYYMMDD}-{SEQ3}`), simpan shift `'open'`.
  - `recordCashMovement(CashierShift $shift, User $cashier, array $data)`: pastikan shift `'open'` dan milik kasir, catat baris *append-only* ke `shift_cash_movements`.
  - `closeShift(CashierShift $shift, User $actor, array $data, bool $isForceClose = false)`: di dalam `DB::transaction()` + `lockForUpdate()`, cek transaksi `pending` milik shift (lempar `ValidationException` 422 dengan daftar `invoice_number` bila ada), hitung `cash_sales_total`, `midtrans_sales_total`, `cash_in_total`, `cash_out_total`, `cash_refund_total`, `expected_closing_cash`, dan `variance`, lalu kunci `status = 'closed'`, `closed_by_user_id = $actor->id`, `is_force_closed = $isForceClose`, `closed_at = now()`.
- [x] **Task 7.7 (Service — Integrasi Checkout ke Shift Aktif):** Perbarui `CheckoutService` agar secara otomatis mengaitkan setiap transaksi POS baru ke `cashier_shift_id` milik shift `'open'` kasir yang sedang bertugas (lempar `ValidationException` 422 bila tidak ada shift aktif).

### Controller & Routes Shift (POS & Admin)
- [x] **Task 7.8 (Controller & Routes — POS Shift):** Buat `PosShiftController` (`open` `POST /pos/shifts/open`, `recordCashMovement` `POST /pos/shifts/{shift}/cash-movements`, `close` `POST /pos/shifts/{shift}/close`, `summary` `GET /pos/shifts/{shift}`) terproteksi `auth`, `role:kasir`, `pos.unlocked`, serta terapkan middleware `pos.shift` pada route `POST /pos/checkout` dan kirim `activeShift` (via `CashierShiftPosResource`) serta `pendingCountForShift` dari `PosController::index`.
- [x] **Task 7.9 (Controller & Routes — Dashboard Admin Shift):** Buat `ShiftController` (`index` `GET /shifts`, `show` `GET /shifts/{shift}`, `forceClose` `POST /shifts/{shift}/force-close`) terproteksi `CashierShiftPolicy` dan `CashierShift::forUser($request->user())`.

### Frontend Vue 3 Shift (POS & Dashboard Admin)
- [x] **Task 7.10 (Vue — Modal Buka Shift, Mutasi Kas & Tutup Shift Blind Count):** Buat:
  - `resources/js/Pages/Pos/Partials/OpenShiftModal.vue` (modal wajib tidak bisa ditutup sebelum kasir membuka shift);
  - `resources/js/Pages/Pos/Partials/ShiftCashMovementModal.vue` (shortcut `F4`, form `cash_in`/`cash_out` + riwayat mutasi shift berjalan);
  - `resources/js/Pages/Pos/Partials/CloseShiftModal.vue` (shortcut `F10`, tampilkan blokir + daftar invoice jika ada `pending`, input Blind Count tanpa angka ekspektasi, lalu tampilkan ringkasan hasil & selisih setelah submit);
  - `resources/js/Pages/Pos/Partials/ShiftClosingReceipt.vue` (template cetak struk thermal `@media print` 58mm/80mm ringkasan tutup shift).
- [x] **Task 7.11 (Vue — Integrasi Shift ke POS & Lock Layar):** Perbarui `resources/js/Pages/Pos/Index.vue` dan `resources/js/Pages/Pos/Partials/PosLockModal.vue` untuk:
  - Menampilkan badge shift aktif di header POS + tombol/shortcut `F4` (Kas Laci) dan `F10` (Tutup Shift);
  - Memastikan saat Kasir B membuka kunci layar milik Kasir A, shift Kasir A tetap `'open'`, keranjang direset, dan jika Kasir B belum punya shift `'open'`, `OpenShiftModal.vue` langsung tampil.
- [x] **Task 7.12 (Vue — Halaman Dashboard Shift & Force-Close):** Buat `resources/js/Pages/Shifts/Index.vue` (filter wilayah/cabang, status, tanggal, badge warna selisih merah/hijau/kuning) dan `resources/js/Pages/Shifts/Show.vue` (rincian penjualan, mutasi kas laci, daftar transaksi shift, serta modal Force-Close bagi `admin_cabang` / `admin_nasional`), lalu tambahkan menu **Shift Kasir** di `Layouts/AuthenticatedLayout.vue`.

### Pembaruan Test Existing & Pengujian Milestone 7
- [x] **Task 7.13 (Penyesuaian Test Existing Milestone 4):** Perbarui `tests/Feature/Milestone4PosCashAndMemberTest.php` dan `tests/Feature/Milestone4MidtransAndSchedulerTest.php` agar membuat `CashierShift` berstatus `'open'` sebelum memanggil `POST /pos/checkout`, lalu pastikan seluruh test Milestone 1–6 tetap lulus 100%.
- [x] **Task 7.14 (Feature Test — Milestone 7):** Buat `tests/Feature/Milestone7CashierShiftTest.php` yang menguji: (a) checkout ditolak HTTP `422` bila kasir belum membuka shift; (b) 1 kasir tidak boleh punya 2 shift `'open'`, namun 2 kasir di cabang yang sama boleh membuka shift paralel; (c) payload `GET /pos` saat shift `'open'` tidak membocorkan `expected_closing_cash`, `cash_sales_total`, atau `variance`; (d) tutup shift ditolak HTTP `422` bila masih ada transaksi Midtrans `'pending'` pada shift tersebut; (e) rumus `expected_closing_cash` dan `variance` akurat (memasukkan `opening_cash`, penjualan tunai, `cash_in`, `cash_out`, dan mengecualikan penjualan Midtrans); (f) `shift_cash_movements` tidak mengubah total `operational_expenses`; (g) `admin_cabang` dapat melakukan force-close shift cabangnya namun ditolak HTTP `403` untuk cabang lain.

---

## Milestone 8: Retur Parsial & Full Void Terpusat (Admin Cabang)

> **Tujuan:** Menyediakan fitur retur parsial dan pembatalan penuh (*Full Void*) berbasis item untuk transaksi `paid` maksimal 7 hari oleh Admin Cabang melalui Dashboard, dengan pemisahan kondisi fisik (`restockable` vs `damaged`), pencatatan refund manual, dan integrasi potongan laci shift aktif.

### Konfigurasi & Migrasi Database Retur
- [x] **Task 8.1 (Konfigurasi — Aturan Retur):** Buat file konfigurasi `config/kopdes.php` berisi `'return_max_days' => (int) env('KOPDES_RETURN_MAX_DAYS', 7)` (tanpa mengedit `.env`).
- [x] **Task 8.2 (Migrasi — Tabel Retur & Alter Transactions):** Buat migrasi untuk:
  - Tabel `transaction_returns` (`return_number` UNIQUE, `transaction_id`, `branch_id`, `processed_by_user_id`, `member_id` nullable, `member_home_branch_id` nullable, `return_type` ENUM `'partial'/'full'`, `refund_method` ENUM `'cash_refund'/'manual_transfer'`, `cash_source` ENUM `'branch_cash'/'active_shift'` nullable, `refund_cashier_shift_id` nullable FK `-> cashier_shifts(id)`, `refund_reference` nullable, `total_refund_amount`, `total_restocked_cogs`, `total_damaged_cogs`, `reason`, `status` ENUM `'completed'`, `returned_at`, composite index `(branch_id, status, returned_at)` dan `(member_id, status, returned_at)`);
  - Tabel `transaction_return_items` (`transaction_return_id`, `transaction_item_id`, `product_id`, `product_name_snapshot`, `qty_returned`, `condition` ENUM `'restockable'/'damaged'`, `unit_price_snapshot`, `cost_price_snapshot`, `subtotal_refund`, `subtotal_cogs`);
  - Alter `transactions`: tambahkan `return_status` (`ENUM('none','partial','full')`, `DEFAULT 'none'`, indexed, setelah `payment_status`).

### Model, Policy & Form Request Retur (`FR-P2-05` s.d. `FR-P2-08`)
- [x] **Task 8.3 (Model — Retur):** Buat model `TransactionReturn` (`HasRegionScope`, `$casts`, relasi `transaction`, `branch`, `processedBy`, `member`, `memberHomeBranch`, `refundShift`, `items`) dan `TransactionReturnItem` (relasi `transactionReturn`, `transactionItem`, `product`), serta tambahkan relasi `returns()` pada `Transaction` dan `returnItems()` + accessor `returned_qty` / `remaining_returnable_qty` pada `TransactionItem`.
- [x] **Task 8.4 (Policy — Retur):** Buat `TransactionReturnPolicy` sesuai Matriks RBAC Fase 2 (`admin_cabang` boleh melihat & memproses retur khusus transaksi di `branch_id`-nya; `admin_nasional` akses semua cabang; `admin_provinsi`/`admin_kabupaten` read-only sesuai wilayah; `kasir` ditolak HTTP `403`; tidak ada izin update/delete karena immutable).
- [x] **Task 8.5 (Form Request — Retur):** Buat `StoreTransactionReturnRequest` yang memvalidasi `transaction_id`, `reason` (wajib), `refund_method` (`cash_refund`/`manual_transfer`), `cash_source` (`required_if:refund_method,cash_refund` in `branch_cash,active_shift`), `refund_cashier_shift_id` (`required_if:cash_source,active_shift`), `refund_reference` (`required_if:refund_method,manual_transfer`, `min:3`), dan `items` (array `min:1` berisi `transaction_item_id`, `qty_returned` integer `>= 1`, `condition` in `restockable,damaged`).

### Service, Controller & Routes Retur
- [x] **Task 8.6 (Service — Eksekusi Retur Atomik):** Buat `App\Services\TransactionReturnService::processReturn()` di dalam `DB::transaction()`:
  - Kunci baris `transactions` (`lockForUpdate()`) dan muat item; validasi `payment_status === 'paid'`, `return_status !== 'full'`, dan usia `paid_at` tidak melewati `config('kopdes.return_max_days', 7)` hari kalender;
  - Jika `cash_source === 'active_shift'`, kunci baris `cashier_shifts` (`lockForUpdate()`), validasi `branch_id` sama dan `status === 'open'` (lempar `ValidationException` 422 jika shift sudah ditutup);
  - Hitung ulang sisa kuantitas yang boleh diretur per `transaction_item_id` (tolak `422` jika melebihi sisa);
  - Hitung `subtotal_refund` (`unit_price_snapshot * qty_returned`), `total_restocked_cogs`, dan `total_damaged_cogs`, lalu generate `return_number` (`RTN-{BRANCH_CODE}-{YYYYMMDD}-{SEQ4}`);
  - Simpan `transaction_returns` dan `transaction_return_items`;
  - Untuk item berkondisi `'restockable'`, urutkan `product_id ASC`, kunci `branch_stocks` (`lockForUpdate()`), tambah `quantity += qty_returned`, dan tulis `stock_movements` (`type = 'void_return'`, `qty_change = +qty_returned`, `reference_type = TransactionReturn::class`, `reference_id = $return->id`);
  - Perbarui `transactions.return_status` menjadi `'full'` jika seluruh item telah habis diretur, atau `'partial'` jika masih ada sisa.
- [x] **Task 8.7 (Controller & Routes — Retur):** Buat `TransactionReturnController` (`index` `GET /returns`, `create` `GET /transactions/{transaction}/return`, `store` `POST /returns`, `show` `GET /returns/{transactionReturn}`) terproteksi `TransactionReturnPolicy` dan `TransactionReturn::forUser($request->user())`.

### Frontend Vue 3 Retur (Dashboard Admin)
- [x] **Task 8.8 (Vue — Form Retur & Full Void):** Buat `resources/js/Pages/Returns/Create.vue` (`<script setup>`) berisi ringkasan invoice asal, tombol pintas **"Pilih Semua (Full Void)"**, daftar item dengan sisa kuantitas yang bisa diretur, input `qty_returned` & pilihan kondisi (`Layak Jual (Restock)` vs `Rusak / Cacat (Tidak Restock)`), input alasan, serta pilihan metode refund (`cash_refund` + dropdown shift `'open'` cabang / kas cabang, atau `manual_transfer` + no. referensi). Tambahkan tombol **"Proses Retur / Void"** pada tampilan riwayat/detail transaksi di dashboard untuk `admin_cabang` dan `admin_nasional`.
- [x] **Task 8.9 (Vue — Riwayat & Bukti Retur Siap Cetak):** Buat `resources/js/Pages/Returns/Index.vue` (tabel riwayat retur dengan filter wilayah/cabang, tanggal, metode refund) dan `resources/js/Pages/Returns/Show.vue` (detail retur immutable + tampilan cetak bukti retur `@media print`), serta tambahkan menu **Retur & Void** di `Layouts/AuthenticatedLayout.vue` (sembunyikan untuk `kasir`).

### Pengujian Milestone 8
- [x] **Task 8.10 (Feature Test — Milestone 8):** Buat `tests/Feature/Milestone8TransactionReturnTest.php` yang menguji: (a) retur parsial lalu retur sisa mengubah `return_status` dari `none` -> `partial` -> `full` dan menolak upaya retur melebihi sisa kuantitas (HTTP `422`); (b) transaksi berusia lebih dari 7 hari atau berstatus selain `paid` ditolak HTTP `422`; (c) item `restockable` menambah `branch_stocks.quantity` dan menulis `stock_movements` bertipe `void_return`, sedangkan item `damaged` tidak menambah stok dan tercatat di `total_damaged_cogs`; (d) `cash_refund` bersumber `active_shift` mengurangi `expected_closing_cash` shift saat ditutup, sedangkan `branch_cash` & `manual_transfer` tidak mengubah ekspektasi laci shift; (e) `cash_refund` ke shift yang sudah berstatus `closed` ditolak HTTP `422`; (f) `kasir` dan `admin_cabang` cabang lain ditolak HTTP `403`.

---

## Milestone 9: Sinkronisasi Laporan Keuangan, SHU & Ekspor Excel/PDF Fase 2

> **Tujuan:** Menyelaraskan `FinancialReportService`, Dashboard, Laporan Penjualan/Laba Rugi, Laporan SHU & Rekap Member, serta seluruh Ekspor Excel/PDF dengan koreksi Retur (`restockable` vs `damaged`), dan menambahkan Ekspor Excel/PDF untuk Riwayat Shift & Riwayat Retur.

### Service Laporan Keuangan & SHU (`FR-P2-11`)
- [x] **Task 9.1 (Service — Koreksi Retur pada FinancialReportService):** Perbarui `App\Services\FinancialReportService` agar:
  - Menghitung `gross_revenue` (Omzet Bruto `paid`), `total_returns` (`SUM(transaction_returns.total_refund_amount)` berdasarkan `returned_at`), `net_revenue` (`gross_revenue - total_returns`), `gross_cogs`, `restocked_cogs`, `damaged_cogs`, `net_cogs` (`gross_cogs - restocked_cogs`), `gross_profit` (`net_revenue - net_cogs`), `total_operational_expenses`, `net_profit` (`gross_profit - total_operational_expenses`), serta `total_member_discount` (informasi total penghematan member);
  - Mengoreksi kalkulasi **SHU** menggunakan `net_profit` pasca-retur;
  - Mengoreksi **Rekap Belanja Member** (KOPDES Asal, Lintas Cabang, dan Total Akumulasi) dengan mengurangi `transaction_returns.total_refund_amount` milik member tersebut pada periode & cakupan yang sama.

### Pembaruan UI Dashboard & Laporan
- [x] **Task 9.2 (Vue — Pembaruan Dashboard & Laporan Keuangan/SHU):** Perbarui `resources/js/Pages/Dashboard/Index.vue`, `resources/js/Pages/Reports/Sales.vue`, `resources/js/Pages/Reports/Members.vue`, dan halaman SHU agar menampilkan komponen **Omzet Bruto**, **Total Retur**, **Omzet Bersih**, **HPP Bersih**, **Kerugian Barang Rusak Retur (`damaged_cogs`)**, **Laba Kotor**, **Biaya Operasional**, dan **Laba Bersih / Dasar SHU** secara transparan.

### Sinkronisasi & Penambahan Ekspor Excel/PDF (`FR-P2-12`)
- [x] **Task 9.3 (Export — Sinkronisasi Laba Rugi & SHU):** Perbarui class Export Excel dan template Blade PDF untuk Laporan Penjualan/Laba Rugi serta Laporan SHU/Member yang sudah ada agar mencerminkan angka koreksi Retur (`net_revenue`, `total_returns`, `damaged_cogs`, `net_profit`) dan rincian penghematan member.
- [x] **Task 9.4 (Export — Ekspor Riwayat Shift Excel & PDF):** Buat class Export Excel dan template Blade PDF untuk Laporan Riwayat Shift Kasir (memuat kode shift, cabang, kasir, waktu buka/tutup, modal awal, penjualan tunai, penjualan Midtrans, kas masuk/keluar, refund tunai laci, ekspektasi, fisik aktual, selisih, dan status force-close) beserta endpoint `GET /shifts/export/excel` & `GET /shifts/export/pdf` terproteksi `ReportFilterRequest` / `CashierShiftPolicy`.
- [x] **Task 9.5 (Export — Ekspor Riwayat Retur Excel & PDF):** Buat class Export Excel dan template Blade PDF untuk Laporan Riwayat Retur & Void (memuat nomor retur, nomor invoice, cabang, admin pengeksekusi, metode refund, nilai refund, HPP restock, HPP rusak, dan alasan) beserta endpoint `GET /returns/export/excel` & `GET /returns/export/pdf` terproteksi `ReportFilterRequest` / `TransactionReturnPolicy`.
- [x] **Task 9.6 (Vue — Tombol Ekspor pada Shift & Retur):** Tambahkan tombol unduh **Export Excel** dan **Export PDF** (menggunakan full browser download dengan query filter aktif) pada `resources/js/Pages/Shifts/Index.vue` dan `resources/js/Pages/Returns/Index.vue`.

### Pengujian Milestone 9 & Verifikasi Regresi Penuh
- [x] **Task 9.7 (Feature Test — Milestone 9):** Buat `tests/Feature/Milestone9Phase2ReportsAndExportsTest.php` yang membuktikan: (a) retur berkondisi `restockable` menurunkan Omzet Bersih dan HPP Bersih sesuai snapshot transaksi asal; (b) retur berkondisi `damaged` menurunkan Omzet Bersih tanpa menurunkan HPP Bersih sehingga Laba Kotor berkurang sebesar nilai barang rusak; (c) Laba Bersih, dasar pembagian SHU, dan akumulasi belanja member (Asal & Lintas Cabang) otomatis terkoreksi oleh retur; (d) endpoint ekspor Excel & PDF untuk Laba Rugi, SHU, Shift, dan Retur mengembalikan HTTP `200` dengan `Content-Type` sesuai untuk scope sah dan HTTP `403` saat manipulasi parameter wilayah di luar scope.
- [x] **Task 9.8 (Verifikasi Regresi & Build Akhir):** Jalankan seluruh test suite (`php artisan test` mencakup Milestone 1–9 dan `OperationalExpenseAndExportTest`) hingga lulus 100%, serta verifikasi `npm run build` selesai tanpa error.