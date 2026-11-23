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
    members: {
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
    router.get(route('reports.members'), buildQueryParams(), {
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
    router.get(route('reports.members'));
};
</script>

<template>
    <Head title="Laporan Rekap Transaksi Member (Fondasi Data SHU)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Laporan Rekap Transaksi Member (Fondasi Data SHU)
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Akumulasi partisipasi belanja lunas (`paid`) anggota di KOPDES Asal maupun lintas desa di seluruh Indonesia
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
                        :href="route('reports.sales', buildQueryParams())"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        Laporan Penjualan
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
                                Cari Kode / Nama Member
                            </label>
                            <TextInput
                                v-model="filterForm.search"
                                type="text"
                                class="mt-1 block w-full text-sm"
                                placeholder="MBR-KOP / Budi..."
                            />
                        </div>

                        <div v-if="drillDown.can_filter_province">
                            <label class="block text-xs font-semibold text-gray-700">
                                Provinsi Asal Member
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
                                Kabupaten/Kota Asal Member
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
                                KOPDES Asal (`home_branch_id`)
                            </label>
                            <select
                                v-model="filterForm.branch_id"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="">Semua KOPDES Asal</option>
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
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-semibold text-gray-500">Anggota Bertransaksi (`paid`)</div>
                        <div class="mt-1 font-mono text-lg font-extrabold text-gray-900">
                            {{ formatNumber(summary.active_shopping_members) }} / {{ formatNumber(summary.total_members) }}
                        </div>
                        <div class="text-xs text-gray-500">
                            Total {{ formatNumber(summary.total_transactions) }} transaksi
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-semibold text-gray-500">Belanja di KOPDES Asal</div>
                        <div class="mt-1 font-mono text-lg font-extrabold text-gray-900">
                            {{ formatRupiah(summary.total_home_branch_spend) }}
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-semibold text-gray-500">Belanja Lintas Cabang (KOPDES Lain)</div>
                        <div class="mt-1 font-mono text-lg font-extrabold text-blue-700">
                            {{ formatRupiah(summary.total_other_branch_spend) }}
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div class="text-xs font-semibold text-gray-500">Total Akumulasi Belanja (SHU)</div>
                        <div class="mt-1 font-mono text-lg font-extrabold text-green-700">
                            {{ formatRupiah(summary.total_accumulated_spend) }}
                        </div>
                    </div>
                </div>

                <!-- Tabel Rekap Partisipasi Belanja Member (FR-17) -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Kode Member
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Nama Anggota
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        KOPDES Asal (`home_branch_id`)
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Jumlah Transaksi (`paid`)
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Belanja di KOPDES Asal
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Belanja di KOPDES Lain (Lintas Cabang)
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Total Akumulasi Belanja
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="m in members"
                                    :key="m.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-bold text-gray-900">
                                        {{ m.member_code }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ m.name }}</div>
                                        <span
                                            v-if="m.status !== 'active'"
                                            class="inline-flex items-center rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700"
                                        >
                                            Kartu Nonaktif
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">
                                            {{ m.home_branch?.name ?? '-' }}
                                        </div>
                                        <div class="font-mono text-xs text-gray-500">
                                            {{ m.home_branch?.code ?? '' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono font-bold text-gray-900">
                                        {{ formatNumber(m.paid_transactions_count) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-800">
                                        {{ formatRupiah(m.home_branch_spend) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-blue-700">
                                        {{ formatRupiah(m.other_branch_spend) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-extrabold text-green-700">
                                        {{ formatRupiah(m.total_spend) }}
                                    </td>
                                </tr>
                                <tr v-if="members.length === 0">
                                    <td
                                        colspan="7"
                                        class="px-4 py-8 text-center text-sm text-gray-500"
                                    >
                                        Belum ada data anggota pada filter wilayah ini.
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
