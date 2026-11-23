# PRD: Sistem Kasir & Manajemen KOPDES (Koperasi Desa Merah Putih)

**Versi Dokumen:** 1.0 (MVP Baseline)

**Status:** Terkunci (Single Source of Truth untuk Implementasi)

**Target Skala Awal:** 1–10 Cabang KOPDES (Siap Skala Nasional)

**Arsitektur Utama:** Monolith Modern Berbasis Laravel + Inertia.js + Vue.js 3 + Tailwind CSS

---

## 1. Ringkasan Eksekutif, Tech Stack & Batasan MVP

### 1.1 Ringkasan Eksekutif

Sistem Kasir & Manajemen **KOPDES (Koperasi Desa Merah Putih)** adalah aplikasi web terpusat untuk mengelola operasional penjualan ritel (Point of Sale), inventori barang per cabang, keanggotaan lintas desa, dan pelaporan keuangan koperasi secara berjenjang dari tingkat **Desa (Cabang KOPDES)** hingga **Nasional**.

Sistem dirancang agar seluruh entitas KOPDES berbagi satu katalog produk terpusat dan satu basis data keanggotaan nasional yang portabel (kartu anggota KOPDES Desa A dapat digunakan berbelanja di KOPDES Desa B), sambil tetap memisahkan stok barang, kasir, serta hak akses laporan secara ketat berdasarkan hierarki administratif wilayah Indonesia.

### 1.2 Tech Stack Terkunci

| Lapisan | Teknologi & Versi | Spesifikasi Teknis & Alasan Pemilihan |
| --- | --- | --- |
| **Backend Framework** | Laravel 11.x / 12.x (Versi stabil terbaru), PHP 8.2+ | Menyediakan Eloquent ORM, Policies, RateLimiter, Scheduler, dan Queue bawaan yang matang untuk aplikasi transaksional. |
| **Database** | MySQL 8.0+ atau MariaDB 10.6+ | Wajib mendukung InnoDB, transaksi ACID, row-level pessimistic locking (`SELECT ... FOR UPDATE`), tipe kolom `JSON`, dan constraint `UNIQUE` komposit. |
| **Frontend Stack** | Inertia.js + Vue.js 3 (Composition API dengan `<script setup>`) + Tailwind CSS | Pengalaman SPA cepat untuk layar kasir tanpa membangun REST API terpisah; state keranjang dikelola reaktif di sisi klien Vue. |
| **Payment Gateway** | Midtrans Snap (Sandbox) + HTTP Webhook Notification | Mendukung QRIS, e-wallet (GoPay, ShopeePay), dan Virtual Account dalam satu antarmuka pop-up (`window.snap.pay`), dengan verifikasi signature SHA-512. |
| **Perangkat Keras POS** | USB Barcode Scanner & USB RFID/NFC Reader (HID Keyboard Wedge) | Beroperasi sebagai input keyboard standar tanpa driver khusus; dipisahkan melalui manajemen fokus dan modal khusus di Vue. |
| **Output Struk** | Thermal Print Berbasis Web (`@media print`) | Template khusus HTML/CSS berukuran kertas 58mm dan 80mm yang dipicu melalui `window.print()`. |
| **Background Processing** | Laravel Queue & Scheduler (Driver `database`) | Menjalankan pembersihan otomatis transaksi Midtrans `pending` yang kedaluwarsa serta melepas `reserved_qty` tanpa membutuhkan Redis di fase MVP. |
| **Autentikasi & Keamanan** | Laravel Session Auth + `Hash::make()` (bcrypt/argon2id) + `RateLimiter` | Admin login dengan Email + Password; Kasir login/unlock POS dengan Tap Kartu RFID + PIN 6 digit. |

### 1.3 Batasan Ruang Lingkup MVP (In-Scope vs. Out-of-Scope)

| Kategori | In-Scope (Wajib Dibangun di MVP) | Out-of-Scope (Ditunda ke Fase 2) |
| --- | --- | --- |
| **Organisasi & Wilayah** | Master wilayah baku (Provinsi, Kabupaten/Kota, Kecamatan, Desa); registrasi cabang KOPDES dengan aturan mutlak 1 Desa = 1 KOPDES; RBAC 5 role berjenjang. | Pengelolaan unit usaha non-ritel (simpan pinjam, logistik gudang pusat, distribusi grosir B2B). |
| **Kasir & Anggota** | Login kasir via tap kartu RFID + PIN 6 digit; quick lock layar kasir; tap kartu member tanpa PIN di POS untuk portabilitas lintas cabang; transaksi pembeli non-member (`member_id = null`). | Portal login mandiri bagi member (cek poin/riwayat belanja via HP); perhitungan dan distribusi otomatis SHU (Sisa Hasil Usaha). |
| **Produk & Stok** | Katalog produk global dengan harga jual seragam; stok independen per cabang (`quantity` dan `reserved_qty`); fitur restock dan penyesuaian stok (`stock adjustment`); audit log stok `append-only`. | Harga jual berbeda per cabang; diskon bertingkat atau harga khusus member vs non-member; transfer/mutasi stok resmi antar cabang; fitur purchase order ke supplier. |
| **Transaksi POS** | Scan barcode produk; manajemen keranjang di Vue; pembayaran Tunai (`cash`) dengan hitung kembalian; pembayaran Midtrans Snap (`midtrans`) dengan webhook dan tombol cek status manual; cetak struk thermal 58mm/80mm. | Pembayaran kasbon/potong saldo anggota; split payment (gabungan tunai + QRIS dalam satu struk); mode offline POS (IndexedDB sync); retur/refund parsial setelah transaksi selesai. |
| **Keuangan & Laporan** | Dashboard & laporan omzet penjualan (`paid`), rekap metode bayar (`cash` vs `midtrans`), laba kotor berbasis snapshot HPP (`total_amount - total_cogs`), rekap partisipasi transaksi per member; filter drill-down sesuai scope wilayah. | Sistem buka/tutup shift kasir (rekonsiliasi laci uang kasir); pencatatan kas operasional (biaya listrik, gaji, sewa); laporan neraca dan arus kas akuntansi jurnal ganda. |

---

## 2. User Roles, Matriks Hak Akses (RBAC), & Aturan Scoping Wilayah

### 2.1 Definisi Role & Mekanisme Autentikasi

| Kode Role (`users.role`) | Metode Autentikasi | Kolom Scope Wajib Terisi | Kolom Scope Wajib `NULL` | Tujuan Operasional |
| --- | --- | --- | --- | --- |
| `admin_nasional` | Email + Password | Tidak ada (akses global) | `province_id`, `regency_id`, `branch_id`, `card_uid`, `pin_hash` | Mengelola master produk global, cabang seluruh Indonesia, akun admin di bawahnya, dan laporan nasional. |
| `admin_provinsi` | Email + Password | `province_id` | `regency_id`, `branch_id`, `card_uid`, `pin_hash` | Memantau cabang, mengelola akun `admin_kabupaten`, dan melihat agregasi laporan pada provinsinya. |
| `admin_kabupaten` | Email + Password | `province_id`, `regency_id` | `branch_id`, `card_uid`, `pin_hash` | Mendaftarkan cabang KOPDES desa di kabupatennya, mengelola `admin_cabang`, dan memantau laporan kabupaten. |
| `admin_cabang` | Email + Password | `province_id`, `regency_id`, `branch_id` | `card_uid`, `pin_hash` | Mengelola kasir cabang, registrasi member lokal, restock/adjustment stok cabang, dan laporan cabangnya. |
| `kasir` | Tap Kartu RFID (`card_uid`) + PIN 6 Digit | `province_id`, `regency_id`, `branch_id`, `card_uid`, `pin_hash` | `email` (opsional/nullable), `password` (nullable) | Mengoperasikan layar POS, melayani transaksi tunai dan Midtrans, serta mencetak struk pada cabangnya. |

### 2.2 Matriks Hak Akses (RBAC)

Keterangan: **C** = Create, **R** = Read, **U** = Update, **D** = Soft Delete/Deactivate, **-** = Dilarang (HTTP 403).

| Modul / Sumber Daya | `admin_nasional` | `admin_provinsi` | `admin_kabupaten` | `admin_cabang` | `kasir` |
| --- | --- | --- | --- | --- | --- |
| **Referensi Wilayah (Read-Only)** | R | R (Provinsi) | R (Kabupaten) | R (Desa/Cabang) | - |
| **Manajemen Cabang KOPDES** | CRUD (Semua) | R (Provinsi) | CRU (Kabupaten) | R (Cabang sendiri) | - |
| **Manajemen User Admin** | CRUD (Semua role admin) | CRUD (`admin_kabupaten` di provinsinya) | CRUD (`admin_cabang` di kabupatennya) | - | - |
| **Manajemen User Kasir & Binding RFID Kasir** | CRUD (Semua) | R (Provinsi) | R (Kabupaten) | CRUD (Cabang sendiri) | - |
| **Manajemen Member & Binding RFID Member** | CRUD (Semua) | R (Provinsi) | R (Kabupaten) | CRUD (Member dengan `home_branch_id` cabangnya) | R (Lookup via Tap Kartu di POS lintas cabang) |
| **Master Kategori & Produk Global** | CRUD | R | R | R | R (Lookup Barcode & Stok Cabang) |
| **Stok Cabang (Restock & Adjustment)** | R (Semua) | R (Provinsi) | R (Kabupaten) | CRU (Cabang sendiri) | R (Stok tersedia cabang sendiri) |
| **Log Pergerakan Stok (`stock_movements`)** | R (Semua) | R (Provinsi) | R (Kabupaten) | R (Cabang sendiri) | - |
| **Layar POS & Pembuatan Transaksi** | - | - | - | - | C & R (Transaksi cabangnya hari ini) |
| **Riwayat Transaksi & Log Webhook Midtrans** | R (Semua) | R (Provinsi) | R (Kabupaten) | R (Cabang sendiri) | R (Riwayat singkat cabangnya untuk cetak ulang / cek status) |
| **Laporan Keuangan & Rekap Member** | R (Drill-down Nasional → Cabang) | R (Drill-down Provinsi → Cabang) | R (Drill-down Kabupaten → Cabang) | R (Cabang sendiri) | - |

### 2.3 Aturan Scoping Wilayah (Anti-IDOR)

1. **Validasi Konsistensi Saat Simpan:** Saat membuat atau memperbarui `branches`, sistem secara otomatis mengisi `district_id`, `regency_id`, dan `province_id` dari tabel `villages` → `districts` → `regencies` → `provinces` di sisi server (tidak pernah mempercayai input wilayah terpisah dari frontend).
2. **Global / Local Query Scope (`HasRegionScope`):** Seluruh query pada model `Branch`, `BranchStock`, `StockMovement`, `Transaction`, dan `User` (kecuali lookup `Member` saat transaksi POS) wajib melewati scope `forUser(User $actor)`:
* `admin_nasional`: tanpa pembatasan wilayah.
* `admin_provinsi`: memfilter `province_id = $actor->province_id`.
* `admin_kabupaten`: memfilter `regency_id = $actor->regency_id`.
* `admin_cabang` dan `kasir`: memfilter `branch_id = $actor->branch_id`.


3. **Pengecualian Terkontrol untuk Portabilitas Member:** Endpoint lookup kartu member di POS (`GET /pos/members/lookup?card_uid=...`) secara eksplisit diizinkan membaca record `members` dari `home_branch_id` mana pun selama `status = 'active'`, dan hanya mengembalikan payload minimal (`id`, `member_code`, `name`, `home_branch_id`, `home_branch_name`) tanpa mengekspos NIK atau data sensitif lainnya.

---

## 3. Alur Pengguna Utama (User Flows)

### 3.1 Alur Registrasi Cabang & Onboarding Kasir/Member (Binding Kartu RFID/NFC)

```text
[Admin Nasional / Kabupaten]
   │ 1. Pilih Provinsi → Kabupaten → Kecamatan → Desa
   ▼
[Validasi Server: Cek apakah village_id sudah punya KOPDES]
   ├── Sudah ada → Tolak (422: "Desa ini sudah memiliki cabang KOPDES terdaftar")
   └── Belum ada → Simpan Branch + Generate kode cabang (KDP-3502-XXXX)
   │
   ▼
[Admin Kabupaten / Nasional buat akun Admin Cabang]
   │
   ▼
[Admin Cabang Login Dashboard (Email + Password)]
   ├── A. Onboarding Kasir
   │     1. Buka form Tambah Kasir → isi Nama & PIN 6 digit (konfirmasi PIN)
   │     2. Klik tombol "Scan Kartu Kasir" → Modal fokus input RFID muncul
   │     3. Tempel kartu kasir ke USB RFID Reader → UID terbaca & dinormalisasi
   │     4. Submit → Server cek keunikan card_uid di tabel `users` dan `members`
   │     5. Simpan user (role = kasir, branch_id = cabang admin, pin_hash = bcrypt)
   │
   └── B. Onboarding Member
         1. Buka form Tambah Member → isi Nama, No HP, NIK (opsional)
         2. Klik tombol "Scan Kartu Member" → Modal fokus input RFID muncul
         3. Tempel kartu member ke USB RFID Reader → UID terbaca & dinormalisasi
         4. Submit → Server cek keunikan card_uid di tabel `members` dan `users`
         5. Simpan member (`home_branch_id` = cabang admin, `status` = active)

```

### 3.2 Alur Transaksi POS Tunai (`cash`) & Cetak Struk Thermal

1. **Autentikasi Kasir:** Kasir membuka `/pos/login`, menempelkan kartu kasir ke USB RFID Reader, lalu mengetik PIN 6 digit. Sistem memverifikasi `card_uid`, `is_active = true`, `role = 'kasir'`, dan `Hash::check($pin, $user->pin_hash)`. Jika valid, sesi dimulai dan mengarah ke `/pos`.
2. **Inisialisasi Layar POS:** Komponen Vue `Pos/Index.vue` memuat identitas cabang, identitas kasir, dan mengaktifkan *auto-focus* pada kolom input barcode. Keranjang (`cartItems`) disimpan di *client-side reactive state* Vue beserta satu `idempotency_key` (UUID v4) yang di-generate saat keranjang pertama kali terisi.
3. **Pemindaian Produk:** Kasir memindai barcode barang dengan USB Barcode Scanner (yang mengirim teks barcode diikuti tombol `Enter`). Frontend mengirim request `GET /pos/products/barcode/{barcode}` (atau lookup dari daftar produk cabang yang di-cache di memori sesi bila katalog kecil, tetap divalidasi stoknya di server saat checkout).
* Jika produk ditemukan dan `available_stock` minimal 1: tambahkan ke `cartItems` (atau tambah `qty` sebesar 1 jika produk sudah ada di keranjang selama tidak melebihi `available_stock`).
* Jika tidak ditemukan atau stok habis: tampilkan toast error suara/visual tanpa memindahkan fokus dari input barcode.


4. **Identifikasi Member (Opsional):**
* Pembeli **Non-Member**: Kasir melewati langkah ini (`selectedMember = null`).
* Pembeli **Member**: Kasir menekan shortcut `F2` (atau tombol "Tap Kartu Member"). Modal khusus terbuka dengan input terisolasi. Member menempelkan kartu ke USB RFID Reader. Frontend mengirim lookup `card_uid` lintas cabang; jika valid dan aktif, nama member dan KOPDES asalnya tampil di panel transaksi, lalu modal menutup dan fokus kembali ke input barcode.


5. **Checkout Tunai:** Kasir menekan `F9` (atau tombol "Bayar") → memilih metode **Tunai** → memasukkan nominal uang diterima (`cash_received`) atau klik tombol pecahan cepat (Uang Pas, Rp50.000, Rp100.000). Vue menampilkan kalkulasi kembalian secara *real-time* (`change_amount = cash_received - total_amount`). Tombol "Selesaikan Transaksi" aktif hanya jika `cash_received` minimal sama dengan `total_amount`.
6. **Eksekusi Backend Atomik:** Frontend menonaktifkan tombol bayar dan mengirim `POST /pos/checkout` dengan payload `{ idempotency_key, payment_method: 'cash', member_id, cash_received, items: [{ product_id, qty }] }`. Di dalam `DB::transaction()`:
* Cek `idempotency_key` (jika sudah ada, kembalikan transaksi lama).
* Kunci baris `branch_stocks` dengan `lockForUpdate()` terurut berdasarkan `product_id` (mencegah deadlock).
* Validasi ketersediaan (`quantity - reserved_qty` minimal sama dengan `qty`).
* Hitung ulang seluruh harga dan HPP dari tabel `products` (abaikan harga dari frontend).
* Simpan `transactions` (`payment_status = 'paid'`, `paid_at = now()`) dan `transaction_items` (dengan kolom snapshot).
* Kurangi `branch_stocks.quantity` dan catat `stock_movements` (`type = 'sale'`).


7. **Cetak Struk & Reset Keranjang:** Backend mengembalikan objek transaksi lengkap. Vue merender komponen `ReceiptThermal.vue` pada area cetak tersembunyi, memicu `window.print()`, mengosongkan `cartItems`, mereset `selectedMember = null`, menghasilkan `idempotency_key` baru untuk transaksi berikutnya, dan mengembalikan fokus ke input barcode.

### 3.3 Alur Transaksi POS Midtrans Snap (`midtrans`)

```text
[Kasir klik "Bayar via Midtrans" di Vue POS]
   │ 1. Disable tombol bayar, POST /pos/checkout (idempotency_key, payment_method='midtrans', items)
   ▼
[Laravel CheckoutService dalam DB::transaction()]
   │ 2. lockForUpdate() pada branch_stocks (urut product_id ASC)
   │ 3. Validasi (quantity - reserved_qty) >= qty
   │ 4. Tambah branch_stocks.reserved_qty += qty  (quantity BELUM dikurangi)
   │ 5. Buat record transactions (payment_status='pending', expires_at=now()+15m) & transaction_items
   │ 6. Request Midtrans Snap API (expiry: 15 menit, order_id = invoice_number)
   │    ├── Gagal hubungi Midtrans → Rollback transaksi (reserved_qty kembali utuh) → Balas 502 ke Vue
   │    └── Sukses dapat snap_token → Simpan snap_token → Commit DB → Kembalikan { transaction_id, snap_token }
   ▼
[Vue POS buka Pop-up window.snap.pay(snap_token)]
   │ 7. Pembeli scan QRIS / bayar via e-wallet / VA
   ├── Jalur A (Utama): Midtrans kirim HTTP POST ke /api/webhooks/midtrans
   │     ├── Validasi SHA-512 signature_key (tolak 403 jika tidak cocok)
   │     ├── Simpan payload mentah ke tabel `payment_logs`
   │     ├── DB::transaction() + lockForUpdate() pada `transactions` & `branch_stocks`
   │     ├── Jika status sudah final (`paid`/`expired`/`cancelled`/`failed`) → Balas 200 OK (Idempotent)
   │     ├── Jika settlement/capture(accept):
   │     │     - transactions.payment_status = 'paid', paid_at = now()
   │     │     - branch_stocks.quantity -= qty; branch_stocks.reserved_qty -= qty
   │     │     - Insert stock_movements (type='sale')
   │     └── Jika expire/cancel/deny:
   │           - transactions.payment_status = 'expired' atau 'cancelled' atau 'failed'
   │           - branch_stocks.reserved_qty -= qty (lepas reservasi)
   │
   ├── Jalur B (Fallback Kasir): Pop-up onSuccess/onPending atau Webhook terlambat
   │     └── Kasir klik "Cek Status Pembayaran" (atau polling ringan tiap 3 detik saat modal menunggu terbuka)
   │         → Backend panggil Midtrans Get Transaction Status API
   │         → Jalankan service rekonsiliasi status yang SAMA persis dengan handler webhook
   │
   └── Jalur C (Safety Net Background): Laravel Scheduler tiap 5 menit
         └── Cari transaksi `pending` dengan `expires_at` terlewati → Cek status terakhir ke Midtrans API →
             Jika bukan `settlement`, ubah ke `expired` dan lepaskan `reserved_qty`

```

### 3.4 Alur Restock & Penyesuaian Stok (`stock_adjustment`)

1. **Akses Menu Inventori:** `admin_cabang` membuka halaman Inventori Cabang (`/inventory`) yang menampilkan daftar produk aktif beserta `quantity`, `reserved_qty`, `available_stock`, dan `min_stock`.
2. **Input Restock (Barang Masuk):**
* Admin memilih produk → klik "Tambah Stok (Restock)" → memasukkan `qty_add` (integer positif minimal 1) dan `notes` (misal: "Kiriman Supplier Bulog Faktur #102").
* Backend menjalankan `DB::transaction()` + `lockForUpdate()` pada `branch_stocks` (menggunakan `firstOrCreate` jika produk baru pertama kali masuk cabang), mencatat `qty_before`, menambahkan `quantity += qty_add`, mencatat `qty_after`, lalu menulis satu baris ke `stock_movements` (`type = 'restock'`, `qty_change = +qty_add`).


3. **Input Penyesuaian Stok (Stock Adjustment):**
* Admin memilih produk → klik "Sesuaikan Stok" → memilih arah penyesuaian atau mengisi **Stok Fisik Aktual** (`actual_quantity`, integer minimal 0) dan wajib mengisi `notes` alasan penyesuaian (misal: "Kemasan rusak 2 pcs saat stock opname").
* Backend mengunci baris `branch_stocks`, memvalidasi bahwa `actual_quantity` tidak boleh lebih kecil dari `reserved_qty` (agar tidak merusak transaksi Midtrans yang sedang berlangsung), menghitung `qty_change = actual_quantity - qty_before`, memperbarui `quantity = actual_quantity`, dan menulis satu baris ke `stock_movements` (`type = 'adjustment'`).



---

## 4. Spesifikasi Fungsional & Acceptance Criteria per Modul

### Modul 1: Wilayah, Cabang KOPDES & RBAC (`FR-01` s.d. `FR-04`)

* **`FR-01` Seeding & Referensi Wilayah Baku**
* **Deskripsi:** Sistem menyediakan data referensi Provinsi, Kabupaten/Kota, Kecamatan, dan Desa menggunakan kode wilayah baku 2/4/7/10 digit.
* **Acceptance Criteria:**
1. Dropdown berjenjang (dependent select) pada form cabang memuat Kabupaten sesuai Provinsi terpilih, Kecamatan sesuai Kabupaten terpilih, dan Desa sesuai Kecamatan terpilih melalui endpoint JSON terproteksi auth.
2. Data wilayah bersifat *read-only* pada aplikasi web MVP.




* **`FR-02` Manajemen Cabang KOPDES (Aturan 1 Desa = 1 KOPDES)**
* **Deskripsi:** `admin_nasional` dan `admin_kabupaten` dapat membuat, melihat, mengubah detail, dan menonaktifkan cabang KOPDES.
* **Acceptance Criteria:**
1. Percobaan membuat cabang pada `village_id` yang sudah digunakan cabang lain ditolak dengan pesan validasi eksplisit pada level FormRequest dan dijamin oleh constraint `UNIQUE` di database.
2. Kode cabang (`code`) unik dan otomatis dihasilkan dengan format `KOP-{REGENCY_ID}-{SEQUENCE_3_DIGIT}` atau diisi manual dengan validasi unik.
3. Menonaktifkan cabang (`is_active = false`) mencegah seluruh kasir cabang tersebut melakukan login atau checkout baru tanpa menghapus data historis.




* **`FR-03` Manajemen Pengguna Admin & Kasir**
* **Deskripsi:** Pengelolaan akun sesuai hierarki RBAC dan pendaftaran kartu RFID + PIN untuk kasir.
* **Acceptance Criteria:**
1. `admin_provinsi` hanya dapat membuat/mengedit `admin_kabupaten` di provinsinya; `admin_kabupaten` hanya dapat membuat/mengedit `admin_cabang` di kabupatennya; `admin_cabang` hanya dapat membuat/mengedit `kasir` di cabangnya.
2. PIN kasir wajib tepat 6 digit numerik (`regex:/^\d{6}$/`), disimpan ter-hash (`pin_hash`), dan tidak pernah dikirim kembali ke frontend.
3. `card_uid` kasir wajib unik secara global di seluruh tabel `users` dan tidak boleh bentrok dengan `card_uid` di tabel `members`.




* **`FR-04` Autentikasi Dual-Mode & Proteksi Kasir**
* **Deskripsi:** Login Email + Password untuk Admin (`/login`) dan Login Tap Kartu + PIN 6 Digit untuk Kasir (`/pos/login`).
* **Acceptance Criteria:**
1. Percobaan PIN salah sebanyak 5 kali berturut-turut untuk kombinasi `card_uid` + IP memblokir login selama 15 menit (HTTP 429 dengan sisa waktu tunggu dalam detik).
2. Layar POS menyediakan tombol **"Kunci Layar" (`F12`)** dan mekanisme *idle auto-lock* setelah 15 menit tanpa interaksi (mouse/keyboard/scanner). Saat terkunci, overlay modal menutupi layar POS dan meminta tap kartu + PIN kasir untuk membuka kembali tanpa menghapus keranjang aktif (jika kasir yang sama membuka kembali) atau mereset keranjang (jika kasir berbeda yang login).





### Modul 2: Keanggotaan Lintas Cabang (`FR-05` s.d. `FR-06`)

* **`FR-05` Registrasi & Manajemen Member**
* **Deskripsi:** `admin_cabang` mendaftarkan anggota koperasi lokal dengan mengikat kartu RFID/NFC.
* **Acceptance Criteria:**
1. `home_branch_id` otomatis diisi sesuai `branch_id` milik `admin_cabang` yang mendaftarkan (untuk `admin_nasional`, dapat memilih cabang asal secara eksplisit).
2. `member_code` unik dengan format `MBR-{BRANCH_CODE}-{SEQUENCE_5_DIGIT}`.
3. Penggantian kartu hilang didukung dengan fitur "Ganti Kartu RFID" yang menimpa `card_uid` setelah validasi keunikan.




* **`FR-06` Lookup Member Lintas KOPDES di POS**
* **Deskripsi:** Kasir di cabang mana pun dapat menautkan transaksi ke member terdaftar dari desa mana pun cukup dengan tap kartu (tanpa PIN).
* **Acceptance Criteria:**
1. Tap kartu member dengan `status = 'inactive'` ditolak dengan pesan "Kartu member tidak aktif".
2. Saat member berhasil tertaut, UI POS menampilkan Nama Member, Kode Member, dan Nama KOPDES Asal (contoh: *Budi Santoso — KOPDES Desa Ngunut*).
3. Kasir dapat melepas tautan member sebelum checkout (kembali ke mode Non-Member).





### Modul 3: Katalog Produk & Manajemen Stok Cabang (`FR-07` s.d. `FR-09`)

* **`FR-07` Master Kategori & Produk Global**
* **Deskripsi:** `admin_nasional` mengelola kategori dan produk dengan harga seragam.
* **Acceptance Criteria:**
1. Kolom `sku` dan `barcode` wajib unik; `selling_price` wajib lebih besar atau sama dengan 0; `cost_price` wajib lebih besar atau sama dengan 0.
2. Penghapusan produk menggunakan `SoftDeletes` (`deleted_at`) sehingga relasi pada `transaction_items` historis tetap utuh.
3. Perubahan `cost_price` atau `selling_price` hanya berlaku untuk transaksi baru dan tidak pernah mengubah angka transaksi historis.




* **`FR-08` Restock & Penyesuaian Stok Cabang**
* **Deskripsi:** `admin_cabang` mengelola persediaan barang pada cabangnya sendiri.
* **Acceptance Criteria:**
1. Halaman stok cabang menampilkan 4 metrik jelas: **Stok Fisik (`quantity`)**, **Ditahan Midtrans (`reserved_qty`)**, **Stok Tersedia (`quantity - reserved_qty`)**, dan **Batas Minimum (`min_stock`)** dengan indikator visual merah jika Stok Tersedia kurang dari atau sama dengan `min_stock`.
2. Setiap operasi restock atau adjustment wajib menciptakan tepat 1 baris log di `stock_movements` dalam transaksi database yang sama.
3. Sistem menolak penyesuaian stok jika nilai stok fisik baru lebih kecil dari `reserved_qty` saat itu.




* **`FR-09` Audit Log Pergerakan Stok**
* **Deskripsi:** Riwayat mutasi stok per produk dan cabang yang tidak dapat diubah maupun dihapus (*append-only*).
* **Acceptance Criteria:**
1. Admin dapat memfilter log berdasarkan rentang tanggal, produk, tipe mutasi (`sale`, `restock`, `adjustment`, `void_return`), dan cabang (sesuai scope wilayah).
2. Tidak ada tombol Edit atau Delete pada antarmuka maupun endpoint backend untuk tabel `stock_movements`.





### Modul 4: Layar POS Kasir (Vue.js + Inertia) (`FR-10` s.d. `FR-14`)

* **`FR-10` Manajemen Input Hardware Terisolasi (Barcode vs. RFID)**
* **Deskripsi:** Perilaku spesifik komponen Vue `Pos/Index.vue` untuk menangani dua perangkat HID Keyboard Wedge secara aman.
* **Acceptance Criteria:**
1. Kolom input barcode memiliki *auto-focus* permanen selama tidak ada modal aktif dan kursor tidak sedang berada pada input kuantitas/nominal tunai.
2. Pemindaian kartu member hanya diterima ketika **Modal Tap Kartu Member** dibuka (via tombol UI atau shortcut `F2`). Input di dalam modal menerapkan *debouncing* 350ms dan fungsi normalisasi UID (trim whitespace, hapus karakter non-alfanumerik, ubah ke huruf kapital).
3. Pemindaian barcode produk yang sama secara berulang menambahkan kuantitas item di keranjang sebesar 1 tanpa membuat baris duplikat.




* **`FR-11` Manajemen Keranjang Sisi Klien & Idempotency Key**
* **Deskripsi:** State keranjang reaktif di Vue sebelum dikirim ke server.
* **Acceptance Criteria:**
1. Perubahan kuantitas (`+`, `-`, atau ketik manual) langsung menghitung ulang subtotal item dan grand total secara *real-time*.
2. `idempotency_key` (UUID v4 menggunakan `crypto.randomUUID()` dengan fallback generator) di-generate saat keranjang memiliki minimal 1 item, dipertahankan saat terjadi retry jaringan pada transaksi yang sama, dan di-regenerate hanya setelah transaksi berhasil selesai atau keranjang dikosongkan.




* **`FR-12` Checkout Pembayaran Tunai (`cash`)**
* **Deskripsi:** Penyelesaian transaksi dengan uang tunai.
* **Acceptance Criteria:**
1. Validasi frontend dan backend mewajibkan `cash_received` minimal sama dengan `total_amount`.
2. `change_amount` dihitung di backend (`cash_received - total_amount`) dan disimpan pada tabel `transactions`.
3. Status transaksi langsung `paid`, `paid_at` terisi waktu server, dan stok cabang berkurang seketika.




* **`FR-13` Checkout Pembayaran Midtrans Snap (`midtrans`) & Rekonsiliasi**
* **Deskripsi:** Integrasi pembayaran non-tunai menggunakan Midtrans Snap Sandbox dan webhook.
* **Acceptance Criteria:**
1. Saat checkout Midtrans dikirim, stok direservasi pada `reserved_qty`, transaksi dibuat dengan status `pending` dan `expires_at` 15 menit dari waktu buat, lalu `window.snap.pay(snapToken)` ditampilkan.
2. Jika kasir menutup pop-up Snap sebelum bayar selesai, transaksi tetap muncul di panel **"Transaksi Pending Cabang"** beserta tombol **"Buka Ulang Pembayaran"**, **"Cek Status Pembayaran"**, dan **"Batalkan Transaksi"** (yang memanggil Midtrans Cancel API lalu melepas `reserved_qty`).
3. Endpoint webhook `POST /api/webhooks/midtrans` dikecualikan dari middleware CSRF, memverifikasi `signature_key` SHA-512, menyimpan payload mentah ke `payment_logs`, dan memproses perubahan status secara idempotent.




* **`FR-14` Cetak Struk Belanja Thermal (58mm / 80mm)**
* **Deskripsi:** Pencetakan struk langsung dari browser menggunakan CSS `@media print`.
* **Acceptance Criteria:**
1. Struk menampilkan: Nama Cabang KOPDES & Alamat Desa, Nomor Invoice, Tanggal & Jam, Nama Kasir, Nama Member & KOPDES Asal (atau "Pembeli Umum" jika non-member), daftar item (nama, qty, harga satuan, subtotal), Total, Metode Pembayaran, Uang Diterima & Kembalian (khusus tunai), serta pesan footer koperasi.
2. Lebar cetak dapat dikonfigurasi (58mm default atau 80mm via pengaturan preferensi browser `localStorage` kasir) tanpa mencetak elemen navigasi atau sidebar dashboard.
3. Kasir dapat mencetak ulang struk untuk transaksi berstatus `paid` di cabangnya pada hari berjalan.





### Modul 5: Dashboard & Laporan Keuangan Berjenjang (`FR-15` s.d. `FR-17`)

* **`FR-15` Ringkasan Dashboard & Drill-Down Wilayah**
* **Deskripsi:** Halaman utama dashboard admin untuk memantau kinerja penjualan secara berjenjang.
* **Acceptance Criteria:**
1. Menampilkan 4 kartu KPI utama untuk periode tanggal terpilih (default: Bulan Ini): **Total Omzet (`paid`)**, **Total Transaksi (`paid`)**, **Total Laba Kotor (`SUM(total_amount - total_cogs)`)**, dan **Margin Laba Kotor (%)**.
2. Menampilkan pecahan metode pembayaran: total nominal & jumlah transaksi `cash` vs `midtrans`.
3. Admin dapat melakukan *drill-down* sesuai scope role: `admin_nasional` memfilter Provinsi → Kabupaten → Cabang; `admin_provinsi` memfilter Kabupaten → Cabang; `admin_kabupaten` memfilter Cabang; `admin_cabang` langsung melihat metrik cabangnya.




* **`FR-16` Laporan Rincian Penjualan & Laba Kotor**
* **Deskripsi:** Tabel laporan transaksi dan agregasi per cabang berdasarkan snapshot HPP.
* **Acceptance Criteria:**
1. Seluruh perhitungan laba kotor bersumber dari `transactions.total_amount - transactions.total_cogs` (yang diagregasi dari `transaction_items.cost_price_snapshot * qty`), bukan dari harga master produk saat ini.
2. Hanya transaksi berstatus `paid` yang masuk ke dalam omzet dan laba kotor; transaksi `pending`, `failed`, `expired`, dan `cancelled` dikecualikan secara ketat.
3. Mendukung filter rentang tanggal (`start_date`, `end_date`), metode bayar (`all`, `cash`, `midtrans`), dan filter wilayah sesuai scope.




* **`FR-17` Laporan Rekap Transaksi Member (Fondasi Data SHU)**
* **Deskripsi:** Rekapitulasi total belanja per anggota koperasi.
* **Acceptance Criteria:**
1. Menampilkan daftar member dengan kolom: Kode Member, Nama, KOPDES Asal (`home_branch_id`), Jumlah Transaksi `paid`, Total Belanja di KOPDES Asal, Total Belanja di KOPDES Lain (Lintas Cabang), dan Total Akumulasi Belanja.
2. `admin_cabang` dapat melihat rekap belanja seluruh member yang berasal dari cabangnya (`home_branch_id = admin.branch_id`), termasuk kontribusi belanja member tersebut di cabang desa lain.





---

## 5. Desain Skema Database (ERD & Kamus Data)

### 5.1 Diagram Relasi Entitas (ERD ASCII)

```text
+-----------+     +-----------+     +-----------+     +-----------+
| provinces |1---N| regencies |1---N| districts |1---N| villages  |
+-----------+     +-----------+     +-----------+     +-----------+
      │                 │                                   │ 1
      │ 1               │ 1                                 │ (UNIQUE)
      │                 │                                   │ 1
      │                 │                            +-------------+
      └─────────────────┴───────────────────────────N|  branches   |
                                                     +-------------+
                       ┌──────────────────┬──────────────┼───────┬────────────────┐
                       │ 1                │ 1            │ 1     │ 1              │ 1
                       ▼ N                ▼ N            ▼ N     ▼ N              ▼ N
                  +---------+       +-----------+   +---------------+       +--------------+
                  |  users  |       |  members  |   | branch_stocks |       | transactions |
                  +---------+       +-----------+   +---------------+       +--------------+
                       │ 1                │ 1            ▲ N                   │ 1      │ 1
                       │ (cashier_id)     │ (member_id)  │                     │        │
                       └──────────────────┼──────────────┼─────────────────────┘        │
                                          │              │ 1                            │
                                          │        +------------+     +------------+    │
                                          │        |  products  |N---1| categories |    │
                                          │        +------------+     +------------+    │
                                          │              │ 1                            │
                                          │              │                              ▼ N
                                          │              ├──────────────────────+-------------------+
                                          │              │                      | transaction_items |
                                          │              ▼ N                    +-------------------+
                                          │        +-----------------+
                                          │        | stock_movements |          +--------------+
                                          │        +-----------------+          | payment_logs |
                                          │                                     +--------------+
                                          └──────── (transaksi mencatat                 ▲ N
                                                     member_home_branch_id)             │ 1
                                                                                (transactions)

```

### 5.2 Kamus Data Lengkap (14 Tabel Domain)

#### 1. `provinces`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `CHAR(2)` | `PRIMARY KEY` | Kode provinsi baku (contoh: `35`) |
| `name` | `VARCHAR(100)` | `NOT NULL`, `INDEX` | Nama provinsi (contoh: `JAWA TIMUR`) |

#### 2. `regencies`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `CHAR(4)` | `PRIMARY KEY` | Kode kabupaten/kota baku (contoh: `3502`) |
| `province_id` | `CHAR(2)` | `NOT NULL`, `FK -> provinces(id) ON DELETE RESTRICT`, `INDEX` | Referensi provinsi |
| `name` | `VARCHAR(100)` | `NOT NULL`, `INDEX` | Nama kabupaten/kota (contoh: `KABUPATEN PONOROGO`) |

#### 3. `districts`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `CHAR(7)` | `PRIMARY KEY` | Kode kecamatan baku (contoh: `3502090`) |
| `regency_id` | `CHAR(4)` | `NOT NULL`, `FK -> regencies(id) ON DELETE RESTRICT`, `INDEX` | Referensi kabupaten/kota |
| `name` | `VARCHAR(100)` | `NOT NULL`, `INDEX` | Nama kecamatan |

#### 4. `villages`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `CHAR(10)` | `PRIMARY KEY` | Kode desa/kelurahan baku |
| `district_id` | `CHAR(7)` | `NOT NULL`, `FK -> districts(id) ON DELETE RESTRICT`, `INDEX` | Referensi kecamatan |
| `name` | `VARCHAR(100)` | `NOT NULL`, `INDEX` | Nama desa/kelurahan |

#### 5. `branches`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID internal cabang KOPDES |
| `code` | `VARCHAR(30)` | `NOT NULL`, `UNIQUE` | Kode unik cabang (contoh: `KOP-3502-001`) |
| `name` | `VARCHAR(150)` | `NOT NULL` | Nama resmi (contoh: `KOPDES Desa Ngunut`) |
| `village_id` | `CHAR(10)` | `NOT NULL`, **`UNIQUE`**, `FK -> villages(id) ON DELETE RESTRICT` | Menjamin aturan mutlak **1 Desa = 1 KOPDES** |
| `district_id` | `CHAR(7)` | `NOT NULL`, `FK -> districts(id) ON DELETE RESTRICT`, `INDEX` | Denormalisasi referensi kecamatan |
| `regency_id` | `CHAR(4)` | `NOT NULL`, `FK -> regencies(id) ON DELETE RESTRICT`, `INDEX` | Denormalisasi untuk filter cepat `admin_kabupaten` |
| `province_id` | `CHAR(2)` | `NOT NULL`, `FK -> provinces(id) ON DELETE RESTRICT`, `INDEX` | Denormalisasi untuk filter cepat `admin_provinsi` |
| `address` | `TEXT` | `NOT NULL` | Alamat lengkap toko/koperasi desa |
| `phone` | `VARCHAR(25)` | `NULL` | Nomor telepon cabang |
| `is_active` | `BOOLEAN` | `NOT NULL`, `DEFAULT TRUE`, `INDEX` | Status operasional cabang |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |

#### 6. `users`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID pengguna |
| `name` | `VARCHAR(150)` | `NOT NULL` | Nama lengkap admin atau kasir |
| `email` | `VARCHAR(150)` | `NULL`, `UNIQUE` | Wajib untuk Admin, nullable untuk Kasir |
| `password` | `VARCHAR(255)` | `NULL` | Hash password Admin, nullable untuk Kasir |
| `role` | `ENUM('admin_nasional','admin_provinsi','admin_kabupaten','admin_cabang','kasir')` | `NOT NULL`, `INDEX` | Peran pengguna |
| `province_id` | `CHAR(2)` | `NULL`, `FK -> provinces(id) ON DELETE RESTRICT`, `INDEX` | Scope provinsi |
| `regency_id` | `CHAR(4)` | `NULL`, `FK -> regencies(id) ON DELETE RESTRICT`, `INDEX` | Scope kabupaten/kota |
| `branch_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> branches(id) ON DELETE RESTRICT`, `INDEX` | Scope cabang KOPDES |
| `card_uid` | `VARCHAR(64)` | `NULL`, `UNIQUE` | UID kartu RFID kasir (ternormalisasi huruf kapital) |
| `pin_hash` | `VARCHAR(255)` | `NULL` | Hash bcrypt/argon2id dari PIN 6 digit kasir |
| `is_active` | `BOOLEAN` | `NOT NULL`, `DEFAULT TRUE`, `INDEX` | Status aktif akun |
| `remember_token` | `VARCHAR(100)` | `NULL` | Token sesi Laravel |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |

#### 7. `members`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID anggota |
| `member_code` | `VARCHAR(40)` | `NOT NULL`, `UNIQUE` | Nomor anggota koperasi |
| `home_branch_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> branches(id) ON DELETE RESTRICT`, `INDEX` | KOPDES asal pendaftaran anggota |
| `card_uid` | `VARCHAR(64)` | `NOT NULL`, `UNIQUE` | UID kartu RFID anggota (berlaku nasional) |
| `name` | `VARCHAR(150)` | `NOT NULL` | Nama lengkap anggota |
| `phone` | `VARCHAR(25)` | `NULL`, `INDEX` | Nomor telepon anggota |
| `nik` | `VARCHAR(20)` | `NULL`, `UNIQUE` | Nomor Induk Kependudukan (opsional di MVP) |
| `status` | `ENUM('active','inactive')` | `NOT NULL`, `DEFAULT 'active'`, `INDEX` | Status keaktifan kartu anggota |
| `joined_at` | `DATE` | `NOT NULL` | Tanggal bergabung |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |

#### 8. `categories`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID kategori |
| `name` | `VARCHAR(100)` | `NOT NULL` | Nama kategori (misal: Sembako, Pupuk, Minuman) |
| `slug` | `VARCHAR(120)` | `NOT NULL`, `UNIQUE` | Slug unik kategori |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |

#### 9. `products`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID produk |
| `category_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> categories(id) ON DELETE RESTRICT`, `INDEX` | Referensi kategori |
| `sku` | `VARCHAR(50)` | `NOT NULL`, `UNIQUE` | Kode stok internal pusat |
| `barcode` | `VARCHAR(64)` | `NOT NULL`, `UNIQUE`, `INDEX` | Kode barcode fisik (EAN-13/UPC/Code-128) |
| `name` | `VARCHAR(200)` | `NOT NULL`, `INDEX` | Nama produk |
| `unit` | `VARCHAR(30)` | `NOT NULL`, `DEFAULT 'pcs'` | Satuan jual (`pcs`, `kg`, `liter`, `karung`, `dus`) |
| `cost_price` | `DECIMAL(15,2)` | `NOT NULL` | HPP / Harga Pokok Penjualan terpusat saat ini |
| `selling_price` | `DECIMAL(15,2)` | `NOT NULL` | Harga jual seragam nasional saat ini |
| `is_active` | `BOOLEAN` | `NOT NULL`, `DEFAULT TRUE`, `INDEX` | Penanda apakah produk bisa dijual di POS |
| `deleted_at` | `TIMESTAMP` | `NULL`, `INDEX` | SoftDeletes Laravel |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |

#### 10. `branch_stocks`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID record stok cabang |
| `branch_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> branches(id) ON DELETE RESTRICT` | Cabang pemilik stok |
| `product_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> products(id) ON DELETE RESTRICT` | Produk terkait |
| `quantity` | `INT` | `NOT NULL`, `DEFAULT 0` | Jumlah stok fisik di cabang |
| `reserved_qty` | `INT` | `NOT NULL`, `DEFAULT 0` | Jumlah stok yang ditahan oleh transaksi Midtrans `pending` |
| `min_stock` | `INT` | `NOT NULL`, `DEFAULT 5` | Ambang batas peringatan stok menipis |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |
| **Composite Constraint** | `UNIQUE (branch_id, product_id)` | `INDEX (product_id)` | Satu produk hanya punya satu baris stok per cabang |

#### 11. `stock_movements`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID log pergerakan stok (*append-only*) |
| `branch_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> branches(id) ON DELETE RESTRICT`, `INDEX` | Cabang tempat perubahan stok terjadi |
| `product_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> products(id) ON DELETE RESTRICT`, `INDEX` | Produk terkait |
| `user_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> users(id) ON DELETE RESTRICT` | Aktor (Kasir atau Admin Cabang) yang memicu perubahan |
| `type` | `ENUM('sale','restock','adjustment','void_return')` | `NOT NULL`, `INDEX` | Jenis pergerakan stok |
| `qty_change` | `INT` | `NOT NULL` | Nilai perubahan bertanda (negatif untuk penjualan/pengurangan, positif untuk restock/penambahan) |
| `qty_before` | `INT` | `NOT NULL` | Nilai `branch_stocks.quantity` tepat sebelum perubahan |
| `qty_after` | `INT` | `NOT NULL` | Nilai `branch_stocks.quantity` tepat setelah perubahan |
| `reference_type` | `VARCHAR(100)` | `NULL` | Nama kelas model referensi (contoh: `App\Models\Transaction`) |
| `reference_id` | `BIGINT UNSIGNED` | `NULL` | ID record referensi |
| `notes` | `TEXT` | `NULL` | Catatan atau alasan mutasi |
| `created_at` | `TIMESTAMP` | `NOT NULL`, `INDEX` | Waktu mutasi dicatat (tanpa `updated_at` karena immutable) |
| **Composite Index** | `INDEX (branch_id, product_id, created_at)` | `INDEX (reference_type, reference_id)` | Optimasi tampilan kartu stok per cabang |

#### 12. `transactions`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID transaksi |
| `invoice_number` | `VARCHAR(50)` | `NOT NULL`, `UNIQUE` | Format `INV-{BRANCH_CODE}-{YYYYMMDD}-{SEQ4}` (juga digunakan sebagai Midtrans `order_id`) |
| `idempotency_key` | `CHAR(36)` | `NOT NULL`, `UNIQUE` | UUID v4 dari frontend Vue untuk mencegah transaksi ganda |
| `branch_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> branches(id) ON DELETE RESTRICT` | Cabang lokasi transaksi dilakukan |
| `cashier_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> users(id) ON DELETE RESTRICT`, `INDEX` | Kasir yang memproses transaksi |
| `member_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> members(id) ON DELETE RESTRICT`, `INDEX` | NULL untuk pembeli non-member |
| `member_home_branch_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> branches(id) ON DELETE RESTRICT`, `INDEX` | **Snapshot** `members.home_branch_id` saat transaksi terjadi |
| `subtotal` | `DECIMAL(15,2)` | `NOT NULL` | Total nilai barang sebelum penyesuaian (sama dengan `total_amount` di MVP) |
| `total_amount` | `DECIMAL(15,2)` | `NOT NULL` | Total nominal yang wajib dibayar pembeli |
| `total_cogs` | `DECIMAL(15,2)` | `NOT NULL` | **Snapshot** total HPP seluruh item (`SUM(transaction_items.subtotal_cogs)`) |
| `payment_method` | `ENUM('cash','midtrans')` | `NOT NULL`, `INDEX` | Metode pembayaran |
| `payment_status` | `ENUM('pending','paid','failed','expired','cancelled')` | `NOT NULL`, `DEFAULT 'pending'`, `INDEX` | Status siklus hidup pembayaran |
| `cash_received` | `DECIMAL(15,2)` | `NULL` | Nominal uang diterima (wajib terisi jika `cash`) |
| `change_amount` | `DECIMAL(15,2)` | `NULL` | Nominal kembalian (wajib terisi jika `cash`) |
| `snap_token` | `VARCHAR(255)` | `NULL` | Token pop-up Midtrans Snap (khusus `midtrans`) |
| `midtrans_payment_type` | `VARCHAR(50)` | `NULL` | Tipe bayar aktual dari webhook (`qris`, `gopay`, `bank_transfer`, `shopeepay`) |
| `expires_at` | `TIMESTAMP` | `NULL`, `INDEX` | Batas waktu pembayaran Midtrans (15 menit sejak checkout) |
| `paid_at` | `TIMESTAMP` | `NULL`, `INDEX` | Waktu konfirmasi pembayaran lunas |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu transaksi dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu transaksi diperbarui |
| **Composite Index** | `INDEX (branch_id, payment_status, paid_at)` | `INDEX (payment_status, expires_at)` | Mempercepat laporan keuangan dan Scheduler pembersihan pending |

#### 13. `transaction_items`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID baris item transaksi |
| `transaction_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> transactions(id) ON DELETE CASCADE`, `INDEX` | Referensi header transaksi |
| `product_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> products(id) ON DELETE RESTRICT`, `INDEX` | Referensi produk master |
| `product_name_snapshot` | `VARCHAR(200)` | `NOT NULL` | **Snapshot** nama produk saat transaksi terjadi |
| `barcode_snapshot` | `VARCHAR(64)` | `NOT NULL` | **Snapshot** barcode produk saat transaksi terjadi |
| `unit_snapshot` | `VARCHAR(30)` | `NOT NULL` | **Snapshot** satuan produk saat transaksi terjadi |
| `qty` | `INT` | `NOT NULL` | Jumlah unit dibeli (minimal 1) |
| `unit_price_snapshot` | `DECIMAL(15,2)` | `NOT NULL` | **Snapshot** `products.selling_price` saat transaksi |
| `cost_price_snapshot` | `DECIMAL(15,2)` | `NOT NULL` | **Snapshot** `products.cost_price` (HPP) saat transaksi |
| `subtotal` | `DECIMAL(15,2)` | `NOT NULL` | Hasil `unit_price_snapshot * qty` |
| `subtotal_cogs` | `DECIMAL(15,2)` | `NOT NULL` | Hasil `cost_price_snapshot * qty` |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |

#### 14. `payment_logs`

| Kolom | Tipe Data | Constraint & Index | Keterangan |
| --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID log notifikasi pembayaran |
| `transaction_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> transactions(id) ON DELETE SET NULL`, `INDEX` | Referensi transaksi lokal (nullable jika webhook masuk dengan `order_id` tidak dikenal) |
| `gateway` | `VARCHAR(30)` | `NOT NULL`, `DEFAULT 'midtrans'` | Nama payment gateway |
| `source` | `ENUM('webhook','manual_check','scheduler_check')` | `NOT NULL` | Sumber pencatatan log |
| `external_order_id` | `VARCHAR(100)` | `NOT NULL`, `INDEX` | Nilai `order_id` dari payload Midtrans |
| `midtrans_transaction_id` | `VARCHAR(100)` | `NULL`, `INDEX` | Nilai `transaction_id` unik dari Midtrans |
| `payment_type` | `VARCHAR(50)` | `NULL` | Metode bayar aktual dari Midtrans |
| `transaction_status` | `VARCHAR(50)` | `NOT NULL` | Status Midtrans (`pending`, `settlement`, `capture`, `deny`, `cancel`, `expire`) |
| `fraud_status` | `VARCHAR(30)` | `NULL` | Status fraud Midtrans (`accept`, `challenge`, `deny`) |
| `gross_amount` | `DECIMAL(15,2)` | `NOT NULL` | Nominal dari payload Midtrans |
| `signature_verified` | `BOOLEAN` | `NOT NULL`, `DEFAULT FALSE` | Hasil validasi SHA-512 (`true` untuk webhook valid atau respons API terautentikasi) |
| `raw_payload` | `JSON` | `NOT NULL` | Salinan utuh JSON notifikasi untuk audit |
| `created_at` | `TIMESTAMP` | `NOT NULL`, `INDEX` | Waktu log dicatat |

### 5.3 Aturan Mutlak Snapshot Data

1. **Snapshot Harga & HPP:** Pada saat `CheckoutService` membuat transaksi, sistem menyalin `products.name`, `products.barcode`, `products.unit`, `products.selling_price`, dan `products.cost_price` ke dalam kolom `*_snapshot` di `transaction_items`, serta menjumlahkan `subtotal_cogs` ke `transactions.total_cogs`. Seluruh query laporan keuangan dilarang mengambil `cost_price` atau `selling_price` dari tabel `products`.
2. **Snapshot Cabang Asal Member:** Jika `member_id` terisi, `transactions.member_home_branch_id` diisi dari `members.home_branch_id` saat transaksi dibuat. Hal ini memastikan laporan partisipasi historis KOPDES asal tidak berubah apabila di masa depan anggota tersebut berpindah domisili desa.

---

## 6. Aturan Bisnis Teknis & Mitigasi Edge Cases Kritis

### 6.1 Pessimistic Locking & Manajemen `reserved_qty` (Anti Stok Minus & Anti Stok Menggantung)

* **Rumus Stok Tersedia:** `available_stock = quantity - reserved_qty`. Nilai `available_stock` tidak pernah boleh negatif.
* **Pencegahan Deadlock:** Sebelum mengunci baris stok untuk keranjang dengan banyak item, urutkan daftar `product_id` secara menaik (`sortBy('product_id')`), lalu kunci seluruh baris sekaligus dengan satu query terurut:
```php
$stocks = BranchStock::where('branch_id', $branchId)
    ->whereIn('product_id', $sortedProductIds)
    ->orderBy('product_id')
    ->lockForUpdate()
    ->get()
    ->keyBy('product_id');

```


* **Matriks Transisi Stok:**

| Peristiwa | Perubahan `quantity` | Perubahan `reserved_qty` | Tulis `stock_movements`? |
| --- | --- | --- | --- |
| Checkout Tunai (`cash` → `paid`) | `-qty` | `0` | Ya (`type = 'sale'`, `qty_change = -qty`) |
| Checkout Midtrans dibuat (`pending`) | `0` | `+qty` | Tidak (belum terjadi perpindahan fisik) |
| Midtrans terkonfirmasi lunas (`pending` → `paid`) | `-qty` | `-qty` | Ya (`type = 'sale'`, `qty_change = -qty`) |
| Midtrans kedaluwarsa / batal / ditolak (`pending` → `expired`/`cancelled`/`failed`) | `0` | `-qty` | Tidak |

* **Pembersihan Otomatis (Laravel Scheduler + Queue Driver `database`):**
* Konfigurasikan command `kopdes:reconcile-expired-transactions` di `routes/console.php` untuk berjalan setiap **5 menit** (`->everyFiveMinutes()->withoutOverlapping()`).
* Command mengambil transaksi dengan `payment_method = 'midtrans'`, `payment_status = 'pending'`, dan `expires_at` kurang dari atau sama dengan `now()`.
* Sebelum membatalkan, job memanggil Midtrans Get Status API untuk memastikan pembeli tidak membayar tepat di detik terakhir sebelum batas waktu. Jika status Midtrans `settlement`, sistem menyelesaikan pembayaran menjadi `paid`; selain itu, sistem mengubah status ke `expired` dan melepaskan `reserved_qty` di dalam `DB::transaction()` + `lockForUpdate()`.



### 6.2 Keamanan & Idempotensi Midtrans Webhook

1. **Pengecualian CSRF:** Daftarkan `/api/webhooks/midtrans` pada pengecualian CSRF di `bootstrap/app.php` (`$middleware->validateCsrfTokens(except: ['api/webhooks/midtrans'])`).
2. **Validasi Signature SHA-512:**
```php
$expectedSignature = hash(
    'sha512',
    $payload['order_id'] .
    $payload['status_code'] .
    $payload['gross_amount'] .
    config('services.midtrans.server_key')
);

if (!hash_equals($expectedSignature, (string) ($payload['signature_key'] ?? ''))) {
    // Tetap simpan ke payment_logs dengan signature_verified = false untuk audit keamanan
    return response()->json(['message' => 'Invalid signature'], 403);
}

```


3. **Verifikasi Nominal (`gross_amount`):** Pastikan `(float) $payload['gross_amount'] === (float) $transaction->total_amount`. Jika berbeda, tolak pemrosesan otomatis dan tandai di log.
4. **Idempotency Guard:** Di dalam `DB::transaction()`, kunci baris transaksi (`Transaction::where('invoice_number', $orderId)->lockForUpdate()->first()`). Jika `$transaction->payment_status` bukan `'pending'` (sudah `'paid'`, `'expired'`, `'cancelled'`, atau `'failed'`), langsung hentikan pemrosesan stok dan kembalikan HTTP `200 OK` agar Midtrans berhenti melakukan *retry*.
5. **Pemetaan Status Midtrans:**
* `settlement`, atau `capture` dengan `fraud_status === 'accept'` → `paid`.
* `pending` → tetap `pending`.
* `expire` → `expired` (lepas `reserved_qty`).
* `cancel` → `cancelled` (lepas `reserved_qty`).
* `deny`, atau `capture` dengan `fraud_status === 'deny'` → `failed` (lepas `reserved_qty`).


6. **Tombol Manual "Cek Status Pembayaran":** Endpoint `POST /pos/transactions/{transaction}/check-status` memanggil Midtrans Status API dari backend dan memanggil `MidtransReconciliationService::applyStatus()` yang sama dengan controller webhook. Hal ini menjamin alur bisnis tidak terduplikasi dan antrean kasir tetap berjalan meski webhook terlambat.

### 6.3 Anti Double-Click Checkout (`idempotency_key`)

* **Frontend Vue:** Saat item pertama ditambahkan ke keranjang kosong, Vue membuat `idempotencyKey = generateUuidV4()`. Saat tombol bayar ditekan, state `isSubmitting = true` langsung mengunci tombol hingga respons diterima.
* **Backend Laravel:** Sebelum mengunci stok, `CheckoutService` memeriksa `Transaction::where('idempotency_key', $dto->idempotencyKey)->first()`.
* Jika sudah ada dan milik `branch_id` serta `cashier_id` yang sama: langsung kembalikan respons sukses berisi data transaksi (dan `snap_token` jika Midtrans) yang sudah tersimpan tanpa memotong atau mereservasi stok ulang.
* Tangkap `UniqueConstraintViolationException` pada kolom `idempotency_key` sebagai pengaman lapis kedua apabila dua request tiba pada milidetik yang sama persis, lalu ambil dan kembalikan record yang berhasil tersimpan.



### 6.4 Pemisahan Input HID Keyboard Wedge di Vue (Barcode vs. RFID)

Kedua perangkat mengirimkan rangkaian karakter cepat diakhiri `Enter`. Untuk mencegah kebocoran input silang:

1. **Isolasi Fokus Modal (`F2`):** Input barcode di layar utama hanya memproses pemindaian ketika `activeModal === null`. Saat kasir membuka modal **Tap Kartu Member (`F2`)**, fokus otomatis dipindahkan ke `<input ref="rfidInput">` di dalam modal dan listener barcode utama dinonaktifkan sementara.
2. **Normalisasi UID Kartu:** Buat utility bersama di frontend (`normalizeCardUid.js`) dan backend (`CardUidNormalizer::normalize(string $raw)`):
* Hapus seluruh spasi di awal/akhir serta karakter non-alfanumerik (`preg_replace('/[^A-Za-z0-9]/', '', trim($raw))`).
* Konversi ke huruf kapital (`strtoupper()`).


3. **Debouncing:** Pada input RFID di form registrasi maupun modal POS, terapkan jeda *debounce* 350ms dan kunci submit selama request lookup sedang berlangsung (`isLookingUpCard = true`) agar getaran pembacaan kartu tidak mengirim dua request sekaligus.
4. **Pencegahan Kartu Nyasar ke Kolom Barcode:** Jika kasir lupa menekan `F2` dan member menempelkan kartu langsung di kolom barcode, sistem tidak akan pernah menemukan barcode produk tersebut dan menampilkan peringatan jelas: *"Barcode tidak ditemukan. Jika Anda memindai Kartu Member, tekan F2 terlebih dahulu."*

### 6.5 Perlindungan PIN Kasir & Pencegahan Kebocoran Data Lintas Wilayah

* **Rate Limiting PIN Kasir:** Gunakan `RateLimiter` Laravel dengan kunci `'kasir-pin:' . $normalizedUid . '|' . $request->ip()`:
* Batas maksimal: **5 kali gagal**.
* Waktu blokir (*decay*): **900 detik (15 menit)**.
* Reset otomatis (`RateLimiter::clear($key)`) saat autentikasi berhasil.


* **Idle Auto-Lock POS di Vue:** Composable `useIdleLock(timeoutMs = 900000)` mendengarkan event `mousemove`, `keydown`, `click`, dan `touchstart` (di-throttle tiap 5 detik). Ketika melewati 15 menit tanpa aktivitas, Vue menampilkan modal kunci layar penuh (`PosLockModal.vue`) dan memanggil `POST /pos/lock` agar session server juga menandai `pos_locked = true`. Middleware `EnsurePosUnlocked` menolak seluruh endpoint checkout jika `session('pos_locked') === true`.
* **Anti-IDOR Wilayah:** Setiap route admin yang menerima parameter `branch_id`, `regency_id`, atau `province_id` (baik sebagai route model binding maupun query filter laporan) wajib divalidasi melalui **Laravel Policy** dan **FormRequest**. Jika `admin_kabupaten` Ponorogo mengirim `?branch_id=99` milik Kabupaten Madiun, backend mengembalikan HTTP `403 Forbidden` (bukan sekadar mengabaikan filter di UI).

---

## 7. Rencana Implementasi Bertahap (Milestones untuk AI Agent)

Pengembangan dibagi menjadi 5 milestone berurutan. Setiap milestone harus lolos seluruh kriteria pengujiannya sebelum melanjutkan ke milestone berikutnya.

### Milestone 1: Fondasi Proyek, Skema Database, Seeder Wilayah & RBAC

* **Tujuan:** Menyiapkan proyek Laravel + Inertia + Vue 3 + Tailwind CSS, seluruh migrasi 14 tabel domain + tabel queue/sessions, seeder wilayah sampel (Jawa Timur → Ponorogo → Kecamatan → Desa sampel), dan fondasi autentikasi serta scoping wilayah.
* **Deliverables Utama:**
1. Instalasi Laravel + Inertia Laravel + `@inertiajs/vue3` + Vue 3 + Tailwind CSS + konfigurasi `QUEUE_CONNECTION=database`.
2. 14 file migrasi sesuai Kamus Data Bagian 5.2 (termasuk seluruh `UNIQUE`, `FOREIGN KEY`, dan composite `INDEX`).
3. 14 Model Eloquent lengkap dengan relasi, `$casts`, `SoftDeletes` pada `Product`, serta trait `HasRegionScope`.
4. `RegionSeeder` (data referensi wilayah sampel lengkap termasuk Provinsi Jawa Timur dan Kabupaten Ponorogo), `RoleAndAdminSeeder` (akun awal `admin_nasional` hingga `admin_cabang` dan 2 cabang sampel).
5. Middleware `EnsureRole`, `EnsurePosUnlocked`, dan Laravel Policies (`BranchPolicy`, `UserPolicy`, `MemberPolicy`, `StockPolicy`, `ReportPolicy`).
6. Halaman login Admin (`/login`) dan layout dasar Dashboard Inertia Vue.


* **Definition of Done (Pengujian):**
* `php artisan migrate:fresh --seed` berjalan tanpa error.
* Feature Test membuktikan: (a) constraint `UNIQUE(village_id)` menolak KOPDES kedua di desa yang sama; (b) `HasRegionScope` mencegah `admin_kabupaten` dan `admin_cabang` mengakses data cabang di luar wilayahnya (HTTP 403).



### Milestone 2: Manajemen Cabang, User/Kasir (RFID + PIN), Member Lintas Cabang & Katalog Produk

* **Tujuan:** Menyelesaikan seluruh modul master data dan fitur autentikasi kasir berbasis RFID + PIN.
* **Deliverables Utama:**
1. CRUD Cabang KOPDES dengan dependent dropdown wilayah (Provinsi → Kabupaten → Kecamatan → Desa).
2. CRUD User Admin berjenjang & CRUD Kasir dengan komponen modal input RFID (`RfidCaptureModal.vue`), validasi PIN 6 digit, dan normalisasi UID.
3. CRUD Member oleh Admin Cabang (otomatis mengisi `home_branch_id`, binding kartu RFID, cek keunikan `card_uid` lintas tabel `users` dan `members`).
4. CRUD Kategori & Master Produk Global (SKU, barcode, `cost_price`, `selling_price`, `SoftDeletes`).
5. Halaman Login Kasir (`/pos/login`), mekanisme `RateLimiter` 5x percobaan PIN, dan fitur Lock/Unlock layar POS.


* **Definition of Done (Pengujian):**
* Feature Test membuktikan: (a) kasir dapat login dengan `card_uid` (berbagai format spasi/huruf kecil tetap ternormalisasi) + PIN 6 digit; (b) percobaan salah PIN ke-6 mengembalikan HTTP 429; (c) `card_uid` yang sudah dipakai kasir ditolak saat didaftarkan untuk member.



### Milestone 3: Manajemen Inventori Cabang (`branch_stocks` & `stock_movements`)

* **Tujuan:** Membangun pengelolaan stok independen per cabang beserta log audit *append-only*.
* **Deliverables Utama:**
1. `StockService` dengan `DB::transaction()` dan `lockForUpdate()` untuk operasi `restock()` dan `adjust()`.
2. Halaman Inventori Cabang (`Inventory/Index.vue`) menampilkan stok fisik, `reserved_qty`, stok tersedia, dan peringatan `min_stock`.
3. Modal Restock dan Modal Penyesuaian Stok (validasi stok fisik aktual tidak kurang dari `reserved_qty` + catatan wajib).
4. Halaman Riwayat Pergerakan Stok (`Inventory/Movements.vue`) dengan filter produk, tipe mutasi, rentang tanggal, dan cabang (read-only).


* **Definition of Done (Pengujian):**
* Feature Test membuktikan: (a) restock menambah `quantity` dan menulis tepat 1 baris `stock_movements` dengan `qty_before`/`qty_after` akurat; (b) adjustment menolak jika nilai baru di bawah `reserved_qty`; (c) `admin_cabang` tidak dapat mengubah stok cabang lain.



### Milestone 4: Layar POS Kasir (Vue 3), Checkout Tunai, Midtrans Snap, Webhook & Cetak Struk

* **Tujuan:** Menyelesaikan alur inti operasional kasir secara penuh, aman terhadap *race condition* dan *double-click*.
* **Deliverables Utama:**
1. Komponen `Pos/Index.vue`, `Pos/Partials/MemberLookupModal.vue` (`F2`), `Pos/Partials/PaymentModal.vue` (`F9`), `Pos/Partials/PendingTransactionsDrawer.vue`, `Pos/Partials/PosLockModal.vue` (`F12` + `useIdleLock`), dan `Pos/Partials/ReceiptThermal.vue` (`@media print` 58mm/80mm).
2. `CheckoutService` menangani alur `cash` dan `midtrans` dengan `idempotency_key`, pengurutan `product_id` + `lockForUpdate()`, pengisian seluruh kolom snapshot (`unit_price_snapshot`, `cost_price_snapshot`, `member_home_branch_id`, `total_cogs`), serta reservasi `reserved_qty`.
3. `MidtransService` (generate Snap Token + Get Transaction Status API + Cancel Transaction API) dan `MidtransReconciliationService` (logika tunggal perubahan status dan stok).
4. `MidtransWebhookController` pada `POST /api/webhooks/midtrans` (bebas CSRF, verifikasi SHA-512, simpan `payment_logs`, idempotent).
5. Endpoint `POST /pos/transactions/{transaction}/check-status` dan `POST /pos/transactions/{transaction}/cancel`.
6. Console Command `kopdes:reconcile-expired-transactions` terjadwal tiap 5 menit untuk melepas `reserved_qty` transaksi kedaluwarsa.


* **Definition of Done (Pengujian):**
* Feature Test membuktikan: (a) checkout tunai mengurangi `quantity`, menulis `stock_movements`, dan mengirim ulang request dengan `idempotency_key` sama tidak membuat transaksi ganda; (b) checkout Midtrans menambah `reserved_qty` tanpa mengurangi `quantity`, sehingga kasir lain tidak bisa menjual stok yang sedang ditahan; (c) webhook dengan signature salah ditolak 403; (d) webhook `settlement` yang dikirim 2 kali hanya memotong stok 1 kali; (e) webhook `expire` dan command scheduler melepas `reserved_qty` kembali ke 0; (f) lookup member lintas cabang berhasil mencatat `branch_id` berbeda dengan `member_home_branch_id`.



### Milestone 5: Dashboard Keuangan Berjenjang & Rekap Transaksi Member

* **Tujuan:** Menyediakan pelaporan keuangan akurat berbasis snapshot HPP dengan drill-down wilayah sesuai RBAC.
* **Deliverables Utama:**
1. `FinancialReportService` menggunakan query agregasi terindeks (`branch_id`, `payment_status`, `paid_at`) yang diproteksi `HasRegionScope`.
2. Halaman Dashboard (`Dashboard/Index.vue`) dengan KPI Omzet, Jumlah Transaksi, Laba Kotor, Margin %, dan perbandingan Tunai vs Midtrans serta filter drill-down wilayah dinamis sesuai role login.
3. Halaman Laporan Penjualan & Laba Kotor (`Reports/Sales.vue`) per cabang dan rincian transaksi.
4. Halaman Laporan Rekap Member (`Reports/Members.vue`) menampilkan belanja di KOPDES asal vs belanja lintas cabang dan total akumulasi.


* **Definition of Done (Pengujian):**
* Feature Test membuktikan: (a) mengubah `products.cost_price` setelah transaksi `paid` tidak mengubah nilai Laba Kotor pada laporan historis; (b) transaksi `pending`, `expired`, `failed`, dan `cancelled` tidak masuk ke total omzet; (c) `admin_cabang` dapat melihat total belanja anggotanya baik saat berbelanja di cabang sendiri maupun di cabang desa lain; (d) percobaan manipulasi parameter URL wilayah oleh role di bawah nasional ditolak dengan HTTP 403.