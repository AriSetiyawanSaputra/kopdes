# AGENTS.md — Aturan Teknis Ketat Proyek KOPDES

Dokumen ini adalah **aturan teknis mengikat** yang WAJIB dipatuhi oleh setiap AI Agent maupun developer pada setiap sesi coding di repositori **KOPDES (Koperasi Desa Merah Putih)**. Seluruh spesifikasi domain merujuk pada [PRD.md](./PRD.md) sebagai *Single Source of Truth*, dan urutan pengerjaan mengikuti [TASKS.md](./TASKS.md).

---

## 1. Tech Stack & Struktur Proyek

- **Backend:** Laravel 13.x (PHP 8.3+), Eloquent ORM, Laravel Policies, Form Requests, RateLimiter, Scheduler, Queue (`database` driver).
- **Frontend:** Inertia.js (`@inertiajs/vue3`) + Vue 3 (**WAJIB Composition API dengan `<script setup>`**) + Tailwind CSS + Ziggy (`route()`).
- **Database:** MySQL 8.0+ / MariaDB 10.6+ (InnoDB, transaksi ACID, `SELECT ... FOR UPDATE`, kolom `JSON`, constraint `UNIQUE` komposit).
- **Payment Gateway:** Midtrans Snap (Sandbox) + HTTP Webhook Notification.
- **Konvensi Struktur:**
  - `app/Models/` & `app/Models/Concerns/` — Model Eloquent, relasi, casts, dan trait `HasRegionScope`.
  - `app/Http/Requests/` — Semua validasi input wajib dipisahkan ke Form Request (dilarang `$request->validate()` langsung di Controller untuk operasi tulis/filter kompleks).
  - `app/Http/Controllers/` — Controller harus tipis (*thin controllers*); delegasikan logika bisnis, transaksi stok, checkout, Midtrans, dan agregasi laporan ke `app/Services/`.
  - `app/Policies/` — Otorisasi RBAC dan proteksi anti-IDOR.
  - `app/Support/` — Utility murni (contoh: `CardUidNormalizer`).
  - `resources/js/Pages/`, `resources/js/Components/`, `resources/js/Composables/`, `resources/js/Utils/` — Seluruh kode Vue 3 menggunakan `<script setup>`.

---

## 2. Aturan Database, Migrasi & Eloquent

1. **Dilarang Mengubah `.env` atau Menghapus Migrasi Lama:**
   - **JANGAN** mengedit file `.env` tanpa izin eksplisit dari pengguna.
   - **JANGAN** menghapus atau menimpa file migrasi bawaan (`0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`) tanpa izin.
   - Untuk menyesuaikan tabel `users` (menjadikan `email` & `password` nullable, menambah `role`, `province_id`, `regency_id`, `branch_id`, `card_uid`, `pin_hash`, `is_active`), **wajib membuat file migrasi baru** (`alter_users_table_...`) setelah tabel wilayah dan `branches` dibuat.
2. **Wajib Eloquent Relationship:**
   - Selalu definisikan dan gunakan Eloquent Relationships (`belongsTo`, `hasMany`, `hasOne`, dll.) beserta eager loading (`with()`) untuk mencegah N+1 query. Hindari raw SQL kecuali agregasi laporan yang tidak bisa diekspresikan bersih dengan Query Builder, dan tetap wajib menggunakan parameter binding (anti SQL Injection).
3. **Aturan Mutlak 1 Desa = 1 KOPDES:**
   - Kolom `branches.village_id` wajib `UNIQUE` di level database dan tervalidasi di Form Request.
   - Saat menyimpan/memperbarui cabang, `district_id`, `regency_id`, dan `province_id` **wajib diturunkan di sisi server** dari `villages -> districts -> regencies -> provinces`; jangan pernah mempercayai input wilayah terpisah dari frontend.
4. **Aturan Mutlak Snapshot Data & Laporan Keuangan:**
   - Saat transaksi dibuat di `CheckoutService`, salin `name`, `barcode`, `unit`, `selling_price`, dan `cost_price` dari `products` ke kolom `*_snapshot` pada `transaction_items`, hitung `total_cogs` pada `transactions`, dan salin `members.home_branch_id` ke `transactions.member_home_branch_id`.
   - Seluruh query laporan keuangan & laba kotor **DILARANG** membaca `selling_price` atau `cost_price` dari tabel `products`; wajib menggunakan `transactions.total_amount - transactions.total_cogs` (dan `transaction_items.*_snapshot`).
   - Hanya transaksi dengan `payment_status = 'paid'` yang dihitung dalam omzet, laba kotor, dan rekap belanja member.
5. **Audit Log Stok Append-Only:**
   - Tabel `stock_movements` bersifat **immutable** (tanpa kolom `updated_at`, set `public const UPDATED_AT = null;`). Dilarang menyediakan fitur atau endpoint Edit/Delete untuk `stock_movements`.
6. **SoftDeletes Produk:**
   - Model `Product` wajib menggunakan `SoftDeletes` agar referensi `transaction_items` historis tetap terjaga.

---

## 3. Aturan Transaksi Database & Pessimistic Locking (Anti Stok Minus & Anti Deadlock)

1. **Wajib `DB::transaction()` + `lockForUpdate()`:**
   - Seluruh mutasi pada `branch_stocks`, `stock_movements`, `transactions`, dan `transaction_items` (restock, stock adjustment, checkout tunai, checkout Midtrans, rekonsiliasi webhook, cancel, scheduler) **WAJIB** dibungkus dalam `DB::transaction()` dengan row-level locking `lockForUpdate()`.
2. **Pencegahan Deadlock Multi-Item:**
   - Sebelum mengunci baris `branch_stocks` untuk keranjang dengan banyak produk, **selalu urutkan `product_id` secara menaik (`sort()->values()`)** lalu kunci sekaligus dengan `->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id')`.
3. **Rumus Ketersediaan Stok:**
   - `available_stock = quantity - reserved_qty`. Nilai ini tidak pernah boleh negatif.
   - Pada `stock_adjustment`, tolak permintaan jika `actual_quantity < reserved_qty`.
4. **Matriks Mutasi Stok:**
   - Checkout Tunai (`cash` → `paid`): `quantity -= qty`, tulis `stock_movements` (`type = 'sale'`).
   - Checkout Midtrans dibuat (`pending`): `reserved_qty += qty` (`quantity` belum berkurang, belum tulis `stock_movements`).
   - Midtrans lunas (`pending` → `paid`): `quantity -= qty`, `reserved_qty -= qty`, tulis `stock_movements` (`type = 'sale'`).
   - Midtrans `expired` / `cancelled` / `failed`: `reserved_qty -= qty` (`quantity` tetap).
5. **Anti Double-Click (`idempotency_key`):**
   - Setiap checkout wajib menyertakan `idempotency_key` (UUID v4). Cek duplikasi di `CheckoutService` sebelum mengunci stok dan tangkap `UniqueConstraintViolationException` sebagai pengaman lapis kedua agar request duplikat mengembalikan transaksi yang sama tanpa memotong/mereservasi stok ulang.

---

## 4. Aturan Scoping Wilayah & RBAC (Anti-IDOR)

1. **Trait `HasRegionScope` Wajib Diterapkan:**
   - Setiap query pada `Branch`, `BranchStock`, `StockMovement`, `Transaction`, dan `User` wajib melewati scope `->forUser($actor)`:
     - `admin_nasional`: tanpa filter wilayah.
     - `admin_provinsi`: filter `province_id = $actor->province_id`.
     - `admin_kabupaten`: filter `regency_id = $actor->regency_id`.
     - `admin_cabang` dan `kasir`: filter `branch_id = $actor->branch_id`.
2. **Validasi Parameter Wilayah (Tolak dengan HTTP 403):**
   - Setiap route admin yang menerima `province_id`, `regency_id`, atau `branch_id` (baik route model binding maupun query filter laporan) wajib divalidasi melalui **Laravel Policy** dan **Form Request**.
   - Jika user meminta wilayah di luar scope-nya, backend **WAJIB mengembalikan HTTP `403 Forbidden`** (bukan sekadar mengabaikan filter).
3. **Pengecualian Terkontrol Portabilitas Member di POS:**
   - Hanya endpoint `GET /pos/members/lookup?card_uid=...` yang diizinkan membaca tabel `members` lintas `home_branch_id` selama `status = 'active'`, dan hanya boleh mengembalikan field minimal (`id`, `member_code`, `name`, `home_branch_id`, `home_branch_name`) tanpa `nik` atau data sensitif lainnya.
4. **Hierarki Pengelolaan User & Keamanan Kredensial:**
   - `admin_provinsi` hanya mengelola `admin_kabupaten` di provinsinya; `admin_kabupaten` hanya mengelola `admin_cabang` di kabupatennya; `admin_cabang` hanya mengelola `kasir` dan `member` di cabangnya.
   - `pin_hash` wajib di-hash (`Hash::make()`), dimasukkan ke `$hidden` / `#[Hidden]` pada model `User`, dan **tidak pernah dikirim ke frontend**.
   - `card_uid` wajib dinormalisasi (`preg_replace('/[^A-Za-z0-9]/', '', trim($raw))` → `strtoupper()`) dan wajib unik secara global di lintas tabel `users` dan `members`.
   - Login/unlock kasir wajib memakai `RateLimiter` (`'kasir-pin:' . $normalizedUid . '|' . $request->ip()`, maks 5x gagal, decay 900 detik, balas HTTP `429`).
   - Kasir atau cabang dengan `is_active = false` tidak boleh login atau melakukan checkout.

---

## 5. Aturan Webhook Midtrans & CSRF

1. **Pengecualian CSRF Wajib di `bootstrap/app.php`:**
   - Daftarkan pengecualian CSRF untuk webhook Midtrans di `bootstrap/app.php` di dalam `->withMiddleware(...)`:
     ```php
     $middleware->validateCsrfTokens(except: [
         'api/webhooks/midtrans',
     ]);
     ```
2. **Keamanan & Idempotensi Webhook (`POST /api/webhooks/midtrans`):**
   - Wajib memverifikasi `signature_key` menggunakan `hash_equals()` terhadap `hash('sha512', $orderId . $statusCode . $grossAmount . config('services.midtrans.server_key'))`.
   - Jika signature tidak valid, tetap catat ke `payment_logs` dengan `signature_verified = false`, lalu balas HTTP `403`.
   - Verifikasi kesesuaian `(float) $payload['gross_amount'] === (float) $transaction->total_amount`.
   - Di dalam `DB::transaction()`, kunci baris `Transaction` dengan `lockForUpdate()`. Jika `payment_status !== 'pending'`, hentikan pemrosesan stok dan langsung balas HTTP `200 OK` (idempotent).
   - Webhook, tombol manual "Cek Status Pembayaran", dan command Scheduler `kopdes:reconcile-expired-transactions` **WAJIB memanggil service rekonsiliasi tunggal** (`MidtransReconciliationService`) agar tidak ada duplikasi logika mutasi stok.

---

## 6. Aturan Frontend Vue 3, POS Hardware & Cetak Thermal

1. **Vue 3 `<script setup>`:** Semua komponen & halaman Vue wajib menggunakan `<script setup>` (dilarang Options API).
2. **Isolasi Input HID Keyboard Wedge (Barcode vs. RFID):**
   - Input barcode di `Pos/Index.vue` hanya aktif dan *auto-focus* ketika `activeModal === null` dan kursor tidak sedang di input kuantitas/nominal tunai.
   - Input kartu RFID hanya diterima di dalam modal khusus (`MemberLookupModal.vue` via shortcut `F2` dan `RfidCaptureModal.vue`), dengan debounce 350ms dan fungsi `normalizeCardUid`.
3. **Idle Auto-Lock & Quick Lock (`F12`):**
   - Composable `useIdleLock` (15 menit) memicu `POST /pos/lock` dan membuka `PosLockModal.vue`. Middleware backend `EnsurePosUnlocked` wajib menolak seluruh endpoint checkout jika `session('pos_locked') === true`.
4. **Cetak Struk Thermal (`@media print`):**
   - Gunakan komponen khusus `ReceiptThermal.vue` untuk lebar 58mm/80mm (`localStorage`) dan pastikan elemen navigasi/modal tidak ikut tercetak.

---

## 7. Alur Kerja Agent di Setiap Sesi Coding

1. **Ikuti Urutan `TASKS.md`:** Kerjakan tepat **1 task** per langkah sesuai lapisan kerjanya. Jangan melompat milestone sebelum Definition of Done milestone sebelumnya terpenuhi.
2. **Tandai Progres:** Setelah sebuah task selesai dan terverifikasi (lint/build/test sesuai lapisannya), perbarui checkbox `- [ ]` menjadi `- [x]` di `TASKS.md`.
3. **Bahasa & Kualitas Kode:**
   - Pesan validasi, label UI, dan error untuk pengguna menggunakan **Bahasa Indonesia** yang jelas.
   - Nama kelas, method, tabel, kolom, dan route menggunakan **Bahasa Inggris** yang konsisten dengan kamus data di `PRD.md`.
   - Jalankan `php artisan test` untuk memverifikasi Feature Test di setiap akhir milestone sebelum melanjutkan ke milestone berikutnya.
