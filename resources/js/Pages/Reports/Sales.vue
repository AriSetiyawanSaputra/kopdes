<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
</script>

<template>
    <Head title="Laporan Penjualan & Laba Kotor (Snapshot HPP)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Laporan Rincian Penjualan &amp; Laba Kotor
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Hanya menghitung transaksi lunas (`paid`) dengan laba kotor berbasis snapshot HPP (`total_amount - total_cogs`)
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('dashboard', buildQueryParams())"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        &larr; Kembali ke Dashboard
                    </Link>
                    <Link
                        :href="route('reports.members', buildQueryParams())"
                        class="rounded-md bg-red-700 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-red-800"
                    >
                        Rekap Transaksi Member
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Filter Bar -->
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
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
                                Metode Pembayaran
                            </label>
                            <select
                                v-model="filterForm.payment_method"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="all">Semua Metode (`all`)</option>
                                <option value="cash">Tunai (`cash`)</option>
                                <option value="midtrans">Midtrans (`midtrans`)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Cari Invoice / Member / Cabang
                            </label>
                            <TextInput
                                v-model="filterForm.search"
                                type="text"
                                class="mt-1 block w-full text-sm"
                                placeholder="INV-KOP / Budi..."
                            />
                        </div>

                        <div v-if="drillDown.can_filter_province">
                            <label class="block text-xs font-semibold text-gray-700">
                                Provinsi
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

                        <div class="flex items-end gap-2">
                            <PrimaryButton
                                type="submit"
                                class="bg-red-700 hover:bg-red-800"
                            >
                                Filter
                            </PrimaryButton>
                            <SecondaryButton
                                type="button"
                                @click="resetFilters"
                            >
                                Reset
                            </SecondaryButton>
                        </div>
                    </form>
                </div>

                <!-- Summary Strip -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-semibold text-gray-500">Transaksi (`paid`)</div>
                        <div class="mt-1 font-mono text-lg font-extrabold text-gray-900">
                            {{ formatNumber(summary.total_transactions) }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-semibold text-gray-500">Total Omzet</div>
                        <div class="mt-1 font-mono text-lg font-extrabold text-gray-900">
                            {{ formatRupiah(summary.total_revenue) }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-semibold text-gray-500">Total HPP Snapshot</div>
                        <div class="mt-1 font-mono text-lg font-bold text-gray-700">
                            {{ formatRupiah(summary.total_cogs) }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-semibold text-gray-500">Laba Kotor</div>
                        <div class="mt-1 font-mono text-lg font-extrabold text-green-700">
                            {{ formatRupiah(summary.gross_profit) }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-semibold text-gray-500">Margin Laba Kotor</div>
                        <div class="mt-1 font-mono text-lg font-extrabold text-amber-700">
                            {{ Number(summary.gross_margin_percent || 0).toFixed(2) }}%
                        </div>
                    </div>
                </div>

                <!-- Tabel 1: Agregasi Penjualan & Laba Kotor Per Cabang -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="border-b border-gray-200 px-5 py-3.5">
                        <h3 class="text-sm font-bold text-gray-900">
                            1. Agregasi Penjualan &amp; Laba Kotor per Cabang KOPDES
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Cabang KOPDES</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">Jml Transaksi</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Omzet (`total_amount`)</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">HPP (`total_cogs`)</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Laba Kotor</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">Margin %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="row in branchSummaries"
                                    :key="row.branch_id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ row.branch_name }}</div>
                                        <div class="font-mono text-xs text-gray-500">{{ row.branch_code }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono font-bold text-gray-900">
                                        {{ formatNumber(row.total_transactions) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-bold text-gray-900">
                                        {{ formatRupiah(row.total_revenue) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-600">
                                        {{ formatRupiah(row.total_cogs) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-bold text-green-700">
                                        {{ formatRupiah(row.gross_profit) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono text-xs font-bold text-amber-700">
                                        {{ Number(row.gross_margin_percent || 0).toFixed(2) }}%
                                    </td>
                                </tr>
                                <tr v-if="branchSummaries.length === 0">
                                    <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">
                                        Tidak ada data cabang pada filter wilayah ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabel 2: Daftar Rincian Transaksi Paid Berbasis Snapshot HPP -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="border-b border-gray-200 px-5 py-3.5">
                        <h3 class="text-sm font-bold text-gray-900">
                            2. Daftar Rincian Transaksi Lunas (`paid`) Berbasis Snapshot HPP
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Invoice &amp; Waktu Lunas</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Cabang &amp; Kasir</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Pembeli</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">Metode</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Omzet</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">HPP Snapshot</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Laba Kotor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="tx in transactions"
                                    :key="tx.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="font-mono text-xs font-bold text-gray-900">
                                            {{ tx.invoice_number }}
                                        </div>
                                        <div class="font-mono text-xs text-gray-500">
                                            {{ tx.paid_at }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ tx.branch?.name ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">Kasir: {{ tx.cashier?.name ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <template v-if="tx.member">
                                            <div class="font-semibold text-gray-900">{{ tx.member.name }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ tx.member.member_code }} — {{ tx.member.home_branch_name || '' }}
                                            </div>
                                        </template>
                                        <span v-else class="text-xs text-gray-500">Pembeli Umum</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase"
                                            :class="
                                                tx.payment_method === 'cash'
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-blue-100 text-blue-800'
                                            "
                                        >
                                            {{ tx.payment_method }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-bold text-gray-900">
                                        {{ formatRupiah(tx.total_amount) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-600">
                                        {{ formatRupiah(tx.total_cogs) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-bold text-green-700">
                                        {{ formatRupiah(tx.gross_profit) }}
                                    </td>
                                </tr>
                                <tr v-if="transactions.length === 0">
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">
                                        Belum ada transaksi berstatus lunas (`paid`) pada periode dan filter ini.
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
