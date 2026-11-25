# Page Override — Halaman Autentikasi (`Auth/Login.vue` & `Pos/Login.vue`)

Dokumen ini melengkapi [MASTER.md](../MASTER.md) khusus untuk halaman autentikasi **KOPDES Merah Putih** (`/login` untuk Admin dan `/pos/login` untuk Kasir POS).

---

## 1. Perilaku Routing Awal
- Akses ke root URL (`/`) saat belum login **langsung diarahkan (`302 Redirect`) ke `/login`**.
- Jika sudah terautentikasi:
  - Role Admin (`admin_nasional`, `admin_provinsi`, `admin_kabupaten`, `admin_cabang`) diarahkan ke `/dashboard`.
  - Role Kasir (`kasir`) diarahkan ke `/pos`.

## 2. Tata Letak Split-Screen (`GuestLayout.vue`)
- **Desktop (`lg:grid-cols-12`):**
  - **Panel Kiri (`lg:col-span-5 xl:col-span-6`, `bg-slate-900` + gradien aksen `kopdes-700`):** Menampilkan emblem perisai KOPDES Merah Putih, headline sistem keuangan terpusat, dan 3 pilar kepercayaan (RBAC Berjenjang, Snapshot HPP, Kartu Anggota Portabel).
  - **Panel Kanan (`lg:col-span-7 xl:col-span-6`, `bg-slate-50`):** Kartu form terpusat (`kopdes-card`, `max-w-md`) dengan garis aksen atas `bg-kopdes-700`.
- **Mobile (`< lg`):** Header brand ringkas Merah KOPDES di atas kartu form agar proporsional di layar `375px` tanpa scroll horizontal.

## 3. Pengalih Mode Autentikasi Dual-Mode
- Di bagian atas kartu form selalu tersedia *segmented switcher* antara:
  - **Admin Dashboard (`/login`)** — Email + Kata Sandi (dengan tombol *toggle* lihat/sembunyikan kata sandi berikon SVG).
  - **Kasir POS (`/pos/login`)** — Tap Kartu RFID (`card_uid` ternormalisasi) + PIN 6 Digit (dengan banner hitung mundur saat terkena RateLimiter HTTP `429`).
