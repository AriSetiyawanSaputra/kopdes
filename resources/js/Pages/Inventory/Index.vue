<script setup>
import { computed, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImportExcelModal from '@/Components/ImportExcelModal.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    stocks: {
        type: Array,
        required: true,
    },
    selectedBranch: {
        type: Object,
        default: null,
    },
    branches: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    permissions: {
        type: Object,
        default: () => ({
            can_restock: false,
            can_adjust: false,
            can_mutate: false,
        }),
    },
});

const filterForm = reactive({
    search: props.filters.search ?? '',
    branch_id: props.filters.branch_id ?? '',
    category_id: props.filters.category_id ?? '',
    low_stock: Boolean(props.filters.low_stock),
});

const activeRestockItem = ref(null);
const activeAdjustItem = ref(null);
const showImportModal = ref(false);

const restockForm = useForm({
    branch_id: '',
    product_id: '',
    qty_add: 1,
    notes: '',
});

const adjustForm = useForm({
    branch_id: '',
    product_id: '',
    actual_quantity: 0,
    notes: '',
});

const lowStockCount = computed(() => {
    return props.stocks.filter((item) => item.available_stock <= item.min_stock).length;
});

const totalPhysicalUnits = computed(() => {
    return props.stocks.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
});

const totalReservedUnits = computed(() => {
    return props.stocks.reduce((sum, item) => sum + Number(item.reserved_qty || 0), 0);
});

const estimatedRestockQtyAfter = computed(() => {
    if (!activeRestockItem.value) {
        return 0;
    }

    const currentQty = Number(activeRestockItem.value.quantity || 0);
    const addQty = Math.max(0, Number(restockForm.qty_add || 0));

    return currentQty + addQty;
});

const adjustDiff = computed(() => {
    if (!activeAdjustItem.value) {
        return 0;
    }

    const currentQty = Number(activeAdjustItem.value.quantity || 0);
    const actualQty = Number(adjustForm.actual_quantity || 0);

    return actualQty - currentQty;
});

const isAdjustBelowReserved = computed(() => {
    if (!activeAdjustItem.value) {
        return false;
    }

    return Number(adjustForm.actual_quantity) < Number(activeAdjustItem.value.reserved_qty || 0);
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

const applyFilters = () => {
    router.get(
        route('inventory.index'),
        {
            search: filterForm.search || undefined,
            branch_id: filterForm.branch_id || undefined,
            category_id: filterForm.category_id || undefined,
            low_stock: filterForm.low_stock ? 1 : undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.category_id = '';
    filterForm.low_stock = false;
    router.get(route('inventory.index'), {
        branch_id: filterForm.branch_id || undefined,
    });
};

const openRestockModal = (item) => {
    activeRestockItem.value = item;
    restockForm.clearErrors();
    restockForm.branch_id = item.branch_id || props.selectedBranch?.id || '';
    restockForm.product_id = item.product_id;
    restockForm.qty_add = 1;
    restockForm.notes = '';
};

const closeRestockModal = () => {
    activeRestockItem.value = null;
    restockForm.reset();
    restockForm.clearErrors();
};

const submitRestock = () => {
    restockForm.post(route('inventory.restock'), {
        preserveScroll: true,
        onSuccess: () => {
            closeRestockModal();
        },
    });
};

const openAdjustModal = (item) => {
    activeAdjustItem.value = item;
    adjustForm.clearErrors();
    adjustForm.branch_id = item.branch_id || props.selectedBranch?.id || '';
    adjustForm.product_id = item.product_id;
    adjustForm.actual_quantity = Number(item.quantity || 0);
    adjustForm.notes = '';
};

const closeAdjustModal = () => {
    activeAdjustItem.value = null;
    adjustForm.reset();
    adjustForm.clearErrors();
};

const submitAdjust = () => {
    adjustForm.post(route('inventory.adjust'), {
        preserveScroll: true,
        onSuccess: () => {
            closeAdjustModal();
        },
    });
};
</script>

<template>
    <Head title="Manajemen Inventori & Stok Cabang KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                            Inventori &amp; Persediaan Barang Cabang
                        </h1>
                        <StatusBadge v-if="selectedBranch" variant="kopdes" size="xs">
                            {{ selectedBranch.code }}
                        </StatusBadge>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-600">
                        <span v-if="selectedBranch" class="font-semibold text-slate-800">
                            {{ selectedBranch.name }}
                        </span>
                        <span v-else>Pantau stok fisik, reservasi Midtrans, dan stok tersedia</span>
                        &mdash;
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-700">
                            Stok Tersedia = Stok Fisik &minus; Ditahan Midtrans
                        </span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="permissions.can_import"
                        type="button"
                        @click="showImportModal = true"
                        class="btn-kopdes-emerald"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Import Stok Awal
                    </button>
                    <Link
                        :href="
                            route('inventory.movements', {
                                branch_id: selectedBranch?.id || undefined,
                            })
                        "
                        class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                    >
                        Riwayat &amp; Audit Log Stok
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- KPI Summary Strip -->
                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Produk Terpantau"
                        :value="formatNumber(stocks.length)"
                        helper="SKU aktif pada filter cabang"
                        accent="kopdes"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        label="Total Stok Fisik"
                        :value="formatNumber(totalPhysicalUnits)"
                        helper="Akumulasi unit di rak & gudang"
                        accent="emerald"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        label="Ditahan Midtrans"
                        :value="formatNumber(totalReservedUnits)"
                        helper="Unit terikat transaksi pending"
                        :accent="totalReservedUnits > 0 ? 'amber' : 'slate'"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        label="Stok Menipis"
                        :value="formatNumber(lowStockCount)"
                        helper="Stok tersedia ≤ batas minimum"
                        :accent="lowStockCount > 0 ? 'rose' : 'emerald'"
                    >
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </template>
                    </StatCard>
                </section>

                <!-- Peringatan Stok Menipis -->
                <div
                    v-if="lowStockCount > 0"
                    role="alert"
                    class="flex flex-col gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-900 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-2.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <span class="font-bold">Peringatan Stok Menipis:</span>
                            Terdapat <span class="kopdes-num font-extrabold">{{ lowStockCount }}</span> produk aktif dengan
                            <strong>Stok Tersedia &le; Batas Minimum</strong> pada cabang ini.
                        </div>
                    </div>
                    <button
                        v-if="!filterForm.low_stock"
                        type="button"
                        @click="
                            filterForm.low_stock = true;
                            applyFilters();
                        "
                        class="shrink-0 cursor-pointer rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-bold text-rose-700 transition-colors hover:bg-rose-100"
                    >
                        Tampilkan Stok Menipis
                    </button>
                </div>

                <!-- Filter Bar -->
                <div class="kopdes-card p-5">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <div v-if="branches.length > 1">
                            <label for="inv-branch" class="block text-xs font-semibold text-slate-700">
                                Cabang KOPDES
                            </label>
                            <select
                                id="inv-branch"
                                v-model="filterForm.branch_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option
                                    v-for="b in branches"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.code }} — {{ b.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="inv-search" class="block text-xs font-semibold text-slate-700">
                                Cari Nama / SKU / Barcode
                            </label>
                            <input
                                id="inv-search"
                                v-model="filterForm.search"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="Beras / SKU / 899..."
                            />
                        </div>

                        <div>
                            <label for="inv-category" class="block text-xs font-semibold text-slate-700">
                                Kategori Produk
                            </label>
                            <select
                                id="inv-category"
                                v-model="filterForm.category_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Kategori</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-end pb-1.5">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                <input
                                    v-model="filterForm.low_stock"
                                    type="checkbox"
                                    @change="applyFilters"
                                    class="h-4 w-4 rounded border-slate-300 text-kopdes-700 shadow-sm focus:ring-kopdes-500"
                                />
                                <span class="text-xs font-semibold">
                                    Hanya Stok Menipis (&le; Min)
                                </span>
                            </label>
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

                <!-- Inventory Table -->
                <div class="kopdes-card overflow-hidden">
                    <div v-if="stocks.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        SKU &amp; Barcode
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Produk &amp; Kategori
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Harga Jual
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Stok Fisik
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Ditahan Midtrans
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Stok Tersedia
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Batas Minimum
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="item in stocks"
                                    :key="item.product_id"
                                    class="transition-colors"
                                    :class="
                                        item.available_stock <= item.min_stock
                                            ? 'bg-rose-50/40 hover:bg-rose-50/70'
                                            : 'hover:bg-slate-50/80'
                                    "
                                >
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <div class="kopdes-num text-xs font-bold text-slate-900">
                                            {{ item.sku }}
                                        </div>
                                        <div class="kopdes-num text-xs text-slate-500">
                                            {{ item.barcode }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-900">
                                            {{ item.name }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ item.category?.name ?? '-' }} &bull; Satuan: {{ item.unit }}
                                        </div>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right font-semibold text-slate-800">
                                        {{ formatRupiah(item.selling_price) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-center text-sm font-bold text-slate-900">
                                        {{ item.quantity }}
                                        <span class="text-xs font-normal text-slate-500">{{ item.unit }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <StatusBadge v-if="item.reserved_qty > 0" variant="amber" size="xs">
                                            <span class="kopdes-num">{{ item.reserved_qty }} {{ item.unit }}</span>
                                        </StatusBadge>
                                        <span v-else class="kopdes-num text-xs text-slate-400">0</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <div class="inline-flex items-center gap-1.5">
                                            <StatusBadge
                                                :variant="item.available_stock <= item.min_stock ? 'rose' : 'emerald'"
                                                size="xs"
                                                dot
                                            >
                                                <span class="kopdes-num">{{ item.available_stock }} {{ item.unit }}</span>
                                            </StatusBadge>
                                            <span
                                                v-if="item.available_stock <= item.min_stock"
                                                class="rounded bg-rose-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                                            >
                                                Menipis
                                            </span>
                                        </div>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-center text-xs text-slate-600">
                                        {{ item.min_stock }} {{ item.unit }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                        <div
                                            v-if="permissions.can_mutate"
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <button
                                                type="button"
                                                @click="openRestockModal(item)"
                                                class="cursor-pointer rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 transition-colors hover:bg-emerald-100"
                                            >
                                                + Restock
                                            </button>
                                            <button
                                                type="button"
                                                @click="openAdjustModal(item)"
                                                class="cursor-pointer rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                            >
                                                Sesuaikan
                                            </button>
                                        </div>
                                        <Link
                                            v-else
                                            :href="
                                                route('inventory.movements', {
                                                    branch_id: selectedBranch?.id || undefined,
                                                    product_id: item.product_id,
                                                })
                                            "
                                            class="text-xs font-semibold text-kopdes-700 hover:underline"
                                        >
                                            Lihat Log
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else
                        title="Tidak ada data stok produk"
                        description="Tidak ada produk aktif yang sesuai dengan filter pencarian atau kategori yang dipilih."
                    />
                </div>
            </div>
        </div>

        <!-- Modal Tambah Stok (Restock) -->
        <Modal :show="activeRestockItem !== null" max-width="lg" @close="closeRestockModal">
            <form v-if="activeRestockItem" @submit.prevent="submitRestock" class="p-6">
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="text-lg font-bold text-slate-900">
                        Tambah Stok Barang Masuk (Restock)
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-600">
                        {{ activeRestockItem.name }} (<span class="kopdes-num">{{ activeRestockItem.sku }}</span>) &mdash; Cabang:
                        <strong>{{ selectedBranch?.name }}</strong>
                    </p>
                </div>

                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-3 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-center text-xs">
                        <div>
                            <div class="text-slate-500">Stok Fisik Saat Ini</div>
                            <div class="kopdes-num mt-1 text-base font-bold text-slate-900">
                                {{ activeRestockItem.quantity }} {{ activeRestockItem.unit }}
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-500">Ditahan Midtrans</div>
                            <div class="kopdes-num mt-1 text-base font-bold text-amber-700">
                                {{ activeRestockItem.reserved_qty }} {{ activeRestockItem.unit }}
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-500">Stok Setelah Restock</div>
                            <div class="kopdes-num mt-1 text-base font-extrabold text-emerald-700">
                                {{ estimatedRestockQtyAfter }} {{ activeRestockItem.unit }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="qty_add" class="block text-xs font-semibold text-slate-700">
                            Jumlah Tambah Stok ({{ activeRestockItem.unit }}) <span class="text-rose-600">*</span>
                        </label>
                        <input
                            id="qty_add"
                            v-model="restockForm.qty_add"
                            type="number"
                            min="1"
                            step="1"
                            class="kopdes-input kopdes-num mt-1 font-bold"
                            required
                        />
                        <InputError class="mt-1.5" :message="restockForm.errors.qty_add" />
                        <InputError class="mt-1.5" :message="restockForm.errors.product_id" />
                    </div>

                    <div>
                        <label for="restock_notes" class="block text-xs font-semibold text-slate-700">
                            Catatan / Nomor Faktur Penerimaan (Opsional)
                        </label>
                        <textarea
                            id="restock_notes"
                            v-model="restockForm.notes"
                            rows="3"
                            class="kopdes-input mt-1"
                            placeholder="Contoh: Kiriman Supplier Bulog Faktur #102"
                        ></textarea>
                        <InputError class="mt-1.5" :message="restockForm.errors.notes" />
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                    <SecondaryButton type="button" @click="closeRestockModal">
                        Batal
                    </SecondaryButton>
                    <PrimaryButton
                        class="!bg-emerald-600 hover:!bg-emerald-700"
                        :disabled="restockForm.processing"
                    >
                        {{ restockForm.processing ? 'Menyimpan...' : 'Simpan Restock' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal Penyesuaian Stok (Stock Adjustment / Opname) -->
        <Modal :show="activeAdjustItem !== null" max-width="lg" @close="closeAdjustModal">
            <form v-if="activeAdjustItem" @submit.prevent="submitAdjust" class="p-6">
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="text-lg font-bold text-slate-900">
                        Penyesuaian Stok Fisik (Stock Opname)
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-600">
                        {{ activeAdjustItem.name }} (<span class="kopdes-num">{{ activeAdjustItem.sku }}</span>) &mdash; Cabang:
                        <strong>{{ selectedBranch?.name }}</strong>
                    </p>
                </div>

                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-3 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-center text-xs">
                        <div>
                            <div class="text-slate-500">Stok Fisik Tercatat</div>
                            <div class="kopdes-num mt-1 text-base font-bold text-slate-900">
                                {{ activeAdjustItem.quantity }} {{ activeAdjustItem.unit }}
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-500">Batas Bawah Reservasi</div>
                            <div class="kopdes-num mt-1 text-base font-bold text-amber-700">
                                {{ activeAdjustItem.reserved_qty }} {{ activeAdjustItem.unit }}
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-500">Selisih Perubahan</div>
                            <div
                                class="kopdes-num mt-1 text-base font-extrabold"
                                :class="
                                    adjustDiff > 0
                                        ? 'text-emerald-700'
                                        : adjustDiff < 0
                                          ? 'text-rose-700'
                                          : 'text-slate-700'
                                "
                            >
                                {{ adjustDiff > 0 ? `+${adjustDiff}` : adjustDiff }}
                                {{ activeAdjustItem.unit }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="activeAdjustItem.reserved_qty > 0"
                        class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900"
                    >
                        <strong>Info Reservasi Midtrans:</strong> Saat ini terdapat
                        <strong class="kopdes-num">{{ activeAdjustItem.reserved_qty }} {{ activeAdjustItem.unit }}</strong>
                        yang sedang ditahan transaksi Midtrans pending. Stok fisik aktual tidak boleh diatur lebih kecil dari
                        <strong class="kopdes-num">{{ activeAdjustItem.reserved_qty }}</strong>.
                    </div>

                    <div>
                        <label for="actual_quantity" class="block text-xs font-semibold text-slate-700">
                            Stok Fisik Aktual Hasil Opname ({{ activeAdjustItem.unit }}) &mdash; Min: {{ activeAdjustItem.reserved_qty }} <span class="text-rose-600">*</span>
                        </label>
                        <input
                            id="actual_quantity"
                            v-model="adjustForm.actual_quantity"
                            type="number"
                            :min="activeAdjustItem.reserved_qty"
                            step="1"
                            class="kopdes-input kopdes-num mt-1 font-bold"
                            required
                        />
                        <p
                            v-if="isAdjustBelowReserved"
                            class="mt-1.5 text-xs font-semibold text-rose-600"
                        >
                            Stok fisik aktual tidak boleh lebih kecil dari stok yang sedang ditahan transaksi Midtrans ({{ activeAdjustItem.reserved_qty }}).
                        </p>
                        <InputError class="mt-1.5" :message="adjustForm.errors.actual_quantity" />
                        <InputError class="mt-1.5" :message="adjustForm.errors.product_id" />
                    </div>

                    <div>
                        <label for="adjust_notes" class="block text-xs font-semibold text-slate-700">
                            Catatan Alasan Penyesuaian Stok (Wajib Diisi) <span class="text-rose-600">*</span>
                        </label>
                        <textarea
                            id="adjust_notes"
                            v-model="adjustForm.notes"
                            rows="3"
                            class="kopdes-input mt-1"
                            placeholder="Contoh: Kemasan rusak 2 pcs saat stock opname mingguan"
                            required
                        ></textarea>
                        <InputError class="mt-1.5" :message="adjustForm.errors.notes" />
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                    <SecondaryButton type="button" @click="closeAdjustModal">
                        Batal
                    </SecondaryButton>
                    <PrimaryButton
                        :disabled="adjustForm.processing || isAdjustBelowReserved"
                    >
                        {{ adjustForm.processing ? 'Menyimpan...' : 'Simpan Penyesuaian Stok' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <ImportExcelModal
            :show="showImportModal"
            type="stocks"
            title="Import Massal Stok Awal Cabang"
            description="Tambah stok awal produk secara massal. Setiap baris valid otomatis dicatat ke Audit Log Stok (restock)."
            :branches="branches"
            :require-branch-selection="Boolean(permissions.is_admin_nasional)"
            :default-branch-id="selectedBranch?.id ?? ''"
            @close="showImportModal = false"
        />
    </AuthenticatedLayout>
</template>
