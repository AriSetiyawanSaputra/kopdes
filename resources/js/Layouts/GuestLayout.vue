<script setup>
import { computed } from 'vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    maxWidth: {
        type: String,
        default: 'md',
        validator: (val) => ['md', 'lg'].includes(val),
    },
});

const page = usePage();
const appSettings = computed(() => page.props.app_settings ?? {});
const isDemoMode = computed(() => Boolean(page.props.app?.demo_mode));
const institutionName = computed(
    () => appSettings.value?.institution_name || 'Koperasi Desa Merah Putih',
);
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900 lg:grid lg:grid-cols-12">
        <!-- Left Brand Panel (Modern Cooperative FinTech & Retail Showcase) -->
        <aside class="relative hidden flex-col justify-between overflow-hidden bg-slate-950 px-10 py-10 text-white lg:col-span-5 lg:flex xl:col-span-6 xl:px-14">
            <!-- Refined Crimson & Slate Ambient Glow -->
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(185,28,28,0.34),transparent_56%),radial-gradient(ellipse_at_bottom_left,rgba(153,27,27,0.22),transparent_52%)]"
                aria-hidden="true"
            ></div>
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.06]"
                style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 26px 26px;"
                aria-hidden="true"
            ></div>

            <!-- Brand Identity Header -->
            <div class="relative z-10 flex items-center justify-between gap-4">
                <Link
                    href="/login"
                    class="flex cursor-pointer items-center gap-3.5 focus:outline-none"
                >
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl shadow-lg ring-1 ring-white/20"
                        :class="appSettings.institution_logo_url ? 'bg-white p-1' : 'bg-gradient-to-br from-kopdes-600 to-kopdes-800'"
                    >
                        <img
                            v-if="appSettings.institution_logo_url"
                            :src="appSettings.institution_logo_url"
                            :alt="institutionName"
                            class="h-full w-full object-contain"
                        />
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="h-6 w-6 text-white"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3L4 7v6c0 4.418 3.582 8 8 8s8-3.582 8-8V7l-8-4z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <span class="block text-base font-extrabold tracking-tight text-white">
                            KOPDES MERAH PUTIH
                        </span>
                        <span class="block text-xs font-medium text-slate-300">
                            Modern Cooperative FinTech &amp; Retail
                        </span>
                    </div>
                </Link>

                <StatusBadge
                    v-if="isDemoMode"
                    variant="amber"
                    size="xs"
                    dot
                    pulse
                >
                    Mode Demo
                </StatusBadge>
            </div>

            <!-- Center Hero Typography + Ecosystem Highlights -->
            <div class="relative z-10 my-auto max-w-lg py-8">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-semibold text-slate-200 backdrop-blur-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-kopdes-500"></span>
                    <span>Ekosistem Digital 1 Desa = 1 KOPDES</span>
                </div>

                <h2 class="mt-4 text-3xl font-extrabold leading-[1.18] tracking-tight text-white xl:text-[34px]">
                    Transparansi Keuangan &amp; Kasir Ritel Koperasi Desa
                </h2>

                <p class="mt-3 text-sm leading-relaxed text-slate-300">
                    Menghubungkan gerai POS desa, kontrol stok anti-minus, pembayaran Tunai &amp;
                    Midtrans Snap, hingga pelaporan Laba Bersih &amp; SHU anggota secara real-time.
                </p>

                <!-- Mini Executive Metrics Strip -->
                <div class="mt-6 grid grid-cols-3 gap-2.5">
                    <div class="rounded-xl border border-white/10 bg-white/[0.04] p-3 backdrop-blur-sm">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                            Hierarki
                        </div>
                        <div class="kopdes-num mt-1 text-sm font-extrabold text-white">
                            4 Level
                        </div>
                        <div class="mt-0.5 text-[10px] text-slate-400">
                            Nasional → Desa
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/[0.04] p-3 backdrop-blur-sm">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                            Akuntansi
                        </div>
                        <div class="kopdes-num mt-1 text-sm font-extrabold text-emerald-400">
                            HPP Snapshot
                        </div>
                        <div class="mt-0.5 text-[10px] text-slate-400">
                            Laba &amp; SHU Akurat
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/[0.04] p-3 backdrop-blur-sm">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                            Akses POS
                        </div>
                        <div class="kopdes-num mt-1 text-sm font-extrabold text-indigo-300">
                            RFID + PIN
                        </div>
                        <div class="mt-0.5 text-[10px] text-slate-400">
                            Kartu Member Portable
                        </div>
                    </div>
                </div>

                <!-- 3 Trust Pillars -->
                <div class="mt-6 space-y-3">
                    <div class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/[0.04] p-3.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-kopdes-700/90 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-white">
                                Laporan Keuangan &amp; Drill-Down Wilayah
                            </h3>
                            <p class="mt-0.5 text-[11px] leading-relaxed text-slate-300">
                                Omzet bersih pasca-retur, HPP snapshot, biaya operasional, hingga ekspor Excel &amp; PDF resmi.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/[0.04] p-3.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-600/90 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-white">
                                Kontrol Shift Kasir &amp; Audit Trail Immutable
                            </h3>
                            <p class="mt-0.5 text-[11px] leading-relaxed text-slate-300">
                                Tutup shift blind-count, kunci layar cepat F12, serta rekam jejak aktivitas admin yang tersensor.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Legal -->
            <div class="relative z-10 flex items-center justify-between border-t border-white/10 pt-5 text-xs text-slate-400">
                <span>&copy; {{ new Date().getFullYear() }} {{ institutionName }}</span>
                <span class="kopdes-num text-[11px] text-slate-300">KOPDES v3 &bull; Aman &amp; Terintegrasi</span>
            </div>
        </aside>

        <!-- Right Authentication Workspace -->
        <main class="flex min-h-screen flex-col justify-between px-4 py-6 sm:px-8 lg:col-span-7 lg:px-12 xl:col-span-6">
            <!-- Mobile Brand Header -->
            <div class="mx-auto mb-5 flex w-full max-w-md items-center justify-between gap-3 lg:hidden">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl shadow-sm"
                        :class="appSettings.institution_logo_url ? 'border border-slate-200 bg-white p-1' : 'bg-gradient-to-br from-kopdes-600 to-kopdes-800'"
                    >
                        <img
                            v-if="appSettings.institution_logo_url"
                            :src="appSettings.institution_logo_url"
                            :alt="institutionName"
                            class="h-full w-full object-contain"
                        />
                        <svg
                            v-else
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
                        <span class="block text-sm font-extrabold tracking-tight text-slate-900">
                            KOPDES Merah Putih
                        </span>
                        <span class="block text-[11px] font-medium text-slate-500">
                            Sistem Kasir &amp; Manajemen Keuangan
                        </span>
                    </div>
                </div>
                <StatusBadge v-if="isDemoMode" variant="amber" size="xs" dot pulse>
                    Demo
                </StatusBadge>
            </div>

            <!-- Centered Card Container -->
            <div
                class="mx-auto my-auto w-full"
                :class="maxWidth === 'lg' ? 'max-w-lg' : 'max-w-md'"
            >
                <div class="kopdes-card relative overflow-hidden p-6 sm:p-8">
                    <div
                        class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-kopdes-800 via-kopdes-600 to-kopdes-700"
                        aria-hidden="true"
                    ></div>
                    <slot />
                </div>
            </div>

            <!-- Workspace Footer -->
            <p class="mt-6 text-center text-xs text-slate-500">
                Koneksi terenkripsi &bull; {{ institutionName }}
            </p>
        </main>
    </div>
</template>
