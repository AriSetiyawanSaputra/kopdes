<script setup>
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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

const cashSharePercent = computed(() => {
    const total = Number(props.kpis.total_revenue || 0);
    if (total <= 0) {
        return 0;
    }
    return Math.round((Number(props.paymentBreakdown.cash?.total_revenue || 0) / total) * 100);
});

const midtransSharePercent = computed(() => {
    const total = Number(props.kpis.total_revenue || 0);
    if (total <= 0) {
        return 0;
    }
    return Math.round((Number(props.paymentBreakdown.midtrans?.total_revenue || 0) / total) * 100);
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

const buildQueryParams = () => ({
    start_date: filterForm.start_date || undefined,
    end_date: filterForm.end_date || undefined,
    payment_method: filterForm.payment_method !== 'all' ? filterForm.payment_method : undefined,
    province_id: props.drillDown.can_filter_province && filterForm.province_id ? filterForm.province_id : undefined,
    regency_id: props.drillDown.can_filter_regency && filterForm.regency_id ? filterForm.regency_id : undefined,
    branch_id: props.drillDown.can_filter_branch && filterForm.branch_id ? filterForm.branch_id : undefined,
});

const applyFilters = () => {
    router.get(route('dashboard'), buildQueryParams(), {
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
    router.get(route('dashboard'));
};
</script>

<template>
    <Head title="Dashboard Keuangan Berjenjang KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Dashboard Keuangan &amp; KPI Berjenjang KOPDES
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Ringkasan kinerja penjualan berstatus <strong>LUNAS (`paid`)</strong> dan laba kotor berbasis snapshot HPP
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('reports.sales', buildQueryParams())"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        Laporan Penjualan &amp; Laba Kotor
                    </Link>
                    <Link
                        :href="route('reports.members', buildQueryParams())"
                        class="rounded-md bg-red-700 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-red-800"
                    >
                        Rekap Transaksi Member (SHU)
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Filter Rentang Tanggal & Drill-Down Wilayah Dinamis (FR-15) -->
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6"
                    >
                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Tanggal Mulai
                            </label>
                            <TextInput
                                v-model="filterForm.start_date"
                                type="date"
                                class="mt-1 block w-full text-sm"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Tanggal Akhir
                            </label>
                            <TextInput
                                v-model="filterForm.end_date"
                                type="date"
                                class="mt-1 block w-full text-sm"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Metode Bayar
                            </label>
                            <select
                                v-model="filterForm.payment_method"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="all">Semua Metode</option>
                                <option value="cash">Tunai (`cash`)</option>
                                <option value="midtrans">Midtrans (`midtrans`)</option>
                            </select>
                        </div>

                        <div v-if="drillDown.can_filter_province">
                            <label class="block text-xs font-semibold text-gray-700">
                                Provinsi (Drill-Down)
                            </label>
                            <select
                                v-model="filterForm.province_id"
                                @change="onProvinceChange"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
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
                            <label class="block text-xs font-semibold text-gray-700">
                                Kabupaten/Kota
                            </label>
                            <select
                                v-model="filterForm.regency_id"
                                @change="onRegencyChange"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
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
                            <label class="block text-xs font-semibold text-gray-700">
                                Cabang KOPDES
                            </label>
                            <select
                                v-model="filterForm.branch_id"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
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
                            <PrimaryButton
                                type="submit"
                                class="bg-red-700 hover:bg-red-800"
                            >
                                Terapkan Filter
                            </PrimaryButton>
                            <SecondaryButton
                                type="button"
                                @click="resetFilters"
                            >
                                Reset (Bulan Ini)
                            </SecondaryButton>
                        </div>
                    </form>
                </div>

                <!-- 4 Kartu KPI Utama (FR-15 Acceptance Criteria 1) -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border-l-4 border-red-700 bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">
                            Total Omzet (`paid`)
                        </div>
                        <div class="mt-2 font-mono text-2xl font-extrabold text-gray-900">
                            {{ formatRupiah(kpis.total_revenue) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            HPP Snapshot: {{ formatRupiah(kpis.total_cogs) }}
                        </div>
                    </div>

                    <div class="rounded-xl border-l-4 border-blue-600 bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">
                            Total Transaksi (`paid`)
                        </div>
                        <div class="mt-2 font-mono text-2xl font-extrabold text-gray-900">
                            {{ formatNumber(kpis.total_transactions) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            Periode {{ filters.start_date }} s.d. {{ filters.end_date }}
                        </div>
                    </div>

                    <div class="rounded-xl border-l-4 border-green-600 bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">
                            Total Laba Kotor (Snapshot)
                        </div>
                        <div class="mt-2 font-mono text-2xl font-extrabold text-green-700">
                            {{ formatRupiah(kpis.gross_profit) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            SUM(total_amount - total_cogs)
                        </div>
                    </div>

                    <div class="rounded-xl border-l-4 border-amber-500 bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">
                            Margin Laba Kotor (%)
                        </div>
                        <div class="mt-2 font-mono text-2xl font-extrabold text-amber-700">
                            {{ Number(kpis.gross_margin_percent || 0).toFixed(2) }}%
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            Rasio Laba Kotor / Total Omzet
                        </div>
                    </div>
                </div>

                <!-- Perbandingan Metode Pembayaran: Cash vs Midtrans (FR-15 Acceptance Criteria 2) -->
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-base font-bold text-gray-900">
                        Perbandingan Metode Pembayaran (Tunai vs Midtrans)
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Pecahan omzet dan jumlah transaksi lunas berdasarkan kanal pembayaran POS
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <!-- Tunai (cash) -->
                        <div class="rounded-lg border border-green-200 bg-green-50/60 p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-extrabold text-green-900">
                                    Tunai (`cash`)
                                </span>
                                <span class="rounded-full bg-green-100 px-2.5 py-0.5 font-mono text-xs font-bold text-green-800">
                                    {{ cashSharePercent }}% Omzet
                                </span>
                            </div>
                            <div class="mt-2 font-mono text-xl font-extrabold text-gray-900">
                                {{ formatRupiah(paymentBreakdown.cash?.total_revenue) }}
                            </div>
                            <div class="mt-1 flex items-center justify-between text-xs text-gray-600">
                                <span>
                                    {{ formatNumber(paymentBreakdown.cash?.transactions_count) }} transaksi lunas
                                </span>
                                <span>
                                    Laba Kotor:
                                    <strong class="text-green-800">
                                        {{ formatRupiah(paymentBreakdown.cash?.gross_profit) }}
                                    </strong>
                                </span>
                            </div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-green-200">
                                <div
                                    class="h-full bg-green-600 transition-all"
                                    :style="{ width: `${cashSharePercent}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Non-Tunai (midtrans) -->
                        <div class="rounded-lg border border-blue-200 bg-blue-50/60 p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-extrabold text-blue-900">
                                    Midtrans Snap (`midtrans`)
                                </span>
                                <span class="rounded-full bg-blue-100 px-2.5 py-0.5 font-mono text-xs font-bold text-blue-800">
                                    {{ midtransSharePercent }}% Omzet
                                </span>
                            </div>
                            <div class="mt-2 font-mono text-xl font-extrabold text-gray-900">
                                {{ formatRupiah(paymentBreakdown.midtrans?.total_revenue) }}
                            </div>
                            <div class="mt-1 flex items-center justify-between text-xs text-gray-600">
                                <span>
                                    {{ formatNumber(paymentBreakdown.midtrans?.transactions_count) }} transaksi lunas
                                </span>
                                <span>
                                    Laba Kotor:
                                    <strong class="text-blue-800">
                                        {{ formatRupiah(paymentBreakdown.midtrans?.gross_profit) }}
                                    </strong>
                                </span>
                            </div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-blue-200">
                                <div
                                    class="h-full bg-blue-600 transition-all"
                                    :style="{ width: `${midtransSharePercent}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ringkasan Kinerja Per Cabang dalam Scope -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                        <div>
                            <h3 class="text-base font-bold text-gray-900">
                                Ringkasan Kinerja Cabang KOPDES
                            </h3>
                            <p class="text-xs text-gray-500">
                                Agregasi penjualan lunas dan laba kotor snapshot per desa pada cakupan wilayah Anda
                            </p>
                        </div>
                        <Link
                            :href="route('reports.sales', buildQueryParams())"
                            class="text-xs font-semibold text-red-700 hover:underline"
                        >
                            Lihat Rincian Transaksi &rarr;
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Cabang KOPDES
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Transaksi (`paid`)
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Omzet Tunai
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Omzet Midtrans
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Total Omzet
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Laba Kotor
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Margin %
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="row in branchSummaries"
                                    :key="row.branch_id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">
                                            {{ row.branch_name }}
                                        </div>
                                        <div class="font-mono text-xs text-gray-500">
                                            {{ row.branch_code }} • {{ row.regency_name || '' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono font-bold text-gray-900">
                                        {{ formatNumber(row.total_transactions) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-700">
                                        {{ formatRupiah(row.cash_revenue) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-700">
                                        {{ formatRupiah(row.midtrans_revenue) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-bold text-gray-900">
                                        {{ formatRupiah(row.total_revenue) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-bold text-green-700">
                                        {{ formatRupiah(row.gross_profit) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono text-xs font-bold text-amber-700">
                                        {{ Number(row.gross_margin_percent || 0).toFixed(2) }}%
                                    </td>
                                </tr>
                                <tr v-if="branchSummaries.length === 0">
                                    <td
                                        colspan="7"
                                        class="px-4 py-8 text-center text-sm text-gray-500"
                                    >
                                        Belum ada cabang KOPDES pada filter wilayah ini.
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
