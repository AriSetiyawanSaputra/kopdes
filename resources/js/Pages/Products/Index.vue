<script setup>
import { reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImportExcelModal from '@/Components/ImportExcelModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    products: {
        type: Array,
        required: true,
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
        default: () => ({ can_manage: false }),
    },
});

const filterForm = reactive({
    search: props.filters.search ?? '',
    category_id: props.filters.category_id ?? '',
    status: props.filters.status ?? '',
});

const showImportModal = ref(false);

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

const applyFilters = () => {
    router.get(
        route('products.index'),
        {
            search: filterForm.search || undefined,
            category_id: filterForm.category_id || undefined,
            status: filterForm.status || undefined,
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
    filterForm.status = '';
    router.get(route('products.index'));
};

const deleteProduct = (product) => {
    if (
        !window.confirm(
            `Apakah Anda yakin ingin menghapus produk "${product.name}" (${product.sku})? Data transaksi historis tetap aman (Soft Delete).`,
        )
    ) {
        return;
    }

    router.delete(route('products.destroy', product.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Katalog Produk Global KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                            Katalog Produk Global KOPDES
                        </h1>
                        <StatusBadge variant="kopdes" size="xs">
                            Harga Seragam Nasional
                        </StatusBadge>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Master produk terpusat (perubahan harga hanya berlaku untuk transaksi baru &bull; Snapshot HPP aman)
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('products.barcode-labels')"
                        class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                    >
                        Cetak Label Barcode
                    </Link>
                    <template v-if="permissions.can_manage">
                        <button
                            type="button"
                            @click="showImportModal = true"
                            class="btn-kopdes-emerald"
                        >
                            Import Excel/CSV
                        </button>
                        <Link
                            :href="route('categories.index')"
                            class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                        >
                            Kelola Kategori
                        </Link>
                        <Link
                            :href="route('products.create')"
                            class="btn-kopdes-primary !px-3.5 !py-2 !text-xs"
                        >
                            + Tambah Produk Global
                        </Link>
                    </template>
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
                            <label for="prod-search" class="block text-xs font-semibold text-slate-700">
                                Cari Nama / SKU / Barcode
                            </label>
                            <input
                                id="prod-search"
                                v-model="filterForm.search"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="Beras / SKU / 899..."
                            />
                        </div>

                        <div>
                            <label for="prod-category" class="block text-xs font-semibold text-slate-700">
                                Kategori
                            </label>
                            <select
                                id="prod-category"
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

                        <div>
                            <label for="prod-status" class="block text-xs font-semibold text-slate-700">
                                Status Penjualan POS
                            </label>
                            <select
                                id="prod-status"
                                v-model="filterForm.status"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Status</option>
                                <option value="active">Aktif Dijual</option>
                                <option value="inactive">Nonaktif</option>
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

                <!-- Products Table -->
                <div class="kopdes-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Daftar Produk Katalog Nasional
                        </h2>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ products.length }} Produk</span>
                        </StatusBadge>
                    </div>
                    <div v-if="products.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        SKU &amp; Barcode
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Nama Produk
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Kategori
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Satuan
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        HPP
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Harga Normal
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Harga Member
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Status
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="product in products"
                                    :key="product.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <div class="kopdes-num text-xs font-bold text-slate-900">
                                            {{ product.sku }}
                                        </div>
                                        <div class="kopdes-num text-xs text-slate-500">
                                            {{ product.barcode }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 font-semibold text-slate-900">
                                        {{ product.name }}
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-700">
                                        <StatusBadge variant="slate" size="xs">
                                            {{ product.category?.name ?? '-' }}
                                        </StatusBadge>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center text-xs font-medium text-slate-600">
                                        {{ product.unit }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right text-slate-700">
                                        {{ formatRupiah(product.cost_price) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right font-bold text-slate-900">
                                        {{ formatRupiah(product.selling_price) }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-right">
                                        <span
                                            v-if="product.member_price !== null && product.member_price !== undefined"
                                            class="font-bold text-emerald-700"
                                        >
                                            {{ formatRupiah(product.member_price) }}
                                        </span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <StatusBadge :variant="product.is_active ? 'emerald' : 'rose'" size="xs" dot>
                                            {{ product.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </StatusBadge>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                        <div
                                            v-if="permissions.can_manage"
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <Link
                                                :href="route('products.edit', product.id)"
                                                class="cursor-pointer rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                @click="deleteProduct(product)"
                                                class="cursor-pointer rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-100"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                        <span v-else class="text-xs text-slate-400">
                                            Katalog Pusat
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else
                        title="Tidak ada produk dalam katalog"
                        description="Belum ada produk yang sesuai dengan filter pencarian, kategori, atau status penjualan."
                    >
                        <template v-if="permissions.can_manage" #actions>
                            <Link :href="route('products.create')" class="btn-kopdes-primary !text-xs">
                                + Tambah Produk Global
                            </Link>
                        </template>
                    </EmptyState>
                </div>
            </div>
        </div>

        <ImportExcelModal
            :show="showImportModal"
            type="products"
            title="Import Massal Master Produk Global"
            description="Tambahkan banyak produk sekaligus ke katalog nasional (validasi all-or-nothing, maks 2.000 baris)."
            @close="showImportModal = false"
        />
    </AuthenticatedLayout>
</template>
