# TASKS.md — Daftar Tugas Implementasi KOPDES (MVP)

Daftar tugas ini memecah seluruh 5 Milestone pada [PRD.md](./PRD.md) menjadi tugas-tugas kecil yang spesifik. **Setiap tugas hanya menyentuh 1 lapisan kerja** (konfigurasi, migrasi, model, seeder, middleware/policy, form request, service, controller/routes, Vue UI, atau feature test) agar implementasi rapi, terukur, dan mudah diuji.

---

## Milestone 1: Fondasi Proyek, Skema Database, Seeder Wilayah & RBAC

> **Tujuan:** Menyiapkan infrastruktur Laravel + Inertia + Vue 3 + Tailwind CSS, 14 tabel domain, seeder wilayah & role awal, middleware/policy RBAC, serta login & layout dasar Admin.

### Konfigurasi Dasar
- [x] **Task 1.1 (Konfigurasi):** Tambahkan blok konfigurasi `midtrans` (`server_key`, `client_key`, `is_production`, `snap_url`, `api_url`) di `config/services.php` (menggunakan `env()` tanpa mengedit `.env`), daftarkan rute API (`routes/api.php`) di `bootstrap/app.php`, dan daftarkan pengecualian CSRF `$middleware->validateCsrfTokens(except: ['api/webhooks/midtrans'])` di `bootstrap/app.php`.

### Migrasi Database (Tanpa Menghapus Migrasi Bawaan)
- [x] **Task 1.2 (Migrasi — Wilayah & Cabang):** Buat migrasi untuk tabel `provinces` (`CHAR(2)`), `regencies` (`CHAR(4)`), `districts` (`CHAR(7)`), `villages` (`CHAR(10)`), dan `branches` (dengan `UNIQUE(village_id)`, `UNIQUE(code)`, FK `ON DELETE RESTRICT`, dan seluruh index sesuai PRD Bagian 5.2).
- [x] **Task 1.3 (Migrasi — Alter Users & Members):** Buat migrasi baru `alter_users_table_for_kopdes` (jadikan `email` & `password` nullable, tambahkan `role` ENUM, `province_id`, `regency_id`, `branch_id`, `card_uid` UNIQUE, `pin_hash`, `is_active`, beserta FK & index) dan migrasi tabel `members` (`member_code` UNIQUE, `home_branch_id`, `card_uid` UNIQUE, `name`, `phone`, `nik` UNIQUE nullable, `status`, `joined_at`).
- [x] **Task 1.4 (Migrasi — Katalog & Stok):** Buat migrasi untuk tabel `categories`, `products` (`sku` UNIQUE, `barcode` UNIQUE, `cost_price`, `selling_price`, `SoftDeletes`), `branch_stocks` (`quantity`, `reserved_qty`, `min_stock`, `UNIQUE(branch_id, product_id)`), dan `stock_movements` (immutable tanpa `updated_at`, `ENUM('sale','restock','adjustment','void_return')`, composite index `(branch_id, product_id, created_at)` dan `(reference_type, reference_id)`).
- [x] **Task 1.5 (Migrasi — Transaksi & Payment Logs):** Buat migrasi untuk tabel `transactions` (`invoice_number` UNIQUE, `idempotency_key` `CHAR(36)` UNIQUE, `member_home_branch_id`, `total_cogs`, kolom tunai & Midtrans, composite index `(branch_id, payment_status, paid_at)` dan `(payment_status, expires_at)`), `transaction_items` (semua kolom `*_snapshot`, `subtotal`, `subtotal_cogs`), dan `payment_logs` (`raw_payload` JSON, `signature_verified`, `source`).

### Trait, Utility & Model Eloquent
- [x] **Task 1.6 (Trait & Utility):** Buat trait `App\Models\Concerns\HasRegionScope` dengan local scope `scopeForUser(Builder $query, User $actor)` (termasuk dukungan custom kolom wilayah bila diperlukan) dan buat kelas utility `App\Support\CardUidNormalizer::normalize(string $raw): string`.
- [x] **Task 1.7 (Model — Wilayah, Cabang, User & Member):** Buat model `Province`, `Regency`, `District`, `Village` (`$incrementing = false`, `$keyType = 'string'`, `$timestamps = false`), `Branch`, `Member`, dan perbarui model `User` (`#[Hidden(['password', 'remember_token', 'pin_hash'])]`, helper method cek role, `$casts`, relasi Eloquent lengkap, dan `HasRegionScope`).
- [x] **Task 1.8 (Model — Katalog, Stok, Transaksi & Log):** Buat model `Category`, `Product` (`SoftDeletes`), `BranchStock` (accessor `available_stock`), `StockMovement` (`public const UPDATED_AT = null;`), `Transaction`, `TransactionItem`, dan `PaymentLog` lengkap dengan `$fillable`, `$casts`, Eloquent Relationships, dan `HasRegionScope`.

### Database Seeders
- [x] **Task 1.9 (Seeder — Referensi Wilayah):** Buat `Database\Seeders\RegionSeeder` berisi data wilayah baku sampel: Provinsi Jawa Timur (`35`), Kabupaten Ponorogo (`3502`) & Kabupaten Madiun (`3519` sebagai pembanding uji anti-IDOR), beberapa kecamatan dan desa sampel yang idempotent (`upsert`/`firstOrCreate`).
- [x] **Task 1.10 (Seeder — Role, Cabang & Akun Awal):** Buat `Database\Seeders\RoleAndAdminSeeder` (2 cabang KOPDES sampel di desa berbeda, akun awal `admin_nasional`, `admin_provinsi`, `admin_kabupaten`, `admin_cabang` untuk tiap cabang, plus contoh `kasir` & `member` dasar) dan daftarkan urutan seeder di `DatabaseSeeder.php`.

### Middleware & Policies (RBAC & Anti-IDOR)
- [x] **Task 1.11 (Middleware):** Buat `App\Http\Middleware\EnsureRole` dan `App\Http\Middleware\EnsurePosUnlocked`, daftarkan alias (`role`, `pos.unlocked`) di `bootstrap/app.php`, serta perbarui `HandleInertiaRequests` agar membagi `auth.user` (id, name, email, role, scope wilayah, info cabang) dan `flash` secara aman tanpa mengekspos `pin_hash`.
- [x] **Task 1.12 (Policies):** Buat `BranchPolicy`, `UserPolicy`, `MemberPolicy`, `StockPolicy`, dan `ReportPolicy` sesuai Matriks RBAC (PRD Bagian 2.2) dan aturan anti-IDOR (PRD Bagian 2.3 & 6.5).

### Autentikasi Admin & Layout Dasar Vue
- [x] **Task 1.13 (Controller & Routes — Auth Admin):** Sesuaikan `AuthenticatedSessionController`, `LoginRequest`, dan `routes/auth.php` agar `/login` hanya menerima role admin aktif (menolak `kasir` atau user/cabang `is_active = false`), nonaktifkan public registration `/register` yang tidak masuk MVP, dan arahkan login berhasil ke `/dashboard`.
- [x] **Task 1.14 (Vue — Layout Admin & Login):** Perbarui `resources/js/Layouts/AuthenticatedLayout.vue` dengan navigasi sidebar/header berbasis role KOPDES, sesuaikan `resources/js/Pages/Auth/Login.vue` (tautan ke `/pos/login` untuk kasir), dan perbarui `resources/js/Pages/Dashboard.vue` sebagai kerangka awal.

### Pengujian Milestone 1
- [x] **Task 1.15 (Feature Test — Milestone 1):** Verifikasi `php artisan migrate:fresh --seed` berjalan mulus dan buat Feature Test (`tests/Feature/Milestone1FoundationTest.php`) yang menguji: (a) constraint `UNIQUE(village_id)` menolak KOPDES kedua di desa yang sama; (b) `HasRegionScope` dan Policy menolak akses `admin_kabupaten` & `admin_cabang` ke data cabang di luar wilayahnya dengan HTTP 403.

---

## Milestone 2: Manajemen Cabang, User/Kasir (RFID + PIN), Member Lintas Cabang & Katalog Produk

> **Tujuan:** Menyelesaikan seluruh modul master data, binding kartu RFID/NFC lintas tabel, dan autentikasi kasir berbasis Tap Kartu RFID + PIN 6 digit dengan proteksi RateLimiter & Lock/Unlock.

### API Referensi Wilayah (Dependent Dropdown)
- [x] **Task 2.1 (Controller & Routes — Wilayah):** Buat `RegionController` (endpoint JSON terproteksi `auth`: daftar provinsi, kabupaten by `province_id`, kecamatan by `regency_id`, desa by `district_id`) yang otomatis membatasi pilihan sesuai scope role user login.

### Modul Cabang KOPDES (`FR-02`)
- [x] **Task 2.2 (Form Request — Cabang):** Buat `StoreBranchRequest` dan `UpdateBranchRequest` (validasi unik `village_id` dengan pesan eksplisit, validasi unik `code` opsional, validasi `village_id` berada dalam scope wilayah aktor).
- [x] **Task 2.3 (Service — Cabang):** Buat `BranchService` untuk membuat & memperbarui cabang dengan derivasi otomatis `district_id`, `regency_id`, `province_id` dari tabel `villages -> districts -> regencies -> provinces` di server serta auto-generate `code` berformat `KOP-{REGENCY_ID}-{SEQ3}` bila kosong.
- [x] **Task 2.4 (Controller & Routes — Cabang):** Buat `BranchController` (`index`, `create`, `store`, `edit`, `update`, `toggleActive`) dengan `BranchPolicy` dan `Branch::forUser($request->user())`.
- [x] **Task 2.5 (Vue — Halaman Cabang):** Buat `resources/js/Pages/Branches/Index.vue` (tabel + filter wilayah + toggle aktif/nonaktif) dan `resources/js/Pages/Branches/Form.vue` (dependent select Provinsi → Kabupaten → Kecamatan → Desa).

### Validasi Kartu RFID & Komponen Capture Reusable
- [x] **Task 2.6 (Validation Rule & Utility JS — RFID):** Buat custom rule `App\Rules\UniqueCardUidAcrossActors` (menormalisasi `card_uid` lalu memvalidasi keunikan pada tabel `users` dan `members` sekaligus, dengan opsi ignore ID) serta utility frontend `resources/js/Utils/normalizeCardUid.js`.
- [x] **Task 2.7 (Vue — Komponen Modal RFID):** Buat komponen reusable `resources/js/Components/RfidCaptureModal.vue` (`<script setup>`) dengan input terisolasi *auto-focus*, *debounce* 350ms, dan normalisasi UID.

### Modul Manajemen User Admin & Kasir (`FR-03`)
- [x] **Task 2.8 (Form Request — User & Kasir):** Buat `StoreUserRequest` dan `UpdateUserRequest` (validasi hierarki role yang boleh dibuat aktor, email & password wajib untuk admin, `card_uid` dengan `UniqueCardUidAcrossActors` & PIN 6 digit `regex:/^\d{6}$/` untuk kasir, serta validasi scope wilayah).
- [x] **Task 2.9 (Service — User & Kasir):** Buat `UserManagementService` untuk membuat/memperbarui admin & kasir (menurunkan kolom scope wajib dan memastikan kolom di luar scope bernilai `null`, hash password/PIN, normalisasi `card_uid`).
- [x] **Task 2.10 (Controller & Routes — User & Kasir):** Buat `UserController` (`index`, `create`, `store`, `edit`, `update`, `toggleActive`) dengan `UserPolicy` dan `User::forUser($request->user())`.
- [x] **Task 2.11 (Vue — Halaman User & Kasir):** Buat `resources/js/Pages/Users/Index.vue` dan `resources/js/Pages/Users/Form.vue` (form dinamis Admin vs Kasir, integrasi `RfidCaptureModal.vue` untuk binding kartu kasir, serta input/reset PIN 6 digit).

### Modul Manajemen Member Lintas Cabang (`FR-05`)
- [x] **Task 2.12 (Form Request — Member):** Buat `StoreMemberRequest`, `UpdateMemberRequest`, dan `ReplaceMemberCardRequest` (validasi `UniqueCardUidAcrossActors`, `nik` opsional unik, `home_branch_id` sesuai hak akses).
- [x] **Task 2.13 (Service — Member):** Buat `MemberService` (otomatis mengisi `home_branch_id` dari cabang `admin_cabang`, auto-generate `member_code` format `MBR-{BRANCH_CODE}-{SEQ5}`, normalisasi `card_uid`, dan fitur penggantian kartu RFID hilang).
- [x] **Task 2.14 (Controller & Routes — Member):** Buat `MemberController` (`index`, `create`, `store`, `edit`, `update`, `replaceCard`, `toggleStatus`) dengan `MemberPolicy` dan scoping wilayah.
- [x] **Task 2.15 (Vue — Halaman Member):** Buat `resources/js/Pages/Members/Index.vue` (pencarian, status, aksi Ganti Kartu RFID) dan `resources/js/Pages/Members/Form.vue` (integrasi `RfidCaptureModal.vue`).

### Modul Master Kategori & Produk Global (`FR-07`)
- [x] **Task 2.16 (Form Request — Kategori & Produk):** Buat `StoreCategoryRequest`, `UpdateCategoryRequest`, `StoreProductRequest`, dan `UpdateProductRequest` (validasi unik `slug`, `sku`, `barcode`, `cost_price >= 0`, `selling_price >= 0`).
- [x] **Task 2.17 (Controller & Routes — Kategori & Produk):** Buat `CategoryController` dan `ProductController` (CRUD khusus `admin_nasional`, read-only untuk admin wilayah/cabang, hapus produk menggunakan `SoftDeletes`).
- [x] **Task 2.18 (Vue — Halaman Kategori & Produk):** Buat `resources/js/Pages/Categories/Index.vue`, `resources/js/Pages/Products/Index.vue`, dan `resources/js/Pages/Products/Form.vue`.

### Autentikasi Kasir RFID + PIN & Lock/Unlock POS (`FR-04`)
- [x] **Task 2.19 (Form Request — Auth Kasir):** Buat `CashierLoginRequest` dan `PosUnlockRequest` (validasi `card_uid` & `pin` tepat 6 digit numerik).
- [x] **Task 2.20 (Controller & Routes — Auth Kasir):** Buat `PosAuthController` (`showLogin` `GET /pos/login`, `login` `POST /pos/login`, `lock` `POST /pos/lock`, `unlock` `POST /pos/unlock`, `logout` `POST /pos/logout`) dengan `CardUidNormalizer`, `RateLimiter` (`kasir-pin:{uid}|{ip}`, 5 percobaan, decay 900 detik, balas HTTP `429` dengan sisa detik), cek `user.is_active` & `branch.is_active`, dan pengelolaan session `pos_locked`.
- [x] **Task 2.21 (Vue — Halaman Login Kasir):** Buat `resources/js/Pages/Pos/Login.vue` (`<script setup>`, input tap kartu RFID terfokus otomatis + input PIN 6 digit, tampilkan pesan sisa waktu tunggu saat HTTP 429).

### Pengujian Milestone 2
- [x] **Task 2.22 (Feature Test — Milestone 2):** Buat Feature Test (`tests/Feature/Milestone2MasterAndCashierAuthTest.php`) yang menguji: (a) CRUD cabang, user, member, produk sesuai RBAC; (b) kasir login sukses dengan berbagai format spasi/huruf kecil `card_uid` + PIN 6 digit; (c) salah PIN ke-6 mengembalikan HTTP 429; (d) `card_uid` milik kasir ditolak saat didaftarkan untuk member (dan sebaliknya); (e) kasir dari cabang nonaktif tidak bisa login.

---

## Milestone 3: Manajemen Inventori Cabang (`branch_stocks` & `stock_movements`)

> **Tujuan:** Membangun pengelolaan stok fisik independen per cabang (`restock` dan `adjustment`) dengan pessimistic locking dan audit log *append-only*.

### Validasi & Service Inventori (`FR-08`, `FR-09`)
- [ ] **Task 3.1 (Form Request — Inventori):** Buat `RestockRequest` (`product_id` aktif, `qty_add` integer >= 1, `notes` nullable string) dan `AdjustStockRequest` (`product_id` aktif, `actual_quantity` integer >= 0, `notes` wajib diisi).
- [ ] **Task 3.2 (Service — Inventori):** Buat `App\Services\StockService` dengan method `restock()` dan `adjust()` di dalam `DB::transaction()`: `firstOrCreate()` + `lockForUpdate()` pada `branch_stocks`, validasi `actual_quantity >= reserved_qty`, hitung `qty_before`, `qty_after`, `qty_change`, perbarui `quantity`, dan tulis tepat 1 baris ke `stock_movements`.

### Controller & Routes Inventori
- [ ] **Task 3.3 (Controller & Routes — Inventori):** Buat `InventoryController` (`index`, `restock`, `adjust`, `movements`) terproteksi `StockPolicy` dan `forUser()` (CRU hanya untuk `admin_cabang` pada cabangnya sendiri; read-only untuk admin di atasnya; tanpa endpoint update/delete pada `stock_movements`).

### Halaman Vue Inventori
- [ ] **Task 3.4 (Vue — Halaman Stok Cabang):** Buat `resources/js/Pages/Inventory/Index.vue` menampilkan daftar produk aktif dengan 4 metrik (`quantity`, `reserved_qty`, `available_stock`, `min_stock`), indikator merah saat `available_stock <= min_stock`, tombol & modal Restock, serta tombol & modal Penyesuaian Stok (menampilkan `reserved_qty` sebagai batas bawah).
- [ ] **Task 3.5 (Vue — Halaman Audit Log Stok):** Buat `resources/js/Pages/Inventory/Movements.vue` (read-only tanpa tombol Edit/Delete, dengan filter rentang tanggal, produk, tipe mutasi `sale`/`restock`/`adjustment`/`void_return`, dan cabang sesuai scope).

### Pengujian Milestone 3
- [ ] **Task 3.6 (Feature Test — Milestone 3):** Buat Feature Test (`tests/Feature/Milestone3InventoryTest.php`) yang menguji: (a) `restock` menambah `quantity` dan menulis tepat 1 baris `stock_movements` dengan `qty_before`/`qty_after`/`qty_change` akurat; (b) `adjustment` menolak jika `actual_quantity < reserved_qty`; (c) `admin_cabang` ditolak HTTP 403 saat mencoba mengubah stok cabang lain.

---

## Milestone 4: Layar POS Kasir (Vue 3), Checkout Tunai, Midtrans Snap, Webhook & Cetak Struk

> **Tujuan:** Menyelesaikan alur inti POS kasir secara penuh (scan barcode, tap kartu member lintas cabang, checkout tunai & Midtrans Snap, webhook aman & idempotent, rekonsiliasi manual & scheduler, lock layar, dan cetak struk thermal 58mm/80mm).

### Endpoint Lookup POS (Produk & Member Lintas Cabang)
- [ ] **Task 4.1 (Controller & Routes — Lookup POS):** Buat `PosLookupController` (`productByBarcode` `GET /pos/products/barcode/{barcode}` dan `memberByCard` `GET /pos/members/lookup?card_uid=...`) terproteksi `role:kasir` & `pos.unlocked`; lookup member membaca lintas `home_branch_id` khusus `status = 'active'` dan hanya mengembalikan `id`, `member_code`, `name`, `home_branch_id`, `home_branch_name`.

### Form Request & Services Checkout / Midtrans (`FR-11`, `FR-12`, `FR-13`)
- [ ] **Task 4.2 (Form Request — Checkout):** Buat `CheckoutRequest` (validasi `idempotency_key` UUID, `payment_method` in `cash,midtrans`, `member_id` nullable exists & active, `cash_received` required_if `cash` numeric >= 0, `items` array min:1 berisi `product_id` & `qty` integer >= 1).
- [ ] **Task 4.3 (Service — Midtrans HTTP Client):** Buat `App\Services\MidtransService` (`createSnapToken()`, `getTransactionStatus()`, `cancelTransaction()`, `verifySignature()`) menggunakan Laravel `Http` client & konfigurasi `config('services.midtrans.*')`, dengan expiry Snap 15 menit dan `order_id = invoice_number`.
- [ ] **Task 4.4 (Service — Checkout Atomik):** Buat `App\Services\CheckoutService` di dalam `DB::transaction()`: cek `idempotency_key` (plus catch `UniqueConstraintViolationException`), urutkan `product_id ASC` lalu kunci `branch_stocks` via `lockForUpdate()`, validasi `(quantity - reserved_qty) >= qty`, hitung ulang harga & HPP dari `products`, generate `invoice_number` (`INV-{BRANCH_CODE}-{YYYYMMDD}-{SEQ4}`), isi seluruh kolom snapshot (`*_snapshot`, `total_cogs`, `member_home_branch_id`), proses `cash` (`paid`, kurangi `quantity`, tulis `stock_movements`) atau `midtrans` (`pending`, tambah `reserved_qty`, `expires_at = now()->addMinutes(15)`, panggil `MidtransService::createSnapToken()`, rollback jika gagal 502).
- [ ] **Task 4.5 (Service — Rekonsiliasi Midtrans Tunggal):** Buat `App\Services\MidtransReconciliationService::applyStatus()` di dalam `DB::transaction()`: kunci `Transaction` (`lockForUpdate()`), catat `payment_logs`, validasi `gross_amount`, guard idempotensi bila `payment_status !== 'pending'`, petakan status Midtrans (`settlement`/`capture+accept` → `paid` kurangi `quantity` & `reserved_qty` + tulis `stock_movements`; `expire`/`cancel`/`deny`/`capture+deny` → `expired`/`cancelled`/`failed` & lepaskan `reserved_qty` dengan kunci `branch_stocks` terurut `product_id ASC`).

### Controller POS, Webhook Midtrans & Scheduler
- [ ] **Task 4.6 (Controller & Routes — POS):** Buat `PosController` (`index` `GET /pos`, `checkout` `POST /pos/checkout`, `pendingTransactions`, `todayTransactions`, `checkStatus` `POST /pos/transactions/{transaction}/check-status`, `cancel` `POST /pos/transactions/{transaction}/cancel`) terproteksi middleware `auth`, `role:kasir`, dan `pos.unlocked` (kecuali render/status lock).
- [ ] **Task 4.7 (Controller & Route — Webhook Midtrans):** Buat `App\Http\Controllers\Api\MidtransWebhookController` pada `POST /api/webhooks/midtrans` di `routes/api.php`: validasi signature SHA-512 (simpan `payment_logs` dengan `signature_verified = false` dan balas HTTP `403` jika gagal), delegasikan ke `MidtransReconciliationService`, dan kembalikan HTTP `200 OK` secara idempotent.
- [ ] **Task 4.8 (Console Command & Scheduler):** Buat `App\Console\Commands\ReconcileExpiredTransactions` (`kopdes:reconcile-expired-transactions`) untuk mencari transaksi `midtrans` berstatus `pending` dengan `expires_at <= now()`, cek status terbaru ke Midtrans Status API, lalu jalankan `MidtransReconciliationService`; daftarkan di `routes/console.php` dengan `->everyFiveMinutes()->withoutOverlapping()`.

### Frontend Vue 3 POS, Modal Hardware & Cetak Struk (`FR-10`, `FR-14`)
- [ ] **Task 4.9 (Utility & Composable — POS Vue):** Buat `resources/js/Utils/uuid.js` (`generateUuidV4` dengan `crypto.randomUUID()` + fallback) dan `resources/js/Composables/useIdleLock.js` (listen `mousemove`/`keydown`/`click`/`touchstart` throttled 5 detik, trigger `POST /pos/lock` setelah 15 menit).
- [ ] **Task 4.10 (Vue — Modal Member, Pembayaran & Kunci Layar):** Buat `resources/js/Pages/Pos/Partials/MemberLookupModal.vue` (`F2`, debounce 350ms, normalisasi UID, lepas member), `resources/js/Pages/Pos/Partials/PaymentModal.vue` (`F9`, mode Tunai dengan tombol pecahan cepat & kembalian real-time, mode Midtrans dengan `window.snap.pay`), dan `resources/js/Pages/Pos/Partials/PosLockModal.vue` (`F12`, pertahankan keranjang bila kasir sama membuka kunci, reset keranjang bila kasir berbeda).
- [ ] **Task 4.11 (Vue — Drawer Pending & Struk Thermal):** Buat `resources/js/Pages/Pos/Partials/PendingTransactionsDrawer.vue` (daftar transaksi `pending` cabang dengan tombol Buka Ulang Snap, Cek Status, Batalkan + tab Riwayat Hari Ini untuk cetak ulang struk `paid`) dan `resources/js/Pages/Pos/Partials/ReceiptThermal.vue` (template `@media print` 58mm/80mm dengan preferensi `localStorage`).
- [ ] **Task 4.12 (Vue & Blade — Halaman Utama POS):** Tambahkan pemuatan skrip Midtrans Snap JS secara kondisional (berdasarkan config `client_key` & `snap_url`) dan buat `resources/js/Pages/Pos/Index.vue` (`<script setup>`, *auto-focus* barcode saat `activeModal === null`, pesan bantuan jika kartu RFID ter-scan di kolom barcode, keranjang reaktif, manajemen `idempotency_key`, kunci tombol `isSubmitting`, integrasi semua partial & `window.print()`).

### Pengujian Milestone 4
- [ ] **Task 4.13 (Feature Test — POS Tunai, Idempotensi & Member Lintas Cabang):** Buat Feature Test (`tests/Feature/Milestone4PosCashAndMemberTest.php`) yang menguji: (a) checkout tunai memotong `quantity`, menghitung `change_amount`, mengisi snapshot HPP, dan menulis `stock_movements`; (b) pengiriman ulang dengan `idempotency_key` sama tidak membuat transaksi ganda atau memotong stok dua kali; (c) lookup & transaksi member lintas cabang mencatat `branch_id` berbeda dari `member_home_branch_id`; (d) checkout ditolak saat sesi `pos_locked = true`.
- [ ] **Task 4.14 (Feature Test — Midtrans, Webhook & Scheduler):** Buat Feature Test (`tests/Feature/Milestone4MidtransAndSchedulerTest.php` dengan `Http::fake()`) yang menguji: (a) checkout Midtrans menambah `reserved_qty` tanpa mengurangi `quantity` dan mencegah kasir lain menjual stok yang ditahan; (b) webhook dengan signature salah ditolak HTTP 403 & tetap tercatat di `payment_logs`; (c) webhook `settlement` dikirim 2 kali hanya memotong stok 1 kali; (d) webhook `expire`, aksi cancel manual, dan command `kopdes:reconcile-expired-transactions` melepas `reserved_qty` kembali ke 0.

---

## Milestone 5: Dashboard Keuangan Berjenjang & Rekap Transaksi Member

> **Tujuan:** Menyediakan dashboard KPI dan pelaporan keuangan akurat berbasis snapshot HPP dengan drill-down wilayah sesuai RBAC serta rekap belanja member lintas cabang sebagai fondasi data SHU.

### Validasi & Service Laporan (`FR-15`, `FR-16`, `FR-17`)
- [ ] **Task 5.1 (Form Request — Filter Laporan Anti-IDOR):** Buat `ReportFilterRequest` untuk memvalidasi `start_date`, `end_date`, `payment_method` (`all`/`cash`/`midtrans`), `province_id`, `regency_id`, dan `branch_id`, serta mengembalikan HTTP `403 Forbidden` jika user memanipulasi parameter wilayah di luar scope role-nya.
- [ ] **Task 5.2 (Service — Laporan Keuangan & Member):** Buat `App\Services\FinancialReportService` menggunakan query agregasi terindeks (`branch_id`, `payment_status`, `paid_at`) & `HasRegionScope`: hitung KPI Dashboard (Omzet `paid`, Total Transaksi `paid`, Laba Kotor `SUM(total_amount - total_cogs)`, Margin %, pecahan `cash` vs `midtrans`), laporan rincian penjualan & laba kotor per cabang, serta rekap transaksi member (Jumlah Transaksi `paid`, Belanja di KOPDES Asal, Belanja di KOPDES Lain, Total Akumulasi) dengan cakupan `member_home_branch_id` / `members.home_branch_id` agar `admin_cabang` melihat belanja anggotanya di cabang lain.

### Controller & Routes Dashboard & Laporan
- [ ] **Task 5.3 (Controller & Routes — Dashboard & Laporan):** Buat `DashboardController` (`GET /dashboard`) dan `ReportController` (`sales` `GET /reports/sales`, `members` `GET /reports/members`) terproteksi `auth`, `ReportPolicy`, dan `ReportFilterRequest`.

### Halaman Vue Dashboard & Laporan
- [ ] **Task 5.4 (Vue — Dashboard KPI & Drill-Down):** Buat `resources/js/Pages/Dashboard/Index.vue` (`<script setup>`) menampilkan 4 kartu KPI utama, perbandingan metode bayar `cash` vs `midtrans`, filter rentang tanggal (default Bulan Ini), dan filter *drill-down* wilayah dinamis sesuai role login.
- [ ] **Task 5.5 (Vue — Halaman Laporan Penjualan & Member):** Buat `resources/js/Pages/Reports/Sales.vue` (tabel agregasi per cabang + daftar transaksi `paid` berbasis snapshot HPP) dan `resources/js/Pages/Reports/Members.vue` (tabel rekap belanja member: KOPDES Asal, Lintas Cabang, dan Total Akumulasi).

### Pengujian Milestone 5
- [ ] **Task 5.6 (Feature Test — Milestone 5):** Buat Feature Test (`tests/Feature/Milestone5ReportsTest.php`) yang membuktikan: (a) mengubah `products.cost_price` setelah transaksi `paid` tidak mengubah nilai Laba Kotor pada laporan historis; (b) transaksi `pending`, `expired`, `failed`, dan `cancelled` tidak masuk ke total omzet; (c) `admin_cabang` melihat total belanja anggotanya baik saat berbelanja di cabang sendiri maupun di cabang desa lain; (d) manipulasi parameter URL wilayah oleh role di bawah `admin_nasional` ditolak dengan HTTP 403.
