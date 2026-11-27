<script setup>
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    shuFinancials: {
        type: Object,
        default: () => ({}),
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

const financials = computed(() => ({
    gross_revenue: Number(props.shuFinancials.gross_revenue ?? props.summary.gross_revenue ?? props.summary.total_revenue ?? 0),
    total_returns: Number(props.shuFinancials.total_returns ?? props.summary.total_returns ?? 0),
    net_revenue: Number(props.shuFinancials.net_revenue ?? props.shuFinancials.total_revenue ?? props.summary.total_revenue ?? 0),
    total_revenue: Number(props.shuFinancials.total_revenue ?? props.summary.total_revenue ?? 0),
    restocked_cogs: Number(props.shuFinancials.restocked_cogs ?? props.summary.restocked_cogs ?? 0),
    damaged_cogs: Number(props.shuFinancials.damaged_cogs ?? props.summary.damaged_cogs ?? 0),
    net_cogs: Number(props.shuFinancials.net_cogs ?? props.shuFinancials.total_cogs ?? props.summary.total_cogs ?? 0),
    total_cogs: Number(props.shuFinancials.total_cogs ?? props.summary.total_cogs ?? 0),
    gross_profit: Number(props.shuFinancials.gross_profit ?? props.summary.gross_profit ?? 0),
    total_operational_expenses: Number(
        props.shuFinancials.total_operational_expenses ?? props.summary.total_operational_expenses ?? 0,
    ),
    net_profit: Number(props.shuFinancials.net_profit ?? props.summary.net_profit ?? 0),
    net_margin_percentage: Number(
        props.shuFinancials.net_margin_percentage ?? props.summary.net_margin_percentage ?? 0,
    ),
    total_member_discount: Number(
        props.shuFinancials.total_member_discount ?? props.summary.total_member_discount ?? 0,
    ),
    member_usaha_pool: Number(
        props.shuFinancials.member_usaha_pool ?? props.shuFinancials.shu_basis ?? props.summary.net_profit ?? 0,
    ),
    shu_allocations: props.shuFinancials.shu_allocations ?? null,
}));

const totalEstimatedShu = computed(() =>
    props.members.reduce((sum, m) => sum + Number(m.estimated_shu_share || 0), 0),
);

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

const exportUrl = (format) => {
    return route('reports.members.export', {
        format,
        ...buildQueryParams(),
    });
};
</script>

<template>
    <Head title="Laporan SHU & Rekap Transaksi Member KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                            Laporan SHU &amp; Rekap Belanja Anggota
                        </h1>
                        <StatusBadge variant="kopdes" dot>
                            Basis Laba Bersih
                        </StatusBadge>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Perhitungan Dasar SHU berbasis Laba Bersih (Laba Kotor &minus; Biaya Operasional) &amp; partisipasi belanja lintas cabang
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
                        :href="route('reports.sales', buildQueryParams())"
                        class="btn-kopdes-primary !px-3.5 !py-2 !text-xs"
                    >
                        Laporan Laba Rugi
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
                            <label for="mbr-start-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Mulai
                            </label>
                            <input
                                id="mbr-start-date"
                                v-model="filterForm.start_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="mbr-end-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Akhir
                            </label>
                            <input
                                id="mbr-end-date"
                                v-model="filterForm.end_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="mbr-payment-method" class="block text-xs font-semibold text-slate-700">
                                Metode Pembayaran
                            </label>
                            <select
                                id="mbr-payment-method"
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
                            <label for="mbr-search" class="block text-xs font-semibold text-slate-700">
                                Cari Kode / Nama Member
                            </label>
                            <input
                                id="mbr-search"
                                v-model="filterForm.search"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="MBR-KOP / Budi..."
                            />
                        </div>

                        <div v-if="drillDown.can_filter_province">
                            <label for="mbr-province" class="block text-xs font-semibold text-slate-700">
                                Provinsi Asal Member
                            </label>
                            <select
                                id="mbr-province"
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
                            <label for="mbr-regency" class="block text-xs font-semibold text-slate-700">
                                Kabupaten/Kota Asal Member
                            </label>
                            <select
                                id="mbr-regency"
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
                            <label for="mbr-branch" class="block text-xs font-semibold text-slate-700">
                                KOPDES Asal
                            </label>
                            <select
                                id="mbr-branch"
                                v-model="filterForm.branch_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
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
                            <PrimaryButton type="submit">
                                Filter
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="resetFilters">
                                Reset
                            </SecondaryButton>
                        </div>
                    </form>
                </div>

                <!-- Rincian Transparan Komponen Dasar SHU (Laba Bersih Pasca-Retur) -->
                <section class="kopdes-card p-5" aria-label="Rincian Dasar SHU">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Rincian Komponen Dasar SHU (Basis Laba Bersih Pasca-Retur)
                            </h2>
                            <p class="text-xs text-slate-600">
                                Omzet Bruto &minus; Retur = Omzet Bersih &minus; HPP Bersih = Laba Kotor &minus; Biaya Operasional = Dasar SHU
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge variant="indigo">
                                Hemat Member: <span class="kopdes-num ms-1 font-bold">{{ formatRupiah(financials.total_member_discount) }}</span>
                            </StatusBadge>
                            <StatusBadge variant="amber">
                                Margin Bersih: <span class="kopdes-num ms-1 font-bold">{{ financials.net_margin_percentage.toFixed(2) }}%</span>
                            </StatusBadge>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                1. Omzet Bruto
                            </p>
                            <p class="kopdes-num mt-1.5 text-base font-extrabold text-slate-900">
                                {{ formatRupiah(financials.gross_revenue) }}
                            </p>
                            <p class="kopdes-num mt-0.5 text-[11px] text-rose-700">
                                (&minus;) Retur {{ formatRupiah(financials.total_returns) }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-3.5">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                                2. Omzet Bersih
                            </p>
                            <p class="kopdes-num mt-1.5 text-base font-extrabold text-slate-900">
                                {{ formatRupiah(financials.net_revenue) }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                3. (&minus;) HPP Bersih
                            </p>
                            <p class="kopdes-num mt-1.5 text-base font-bold text-slate-700">
                                {{ formatRupiah(financials.net_cogs) }}
                            </p>
                            <p class="kopdes-num mt-0.5 text-[11px] text-amber-700">
                                Rusak: {{ formatRupiah(financials.damaged_cogs) }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3.5">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-800">
                                4. (=) Laba Kotor
                            </p>
                            <p class="kopdes-num mt-1.5 text-base font-extrabold text-emerald-700">
                                {{ formatRupiah(financials.gross_profit) }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3.5">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-800">
                                5. (&minus;) Biaya Ops
                            </p>
                            <p class="kopdes-num mt-1.5 text-base font-extrabold text-rose-700">
                                {{ formatRupiah(financials.total_operational_expenses) }}
                            </p>
                        </div>

                        <div
                            class="rounded-xl border p-3.5"
                            :class="
                                financials.net_profit >= 0
                                    ? 'border-kopdes-200 bg-kopdes-50'
                                    : 'border-rose-300 bg-rose-100/70'
                            "
                        >
                            <p class="text-[11px] font-extrabold uppercase tracking-wider text-kopdes-800">
                                6. (=) Dasar SHU
                            </p>
                            <p
                                class="kopdes-num mt-1.5 text-base font-extrabold"
                                :class="financials.net_profit >= 0 ? 'text-kopdes-700' : 'text-rose-800'"
                            >
                                {{ formatRupiah(financials.net_profit) }}
                            </p>
                        </div>
                    </div>

                    <!-- Rincian 4 Pos Alokasi SHU Dinamis (dari Pengaturan Sistem) -->
                    <div
                        v-if="financials.shu_allocations"
                        class="mt-4 space-y-3 border-t border-slate-100 pt-4"
                    >
                        <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full bg-kopdes-700"
                                :style="{ width: `${Number(financials.shu_allocations.member_usaha?.percent || 0)}%` }"
                                title="Jasa Usaha Anggota"
                            ></div>
                            <div
                                class="h-full bg-indigo-600"
                                :style="{ width: `${Number(financials.shu_allocations.reserve?.percent || 0)}%` }"
                                title="Dana Cadangan"
                            ></div>
                            <div
                                class="h-full bg-emerald-600"
                                :style="{ width: `${Number(financials.shu_allocations.management?.percent || 0)}%` }"
                                title="Dana Pengurus"
                            ></div>
                            <div
                                class="h-full bg-amber-500"
                                :style="{ width: `${Number(financials.shu_allocations.education?.percent || 0)}%` }"
                                title="Pendidikan & Sosial"
                            ></div>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                            <div class="rounded-xl border border-kopdes-200/80 bg-kopdes-50/70 px-3.5 py-2.5 text-xs">
                                <div class="flex items-center gap-1.5 font-semibold text-kopdes-900">
                                    <span class="h-2 w-2 rounded-full bg-kopdes-700"></span>
                                    Jasa Usaha ({{ Number(financials.shu_allocations.member_usaha?.percent || 0).toFixed(0) }}%)
                                </div>
                                <div class="kopdes-num mt-1 text-sm font-extrabold text-kopdes-700">
                                    {{ formatRupiah(financials.shu_allocations.member_usaha?.amount) }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 px-3.5 py-2.5 text-xs">
                                <div class="flex items-center gap-1.5 font-semibold text-indigo-900">
                                    <span class="h-2 w-2 rounded-full bg-indigo-600"></span>
                                    Dana Cadangan ({{ Number(financials.shu_allocations.reserve?.percent || 0).toFixed(0) }}%)
                                </div>
                                <div class="kopdes-num mt-1 text-sm font-bold text-slate-900">
                                    {{ formatRupiah(financials.shu_allocations.reserve?.amount) }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 px-3.5 py-2.5 text-xs">
                                <div class="flex items-center gap-1.5 font-semibold text-emerald-900">
                                    <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                                    Dana Pengurus ({{ Number(financials.shu_allocations.management?.percent || 0).toFixed(0) }}%)
                                </div>
                                <div class="kopdes-num mt-1 text-sm font-bold text-slate-900">
                                    {{ formatRupiah(financials.shu_allocations.management?.amount) }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-amber-100 bg-amber-50/60 px-3.5 py-2.5 text-xs">
                                <div class="flex items-center gap-1.5 font-semibold text-amber-900">
                                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                    Pendidikan &amp; Sosial ({{ Number(financials.shu_allocations.education?.percent || 0).toFixed(0) }}%)
                                </div>
                                <div class="kopdes-num mt-1 text-sm font-bold text-slate-900">
                                    {{ formatRupiah(financials.shu_allocations.education?.amount) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Summary Strip Partisipasi Member -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Anggota Bertransaksi"
                        :value="`${formatNumber(summary.active_shopping_members)} / ${formatNumber(summary.total_members)}`"
                        :helper="`Total ${formatNumber(summary.total_transactions)} transaksi lunas`"
                        accent="kopdes"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        label="Belanja di KOPDES Asal"
                        :value="formatRupiah(summary.total_home_branch_spend)"
                        helper="Transaksi di cabang terdaftar"
                        accent="slate"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        label="Belanja Lintas Cabang"
                        :value="formatRupiah(summary.total_other_branch_spend)"
                        helper="Portabilitas kartu di KOPDES lain"
                        accent="indigo"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        label="Akumulasi Belanja Anggota"
                        :value="formatRupiah(summary.total_accumulated_spend)"
                        :helper="`Est. SHU Total ${formatRupiah(totalEstimatedShu)}`"
                        accent="emerald"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </template>
                    </StatCard>
                </div>

                <!-- Tabel Rekap Partisipasi Belanja Member & Estimasi SHU (FR-17) -->
                <div class="kopdes-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Daftar Partisipasi Belanja Anggota (KOPDES Asal vs. Lintas Cabang) &amp; Estimasi Porsi SHU
                        </h2>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ formatNumber(members.length) }} Anggota</span>
                        </StatusBadge>
                    </div>
                    <div v-if="members.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Kode Member
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Nama Anggota
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        KOPDES Asal
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Trx
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        KOPDES Asal
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        KOPDES Lain
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Total Belanja
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Porsi %
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Est. Porsi SHU
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="m in members"
                                    :key="m.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-xs font-bold text-slate-900">
                                        {{ m.member_code }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-900">{{ m.name }}</div>
                                        <StatusBadge v-if="m.status !== 'active'" variant="rose" size="xs" class="mt-0.5">
                                            Kartu Nonaktif
                                        </StatusBadge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900">
                                            {{ m.home_branch?.name ?? '-' }}
                                        </div>
                                        <div class="kopdes-num text-xs text-slate-500">
                                            {{ m.home_branch?.code ?? '' }}
                                        </div>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center font-bold text-slate-900">
                                        {{ formatNumber(m.paid_transactions_count) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right text-slate-800">
                                        {{ formatRupiah(m.home_branch_spend) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-semibold text-indigo-700">
                                        {{ formatRupiah(m.other_branch_spend) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-extrabold text-emerald-700">
                                        {{ formatRupiah(m.total_spend) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center text-xs font-semibold text-slate-700">
                                        {{ Number(m.spend_share_percent || 0).toFixed(2) }}%
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-extrabold text-kopdes-700">
                                        {{ formatRupiah(m.estimated_shu_share) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right text-xs font-extrabold uppercase tracking-wider text-slate-700">
                                        Total Keseluruhan ({{ formatNumber(summary.total_members) }} Anggota)
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center font-extrabold text-slate-900">
                                        {{ formatNumber(summary.total_transactions) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-bold text-slate-900">
                                        {{ formatRupiah(summary.total_home_branch_spend) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-bold text-indigo-700">
                                        {{ formatRupiah(summary.total_other_branch_spend) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-extrabold text-emerald-700">
                                        {{ formatRupiah(summary.total_accumulated_spend) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center text-xs font-extrabold text-slate-800">
                                        {{ Number(summary.total_accumulated_spend || 0) > 0 ? '100.00%' : '0.00%' }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-extrabold text-kopdes-700">
                                        {{ formatRupiah(totalEstimatedShu) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <EmptyState
                        v-else
                        title="Belum ada data anggota"
                        description="Belum ada anggota KOPDES yang sesuai dengan filter wilayah dan pencarian ini."
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
