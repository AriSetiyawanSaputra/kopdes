# PRD Phase 3: Utilitas Onboarding Massal, Cetak Barcode/Kartu, Pengaturan & Audit Trail, Portal Member, serta Mode Demo Portofolio

**Versi Dokumen:** 3.0 (Phase 3 / Portfolio-Ready Baseline)  
**Status:** Terkunci (Single Source of Truth untuk Implementasi Fase 3)  
**Prasyarat Baseline:** MVP Milestone 1–5 (`PRD.md` & `TASKS.md`), Modul Biaya Operasional/SHU/Ekspor Excel & PDF, serta Fase 2 Milestone 6–9 (`PRD_PHASE2.md` & `TASKS_PHASE2.md`) telah selesai dan seluruh test lulus 100%.  
**Tech Stack:** Laravel (PHP 8.2+), MySQL 8.x / MariaDB 10.6+, Inertia.js + Vue.js 3 (`<script setup>`) + Tailwind CSS, library Excel/PDF existing, `jsbarcode` (SVG Barcode 1D), `qrcode` (SVG QR Code).

---

## 1. Ringkasan Eksekutif, Tambahan Library & Batasan Fase 3

### 1.1 Ringkasan Eksekutif
Fase 3 menyempurnakan **Sistem Kasir & Manajemen KOPDES (Koperasi Desa Merah Putih)** dari aplikasi operasional inti menjadi platform koperasi menyeluruh yang siap didemonstrasikan secara meyakinkan sebagai proyek portofolio maupun digunakan untuk *onboarding* cabang nyata.

Lima kapabilitas terintegrasi yang dibangun pada Fase 3 adalah:
1. **Seeder Wilayah Resmi & Onboarding Massal (Pilar 1):** Command Artisan *chunked upsert* untuk impor wilayah baku dari CSV, serta fitur Import Massal Excel/CSV dengan laporan validasi per baris untuk Master Produk, Stok Awal Cabang, dan Daftar Anggota (dengan kolom `members.card_uid` diubah menjadi `nullable` + `UNIQUE` agar kartu fisik bisa di-*binding* menyusul).
2. **Cetak Label Barcode & Kartu Member (Pilar 2):** Generator cetak stiker barcode produk (Code-128/EAN-13 berbasis SVG) untuk produk lokal/curah tanpa barcode pabrikan, serta template cetak kartu anggota fisik lengkap dengan QR Code `member_code`.
3. **Pengaturan Sistem Dinamis & Audit Trail Admin (Pilar 3):** Pemindahan parameter bisnis kunci (nama lembaga, logo koperasi, footer struk thermal, ukuran kertas default, batas hari retur, dan persentase alokasi SHU) ke tabel `app_settings` ber-cache khusus `admin_nasional`, serta pencatatan jejak audit *immutable* (`activity_logs`) untuk seluruh aksi administratif sensitif.
4. **Portal Mandiri Member (Mobile-First, Read-Only + Ganti PIN):** Akses mandiri bagi anggota koperasi melalui Laravel Auth Guard terpisah (`member`) di `/member/login` untuk menampilkan Kartu Member Digital, ringkasan belanja (KOPDES Asal vs Lintas Cabang), Total Hemat Member, Estimasi SHU Berjalan, riwayat transaksi/struk digital, serta fitur ganti PIN.
5. **Mode Demo & Simulator Hardware (Portfolio Showcase):** Tombol *Quick-Fill Login* di seluruh halaman login (Admin, Kasir, Member), Simulator Scan Barcode & Tap Kartu RFID langsung di layar POS/modal, serta `DemoDataSeeder` kaya data 3 bulan terakhir—seluruhnya terisolasi ketat di balik flag `APP_DEMO_MODE=true`.

### 1.2 Tambahan Library Frontend (NPM)
Tidak ada penggantian library backend existing (tetap menggunakan package Excel & PDF yang sudah terpasang di `composer.json`). Tambahan minimal di sisi frontend (`package.json`):
* **`jsbarcode`**: Merender barcode 1D (Code-128 / EAN-13) sebagai SVG tajam pada halaman cetak label produk (`@media print`).
* **`qrcode`**: Merender QR Code berbasis SVG/DataURL untuk Kartu Member Fisik dan Kartu Member Digital pada Portal Member.

### 1.3 Batasan Ruang Lingkup Fase 3 (In-Scope vs. Out-of-Scope)

| Kategori | In-Scope (Wajib Dibangun di Fase 3) | Out-of-Scope (Di Luar Cakupan Portofolio Ini) |
| :--- | :--- | :--- |
| **Wilayah & Import Massal** | Command Artisan `kopdes:import-regions {file}` berbasis CSV (*chunked upsert*); download template & import Excel/CSV untuk Produk (`admin_nasional`), Stok Awal Cabang (`admin_cabang`), dan Member (`admin_cabang`/`admin_nasional`) dengan validasi *all-or-nothing* + laporan error per baris; `members.card_uid` menjadi `nullable` + `UNIQUE`. | Integrasi API sinkronisasi real-time ke server Kemendagri/BPS; import transaksi penjualan historis dari aplikasi POS pihak ketiga; background async import via email notification. |
| **Cetak Barcode & Kartu** | Halaman generator cetak label barcode produk (pilih multi-produk, atur jumlah salinan, mode Kertas Stiker A4 & Label Thermal, tampilkan Harga Normal & Harga Member); cetak layout kartu anggota fisik (front card + QR `member_code`). | Integrasi printer zebra ZPL/EPL langsung via WebUSB/Bluetooth; desain editor drag-and-drop kartu member; cetak kartu RFID *encoding* (menulis chip NFC). |
| **Pengaturan & Audit Trail** | Tabel `app_settings` ber-cache (khusus `admin_nasional`) untuk nama lembaga, logo (upload file `public` storage), footer struk, default ukuran struk (58mm/80mm), `return_max_days`, dan persentase alokasi SHU; tabel `activity_logs` *append-only* untuk mencatat aksi sensitif beserta *diff* nilai lama vs baru. | Pengaturan per cabang yang berbeda-beda (tetap terpusat nasional); ekspor log audit ke SIEM eksternal; fitur *rollback* otomatis dari riwayat audit log. |
| **Portal Mandiri Member** | Laravel Auth Guard `member` terpisah; login via `member_code` atau `phone` + PIN 6 digit (`RateLimiter` 5x gagal); reset/set PIN member oleh `admin_cabang`/`admin_nasional`; tampilan mobile-first: Kartu Digital + QR, KPI Belanja (Asal vs Lintas Cabang), Total Hemat Member, Estimasi SHU Berjalan, Riwayat Transaksi & Struk Digital, Ganti PIN mandiri. | Registrasi member mandiri (*self-signup*) dari publik; reset PIN via OTP SMS/WhatsApp; belanja online (*e-commerce*) / pemesanan barang dari Portal Member; pengajuan klaim pencairan SHU dari portal. |
| **Mode Demo & Simulator** | Flag `config('kopdes.demo_mode')` (`APP_DEMO_MODE`, default `false`); Quick-Fill Login pada `/login`, `/pos/login`, `/member/login`; panel Simulator Hardware (Scan Barcode produk, Tap Kartu Member Asal/Lintas Cabang, Simulasi Tap RFID saat pendaftaran) di Vue; `DemoDataSeeder` realistis 3 bulan. | Reset database demo otomatis tiap jam via cron publik; tur interaktif multi-langkah (*onboarding spotlight library*). |

### 1.4 Prinsip Backward Compatibility
1. Mengubah `members.card_uid` menjadi `nullable` tetap mempertahankan constraint `UNIQUE` (di MySQL/MariaDB, banyak nilai `NULL` diizinkan dalam kolom `UNIQUE` sambil tetap mencegah duplikasi nilai non-`NULL`). Member dengan `card_uid = NULL` tidak dapat ditemukan lewat lookup RFID POS, namun tetap dapat login di Portal Member menggunakan `member_code` atau `phone`.
2. Seluruh helper pembacaan pengaturan (`AppSettingService::get(...)`) wajib memiliki nilai fallback identik dengan konfigurasi MVP/Fase 2 (`return_max_days = 7`, persentase SHU existing, dll.) sehingga seluruh Feature Test Milestone 1–9 tetap lulus tanpa ketergantungan seeder pengaturan.
3. Guard default Laravel tetap `web` (`users`). Guard baru `member` (`members`) hanya aktif pada grup route `/member/*` dan `/member/login` sehingga sesi Admin maupun Kasir tidak terganggu.

---

## 2. Pembaruan Hak Akses (RBAC), Auth Guard & Aturan Anti-IDOR

### 2.1 Arsitektur Dual Auth Guard Laravel (`config/auth.php`)

| Nama Guard | Driver | Provider & Model | Route Prefix | Identitas Login |
| :--- | :--- | :--- | :--- | :--- |
| `web` (Default) | `session` | `users` (`App\Models\User`) | `/login`, `/dashboard`, `/pos/*`, dll. | Admin: Email + Password; Kasir: `card_uid` + PIN 6 digit |
| `member` (Baru) | `session` | `members` (`App\Models\Member`) | `/member/login`, `/member/*` | Member: `member_code` atau `phone` + PIN 6 digit |

* **Isolasi Session & Redirect:** Middleware khusus `EnsureMemberAuthenticated` (atau `auth:member`) mengarahkan tamu yang belum login pada `/member/*` ke `/member/login` (bukan `/login`). Sebaliknya, akun `member` yang login tidak memiliki akses apa pun ke `/dashboard` maupun `/pos`.

### 2.2 Matriks Hak Akses (RBAC) Fase 3

Keterangan: **C** = Create/Import, **R** = Read, **U** = Update, **X** = Aksi Cetak/Eksekusi, **-** = Dilarang (HTTP 403 / Redirect Login Guard).

| Modul / Sumber Daya Fase 3 | `admin_nasional` | `admin_provinsi` | `admin_kabupaten` | `admin_cabang` | `kasir` | `member` (Guard `member`) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Import Massal Master Produk** | C & R (Template) | - | - | - | - | - |
| **Import Massal Stok Awal Cabang** | C (Pilih cabang) | - | - | C (Cabang sendiri) | - | - |
| **Import Massal Member** | C (Pilih cabang) | - | - | C (`home_branch_id` sendiri) | - | - |
| **Set / Reset PIN 6 Digit Member** | U (Semua member) | - | - | U (Member cabang asal sendiri) | - | U (Ganti PIN sendiri via PIN lama) |
| **Cetak Label Barcode Produk** | X | X | X | X | X (Produk aktif) | - |
| **Cetak Layout Kartu Member Fisik** | X (Semua member) | R | R | X (Member cabang asal sendiri) | - | R (Kartu Digital sendiri) |
| **Pengaturan Sistem (`app_settings`)** | R & U | R (Read-only internal) | R (Read-only internal) | R (Read-only internal) | R (Footer/kertas struk) | R (Nama/logo koperasi) |
| **Audit Trail (`activity_logs`)** | R (Semua cabang & global) | R (Provinsi) | R (Kabupaten) | R (Cabang sendiri) | - | - |
| **Portal Mandiri Member (`/member/*`)** | - | - | - | - | - | R (Data & transaksi miliknya sendiri) |

### 2.3 Aturan Scoping Anti-IDOR Fase 3
1. **Anti-IDOR Portal Member:** Setiap query pada `MemberPortalController` wajib mengunci klausa `where('member_id', auth('member')->id())`. Jika Member A mengakses `GET /member/transactions/{transaction}` dengan ID transaksi milik Member B (atau transaksi non-member), backend mengembalikan HTTP `403 Forbidden` (via `MemberPortalPolicy` / validasi eksplisit).
2. **Anti-IDOR Import Massal & Reset PIN Member:** `admin_cabang` yang mengimpor Stok Awal atau Member tidak boleh menyuntikkan `branch_id` cabang lain pada payload request—backend selalu memaksa `branch_id = auth()->user()->branch_id`. Saat `admin_cabang` mereset PIN atau mencetak kartu fisik member, sistem memvalidasi `$member->home_branch_id === auth()->user()->branch_id` (HTTP `403` jika berbeda).
3. **Anti-IDOR Audit Trail (`activity_logs`):** Model `ActivityLog` menggunakan trait `HasRegionScope` (berdasarkan `branch_id`, `regency_id`, `province_id` yang disimpan pada log). Log bertingkat global (seperti perubahan `app_settings` atau `products` dengan `branch_id = NULL`) hanya terlihat oleh `admin_nasional`.

---

## 3. Alur Pengguna Utama (User Flows)

### 3.1 Alur Import Wilayah Resmi & Onboarding Massal (All-or-Nothing)

```text
A. Import Wilayah Resmi (CLI Artisan)
[Admin/Developer jalankan: php artisan kopdes:import-regions storage/app/regions.csv --type=villages]
   │ 1. Validasi keberadaan file & header kolom wajib CSV
   │ 2. Baca file secara streaming (LazyCollection / fgetcsv, hemat memori)
   │ 3. Chunk per 1.000 baris -> DB::table(...)->upsert() (idempotent)
   └── Output progress bar + jumlah baris diproses & waktu eksekusi

B. Import Massal Excel/CSV di Dashboard (Produk / Stok Awal / Member)
[Admin unduh Template Excel (.xlsx) dari halaman Produk / Inventori / Member]
   │ 1. Admin mengisi baris data pada Excel dan upload ke POST /imports/{type}
   ▼
[BulkImportService parse file & validasi seluruh baris terlebih dahulu]
   ├── Ada >= 1 baris error (duplikat barcode/SKU/NIK/card_uid, format salah, member_price > selling_price, duplikat internal antar baris file):
   │     -> Batalkan seluruh penyimpanan (All-or-Nothing)
   │     -> Kembalikan HTTP 422 berisi array errors: [{ row: 14, field: 'barcode', message: '...' }]
   │     -> UI menampilkan tabel error per baris agar Admin langsung memperbaiki file
   └── 0 error pada seluruh baris:
         -> DB::transaction():
            - Produk: insert ke `products`
            - Stok Awal: `lockForUpdate()` pada `branch_stocks`, tambah/set stok + tulis `stock_movements` (type='restock', notes='Import Stok Awal')
            - Member: generate `member_code`, normalisasi `card_uid` (jika diisi; boleh NULL), hash PIN awal (jika diisi)
         -> Catat 1 ringkasan log ke `activity_logs` (jumlah baris diimpor)
         -> Kembalikan flash sukses beserta total record berhasil
```

### 3.2 Alur Cetak Label Barcode Produk & Kartu Member Fisik
1. **Cetak Label Barcode (`/products/barcode-labels`):**
   * Admin atau Kasir mencari dan memilih satu atau beberapa produk aktif, lalu menentukan jumlah salinan stiker per produk (misal: Beras 5kg sebanyak 12 stiker, Gula 1kg sebanyak 8 stiker).
   * Memilih mode tata letak cetak: **Kertas Stiker A4 (Grid 3x8 / 24 label per halaman)** atau **Printer Label Thermal (50x30mm / 1 label per baris)**.
   * Memilih opsi tampilan: tampilkan `Harga Normal` dan (opsional) `Harga Member`.
   * Vue merender SVG barcode via `jsbarcode` (auto-pilih `EAN13` jika 13 digit numerik valid, fallback `CODE128` untuk format lainnya), lalu tombol **"Cetak Label"** memicu `window.print()` dengan stylesheet `@media print` bersih.
2. **Cetak Kartu Member Fisik (`/members/{member}/card` atau cetak batch `/members/print-cards`):**
   * `admin_cabang` memilih satu atau beberapa member cabangnya → klik **"Cetak Kartu Member"**.
   * Vue merender layout ukuran kartu standar ID-1 ($85.6\text{ mm} \times 53.98\text{ mm}$) menampilkan Logo & Nama KOPDES dari `app_settings`, Nama Anggota, `member_code`, Nama KOPDES Asal, dan SVG QR Code berisi `member_code` via `qrcode`.

### 3.3 Alur Pengaturan Sistem Dinamis & Audit Trail
1. `admin_nasional` membuka menu **Pengaturan Sistem** (`/settings`).
2. Mengubah parameter (misal: mengubah `return_max_days` dari 7 menjadi 10 hari, mengubah `receipt_footer`, mengunggah logo koperasi, atau menyesuaikan komposisi persentase SHU yang total alokasinya wajib tepat $100\%$).
3. Saat `PUT /settings` disubmit:
   * `AppSettingService` memvalidasi seluruh aturan bisnis (termasuk total persentase alokasi SHU $= 100\%$), menyimpan ke `app_settings`, menghapus cache `Cache::forget('kopdes.app_settings')`, dan mencatat perubahan (`old_values` vs `new_values`) ke `activity_logs`.
   * Seluruh modul terkait (`TransactionReturnService`, `FinancialReportService`, `ReceiptThermal.vue`, Ekspor PDF/Excel, dan Portal Member) otomatis membaca nilai terbaru dari `AppSettingService` (melalui shared Inertia props untuk data non-sensitif).

### 3.4 Alur Portal Mandiri Member (`/member/login` -> `/member`)

```text
[Anggota buka /member/login di HP atau Browser]
   │ 1. Input Kode Member ATAU Nomor HP + PIN 6 Digit
   │    (Jika APP_DEMO_MODE=true, tersedia tombol Quick-Fill Member Demo)
   ▼
[POST /member/login -> MemberAuthController]
   ├── RateLimiter cek 'member-pin:{identifier}|{ip}' (maks 5x gagal -> blokir 15 menit / HTTP 429)
   ├── Validasi member ditemukan, status == 'active', dan pin_hash tidak NULL
   ├── Validasi Hash::check($pin, $member->pin_hash)
   └── Sukses -> Auth::guard('member')->login($member) -> Redirect ke /member
   │
   ▼
[Dashboard Portal Member (/member — Mobile-First)]
   ├── Tab 1: Beranda & Kartu Digital
   │     - Kartu Member Digital (Nama, member_code, KOPDES Asal, Status RFID aktif/belum di-binding, QR Code)
   │     - KPI Periode (default Tahun Berjalan):
   │         1. Total Belanja Bersih (pasca-retur)
   │         2. Rincian Belanja: KOPDES Asal vs Lintas Cabang
   │         3. Total Hemat Harga Member (SUM member_discount_total bersih)
   │         4. Estimasi SHU Jasa Usaha Berjalan (beserta catatan "Estimasi sementara sebelum RAT")
   ├── Tab 2: Riwayat Belanja (/member/transactions)
   │     - Daftar transaksi paid milik member (badge lokasi KOPDES, status retur jika ada)
   │     - Klik transaksi -> Modal/Halaman Struk Digital (item, harga normal vs member, hemat, info retur)
   └── Tab 3: Keamanan (/member/security)
         - Form Ganti PIN 6 Digit (wajib masukkan PIN lama, PIN baru, konfirmasi PIN baru)
```

### 3.5 Alur Mode Demo & Simulator Hardware (Khusus `APP_DEMO_MODE=true`)
1. Reviewer menjalankan aplikasi dengan `APP_DEMO_MODE=true` dan `php artisan migrate:fresh --seed` (atau `php artisan db:seed --class=DemoDataSeeder`).
2. **Quick-Fill Login:** Di `/login`, `/pos/login`, dan `/member/login`, muncul panel berlabel **"Mode Demo Portofolio"** dengan tombol 1-klik untuk mengisi kredensial (Admin Nasional, Admin Provinsi Jatim, Admin Kabupaten Ponorogo, Admin Cabang Ngunut, Kasir Ngunut + RFID + PIN, dan Member Lintas Cabang + PIN).
3. **Simulator Hardware di POS (`Pos/Index.vue`):** Di pojok kanan bawah (atau toolbar atas POS) muncul tombol **"Simulator Hardware"** yang membuka popover ringan berisi:
   * Daftar 5–8 produk sampel (klik 1x langsung memicu fungsi *scan barcode* yang sama persis dengan USB Barcode Scanner).
   * Daftar 2–3 kartu member sampel (Member KOPDES Asal dan Member KOPDES Desa Lain; klik 1x langsung memicu lookup RFID di `MemberLookupModal.vue` atau menautkan member lewat jalur normal).
4. **Simulator RFID di Form Pendaftaran:** Pada `RfidCaptureModal.vue`, muncul tombol **"Generate UID Kartu Demo"** agar reviewer bisa menguji pendaftaran kasir/member baru tanpa alat RFID fisik.
5. **Keamanan Non-Demo:** Ketika `APP_DEMO_MODE=false` (nilai default di `config/kopdes.php`), seluruh komponen UI demo dan endpoint daftar sampel demo **tidak dirender dan ditolak HTTP 404**.

---

## 4. Spesifikasi Fungsional & Acceptance Criteria per Modul

### Modul 10: Seeder Wilayah Resmi & Import Massal (`FR-P3-01` s.d. `FR-P3-04`)

* **`FR-P3-01` Command Artisan Import Wilayah Baku (`kopdes:import-regions`)**
  * **Deskripsi:** CLI untuk mengimpor data resmi Provinsi, Kabupaten/Kota, Kecamatan, dan Desa dari file CSV secara cepat dan hemat memori.
  * **Acceptance Criteria:**
    1. Mendukung opsi `--type=provinces|regencies|districts|villages` serta pembacaan file CSV secara *streaming* dengan `upsert` per 1.000 baris.
    2. Menolak file yang tidak ada atau memiliki header CSV tidak sesuai dengan exit code non-zero dan pesan error jelas.
    3. Pengulangan command pada file yang sama bersifat *idempotent* (memperbarui nama tanpa menduplikasi PK).

* **`FR-P3-02` Import Massal Master Produk (`admin_nasional`)**
  * **Deskripsi:** Unggah file Excel (`.xlsx`) atau `.csv` untuk menambahkan produk baru secara massal ke katalog global.
  * **Acceptance Criteria:**
    1. Menyediakan endpoint unduh template `GET /imports/products/template` berisi kolom: `category_name`, `sku`, `barcode`, `name`, `unit`, `cost_price`, `selling_price`, `member_price`.
    2. Validasi per baris menolak jika `sku` atau `barcode` sudah ada di DB maupun duplikat di dalam file, `category_name` tidak ditemukan, `cost_price < 0`, `selling_price < 0`, atau `member_price > selling_price`.
    3. Bersifat *all-or-nothing*: jika terdapat error di baris mana pun, tidak ada satu pun produk yang tersimpan dan UI menampilkan daftar error lengkap dengan nomor baris Excel.
    4. Jika sukses, mencatat ringkasan ke `activity_logs`.

* **`FR-P3-03` Import Massal Stok Awal Cabang (`admin_cabang` / `admin_nasional`)**
  * **Deskripsi:** Unggah file Excel/CSV untuk mengisi stok awal cabang secara massal.
  * **Acceptance Criteria:**
    1. Template `GET /imports/stocks/template` berisi kolom: `barcode`, `qty_add`, `min_stock`, `notes`.
    2. Setiap baris valid mengeksekusi `lockForUpdate()` pada `branch_stocks`, menambah `quantity += qty_add`, memperbarui `min_stock` jika diisi, dan menulis tepat 1 baris ke `stock_movements` (`type = 'restock'`).
    3. Untuk `admin_cabang`, target cabang dikunci otomatis ke `auth()->user()->branch_id` (anti-IDOR).

* **`FR-P3-04` Import Massal Member & Kolom `card_uid` Nullable**
  * **Deskripsi:** Unggah file Excel/CSV anggota desa oleh `admin_cabang` atau `admin_nasional` tanpa mewajibkan kartu RFID langsung tersedia.
  * **Acceptance Criteria:**
    1. Kolom `members.card_uid` berubah menjadi `nullable` dengan constraint `UNIQUE` tetap aktif.
    2. Template `GET /imports/members/template` berisi kolom: `name`, `phone`, `nik`, `card_uid` (opsional), `pin` (opsional, 6 digit), `joined_at` (opsional, default hari ini).
    3. Jika `card_uid` diisi pada baris Excel, nilainya dinormalisasi dengan `CardUidNormalizer` dan divalidasi unik lintas tabel `users` dan `members` serta unik antar baris file.
    4. Member dengan `card_uid = NULL` ditandai badge **"Belum Binding RFID"** pada `Members/Index.vue` dengan tombol cepat **"Pasang Kartu RFID"** menggunakan `RfidCaptureModal.vue`.

### Modul 11: Cetak Label Barcode & Kartu Member (`FR-P3-05` s.d. `FR-P3-06`)

* **`FR-P3-05` Generator Cetak Stiker Barcode Produk**
  * **Deskripsi:** Antarmuka pemilihan produk dan pencetakan stiker barcode berbasis SVG (`jsbarcode`).
  * **Acceptance Criteria:**
    1. Dapat diakses oleh seluruh role Admin dan Kasir pada `/products/barcode-labels`.
    2. Mendukung pemilihan banyak produk sekaligus dengan kuantitas label masing-masing ($\ge 1$, maksimal total 200 label per sekali cetak agar browser tidak lag).
    3. Mendukung 2 preset layout `@media print`: **A4 Stiker Grid (3 kolom)** dan **Thermal Label (50x30mm)**, menampilkan Nama Produk, SVG Barcode + teks angka barcode, Harga Normal, dan Harga Member (jika tersedia dan opsi diaktifkan).

* **`FR-P3-06` Cetak Layout Kartu Member Fisik**
  * **Deskripsi:** Template cetak kartu anggota dengan QR Code `member_code` (`qrcode`).
  * **Acceptance Criteria:**
    1. `admin_cabang` dapat mencetak kartu untuk member dari cabangnya (`home_branch_id`), baik satuan maupun pilihan multi-member (*batch print* maksimal 20 kartu per halaman A4).
    2. Setiap kartu memuat Logo & Nama Lembaga (dari `app_settings`), Nama KOPDES Asal, Nama Anggota, `member_code`, Tanggal Bergabung, dan QR Code SVG yang merepresentasikan `member_code`.

### Modul 12: Pengaturan Sistem Dinamis & Audit Trail Admin (`FR-P3-07` s.d. `FR-P3-08`)

* **`FR-P3-07` Pengaturan Sistem Terpusat (`app_settings`)**
  * **Deskripsi:** Pengelolaan konfigurasi operasional koperasi dari Dashboard `admin_nasional` dengan caching.
  * **Acceptance Criteria:**
    1. Hanya `admin_nasional` yang dapat mengakses `GET /settings` dan `PUT /settings` (role lain ditolak HTTP `403`).
    2. Parameter yang dikelola secara eksplisit:
       - `institution_name` (string, wajib, default: `'Koperasi Desa Merah Putih'`)
       - `institution_logo_path` (upload gambar PNG/JPG/WebP maks 2MB, opsional)
       - `receipt_footer` (string maks 255 karakter, default: `'Terima kasih telah berbelanja di KOPDES Merah Putih.'`)
       - `default_receipt_paper` (enum `'58mm'|'80mm'`, default `'58mm'`)
       - `return_max_days` (integer 1–90, default `7`)
       - `shu_allocation_member_usaha` (numeric 0–100, default persentase jasa usaha anggota sesuai existing)
       - `shu_allocation_reserve` (numeric 0–100, dana cadangan koperasi)
       - `shu_allocation_management` (numeric 0–100, dana pengurus & pengawas)
       - `shu_allocation_education` (numeric 0–100, dana pendidikan & sosial)
    3. Validasi wajib memastikan penjumlahan seluruh persentase alokasi SHU tepat sama dengan $100\%$.
    4. Perubahan `return_max_days` langsung berlaku pada `TransactionReturnService`, perubahan persentase SHU langsung berlaku pada `FinancialReportService` & Portal Member, dan perubahan `institution_name`/`receipt_footer`/`institution_logo_path` langsung tampil pada struk thermal dan ekspor PDF/Excel.

* **`FR-P3-08` Audit Trail / Activity Log Admin (`activity_logs`)**
  * **Deskripsi:** Pencatatan otomatis dan *append-only* atas seluruh tindakan administratif penting.
  * **Acceptance Criteria:**
    1. Sistem mencatat otomatis ke `activity_logs` pada 8 peristiwa wajib berikut:
       - `product.price_updated` (perubahan `cost_price`, `selling_price`, atau `member_price`)
       - `branch.status_toggled` (aktivasi/nonaktivasi cabang)
       - `user.status_toggled` (aktivasi/nonaktivasi admin/kasir)
       - `member.status_toggled` & `member.card_replaced` & `member.pin_reset`
       - `shift.force_closed` (penutupan paksa shift kasir oleh admin)
       - `transaction.returned` (eksekusi retur parsial / full void)
       - `import.completed` (import massal produk, stok awal, atau member)
       - `settings.updated` (perubahan `app_settings`)
    2. Payload `old_values` dan `new_values` secara otomatis membuang/menyensor field sensitif (`password`, `pin_hash`, `remember_token`).
    3. Halaman `ActivityLogs/Index.vue` bersifat *read-only* (tanpa tombol edit/hapus), mendukung filter rentang tanggal, jenis aksi (`action`), aktor (`actor_id`), dan wilayah/cabang sesuai `HasRegionScope`.

### Modul 13: Portal Mandiri Member (`FR-P3-09` s.d. `FR-P3-12`)

* **`FR-P3-09` Autentikasi Portal Member & Pengelolaan PIN Member**
  * **Deskripsi:** Login khusus anggota pada `/member/login` menggunakan guard `member`, serta fitur set/reset PIN oleh admin cabang.
  * **Acceptance Criteria:**
    1. `admin_cabang` (khusus member cabangnya) dan `admin_nasional` dapat mengisi PIN 6 digit saat pembuatan member maupun mereset PIN lewat aksi **"Set/Reset PIN"** pada `Members/Index.vue` (tercatat di `activity_logs`).
    2. Member login di `/member/login` dengan `identifier` (`member_code` ATAU `phone` yang cocok tepat satu member) + `pin` 6 digit.
    3. Menolak member dengan `status = 'inactive'` atau `pin_hash === null` dengan pesan ramah *"Akun portal belum aktif. Hubungi Admin KOPDES asal Anda."*
    4. Dilindungi `RateLimiter` (`member-pin:{identifier}|{ip}`): maksimal 5 kali gagal berturut-turut, blokir 15 menit (HTTP `429`).

* **`FR-P3-10` Beranda Portal Member: Kartu Digital, Ringkasan Belanja & Estimasi SHU**
  * **Deskripsi:** Tampilan utama mobile-first setelah member berhasil login ke `/member`.
  * **Acceptance Criteria:**
    1. Menampilkan **Kartu Member Digital** berisi Logo & Nama KOPDES, Nama Member, `member_code`, KOPDES Asal (`homeBranch.name`), status kartu RFID (`Aktif` / `Belum Dipasang`), dan QR Code SVG `member_code`.
    2. Menampilkan 4 metrik untuk periode filter (default: Tahun Berjalan, bisa ganti ke Bulan Ini / Tahun Lalu):
       - **Total Belanja Bersih:** Total `transactions.total_amount` (`paid`) milik member dikurangi total `transaction_returns.total_refund_amount` (`completed`) milik member.
       - **Rincian Asal vs Lintas Cabang:** Pemisahan belanja di KOPDES Asal (`branch_id === member_home_branch_id`) vs KOPDES Desa Lain (`branch_id !== member_home_branch_id`).
       - **Total Hemat Harga Member:** Total `transactions.member_discount_total` dari transaksi `paid` milik member (dikurangi proporsi diskon item yang diretur, atau nol untuk item full void).
       - **Estimasi SHU Jasa Usaha Berjalan:** Dihitung proporsional berdasarkan porsi belanja bersih member terhadap total belanja seluruh member pada `home_branch_id` dikalikan pool alokasi Jasa Usaha Anggota (`shu_allocation_member_usaha` dari `net_profit` positif cabang asal, bernilai `Rp 0` jika `net_profit <= 0`), disertai label eksplisit *"Estimasi sementara sebelum penetapan RAT"*.

* **`FR-P3-11` Riwayat Transaksi & Struk Digital Member**
  * **Deskripsi:** Daftar belanja pribadi member lintas cabang beserta rincian struk.
  * **Acceptance Criteria:**
    1. Menampilkan daftar terpaginasi transaksi `paid` milik `auth('member')->id()` lengkap dengan Tanggal, Nomor Invoice, Nama KOPDES lokasi belanja (badge `KOPDES Asal` vs `Lintas Cabang`), Metode Bayar, Total Bayar, Total Hemat, dan Badge Retur (`Sebagian Diretur` / `Dibatalkan Penuh`).
    2. Mengklik transaksi membuka rincian struk digital (`Member/TransactionShow.vue`) termasuk daftar item, harga reguler vs harga member, dan rincian retur jika ada.
    3. Percobaan mengakses ID transaksi milik member lain ditolak HTTP `403`.

* **`FR-P3-12` Ganti PIN Mandiri Member**
  * **Deskripsi:** Anggota dapat mengganti PIN 6 digit miliknya sendiri di `/member/security`.
  * **Acceptance Criteria:**
    1. Mewajibkan verifikasi `current_pin` (`Hash::check`), `new_pin` tepat 6 digit numerik (`regex:/^\d{6}$/`), dan `new_pin_confirmation` cocok, serta `new_pin` tidak sama dengan `current_pin`.
    2. Memperbarui `members.pin_hash` tanpa mengeluarkan sesi member saat ini dan menampilkan pesan sukses.

### Modul 14: Mode Demo, Simulator Hardware & Rich Demo Seeder (`FR-P3-13` s.d. `FR-P3-14`)

* **`FR-P3-13` Quick-Fill Login & Simulator Hardware POS/RFID**
  * **Deskripsi:** Memudahkan reviewer portofolio mencoba seluruh fitur tanpa perangkat fisik USB scanner/RFID.
  * **Acceptance Criteria:**
    1. Seluruh fitur demo dikendalikan oleh `config('kopdes.demo_mode')` (`env('APP_DEMO_MODE', false)`). Ketika `false`, tidak ada satu pun tombol demo/simulator yang dirender di Vue dan endpoint sampel demo mengembalikan HTTP `404`.
    2. Ketika `true`:
       - `/login` menampilkan tombol Quick-Fill untuk 4 jenjang Admin (`admin_nasional`, `admin_provinsi`, `admin_kabupaten`, `admin_cabang`).
       - `/pos/login` menampilkan tombol Quick-Fill Kasir Cabang A dan Kasir Cabang B (mengisi `card_uid` + PIN 6 digit).
       - `/member/login` menampilkan tombol Quick-Fill Member Cabang A dan Member Cabang B (mengisi `member_code` + PIN 6 digit).
       - `Pos/Index.vue` menampilkan popover **"Simulator Hardware"** berisi tombol simulasi scan 6 produk sampel (termasuk produk dengan `member_price` dan tanpa `member_price`) serta simulasi tap kartu Member Asal dan Member Lintas Cabang.
       - `RfidCaptureModal.vue` menampilkan tombol **"Simulasi Tap Kartu Baru"** yang mengisi UID unik terformat otomatis.

* **`FR-P3-14` Rich `DemoDataSeeder` (Data 3 Bulan Realistis)**
  * **Deskripsi:** Seeder khusus portofolio yang menyajikan ekosistem KOPDES aktif sejak menit pertama dibuka.
  * **Acceptance Criteria:**
    1. Menjalankan `php artisan db:seed --class=DemoDataSeeder` bersifat *idempotent* dan selesai dalam waktu kurang dari 15 detik.
    2. Menghasilkan minimal: 2 Provinsi, 3 Kabupaten, 4 Cabang KOPDES aktif, 15+ produk sembako/UMKM (sebagian dengan `member_price`), stok awal & mutasi stok tiap cabang, 10+ member (sebagian punya kartu RFID + PIN Portal, sebagian belum *binding* RFID), 60+ transaksi historis tersebar 3 bulan terakhir (kombinasi `cash` & `midtrans`, member asal & lintas cabang, non-member), beberapa shift tertutup (ada yang seimbang, surplus, dan minus), beberapa transaksi retur (`restockable` & `damaged`), biaya operasional lintas kategori, serta beberapa sampel `activity_logs`.

---

## 5. Desain Skema Database Fase 3 (Tabel Baru & Alter Existing)

### 5.1 Diagram Relasi Tambahan Fase 3 (ERD ASCII)

```text
+------------------+              +-------------------+
|   app_settings   |              |   activity_logs   |
| (key PK, value)  |              +-------------------+
+------------------+                │ N    │ N    │ N
  (updated_by -> users)             │      │      └── (province_id/regency_id/branch_id)
                                    │      └───────── (subject_type, subject_id)
                                    └──────────────── (actor_id -> users)

+---------------------------------------------------------+
|                        members                          |
| (ALTER: card_uid NULLABLE UNIQUE, +pin_hash, +rem_tok)  |
+---------------------------------------------------------+
     │ 1 (Auth Guard: 'member')
     ├──► N transactions (member_id)
     └──► N transaction_returns (member_id)
```

### 5.2 Tabel Baru (2 Tabel)

#### 1. `app_settings`
| Kolom | Tipe Data | Constraint & Index | Keterangan |
| :--- | :--- | :--- | :--- |
| `key` | `VARCHAR(100)` | `PRIMARY KEY` | Kunci pengaturan unik (misal: `return_max_days`, `institution_name`) |
| `value` | `TEXT` | `NULL` | Nilai tersimpan dalam format string/JSON |
| `type` | `ENUM('string','integer','decimal','boolean','json')` | `NOT NULL`, `DEFAULT 'string'` | Tipe casting saat dibaca `AppSettingService` |
| `group` | `VARCHAR(50)` | `NOT NULL`, `DEFAULT 'general'`, `INDEX` | Kelompok tampilan UI (`general`, `receipt`, `return`, `shu`) |
| `updated_by_user_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> users(id) ON DELETE SET NULL` | `admin_nasional` terakhir yang mengubah |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |

#### 2. `activity_logs`
| Kolom | Tipe Data | Constraint & Index | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID log audit (*append-only*) |
| `actor_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> users(id) ON DELETE SET NULL`, `INDEX` | User admin/kasir pelaku aksi |
| `actor_role` | `VARCHAR(50)` | `NULL`, `INDEX` | Snapshot role aktor saat aksi terjadi |
| `action` | `VARCHAR(100)` | `NOT NULL`, `INDEX` | Kode aksi terstandar (contoh: `product.price_updated`, `shift.force_closed`) |
| `description` | `VARCHAR(255)` | `NOT NULL` | Ringkasan deskriptif terbaca manusia |
| `subject_type` | `VARCHAR(120)` | `NULL` | Nama kelas model target (contoh: `App\Models\Product`) |
| `subject_id` | `BIGINT UNSIGNED` | `NULL` | ID record target |
| `province_id` | `CHAR(2)` | `NULL`, `FK -> provinces(id) ON DELETE RESTRICT`, `INDEX` | Scope provinsi untuk `HasRegionScope` (NULL untuk aksi global) |
| `regency_id` | `CHAR(4)` | `NULL`, `FK -> regencies(id) ON DELETE RESTRICT`, `INDEX` | Scope kabupaten untuk `HasRegionScope` |
| `branch_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> branches(id) ON DELETE RESTRICT`, `INDEX` | Scope cabang untuk `HasRegionScope` |
| `old_values` | `JSON` | `NULL` | Nilai field sebelum perubahan (sudah tersensor dari data sensitif) |
| `new_values` | `JSON` | `NULL` | Nilai field sesudah perubahan (sudah tersensor dari data sensitif) |
| `ip_address` | `VARCHAR(45)` | `NULL` | Alamat IP pelaku |
| `user_agent` | `VARCHAR(255)` | `NULL` | User agent browser/CLI |
| `created_at` | `TIMESTAMP` | `NOT NULL`, `INDEX` | Waktu aksi dicatat (tanpa `updated_at`, immutable) |
| **Composite Index** | `INDEX (subject_type, subject_id)` | `INDEX (branch_id, action, created_at)` | Optimasi pencarian jejak audit per entitas dan per cabang |

### 5.3 Perubahan Tabel Existing (`members`)

| Kolom | Perubahan Tipe & Constraint | Keterangan |
| :--- | :--- | :--- |
| `card_uid` | Ubah dari `VARCHAR(64) NOT NULL UNIQUE` menjadi `VARCHAR(64) NULL UNIQUE` | Memungkinkan import massal member sebelum kartu RFID fisik dibagikan/di-*binding*. |
| `pin_hash` | Tambah `VARCHAR(255) NULL AFTER status` | Hash bcrypt/argon2id dari PIN 6 digit untuk login Portal Member. Disembunyikan pada `#[Hidden]`. |
| `remember_token` | Tambah `VARCHAR(100) NULL AFTER pin_hash` | Diperlukan oleh `Illuminate\Foundation\Auth\User` (`Authenticatable`) untuk guard `member`. |

---

## 6. Aturan Bisnis Teknis & Mitigasi Edge Cases Kritis

### 6.1 Isolasi Guard `member` vs `web` & Pencegahan Kebocoran Data
* Pada `config/auth.php`, daftarkan guard `'member' => ['driver' => 'session', 'provider' => 'members']` dan provider `'members' => ['driver' => 'eloquent', 'model' => App\Models\Member::class]`.
* Model `Member` meng-extend `Illuminate\Foundation\Auth\User` (atau menggunakan trait `Illuminate\Auth\Authenticatable`) dengan `protected $hidden = ['pin_hash', 'remember_token'];` dan method `getAuthPassword()` mengembalikan `$this->pin_hash` (walau verifikasi eksplisit dilakukan via `Hash::check()` di `MemberAuthController`).
* Pada `HandleInertiaRequests::share()`, pisahkan kunci shared props secara tegas:
  * `auth.user` hanya diisi dari `auth('web')->user()`.
  * `auth.member` hanya diisi dari `auth('member')->user()` (hanya mengembalikan `id`, `member_code`, `name`, `phone`, `home_branch_id`, `home_branch_name`, `has_rfid_card`, `status` — tanpa `pin_hash` atau `nik`).

### 6.2 Rumus Estimasi SHU pada Portal Member
Agar konsisten 100% dengan `FinancialReportService` yang sudah lulus uji pada Milestone 9:
1. Hitung `net_profit` KOPDES Asal (`member.home_branch_id`) pada periode terpilih menggunakan `FinancialReportService`.
2. Jika `net_profit` $\le 0$, maka `estimated_shu = 0`.
3. Jika `net_profit` $> 0$:
   $$P_{\text{jasa\_usaha}} = \text{net\_profit} \times \frac{\text{shu\_allocation\_member\_usaha}}{100}$$
   $$\text{Estimasi SHU Member} = P_{\text{jasa\_usaha}} \times \frac{\text{Belanja Bersih Member}}{\text{Total Belanja Bersih Seluruh Member Cabang Asal}}$$
   *(Aman terhadap pembagian nol: jika penyebut $= 0$, hasil $= 0$).*
4. **Total Hemat Member Bersih:** Dihitung dari `SUM(transactions.member_discount_total)` milik member pada periode terpilih dikurangi nilai diskon proporsional item yang telah diretur:
   $$\text{Diskon Retur Item} = (\text{regular\_price\_snapshot} - \text{unit\_price\_snapshot}) \times \text{qty\_returned}$$

### 6.3 Integritas & Performa Import Massal
* **Aturan All-or-Nothing:** `BulkImportService` memvalidasi seluruh baris di memori terlebih dahulu (maksimal 2.000 baris per file upload untuk menjaga waktu respons HTTP web tetap cepat) sebelum membuka `DB::transaction()`. Validasi mengecek duplikasi lintas-file (misalnya baris 5 dan baris 12 di Excel memakai `barcode` atau `card_uid` yang sama) sekaligus duplikasi terhadap database.
* **Normalisasi Konsisten:** Seluruh `card_uid` yang masuk lewat import member melewati `CardUidNormalizer::normalize()` sebelum dicek keunikannya terhadap tabel `users` dan `members`. Jika kolom `card_uid` kosong/whitespace, simpan sebagai `NULL` (bukan string kosong `""`, karena string kosong akan melanggar constraint `UNIQUE` pada baris kedua).
* **Keamanan File Upload:** Validasi MIME ketat (`xlsx`, `csv`), batas ukuran file maksimal `5MB`, dan untuk upload logo koperasi di `app_settings` hanya izinkan `png`, `jpg`, `jpeg`, `webp` maksimal `2MB` menggunakan `Storage::disk('public')->putFile('logos', ...)` (tanpa mempertahankan nama file mentah dari user).

### 6.4 Immutability & Penyensoran `activity_logs`
* `ActivityLogService::record()` secara otomatis menyaring kunci sensitif (`password`, `pin`, `pin_hash`, `remember_token`, `server_key`) dari `old_values` dan `new_values` sebelum disimpan.
* Model `ActivityLog` menetapkan `public const UPDATED_AT = null;` dan tidak menyediakan route `PUT`, `PATCH`, atau `DELETE`.
* Scoping `HasRegionScope` pada `ActivityLog`: untuk `admin_provinsi`, `admin_kabupaten`, dan `admin_cabang`, query otomatis memfilter hanya log yang memiliki kolom wilayah sesuai scope-nya (log global dengan `province_id = NULL` otomatis tersembunyi dari admin di bawah `admin_nasional`).

### 6.5 Keamanan Mode Demo (`APP_DEMO_MODE`)
* Tambahkan `'demo_mode' => (bool) env('APP_DEMO_MODE', false)` di `config/kopdes.php`.
* Middleware `EnsureDemoModeEnabled` melindungi endpoint `GET /demo/pos-samples` (yang memasok daftar produk & kartu member sampel untuk Simulator Hardware POS) dan mengembalikan HTTP `404 Not Found` apabila `config('kopdes.demo_mode') === false`.
* Tombol Quick-Fill di Vue hanya membaca flag `app.demo_mode` dari Inertia shared props dan **tidak** melakukan bypass autentikasi di backend—tombol tersebut hanya mengisi form dengan akun demo lalu tetap menempuh jalur validasi login & `RateLimiter` yang normal.

---

## 7. Rencana Implementasi Bertahap (Milestones Fase 3)

Pengembangan Fase 3 dipecah menjadi 4 milestone berurutan (melanjutkan Milestone 1–9 dari MVP & Fase 2):

### Milestone 10: Pengaturan Sistem Dinamis (`app_settings`) & Audit Trail Admin (`activity_logs`)
* **Tujuan:** Memusatkan konfigurasi koperasi di database ber-cache dan mencatat seluruh aksi administratif sensitif secara otomatis.
* **Deliverables Utama:**
  1. Migrasi & Model `AppSetting` dan `ActivityLog` (`HasRegionScope`, immutable).
  2. `AppSettingService` (cached `get/all/updateMany`, upload logo, validasi total alokasi SHU $= 100\%$) dan `ActivityLogService` (pencatatan + redaksi field sensitif).
  3. Integrasi `AppSettingService` ke `TransactionReturnService` (`return_max_days`), `FinancialReportService` (alokasi SHU), `HandleInertiaRequests` (nama/logo/footer/ukuran kertas default), `ReceiptThermal.vue`, serta Ekspor PDF/Excel.
  4. Pemasangan pencatatan `ActivityLogService` pada 8 peristiwa sensitif di service/controller terkait (`Product`, `Branch`, `User`, `Member`, `CashierShiftService::forceClose`, `TransactionReturnService`, `AppSettingService`).
  5. Halaman Vue `Settings/Index.vue` (khusus `admin_nasional`) dan `ActivityLogs/Index.vue` (read-only dengan filter & scoping wilayah).
* **Definition of Done (Pengujian):**
  - Feature Test membuktikan: (a) hanya `admin_nasional` dapat mengubah `app_settings` (role lain HTTP `403`); (b) total persentase SHU $\ne 100\%$ ditolak HTTP `422`; (c) perubahan `return_max_days` langsung mengubah batas validasi retur; (d) 8 aksi sensitif otomatis menulis `activity_logs` tanpa membocorkan `password`/`pin_hash`; (e) `admin_cabang` hanya melihat log cabangnya sendiri (anti-IDOR).

### Milestone 11: Import Wilayah Resmi, Onboarding Massal Excel/CSV & Cetak Label Barcode/Kartu Member
* **Tujuan:** Mempercepat *onboarding* cabang baru dan menyediakan cetak stiker barcode produk serta kartu member fisik.
* **Deliverables Utama:**
  1. Migrasi alter `members` (`card_uid` menjadi `nullable` + `UNIQUE`, tambah `pin_hash` & `remember_token`) beserta penyesuaian validasi `StoreMemberRequest`/`UpdateMemberRequest` & `Members/Index.vue` (badge "Belum Binding RFID" + aksi pasang kartu).
  2. Console Command `kopdes:import-regions {file} --type=...` (*streaming* CSV + *chunked upsert*).
  3. `BulkImportService` & `BulkImportController` (download template `.xlsx` + upload & validasi *all-or-nothing* untuk Produk, Stok Awal Cabang, dan Member + tulis `activity_logs`) beserta modal/halaman import di Vue (`Products`, `Inventory`, `Members`).
  4. Instalasi NPM `jsbarcode` & `qrcode`, halaman cetak stiker barcode `Products/BarcodeLabels.vue` (mode A4 Grid & Thermal Label 50x30mm), dan halaman cetak kartu member fisik `Members/PrintCards.vue` (satuan & batch dengan QR `member_code`).
* **Definition of Done (Pengujian):**
  - Feature Test membuktikan: (a) command `kopdes:import-regions` mengimpor CSV wilayah secara idempotent; (b) import produk/stok/member menolak keseluruhan file (HTTP `422` + nomor baris) jika ada 1 baris invalid atau duplikat internal; (c) import member dengan `card_uid` kosong menyimpan `NULL` untuk banyak baris tanpa konflik `UNIQUE`; (d) `admin_cabang` tidak bisa mengimpor stok/member ke cabang lain; (e) halaman cetak barcode & kartu member dapat diakses sesuai RBAC dan menolak lintas cabang (HTTP `403`).

### Milestone 12: Portal Mandiri Member (Auth Guard `member`, Kartu Digital, SHU & Riwayat)
* **Tujuan:** Menyediakan portal mandiri mobile-first bagi anggota koperasi dengan isolasi sesi penuh.
* **Deliverables Utama:**
  1. Konfigurasi guard `member` & provider `members` di `config/auth.php`, pembaruan model `Member` (`Authenticatable`), middleware `EnsureMemberAuthenticated`, serta pemisahan `auth.member` pada `HandleInertiaRequests`.
  2. Fitur Set/Reset PIN 6 digit member oleh `admin_cabang`/`admin_nasional` pada `MemberController` & `Members/Index.vue` (tercatat di `activity_logs`).
  3. `MemberAuthController` (`/member/login`, `/member/logout`, `RateLimiter` 5x gagal) dan `MemberPortalController` (`dashboard`, `transactions`, `showTransaction`, `updatePin`) beserta `MemberPortalService` (belanja bersih, asal vs lintas cabang, total hemat bersih, estimasi SHU berjalan).
  4. Layout mobile-first `Layouts/MemberLayout.vue` dan halaman Vue: `Member/Login.vue`, `Member/Dashboard.vue` (termasuk Kartu Member Digital + SVG QR), `Member/Transactions.vue`, `Member/TransactionShow.vue`, dan `Member/Security.vue`.
* **Definition of Done (Pengujian):**
  - Feature Test membuktikan: (a) member login via `member_code` atau `phone` + PIN 6 digit; (b) 5x salah PIN mengembalikan HTTP `429`; (c) member tidak aktif atau belum punya PIN ditolak login; (d) sesi `member` terisolasi dari `/dashboard` dan `/pos` (tidak bisa mengakses route admin/kasir); (e) member ditolak HTTP `403` saat mengakses struk transaksi milik member lain; (f) perhitungan Belanja Bersih (Asal vs Lintas Cabang), Total Hemat Bersih, dan Estimasi SHU Berjalan akurat setelah memperhitungkan retur dan biaya operasional; (g) ganti PIN mandiri bekerja dan memverifikasi PIN lama.

### Milestone 13: Mode Demo Portofolio, Simulator Hardware, Rich `DemoDataSeeder` & Final Regression
* **Tujuan:** Menyiapkan pengalaman demonstrasi terbaik bagi reviewer portofolio tanpa kompromi keamanan produksi.
* **Deliverables Utama:**
  1. Konfigurasi `demo_mode` di `config/kopdes.php`, middleware `EnsureDemoModeEnabled`, dan `DemoSimulatorController` (`GET /demo/pos-samples`).
  2. Panel Quick-Fill Login pada `Auth/Login.vue`, `Pos/Login.vue`, dan `Member/Login.vue` (khusus `demo_mode === true`).
  3. Komponen `Pos/Partials/HardwareSimulatorPopover.vue` pada `Pos/Index.vue` (simulasi scan barcode produk & tap kartu member asal/lintas cabang) serta tombol simulasi tap kartu baru di `RfidCaptureModal.vue`.
  4. `Database\Seeders\DemoDataSeeder` yang menghasilkan data 3 bulan realistis (multi-cabang, transaksi tunai/Midtrans, member lintas cabang, shift tertutup, retur restockable/damaged, biaya operasional, activity logs).
  5. Pembaruan `README.md` proyek (dokumentasi arsitektur, akun demo, cara menjalankan mode demo & test suite).
* **Definition of Done (Pengujian):**
  - Feature Test membuktikan: (a) saat `demo_mode = false`, endpoint `/demo/pos-samples` mengembalikan HTTP `404` dan props `app.demo_mode` bernilai `false`; (b) saat `demo_mode = true`, endpoint mengembalikan daftar sampel valid sesuai cabang kasir aktif; (c) `DemoDataSeeder` berjalan sukses tanpa error dan idempotent; (d) seluruh test suite (`Milestone 1` s.d. `Milestone 13` + `OperationalExpenseAndExportTest`) lulus 100% dan `npm run build` bebas error.