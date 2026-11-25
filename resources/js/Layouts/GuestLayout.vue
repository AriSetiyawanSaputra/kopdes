<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900 lg:grid lg:grid-cols-12">
        <!-- Left Brand Panel (Desktop Executive Showcase) -->
        <aside class="relative hidden flex-col justify-between overflow-hidden bg-slate-900 px-10 py-10 text-white lg:col-span-5 lg:flex xl:col-span-6 xl:px-14">
            <!-- Subtle Crimson & Slate Geometric Backdrop -->
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(185,28,28,0.42),transparent_55%),radial-gradient(circle_at_bottom_left,rgba(153,27,27,0.28),transparent_50%)]"
                aria-hidden="true"
            ></div>
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 24px 24px;"
                aria-hidden="true"
            ></div>

            <!-- Brand Identity -->
            <div class="relative z-10 flex items-center gap-3.5">
                <Link
                    href="/login"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-kopdes-700 shadow-lg ring-1 ring-white/20"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        class="h-6 w-6 text-white"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 3L4 7v6c0 4.418 3.582 8 8 8s8-3.582 8-8V7l-8-4z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12l2 2 4-4"
                        />
                    </svg>
                </Link>
                <div>
                    <span class="block text-lg font-extrabold tracking-tight text-white">
                        KOPDES MERAH PUTIH
                    </span>
                    <span class="block text-xs font-medium text-kopdes-200">
                        Sistem Kasir &amp; Manajemen Keuangan Terpusat
                    </span>
                </div>
            </div>

            <!-- Center Value Proposition & Trust Pillars -->
            <div class="relative z-10 my-auto max-w-lg py-10">
                <span class="inline-flex items-center gap-2 rounded-full border border-kopdes-500/40 bg-kopdes-900/60 px-3 py-1 text-xs font-semibold text-kopdes-100">
                    <span class="h-1.5 w-1.5 rounded-full bg-kopdes-400"></span>
                    Platform Koperasi Desa Nasional
                </span>

                <h2 class="mt-4 text-3xl font-extrabold leading-tight tracking-tight text-white xl:text-4xl">
                    Kelola Keuangan &amp; Operasional KOPDES Secara Transparan
                </h2>

                <p class="mt-3 text-sm leading-relaxed text-slate-300">
                    Terintegrasi dari tingkat Desa hingga Nasional dengan kendali stok real-time,
                    pembayaran Tunai &amp; Midtrans Snap, serta laporan laba kotor berbasis snapshot HPP.
                </p>

                <!-- 3 Pillars -->
                <div class="mt-8 space-y-3.5">
                    <div class="flex items-start gap-3.5 rounded-xl border border-white/10 bg-white/5 p-3.5 backdrop-blur-sm">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-kopdes-700/90 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">
                                Pelaporan Keuangan Berjenjang &amp; Akurat
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-300">
                                Drill-down Nasional, Provinsi, Kabupaten, hingga Cabang Desa dengan proteksi RBAC ketat.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 rounded-xl border border-white/10 bg-white/5 p-3.5 backdrop-blur-sm">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-600/90 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">
                                Snapshot HPP &amp; Fondasi Data SHU Anggota
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-300">
                                Perubahan harga produk tidak memengaruhi laba kotor historis dan rekap belanja anggota.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 rounded-xl border border-white/10 bg-white/5 p-3.5 backdrop-blur-sm">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600/90 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">
                                Kartu RFID Kasir &amp; Portabilitas Member
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-300">
                                Autentikasi cepat RFID + PIN 6 digit bagi kasir serta kartu anggota lintas cabang KOPDES.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Legal / Status -->
            <div class="relative z-10 flex items-center justify-between border-t border-white/10 pt-5 text-xs text-slate-400">
                <span>&copy; {{ new Date().getFullYear() }} KOPDES Merah Putih</span>
                <span class="font-mono text-[11px] text-slate-300">1 Desa = 1 KOPDES</span>
            </div>
        </aside>

        <!-- Right Authentication Workspace -->
        <main class="flex min-h-screen flex-col justify-between px-4 py-8 sm:px-8 lg:col-span-7 lg:px-12 xl:col-span-6">
            <!-- Mobile Brand Header -->
            <div class="mx-auto mb-6 flex w-full max-w-md items-center gap-3 lg:hidden">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-kopdes-700 shadow-sm">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        class="h-5 w-5 text-white"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3L4 7v6c0 4.418 3.582 8 8 8s8-3.582 8-8V7l-8-4z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                    </svg>
                </div>
                <div class="leading-tight">
                    <span class="block text-base font-extrabold tracking-tight text-slate-900">
                        KOPDES Merah Putih
                    </span>
                    <span class="block text-xs font-medium text-slate-600">
                        Sistem Kasir &amp; Manajemen Keuangan
                    </span>
                </div>
            </div>

            <!-- Centered Card Container -->
            <div class="mx-auto my-auto w-full max-w-md">
                <div class="kopdes-card relative overflow-hidden p-6 sm:p-8">
                    <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-kopdes-800 via-kopdes-700 to-kopdes-600"></div>
                    <slot />
                </div>
            </div>

            <!-- Mobile / Workspace Footer -->
            <p class="mt-6 text-center text-xs text-slate-500">
                Akses sistem terenkripsi &bull; Koperasi Desa Merah Putih
            </p>
        </main>
    </div>
</template>
