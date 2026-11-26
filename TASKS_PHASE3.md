# TASKS_PHASE3.md — Daftar Tugas Implementasi KOPDES (Fase 3 / Portfolio-Ready)

Daftar tugas ini memecah seluruh 4 Milestone Fase 3 pada [PRD_PHASE3.md](./PRD_PHASE3.md) (melanjutkan [TASKS.md](./TASKS.md), modul Biaya Operasional/SHU/Ekspor, serta [TASKS_PHASE2.md](./TASKS_PHASE2.md)) menjadi tugas-tugas kecil yang spesifik. **Setiap tugas hanya menyentuh 1 lapisan kerja** (konfigurasi, migrasi, model, middleware/policy, form request, service, command, controller/routes, Vue UI, atau feature test) agar implementasi rapi, terukur, dan bebas regresi terhadap Milestone 1–9.

---

## Milestone 10: Pengaturan Sistem Dinamis (`app_settings`) & Audit Trail Admin (`activity_logs`)

> **Tujuan:** Memusatkan konfigurasi bisnis koperasi di database ber-cache (khusus `admin_nasional`) dan mencatat seluruh aksi administratif sensitif secara otomatis dan *immutable* dengan proteksi `HasRegionScope`.

### Migrasi Database Pengaturan & Audit Trail
- [x] **Task 10.1 (Migrasi — `app_settings` & `activity_logs`):** Buat migrasi untuk:
  - Tabel `app_settings` (`key` `VARCHAR(100)` PK, `value` `TEXT` nullable, `type` ENUM `'string','integer','decimal','boolean','json'` default `'string'`, `group` `VARCHAR(50)` indexed, `updated_by_user_id` nullable FK `-> users(id) ON DELETE SET NULL`, timestamps);
  - Tabel `activity_logs` (immutable tanpa `updated_at`: `id` PK, `actor_id` nullable FK `-> users(id) ON DELETE SET NULL`, `actor_role`, `action` indexed, `description`, `subject_type`, `subject_id`, `province_id` nullable FK, `regency_id` nullable FK, `branch_id` nullable FK, `old_values` JSON nullable, `new_values` JSON nullable, `ip_address`, `user_agent`, `created_at` indexed, composite index `(subject_type, subject_id)` dan `(branch_id, action, created_at)`).

### Model, Policy & Services (`FR-P3-07`, `FR-P3-08`)
- [x] **Task 10.2 (Model — `AppSetting` & `ActivityLog`):**
  - Buat model `AppSetting` (`$primaryKey = 'key'`, `$incrementing = false`, `$keyType = 'string'`, relasi `updatedBy()`).
  - Buat model `ActivityLog` (`public const UPDATED_AT = null;`, `$casts` `old_values`/`new_values` => `'array'`, relasi `actor()`, `subject()` morphTo, `province()`, `regency()`, `branch()`, serta `HasRegionScope` yang menyembunyikan log global `province_id = NULL` dari role di bawah `admin_nasional`).
- [x] **Task 10.3 (Policy — Pengaturan & Audit Trail):** Buat `AppSettingPolicy` (hanya `admin_nasional` diizinkan melihat & mengubah) dan `ActivityLogPolicy` (`admin_nasional` semua log; `admin_provinsi`/`admin_kabupaten`/`admin_cabang` read-only sesuai scope; `kasir` ditolak HTTP `403`; tanpa izin update/delete).
- [x] **Task 10.4 (Service — `AppSettingService` & `ActivityLogService`):**
  - Buat `App\Services\AppSettingService` dengan method `all()`, `get(string $key, mixed $default = null)`, `publicConfig()` (untuk shared Inertia props non-sensitif), dan `updateMany(array $data, User $actor, ?UploadedFile $logo = null)` yang memakai cache kunci `'kopdes.app_settings'`, menyimpan logo via `Storage::disk('public')->putFile('logos', ...)`, menyediakan nilai fallback identik MVP/Fase 2, serta menghapus cache saat update.
  - Buat `App\Services\ActivityLogService::record()` yang otomatis menyensor kunci sensitif (`password`, `pin`, `pin_hash`, `remember_token`, `server_key`) dari `old_values`/`new_values`, mengisi scope wilayah dari subjek/aktor, serta mencatat `ip_address` dan `user_agent`.

### Integrasi ke Modul Existing
- [x] **Task 10.5 (Integrasi — Konsumsi `AppSettingService`):**
  - Perbarui `TransactionReturnService` agar membaca batas hari retur dari `AppSettingService::get('return_max_days', config('kopdes.return_max_days', 7))`.
  - Perbarui `FinancialReportService` (dan kalkulasi SHU) agar membaca persentase alokasi SHU (`shu_allocation_member_usaha`, `shu_allocation_reserve`, `shu_allocation_management`, `shu_allocation_education`) dari `AppSettingService`.
  - Perbarui `HandleInertiaRequests` untuk membagikan `app_settings` publik (`institution_name`, `institution_logo_url`, `receipt_footer`, `default_receipt_paper`).
  - Perbarui `ReceiptThermal.vue`, `ShiftClosingReceipt.vue`, dan template Ekspor PDF/Excel agar menampilkan `institution_name`, logo (jika ada), `receipt_footer`, dan menghormati `default_receipt_paper`.
- [x] **Task 10.6 (Integrasi — Hook Pencatatan `ActivityLogService`):** Pasang pemanggilan `ActivityLogService::record()` pada service/controller existing untuk peristiwa: `product.price_updated` (saat `cost_price`/`selling_price`/`member_price` berubah), `branch.status_toggled`, `user.status_toggled`, `member.status_toggled`, `member.card_replaced`, `shift.force_closed`, `transaction.returned`, dan `settings.updated`.

### Form Request, Controller, Routes & Vue UI
- [x] **Task 10.7 (Form Request, Controller & Routes — Settings & Activity Logs):**
  - Buat `UpdateAppSettingsRequest` (validasi `institution_name`, `logo` opsional `mimes:png,jpg,jpeg,webp|max:2048`, `receipt_footer` maks 255, `default_receipt_paper` in `58mm,80mm`, `return_max_days` integer 1–90, serta validasi kustom bahwa total 4 persentase alokasi SHU tepat sama dengan `$100\%$`).
  - Buat `SettingController` (`index` `GET /settings`, `update` `PUT /settings`) terproteksi `AppSettingPolicy` dan `ActivityLogController` (`index` `GET /activity-logs`) terproteksi `ActivityLogPolicy` & `ActivityLog::forUser($request->user())`.
- [x] **Task 10.8 (Vue — Halaman Pengaturan Sistem & Audit Trail):**
  - Buat `resources/js/Pages/Settings/Index.vue` (`<script setup>`, form pengaturan umum + upload preview logo + pengaturan struk/retur + indikator real-time total alokasi SHU harus `$100\%$`).
  - Buat `resources/js/Pages/ActivityLogs/Index.vue` (tabel read-only dengan filter tanggal, aksi, aktor, cabang sesuai scope + modal/panel inspeksi JSON *diff* `old_values` vs `new_values`).
  - Tambahkan menu **Pengaturan Sistem** (khusus `admin_nasional`) dan **Audit Trail** (semua role admin, tersembunyi untuk `kasir`) di `Layouts/AuthenticatedLayout.vue`.

### Pengujian Milestone 10
- [x] **Task 10.9 (Feature Test — Milestone 10):** Buat `tests/Feature/Milestone10SettingsAndActivityLogTest.php` yang menguji: (a) hanya `admin_nasional` dapat mengakses/mengubah `app_settings` (role lain HTTP `403`); (b) total alokasi SHU $\ne 100\%$ ditolak HTTP `422`; (c) perubahan `return_max_days` langsung memengaruhi validasi retur di `TransactionReturnService` dan perubahan persentase SHU memengaruhi laporan SHU; (d) aksi sensitif (ubah harga produk, toggle cabang/user/member, force-close shift, retur, update settings) otomatis menulis `activity_logs` tanpa membocorkan `password`/`pin_hash`; (e) `admin_cabang` dan `admin_kabupaten` hanya melihat log pada scope wilayahnya dan tidak melihat log global maupun cabang lain.

---

## Milestone 11: Import Wilayah Resmi, Onboarding Massal Excel/CSV & Cetak Label Barcode/Kartu Member

> **Tujuan:** Mempercepat *onboarding* cabang baru lewat CLI impor wilayah & import massal Excel/CSV (*all-or-nothing*), mengubah `members.card_uid` menjadi *nullable*, serta menyediakan cetak stiker barcode produk (SVG) dan kartu member fisik (QR Code).

### Migrasi Alter `members` & Penyesuaian Member Existing
- [x] **Task 11.1 (Migrasi — Alter `members` untuk Nullable RFID & Portal PIN):** Buat migrasi `alter_members_table_for_phase3` untuk mengubah `card_uid` menjadi `VARCHAR(64) NULL` (pertahankan index `UNIQUE`), serta menambahkan `pin_hash` (`VARCHAR(255) NULL` setelah `status`) dan `remember_token` (`VARCHAR(100) NULL` setelah `pin_hash`).
- [x] **Task 11.2 (Model, Request & Vue — Dukungan Member Tanpa Kartu RFID Awal):**
  - Perbarui `Member` model (`$hidden` menyertakan `pin_hash` & `remember_token`, tambahkan accessor `has_rfid_card`).
  - Perbarui `StoreMemberRequest`, `UpdateMemberRequest`, dan `MemberService` agar `card_uid` bersifat opsional (`nullable`), mengubah string kosong/spasi menjadi `null`, dan hanya menormalisasi + mengecek `UniqueCardUidAcrossActors` jika terisi.
  - Perbarui `resources/js/Pages/Members/Index.vue` dan `Members/Form.vue` agar menampilkan badge **"Belum Binding RFID"** untuk member dengan `card_uid === null` beserta tombol cepat **"Pasang Kartu RFID"** via `RfidCaptureModal.vue`.

### Command Artisan Import Wilayah Baku (`FR-P3-01`)
- [x] **Task 11.3 (Console Command — `kopdes:import-regions`):** Buat `App\Console\Commands\ImportRegionsCommand` (`kopdes:import-regions {file} {--type=}`) mendukung `--type=provinces|regencies|districts|villages`: validasi keberadaan file & kecocokan header CSV, baca streaming (`fgetcsv` / `LazyCollection`), eksekusi `DB::table(...)->upsert()` per chunk 1.000 baris secara *idempotent*, dan tampilkan progress bar + ringkasan baris.

### Import Massal Excel/CSV: Produk, Stok Awal & Member (`FR-P3-02` s.d. `FR-P3-04`)
- [x] **Task 11.4 (Template Export — Excel Import Templates):** Buat class template `.xlsx` menggunakan library Excel existing untuk unduhan template Produk (`category_name, sku, barcode, name, unit, cost_price, selling_price, member_price`), Stok Awal Cabang (`barcode, qty_add, min_stock, notes`), dan Member (`name, phone, nik, card_uid, pin, joined_at`).
- [x] **Task 11.5 (Form Request & Service — `BulkImportService` All-or-Nothing):**
  - Buat `BulkImportRequest` (validasi `file` wajib `mimes:xlsx,csv|max:5120`, `branch_id` opsional khusus `admin_nasional`).
  - Buat `App\Services\BulkImportService` (batas maks 2.000 baris per upload, validasi seluruh baris di memori terlebih dahulu termasuk deteksi duplikat internal antar baris file & duplikat DB, lempar error HTTP `422` berisi daftar `[{ row, field, message }]` jika ada $\ge 1$ error, jalankan dalam `DB::transaction()` jika 0 error):
    - `importProducts()`: insert ke `products`.
    - `importInitialStocks()`: `lockForUpdate()` pada `branch_stocks`, tambah `quantity`, perbarui `min_stock` opsional, tulis `stock_movements` (`type = 'restock'`).
    - `importMembers()`: generate `member_code`, normalisasi `card_uid` (`null` jika kosong, unik lintas `users`/`members`), hash `pin` jika diisi 6 digit, default `joined_at = today()`.
    - Catat ringkasan ke `activity_logs` (`action = 'import.completed'`).
- [x] **Task 11.6 (Controller & Routes — Bulk Import):** Buat `BulkImportController` (`downloadTemplate` `GET /imports/{type}/template`, `import` `POST /imports/{type}`) dengan pembatasan RBAC ketat (`products` khusus `admin_nasional`; `stocks` & `members` khusus `admin_cabang` terkunci ke `auth()->user()->branch_id` dan `admin_nasional` dengan `branch_id` terpilih).
- [x] **Task 11.7 (Vue — Komponen & Integrasi Import Excel):** Buat komponen reusable `resources/js/Components/ImportExcelModal.vue` (tombol unduh template, dropzone/file input `.xlsx`/`.csv`, tabel laporan error per nomor baris Excel saat gagal, flash sukses saat berhasil) dan pasang pada `Products/Index.vue`, `Inventory/Index.vue`, dan `Members/Index.vue` sesuai hak akses.

### Cetak Label Barcode Produk & Kartu Member Fisik (`FR-P3-05`, `FR-P3-06`)
- [x] **Task 11.8 (NPM Dependencies & Controller — Barcode & Kartu Member):**
  - Tambahkan package NPM `jsbarcode` dan `qrcode` ke `package.json`.
  - Buat `ProductBarcodeController` (`index` `GET /products/barcode-labels`, dapat diakses semua role Admin & Kasir) dan `MemberCardController` (`show` `GET /members/{member}/card`, `batch` `GET /members/print-cards`, terproteksi `MemberPolicy` & anti-IDOR `home_branch_id`).
- [x] **Task 11.9 (Vue — Cetak Label Barcode & Cetak Kartu Member):**
  - Buat `resources/js/Pages/Products/BarcodeLabels.vue` (`<script setup>`, pilih multi-produk, atur jumlah salinan dengan batas total maks 200 label, toggle tampilkan Harga Member, preset `@media print` A4 Grid 3 kolom vs Thermal 50x30mm, render SVG via `jsbarcode` auto EAN-13/CODE128).
  - Buat `resources/js/Pages/Members/PrintCards.vue` (`<script setup>`, layout kartu ID-1 `$85.6\text{ mm} \times 53.98\text{ mm}$`, tampilkan logo & nama dari `app_settings`, nama KOPDES asal, `member_code`, render QR SVG via `qrcode`, dukung cetak satuan & batch maks 20 kartu), serta tambahkan tombol akses dari `Products/Index.vue`, `Pos/Index.vue` (link cetak label), dan `Members/Index.vue`.

### Pengujian Milestone 11
- [x] **Task 11.10 (Feature Test — Milestone 11):** Buat `tests/Feature/Milestone11BulkImportAndPrintingTest.php` yang menguji: (a) command `kopdes:import-regions` mengimpor CSV wilayah secara *idempotent* dan menolak header invalid; (b) import produk/stok/member gagal total (*all-or-nothing*, HTTP `422` + nomor baris) bila ada 1 baris duplikat internal atau melanggar validasi (`member_price > selling_price`, barcode tidak ada, dll.); (c) import member dengan banyak baris `card_uid` kosong tersimpan sebagai `NULL` tanpa bentrok `UNIQUE`, dan `card_uid` yang terisi tetap divalidasi unik lintas `users` & `members`; (d) import stok awal menulis `stock_movements` (`restock`) dan `admin_cabang` tidak bisa menyuntikkan `branch_id` cabang lain; (e) akses halaman cetak label barcode & cetak kartu member mengikuti RBAC dan menolak lintas cabang HTTP `403`.

---

## Milestone 12: Portal Mandiri Member (Auth Guard `member`, Kartu Digital, SHU & Riwayat)

> **Tujuan:** Membangun portal mandiri *mobile-first* bagi anggota koperasi menggunakan Laravel Auth Guard terpisah (`member`), menampilkan Kartu Member Digital + QR, ringkasan belanja (Asal vs Lintas Cabang), Total Hemat Member, Estimasi SHU Berjalan, riwayat struk digital, dan ganti PIN mandiri.

### Konfigurasi Auth Guard `member`, Model & Middleware
- [x] **Task 12.1 (Konfigurasi & Model — Guard `member`):**
  - Tambahkan guard `'member'` (`driver: session`, `provider: members`) dan provider `'members'` (`driver: eloquent`, `model: App\Models\Member::class`) pada `config/auth.php`.
  - Perbarui `App\Models\Member` agar meng-extend `Illuminate\Foundation\Auth\User` (`Authenticatable`) dan implementasikan `getAuthPassword()` mengembalikan `$this->pin_hash`.
- [x] **Task 12.2 (Middleware & Inertia Props — Isolasi Guard `member`):**
  - Buat middleware `EnsureMemberAuthenticated` (arahkan tamu `/member/*` ke `/member/login`) dan `RedirectIfMemberAuthenticated` (arahkan member yang sudah login dari `/member/login` ke `/member`), daftarkan alias (`auth.member`, `guest.member`) di `bootstrap/app.php`.
  - Perbarui `HandleInertiaRequests::share()` agar memisahkan `auth.user` (`auth('web')->user()`) dan `auth.member` (`auth('member')->user()` dengan field aman minimal: `id`, `member_code`, `name`, `phone`, `home_branch_id`, `home_branch_name`, `has_rfid_card`, `status` — tanpa `pin_hash` atau `nik`).

### Pengelolaan PIN Member oleh Admin Cabang (`FR-P3-09`)
- [x] **Task 12.3 (Request, Controller & Vue — Set/Reset PIN Member):**
  - Buat `ResetMemberPinRequest` (`pin` wajib tepat 6 digit numerik `regex:/^\d{6}$/` & konfirmasi) dan dukung field `pin` opsional pada `StoreMemberRequest`.
  - Tambahkan endpoint `POST /members/{member}/reset-pin` pada `MemberController` terproteksi `MemberPolicy` (anti-IDOR `home_branch_id`), hash PIN, dan catat `member.pin_reset` ke `activity_logs` tanpa menyimpan nilai PIN.
  - Tambahkan aksi/modal **"Set / Reset PIN Portal"** pada `resources/js/Pages/Members/Index.vue` dan input PIN opsional pada `Members/Form.vue`.

### Autentikasi & Layanan Portal Member (`FR-P3-09` s.d. `FR-P3-12`)
- [x] **Task 12.4 (Request & Controller — Login/Logout Portal Member):**
  - Buat `MemberLoginRequest` (`identifier` required string, `pin` `regex:/^\d{6}$/`).
  - Buat `MemberAuthController` (`showLogin` `GET /member/login`, `login` `POST /member/login`, `logout` `POST /member/logout`): cari member berdasarkan `member_code` atau `phone` (tolak bila ambigu/tidak ditemukan, `status !== 'active'`, atau `pin_hash === null`), terapkan `RateLimiter` (`member-pin:{identifier}|{ip}`, maks 5 gagal, decay 900 detik, balas HTTP `429`), verifikasi `Hash::check`, login melalui `Auth::guard('member')`.
- [x] **Task 12.5 (Service — `MemberPortalService`):** Buat `App\Services\MemberPortalService` yang menghitung untuk member terpilih pada filter periode (`current_year` default, `current_month`, `last_year`):
  - `net_spending_total`, `home_branch_spending`, dan `cross_branch_spending` (transaksi `paid` dikurangi `transaction_returns` `completed`);
  - `net_member_savings` (`SUM(transactions.member_discount_total)` dikurangi diskon proporsional item yang diretur `(regular_price_snapshot - unit_price_snapshot) * qty_returned`);
  - `estimated_shu` berdasarkan porsi belanja bersih member terhadap total belanja bersih seluruh member cabang asal dikalikan pool `shu_allocation_member_usaha` dari `net_profit` positif cabang asal via `FinancialReportService` (`0` bila `net_profit <= 0` atau penyebut `0`).
- [x] **Task 12.6 (Request, Controller & Routes — Portal Member):**
  - Buat `UpdateMemberPinRequest` (`current_pin`, `new_pin` 6 digit berbeda dari `current_pin`, `new_pin_confirmation`).
  - Buat `MemberPortalController` (`dashboard` `GET /member`, `transactions` `GET /member/transactions`, `showTransaction` `GET /member/transactions/{transaction}`, `security` `GET /member/security`, `updatePin` `PUT /member/security/pin`) terproteksi `auth.member` dengan validasi kepemilikan ketat `transaction.member_id === auth('member')->id()` (HTTP `403` jika bukan miliknya).

### Frontend Vue 3 Portal Member (Mobile-First)
- [x] **Task 12.7 (Vue — Layout Mobile-First & Login Member):** Buat `resources/js/Layouts/MemberLayout.vue` (header ringkas + navigasi tab bawah *mobile-first* Beranda, Riwayat, Keamanan + tombol Keluar) dan `resources/js/Pages/Member/Login.vue` (input Kode Member / No HP + PIN 6 digit, pesan sisa waktu tunggu HTTP `429`, tautan kembali ke Login Admin/Kasir).
- [x] **Task 12.8 (Vue — Beranda Kartu Digital, Riwayat & Ganti PIN):**
  - Buat `resources/js/Pages/Member/Dashboard.vue` (Kartu Member Digital bergaya kartu keanggotaan modern + SVG QR Code via `qrcode`, status RFID, pemilih periode, 4 kartu metrik Belanja Bersih, Asal vs Lintas Cabang, Total Hemat Member, Estimasi SHU Berjalan + disclaimer RAT).
  - Buat `resources/js/Pages/Member/Transactions.vue` (daftar transaksi `paid` terpaginasi dengan badge `KOPDES Asal` / `Lintas Cabang` & status retur) dan `resources/js/Pages/Member/TransactionShow.vue` (struk digital lengkap dengan perbandingan harga normal vs member & rincian retur).
  - Buat `resources/js/Pages/Member/Security.vue` (form ganti PIN 6 digit mandiri).

### Pengujian Milestone 12
- [x] **Task 12.9 (Feature Test — Milestone 12):** Buat `tests/Feature/Milestone12MemberPortalTest.php` yang menguji: (a) login sukses via `member_code` dan via `phone` + PIN 6 digit; (b) salah PIN ke-6 mengembalikan HTTP `429`; (c) member `inactive` atau `pin_hash = null` ditolak login; (d) sesi guard `member` tidak dapat mengakses `/dashboard` maupun `/pos`, dan tamu `/member` diarahkan ke `/member/login`; (e) akses `GET /member/transactions/{id}` milik member lain ditolak HTTP `403`; (f) perhitungan Belanja Bersih (Asal vs Lintas Cabang), Total Hemat Bersih (pasca-retur), dan Estimasi SHU Berjalan (termasuk kondisi `net_profit <= 0`) akurat; (g) ganti PIN mandiri menolak `current_pin` salah dan berhasil memperbarui hash saat valid; (h) `admin_cabang` dapat mereset PIN member cabangnya (tercatat di `activity_logs`) namun ditolak HTTP `403` untuk member cabang lain.

---

## Milestone 13: Mode Demo Portofolio, Simulator Hardware, Rich `DemoDataSeeder` & Final Regression

> **Tujuan:** Menghadirkan pengalaman demo portofolio terbaik (Quick-Fill Login, Simulator Scan Barcode & Tap RFID, serta data realistis 3 bulan) yang terisolasi ketat di balik flag `APP_DEMO_MODE`, lalu memverifikasi regresi penuh seluruh proyek.

### Konfigurasi & Endpoint Simulator Demo (`FR-P3-13`)
- [x] **Task 13.1 (Konfigurasi & Middleware — `APP_DEMO_MODE`):**
  - Tambahkan `'demo_mode' => (bool) env('APP_DEMO_MODE', false)` di `config/kopdes.php` (tanpa mengedit `.env`).
  - Bagikan `app.demo_mode` pada `HandleInertiaRequests`.
  - Buat middleware `App\Http\Middleware\EnsureDemoModeEnabled` (mengembalikan HTTP `404 Not Found` bila `config('kopdes.demo_mode') === false`) dan daftarkan alias `demo.mode` di `bootstrap/app.php`.
- [x] **Task 13.2 (Controller & Routes — Sampel Simulator POS):** Buat `DemoSimulatorController` (`posSamples` `GET /demo/pos-samples` terproteksi `auth`, `role:kasir`, `demo.mode`) yang mengembalikan maksimal 8 produk aktif berstok pada cabang kasir (campuran produk dengan `member_price` & tanpa `member_price`) serta maksimal 3 member aktif dengan `card_uid` (minimal 1 Member KOPDES Asal & 1 Member Lintas Cabang).

### Frontend Vue 3 Quick-Fill & Simulator Hardware
- [x] **Task 13.3 (Vue — Panel Quick-Fill Login):** Tambahkan panel **"Mode Demo Portofolio"** (hanya dirender jika `$page.props.app.demo_mode === true`) yang mengisi form secara normal (tanpa bypass backend) pada:
  - `resources/js/Pages/Auth/Login.vue` (4 tombol: Admin Nasional, Admin Provinsi Jatim, Admin Kabupaten Ponorogo, Admin Cabang Ngunut);
  - `resources/js/Pages/Pos/Login.vue` (2 tombol: Kasir Cabang A & Kasir Cabang B beserta UID kartu & PIN);
  - `resources/js/Pages/Member/Login.vue` (2 tombol: Member Asal & Member Lintas Cabang beserta kode & PIN).
- [x] **Task 13.4 (Vue — Simulator Hardware POS & RFID Capture):**
  - Buat `resources/js/Pages/Pos/Partials/HardwareSimulatorPopover.vue` dan pasang di `Pos/Index.vue` (hanya muncul saat `demo_mode === true`): klik produk memicu fungsi scan barcode existing, klik member memicu lookup RFID existing.
  - Perbarui `resources/js/Components/RfidCaptureModal.vue` agar menampilkan tombol **"Simulasi Tap Kartu Baru"** (menghasilkan UID demo unik terformat) khusus saat `demo_mode === true`.

### Rich `DemoDataSeeder` & Dokumentasi Portofolio (`FR-P3-14`)
- [x] **Task 13.5 (Seeder — `DemoDataSeeder` 3 Bulan Realistis):** Buat `Database\Seeders\DemoDataSeeder` yang bersifat *idempotent* dan selesai $< 15$ detik, menghasilkan: 2 Provinsi, 3 Kabupaten, 4 Cabang KOPDES aktif, 15+ produk sembako/UMKM (campuran `member_price` & reguler), stok cabang & `stock_movements`, 10+ member (campuran punya RFID + PIN Portal & belum binding RFID), 60+ transaksi tersebar 3 bulan terakhir (`cash` & `midtrans`, member asal & lintas cabang, non-member), shift tertutup (seimbang, surplus, minus), retur (`restockable` & `damaged`), biaya operasional lintas kategori, pengaturan awal `app_settings`, serta sampel `activity_logs`.
- [x] **Task 13.6 (Dokumentasi — `README.md`):** Perbarui `README.md` proyek dengan ringkasan arsitektur sistem KOPDES, daftar fitur lengkap MVP hingga Fase 3, panduan instalasi lokal, cara mengaktifkan `APP_DEMO_MODE=true` & menjalankan `DemoDataSeeder`, tabel akun demo (Admin, Kasir, Member), serta perintah menjalankan seluruh test suite.

### Pengujian Milestone 13 & Final Regression
- [x] **Task 13.7 (Feature Test & Final Regression):**
  - Buat `tests/Feature/Milestone13DemoModeAndSeederTest.php` yang menguji: (a) saat `demo_mode = false`, `GET /demo/pos-samples` mengembalikan HTTP `404` dan shared props `app.demo_mode === false`; (b) saat `demo_mode = true`, `GET /demo/pos-samples` mengembalikan HTTP `200` berisi produk cabang kasir & member sampel; (c) menjalankan `DemoDataSeeder` dua kali berturut-turut berhasil tanpa error (*idempotent*) dan menghasilkan data multi-cabang, transaksi lintas cabang, shift, retur, biaya operasional, serta activity log.
  - Jalankan full test suite (`php artisan test` mencakup Milestone 1–13 + `OperationalExpenseAndExportTest`) hingga lulus **100%**, lalu jalankan `npm run build` dan pastikan selesai dengan **0 error**.