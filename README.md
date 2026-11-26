# KOPDES — Sistem Kasir & Manajemen Koperasi Desa Merah Putih

Aplikasi web *full-stack* untuk operasional **Koperasi Desa (KOPDES) Merah Putih** berskala nasional-hingga-desa dengan arsitektur *multi-branch* hierarkis (**Nasional → Provinsi → Kabupaten → Cabang Desa**), Point of Sale (POS) berdukungan USB Barcode Scanner & RFID Reader, integrasi pembayaran **Midtrans Snap**, manajemen Shift Kasir *Blind Count*, Retur/Void terpusat, Laporan Keuangan & SHU (Excel/PDF), Pengaturan Sistem Dinamis & Audit Trail, Onboarding Massal Excel/CSV, Cetak Barcode/Kartu Member, **Portal Mandiri Member** (*mobile-first*), serta **Mode Demo Portofolio** lengkap dengan Simulator Hardware.

---

## Arsitektur & Tech Stack

- **Backend:** Laravel 13.x (PHP 8.3+), Eloquent ORM, Form Requests, Laravel Policies, Service Layer, Dual Auth Guard (`web` untuk Admin/Kasir & `member` untuk Portal Anggota), `RateLimiter`, Scheduler.
- **Frontend:** Inertia.js (`@inertiajs/vue3`), Vue 3 (`<script setup>` Composition API), Tailwind CSS, Ziggy (`route()`), `jsbarcode` (SVG Barcode 1D), `qrcode` (SVG QR Code).
- **Database:** MySQL 8.0+ / MariaDB 10.6+ (InnoDB, transaksi ACID, `DB::transaction()` + `lockForUpdate()` anti stok minus & anti deadlock, snapshot harga & HPP historis).
- **Pembayaran & Dokumen:** Midtrans Snap + Webhook rekonsiliasi idempotent (`SHA-512`), `phpoffice/phpspreadsheet` (Ekspor & Import massal `.xlsx`/`.csv`), `barryvdh/laravel-dompdf` (Ekspor Laporan PDF), `@media print` Thermal 58mm/80mm & A4 Stiker/ID-1.

---

## Daftar Fitur Lengkap (MVP – Fase 3)

### 1. Fondasi Multi-Cabang, RBAC Hierarkis & Anti-IDOR (MVP Milestone 1–3)
- **Aturan 1 Desa = 1 KOPDES:** Penurunan otomatis `district_id`, `regency_id`, dan `province_id` dari referensi wilayah baku di sisi server.
- **Scoping Wilayah Ketat (`HasRegionScope`):** `admin_nasional`, `admin_provinsi`, `admin_kabupaten`, `admin_cabang`, dan `kasir` hanya dapat melihat/mengelola data sesuai wilayahnya (akses lintas wilayah ditolak HTTP `403`).
- **Katalog Produk Global & Inventori Cabang:** Restock, Stock Adjustment (menolak nilai di bawah `reserved_qty`), dan `stock_movements` *append-only* (immutable).
- **Autentikasi Kasir RFID + PIN 6 Digit:** Dilindungi `RateLimiter` (maks 5x salah -> blokir 15 menit / HTTP `429`), Quick Lock (`F12`), dan Auto-Lock 15 menit (`EnsurePosUnlocked`).

### 2. POS Kasir, Midtrans Snap, Biaya Operasional, SHU & Ekspor (MVP Milestone 4–5 + Modul Operasional)
- **Checkout Atomik Anti Double-Click:** Memakai `idempotency_key` (UUID v4) & `lockForUpdate()` terurut `product_id`.
- **Portabilitas Member Lintas Desa:** Kartu RFID anggota berlaku nasional untuk berbelanja di seluruh cabang KOPDES.
- **Midtrans Snap + Webhook & Rekonsiliasi Terpusat:** Reservasi stok (`reserved_qty`) saat pending, pemotongan stok saat `paid`, pelepasan otomatis saat `expired`/`cancelled`, serta scheduler `kopdes:reconcile-expired-transactions`.
- **Biaya Operasional Cabang & Laporan Laba Rugi / SHU:** Omzet Bruto → (-) Retur → Omzet Bersih → (-) HPP Bersih Snapshot → Laba Kotor → (-) Biaya Operasional → Laba Bersih / Dasar SHU, lengkap dengan Ekspor **Excel (`.xlsx`)** dan **PDF**.

### 3. Harga Khusus Anggota, Shift Kasir & Retur Terpusat (Fase 2 Milestone 6–9)
- **Harga Khusus Member (`member_price`):** Otomatis aktif saat kartu member ditautkan di POS, fallback ke harga normal jika `NULL`, tersimpan sebagai snapshot permanen dan tercetak di struk thermal.
- **Manajemen Shift Kasir (*Blind Count*):** Wajib buka shift sebelum checkout, mutasi kas laci (`cash_in`/`cash_out`), tutup shift *Blind Count* (menyembunyikan kas ekspektasi saat input fisik), cetak struk penutupan shift, dan fitur **Force-Close** oleh Admin.
- **Retur Parsial & Full Void:** Eksklusif Dashboard Admin (dalam batas hari retur), memisahkan barang `restockable` (kembali ke stok & mengoreksi HPP) vs `damaged` (dicatat kerugian tanpa menambah stok), terintegrasi penuh ke Laba Bersih & SHU.

### 4. Onboarding Massal, Cetak Barcode/Kartu, Pengaturan, Audit Trail & Portal Member (Fase 3 Milestone 10–13)
- **Pengaturan Sistem Dinamis (`app_settings`):** Konfigurasi terpusat ber-cache oleh `admin_nasional` (nama lembaga, upload logo, footer & ukuran struk default, batas hari retur, dan 4 alokasi persentase SHU wajib total `100%`).
- **Audit Trail Admin (`activity_logs`):** Pencatatan otomatis & *immutable* untuk aksi sensitif dengan redaksi otomatis `password`/`pin_hash` dan scoping `HasRegionScope`.
- **Import Wilayah & Onboarding Massal Excel/CSV:** Command `php artisan kopdes:import-regions`, unduh template `.xlsx`, serta import **all-or-nothing** Produk, Stok Awal Cabang, dan Member (`card_uid` nullable untuk binding RFID menyusul).
- **Cetak Stiker Barcode & Kartu Member:** Cetak label barcode produk SVG (`jsbarcode`, mode A4 Grid 3 kolom & Thermal 50x30mm) dan kartu member standar ID-1 (`85.6mm x 53.98mm`) satuan/batch dengan QR Code (`qrcode`).
- **Portal Mandiri Member (`/member/login` & `/member`):** Guard sesi terpisah (`member`), login Kode Member / No HP + PIN 6 digit (`RateLimiter`), tampilan *mobile-first* berisi Kartu Member Digital + SVG QR, KPI Belanja (Asal vs Lintas Cabang), Total Hemat Member, Estimasi SHU Berjalan, Struk Digital, dan Ganti PIN Mandiri.
- **Mode Demo Portofolio & Simulator Hardware:** Terisolasi ketat di balik `APP_DEMO_MODE=true` (default `false` -> HTTP `404`), menyediakan Quick-Fill Login, Simulator Scan Barcode & Tap RFID di layar POS, serta `DemoDataSeeder` 3 bulan realistis.

---

## Panduan Instalasi Lokal

### Prasyarat
- PHP **8.3+**, Composer 2.x
- Node.js **20+** & NPM
- MySQL **8.0+** / MariaDB **10.6+**

### Langkah Setup
```bash
# 1. Clone repositori & masuk folder proyek
git clone <url-repositori> kopdes
cd kopdes

# 2. Instal dependensi backend & frontend
composer install
npm install

# 3. Salin konfigurasi lingkungan & generate APP_KEY
cp .env.example .env
php artisan key:generate

# 4. Atur koneksi database MySQL pada .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD)
#    Opsional untuk mencoba Mode Demo Portofolio:
#    APP_DEMO_MODE=true

# 5. Buat symlink storage publik (untuk logo koperasi)
php artisan storage:link

# 6. Jalankan migrasi & build aset frontend
php artisan migrate
npm run build
```

---

## Mengaktifkan Mode Demo Portofolio & `DemoDataSeeder`

Untuk meninjau seluruh ekosistem KOPDES tanpa perangkat USB Barcode Scanner atau USB RFID Reader fisik:

1. Tambahkan atau ubah baris berikut di file `.env` lokal Anda:
   ```env
   APP_DEMO_MODE=true
   ```
2. Jalankan seeder portofolio 3 bulan (*idempotent* — aman dijalankan berulang kali):
   ```bash
   php artisan db:seed --class=DemoDataSeeder
   ```
3. Jalankan server pengembangan:
   ```bash
   php artisan serve
   ```

> **Catatan Keamanan Produksi:** Nilai default `config('kopdes.demo_mode')` adalah `false`. Saat `APP_DEMO_MODE=false`, seluruh panel Quick-Fill Login & Simulator Hardware tidak dirender di antarmuka dan endpoint `GET /demo/pos-samples` otomatis mengembalikan **HTTP 404 Not Found**.

---

## Daftar Akun Demo (Setelah Menjalankan `DemoDataSeeder`)

### 1. Dashboard Admin (`/login`)
| Peran | Email | Kata Sandi | Cakupan Wilayah |
| :--- | :--- | :--- | :--- |
| **Admin Nasional** | `admin.nasional@kopdes.id` | `password` | Seluruh Indonesia + Pengaturan Sistem |
| **Admin Provinsi** | `admin.jatim@kopdes.id` | `password` | Provinsi Jawa Timur |
| **Admin Kabupaten** | `admin.ponorogo@kopdes.id` | `password` | Kabupaten Ponorogo |
| **Admin Cabang (Ngunut)** | `admin.ngunut@kopdes.id` | `password` | KOPDES Desa Ngunut (`KOP-3502-001`) |
| **Admin Cabang (Cekok)** | `admin.cekok@kopdes.id` | `password` | KOPDES Desa Cekok (`KOP-3502-002`) |
| **Admin Cabang (Balerejo)** | `admin.balerejo@kopdes.id` | `password` | KOPDES Desa Balerejo (`KOP-3519-001`) |
| **Admin Cabang (Biting)** | `admin.biting@kopdes.id` | `password` | KOPDES Desa Biting (`KOP-3312-001`) |

### 2. Terminal Kasir POS (`/pos/login`)
| Kasir | Cabang KOPDES | UID Kartu RFID | PIN (6 Digit) |
| :--- | :--- | :--- | :--- |
| **Kasir Ngunut 1** | KOPDES Desa Ngunut | `0008493873` | `123456` |
| **Kasir Cekok 1** | KOPDES Desa Cekok | `0008305215` | `123456` |
| **Kasir Balerejo 1** | KOPDES Desa Balerejo | `0008510003` | `123456` |
| **Kasir Biting 1** | KOPDES Desa Biting | `0008510004` | `123456` |

### 3. Portal Mandiri Member (`/member/login`)
| Anggota | KOPDES Asal | Kode Member | Nomor HP | UID RFID | PIN |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Ari Setiyawan** | KOPDES Desa Ngunut | `MBR-KOP-3502-001-00001` | `081234567890` | `0009329774` | `123456` |
| **Cintya Bella** | KOPDES Desa Cekok | `MBR-KOP-3502-002-00001` | `081234567891` | `0009434179` | `123456` |
| **Siti Aminah** *(Belum Binding RFID)* | KOPDES Desa Ngunut | `MBR-KOP-3502-001-00003` | `081234560103` | *(kosong)* | `123456` |

---

## Menjalankan Pengujian Otomatis

Seluruh fitur dari Milestone 1 hingga Milestone 13 dilengkapi Feature Test terotomatisasi (PHPUnit + SQLite in-memory):

```bash
# Menjalankan seluruh test suite (Milestone 1-13 + Biaya Operasional/Ekspor/SHU)
php artisan test

# Menjalankan build produksi Vite
npm run build
```

---

## Lisensi

Proyek ini dikembangkan sebagai implementasi sistem koperasi desa modern berbasis Laravel & Vue.js 3.
