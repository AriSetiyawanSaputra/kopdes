<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import MemberLayout from '@/Layouts/MemberLayout.vue';
import Modal from '@/Components/Modal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import QRCode from 'qrcode';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    recentTransactions: {
        type: Array,
        default: () => [],
    },
    periodOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const member = computed(() => page.props.auth?.member ?? {});
const appSettings = computed(
    () =>
        page.props.app_settings ?? {
            institution_name: 'Koperasi Desa Merah Putih',
            institution_logo_url: null,
        },
);

const qrDataUrl = ref('');
const showQrModal = ref(false);

const renderQrCode = async () => {
    const code = member.value?.member_code;
    if (!code) {
        qrDataUrl.value = '';
        return;
    }

    try {
        qrDataUrl.value = await QRCode.toDataURL(code, {
            margin: 1,
            width: 320,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });
    } catch {
        qrDataUrl.value = '';
    }
};

onMounted(renderQrCode);
watch(() => member.value?.member_code, renderQrCode);

const selectPeriod = (period) => {
    router.get(
        route('member.dashboard'),
        { period },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const formatRp = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(val || 0));
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return dateStr;
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
};

const homePercent = computed(() => {
    const total = Number(props.summary.net_spending_total || 0);
    if (total <= 0) return 0;
    return Math.min(
        100,
        Math.round((Number(props.summary.home_branch_spending || 0) / total) * 100),
    );
});

const crossPercent = computed(() => {
    const total = Number(props.summary.net_spending_total || 0);
    if (total <= 0) return 0;
    return Math.max(0, 100 - homePercent.value);
});
</script>

<template>
    <Head title="Beranda Portal Member" />

    <MemberLayout>
        <div class="space-y-5">
            <!-- Header Sapaan Anggota & Filter Periode -->
            <section class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500">
                        Selamat datang kembali,
                    </p>
                    <h1 class="truncate text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        {{ member.name || 'Anggota KOPDES' }}
                    </h1>
                </div>

                <!-- Pemilih Periode -->
                <div
                    role="group"
                    aria-label="Filter Periode Manfaat Anggota"
                    class="grid grid-cols-3 gap-1 rounded-xl border border-slate-200/90 bg-white p-1 shadow-sm sm:w-auto sm:min-w-[270px]"
                >
                    <button
                        v-for="opt in periodOptions"
                        :key="opt.value"
                        type="button"
                        @click="selectPeriod(opt.value)"
                        class="cursor-pointer rounded-lg px-2.5 py-1.5 text-xs font-bold transition"
                        :class="
                            summary.period === opt.value
                                ? 'bg-kopdes-700 text-white shadow-sm'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                        "
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </section>

            <!-- Grid Responsif: 1 Kolom App-Like di Mobile, 2 Kolom Elegan di Desktop -->
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-12 lg:items-start">
                <!-- Kolom Kiri (5): Kartu Anggota Digital + Aksi Cepat -->
                <div class="space-y-4 lg:col-span-5">
                    <!-- Kartu Anggota Digital KOPDES Merah Putih -->
                    <section
                        aria-label="Kartu Anggota Digital KOPDES Merah Putih"
                        class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-kopdes-800 via-kopdes-700 to-rose-800 p-5 text-white shadow-lg ring-1 ring-inset ring-white/15"
                    >
                        <!-- Subtle Geometric Nusantara Pattern -->
                        <svg
                            class="pointer-events-none absolute inset-0 h-full w-full opacity-[0.07]"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <defs>
                                <pattern id="kopdes-card-grid" width="28" height="28" patternUnits="userSpaceOnUse">
                                    <path d="M 14 0 L 28 14 L 14 28 L 0 14 Z" fill="none" stroke="white" stroke-width="1" />
                                </pattern>
                            </defs>
                            <rect width="100%" height="100%" fill="url(#kopdes-card-grid)" />
                        </svg>
                        <div class="pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full bg-white/10 blur-xl"></div>
                        <div class="pointer-events-none absolute -bottom-14 -left-10 h-40 w-40 rounded-full bg-black/20 blur-xl"></div>

                        <!-- Top Strip: Lembaga + Status Aktif -->
                        <div class="relative flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <div
                                    v-if="appSettings.institution_logo_url"
                                    class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white p-0.5 shadow-sm"
                                >
                                    <img
                                        :src="appSettings.institution_logo_url"
                                        :alt="appSettings.institution_name"
                                        class="h-full w-full object-contain"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-[11px] font-extrabold uppercase tracking-wider text-white">
                                        {{ appSettings.institution_name }}
                                    </div>
                                    <div class="text-[9px] font-semibold uppercase tracking-[0.18em] text-rose-200">
                                        Kartu Anggota Digital
                                    </div>
                                </div>
                            </div>

                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-400/20 px-2.5 py-0.5 text-[10px] font-bold text-emerald-100 ring-1 ring-inset ring-emerald-300/40">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                                Aktif
                            </span>
                        </div>

                        <!-- Card Body: Member Info + Clickable Enlarge QR -->
                        <div class="relative mt-5 flex items-end justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-[10px] font-medium uppercase tracking-widest text-rose-200">
                                    Pemegang Kartu
                                </div>
                                <div class="mt-0.5 truncate text-lg font-extrabold tracking-tight text-white">
                                    {{ member.name }}
                                </div>
                                <div class="kopdes-num mt-1.5 inline-flex items-center rounded-lg bg-black/25 px-2.5 py-1 text-xs font-bold tracking-wider text-white ring-1 ring-inset ring-white/15">
                                    {{ member.member_code }}
                                </div>

                                <div class="mt-3.5 space-y-1.5 text-[11px] text-rose-100">
                                    <div class="flex items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-rose-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="truncate">
                                            KOPDES Asal: <strong class="text-white">{{ member.home_branch_name || '-' }}</strong>
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                        <span
                                            v-if="member.has_rfid_card"
                                            class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-bold text-white ring-1 ring-inset ring-white/25"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                            </svg>
                                            Kartu RFID Terpasang
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-400/25 px-2 py-0.5 text-[10px] font-bold text-amber-100 ring-1 ring-inset ring-amber-300/40"
                                        >
                                            RFID Belum Dipasang
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Clickable QR Code Box (Safe Data URL <img>) -->
                            <button
                                type="button"
                                @click="showQrModal = true"
                                class="group shrink-0 cursor-pointer rounded-xl bg-white p-2 text-center shadow-md transition-transform duration-150 hover:scale-[1.03] focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-kopdes-800"
                                title="Ketuk untuk memperbesar QR Code"
                            >
                                <img
                                    v-if="qrDataUrl"
                                    :src="qrDataUrl"
                                    alt="QR Code Kode Member"
                                    class="h-[88px] w-[88px] object-contain"
                                />
                                <div
                                    v-else
                                    class="flex h-[88px] w-[88px] items-center justify-center rounded bg-slate-100 text-[10px] font-semibold text-slate-400"
                                >
                                    QR
                                </div>
                                <div class="mt-1 flex items-center justify-center gap-1 text-[9px] font-extrabold uppercase tracking-wider text-kopdes-700 group-hover:text-kopdes-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                    </svg>
                                    Perbesar
                                </div>
                            </button>
                        </div>
                    </section>

                    <!-- Shortcut Aksi Cepat Anggota -->
                    <div class="grid grid-cols-2 gap-3">
                        <Link
                            :href="route('member.transactions.index')"
                            class="kopdes-card flex items-center gap-3 p-3.5 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50/40"
                        >
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-kopdes-50 text-kopdes-700 ring-1 ring-inset ring-kopdes-200/70">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-xs font-bold text-slate-900">Semua Struk</div>
                                <div class="truncate text-[11px] text-slate-500">Riwayat belanja</div>
                            </div>
                        </Link>

                        <Link
                            :href="route('member.security')"
                            class="kopdes-card flex items-center gap-3 p-3.5 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50/40"
                        >
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-xs font-bold text-slate-900">Ganti PIN</div>
                                <div class="truncate text-[11px] text-slate-500">Keamanan portal</div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Kolom Kanan (7): Ringkasan Manfaat Anggota & Riwayat Terbaru -->
                <div class="space-y-5 lg:col-span-7">
                    <!-- 3 Kartu Ringkasan Manfaat Anggota -->
                    <section aria-label="Ringkasan Manfaat Anggota" class="space-y-3">
                        <!-- 1. Total Belanja + Pecahan KOPDES Asal vs Lintas Cabang -->
                        <div class="kopdes-card relative overflow-hidden p-5">
                            <div class="absolute inset-x-0 top-0 h-1 bg-kopdes-700"></div>
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                        Total Belanja Bersih ({{ summary.period_label }})
                                    </span>
                                    <div class="kopdes-num mt-1.5 text-2xl font-extrabold tracking-tight text-slate-900">
                                        {{ formatRp(summary.net_spending_total) }}
                                    </div>
                                    <div v-if="summary.total_return_amount > 0" class="kopdes-num mt-0.5 text-[11px] text-slate-500">
                                        Bruto {{ formatRp(summary.gross_spending_total) }} &middot; Retur &minus;{{ formatRp(summary.total_return_amount) }}
                                    </div>
                                </div>
                                <StatusBadge variant="slate" size="xs">
                                    <span class="kopdes-num">{{ summary.paid_transactions_count }} Transaksi</span>
                                </StatusBadge>
                            </div>

                            <!-- Pecahan KOPDES Asal vs Lintas Cabang -->
                            <div class="mt-4 rounded-xl border border-slate-200/80 bg-slate-50/70 p-3.5">
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <div class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                                            <span class="h-2 w-2 rounded-full bg-kopdes-700"></span>
                                            KOPDES Asal ({{ homePercent }}%)
                                        </div>
                                        <div class="kopdes-num mt-0.5 text-sm font-extrabold text-slate-900">
                                            {{ formatRp(summary.home_branch_spending) }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="flex items-center justify-end gap-1.5 text-[11px] font-semibold text-indigo-700">
                                            <span>Lintas Cabang ({{ crossPercent }}%)</span>
                                            <span class="h-2 w-2 rounded-full bg-indigo-600"></span>
                                        </div>
                                        <div class="kopdes-num mt-0.5 text-sm font-extrabold text-indigo-700">
                                            {{ formatRp(summary.cross_branch_spending) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2.5 flex h-2 w-full overflow-hidden rounded-full bg-slate-200">
                                    <div
                                        class="h-full bg-kopdes-700 transition-all duration-300"
                                        :style="{ width: `${homePercent}%` }"
                                    ></div>
                                    <div
                                        class="h-full bg-indigo-600 transition-all duration-300"
                                        :style="{ width: `${crossPercent}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- 2 & 3. Total Hemat Member + Estimasi SHU Berjalan -->
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <!-- Total Hemat Harga Member -->
                            <div class="kopdes-card relative overflow-hidden border-emerald-200/90 bg-emerald-50/60 p-4">
                                <div class="absolute inset-x-0 top-0 h-1 bg-emerald-600"></div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-900">
                                        Total Hemat Member
                                    </span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="kopdes-num mt-2 text-2xl font-extrabold text-emerald-700">
                                    {{ formatRp(summary.net_member_savings) }}
                                </div>
                                <p class="mt-1 text-[11px] font-medium text-emerald-800/80">
                                    Penghematan bersih dari harga khusus anggota
                                </p>
                            </div>

                            <!-- Estimasi SHU Jasa Usaha Berjalan -->
                            <div class="kopdes-card relative overflow-hidden border-amber-200/90 bg-amber-50/70 p-4">
                                <div class="absolute inset-x-0 top-0 h-1 bg-amber-500"></div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-amber-900">
                                        Estimasi SHU Berjalan
                                    </span>
                                    <StatusBadge variant="amber" size="xs">
                                        <span class="kopdes-num">Porsi {{ summary.spend_share_percent }}%</span>
                                    </StatusBadge>
                                </div>
                                <div class="kopdes-num mt-2 text-2xl font-extrabold text-amber-900">
                                    {{ formatRp(summary.estimated_shu) }}
                                </div>
                                <p class="mt-1 text-[10px] font-semibold leading-snug text-amber-800">
                                    {{ summary.shu_disclaimer }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Daftar Belanja Terbaru (Card List Mobile-First) -->
                    <section class="kopdes-card overflow-hidden">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                            <div>
                                <h2 class="text-sm font-extrabold text-slate-900">
                                    Belanja Terbaru
                                </h2>
                                <p class="text-[11px] text-slate-500">
                                    Ketuk transaksi untuk melihat struk digital lengkap
                                </p>
                            </div>
                            <Link
                                :href="route('member.transactions.index')"
                                class="text-xs font-bold text-kopdes-700 hover:text-kopdes-800 hover:underline"
                            >
                                Lihat Semua &rarr;
                            </Link>
                        </div>

                        <div v-if="recentTransactions.length > 0" class="divide-y divide-slate-100">
                            <Link
                                v-for="tx in recentTransactions"
                                :key="tx.id"
                                :href="route('member.transactions.show', tx.id)"
                                class="flex items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-slate-50/90"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="truncate text-xs font-extrabold text-slate-900">
                                            {{ tx.branch_name }}
                                        </span>
                                        <StatusBadge v-if="tx.is_cross_branch" variant="indigo" size="xs">
                                            Lintas Cabang
                                        </StatusBadge>
                                        <StatusBadge v-else variant="slate" size="xs">
                                            KOPDES Asal
                                        </StatusBadge>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                                        <span class="kopdes-num font-semibold text-slate-600">{{ tx.invoice_number }}</span>
                                        <span>&middot;</span>
                                        <span>{{ formatDateTime(tx.paid_at) }}</span>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-2 text-right">
                                    <div>
                                        <div class="kopdes-num text-sm font-extrabold text-slate-900">
                                            {{ formatRp(tx.total_amount) }}
                                        </div>
                                        <div
                                            v-if="tx.member_discount_total > 0"
                                            class="kopdes-num text-[10px] font-bold text-emerald-700"
                                        >
                                            Hemat {{ formatRp(tx.member_discount_total) }}
                                        </div>
                                    </div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                            </Link>
                        </div>

                        <EmptyState
                            v-else
                            title="Belum ada transaksi belanja"
                            description="Riwayat belanja Anda di KOPDES asal maupun cabang desa lain akan tampil di sini setelah transaksi lunas."
                        />
                    </section>
                </div>
            </div>
        </div>

        <!-- Modal Perbesar QR Code Kartu Anggota (Aman 100% <img :src="qrDataUrl">) -->
        <Modal :show="showQrModal" max-width="sm" @close="showQrModal = false">
            <div class="p-6 text-center">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div class="text-left">
                        <h3 class="text-base font-extrabold text-slate-900">
                            QR Code Anggota KOPDES
                        </h3>
                        <p class="text-[11px] text-slate-500">
                            Tunjukkan ke kasir POS saat berbelanja
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showQrModal = false"
                        aria-label="Tutup QR Code"
                        class="cursor-pointer rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-5 inline-flex flex-col items-center rounded-2xl border-2 border-slate-200 bg-white p-4 shadow-sm">
                    <img
                        v-if="qrDataUrl"
                        :src="qrDataUrl"
                        alt="QR Code Kode Member Diperbesar"
                        class="h-56 w-56 object-contain"
                    />
                    <div
                        v-else
                        class="flex h-56 w-56 items-center justify-center rounded-xl bg-slate-100 text-xs text-slate-400"
                    >
                        Memuat QR...
                    </div>
                </div>

                <div class="mt-4">
                    <div class="text-sm font-extrabold text-slate-900">
                        {{ member.name }}
                    </div>
                    <div class="kopdes-num mt-1 inline-block rounded-lg bg-kopdes-50 px-3 py-1 text-sm font-extrabold tracking-wider text-kopdes-700 ring-1 ring-inset ring-kopdes-200">
                        {{ member.member_code }}
                    </div>
                    <div class="mt-1.5 text-xs text-slate-500">
                        {{ member.home_branch_name || appSettings.institution_name }}
                    </div>
                </div>

                <button
                    type="button"
                    @click="showQrModal = false"
                    class="btn-kopdes-secondary mt-5 w-full"
                >
                    Tutup
                </button>
            </div>
        </Modal>
    </MemberLayout>
</template>
