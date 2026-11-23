<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    movements: {
        type: Array,
        required: true,
    },
    branches: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
    movementTypes: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const filterForm = reactive({
    branch_id: props.filters.branch_id ?? '',
    product_id: props.filters.product_id ?? '',
    type: props.filters.type ?? '',
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
    search: props.filters.search ?? '',
});

const typeBadgeClass = (type) => {
    return {
        restock: 'bg-green-100 text-green-800 border-green-300',
        adjustment: 'bg-amber-100 text-amber-800 border-amber-300',
        sale: 'bg-blue-100 text-blue-800 border-blue-300',
        void_return: 'bg-purple-100 text-purple-800 border-purple-300',
    }[type] ?? 'bg-gray-100 text-gray-800 border-gray-300';
};

const typeLabel = (type) => {
    return {
        restock: 'Restock (Masuk)',
        adjustment: 'Penyesuaian (Opname)',
        sale: 'Penjualan POS',
        void_return: 'Void / Retur',
    }[type] ?? type;
};

const applyFilters = () => {
    router.get(
        route('inventory.movements'),
        {
            branch_id: filterForm.branch_id || undefined,
            product_id: filterForm.product_id || undefined,
            type: filterForm.type || undefined,
            start_date: filterForm.start_date || undefined,
            end_date: filterForm.end_date || undefined,
            search: filterForm.search || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    filterForm.branch_id = '';
    filterForm.product_id = '';
    filterForm.type = '';
    filterForm.start_date = '';
    filterForm.end_date = '';
    filterForm.search = '';
    router.get(route('inventory.movements'));
};
</script>

<template>
    <Head title="Audit Log Pergerakan Stok (Immutable)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Audit Log Pergerakan Stok (`stock_movements`)
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Catatan riwayat mutasi stok bersifat <strong>immutable (append-only)</strong> — tidak dapat diubah maupun dihapus
                    </p>
                </div>

                <div>
                    <Link
                        :href="
                            route('inventory.index', {
                                branch_id: filterForm.branch_id || undefined,
                            })
                        "
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        &larr; Kembali ke Stok Cabang
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
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6"
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
                                <option value="">Semua Cabang</option>
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
                                Produk
                            </label>
                            <select
                                v-model="filterForm.product_id"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="">Semua Produk</option>
                                <option
                                    v-for="prod in products"
                                    :key="prod.id"
                                    :value="prod.id"
                                >
                                    {{ prod.sku }} — {{ prod.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Tipe Mutasi
                            </label>
                            <select
                                v-model="filterForm.type"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="">Semua Tipe Mutasi</option>
                                <option
                                    v-for="t in movementTypes"
                                    :key="t.value"
                                    :value="t.value"
                                >
                                    {{ t.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Dari Tanggal
                            </label>
                            <TextInput
                                v-model="filterForm.start_date"
                                type="date"
                                class="mt-1 block w-full text-sm"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Sampai Tanggal
                            </label>
                            <TextInput
                                v-model="filterForm.end_date"
                                type="date"
                                class="mt-1 block w-full text-sm"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Cari Catatan / Produk
                            </label>
                            <TextInput
                                v-model="filterForm.search"
                                type="text"
                                class="mt-1 block w-full text-sm"
                                placeholder="Faktur / Opname..."
                            />
                        </div>

                        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
                            <PrimaryButton
                                type="submit"
                                class="bg-red-700 hover:bg-red-800"
                            >
                                Terapkan Filter
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

                <!-- Movements Table (Read-Only / Append-Only) -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Waktu (`created_at`)
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Cabang KOPDES
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Produk
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Tipe Mutasi
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Stok Sebelum (`qty_before`)
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Perubahan (`qty_change`)
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Stok Sesudah (`qty_after`)
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Petugas
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Catatan / Alasan
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="m in movements"
                                    :key="m.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-700">
                                        {{ m.created_at }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">
                                            {{ m.branch?.name ?? '-' }}
                                        </div>
                                        <div class="font-mono text-xs text-gray-500">
                                            {{ m.branch?.code ?? '' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">
                                            {{ m.product?.name ?? '-' }}
                                        </div>
                                        <div class="font-mono text-xs text-gray-500">
                                            {{ m.product?.sku ?? '' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center rounded border px-2 py-0.5 text-xs font-semibold"
                                            :class="typeBadgeClass(m.type)"
                                        >
                                            {{ typeLabel(m.type) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono text-gray-700">
                                        {{ m.qty_before }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono font-bold">
                                        <span
                                            :class="
                                                m.qty_change > 0
                                                    ? 'text-green-700'
                                                    : m.qty_change < 0
                                                      ? 'text-red-700'
                                                      : 'text-gray-600'
                                            "
                                        >
                                            {{ m.qty_change > 0 ? `+${m.qty_change}` : m.qty_change }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-mono font-bold text-gray-900">
                                        {{ m.qty_after }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">
                                            {{ m.user?.name ?? '-' }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ m.user?.role ?? '' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700">
                                        {{ m.notes || '-' }}
                                    </td>
                                </tr>
                                <tr v-if="movements.length === 0">
                                    <td
                                        colspan="9"
                                        class="px-4 py-8 text-center text-sm text-gray-500"
                                    >
                                        Belum ada riwayat pergerakan stok yang tercatat untuk filter ini.
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
