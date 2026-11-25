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
    shifts: {
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
    status: props.filters.status ?? 'all',
    province_id: props.filters.province_id ?? '',
    regency_id: props.filters.regency_id ?? '',
    branch_id: props.filters.branch_id ?? '',
    search: props.filters.search ?? '',
});

const formatRupiah = (value) => {
    const num = Number(value || 0);
    const prefix = num < 0 ? '-' : '';
    return (
        prefix +
        new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Math.abs(num))
    );
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('id-ID').format(Number(value || 0));
};

const varianceBadgeClass = (variance) => {
    const v = Number(variance || 0);
    if (v < 0) {
        return 'bg-rose-50 text-rose-700 ring-rose-200';
    }
    if (v > 0) {
        return 'bg-amber-50 text-amber-800 ring-amber-200';
    }
    return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
};

const buildQueryParams = () => ({
    start_date: filterForm.start_date || undefined,
    end_date: filterForm.end_date || undefined,
    status: filterForm.status !== 'all' ? filterForm.status : undefined,
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
    router.get(route('shifts.index'), buildQueryParams(), {
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
    router.get(route('shifts.index'));
};

const exportUrl = (format) => {
    return route('shifts.export', {
        format,
        ...buildQueryParams(),
    });
};
</script>

<template>
    <Head title="Monitoring Shift Kasir KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        Monitoring &amp; Riwayat Shift Kasir
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Pengawasan laci tunai kasir, hasil Blind Count, selisih kas, dan fitur force-close shift cabang
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
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Filter Bar -->
                <section class="kopdes-card p-5" aria-label="Filter Shift Kasir">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <label for="shift-start-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Mulai
                            </label>
                            <input
                                id="shift-start-date"
                                v-model="filterForm.start_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="shift-end-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Akhir
                            </label>
                            <input
                                id="shift-end-date"
                                v-model="filterForm.end_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="shift-status" class="block text-xs font-semibold text-slate-700">
                                Status Shift
                            </label>
                            <select
                                id="shift-status"
                                v-model="filterForm.status"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="all">Semua Status</option>
                                <option value="open">Aktif (Open)</option>
                                <option value="closed">Selesai (Closed)</option>
                            </select>
                        </div>

                        <div>
                            <label for="shift-search" class="block text-xs font-semibold text-slate-700">
                                Cari Kode Shift / Kasir / Cabang
                            </label>
                            <input
                                id="shift-search"
                                v-model="filterForm.search"
                                type="text"
                                placeholder="SHF-KOP / Kasir..."
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div v-if="drillDown.can_filter_province">
                            <label for="shift-province" class="block text-xs font-semibold text-slate-700">
                                Provinsi
                            </label>
                            <select
                                id="shift-province"
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
                            <label for="shift-regency" class="block text-xs font-semibold text-slate-700">
                                Kabupaten / Kota
                            </label>
                            <select
                                id="shift-regency"
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
                            <label for="shift-branch" class="block text-xs font-semibold text-slate-700">
                                Cabang KOPDES
                            </label>
                            <select
                                id="shift-branch"
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
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Shift</p>
                        <p class="kopdes-num mt-2 text-2xl font-extrabold text-slate-900">
                            {{ formatNumber(summary.total_shifts) }}
                        </p>
                    </div>
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div class="absolute inset-x-0 top-0 h-1 bg-blue-600"></div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Shift Aktif (Open)</p>
                        <p class="kopdes-num mt-2 text-2xl font-extrabold text-blue-700">
                            {{ formatNumber(summary.open_shifts) }}
                        </p>
                    </div>
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div class="absolute inset-x-0 top-0 h-1 bg-emerald-600"></div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Shift Selesai (Closed)</p>
                        <p class="kopdes-num mt-2 text-2xl font-extrabold text-emerald-700">
                            {{ formatNumber(summary.closed_shifts) }}
                        </p>
                    </div>
                    <div class="kopdes-card relative overflow-hidden p-5">
                        <div
                            class="absolute inset-x-0 top-0 h-1"
                            :class="
                                Number(summary.total_variance) < 0
                                    ? 'bg-rose-600'
                                    : Number(summary.total_variance) > 0
                                        ? 'bg-amber-500'
                                        : 'bg-emerald-600'
                            "
                        ></div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Akumulasi Selisih Kas</p>
                        <p
                            class="kopdes-num mt-2 text-2xl font-extrabold"
                            :class="
                                Number(summary.total_variance) < 0
                                    ? 'text-rose-700'
                                    : Number(summary.total_variance) > 0
                                        ? 'text-amber-700'
                                        : 'text-emerald-700'
                            "
                        >
                            {{ Number(summary.total_variance) > 0 ? '+' : '' }}{{ formatRupiah(summary.total_variance) }}
                        </p>
                    </div>
                </section>

                <!-- Tabel Riwayat Shift -->
                <section class="kopdes-card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Daftar Shift Kasir
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Kode Shift &amp; Waktu</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang &amp; Kasir</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Modal Awal</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Ekspektasi Tunai</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Fisik Aktual</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Selisih Kas</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="s in shifts"
                                    :key="s.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <div class="kopdes-num text-xs font-bold text-slate-900">{{ s.shift_code }}</div>
                                        <div class="kopdes-num text-[11px] text-slate-500">Buka: {{ s.opened_at }}</div>
                                        <div v-if="s.closed_at" class="kopdes-num text-[11px] text-slate-500">
                                            Tutup: {{ s.closed_at }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-900">{{ s.cashier?.name ?? '-' }}</div>
                                        <div class="text-xs text-slate-500">
                                            {{ s.branch?.name ?? '-' }} ({{ s.branch?.code ?? '' }})
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <span
                                            v-if="s.status === 'open'"
                                            class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 ring-1 ring-inset ring-blue-200"
                                        >
                                            Aktif (Open)
                                        </span>
                                        <div v-else class="flex flex-col items-center gap-1">
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                                Ditutup
                                            </span>
                                            <span
                                                v-if="s.is_force_closed"
                                                class="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800"
                                            >
                                                Force-Close
                                            </span>
                                        </div>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right text-slate-700">
                                        {{ formatRupiah(s.opening_cash) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right font-semibold text-slate-900">
                                        {{ s.status === 'closed' ? formatRupiah(s.expected_closing_cash) : '-' }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right font-semibold text-slate-900">
                                        {{ s.status === 'closed' ? formatRupiah(s.actual_closing_cash) : '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <span
                                            v-if="s.status === 'closed'"
                                            class="kopdes-num inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 ring-inset"
                                            :class="varianceBadgeClass(s.variance)"
                                        >
                                            {{ Number(s.variance) > 0 ? '+' : '' }}{{ formatRupiah(s.variance) }}
                                        </span>
                                        <span v-else class="text-xs text-slate-400">Menunggu Tutup</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <Link
                                            :href="route('shifts.show', s.id)"
                                            class="inline-flex cursor-pointer items-center rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                        >
                                            Detail
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="shifts.length === 0">
                                    <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                        Belum ada riwayat shift kasir pada periode dan filter ini.
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
