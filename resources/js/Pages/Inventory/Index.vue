<script setup>
import { computed, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Inventori &amp; Persediaan Barang Cabang
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        <span v-if="selectedBranch" class="font-semibold text-gray-700">
                            {{ selectedBranch.name }} ({{ selectedBranch.code }})
                        </span>
                        <span v-else>Pantau stok fisik, reservasi Midtrans, dan stok tersedia</span>
                        — rumus ketersediaan:
                        <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-800">
                            Stok Tersedia = Stok Fisik - Ditahan Midtrans
                        </code>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="
                            route('inventory.movements', {
                                branch_id: selectedBranch?.id || undefined,
                            })
                        "
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        Riwayat &amp; Audit Log Stok
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <!-- Peringatan Stok Menipis -->
                <div
                    v-if="lowStockCount > 0"
                    class="flex items-center justify-between rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    <div>
                        <span class="font-bold">Perhatian:</span>
                        Terdapat <span class="font-extrabold">{{ lowStockCount }}</span> produk aktif dengan
                        <strong>Stok Tersedia &le; Batas Minimum</strong> pada cabang ini.
                    </div>
                    <button
                        v-if="!filterForm.low_stock"
                        type="button"
                        @click="
                            filterForm.low_stock = true;
                            applyFilters();
                        "
                        class="rounded border border-red-300 bg-white px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100"
                    >
                        Tampilkan Stok Menipis
                    </button>
                </div>

                <!-- Filter Bar -->
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <div v-if="branches.length > 1">
                            <label class="block text-xs font-semibold text-gray-700">
                                Cabang KOPDES
                            </label>
                            <select
                                v-model="filterForm.branch_id"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
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
                            <label class="block text-xs font-semibold text-gray-700">
                                Cari Nama / SKU / Barcode
                            </label>
                            <TextInput
                                v-model="filterForm.search"
                                type="text"
                                class="mt-1 block w-full text-sm"
                                placeholder="Beras / SKU / 899..."
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Kategori Produk
                            </label>
                            <select
                                v-model="filterForm.category_id"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
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
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                                <input
                                    v-model="filterForm.low_stock"
                                    type="checkbox"
                                    @change="applyFilters"
                                    class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500"
                                />
                                <span class="text-xs font-semibold">
                                    Hanya Stok Menipis (&le; Min)
                                </span>
                            </label>
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

                <!-- Inventory Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        SKU &amp; Barcode
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Produk &amp; Kategori
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Harga Jual
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Stok Fisik (`quantity`)
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Ditahan Midtrans (`reserved_qty`)
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Stok Tersedia (`available_stock`)
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Batas Minimum (`min_stock`)
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="item in stocks"
                                    :key="item.product_id"
                                    :class="
                                        item.available_stock <= item.min_stock
                                            ? 'bg-red-50/60 hover:bg-red-50'
                                            : 'hover:bg-gray-50'
                                    "
                                >
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="font-mono text-xs font-bold text-gray-900">
                                            {{ item.sku }}
                                        </div>
                                        <div class="font-mono text-xs text-gray-500">
                                            {{ item.barcode }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">
                                            {{ item.name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ item.category?.name ?? '-' }} • Satuan: {{ item.unit }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-gray-800">
                                        {{ formatRupiah(item.selling_price) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono text-sm font-bold text-gray-900">
                                        {{ item.quantity }}
                                        <span class="text-xs font-normal text-gray-500">{{ item.unit }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono text-sm">
                                        <span
                                            v-if="item.reserved_qty > 0"
                                            class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800"
                                        >
                                            {{ item.reserved_qty }} {{ item.unit }}
                                        </span>
                                        <span v-else class="text-gray-400">
                                            0
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <div class="inline-flex items-center gap-1.5">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 font-mono text-xs font-extrabold"
                                                :class="
                                                    item.available_stock <= item.min_stock
                                                        ? 'bg-red-100 text-red-800 ring-1 ring-red-300'
                                                        : 'bg-green-100 text-green-800'
                                                "
                                            >
                                                {{ item.available_stock }} {{ item.unit }}
                                            </span>
                                            <span
                                                v-if="item.available_stock <= item.min_stock"
                                                class="rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                                            >
                                                Menipis
                                            </span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono text-xs text-gray-600">
                                        {{ item.min_stock }} {{ item.unit }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div
                                            v-if="permissions.can_mutate"
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <button
                                                type="button"
                                                @click="openRestockModal(item)"
                                                class="rounded border border-green-300 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800 shadow-sm hover:bg-green-100"
                                            >
                                                + Restock
                                            </button>
                                            <button
                                                type="button"
                                                @click="openAdjustModal(item)"
                                                class="rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                                            >
                                                Sesuaikan Stok
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
                                            class="text-xs font-semibold text-red-700 hover:underline"
                                        >
                                            Lihat Log
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="stocks.length === 0">
                                    <td
                                        colspan="8"
                                        class="px-4 py-8 text-center text-sm text-gray-500"
                                    >
                                        Tidak ada data stok produk aktif yang sesuai dengan filter.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Stok (Restock) -->
        <Modal :show="activeRestockItem !== null" max-width="lg" @close="closeRestockModal">
            <form v-if="activeRestockItem" @submit.prevent="submitRestock" class="p-6">
                <div class="border-b border-gray-200 pb-3">
                    <h3 class="text-lg font-bold text-gray-900">
                        Tambah Stok Barang Masuk (Restock)
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ activeRestockItem.name }} ({{ activeRestockItem.sku }}) — Cabang:
                        {{ selectedBranch?.name }}
                    </p>
                </div>

                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-3 gap-3 rounded-lg bg-gray-50 p-3 text-center text-xs ring-1 ring-gray-200">
                        <div>
                            <div class="text-gray-500">Stok Fisik Saat Ini</div>
                            <div class="mt-0.5 font-mono text-base font-bold text-gray-900">
                                {{ activeRestockItem.quantity }} {{ activeRestockItem.unit }}
                            </div>
                        </div>
                        <div>
                            <div class="text-gray-500">Ditahan Midtrans</div>
                            <div class="mt-0.5 font-mono text-base font-bold text-amber-700">
                                {{ activeRestockItem.reserved_qty }} {{ activeRestockItem.unit }}
                            </div>
                        </div>
                        <div>
                            <div class="text-gray-500">Stok Fisik Setelah Restock</div>
                            <div class="mt-0.5 font-mono text-base font-extrabold text-green-700">
                                {{ estimatedRestockQtyAfter }} {{ activeRestockItem.unit }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <InputLabel
                            for="qty_add"
                            :value="`Jumlah Tambah Stok (${activeRestockItem.unit}) *`"
                        />
                        <TextInput
                            id="qty_add"
                            v-model="restockForm.qty_add"
                            type="number"
                            min="1"
                            step="1"
                            class="mt-1 block w-full font-mono"
                            required
                        />
                        <InputError class="mt-2" :message="restockForm.errors.qty_add" />
                        <InputError class="mt-2" :message="restockForm.errors.product_id" />
                    </div>

                    <div>
                        <InputLabel
                            for="restock_notes"
                            value="Catatan / Nomor Faktur Penerimaan (Opsional)"
                        />
                        <textarea
                            id="restock_notes"
                            v-model="restockForm.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            placeholder="Contoh: Kiriman Supplier Bulog Faktur #102"
                        ></textarea>
                        <InputError class="mt-2" :message="restockForm.errors.notes" />
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="closeRestockModal">
                        Batal
                    </SecondaryButton>
                    <PrimaryButton
                        class="bg-green-700 hover:bg-green-800"
                        :class="{ 'opacity-25': restockForm.processing }"
                        :disabled="restockForm.processing"
                    >
                        Simpan Restock
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal Penyesuaian Stok (Stock Adjustment / Opname) -->
        <Modal :show="activeAdjustItem !== null" max-width="lg" @close="closeAdjustModal">
            <form v-if="activeAdjustItem" @submit.prevent="submitAdjust" class="p-6">
                <div class="border-b border-gray-200 pb-3">
                    <h3 class="text-lg font-bold text-gray-900">
                        Penyesuaian Stok Fisik (Stock Adjustment)
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ activeAdjustItem.name }} ({{ activeAdjustItem.sku }}) — Cabang:
                        {{ selectedBranch?.name }}
                    </p>
                </div>

                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-3 gap-3 rounded-lg bg-gray-50 p-3 text-center text-xs ring-1 ring-gray-200">
                        <div>
                            <div class="text-gray-500">Stok Fisik Tercatat</div>
                            <div class="mt-0.5 font-mono text-base font-bold text-gray-900">
                                {{ activeAdjustItem.quantity }} {{ activeAdjustItem.unit }}
                            </div>
                        </div>
                        <div>
                            <div class="text-gray-500">Batas Bawah (`reserved_qty`)</div>
                            <div class="mt-0.5 font-mono text-base font-bold text-amber-700">
                                {{ activeAdjustItem.reserved_qty }} {{ activeAdjustItem.unit }}
                            </div>
                        </div>
                        <div>
                            <div class="text-gray-500">Selisih (`qty_change`)</div>
                            <div
                                class="mt-0.5 font-mono text-base font-extrabold"
                                :class="
                                    adjustDiff > 0
                                        ? 'text-green-700'
                                        : adjustDiff < 0
                                          ? 'text-red-700'
                                          : 'text-gray-700'
                                "
                            >
                                {{ adjustDiff > 0 ? `+${adjustDiff}` : adjustDiff }}
                                {{ activeAdjustItem.unit }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="activeAdjustItem.reserved_qty > 0"
                        class="rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800"
                    >
                        <strong>Info Reservasi Midtrans:</strong> Saat ini terdapat
                        <strong>{{ activeAdjustItem.reserved_qty }} {{ activeAdjustItem.unit }}</strong>
                        yang sedang ditahan transaksi Midtrans <code>pending</code>. Stok fisik aktual tidak boleh
                        diatur lebih kecil dari <strong>{{ activeAdjustItem.reserved_qty }}</strong>.
                    </div>

                    <div>
                        <InputLabel
                            for="actual_quantity"
                            :value="`Stok Fisik Aktual Hasil Opname (${activeAdjustItem.unit}) — Min: ${activeAdjustItem.reserved_qty} *`"
                        />
                        <TextInput
                            id="actual_quantity"
                            v-model="adjustForm.actual_quantity"
                            type="number"
                            :min="activeAdjustItem.reserved_qty"
                            step="1"
                            class="mt-1 block w-full font-mono"
                            required
                        />
                        <p
                            v-if="isAdjustBelowReserved"
                            class="mt-1.5 text-xs font-semibold text-red-600"
                        >
                            Stok fisik aktual tidak boleh lebih kecil dari stok yang sedang ditahan transaksi Midtrans ({{ activeAdjustItem.reserved_qty }}).
                        </p>
                        <InputError class="mt-2" :message="adjustForm.errors.actual_quantity" />
                        <InputError class="mt-2" :message="adjustForm.errors.product_id" />
                    </div>

                    <div>
                        <InputLabel
                            for="adjust_notes"
                            value="Catatan Alasan Penyesuaian Stok (Wajib Diisi) *"
                        />
                        <textarea
                            id="adjust_notes"
                            v-model="adjustForm.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            placeholder="Contoh: Kemasan rusak 2 pcs saat stock opname mingguan"
                            required
                        ></textarea>
                        <InputError class="mt-2" :message="adjustForm.errors.notes" />
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="closeAdjustModal">
                        Batal
                    </SecondaryButton>
                    <PrimaryButton
                        class="bg-red-700 hover:bg-red-800"
                        :class="{ 'opacity-25': adjustForm.processing || isAdjustBelowReserved }"
                        :disabled="adjustForm.processing || isAdjustBelowReserved"
                    >
                        Simpan Penyesuaian Stok
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
