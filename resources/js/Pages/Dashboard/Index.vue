<script setup>
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    kpis: {
        type: Object,
        required: true,
    },
    paymentBreakdown: {
        type: Object,
        required: true,
    },
    branchSummaries: {
        type: Array,
        default: () => [],
    },
    drillDown: {
        type: Object,
        default: () => ({
            provinces: [],
            regencies: [],
            branches: [],
            can_filter_province: false,
            can_filter_regency: false,
            can_filter_branch: false,
        }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const filterForm = reactive({
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
    payment_method: props.filters.payment_method ?? 'all',
    province_id: props.filters.province_id ?? '',
    regency_id: props.filters.regency_id ?? '',
    branch_id: props.filters.branch_id ?? '',
});

const grossRevenue = computed(() => Number(props.kpis.gross_revenue ?? props.kpis.total_revenue ?? 0));
const totalReturns = computed(() => Number(props.kpis.total_returns || 0));
const totalRevenue = computed(() => Number(props.kpis.net_revenue ?? props.kpis.total_revenue ?? 0));
const grossCogs = computed(() => Number(props.kpis.gross_cogs ?? props.kpis.total_cogs ?? 0));
const restockedCogs = computed(() => Number(props.kpis.restocked_cogs || 0));
const damagedCogs = computed(() => Number(props.kpis.damaged_cogs || 0));
const totalCogs = computed(() => Number(props.kpis.net_cogs ?? props.kpis.total_cogs ?? 0));
const grossProfit = computed(() => Number(props.kpis.gross_profit || 0));
const totalTransactions = computed(() => Number(props.kpis.total_transactions || 0));
const grossMarginPercent = computed(() => Number(props.kpis.gross_margin_percent || 0));
const totalOperationalExpenses = computed(() => Number(props.kpis.total_operational_expenses || 0));
const netProfit = computed(() => Number(props.kpis.net_profit ?? (grossProfit.value - totalOperationalExpenses.value)));
const netMarginPercentage = computed(() => Number(props.kpis.net_margin_percentage || 0));
const totalMemberDiscount = computed(() => Number(props.kpis.total_member_discount || 0));

const avgTransactionValue = computed(() => {
    if (totalTransactions.value <= 0) {
        return 0;
    }
    return totalRevenue.value / totalTransactions.value;
});

const cashSharePercent = computed(() => {
    if (totalRevenue.value <= 0) {
        return 0;
    }
    return Math.round(
        (Number(props.paymentBreakdown.cash?.total_revenue || 0) / totalRevenue.value) * 100,
    );
});

const midtransSharePercent = computed(() => {
    if (totalRevenue.value <= 0) {
        return 0;
    }
    return Math.round(
        (Number(props.paymentBreakdown.midtrans?.total_revenue || 0) / totalRevenue.value) * 100,
    );
});

const cogsRatioPercent = computed(() => {
    if (totalRevenue.value <= 0) {
        return 0;
    }
    return Math.min(100, Math.max(0, Math.round((totalCogs.value / totalRevenue.value) * 100)));
});

const profitRatioPercent = computed(() => {
    if (totalRevenue.value <= 0) {
        return 0;
    }
    return Math.min(100, Math.max(0, 100 - cogsRatioPercent.value));
});

const branchTotals = computed(() => {
    const totals = props.branchSummaries.reduce(
        (acc, row) => {
            acc.transactions += Number(row.total_transactions || 0);
            acc.cashRevenue += Number(row.cash_revenue || 0);
            acc.midtransRevenue += Number(row.midtrans_revenue || 0);
            acc.returns += Number(row.total_returns || 0);
            acc.revenue += Number(row.net_revenue ?? row.total_revenue ?? 0);
            acc.profit += Number(row.gross_profit || 0);
            acc.operationalExpenses += Number(row.operational_expenses || 0);
            acc.netProfit += Number(row.net_profit ?? (Number(row.gross_profit || 0) - Number(row.operational_expenses || 0)));
            return acc;
        },
        { transactions: 0, cashRevenue: 0, midtransRevenue: 0, returns: 0, revenue: 0, profit: 0, operationalExpenses: 0, netProfit: 0 },
    );

    return {
        ...totals,
        margin: totals.revenue > 0 ? (totals.profit / totals.revenue) * 100 : 0,
        netMargin: totals.revenue > 0 ? (totals.netProfit / totals.revenue) * 100 : 0,
    };
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('id-ID').format(Number(value || 0));
};

const formatDateInput = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const buildQueryParams = () => ({
    start_date: filterForm.start_date || undefined,
    end_date: filterForm.end_date || undefined,
    payment_method:
        filterForm.payment_method !== 'all' ? filterForm.payment_method : undefined,
    province_id:
        props.drillDown.can_filter_province && filterForm.province_id
            ? filterForm.province_id
            : undefined,
    regency_id:
        props.drillDown.can_filter_regency && filterForm.regency_id
            ? filterForm.regency_id
            : undefined,
    branch_id:
        props.drillDown.can_filter_branch && filterForm.branch_id
            ? filterForm.branch_id
            : undefined,
});

const applyFilters = () => {
    router.get(route('dashboard'), buildQueryParams(), {
        preserveState: true,
        replace: true,
    });
};

const applyDatePreset = (preset) => {
    const today = new Date();
    if (preset === 'today') {
        const d = formatDateInput(today);
        filterForm.start_date = d;
        filterForm.end_date = d;
    } else if (preset === 'last7') {
        const start = new Date(today);
        start.setDate(today.getDate() - 6);
        filterForm.start_date = formatDateInput(start);
        filterForm.end_date = formatDateInput(today);
    } else {
        const start = new Date(today.getFullYear(), today.getMonth(), 1);
        const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        filterForm.start_date = formatDateInput(start);
        filterForm.end_date = formatDateInput(end);
    }
    applyFilters();
};

const onProvinceChange = () => {
    filterForm.regency_id = '';
    filterForm.branch_id = '';
    applyFilters();
};

const onRegencyChange = () => {
    filterForm.branch_id = '';
    applyFilters();
};

const resetFilters = () => {
    router.get(route('dashboard'));
};

const salesExportUrl = (format) => {
    try {
        return route('reports.sales.export', {
            format,
            ...buildQueryParams(),
        });
    } catch {
        return '#';
    }
};

const branchRevenueShare = (branchRevenue) => {
    if (branchTotals.value.revenue <= 0) {
        return 0;
    }
    return Math.round((Number(branchRevenue || 0) / branchTotals.value.revenue) * 100);
};
</script>

<template>
    <Head title="Dashboard Keuangan KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                            Dashboard Keuangan KOPDES
                        </h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                            Basis Snapshot HPP &bull; Lunas (Paid)
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        Periode aktif:
                        <span class="kopdes-num font-semibold text-slate-800">
                            {{ filters.start_date }}
                        </span>
                        s.d.
                        <span class="kopdes-num font-semibold text-slate-800">
                            {{ filters.end_date }}
                        </span>
                    </p>
                </div>

                <!-- Quick Export & Report Navigation -->
                <div class="flex flex-wrap items-center gap-2">
                    <a :href="salesExportUrl('pdf')" class="btn-kopdes-outline">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export PDF
                    </a>
                    <a :href="salesExportUrl('excel')" class="btn-kopdes-emerald">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        Export Excel
                    </a>
                    <Link
                        :href="route('reports.sales', buildQueryParams())"
                        class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                    >
                        Rincian Penjualan
                    </Link>
                    <Link
                        :href="route('reports.members', buildQueryParams())"
                        class="btn-kopdes-primary !px-3.5 !py-2 !text-xs"
                    >
                        Rekap Member (SHU)
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Filter Periode & Drill-Down Wilayah Berjenjang -->
                <section class="kopdes-card p-5" aria-label="Filter Laporan Keuangan">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-kopdes-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <h2 class="text-sm font-bold text-slate-900">
                                Filter Periode &amp; Drill-Down Wilayah
                            </h2>
                        </div>

                        <!-- Preset Periode Cepat -->
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button
                                type="button"
                                class="cursor-pointer rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                @click="applyDatePreset('today')"
                            >
                                Hari Ini
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                @click="applyDatePreset('last7')"
                            >
                                7 Hari Terakhir
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                @click="applyDatePreset('month')"
                            >
                                Bulan Ini
                            </button>
                        </div>
                    </div>

                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-6"
                    >
                        <div>
                            <label for="dash-start-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Mulai
                            </label>
                            <input
                                id="dash-start-date"
                                v-model="filterForm.start_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="dash-end-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Akhir
                            </label>
                            <input
                                id="dash-end-date"
                                v-model="filterForm.end_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="dash-payment-method" class="block text-xs font-semibold text-slate-700">
                                Metode Bayar
                            </label>
                            <select
                                id="dash-payment-method"
                                v-model="filterForm.payment_method"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="all">Semua Metode</option>
                                <option value="cash">Tunai (Cash)</option>
                                <option value="midtrans">Non-Tunai (Midtrans)</option>
                            </select>
                        </div>

                        <div v-if="drillDown.can_filter_province">
                            <label for="dash-province" class="block text-xs font-semibold text-slate-700">
                                Provinsi
                            </label>
                            <select
                                id="dash-province"
                                v-model="filterForm.province_id"
                                @change="onProvinceChange"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Provinsi</option>
                                <option
                                    v-for="prov in drillDown.provinces"
                                    :key="prov.id"
                                    :value="prov.id"
                                >
                                    {{ prov.name }}
                                </option>
                            </select>
                        </div>

                        <div v-if="drillDown.can_filter_regency">
                            <label for="dash-regency" class="block text-xs font-semibold text-slate-700">
                                Kabupaten / Kota
                            </label>
                            <select
                                id="dash-regency"
                                v-model="filterForm.regency_id"
                                @change="onRegencyChange"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Kabupaten/Kota</option>
                                <option
                                    v-for="reg in drillDown.regencies"
                                    :key="reg.id"
                                    :value="reg.id"
                                >
                                    {{ reg.name }}
                                </option>
                            </select>
                        </div>

                        <div v-if="drillDown.can_filter_branch">
                            <label for="dash-branch" class="block text-xs font-semibold text-slate-700">
                                Cabang KOPDES
                            </label>
                            <select
                                id="dash-branch"
                                v-model="filterForm.branch_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Cabang</option>
                                <option
                                    v-for="b in drillDown.branches"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.code }} — {{ b.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
                            <PrimaryButton type="submit">
                                Terapkan Filter
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="resetFilters">
                                Reset Filter
                            </SecondaryButton>
                        </div>
                    </form>
                </section>

                <!-- 4 Kartu KPI Keuangan Utama (Omzet -> Laba Kotor -> Biaya Operasional -> Laba Bersih) -->
                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan KPI Keuangan">
                    <!-- KPI 1: Omzet Bersih (Pasca-Retur) -->
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div class="absolute inset-x-0 top-0 h-1 bg-kopdes-700"></div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Omzet Bersih (Pasca-Retur)
                                </p>
                                <p class="kopdes-num mt-2 truncate text-2xl font-extrabold text-slate-900">
                                    {{ formatRupiah(totalRevenue) }}
                                </p>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-kopdes-50 text-kopdes-700 ring-1 ring-kopdes-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5 text-xs text-slate-600">
                            <span>Bruto {{ formatRupiah(grossRevenue) }}</span>
                            <span class="kopdes-num font-semibold" :class="totalReturns > 0 ? 'text-rose-700' : 'text-slate-500'">
                                Retur {{ formatRupiah(totalReturns) }}
                            </span>
                        </div>
                    </div>

                    <!-- KPI 2: Total Laba Kotor -->
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div class="absolute inset-x-0 top-0 h-1 bg-emerald-600"></div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Laba Kotor (Pasca-Retur)
                                </p>
                                <p class="kopdes-num mt-2 truncate text-2xl font-extrabold text-emerald-700">
                                    {{ formatRupiah(grossProfit) }}
                                </p>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5 text-xs text-slate-600">
                            <span>HPP Bersih {{ formatRupiah(totalCogs) }}</span>
                            <span class="kopdes-num font-bold text-amber-700">
                                {{ grossMarginPercent.toFixed(2) }}%
                            </span>
                        </div>
                    </div>

                    <!-- KPI 3: Total Biaya Operasional -->
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div class="absolute inset-x-0 top-0 h-1 bg-rose-600"></div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Total Biaya Operasional
                                </p>
                                <p class="kopdes-num mt-2 truncate text-2xl font-extrabold text-rose-700">
                                    {{ formatRupiah(totalOperationalExpenses) }}
                                </p>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-700 ring-1 ring-rose-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5 text-xs text-slate-600">
                            <span>Beban Cabang Aktif</span>
                            <Link
                                :href="route('expenses.index', buildQueryParams())"
                                class="font-semibold text-kopdes-700 hover:underline"
                            >
                                Rincian Biaya &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- KPI 4: Laba Bersih / SHU Bruto -->
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div
                            class="absolute inset-x-0 top-0 h-1"
                            :class="netProfit >= 0 ? 'bg-blue-600' : 'bg-rose-600'"
                        ></div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Laba Bersih (Dasar SHU)
                                </p>
                                <p
                                    class="kopdes-num mt-2 truncate text-2xl font-extrabold"
                                    :class="netProfit >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                >
                                    {{ formatRupiah(netProfit) }}
                                </p>
                            </div>
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ring-1"
                                :class="
                                    netProfit >= 0
                                        ? 'bg-blue-50 text-blue-700 ring-blue-100'
                                        : 'bg-rose-50 text-rose-700 ring-rose-100'
                                "
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5 text-xs text-slate-600">
                            <span>Margin Bersih</span>
                            <span class="kopdes-num font-bold text-amber-700">
                                {{ netMarginPercentage.toFixed(2) }}%
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Baris Analitik Visual 2 Kolom -->
                <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <!-- Kolom Kiri (7): Perbandingan Kanal Pembayaran -->
                    <div class="kopdes-card p-5 lg:col-span-7">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">
                                    Distribusi Kanal Pembayaran POS
                                </h2>
                                <p class="mt-0.5 text-xs text-slate-600">
                                    Proporsi omzet dan laba kotor antara pembayaran Tunai dan Midtrans Snap
                                </p>
                            </div>
                            <span class="rounded-md bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                Tunai vs Midtrans
                            </span>
                        </div>

                        <!-- Stacked Comparison Bar -->
                        <div class="mt-4">
                            <div class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full bg-emerald-600 transition-all duration-300"
                                    :style="{ width: `${cashSharePercent}%` }"
                                    :title="`Tunai: ${cashSharePercent}%`"
                                ></div>
                                <div
                                    class="h-full bg-blue-600 transition-all duration-300"
                                    :style="{ width: `${midtransSharePercent}%` }"
                                    :title="`Midtrans: ${midtransSharePercent}%`"
                                ></div>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-xs font-semibold">
                                <span class="flex items-center gap-1.5 text-emerald-700">
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-600"></span>
                                    Tunai ({{ cashSharePercent }}%)
                                </span>
                                <span class="flex items-center gap-1.5 text-blue-700">
                                    <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                    Midtrans ({{ midtransSharePercent }}%)
                                </span>
                            </div>
                        </div>

                        <!-- Detail 2 Kanal -->
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-900">
                                        Tunai (Cash)
                                    </span>
                                    <span class="kopdes-num rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">
                                        {{ formatNumber(paymentBreakdown.cash?.transactions_count) }} Trx
                                    </span>
                                </div>
                                <p class="kopdes-num mt-2 text-xl font-extrabold text-slate-900">
                                    {{ formatRupiah(paymentBreakdown.cash?.total_revenue) }}
                                </p>
                                <p class="mt-1 text-xs text-slate-600">
                                    Laba Kotor:
                                    <strong class="kopdes-num text-emerald-800">
                                        {{ formatRupiah(paymentBreakdown.cash?.gross_profit) }}
                                    </strong>
                                </p>
                            </div>

                            <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-blue-900">
                                        Midtrans Snap
                                    </span>
                                    <span class="kopdes-num rounded-full bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-800">
                                        {{ formatNumber(paymentBreakdown.midtrans?.transactions_count) }} Trx
                                    </span>
                                </div>
                                <p class="kopdes-num mt-2 text-xl font-extrabold text-slate-900">
                                    {{ formatRupiah(paymentBreakdown.midtrans?.total_revenue) }}
                                </p>
                                <p class="mt-1 text-xs text-slate-600">
                                    Laba Kotor:
                                    <strong class="kopdes-num text-blue-800">
                                        {{ formatRupiah(paymentBreakdown.midtrans?.gross_profit) }}
                                    </strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan (5): Struktur Laba Rugi Lengkap (Omzet -> HPP -> Opex -> Laba Bersih) -->
                    <div class="kopdes-card flex flex-col justify-between p-5 lg:col-span-5">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Ringkasan Laba Rugi &amp; Dasar SHU
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-600">
                                Alur lengkap dari Omzet hingga Laba Bersih setelah Biaya Operasional
                            </p>

                            <dl class="mt-4 space-y-1.5 text-sm">
                                <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5">
                                    <dt class="text-xs font-semibold text-slate-700">
                                        Omzet Bruto ({{ formatNumber(totalTransactions) }} Trx Paid)
                                    </dt>
                                    <dd class="kopdes-num text-sm font-bold text-slate-900">
                                        {{ formatRupiah(grossRevenue) }}
                                    </dd>
                                </div>

                                <div class="flex items-center justify-between rounded-lg bg-rose-50/50 px-3 py-1.5">
                                    <dt class="text-xs font-semibold text-rose-800">
                                        (&minus;) Total Retur &amp; Void
                                    </dt>
                                    <dd class="kopdes-num text-sm font-semibold text-rose-700">
                                        {{ formatRupiah(totalReturns) }}
                                    </dd>
                                </div>

                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-1.5">
                                    <dt class="text-xs font-bold text-slate-900">
                                        (=) Omzet Bersih
                                    </dt>
                                    <dd class="kopdes-num text-sm font-extrabold text-slate-900">
                                        {{ formatRupiah(totalRevenue) }}
                                    </dd>
                                </div>

                                <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5">
                                    <dt class="text-xs font-semibold text-slate-600">
                                        (&minus;) HPP Bersih ({{ cogsRatioPercent }}%)
                                        <span v-if="restockedCogs > 0" class="block text-[10px] font-normal text-slate-500">
                                            Bruto {{ formatRupiah(grossCogs) }} &minus; Restock {{ formatRupiah(restockedCogs) }}
                                        </span>
                                    </dt>
                                    <dd class="kopdes-num text-sm font-semibold text-slate-700">
                                        {{ formatRupiah(totalCogs) }}
                                    </dd>
                                </div>

                                <div class="flex items-center justify-between rounded-lg bg-amber-50/70 px-3 py-1.5">
                                    <dt class="text-xs font-semibold text-amber-900">
                                        Info: Kerugian Barang Rusak Retur
                                    </dt>
                                    <dd class="kopdes-num text-xs font-bold text-amber-800">
                                        {{ formatRupiah(damagedCogs) }}
                                    </dd>
                                </div>

                                <div class="flex items-center justify-between rounded-lg bg-emerald-50/70 px-3 py-1.5">
                                    <dt class="text-xs font-semibold text-emerald-900">
                                        (=) Laba Kotor ({{ profitRatioPercent }}%)
                                    </dt>
                                    <dd class="kopdes-num text-sm font-bold text-emerald-700">
                                        {{ formatRupiah(grossProfit) }}
                                    </dd>
                                </div>

                                <div class="flex items-center justify-between rounded-lg bg-rose-50/70 px-3 py-1.5">
                                    <dt class="text-xs font-semibold text-rose-900">
                                        (&minus;) Biaya Operasional
                                    </dt>
                                    <dd class="kopdes-num text-sm font-bold text-rose-700">
                                        {{ formatRupiah(totalOperationalExpenses) }}
                                    </dd>
                                </div>

                                <div
                                    class="flex items-center justify-between rounded-lg border px-3 py-2"
                                    :class="
                                        netProfit >= 0
                                            ? 'border-emerald-200 bg-emerald-50'
                                            : 'border-rose-200 bg-rose-50'
                                    "
                                >
                                    <dt class="text-xs font-extrabold uppercase tracking-wide text-slate-900">
                                        (=) Laba Bersih / Dasar SHU
                                    </dt>
                                    <dd
                                        class="kopdes-num text-base font-extrabold"
                                        :class="netProfit >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                    >
                                        {{ formatRupiah(netProfit) }}
                                    </dd>
                                </div>

                                <div class="flex items-center justify-between rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-1.5">
                                    <dt class="text-xs font-semibold text-blue-900">
                                        Info: Total Hemat Anggota Member
                                    </dt>
                                    <dd class="kopdes-num text-xs font-bold text-blue-700">
                                        {{ formatRupiah(totalMemberDiscount) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="mt-4 border-t border-slate-100 pt-3.5">
                            <Link
                                :href="route('reports.members', buildQueryParams())"
                                class="flex cursor-pointer items-center justify-between rounded-lg border border-kopdes-200 bg-kopdes-50/80 px-3.5 py-2.5 text-xs font-semibold text-kopdes-800 transition-colors hover:bg-kopdes-100"
                            >
                                <span>Lihat Perhitungan SHU &amp; Rekap Anggota</span>
                                <span aria-hidden="true">&rarr;</span>
                            </Link>
                        </div>
                    </div>
                </section>

                <!-- Tabel Ringkasan Kinerja Cabang KOPDES -->
                <section class="kopdes-card overflow-hidden">
                    <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Ringkasan Kinerja per Cabang KOPDES
                            </h2>
                            <p class="text-xs text-slate-600">
                                Agregasi omzet, kanal pembayaran, dan laba kotor snapshot tiap desa pada cakupan wilayah Anda
                            </p>
                        </div>
                        <Link
                            :href="route('reports.sales', buildQueryParams())"
                            class="inline-flex cursor-pointer items-center gap-1 text-xs font-bold text-kopdes-700 transition-colors hover:text-kopdes-900 hover:underline"
                        >
                            Lihat Rincian Transaksi &rarr;
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Cabang KOPDES
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Transaksi
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Omzet Tunai
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Omzet Midtrans
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Total Omzet
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Laba Kotor
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Biaya Ops
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Laba Bersih
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Net %
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="row in branchSummaries"
                                    :key="row.branch_id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <span class="kopdes-num rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-700">
                                                {{ row.branch_code }}
                                            </span>
                                            <span class="font-semibold text-slate-900">
                                                {{ row.branch_name }}
                                            </span>
                                        </div>
                                        <div class="mt-1 flex items-center gap-2">
                                            <span class="text-xs text-slate-500">
                                                {{ row.regency_name || '' }}
                                            </span>
                                            <div class="hidden h-1.5 w-24 overflow-hidden rounded-full bg-slate-100 sm:block">
                                                <div
                                                    class="h-full rounded-full bg-kopdes-600"
                                                    :style="{ width: `${branchRevenueShare(row.total_revenue)}%` }"
                                                ></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-center font-bold text-slate-900">
                                        {{ formatNumber(row.total_transactions) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right text-slate-700">
                                        {{ formatRupiah(row.cash_revenue) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right text-slate-700">
                                        {{ formatRupiah(row.midtrans_revenue) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right font-bold text-slate-900">
                                        {{ formatRupiah(row.total_revenue) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right font-bold text-emerald-700">
                                        {{ formatRupiah(row.gross_profit) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right font-semibold text-rose-700">
                                        {{ formatRupiah(row.operational_expenses) }}
                                    </td>
                                    <td
                                        class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right font-extrabold"
                                        :class="Number(row.net_profit || 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                    >
                                        {{ formatRupiah(row.net_profit) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <span class="kopdes-num inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-200">
                                            {{ Number(row.net_margin_percentage || 0).toFixed(2) }}%
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="branchSummaries.length === 0">
                                    <td
                                        colspan="9"
                                        class="px-4 py-10 text-center text-sm text-slate-500"
                                    >
                                        Belum ada cabang KOPDES pada filter wilayah ini.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="branchSummaries.length > 0" class="border-t-2 border-slate-200 bg-slate-50">
                                <tr>
                                    <td class="px-4 py-3 text-xs font-extrabold uppercase tracking-wider text-slate-700">
                                        Total Keseluruhan ({{ formatNumber(branchSummaries.length) }} Cabang)
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center font-extrabold text-slate-900">
                                        {{ formatNumber(branchTotals.transactions) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-bold text-slate-800">
                                        {{ formatRupiah(branchTotals.cashRevenue) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-bold text-slate-800">
                                        {{ formatRupiah(branchTotals.midtransRevenue) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-extrabold text-slate-900">
                                        {{ formatRupiah(branchTotals.revenue) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-extrabold text-emerald-700">
                                        {{ formatRupiah(branchTotals.profit) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-extrabold text-rose-700">
                                        {{ formatRupiah(branchTotals.operationalExpenses) }}
                                    </td>
                                    <td
                                        class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-extrabold"
                                        :class="branchTotals.netProfit >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                    >
                                        {{ formatRupiah(branchTotals.netProfit) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <span class="kopdes-num inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-extrabold text-amber-900">
                                            {{ branchTotals.netMargin.toFixed(2) }}%
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
