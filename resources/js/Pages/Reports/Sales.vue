<script setup>
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    branchSummaries: {
        type: Array,
        default: () => [],
    },
    transactions: {
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
    permissions: {
        type: Object,
        default: () => ({ can_process_return: false }),
    },
});

const filterForm = reactive({
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
    payment_method: props.filters.payment_method ?? 'all',
    province_id: props.filters.province_id ?? '',
    regency_id: props.filters.regency_id ?? '',
    branch_id: props.filters.branch_id ?? '',
    search: props.filters.search ?? '',
});

const netProfit = computed(() => Number(props.summary.net_profit ?? 0));

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

const buildQueryParams = () => ({
    start_date: filterForm.start_date || undefined,
    end_date: filterForm.end_date || undefined,
    payment_method: filterForm.payment_method !== 'all' ? filterForm.payment_method : undefined,
    province_id: props.drillDown.can_filter_province && filterForm.province_id ? filterForm.province_id : undefined,
    regency_id: props.drillDown.can_filter_regency && filterForm.regency_id ? filterForm.regency_id : undefined,
    branch_id: props.drillDown.can_filter_branch && filterForm.branch_id ? filterForm.branch_id : undefined,
    search: filterForm.search || undefined,
});

const applyFilters = () => {
    router.get(route('reports.sales'), buildQueryParams(), {
        preserveState: true,
        replace: true,
    });
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
    router.get(route('reports.sales'));
};

const exportUrl = (format) => {
    return route('reports.sales.export', {
        format,
        ...buildQueryParams(),
    });
};
</script>

<template>
    <Head title="Laporan Penjualan & Laba Rugi KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        Laporan Penjualan &amp; Laba Rugi
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Omzet transaksi lunas (paid) &rarr; HPP Snapshot &rarr; Laba Kotor &rarr; Biaya Operasional &rarr; Laba Bersih
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a :href="exportUrl('pdf')" class="btn-kopdes-outline">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export PDF
                    </a>
                    <a :href="exportUrl('excel')" class="btn-kopdes-emerald">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        Export Excel
                    </a>
                    <Link
                        :href="route('expenses.index', buildQueryParams())"
                        class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                    >
                        Biaya Operasional
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
                <!-- Filter Bar -->
                <div class="kopdes-card p-5">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <label for="sales-start-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Mulai
                            </label>
                            <input
                                id="sales-start-date"
                                v-model="filterForm.start_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="sales-end-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Akhir
                            </label>
                            <input
                                id="sales-end-date"
                                v-model="filterForm.end_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="sales-payment-method" class="block text-xs font-semibold text-slate-700">
                                Metode Pembayaran
                            </label>
                            <select
                                id="sales-payment-method"
                                v-model="filterForm.payment_method"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="all">Semua Metode</option>
                                <option value="cash">Tunai (Cash)</option>
                                <option value="midtrans">Non-Tunai (Midtrans)</option>
                            </select>
                        </div>

                        <div>
                            <label for="sales-search" class="block text-xs font-semibold text-slate-700">
                                Cari Invoice / Member / Cabang
                            </label>
                            <input
                                id="sales-search"
                                v-model="filterForm.search"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="INV-KOP / Budi..."
                            />
                        </div>

                        <div v-if="drillDown.can_filter_province">
                            <label for="sales-province" class="block text-xs font-semibold text-slate-700">
                                Provinsi
                            </label>
                            <select
                                id="sales-province"
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
                            <label for="sales-regency" class="block text-xs font-semibold text-slate-700">
                                Kabupaten/Kota
                            </label>
                            <select
                                id="sales-regency"
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
                            <label for="sales-branch" class="block text-xs font-semibold text-slate-700">
                                Cabang KOPDES
                            </label>
                            <select
                                id="sales-branch"
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

                        <div class="flex items-end gap-2">
                            <PrimaryButton type="submit">
                                Filter
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="resetFilters">
                                Reset
                            </SecondaryButton>
                        </div>
                    </form>
                </div>

                <!-- Summary Strip Laba Rugi Lengkap (Pasca-Retur) -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="kopdes-card relative overflow-hidden p-4">
                        <div class="absolute inset-x-0 top-0 h-1 bg-slate-700"></div>
                        <div class="text-xs font-semibold text-slate-500">Omzet Bruto (Paid)</div>
                        <div class="kopdes-num mt-1 text-lg font-extrabold text-slate-900">
                            {{ formatRupiah(summary.gross_revenue ?? summary.total_revenue) }}
                        </div>
                        <div class="mt-0.5 text-[11px] text-slate-500">
                            {{ formatNumber(summary.total_transactions) }} transaksi paid
                        </div>
                    </div>

                    <div class="kopdes-card relative overflow-hidden p-4">
                        <div class="absolute inset-x-0 top-0 h-1 bg-rose-500"></div>
                        <div class="text-xs font-semibold text-slate-500">Total Retur &amp; Void</div>
                        <div class="kopdes-num mt-1 text-lg font-extrabold text-rose-700">
                            {{ formatRupiah(summary.total_returns) }}
                        </div>
                        <div class="mt-0.5 text-[11px] text-amber-700">
                            Rusak: {{ formatRupiah(summary.damaged_cogs) }}
                        </div>
                    </div>

                    <div class="kopdes-card relative overflow-hidden p-4">
                        <div class="absolute inset-x-0 top-0 h-1 bg-kopdes-700"></div>
                        <div class="text-xs font-semibold text-slate-500">Omzet Bersih</div>
                        <div class="kopdes-num mt-1 text-lg font-extrabold text-slate-900">
                            {{ formatRupiah(summary.net_revenue ?? summary.total_revenue) }}
                        </div>
                        <div class="mt-0.5 text-[11px] text-blue-700">
                            Hemat Member: {{ formatRupiah(summary.total_member_discount) }}
                        </div>
                    </div>

                    <div class="kopdes-card relative overflow-hidden p-4">
                        <div class="absolute inset-x-0 top-0 h-1 bg-slate-500"></div>
                        <div class="text-xs font-semibold text-slate-500">HPP Bersih</div>
                        <div class="kopdes-num mt-1 text-lg font-bold text-slate-700">
                            {{ formatRupiah(summary.net_cogs ?? summary.total_cogs) }}
                        </div>
                        <div class="mt-0.5 text-[11px] text-slate-500">
                            Restock: {{ formatRupiah(summary.restocked_cogs) }}
                        </div>
                    </div>

                    <div class="kopdes-card relative overflow-hidden p-4">
                        <div class="absolute inset-x-0 top-0 h-1 bg-emerald-600"></div>
                        <div class="text-xs font-semibold text-slate-500">Laba Kotor</div>
                        <div class="kopdes-num mt-1 text-lg font-extrabold text-emerald-700">
                            {{ formatRupiah(summary.gross_profit) }}
                        </div>
                        <div class="kopdes-num mt-0.5 text-[11px] font-semibold text-amber-700">
                            Margin {{ Number(summary.gross_margin_percent || 0).toFixed(2) }}%
                        </div>
                    </div>

                    <div class="kopdes-card relative overflow-hidden p-4">
                        <div class="absolute inset-x-0 top-0 h-1 bg-rose-600"></div>
                        <div class="text-xs font-semibold text-slate-500">Biaya Operasional</div>
                        <div class="kopdes-num mt-1 text-lg font-extrabold text-rose-700">
                            {{ formatRupiah(summary.total_operational_expenses) }}
                        </div>
                        <div class="mt-0.5 text-[11px] text-slate-500">
                            Beban cabang periode
                        </div>
                    </div>

                    <div class="kopdes-card relative overflow-hidden p-4">
                        <div
                            class="absolute inset-x-0 top-0 h-1"
                            :class="netProfit >= 0 ? 'bg-blue-600' : 'bg-rose-600'"
                        ></div>
                        <div class="text-xs font-semibold text-slate-500">Laba Bersih (Dasar SHU)</div>
                        <div
                            class="kopdes-num mt-1 text-lg font-extrabold"
                            :class="netProfit >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                        >
                            {{ formatRupiah(netProfit) }}
                        </div>
                        <div class="mt-0.5 text-[11px] text-slate-500">
                            Laba Kotor &minus; Biaya Ops
                        </div>
                    </div>

                    <div class="kopdes-card relative overflow-hidden p-4">
                        <div class="absolute inset-x-0 top-0 h-1 bg-amber-500"></div>
                        <div class="text-xs font-semibold text-slate-500">Margin Bersih</div>
                        <div class="kopdes-num mt-1 text-lg font-extrabold text-amber-700">
                            {{ Number(summary.net_margin_percentage || 0).toFixed(2) }}%
                        </div>
                        <div class="mt-0.5 text-[11px] text-slate-500">
                            Laba Bersih / Omzet Bersih
                        </div>
                    </div>
                </div>

                <!-- Tabel 1: Agregasi Laba Rugi Per Cabang -->
                <div class="kopdes-card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            1. Agregasi Laba Rugi per Cabang KOPDES
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang KOPDES</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Trx</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Omzet Bruto</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Retur</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Omzet Bersih</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">HPP Bersih</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Laba Kotor</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Biaya Ops</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Laba Bersih</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Net %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="row in branchSummaries"
                                    :key="row.branch_id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-900">{{ row.branch_name }}</div>
                                        <div class="kopdes-num text-xs text-slate-500">{{ row.branch_code }}</div>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center font-bold text-slate-900">
                                        {{ formatNumber(row.total_transactions) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right text-slate-700">
                                        {{ formatRupiah(row.gross_revenue ?? row.total_revenue) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right text-rose-700">
                                        {{ formatRupiah(row.total_returns) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-bold text-slate-900">
                                        {{ formatRupiah(row.net_revenue ?? row.total_revenue) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right text-slate-600">
                                        <div>{{ formatRupiah(row.net_cogs ?? row.total_cogs) }}</div>
                                        <div v-if="Number(row.damaged_cogs || 0) > 0" class="text-[10px] text-amber-700">
                                            Rusak: {{ formatRupiah(row.damaged_cogs) }}
                                        </div>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-bold text-emerald-700">
                                        {{ formatRupiah(row.gross_profit) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-semibold text-rose-700">
                                        {{ formatRupiah(row.operational_expenses) }}
                                    </td>
                                    <td
                                        class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-extrabold"
                                        :class="Number(row.net_profit || 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                    >
                                        {{ formatRupiah(row.net_profit) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center text-xs font-bold text-amber-700">
                                        {{ Number(row.net_margin_percentage || 0).toFixed(2) }}%
                                    </td>
                                </tr>
                                <tr v-if="branchSummaries.length === 0">
                                    <td colspan="10" class="px-4 py-6 text-center text-sm text-slate-500">
                                        Tidak ada data cabang pada filter wilayah ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabel 2: Daftar Rincian Transaksi Paid Berbasis Snapshot HPP -->
                <div class="kopdes-card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            2. Daftar Rincian Transaksi Lunas (Paid) Berbasis Snapshot HPP
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Invoice &amp; Waktu Lunas</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang &amp; Kasir</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Pembeli</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Metode</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Omzet</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">HPP Snapshot</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Laba Kotor</th>
                                    <th
                                        v-if="permissions.can_process_return"
                                        class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="tx in transactions"
                                    :key="tx.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <span class="kopdes-num text-xs font-bold text-slate-900">
                                                {{ tx.invoice_number }}
                                            </span>
                                            <span
                                                v-if="tx.return_status === 'partial'"
                                                class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800"
                                            >
                                                Retur Parsial
                                            </span>
                                            <span
                                                v-else-if="tx.return_status === 'full'"
                                                class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800"
                                            >
                                                Full Void
                                            </span>
                                        </div>
                                        <div class="kopdes-num text-xs text-slate-500">
                                            {{ tx.paid_at }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900">{{ tx.branch?.name ?? '-' }}</div>
                                        <div class="text-xs text-slate-500">Kasir: {{ tx.cashier?.name ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <template v-if="tx.member">
                                            <div class="font-semibold text-slate-900">{{ tx.member.name }}</div>
                                            <div class="text-xs text-slate-500">
                                                {{ tx.member.member_code }} — {{ tx.member.home_branch_name || '' }}
                                            </div>
                                        </template>
                                        <span v-else class="text-xs text-slate-500">Pembeli Umum</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase"
                                            :class="
                                                tx.payment_method === 'cash'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-blue-100 text-blue-800'
                                            "
                                        >
                                            {{ tx.payment_method }}
                                        </span>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-bold text-slate-900">
                                        {{ formatRupiah(tx.total_amount) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right text-slate-600">
                                        {{ formatRupiah(tx.total_cogs) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-bold text-emerald-700">
                                        {{ formatRupiah(tx.gross_profit) }}
                                    </td>
                                    <td
                                        v-if="permissions.can_process_return"
                                        class="whitespace-nowrap px-4 py-3 text-center"
                                    >
                                        <Link
                                            v-if="tx.return_status !== 'full'"
                                            :href="route('returns.create', tx.id)"
                                            class="inline-flex cursor-pointer items-center rounded-md border border-kopdes-200 bg-kopdes-50 px-2.5 py-1 text-xs font-semibold text-kopdes-700 transition-colors hover:bg-kopdes-100"
                                        >
                                            Proses Retur / Void
                                        </Link>
                                        <span v-else class="text-xs text-slate-400">Selesai Diretur</span>
                                    </td>
                                </tr>
                                <tr v-if="transactions.length === 0">
                                    <td :colspan="permissions.can_process_return ? 8 : 7" class="px-4 py-8 text-center text-sm text-slate-500">
                                        Belum ada transaksi berstatus lunas (paid) pada periode dan filter ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
