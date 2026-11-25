# PRD Phase 2: Manajemen Shift Kasir, Retur & Pembatalan Transaksi, dan Harga Khusus Member

**Versi Dokumen:** 2.0 (Phase 2 Baseline)  
**Status:** Terkunci (Single Source of Truth untuk Implementasi Fase 2)  
**Prasyarat Baseline:** MVP Milestone 1–5 (`PRD.md` & `TASKS.md`), Modul Biaya Operasional (`operational_expenses`), Kalkulasi SHU, serta Ekspor Laporan (Excel & PDF) telah selesai dan seluruh test lulus.  
**Tech Stack:** Laravel (PHP 8.2+), MySQL 8.x / MariaDB 10.6+, Inertia.js + Vue.js 3 (`<script setup>`) + Tailwind CSS, Midtrans Snap + Webhook, Queue & Scheduler (`database` driver), Thermal Print (`@media print` 58mm/80mm).

---

## 1. Ringkasan Eksekutif, Prasyarat Baseline & Batasan Fase 2

### 1.1 Ringkasan Eksekutif
Fase 2 memperdalam kontrol operasional harian dan insentif keanggotaan pada **Sistem Kasir & Manajemen KOPDES (Koperasi Desa Merah Putih)** tanpa mengubah arsitektur hierarki wilayah (Nasional -> Provinsi -> Kabupaten/Kota -> Cabang KOPDES Desa) maupun prinsip portabilitas member lintas cabang.

Tiga kapabilitas utama yang ditambahkan pada Fase 2 adalah:
1. **Manajemen Shift Kasir:** Pengendalian laci uang tunai kasir melalui siklus Buka Shift -> Mutasi Kas Laci -> Tutup Shift dengan metode **Blind Count**, pemblokiran tutup shift jika masih terdapat transaksi Midtrans `pending`, serta dukungan multi-shift paralel per cabang.
2. **Retur & Pembatalan Transaksi (Partial Return & Full Void):** Mekanisme pengembalian barang berbasis item untuk transaksi berstatus `paid` (batas maksimal 7 hari), dieksekusi terpusat oleh Admin Cabang melalui Dashboard dengan pemisahan kondisi barang (**Layak Jual** vs **Rusak/Cacat**) dan pencatatan refund manual (**Tunai** atau **Transfer Manual**).
3. **Harga Khusus Anggota Member:** Skema harga eksplisit per produk (`products.member_price`) yang berlaku lintas cabang secara nasional, dengan kalkulasi instan di layar POS Vue, penyimpanan snapshot diskon pada transaksi, dan tampilan **"Total Hemat Member"** pada POS maupun struk thermal.

### 1.2 Batasan Ruang Lingkup Fase 2 (In-Scope vs. Out-of-Scope)

| Kategori | In-Scope (Wajib Dibangun di Fase 2) | Out-of-Scope (Ditunda ke Fase 3) |
| :--- | :--- | :--- |
| **Shift Kasir** | Buka shift (`opening_cash`), aturan 1 kasir maksimal 1 shift `open` (cabang boleh paralel), mutasi kas laci (`cash_in`/`cash_out`), tutup shift **Blind Count**, pemblokiran tutup shift bila ada `pending` Midtrans, cetak struk ringkasan tutup shift (thermal), audit & force-close shift oleh Admin Cabang. | Penjadwalan roster shift otomatis; integrasi laci kas (*cash drawer*) via ESC/POS serial port; rekap pecahan uang kertas/koin (denomination counter). |
| **Retur & Void** | Retur parsial & Full Void berbasis item untuk transaksi `paid` maksimal 7 hari; otorisasi oleh `admin_cabang` / `admin_nasional` di Dashboard; kondisi barang `restockable` (menambah stok + log `void_return`) vs `damaged` (tanpa menambah stok); refund `cash_refund` (sumber kas cabang atau laci shift aktif) & `manual_transfer`; koreksi otomatis Omzet, HPP, Laba Kotor, Laba Bersih, SHU, & Ekspor. | Integrasi Midtrans Refund API otomatis; penukaran barang langsung (*exchange*) dalam satu formulir; nota kredit / *store credit* anggota; pengajuan retur mandiri dari layar kasir. |
| **Harga Member** | Kolom `member_price` nullable pada master `products` (dikelola `admin_nasional`); berlaku nasional lintas cabang saat kartu member aktif di-tap; validasi `member_price` tidak melebihi `selling_price` + peringatan visual bila di bawah `cost_price`; kalkulasi ulang keranjang Vue real-time; snapshot harga reguler & diskon; tampilan "Total Hemat Member" di POS & struk. | Diskon persentase bertingkat per kategori atau level keanggotaan; harga promo terbatas waktu (*flash sale* / periode tanggal); harga grosir bertingkat berdasarkan kuantitas (*tiered pricing*). |

### 1.3 Prinsip Backward Compatibility
Seluruh perubahan database dan layanan pada Fase 2 wajib menjaga keutuhan data historis dari MVP:
1. Kolom baru pada `transactions` (`cashier_shift_id`, `return_status`, `regular_subtotal`, `member_discount_total`) dan `transaction_items` (`regular_price_snapshot`, `member_discount_snapshot`, `is_member_price_applied`) menggunakan nilai *default* aman atau *nullable* dan disertai migrasi *backfill* untuk baris transaksi lama.
2. Nilai `transactions.payment_status` untuk transaksi lunas tetap `'paid'` meski telah mengalami retur parsial atau penuh; status retur dilacak terpisah pada kolom `transactions.return_status` (`none`, `partial`, `full`) agar seluruh query dan relasi pembayaran yang sudah ada tetap konsisten.

---

## 2. Pembaruan Hak Akses (RBAC) & Aturan Scoping Wilayah Fase 2

Keterangan: **C** = Create, **R** = Read, **U** = Update, **X** = Eksekusi Aksi Khusus, **-** = Dilarang (HTTP 403).

| Modul / Sumber Daya Fase 2 | `admin_nasional` | `admin_provinsi` | `admin_kabupaten` | `admin_cabang` | `kasir` |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Buka Shift, Mutasi Kas Laci, Tutup Shift (POS)** | - | - | - | - | C & X (Shift miliknya sendiri di cabangnya) |
| **Riwayat Shift, Laporan Selisih Kas & Force-Close Shift** | R & X (Semua cabang) | R (Provinsi) | R (Kabupaten) | R & X (Cabang sendiri) | R (Ringkasan shift sendiri setelah tutup) |
| **Eksekusi Retur Parsial / Full Void Transaksi `paid`** | C & R (Semua cabang) | R (Provinsi) | R (Kabupaten) | C & R (Transaksi `branch_id` cabangnya sendiri) | - |
| **Pengelolaan `member_price` di Master Produk** | CRUD | R | R | R | R (Otomatis di POS saat lookup barcode) |
| **Laporan Keuangan, SHU & Ekspor (Terkoreksi Retur)** | R (Nasional -> Cabang) | R (Provinsi -> Cabang) | R (Kabupaten -> Cabang) | R (Cabang sendiri) | - |

### Aturan Scoping Anti-IDOR Fase 2
1. **Shift Kasir:** Model `CashierShift` menggunakan trait `HasRegionScope`. `kasir` hanya dapat memodifikasi (mutasi kas laci / tutup shift) record `cashier_shifts` di mana `cashier_id === auth()->id()` dan `branch_id === auth()->user()->branch_id`. `admin_cabang` hanya dapat melihat atau melakukan *force-close* pada shift di cabangnya sendiri (`branch_id`).
2. **Retur Transaksi:** Model `TransactionReturn` menggunakan trait `HasRegionScope`. `admin_cabang` hanya dapat membuat retur untuk transaksi milik cabangnya (`transaction.branch_id === auth()->user()->branch_id`). Jika `cash_source = 'active_shift'`, sistem memvalidasi bahwa `refund_cashier_shift_id` yang dipilih berstatus `open` dan berada di `branch_id` yang sama.
3. **Portabilitas Harga Member:** Validasi harga khusus member di `CheckoutService` tidak membatasi `member.home_branch_id`; seluruh member dengan `status = 'active'` dari cabang mana pun berhak atas `products.member_price`.

---

## 3. Alur Pengguna Utama (User Flows)

### 3.1 Alur Manajemen Shift Kasir (Buka Shift -> Mutasi Kas -> Tutup Shift Blind Count)

```text
[Kasir Login di /pos/login (Tap Kartu RFID + PIN 6 Digit)]
   │
   ▼
[Masuk ke /pos -> Cek apakah kasir memiliki CashierShift status = 'open']
   ├── Belum ada shift open:
   │     1. Tampilkan Modal Wajib "Buka Shift Kasir" (blokir layar POS)
   │     2. Kasir input Modal Awal Laci (opening_cash >= 0) + catatan opsional
   │     3. POST /pos/shifts/open -> DB simpan CashierShift (status='open', opened_at=now())
   │     4. Layar POS terbuka; seluruh checkout otomatis tertaut ke cashier_shift_id ini
   │
   └── Sudah ada shift open:
         1. Langsung masuk layar POS aktif
         2. Selama shift berjalan, kasir dapat membuka Modal "Kas Laci" (Shortcut F4):
            ├── Kas Masuk (cash_in): mis. tambah uang receh kembalian Rp200.000
            └── Kas Keluar (cash_out): mis. setor tunai ke brankas admin Rp1.500.000
            -> POST /pos/shifts/{shift}/cash-movements (catatan wajib diisi)
         3. Saat pergantian shift, kasir klik "Tutup Shift" (Shortcut F10):
            ├── Sistem cek transaksi Midtrans status='pending' pada shift ini
            │     ├── Masih ada pending (> 0) -> BLOKIR tutup shift, arahkan ke Drawer Pending
            │     └── Tidak ada pending (0) -> Tampilkan Modal "Tutup Shift (Blind Count)"
            ├── Layar TIDAK menampilkan expected_closing_cash
            ├── Kasir menghitung uang fisik di laci lalu input actual_closing_cash + catatan
            ├── POST /pos/shifts/{shift}/close (dalam DB::transaction + lockForUpdate()):
            │     - Hitung total cash_sales, midtrans_sales, cash_in, cash_out, shift_cash_refunds
            │     - Hitung expected_closing_cash dan variance = actual - expected
            │     - Kunci shift (status='closed', closed_at=now())
            └── Tampilkan Ringkasan Penutupan Shift (termasuk Selisih) + Tombol Cetak Struk Shift
                -> Kasir dapat logout atau buka shift baru
```

### 3.2 Alur Retur Parsial & Full Void (Terpusat di Dashboard Admin Cabang)

```text
[Pembeli datang membawa struk/barang retur ke KOPDES]
   │
   ▼
[Admin Cabang login Dashboard -> Buka menu Riwayat Transaksi -> Cari Invoice]
   │ 1. Buka Detail Transaksi -> klik tombol "Proses Retur / Void"
   ▼
[Validasi Awal Server & UI]
   ├── payment_status != 'paid' -> Tombol nonaktif / tolak 422
   ├── return_status == 'full' -> Tombol nonaktif / tolak 422 ("Seluruh item sudah diretur")
   └── paid_at > 7 hari kalender -> Tolak 422 ("Melewati batas retur 7 hari")
   │
   ▼
[Form Retur pada Dashboard Admin Cabang]
   1. Pilih item yang dikembalikan (atau klik "Pilih Semua / Full Void")
   2. Untuk setiap item terpilih, isi:
      - qty_return (1 s.d. qty_tersisa_yang_belum_diretur)
      - condition: "Layak Jual (Restock)" atau "Rusak / Cacat (Tidak Restock)"
   3. Isi alasan retur wajib (reason)
   4. Pilih Metode Pengembalian Dana (refund_method):
      ├── cash_refund (Tunai):
      │     Pilih sumber uang (cash_source):
      │     - branch_cash (Kas/Brankas Admin Cabang — tidak mengubah laci kasir)
      │     - active_shift (Pilih Shift Kasir 'open' di cabang ini — mengurangi ekspektasi laci shift)
      └── manual_transfer (Transfer Bank / E-Wallet Manual):
            Wajib isi refund_reference (No. Referensi / Bank tujuan)
   │
   ▼
[POST /returns -> TransactionReturnService dalam DB::transaction()]
   1. lockForUpdate() pada transactions, transaction_items, dan branch_stocks (urut product_id ASC)
   2. Validasi ulang sisa kuantitas retur tiap item agar bebas race condition
   3. Hitung nilai retur per item menggunakan unit_price_snapshot & cost_price_snapshot transaksi asli
   4. Simpan transaction_returns (return_number unik, status='completed') & transaction_return_items
   5. Untuk setiap item dengan condition == 'restockable':
      - branch_stocks.quantity += qty_return
      - Insert stock_movements (type='void_return', qty_change=+qty_return, reference=TransactionReturn)
   6. Untuk item dengan condition == 'damaged':
      - Stok jual TIDAK ditambah; nilai HPP tetap dicatat di damaged_cogs untuk transparansi laporan
   7. Perbarui transactions.return_status ('partial' atau 'full')
   8. Commit -> Redirect ke Detail Retur + Opsi Cetak Bukti Retur
```

### 3.3 Alur Harga Khusus Anggota Member di Layar POS

1. **Pengaturan Pusat (`admin_nasional`):** Pada form Tambah/Edit Produk (`Products/Form.vue`), `admin_nasional` mengisi `selling_price` (Harga Normal) dan secara opsional mengisi `member_price` (Harga Khusus Member). Jika `member_price` lebih rendah dari `cost_price`, tampilkan banner peringatan kuning (*non-blocking*): *"Perhatian: Harga Member lebih rendah dari HPP (potensi jual rugi)"*.
2. **Pemindaian di Layar POS:** Saat kasir memindai barcode, endpoint `GET /pos/products/barcode/{barcode}` mengembalikan `selling_price` dan `member_price`.
3. **Mode Non-Member (`selectedMember === null`):** Seluruh item di keranjang menggunakan `selling_price`.
4. **Tap Kartu Member (`F2`):** Begitu kartu member aktif dari cabang mana pun berhasil di-tap:
   - State Vue `Pos/Index.vue` segera menghitung ulang seluruh baris keranjang: item yang memiliki `member_price !== null` beralih memakai `member_price`, menampilkan harga normal dicoret di samping harga member, dan menampilkan badge **"Hemat Rp X.XXX"** per baris serta total **"Total Hemat Member"** di ringkasan keranjang.
   - Jika kasir melepas tautan member sebelum checkout, seluruh item langsung kembali ke `selling_price` dan "Total Hemat Member" menjadi `Rp 0`.
5. **Verifikasi Server saat Checkout (`CheckoutService`):** Server mengabaikan harga dari frontend, membaca `products.selling_price` dan `products.member_price` langsung dari database, memverifikasi `member_id` berstatus `active`, lalu menyimpan snapshot `regular_price_snapshot`, `unit_price_snapshot`, `member_discount_snapshot`, `regular_subtotal`, `member_discount_total`, dan `total_amount`.
6. **Cetak Struk Thermal:** Jika `member_discount_total > 0`, `ReceiptThermal.vue` mencetak informasi harga hemat member di bagian ringkasan struk.

---

## 4. Spesifikasi Fungsional & Acceptance Criteria per Modul

### Modul 6: Manajemen Shift Kasir (`FR-P2-01` s.d. `FR-P2-04`)

* **`FR-P2-01` Buka Shift Kasir & Kewajiban Shift Aktif di POS**
  - **Deskripsi:** Kasir wajib memiliki tepat satu shift berstatus `open` sebelum dapat memproses transaksi di POS.
  - **Acceptance Criteria:**
    1. Satu kasir hanya boleh memiliki maksimal 1 record `cashier_shifts` berstatus `open` secara bersamaan (dijaga di service dan divalidasi dengan `lockForUpdate()`).
    2. Satu cabang diperbolehkan memiliki lebih dari 1 shift berstatus `open` dari kasir berbeda secara bersamaan.
    3. Middleware `EnsureActiveShift` menolak `POST /pos/checkout` dengan HTTP `422` jika kasir tidak memiliki shift berstatus `open`.
    4. Input `opening_cash` wajib numerik $\ge 0$.

* **`FR-P2-02` Mutasi Kas Laci Selama Shift (`cash_in` & `cash_out`)**
  - **Deskripsi:** Kasir dapat mencatat uang masuk (tambah modal receh) dan uang keluar (setor ke brankas admin) selama shift berstatus `open`.
  - **Acceptance Criteria:**
    1. Dapat diakses cepat dari layar POS melalui tombol UI atau shortcut `F4` (Modal `ShiftCashMovementModal.vue`).
    2. Kolom `type` (`cash_in` / `cash_out`), `amount` ($> 0$), dan `notes` wajib diisi.
    3. Data tersimpan di `shift_cash_movements` (bersifat *append-only*, tidak dapat diedit/dihapus) dan **tidak pernah** masuk ke tabel `operational_expenses` maupun mengurangi Laba Bersih.

* **`FR-P2-03` Tutup Shift dengan Blind Count & Pemblokiran Pending Midtrans**
  - **Deskripsi:** Rekonsiliasi laci tunai di akhir shift tanpa membocorkan ekspektasi sistem sebelum kasir memasukkan hasil hitung fisik.
  - **Acceptance Criteria:**
    1. Jika terdapat minimal 1 transaksi dengan `cashier_shift_id = shift.id` dan `payment_status = 'pending'`, UI menonaktifkan submit dan backend menolak `POST /pos/shifts/{shift}/close` dengan HTTP `422` beserta daftar nomor invoice yang masih `pending`.
    2. Endpoint `GET /pos` dan modal tutup shift (`CloseShiftModal.vue`, shortcut `F10`) **tidak mengirim** nilai `expected_closing_cash`, `cash_sales_total`, maupun `variance` selama shift masih berstatus `open` (Blind Count sejati di sisi backend & frontend).
    3. Setelah kasir mengirim `actual_closing_cash` ($\ge 0$), backend mengunci shift (`lockForUpdate()`), menghitung seluruh agregasi transaksi, menyimpan `expected_closing_cash`, `actual_closing_cash`, dan `variance`, mengubah status ke `closed`, lalu mengembalikan ringkasan penutupan lengkap untuk ditampilkan dan dicetak via struk thermal (`ShiftClosingReceipt.vue`).

* **`FR-P2-04` Monitoring Shift & Force-Close oleh Admin Cabang**
  - **Deskripsi:** Halaman Dashboard Admin untuk memantau shift aktif, riwayat shift, selisih kas (*short/over*), serta menutup paksa shift jika kasir berhalangan hadir/lupa tutup shift.
  - **Acceptance Criteria:**
    1. Halaman `Shifts/Index.vue` menampilkan filter cabang (sesuai scope wilayah), status (`open`/`closed`), rentang tanggal, nama kasir, modal awal, ekspektasi tunai, fisik aktual, dan selisih kas (berwarna merah jika selisih $< 0$, hijau jika $= 0$, kuning jika $> 0$).
    2. `admin_cabang` (pada cabangnya) dan `admin_nasional` dapat melakukan **Force-Close Shift** pada shift berstatus `open` (dengan syarat tidak ada transaksi `pending` di shift tersebut), menginput `actual_closing_cash`, mengisi `closing_notes` wajib, serta mencatat `closed_by_user_id = auth()->id()`.

### Modul 7: Retur & Pembatalan Transaksi (`FR-P2-05` s.d. `FR-P2-08`)

* **`FR-P2-05` Validasi Kelayakan Retur / Full Void**
  - **Deskripsi:** Pengendalian transaksi mana yang boleh diretur atau dibatalkan.
  - **Acceptance Criteria:**
    1. Hanya transaksi dengan `payment_status = 'paid'` dan `return_status` bukan `'full'` yang dapat diproses.
    2. Selisih waktu antara `paid_at` dan `now()` tidak boleh melebihi batas `config('kopdes.return_max_days', 7)` hari kalender (dihitung inklusif hingga akhir hari ke-7).
    3. Total akumulasi kuantitas retur per `transaction_item_id` dari seluruh `transaction_returns` berstatus `completed` tidak boleh melebihi `transaction_items.qty`.

* **`FR-P2-06` Eksekusi Retur Item, Kondisi Barang & Pengembalian Stok**
  - **Deskripsi:** Admin Cabang memilih satu atau seluruh item untuk diretur beserta kondisi fisiknya.
  - **Acceptance Criteria:**
    1. Menyediakan tombol pintas **"Pilih Semua (Full Void)"** di UI form retur (`Returns/Create.vue`) yang otomatis mengisi seluruh sisa kuantitas item.
    2. Item berkondisi `restockable` secara atomik menambah `branch_stocks.quantity` dan menulis tepat 1 baris pada `stock_movements` dengan `type = 'void_return'`, `qty_change = +qty_returned`, `reference_type = App\Models\TransactionReturn`, dan `reference_id = $return->id`.
    3. Item berkondisi `damaged` tidak mengubah `branch_stocks.quantity` dan tidak menulis ke `stock_movements`, tetapi tetap tercatat pada `transaction_return_items` beserta `damaged_cogs`.
    4. Nilai pengembalian dana per item selalu menggunakan `transaction_items.unit_price_snapshot * qty_returned` (bukan harga produk saat ini).

* **`FR-P2-07` Pencatatan Refund Manual & Dampak Laci Shift**
  - **Deskripsi:** Mencatat sumber pengembalian dana ke pembeli secara akurat.
  - **Acceptance Criteria:**
    1. Jika `refund_method = 'cash_refund'`, admin wajib memilih `cash_source`: `'branch_cash'` atau `'active_shift'`.
    2. Jika `cash_source = 'active_shift'`, admin wajib memilih `refund_cashier_shift_id` dari daftar shift yang sedang `open` di cabang tersebut; nominal `total_refund_amount` otomatis mengurangi `expected_closing_cash` pada shift terpilih saat shift ditutup.
    3. Jika `refund_method = 'manual_transfer'`, kolom `refund_reference` wajib diisi (minimal 3 karakter) dan `cash_source` bernilai `null`.

* **`FR-P2-08` Riwayat Retur & Bukti Retur**
  - **Deskripsi:** Jejak audit lengkap seluruh pengembalian barang dan pembatalan transaksi.
  - **Acceptance Criteria:**
    1. Halaman `Returns/Index.vue` dan `Returns/Show.vue` menampilkan Nomor Retur (`RTN-{BRANCH_CODE}-{YYYYMMDD}-{SEQ4}`), Nomor Invoice asal, Admin pengeksekusi, alasan, daftar item + kondisi, total refund, metode refund, serta tombol cetak bukti retur.
    2. Data retur bersifat *immutable* (tidak ada fitur Edit atau Delete pada retur yang sudah `completed`).

### Modul 8: Harga Khusus Anggota Member (`FR-P2-09` s.d. `FR-P2-10`)

* **`FR-P2-09` Pengelolaan `member_price` di Master Produk**
  - **Deskripsi:** `admin_nasional` menetapkan harga khusus anggota pada katalog terpusat.
  - **Acceptance Criteria:**
    1. `member_price` bersifat opsional (`nullable`). Jika diisi, validasi mewajibkan `member_price` $\ge 0$ dan `member_price` $\le$ `selling_price`.
    2. Di `Products/Form.vue`, jika `member_price` diisi lebih kecil dari `cost_price`, tampilkan peringatan visual tanpa memblokir penyimpanan.
    3. Di `Products/Index.vue`, tampilkan kolom Harga Normal dan Harga Member (tampilkan `-` bila mengikuti harga normal).

* **`FR-P2-10` Penerapan Dinamis di POS & Struk Thermal**
  - **Deskripsi:** Perhitungan otomatis harga member lintas cabang dan tampilan penghematan.
  - **Acceptance Criteria:**
    1. Saat member aktif di-tap (`F2`) di `Pos/Index.vue`, seluruh item di keranjang langsung memakai `member_price` (bila tersedia), memperbarui Grand Total, dan menampilkan **"Total Hemat Member"**.
    2. Saat tautan member dilepas sebelum checkout, seluruh item langsung kembali ke `selling_price` dan "Total Hemat Member" direset ke 0.
    3. `CheckoutService` menghitung dan menyimpan `regular_price_snapshot`, `unit_price_snapshot`, `member_discount_snapshot`, `regular_subtotal`, dan `member_discount_total` di server.
    4. `ReceiptThermal.vue` menampilkan baris **"Total Hemat Member"** apabila `member_discount_total > 0`.

### Modul 9: Sinkronisasi Laporan Keuangan, SHU & Ekspor Excel/PDF (`FR-P2-11` s.d. `FR-P2-12`)

* **`FR-P2-11` Koreksi Otomatis Laba Rugi & SHU atas Retur**
  - **Deskripsi:** Seluruh laporan keuangan mencerminkan angka bersih setelah dikurangi retur `completed`.
  - **Acceptance Criteria:**
    1. `FinancialReportService` menyajikan komponen transparan: **Omzet Bruto (`paid`)**, **Total Retur**, **Omzet Bersih**, **HPP Bersih** (HPP Bruto dikurangi HPP barang retur yang `restockable`), **Nilai Barang Rusak Retur (`damaged_cogs`)**, **Laba Kotor**, **Biaya Operasional**, dan **Laba Bersih / Dasar SHU**.
    2. Laporan Rekap Member (`Reports/Members.vue`) dan kalkulasi SHU per anggota otomatis mengurangi total belanja anggota (baik KOPDES Asal maupun Lintas Cabang) dengan nilai retur dari transaksi anggota tersebut.

* **`FR-P2-12` Sinkronisasi Ekspor Excel & PDF + Ekspor Laporan Shift & Retur**
  - **Deskripsi:** Memastikan file ekspor konsisten dengan dashboard terbaru serta menambah ekspor untuk modul Fase 2.
  - **Acceptance Criteria:**
    1. Ekspor Excel & PDF pada Laporan Penjualan/Laba Rugi dan SHU sudah memasukkan baris koreksi Retur dan kolom Total Hemat Member (opsional pada rincian transaksi).
    2. Menambahkan tombol Ekspor Excel & PDF pada halaman Riwayat Shift (`Shifts/Index.vue`) dan Riwayat Retur (`Returns/Index.vue`) yang terproteksi `ReportFilterRequest` / policy anti-IDOR.

---

## 5. Desain Skema Database Fase 2 (Migrasi Baru & Alter Tabel Existing)

### 5.1 Diagram Relasi Entitas Fase 2 (ERD ASCII)

```text
                       +-------------+
                       |  branches   |
                       +-------------+
                        │ 1         │ 1
                        ▼ N         ▼ N
               +----------------+  +---------------------+
               | cashier_shifts |  | transaction_returns |
               +----------------+  +---------------------+
                │ 1    │ 1    ▲ 1      ▲ 1       │ 1
   (cashier_id) │      │      │        │         │
  [users] ──────┘      ▼ N    │        │         ▼ N
              +----------------------+ │  +--------------------------+
              | shift_cash_movements | │  | transaction_return_items |
              +----------------------+ │  +--------------------------+
                       │               │         │ N
                       │ 1             │         │ (transaction_item_id)
                       ▼ N             │ 1       ▼ 1
                +--------------+1──────┘  +-------------------+
                | transactions |1--------N| transaction_items |
                +--------------+          +-------------------+
                                                    │ N
                                                    ▼ 1
                                             +------------+
                                             |  products  | (+ member_price)
                                             +------------+
```

### 5.2 Tabel Baru (4 Tabel)

#### 1. `cashier_shifts`
| Kolom | Tipe Data | Constraint & Index | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID shift kasir |
| `shift_code` | `VARCHAR(40)` | `NOT NULL`, `UNIQUE` | Format `SHF-{BRANCH_CODE}-{YYYYMMDD}-{SEQ3}` |
| `branch_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> branches(id) ON DELETE RESTRICT`, `INDEX` | Cabang lokasi shift |
| `cashier_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> users(id) ON DELETE RESTRICT`, `INDEX` | Kasir pemilik shift |
| `closed_by_user_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> users(id) ON DELETE RESTRICT` | Diisi `cashier_id` saat tutup normal atau ID Admin saat *force-close* |
| `status` | `ENUM('open','closed')` | `NOT NULL`, `DEFAULT 'open'`, `INDEX` | Status shift |
| `is_force_closed` | `BOOLEAN` | `NOT NULL`, `DEFAULT FALSE` | Penanda bila shift ditutup paksa oleh Admin |
| `opening_cash` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00` | Modal awal uang tunai di laci |
| `cash_sales_total` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00` | Snapshot total penjualan tunai `paid` saat tutup shift |
| `midtrans_sales_total` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00` | Snapshot total penjualan Midtrans `paid` saat tutup shift |
| `cash_in_total` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00` | Snapshot total kas masuk laci selama shift |
| `cash_out_total` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00` | Snapshot total kas keluar laci selama shift |
| `cash_refund_total` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00` | Snapshot total refund tunai yang diambil dari laci shift ini |
| `expected_closing_cash` | `DECIMAL(15,2)` | `NULL` | Hasil rumus ekspektasi uang laci (diisi saat `closed`) |
| `actual_closing_cash` | `DECIMAL(15,2)` | `NULL` | Nominal uang tunai fisik hasil Blind Count (diisi saat `closed`) |
| `variance` | `DECIMAL(15,2)` | `NULL` | Selisih `actual_closing_cash - expected_closing_cash` |
| `opening_notes` | `TEXT` | `NULL` | Catatan saat buka shift |
| `closing_notes` | `TEXT` | `NULL` | Catatan saat tutup shift (wajib jika selisih $\ne 0$ atau *force-close*) |
| `opened_at` | `TIMESTAMP` | `NOT NULL`, `INDEX` | Waktu buka shift |
| `closed_at` | `TIMESTAMP` | `NULL`, `INDEX` | Waktu tutup shift |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |
| **Composite Index** | `INDEX (branch_id, status, opened_at)` | `INDEX (cashier_id, status)` | Mempercepat cek shift aktif kasir dan laporan shift cabang |

#### 2. `shift_cash_movements`
| Kolom | Tipe Data | Constraint & Index | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID mutasi kas laci (*append-only*) |
| `cashier_shift_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> cashier_shifts(id) ON DELETE RESTRICT`, `INDEX` | Referensi shift aktif |
| `branch_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> branches(id) ON DELETE RESTRICT`, `INDEX` | Denormalisasi cabang untuk `HasRegionScope` |
| `user_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> users(id) ON DELETE RESTRICT` | Kasir pencatat |
| `type` | `ENUM('cash_in','cash_out')` | `NOT NULL`, `INDEX` | `cash_in` (tambah receh) atau `cash_out` (setor brankas) |
| `amount` | `DECIMAL(15,2)` | `NOT NULL` | Nominal mutasi ($> 0$) |
| `notes` | `VARCHAR(255)` | `NOT NULL` | Keterangan wajib tujuan kas masuk/keluar |
| `created_at` | `TIMESTAMP` | `NOT NULL`, `INDEX` | Waktu pencatatan (tanpa `updated_at`, immutable) |

#### 3. `transaction_returns`
| Kolom | Tipe Data | Constraint & Index | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID transaksi retur |
| `return_number` | `VARCHAR(50)` | `NOT NULL`, `UNIQUE` | Format `RTN-{BRANCH_CODE}-{YYYYMMDD}-{SEQ4}` |
| `transaction_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> transactions(id) ON DELETE RESTRICT`, `INDEX` | Invoice `paid` yang diretur |
| `branch_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> branches(id) ON DELETE RESTRICT`, `INDEX` | Cabang lokasi transaksi asal |
| `processed_by_user_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> users(id) ON DELETE RESTRICT`, `INDEX` | Admin Cabang / Admin Nasional pengeksekusi |
| `member_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> members(id) ON DELETE RESTRICT`, `INDEX` | Snapshot `transactions.member_id` untuk koreksi rekap member |
| `member_home_branch_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> branches(id) ON DELETE RESTRICT`, `INDEX` | Snapshot `transactions.member_home_branch_id` |
| `return_type` | `ENUM('partial','full')` | `NOT NULL`, `INDEX` | Jenis retur pada aksi ini (atau menandai jika transaksi menjadi penuh) |
| `refund_method` | `ENUM('cash_refund','manual_transfer')` | `NOT NULL`, `INDEX` | Metode pengembalian dana ke pembeli |
| `cash_source` | `ENUM('branch_cash','active_shift')` | `NULL` | Wajib diisi jika `refund_method = 'cash_refund'` |
| `refund_cashier_shift_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> cashier_shifts(id) ON DELETE RESTRICT`, `INDEX` | Wajib diisi jika `cash_source = 'active_shift'` |
| `refund_reference` | `VARCHAR(120)` | `NULL` | Wajib diisi jika `refund_method = 'manual_transfer'` (no. ref / rekening) |
| `total_refund_amount` | `DECIMAL(15,2)` | `NOT NULL` | Total uang yang dikembalikan (`SUM(subtotal_refund)`) |
| `total_restocked_cogs` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00` | Total HPP barang `restockable` yang kembali ke inventori |
| `total_damaged_cogs` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00` | Total HPP barang `damaged` (kerugian barang rusak) |
| `reason` | `TEXT` | `NOT NULL` | Alasan pengembalian barang / pembatalan |
| `status` | `ENUM('completed')` | `NOT NULL`, `DEFAULT 'completed'` | Status final retur (*immutable*) |
| `returned_at` | `TIMESTAMP` | `NOT NULL`, `INDEX` | Waktu eksekusi retur |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |
| **Composite Index** | `INDEX (branch_id, status, returned_at)` | `INDEX (member_id, status, returned_at)` | Mempercepat agregasi laporan keuangan & koreksi SHU member |

#### 4. `transaction_return_items`
| Kolom | Tipe Data | Constraint & Index | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | ID baris item retur |
| `transaction_return_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> transaction_returns(id) ON DELETE CASCADE`, `INDEX` | Referensi header retur |
| `transaction_item_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> transaction_items(id) ON DELETE RESTRICT`, `INDEX` | Referensi baris item transaksi asal |
| `product_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> products(id) ON DELETE RESTRICT`, `INDEX` | Produk terkait |
| `product_name_snapshot` | `VARCHAR(200)` | `NOT NULL` | Salinan dari `transaction_items.product_name_snapshot` |
| `qty_returned` | `INT` | `NOT NULL` | Jumlah unit diretur ($\ge 1$) |
| `condition` | `ENUM('restockable','damaged')` | `NOT NULL`, `INDEX` | Menentukan apakah menambah stok jual atau tidak |
| `unit_price_snapshot` | `DECIMAL(15,2)` | `NOT NULL` | Salinan `transaction_items.unit_price_snapshot` (harga aktual dibayar) |
| `cost_price_snapshot` | `DECIMAL(15,2)` | `NOT NULL` | Salinan `transaction_items.cost_price_snapshot` (HPP historis) |
| `subtotal_refund` | `DECIMAL(15,2)` | `NOT NULL` | `unit_price_snapshot * qty_returned` |
| `subtotal_cogs` | `DECIMAL(15,2)` | `NOT NULL` | `cost_price_snapshot * qty_returned` |
| `created_at` | `TIMESTAMP` | `NULL` | Waktu dibuat |
| `updated_at` | `TIMESTAMP` | `NULL` | Waktu diperbarui |

### 5.3 Perubahan Tabel Existing (Alter Migrations + Backfill)

#### A. Alter `products`
| Kolom Baru | Tipe Data | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `member_price` | `DECIMAL(15,2)` | `NULL`, `AFTER selling_price` | Harga khusus anggota member. Jika `NULL`, otomatis menggunakan `selling_price`. |

#### B. Alter `transactions`
| Kolom Baru | Tipe Data | Constraint & Index | Keterangan |
| :--- | :--- | :--- | :--- |
| `cashier_shift_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> cashier_shifts(id) ON DELETE RESTRICT`, `INDEX`, `AFTER cashier_id` | Nullable untuk kompatibilitas data historis MVP; wajib diisi pada transaksi POS baru. |
| `regular_subtotal` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00`, `AFTER member_home_branch_id` | Total nilai belanja berdasarkan harga normal (`selling_price`). *Backfill:* `UPDATE transactions SET regular_subtotal = subtotal`. |
| `member_discount_total` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00`, `AFTER regular_subtotal` | Total penghematan member (`regular_subtotal - total_amount`). |
| `return_status` | `ENUM('none','partial','full')` | `NOT NULL`, `DEFAULT 'none'`, `INDEX`, `AFTER payment_status` | Pelacak status retur transaksi `paid`. |

#### C. Alter `transaction_items`
| Kolom Baru | Tipe Data | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `regular_price_snapshot` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00`, `AFTER qty` | Snapshot `products.selling_price` saat transaksi. *Backfill:* `UPDATE transaction_items SET regular_price_snapshot = unit_price_snapshot`. |
| `member_discount_snapshot` | `DECIMAL(15,2)` | `NOT NULL`, `DEFAULT 0.00`, `AFTER unit_price_snapshot` | Snapshot selisih hemat baris item: `(regular_price_snapshot - unit_price_snapshot) * qty`. |
| `is_member_price_applied` | `BOOLEAN` | `NOT NULL`, `DEFAULT FALSE`, `AFTER member_discount_snapshot` | Penanda eksplisit bahwa harga khusus member berlaku pada baris ini. |

### 5.4 Pemetaan Relasi Eloquent Tambahan
- `CashierShift`: `belongsTo(Branch)`, `belongsTo(User, 'cashier_id')`, `belongsTo(User, 'closed_by_user_id')`, `hasMany(ShiftCashMovement)`, `hasMany(Transaction)`, `hasMany(TransactionReturn, 'refund_cashier_shift_id')`.
- `ShiftCashMovement`: `belongsTo(CashierShift)`, `belongsTo(Branch)`, `belongsTo(User)`.
- `TransactionReturn`: `belongsTo(Transaction)`, `belongsTo(Branch)`, `belongsTo(User, 'processed_by_user_id')`, `belongsTo(Member)`, `belongsTo(Branch, 'member_home_branch_id')`, `belongsTo(CashierShift, 'refund_cashier_shift_id')`, `hasMany(TransactionReturnItem)`.
- `TransactionReturnItem`: `belongsTo(TransactionReturn)`, `belongsTo(TransactionItem)`, `belongsTo(Product)`.
- `Transaction`: tambahkan `belongsTo(CashierShift)` dan `hasMany(TransactionReturn)`.
- `TransactionItem`: tambahkan `hasMany(TransactionReturnItem)` dan accessor `returned_qty` / `remaining_returnable_qty`.

---

## 6. Aturan Bisnis Teknis, Rumus Akuntansi & Mitigasi Edge Cases Kritis

### 6.1 Rumus Ekspektasi Laci Shift & Penanganan Transaksi Tunai
Saat `CashierShiftService::closeShift()` dijalankan di dalam `DB::transaction()` dengan `lockForUpdate()` pada baris `cashier_shifts`:

$$E_{\text{cash}} = O_{\text{cash}} + S_{\text{cash}} + M_{\text{in}} - M_{\text{out}} - R_{\text{shift\_cash}}$$

$$\text{Variance} = A_{\text{cash}} - E_{\text{cash}}$$

Dengan definisi variabel:
* $O_{\text{cash}}$ = `cashier_shifts.opening_cash`.
* $S_{\text{cash}}$ = Total `transactions.total_amount` di mana `cashier_shift_id = shift.id`, `payment_method = 'cash'`, dan `payment_status = 'paid'`.
* $M_{\text{in}}$ = Total `shift_cash_movements.amount` bertipe `'cash_in'` milik shift ini.
* $M_{\text{out}}$ = Total `shift_cash_movements.amount` bertipe `'cash_out'` milik shift ini.
* $R_{\text{shift\_cash}}$ = Total `transaction_returns.total_refund_amount` di mana `refund_method = 'cash_refund'`, `cash_source = 'active_shift'`, dan `refund_cashier_shift_id = shift.id`.
* $A_{\text{cash}}$ = `actual_closing_cash` yang diinput kasir pada mode Blind Count.
* *Catatan Penjualan Midtrans:* `midtrans_sales_total` tetap dihitung dari transaksi `payment_method = 'midtrans'` dan `payment_status = 'paid'` pada shift tersebut untuk informasi ringkasan omzet shift, tetapi **tidak ditambahkan** ke $E_{\text{cash}}$ karena uang Midtrans tidak masuk ke laci fisik.

### 6.2 Rumus Akuntansi Retur pada `FinancialReportService` & SHU
Untuk menjaga akurasi akuntansi ritel saat terdapat barang retur layak jual maupun rusak:
* **Omzet Bruto ($R_{\text{bruto}}$):** Total `transactions.total_amount` (`payment_status = 'paid'`) pada periode `paid_at`.
* **Total Retur ($R_{\text{retur}}$):** Total `transaction_returns.total_refund_amount` (`status = 'completed'`) pada periode `returned_at`.
* **Omzet Bersih ($R_{\text{bersih}}$):**
  $$R_{\text{bersih}} = R_{\text{bruto}} - R_{\text{retur}}$$
* **HPP Bruto ($C_{\text{bruto}}$):** Total `transactions.total_cogs` (`payment_status = 'paid'`) pada periode `paid_at`.
* **HPP Kembali ke Stok ($C_{\text{restock}}$):** Total `transaction_returns.total_restocked_cogs` (hanya dari item berkondisi `'restockable'`).
* **HPP Barang Rusak ($C_{\text{damaged}}$):** Total `transaction_returns.total_damaged_cogs` (ditampilkan sebagai metrik informasi kerugian retur rusak; **tidak** mengurangi HPP karena nilai modal barang tersebut benar-benar hilang/tidak dapat dijual lagi).
* **HPP Bersih ($C_{\text{bersih}}$):**
  $$C_{\text{bersih}} = C_{\text{bruto}} - C_{\text{restock}}$$
* **Laba Kotor ($P_{\text{kotor}}$) & Laba Bersih / Dasar SHU ($P_{\text{bersih}}$):**
  $$P_{\text{kotor}} = R_{\text{bersih}} - C_{\text{bersih}}$$
  $$P_{\text{bersih}} = P_{\text{kotor}} - E_{\text{operasional}}$$
* **Akumulasi Belanja Member (Rekap SHU Anggota):** Total belanja bersih member di KOPDES Asal dan Lintas Cabang dihitung dari `SUM(transactions.total_amount)` dikurangi `SUM(transaction_returns.total_refund_amount)` milik member tersebut pada periode yang sama (dengan pemisahan Asal vs Lintas Cabang menggunakan `branch_id === member_home_branch_id`).

### 6.3 Mitigasi Concurrency & Edge Cases Kritis

1. **Anti Over-Return (Race Condition Retur Ganda):**
   - Di dalam `TransactionReturnService::processReturn()`, bungkus seluruh proses pada `DB::transaction()`.
   - Kunci baris `transactions` dengan `lockForUpdate()`, lalu hitung ulang `already_returned_qty` tiap `transaction_item_id` dari `transaction_return_items`. Jika `qty_return > (item.qty - already_returned_qty)`, lempar `ValidationException` (HTTP `422`).
   - Jika ada item berkondisi `restockable`, urutkan `product_id` secara menaik (`sort()`) lalu kunci `branch_stocks` dengan `lockForUpdate()` sebelum menambah `quantity` dan menulis `stock_movements`.

2. **Konflik Refund Tunai ke Shift yang Keburu Ditutup:**
   - Jika Admin Cabang memilih `cash_source = 'active_shift'` untuk Shift #5, namun tepat sebelum Admin menekan Submit ternyata Kasir menutup Shift #5:
   - Di dalam `DB::transaction()`, `TransactionReturnService` mengunci baris `cashier_shifts` terpilih dengan `lockForUpdate()` dan memeriksa `$shift->status === 'open'`. Jika ternyata sudah `'closed'`, transaksi dibatalkan (HTTP `422`: *"Shift kasir terpilih baru saja ditutup. Silakan pilih shift aktif lain atau gunakan Kas Cabang."*).

3. **Pemblokiran Tutup Shift & Interaksi Lock Layar (`PosLockModal`):**
   - Jika Kasir A mengunci layar (`F12`) saat shift masih `open`, lalu Kasir B membuka kunci layar (`PosLockModal`) dengan kartu Kasir B:
     - Shift Kasir A tetap `open` (tidak tertutup otomatis agar tanggung jawab laci Kasir A tetap tercatat).
     - Keranjang POS direset dan sistem memeriksa apakah Kasir B sudah memiliki shift `open`. Jika belum, modal **Buka Shift** langsung muncul untuk Kasir B.
   - Jika suatu saat Kasir A lupa menutup shift dan pulang, `admin_cabang` menyelesaikan shift Kasir A melalui fitur **Force-Close Shift** di Dashboard Admin.

4. **Keamanan Blind Count di Payload Inertia / JSON:**
   - Selama `$shift->status === 'open'`, `CashierShiftResource` untuk layar POS **dilarang keras** menyertakan kolom `cash_sales_total`, `expected_closing_cash`, atau `variance` dalam response Inertia/JSON agar kasir tidak dapat melihat angka ekspektasi melalui *Browser DevTools (Network/Vue Inspector)*. Nilai tersebut hanya dihitung dan dikembalikan setelah shift resmi berubah status menjadi `'closed'`.

5. **Konsistensi Harga Member pada Retry `idempotency_key` & Midtrans `pending`:**
   - Begitu transaksi dibuat (baik `cash` maupun `midtrans` `pending`), seluruh `regular_price_snapshot`, `unit_price_snapshot`, dan `member_discount_snapshot` sudah dikunci di database.
   - Perubahan `products.member_price` oleh `admin_nasional` saat transaksi Midtrans masih `pending` tidak akan mengubah nominal transaksi yang sedang menunggu pembayaran.
   - Jika kasir men-tap kartu member atau melepas kartu member di `Pos/Index.vue`, Vue tetap boleh mempertahankan keranjang, namun **wajib meregenerasi `idempotency_key`** apabila terjadi perubahan `selectedMember` atau perubahan daftar item/kuantitas keranjang agar payload baru tidak mengembalikan hasil transaksi lama bila sebelumnya sempat gagal di jaringan.

6. **Penyesuaian Test Milestone 4 Existing:**
   - Karena `POST /pos/checkout` kini mensyaratkan shift kasir berstatus `open` (`EnsureActiveShift`), seluruh skenario test POS pada `Milestone4PosCashAndMemberTest.php` dan `Milestone4MidtransAndSchedulerTest.php` wajib diperbarui (menambahkan helper pembuatan `CashierShift` berstatus `open` sebelum memanggil endpoint checkout) agar suite test dari Milestone 1 hingga Fase 2 lulus 100%.

---

## 7. Rencana Implementasi Bertahap (Milestones Fase 2)

Pengembangan Fase 2 dipecah menjadi 4 milestone berurutan (melanjutkan penomoran dari MVP Milestone 1–5):

### Milestone 6: Harga Khusus Anggota Member (`member_price` & Snapshot Diskon)
* **Tujuan:** Menyediakan pengaturan harga khusus member di katalog pusat, penerapan otomatis lintas cabang di POS Vue, penyimpanan snapshot diskon, dan tampilan "Total Hemat Member" di struk thermal.
* **Deliverables Utama:**
  1. Migrasi alter `products` (`member_price`), `transactions` (`regular_subtotal`, `member_discount_total`), dan `transaction_items` (`regular_price_snapshot`, `member_discount_snapshot`, `is_member_price_applied`) beserta *backfill* data lama.
  2. Pembaruan `StoreProductRequest`, `UpdateProductRequest`, `Products/Form.vue` (peringatan visual bila `member_price < cost_price`), dan `Products/Index.vue`.
  3. Pembaruan `PosLookupController::productByBarcode`, `CheckoutService` (evaluasi harga member di server + snapshot), `Pos/Index.vue` (kalkulasi ulang instan saat tap/lepas kartu member), dan `ReceiptThermal.vue`.
* **Definition of Done (Pengujian):**
  - Feature Test membuktikan: (a) `member_price > selling_price` ditolak validasi; (b) pembeli member (termasuk lintas cabang) mendapat `member_price` dan mencatat `member_discount_total` akurat; (c) produk tanpa `member_price` otomatis memakai `selling_price`; (d) pembeli non-member tetap memakai `selling_price` walau produk punya `member_price`; (e) perubahan `member_price` setelah transaksi tidak mengubah snapshot historis.

### Milestone 7: Manajemen Shift Kasir (Buka/Tutup Blind Count, Mutasi Kas Laci & Force-Close)
* **Tujuan:** Mengontrol penuh siklus operasional kasir per shift dan rekonsiliasi laci tunai di cabang.
* **Deliverables Utama:**
  1. Migrasi `cashier_shifts`, `shift_cash_movements`, dan alter `transactions.cashier_shift_id`.
  2. Model `CashierShift`, `ShiftCashMovement`, policy `CashierShiftPolicy`, middleware `EnsureActiveShift`, serta `CashierShiftService` (`openShift`, `recordCashMovement`, `closeShift`, `forceCloseShift`).
  3. Controller & Routes POS Shift (`PosShiftController`) dan Admin Shift (`ShiftController`).
  4. Komponen Vue POS: `OpenShiftModal.vue`, `ShiftCashMovementModal.vue` (`F4`), `CloseShiftModal.vue` (`F10`, Blind Count), `ShiftClosingReceipt.vue` (thermal 58mm/80mm), integrasi pada `Pos/Index.vue`, serta halaman Dashboard `Shifts/Index.vue` & `Shifts/Show.vue` (termasuk fitur Force-Close).
  5. Pembaruan helper shift pada test Milestone 4 yang sudah ada.
* **Definition of Done (Pengujian):**
  - Feature Test membuktikan: (a) kasir ditolak checkout jika belum membuka shift; (b) 1 kasir tidak bisa punya 2 shift `open`, tetapi 2 kasir di 1 cabang bisa punya shift `open` bersamaan; (c) payload sebelum tutup shift tidak membocorkan `expected_closing_cash`; (d) tutup shift diblokir jika masih ada transaksi Midtrans `pending`; (e) perhitungan `expected_closing_cash` dan `variance` akurat (memperhitungkan `opening_cash`, penjualan tunai, `cash_in`, `cash_out`, dan mengecualikan Midtrans); (f) mutasi kas laci tidak masuk ke Biaya Operasional; (g) `admin_cabang` dapat melakukan force-close shift cabangnya namun ditolak HTTP 403 untuk cabang lain.

### Milestone 8: Retur Parsial & Full Void Terpusat (Admin Cabang)
* **Tujuan:** Membangun alur pengembalian barang dan pembatalan transaksi `paid` yang aman terhadap *race condition*, terpisah kondisi fisiknya, dan terekonsiliasi dengan stok serta laci shift.
* **Deliverables Utama:**
  1. Konfigurasi `config/kopdes.php` (`return_max_days => env('KOPDES_RETURN_MAX_DAYS', 7)`).
  2. Migrasi `transaction_returns`, `transaction_return_items`, dan alter `transactions.return_status`.
  3. Model `TransactionReturn`, `TransactionReturnItem`, policy `TransactionReturnPolicy`, Form Request `StoreTransactionReturnRequest`, dan `TransactionReturnService` (`lockForUpdate()`, `restockable` vs `damaged`, `stock_movements` `void_return`, validasi shift aktif untuk `cash_source = 'active_shift'`).
  4. `TransactionReturnController` serta halaman Vue Dashboard: `Returns/Index.vue`, `Returns/Create.vue` (dengan tombol Full Void & pilihan kondisi per item), dan `Returns/Show.vue` (bukti retur siap cetak).
* **Definition of Done (Pengujian):**
  - Feature Test membuktikan: (a) retur parsial lalu retur sisa item mengubah `return_status` dari `none` -> `partial` -> `full` dan menolak retur melebihi sisa kuantitas; (b) transaksi di atas 7 hari atau bukan `paid` ditolak HTTP 422; (c) item `restockable` menambah `branch_stocks.quantity` + menulis `stock_movements` (`type = 'void_return'`), sedangkan item `damaged` tidak menambah stok; (d) `cash_refund` dengan `active_shift` mengurangi `expected_closing_cash` shift kasir tersebut, sedangkan `branch_cash` dan `manual_transfer` tidak mengubah laci shift; (e) kasir dan `admin_cabang` cabang lain ditolak HTTP 403.

### Milestone 9: Sinkronisasi Laporan Keuangan, SHU & Ekspor Excel/PDF Fase 2
* **Tujuan:** Menyelaraskan seluruh laporan keuangan, kalkulasi SHU, dan ekspor Excel/PDF dengan data Retur, Shift, dan Harga Member.
* **Deliverables Utama:**
  1. Pembaruan `FinancialReportService`: integrasi `total_returns`, `net_revenue`, `restocked_cogs`, `damaged_cogs`, `net_cogs`, `gross_profit`, `net_profit` (Laba Bersih / Dasar SHU), dan koreksi akumulasi belanja Member lintas cabang.
  2. Pembaruan `Dashboard/Index.vue`, `Reports/Sales.vue`, `Reports/Members.vue`, dan halaman SHU untuk menampilkan metrik retur dan barang rusak secara transparan.
  3. Pembaruan Export Excel & PDF (Laba Rugi & SHU) serta penambahan Export Excel & PDF untuk Riwayat Shift (`Shifts/Index.vue`) dan Riwayat Retur (`Returns/Index.vue`) lengkap dengan proteksi anti-IDOR.
* **Definition of Done (Pengujian):**
  - Feature Test membuktikan: (a) retur `restockable` menurunkan Omzet Bersih dan HPP Bersih sesuai snapshot transaksi asal; (b) retur `damaged` menurunkan Omzet Bersih tanpa menurunkan HPP Bersih sehingga Laba Kotor mencerminkan kerugian barang rusak; (c) Laba Bersih & dasar pembagian SHU serta akumulasi belanja member otomatis terkoreksi oleh retur; (d) seluruh endpoint ekspor Excel & PDF baru maupun diperbarui mengembalikan HTTP 200 untuk scope sah dan HTTP 403 untuk manipulasi wilayah lintas scope; (e) seluruh test suite (`Milestone 1` s.d. `Milestone 9` + `OperationalExpenseAndExportTest`) lulus 100%.