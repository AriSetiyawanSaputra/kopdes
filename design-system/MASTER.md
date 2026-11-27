# Design System Master — KOPDES Merah Putih

Dokumen ini adalah **Standar Desain UI/UX Resmi (Global Source of Truth)** untuk seluruh halaman web **Sistem Kasir & Manajemen Keuangan KOPDES (Koperasi Desa Merah Putih)**. Setiap halaman baru maupun pembaruan halaman lama WAJIB mematuhi token warna, tipografi, komponen tombol, struktur sidebar, dan aturan aksesibilitas di dokumen ini.

> **Pola Pengambilan Aturan (Master + Overrides):**
> 1. Sebelum membangun halaman spesifik (misal Dashboard), cek `design-system/pages/<nama-halaman>.md`.
> 2. Jika file override halaman tersedia, aturan di dalamnya melengkapi/meng-override aturan umum di `MASTER.md`.
> 3. Jika tidak ada override halaman, gunakan `design-system/MASTER.md` sepenuhnya.

---

## 1. Identitas Visual & Palet Warna (Merah Putih KOPDES)

Tema desain menggabungkan **Otoritas Finansial & Kepercayaan Koperasi** (`Slate Navy`) dengan **Identitas Kebangsaan Merah Putih** (`Merah KOPDES #B91C1C` & permukaan putih bersih `#FFFFFF` / `#F8FAFC`).

### 1.1 Warna Utama — Merah KOPDES (`kopdes.*`)

| Token Tailwind | Hex | Penggunaan Resmi |
| :--- | :--- | :--- |
| `kopdes-50` | `#FEF2F2` | Latar lembut badge, highlight aktif, banner info aksen |
| `kopdes-100` | `#FEE2E2` | Border lembut aksen merah, latar ikon KPI primer |
| `kopdes-200` | `#FECACA` | Ring fokus lembut, garis pemisah aksen |
| `kopdes-500` | `#DC2626` | Aksen interaktif, garis indikator fokus (`focus:ring-kopdes-500`) |
| `kopdes-600` | `#C81E1E` | Hover aksen, link interaktif merah |
| `kopdes-700` | `#B91C1C` | **Warna Primer Utama KOPDES** — Tombol CTA Utama, item sidebar aktif, header brand |
| `kopdes-800` | `#991B1B` | State hover tombol primer (`hover:bg-kopdes-800`) |
| `kopdes-900` | `#7F1D1D` | State pressed/active tombol primer (`active:bg-kopdes-900`) |
| `kopdes-950` | `#450A0A` | Teks kontras sangat tinggi pada permukaan merah terang |

### 1.2 Warna Netral & Permukaan (Kontras Tinggi WCAG AA/AAA >= 4.5:1)

| Peran | Token Tailwind | Hex | Keterangan |
| :--- | :--- | :--- | :--- |
| **Canvas Halaman** | `bg-slate-50` | `#F8FAFC` | Latar utama ruang kerja admin (bersih, tidak menyilaukan) |
| **Permukaan Kartu** | `bg-white` | `#FFFFFF` | Kartu KPI, tabel, panel filter |
| **Border Kartu** | `border-slate-200` | `#E2E8F0` | Selalu terlihat jelas di mode terang (dilarang border transparan) |
| **Sidebar Gelap** | `bg-slate-900` | `#0F172A` | Navigasi sidebar tetap yang tegas dan berwibawa |
| **Teks Utama** | `text-slate-900` | `#0F172A` | Judul, angka KPI, nilai tabel utama |
| **Teks Sekunder** | `text-slate-600` | `#475569` | Label subjudul, metadata, keterangan (kontras minimum) |
| **Teks Muted** | `text-slate-500` | `#64748B` | Hanya untuk caption kecil / helper text (dilarang memakai `slate-400` untuk teks isi) |

### 1.3 Warna Semantik Keuangan

| Status / Domain | Teks | Latar Lembut | Aksen / Bar | Penggunaan |
| :--- | :--- | :--- | :--- | :--- |
| **Laba / Lunas (`paid`) / Aktif / Tunai** | `text-emerald-700` (`#047857`) | `bg-emerald-50` (`#ECFDF5`) | `bg-emerald-600` | Laba kotor/bersih, status lunas, transaksi tunai, export Excel |
| **Info / Midtrans / Lintas Cabang** | `text-indigo-700` (`#4338CA`) | `bg-indigo-50` (`#EEF2FF`) | `bg-indigo-600` | Transaksi non-tunai Midtrans, belanja lintas cabang, informasi sistem |
| **Margin / Peringatan / Pending / Demo** | `text-amber-800` (`#92400E`) | `bg-amber-50` (`#FFFBEB`) | `bg-amber-500` | Persentase margin, stok mendekati `min_stock`, transaksi `pending`, penanda Mode Demo |
| **Bahaya / Batal / Selisih Kurang / Error** | `text-rose-700` (`#BE123C`) | `bg-rose-50` (`#FFF1F2`) | `bg-rose-600` | Aksi destruktif, retur/void, selisih kas kurang, error validasi |

---

## 2. Sistem Tipografi

Menggunakan kombinasi **Plus Jakarta Sans** (UI modern & berwibawa, sangat terbaca untuk bahasa Indonesia) dan **JetBrains Mono** (khusus nominal Rupiah, kode cabang/invoice/member, dan angka tabel agar sejajar vertikal dengan `tabular-nums`).

- **Font Sans (Default UI & Heading):** `'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif`
- **Font Mono (Data Keuangan & Kode):** `'JetBrains Mono', ui-monospace, monospace` + class `tabular-nums`

| Skala | Class Tailwind | Ukuran / Bobot | Penggunaan |
| :--- | :--- | :--- | :--- |
| **Page Title** | `text-xl sm:text-2xl font-bold tracking-tight text-slate-900` | 20–24px / 700 | Judul utama halaman di header |
| **Section Heading** | `text-base font-bold text-slate-900` | 16px / 700 | Judul kartu analitik dan tabel |
| **KPI Metric Value** | `font-mono tabular-nums text-2xl font-extrabold tracking-tight` | 24px / 800 | Angka utama KPI (Omzet, Laba Kotor, Margin) |
| **Card Label / Eyebrow** | `text-xs font-semibold uppercase tracking-wider text-slate-500` | 12px / 600 | Label di atas angka KPI & judul grup sidebar |
| **Body / Table Cell** | `text-sm text-slate-700` | 14px / 400–500 | Teks tabel, isi form, deskripsi |
| **Financial Cell** | `font-mono tabular-nums text-sm font-semibold` | 14px / 600–700 | Nominal Rupiah rata kanan (`text-right`) |
| **Micro / Badge / Helper** | `text-xs font-medium` | 12px / 500–600 | Badge status, keterangan input, timestamp |

---

## 3. Sistem Tombol & Kontrol Interaktif

Semua tombol WAJIB memiliki `cursor-pointer`, `transition-colors duration-200`, `rounded-lg`, tinggi nyaman sentuh (`py-2 px-4`), fokus keyboard jelas (`focus:outline-none focus:ring-2 focus:ring-offset-2`), dan **tidak menggunakan transformasi `scale` saat hover** agar tidak menimbulkan *layout shift*.

| Varian | Komponen / Utility Class | Gaya Visual | Penggunaan |
| :--- | :--- | :--- | :--- |
| **Primary (CTA Utama)** | `<PrimaryButton>` / `.btn-kopdes-primary` | `bg-kopdes-700 text-white hover:bg-kopdes-800 active:bg-kopdes-900 focus:ring-kopdes-500` | Simpan, Terapkan Filter, Tambah Data, Aksi Utama |
| **Secondary (Netral)** | `<SecondaryButton>` / `.btn-kopdes-secondary` | `bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 hover:border-slate-400 focus:ring-kopdes-500` | Reset, Batal, Navigasi Sekunder |
| **Outline Brand (PDF / Aksen)** | `.btn-kopdes-outline` | `bg-kopdes-50 text-kopdes-700 border border-kopdes-200 hover:bg-kopdes-100 focus:ring-kopdes-500` | Export PDF, Aksi Sekunder Beraksen KOPDES |
| **Emerald (Excel / Sukses)** | `.btn-kopdes-emerald` | `bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 focus:ring-emerald-500` | Export Excel, Konfirmasi Sukses |
| **Danger (Destruktif)** | `<DangerButton>` / `.btn-kopdes-danger` | `bg-rose-600 text-white hover:bg-rose-700 active:bg-rose-800 focus:ring-rose-500` | Hapus, Nonaktifkan Akun/Cabang |

---

## 4. Arsitektur Layout Admin (Sidebar + Sticky Topbar)

Seluruh halaman backoffice admin menggunakan **[AuthenticatedLayout.vue](../resources/js/Layouts/AuthenticatedLayout.vue)**:

1. **Sidebar Kiri Collapsible (`w-64` expanded ↔ `w-[76px]` collapsed, `bg-slate-900 text-slate-200`) pada Desktop (`lg:fixed lg:inset-y-0`):**
   - **Header Brand:** Logo institusi dinamis (`app_settings.institution_logo_url`) atau emblem perisai KOPDES Merah Putih (`bg-kopdes-700`) + nama institusi & subjudul `FinTech & Retail`.
   - **Grup Menu Terstruktur (Role-Aware):**
     - `UTAMA`: Dashboard Keuangan
     - `OPERASIONAL & STOK`: Shift Kasir, Retur & Void, Stok Cabang, Audit Log Stok
     - `KEUANGAN & LAPORAN`: Penjualan & Laba Rugi, Biaya Operasional, Rekap Member (SHU)
     - `MASTER DATA`: Cabang KOPDES, Anggota (Member), Katalog Produk, Kategori Produk (`admin_nasional`), Pengguna & Kasir
     - `SISTEM`: Pengaturan Sistem (`admin_nasional`), Audit Trail
   - **Active Menu State:** Latar `bg-kopdes-700 text-white shadow-sm` dengan ikon SVG crisp.
   - **Sidebar Footer:** Kartu identitas Role & Cakupan Wilayah aktif (`Nasional`, Provinsi, Kabupaten, atau Nama & Kode Cabang).
2. **Mobile Drawer (`< lg`):** Tombol hamburger di topbar membuka sidebar *slide-over* dengan backdrop `bg-slate-900/60 backdrop-blur-sm`.
3. **Sticky Topbar (`h-16 bg-white/95 backdrop-blur border-b border-slate-200`):**
   - Menampilkan tombol collapse sidebar (desktop), konteks wilayah/cabang aktif, badge role, penanda halus Mode Demo (`APP_DEMO_MODE=true`), shortcut Terminal Kasir POS, serta dropdown profil pengguna (`Profil Akun` & `Keluar`).
4. **Komponen UI Dasar (`resources/js/Components/`):**
   - `AppButton.vue`, `StatusBadge.vue`, `AppCard.vue`, `StatCard.vue`, `Modal.vue`, `EmptyState.vue`, `AppPagination.vue` (bebas `v-html`), `ToastNotification.vue`.

---

## 5. Komponen Kartu, Tabel & Form Standar

- **Kartu (`.kopdes-card`):** `rounded-xl bg-white border border-slate-200 shadow-sm`
- **Tabel Keuangan:**
  - Header `bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase tracking-wider text-slate-600`.
  - Baris data `divide-y divide-slate-100 hover:bg-slate-50/80 transition-colors`.
  - Seluruh kolom nominal uang rata kanan (`text-right font-mono tabular-nums`), kolom jumlah/persentase rata tengah (`text-center font-mono tabular-nums`).
  - Selalu sediakan baris **Total** (`bg-slate-50 font-bold border-t-2 border-slate-200`) pada tabel agregasi keuangan.
- **Input & Select Form:** `rounded-lg border-slate-300 text-sm text-slate-900 shadow-sm focus:border-kopdes-600 focus:ring-kopdes-500`.

---

## 6. Aturan Kualitas & Anti-Pattern (Wajib Dipatuhi)

1. **Dilarang Emoji sebagai Ikon UI:** Selalu gunakan ikon SVG (`viewBox="0 0 24 24"`, `w-5 h-5` atau `w-4 h-4`, konsisten gaya Heroicons Outline).
2. **Kontras & Keterbacaan:** Teks isi wajib `text-slate-900` / `text-slate-700`; teks label minimal `text-slate-600`; warna bukan satu-satunya penanda status (selalu sertakan teks label).
3. **Stabilitas Interaksi:** Semua elemen klik memiliki `cursor-pointer` dan `transition-colors duration-200`. Hindari `hover:scale-*` pada kartu atau tombol.
4. **Aksesibilitas:** Semua input form wajib memiliki `<label>`, semua tombol ikon wajib memiliki `aria-label`, dan hormati `prefers-reduced-motion`.
5. **Responsif Penuh:** Tata letak diuji pada lebar `375px` (mobile), `768px` (tablet), `1024px` (laptop), dan `1440px` (desktop) tanpa *horizontal scroll* pada body halaman.
