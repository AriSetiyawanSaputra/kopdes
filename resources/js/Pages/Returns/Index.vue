<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    returns: {
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
    refund_method: props.filters.refund_method ?? 'all',
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
    refund_method: filterForm.refund_method !== 'all' ? filterForm.refund_method : undefined,
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
    search: filterForm.search || undefined,
});

const applyFilters = () => {
    router.get(route('returns.index'), buildQueryParams(), {
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
    router.get(route('returns.index'));
};

const exportUrl = (format) => {
    return route('returns.export', {
        format,
        ...buildQueryParams(),
    });
};
</script>

<template>
    <Head title="Riwayat Retur & Pembatalan Transaksi KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        Riwayat Retur &amp; Void Transaksi
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Jejak audit immutable seluruh retur parsial, full void, pengembalian stok, dan refund manual cabang
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
                        :href="route('reports.sales')"
                        class="btn-kopdes-primary !px-3.5 !py-2 !text-xs"
                    >
                        Pilih Transaksi untuk Retur &rarr;
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Filter Bar -->
                <section class="kopdes-card p-5" aria-label="Filter Riwayat Retur">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <label for="ret-start" class="block text-xs font-semibold text-slate-700">Tanggal Mulai</label>
                            <input id="ret-start" v-model="filterForm.start_date" type="date" class="kopdes-input mt-1" />
                        </div>

                        <div>
                            <label for="ret-end" class="block text-xs font-semibold text-slate-700">Tanggal Akhir</label>
                            <input id="ret-end" v-model="filterForm.end_date" type="date" class="kopdes-input mt-1" />
                        </div>

                        <div>
                            <label for="ret-method" class="block text-xs font-semibold text-slate-700">Metode Refund</label>
                            <select
                                id="ret-method"
                                v-model="filterForm.refund_method"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="all">Semua Metode</option>
                                <option value="cash_refund">Tunai (Cash Refund)</option>
                                <option value="manual_transfer">Transfer Manual</option>
                            </select>
                        </div>

                        <div>
                            <label for="ret-search" class="block text-xs font-semibold text-slate-700">Cari No. Retur / Invoice / Alasan</label>
                            <input
                                id="ret-search"
                                v-model="filterForm.search"
                                type="text"
                                placeholder="RTN-KOP / INV-KOP..."
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div v-if="drillDown.can_filter_province">
                            <label for="ret-province" class="block text-xs font-semibold text-slate-700">Provinsi</label>
                            <select
                                id="ret-province"
                                v-model="filterForm.province_id"
                                @change="onProvinceChange"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Provinsi</option>
                                <option v-for="prov in drillDown.provinces" :key="prov.id" :value="prov.id">
                                    {{ prov.name }}
                                </option>
                            </select>
                        </div>

                        <div v-if="drillDown.can_filter_regency">
                            <label for="ret-regency" class="block text-xs font-semibold text-slate-700">Kabupaten / Kota</label>
                            <select
                                id="ret-regency"
                                v-model="filterForm.regency_id"
                                @change="onRegencyChange"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Kabupaten/Kota</option>
                                <option v-for="reg in drillDown.regencies" :key="reg.id" :value="reg.id">
                                    {{ reg.name }}
                                </option>
                            </select>
                        </div>

                        <div v-if="drillDown.can_filter_branch">
                            <label for="ret-branch" class="block text-xs font-semibold text-slate-700">Cabang KOPDES</label>
                            <select
                                id="ret-branch"
                                v-model="filterForm.branch_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Cabang</option>
                                <option v-for="b in drillDown.branches" :key="b.id" :value="b.id">
                                    {{ b.code }} — {{ b.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <PrimaryButton type="submit">Terapkan Filter</PrimaryButton>
                            <SecondaryButton type="button" @click="resetFilters">Reset</SecondaryButton>
                        </div>
                    </form>
                </section>

                <!-- KPI Strip -->
                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div class="absolute inset-x-0 top-0 h-1 bg-kopdes-700"></div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Dokumen Retur</p>
                        <p class="kopdes-num mt-2 text-2xl font-extrabold text-slate-900">
                            {{ formatNumber(summary.total_returns_count) }}
                        </p>
                    </div>
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div class="absolute inset-x-0 top-0 h-1 bg-rose-600"></div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Nilai Refund</p>
                        <p class="kopdes-num mt-2 text-2xl font-extrabold text-rose-700">
                            {{ formatRupiah(summary.total_refund_amount) }}
                        </p>
                    </div>
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div class="absolute inset-x-0 top-0 h-1 bg-emerald-600"></div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">HPP Restock (Layak Jual)</p>
                        <p class="kopdes-num mt-2 text-2xl font-extrabold text-emerald-700">
                            {{ formatRupiah(summary.total_restocked_cogs) }}
                        </p>
                    </div>
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div class="absolute inset-x-0 top-0 h-1 bg-amber-500"></div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Kerugian Barang Rusak</p>
                        <p class="kopdes-num mt-2 text-2xl font-extrabold text-amber-700">
                            {{ formatRupiah(summary.total_damaged_cogs) }}
                        </p>
                    </div>
                </section>

                <!-- Tabel Riwayat Retur -->
                <section class="kopdes-card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-bold text-slate-900">Daftar Dokumen Retur &amp; Void</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">No. Retur &amp; Waktu</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Invoice Asal &amp; Cabang</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Jenis</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Metode Refund</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Total Refund</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Diproses Oleh</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="ret in returns" :key="ret.id" class="transition-colors hover:bg-slate-50/80">
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <div class="kopdes-num text-xs font-bold text-slate-900">{{ ret.return_number }}</div>
                                        <div class="kopdes-num text-[11px] text-slate-500">{{ ret.returned_at }}</div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="kopdes-num text-xs font-bold text-slate-900">{{ ret.invoice_number }}</div>
                                        <div class="text-xs text-slate-500">{{ ret.branch?.name ?? '-' }} &bull; {{ ret.member_name }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold"
                                            :class="
                                                ret.return_type === 'full'
                                                    ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200'
                                                    : 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200'
                                            "
                                        >
                                            {{ ret.return_type === 'full' ? 'Full Void' : 'Parsial' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-xs">
                                        <div class="font-bold text-slate-800">
                                            {{ ret.refund_method === 'cash_refund' ? 'Tunai' : 'Transfer Manual' }}
                                        </div>
                                        <div v-if="ret.refund_method === 'cash_refund'" class="text-slate-500">
                                            {{
                                                ret.cash_source === 'active_shift'
                                                    ? `Laci Shift (${ret.refund_shift_code || '-'})`
                                                    : 'Kas Cabang'
                                            }}
                                        </div>
                                        <div v-else class="kopdes-num text-slate-500">{{ ret.refund_reference }}</div>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right font-extrabold text-rose-700">
                                        {{ formatRupiah(ret.total_refund_amount) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-xs text-slate-700">
                                        {{ ret.processed_by }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <Link
                                            :href="route('returns.show', ret.id)"
                                            class="inline-flex cursor-pointer items-center rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                        >
                                            Bukti Retur
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="returns.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                        Belum ada riwayat retur atau pembatalan transaksi pada periode ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
