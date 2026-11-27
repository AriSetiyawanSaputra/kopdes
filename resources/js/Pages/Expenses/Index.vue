<script setup>
import { computed, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    categorySummaries: {
        type: Array,
        default: () => [],
    },
    expenses: {
        type: Array,
        default: () => [],
    },
    categories: {
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
        default: () => ({
            can_manage: false,
            requires_branch_selection: false,
        }),
    },
});

const filterForm = reactive({
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
    category: props.filters.category ?? 'all',
    province_id: props.filters.province_id ?? '',
    regency_id: props.filters.regency_id ?? '',
    branch_id: props.filters.branch_id ?? '',
    search: props.filters.search ?? '',
});

const showFormModal = ref(false);
const showDeleteModal = ref(false);
const editingExpense = ref(null);
const deletingExpense = ref(null);

const todayDate = () => {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const expenseForm = useForm({
    branch_id: '',
    expense_date: todayDate(),
    category: 'listrik_air',
    description: '',
    amount: '',
    notes: '',
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
    category: filterForm.category && filterForm.category !== 'all' ? filterForm.category : undefined,
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
    router.get(route('expenses.index'), buildQueryParams(), {
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
    router.get(route('expenses.index'));
};

const exportUrl = (format) => {
    return route('expenses.export', {
        format,
        ...buildQueryParams(),
    });
};

const openCreateModal = () => {
    editingExpense.value = null;
    expenseForm.reset();
    expenseForm.clearErrors();
    expenseForm.expense_date = todayDate();
    expenseForm.category = 'listrik_air';
    expenseForm.branch_id = filterForm.branch_id || (props.drillDown.branches[0]?.id ?? '');
    showFormModal.value = true;
};

const openEditModal = (expense) => {
    editingExpense.value = expense;
    expenseForm.clearErrors();
    expenseForm.branch_id = expense.branch_id ?? '';
    expenseForm.expense_date = expense.expense_date ?? todayDate();
    expenseForm.category = expense.category ?? 'lainnya';
    expenseForm.description = expense.description ?? '';
    expenseForm.amount = expense.amount ?? '';
    expenseForm.notes = expense.notes ?? '';
    showFormModal.value = true;
};

const closeFormModal = () => {
    showFormModal.value = false;
    editingExpense.value = null;
    expenseForm.clearErrors();
};

const submitExpense = () => {
    if (editingExpense.value) {
        expenseForm.put(route('expenses.update', editingExpense.value.id), {
            preserveScroll: true,
            onSuccess: () => closeFormModal(),
        });
    } else {
        expenseForm.post(route('expenses.store'), {
            preserveScroll: true,
            onSuccess: () => closeFormModal(),
        });
    }
};

const confirmDelete = (expense) => {
    deletingExpense.value = expense;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deletingExpense.value = null;
};

const performDelete = () => {
    if (!deletingExpense.value) {
        return;
    }

    router.delete(route('expenses.destroy', deletingExpense.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

const activeCategoryBreakdown = computed(() =>
    props.categorySummaries.filter((c) => Number(c.count || 0) > 0),
);
</script>

<template>
    <Head title="Biaya Operasional Cabang KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                            Biaya Operasional Cabang
                        </h1>
                        <StatusBadge v-if="!permissions.can_manage" variant="slate">
                            Mode Baca (Audit Wilayah)
                        </StatusBadge>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        Periode aktif:
                        <span class="kopdes-num font-semibold text-slate-800">{{ filters.start_date }}</span>
                        s.d.
                        <span class="kopdes-num font-semibold text-slate-800">{{ filters.end_date }}</span>
                        &mdash; menjadi pengurang langsung Laba Kotor pada Laporan Laba Rugi &amp; Dasar SHU.
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
                    <PrimaryButton
                        v-if="permissions.can_manage"
                        type="button"
                        @click="openCreateModal"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Catat Biaya
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Filter Bar -->
                <section class="kopdes-card p-5" aria-label="Filter Biaya Operasional">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <label for="exp-start-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Mulai
                            </label>
                            <input
                                id="exp-start-date"
                                v-model="filterForm.start_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="exp-end-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Akhir
                            </label>
                            <input
                                id="exp-end-date"
                                v-model="filterForm.end_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="exp-category" class="block text-xs font-semibold text-slate-700">
                                Kategori Biaya
                            </label>
                            <select
                                id="exp-category"
                                v-model="filterForm.category"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="all">Semua Kategori</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.value"
                                    :value="cat.value"
                                >
                                    {{ cat.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="exp-search" class="block text-xs font-semibold text-slate-700">
                                Cari Deskripsi / Catatan / Cabang
                            </label>
                            <input
                                id="exp-search"
                                v-model="filterForm.search"
                                type="text"
                                placeholder="Token listrik, honor kasir..."
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div v-if="drillDown.can_filter_province">
                            <label for="exp-province" class="block text-xs font-semibold text-slate-700">
                                Provinsi
                            </label>
                            <select
                                id="exp-province"
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
                            <label for="exp-regency" class="block text-xs font-semibold text-slate-700">
                                Kabupaten / Kota
                            </label>
                            <select
                                id="exp-regency"
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
                            <label for="exp-branch" class="block text-xs font-semibold text-slate-700">
                                Cabang KOPDES
                            </label>
                            <select
                                id="exp-branch"
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
                                Terapkan Filter
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="resetFilters">
                                Reset
                            </SecondaryButton>
                        </div>
                    </form>
                </section>

                <!-- KPI Ringkasan Biaya Operasional -->
                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Total Biaya Operasional"
                        :value="formatRupiah(summary.total_amount)"
                        :helper="`Periode ${filters.start_date} s.d. ${filters.end_date}`"
                        accent="rose"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        label="Jumlah Catatan Biaya"
                        :value="formatNumber(summary.total_count)"
                        helper="Transaksi pengeluaran aktif"
                        accent="kopdes"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        label="Rata-rata per Catatan"
                        :value="formatRupiah(summary.average_amount)"
                        helper="Nominal rata-rata pengeluaran"
                        accent="amber"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        label="Kategori Aktif"
                        :value="`${formatNumber(summary.active_categories_count)} / ${categories.length}`"
                        helper="Pos pengeluaran terpakai"
                        accent="indigo"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </template>
                    </StatCard>
                </section>

                <!-- Rekap Subtotal per Kategori -->
                <section class="kopdes-card p-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Ringkasan Pengeluaran per Kategori
                            </h2>
                            <p class="text-xs text-slate-600">
                                Distribusi proporsi biaya operasional pada periode dan cakupan wilayah terpilih
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="activeCategoryBreakdown.length > 0"
                        class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="cat in activeCategoryBreakdown"
                            :key="cat.category"
                            class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold text-slate-800">
                                    {{ cat.label }}
                                </span>
                                <StatusBadge variant="rose" size="xs">
                                    <span class="kopdes-num">{{ Number(cat.percentage || 0).toFixed(1) }}%</span>
                                </StatusBadge>
                            </div>
                            <div class="mt-1.5 flex items-baseline justify-between">
                                <span class="kopdes-num text-base font-extrabold text-rose-700">
                                    {{ formatRupiah(cat.total_amount) }}
                                </span>
                                <span class="text-xs text-slate-500">
                                    <span class="kopdes-num font-semibold text-slate-700">{{ formatNumber(cat.count) }}</span> catatan
                                </span>
                            </div>
                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                <div
                                    class="h-full rounded-full bg-rose-600 transition-all duration-300"
                                    :style="{ width: `${Math.min(100, Math.max(0, Number(cat.percentage || 0)))}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <EmptyState
                        v-else
                        title="Belum ada distribusi biaya"
                        description="Belum ada catatan biaya operasional pada periode dan filter ini."
                    />
                </section>

                <!-- Tabel Rincian Biaya Operasional -->
                <section class="kopdes-card overflow-hidden">
                    <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Daftar Rincian Biaya Operasional
                            </h2>
                            <p class="text-xs text-slate-600">
                                Seluruh pencatatan bersifat audit-safe (penghapusan menggunakan soft-delete)
                            </p>
                        </div>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ formatNumber(expenses.length) }} Catatan</span>
                        </StatusBadge>
                    </div>

                    <div v-if="expenses.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Tanggal
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Cabang KOPDES
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Kategori
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Deskripsi &amp; Catatan
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Pencatat
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Nominal
                                    </th>
                                    <th
                                        v-if="permissions.can_manage"
                                        class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="exp in expenses"
                                    :key="exp.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-xs font-bold text-slate-800">
                                        {{ exp.expense_date }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-900">
                                            {{ exp.branch?.name ?? '-' }}
                                        </div>
                                        <div class="kopdes-num text-xs text-slate-500">
                                            {{ exp.branch?.code ?? '' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <StatusBadge variant="slate" size="xs">
                                            {{ exp.category_label }}
                                        </StatusBadge>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-900">
                                            {{ exp.description }}
                                        </div>
                                        <div v-if="exp.notes" class="mt-0.5 text-xs text-slate-500">
                                            {{ exp.notes }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-xs text-slate-600">
                                        {{ exp.user?.name ?? '-' }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right font-bold text-rose-700">
                                        {{ formatRupiah(exp.amount) }}
                                    </td>
                                    <td
                                        v-if="permissions.can_manage"
                                        class="whitespace-nowrap px-4 py-3.5 text-center"
                                    >
                                        <div class="inline-flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                @click="openEditModal(exp)"
                                                class="cursor-pointer rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                @click="confirmDelete(exp)"
                                                class="cursor-pointer rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-100"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                                <tr>
                                    <td
                                        colspan="5"
                                        class="px-4 py-3 text-right text-xs font-extrabold uppercase tracking-wider text-slate-700"
                                    >
                                        Total Biaya Operasional ({{ formatNumber(summary.total_count) }} Catatan)
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-extrabold text-rose-700">
                                        {{ formatRupiah(summary.total_amount) }}
                                    </td>
                                    <td v-if="permissions.can_manage"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <EmptyState
                        v-else
                        title="Belum ada catatan biaya operasional"
                        description="Catat pengeluaran rutin cabang (listrik, air, honor, ATK) agar perhitungan Laba Bersih & Dasar SHU selalu akurat."
                    >
                        <template v-if="permissions.can_manage" #actions>
                            <PrimaryButton type="button" @click="openCreateModal">
                                + Catat Biaya Pertama
                            </PrimaryButton>
                        </template>
                    </EmptyState>
                </section>
            </div>
        </div>

        <!-- Modal Tambah / Edit Biaya Operasional -->
        <Modal :show="showFormModal" max-width="lg" @close="closeFormModal">
            <form @submit.prevent="submitExpense" class="p-6">
                <div class="flex items-start justify-between border-b border-slate-200 pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">
                            {{ editingExpense ? 'Edit Biaya Operasional' : 'Catat Biaya Operasional Baru' }}
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-600">
                            Pastikan tanggal, kategori, dan nominal sesuai bukti pengeluaran cabang.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeFormModal"
                        aria-label="Tutup modal"
                        class="cursor-pointer rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <div v-if="permissions.requires_branch_selection">
                        <label for="form-branch-id" class="block text-xs font-semibold text-slate-700">
                            Cabang KOPDES <span class="text-rose-600">*</span>
                        </label>
                        <select
                            id="form-branch-id"
                            v-model="expenseForm.branch_id"
                            required
                            class="kopdes-input mt-1 cursor-pointer"
                        >
                            <option value="" disabled>-- Pilih Cabang KOPDES --</option>
                            <option
                                v-for="b in drillDown.branches"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.code }} — {{ b.name }}
                            </option>
                        </select>
                        <InputError :message="expenseForm.errors.branch_id" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="form-expense-date" class="block text-xs font-semibold text-slate-700">
                                Tanggal Pengeluaran <span class="text-rose-600">*</span>
                            </label>
                            <input
                                id="form-expense-date"
                                v-model="expenseForm.expense_date"
                                type="date"
                                required
                                class="kopdes-input mt-1"
                            />
                            <InputError :message="expenseForm.errors.expense_date" class="mt-1" />
                        </div>

                        <div>
                            <label for="form-category" class="block text-xs font-semibold text-slate-700">
                                Kategori Biaya <span class="text-rose-600">*</span>
                            </label>
                            <select
                                id="form-category"
                                v-model="expenseForm.category"
                                required
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option
                                    v-for="cat in categories"
                                    :key="cat.value"
                                    :value="cat.value"
                                >
                                    {{ cat.label }}
                                </option>
                            </select>
                            <InputError :message="expenseForm.errors.category" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label for="form-description" class="block text-xs font-semibold text-slate-700">
                            Deskripsi Pengeluaran <span class="text-rose-600">*</span>
                        </label>
                        <input
                            id="form-description"
                            v-model="expenseForm.description"
                            type="text"
                            maxlength="200"
                            required
                            placeholder="Contoh: Pembayaran listrik PLN & PDAM bulan November"
                            class="kopdes-input mt-1"
                        />
                        <InputError :message="expenseForm.errors.description" class="mt-1" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="form-amount" class="block text-xs font-semibold text-slate-700">
                                Nominal Pengeluaran <span class="text-rose-600">*</span>
                            </label>
                            <span v-if="Number(expenseForm.amount) > 0" class="kopdes-num text-xs font-bold text-rose-700">
                                {{ formatRupiah(expenseForm.amount) }}
                            </span>
                        </div>
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-slate-500">
                                Rp
                            </span>
                            <input
                                id="form-amount"
                                v-model="expenseForm.amount"
                                type="number"
                                min="1"
                                step="any"
                                required
                                placeholder="250000"
                                class="kopdes-input kopdes-num pl-9 font-bold"
                            />
                        </div>
                        <InputError :message="expenseForm.errors.amount" class="mt-1" />
                    </div>

                    <div>
                        <label for="form-notes" class="block text-xs font-semibold text-slate-700">
                            Catatan Tambahan (Opsional)
                        </label>
                        <textarea
                            id="form-notes"
                            v-model="expenseForm.notes"
                            rows="3"
                            maxlength="1000"
                            placeholder="Nomor kuitansi, nama penerima, atau keterangan tambahan..."
                            class="kopdes-input mt-1"
                        ></textarea>
                        <InputError :message="expenseForm.errors.notes" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2.5 border-t border-slate-200 pt-4">
                    <SecondaryButton type="button" @click="closeFormModal">
                        Batal
                    </SecondaryButton>
                    <PrimaryButton type="submit" :disabled="expenseForm.processing">
                        {{ expenseForm.processing ? 'Menyimpan...' : (editingExpense ? 'Simpan Perubahan' : 'Simpan Biaya') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal Konfirmasi Hapus (Soft Delete) -->
        <Modal :show="showDeleteModal" max-width="md" @close="closeDeleteModal">
            <div class="p-6">
                <h3 class="text-lg font-bold text-slate-900">
                    Hapus Catatan Biaya Operasional?
                </h3>
                <p class="mt-2 text-sm text-slate-600">
                    Anda akan menghapus catatan
                    <strong class="text-slate-900">{{ deletingExpense?.description }}</strong>
                    senilai
                    <strong class="kopdes-num text-rose-700">{{ formatRupiah(deletingExpense?.amount) }}</strong>.
                    Data akan diarsipkan (soft-delete) dan tidak lagi mengurangi Laba Bersih / SHU.
                </p>

                <div class="mt-6 flex items-center justify-end gap-2.5">
                    <SecondaryButton type="button" @click="closeDeleteModal">
                        Batal
                    </SecondaryButton>
                    <DangerButton type="button" @click="performDelete">
                        Ya, Hapus Biaya
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
