<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Katalog Produk Global KOPDES
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Master produk terpusat dengan harga jual seragam nasional (perubahan harga hanya berlaku untuk transaksi baru)
                    </p>
                </div>

                <div v-if="permissions.can_manage" class="flex items-center gap-2">
                    <Link
                        :href="route('categories.index')"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        Kelola Kategori
                    </Link>
                    <Link
                        :href="route('products.create')"
                        class="inline-flex items-center rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800"
                    >
                        + Tambah Produk Global
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <!-- Filter Bar -->
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    >
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
                                Kategori
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

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Status Penjualan POS
                            </label>
                            <select
                                v-model="filterForm.status"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="">Semua Status</option>
                                <option value="active">Aktif Dijual</option>
                                <option value="inactive">Nonaktif</option>
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

                <!-- Products Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        SKU &amp; Barcode
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Nama Produk
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Kategori
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Satuan
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        HPP
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Harga Normal
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Harga Member
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Status
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="product in products"
                                    :key="product.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="font-mono text-xs font-bold text-gray-900">
                                            {{ product.sku }}
                                        </div>
                                        <div class="font-mono text-xs text-gray-500">
                                            {{ product.barcode }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-900">
                                        {{ product.name }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ product.category?.name ?? '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center text-gray-600">
                                        {{ product.unit }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-700">
                                        {{ formatRupiah(product.cost_price) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-bold text-gray-900">
                                        {{ formatRupiah(product.selling_price) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono">
                                        <span
                                            v-if="product.member_price !== null && product.member_price !== undefined"
                                            class="font-bold text-emerald-700"
                                        >
                                            {{ formatRupiah(product.member_price) }}
                                        </span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <span
                                            v-if="product.is_active"
                                            class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800"
                                        >
                                            Aktif
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800"
                                        >
                                            Nonaktif
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div
                                            v-if="permissions.can_manage"
                                            class="inline-flex items-center gap-2"
                                        >
                                            <Link
                                                :href="route('products.edit', product.id)"
                                                class="rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                @click="deleteProduct(product)"
                                                class="rounded border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-100"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                        <span
                                            v-else
                                            class="text-xs text-gray-400"
                                        >
                                            Katalog Pusat
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="products.length === 0">
                                    <td
                                        colspan="9"
                                        class="px-4 py-8 text-center text-sm text-gray-500"
                                    >
                                        Tidak ada produk dalam katalog.
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
