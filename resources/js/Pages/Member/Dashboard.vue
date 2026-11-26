<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import MemberLayout from '@/Layouts/MemberLayout.vue';
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

const qrSvg = ref('');

const renderQrSvg = async () => {
    const code = member.value?.member_code;
    if (!code) {
        qrSvg.value = '';
        return;
    }

    try {
        qrSvg.value = await QRCode.toString(code, {
            type: 'svg',
            margin: 1,
            width: 112,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });
    } catch {
        qrSvg.value = '';
    }
};

onMounted(renderQrSvg);
watch(() => member.value?.member_code, renderQrSvg);

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
</script>

<template>
    <Head title="Beranda Portal Member" />

    <MemberLayout>
        <div class="space-y-4">
            <!-- Kartu Member Digital (Modern Card + SVG QR) -->
            <section
                aria-label="Kartu Member Digital"
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-kopdes-800 via-kopdes-700 to-red-900 p-5 text-white shadow-lg"
            >
                <!-- Subtle Decorative Circles -->
                <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute -bottom-12 -left-8 h-36 w-36 rounded-full bg-black/15"></div>

                <div class="relative flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
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
                            <div class="truncate text-[11px] font-bold uppercase tracking-wider text-red-100">
                                {{ appSettings.institution_name }}
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="text-[10px] font-medium uppercase tracking-widest text-red-200">
                                Kartu Anggota Digital
                            </div>
                            <h1 class="mt-0.5 truncate text-lg font-extrabold tracking-tight text-white">
                                {{ member.name }}
                            </h1>
                            <div class="kopdes-num mt-1 inline-block rounded-md bg-black/20 px-2 py-0.5 text-xs font-bold tracking-wider text-white">
                                {{ member.member_code }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1 text-[11px] text-red-100">
                            <div class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-red-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="truncate">KOPDES Asal: <strong class="text-white">{{ member.home_branch_name || '-' }}</strong></span>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                <span
                                    v-if="member.has_rfid_card"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/25 px-2 py-0.5 text-[10px] font-bold text-emerald-100 ring-1 ring-emerald-300/40"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                                    Kartu RFID Aktif
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-400/25 px-2 py-0.5 text-[10px] font-bold text-amber-100 ring-1 ring-amber-300/40"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span>
                                    RFID Belum Dipasang
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code SVG Box -->
                    <div class="shrink-0 rounded-xl bg-white p-2 shadow-md">
                        <div
                            v-if="qrSvg"
                            v-html="qrSvg"
                            class="h-[92px] w-[92px] [&>svg]:h-full [&>svg]:w-full"
                            aria-label="QR Code Kode Member"
                        ></div>
                        <div
                            v-else
                            class="flex h-[92px] w-[92px] items-center justify-center bg-slate-100 text-[10px] text-slate-400"
                        >
                            QR
                        </div>
                        <div class="mt-1 text-center text-[9px] font-bold uppercase tracking-wider text-slate-600">
                            Scan Kode
                        </div>
                    </div>
                </div>
            </section>

            <!-- Pemilih Periode -->
            <section class="flex items-center justify-between gap-2 rounded-xl bg-white p-2 shadow-sm ring-1 ring-slate-200/80">
                <div class="grid w-full grid-cols-3 gap-1">
                    <button
                        v-for="opt in periodOptions"
                        :key="opt.value"
                        type="button"
                        @click="selectPeriod(opt.value)"
                        class="rounded-lg px-2 py-1.5 text-xs font-bold transition"
                        :class="
                            summary.period === opt.value
                                ? 'bg-kopdes-700 text-white shadow-sm'
                                : 'text-slate-600 hover:bg-slate-100'
                        "
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </section>

            <!-- 4 Metrik KPI Utama -->
            <section class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <!-- 1. Total Belanja Bersih -->
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/80">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500">Total Belanja Bersih</span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                            {{ summary.paid_transactions_count }} trx
                        </span>
                    </div>
                    <div class="kopdes-num mt-1.5 text-xl font-extrabold text-slate-900">
                        {{ formatRp(summary.net_spending_total) }}
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">
                        <span v-if="summary.total_return_amount > 0">
                            Bruto {{ formatRp(summary.gross_spending_total) }} · Retur -{{ formatRp(summary.total_return_amount) }}
                        </span>
                        <span v-else>{{ summary.period_label }}</span>
                    </div>
                </div>

                <!-- 2. Total Hemat Harga Member -->
                <div class="rounded-xl bg-emerald-50/70 p-4 shadow-sm ring-1 ring-emerald-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-emerald-800">Total Hemat Member</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div class="kopdes-num mt-1.5 text-xl font-extrabold text-emerald-700">
                        {{ formatRp(summary.net_member_savings) }}
                    </div>
                    <div class="mt-1 text-[11px] text-emerald-700/80">
                        Penghematan bersih dari harga khusus anggota
                    </div>
                </div>

                <!-- 3. Rincian Belanja Asal vs Lintas Cabang -->
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/80">
                    <div class="text-xs font-semibold text-slate-500">
                        KOPDES Asal vs Lintas Cabang
                    </div>
                    <div class="mt-2 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-slate-700">KOPDES Asal</span>
                            <span class="kopdes-num font-bold text-slate-900">
                                {{ formatRp(summary.home_branch_spending) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-slate-700">Lintas Cabang</span>
                            <span class="kopdes-num font-bold text-blue-700">
                                {{ formatRp(summary.cross_branch_spending) }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-blue-100">
                        <div
                            class="h-full rounded-full bg-kopdes-700 transition-all"
                            :style="{ width: `${homePercent}%` }"
                        ></div>
                    </div>
                    <div class="mt-1 flex justify-between text-[10px] font-medium text-slate-500">
                        <span>Asal {{ homePercent }}%</span>
                        <span>Lintas {{ summary.net_spending_total > 0 ? 100 - homePercent : 0 }}%</span>
                    </div>
                </div>

                <!-- 4. Estimasi SHU Jasa Usaha Berjalan -->
                <div class="rounded-xl bg-amber-50/70 p-4 shadow-sm ring-1 ring-amber-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-amber-900">Estimasi SHU Berjalan</span>
                        <span class="kopdes-num rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                            Porsi {{ summary.spend_share_percent }}%
                        </span>
                    </div>
                    <div class="kopdes-num mt-1.5 text-xl font-extrabold text-amber-900">
                        {{ formatRp(summary.estimated_shu) }}
                    </div>
                    <p class="mt-1 text-[10px] font-semibold text-amber-800">
                        {{ summary.shu_disclaimer }}
                    </p>
                </div>
            </section>

            <!-- Transaksi Terbaru -->
            <section class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/80">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-extrabold text-slate-900">
                        Belanja Terbaru
                    </h2>
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
                        class="flex items-center justify-between gap-3 py-3 transition hover:bg-slate-50 -mx-2 px-2 rounded-lg"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="kopdes-num text-xs font-bold text-slate-900">
                                    {{ tx.invoice_number }}
                                </span>
                                <span
                                    v-if="tx.is_cross_branch"
                                    class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700"
                                >
                                    Lintas Cabang
                                </span>
                                <span
                                    v-else
                                    class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600"
                                >
                                    KOPDES Asal
                                </span>
                            </div>
                            <div class="mt-0.5 truncate text-[11px] text-slate-500">
                                {{ tx.branch_name }} · {{ formatDateTime(tx.paid_at) }}
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="kopdes-num text-xs font-extrabold text-slate-900">
                                {{ formatRp(tx.total_amount) }}
                            </div>
                            <div
                                v-if="tx.member_discount_total > 0"
                                class="kopdes-num text-[10px] font-bold text-emerald-600"
                            >
                                Hemat {{ formatRp(tx.member_discount_total) }}
                            </div>
                        </div>
                    </Link>
                </div>

                <div v-else class="py-6 text-center text-xs text-slate-500">
                    Belum ada riwayat transaksi belanja tercatat.
                </div>
            </section>
        </div>
    </MemberLayout>
</template>
