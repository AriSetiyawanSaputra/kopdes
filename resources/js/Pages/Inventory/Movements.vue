<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
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

const typeVariant = (type) => {
    return {
        restock: 'emerald',
        adjustment: 'amber',
        sale: 'indigo',
        void_return: 'rose',
    }[type] ?? 'slate';
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
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                            Audit Log Pergerakan Stok
                        </h1>
                        <StatusBadge variant="slate" size="xs">
                            Append-Only (Immutable)
                        </StatusBadge>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Catatan riwayat mutasi stok bersifat <strong>immutable</strong> — tidak dapat diubah maupun dihapus
                    </p>
                </div>

                <div>
                    <Link
                        :href="
                            route('inventory.index', {
                                branch_id: filterForm.branch_id || undefined,
                            })
                        "
                        class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                    >
                        &larr; Kembali ke Stok Cabang
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
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-6"
                    >
                        <div v-if="branches.length > 1">
                            <label for="mov-branch" class="block text-xs font-semibold text-slate-700">
                                Cabang KOPDES
                            </label>
                            <select
                                id="mov-branch"
                                v-model="filterForm.branch_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
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
                            <label for="mov-product" class="block text-xs font-semibold text-slate-700">
                                Produk
                            </label>
                            <select
                                id="mov-product"
                                v-model="filterForm.product_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
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
                            <label for="mov-type" class="block text-xs font-semibold text-slate-700">
                                Tipe Mutasi
                            </label>
                            <select
                                id="mov-type"
                                v-model="filterForm.type"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
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
                            <label for="mov-start" class="block text-xs font-semibold text-slate-700">
                                Dari Tanggal
                            </label>
                            <input
                                id="mov-start"
                                v-model="filterForm.start_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="mov-end" class="block text-xs font-semibold text-slate-700">
                                Sampai Tanggal
                            </label>
                            <input
                                id="mov-end"
                                v-model="filterForm.end_date"
                                type="date"
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div>
                            <label for="mov-search" class="block text-xs font-semibold text-slate-700">
                                Cari Catatan / Produk
                            </label>
                            <input
                                id="mov-search"
                                v-model="filterForm.search"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="Faktur / Opname..."
                            />
                        </div>

                        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
                            <PrimaryButton type="submit">
                                Terapkan Filter
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="resetFilters">
                                Reset
                            </SecondaryButton>
                        </div>
                    </form>
                </div>

                <!-- Movements Table (Read-Only / Append-Only) -->
                <div class="kopdes-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Riwayat Mutasi Stok
                        </h2>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ movements.length }} Catatan</span>
                        </StatusBadge>
                    </div>
                    <div v-if="movements.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Waktu
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Cabang KOPDES
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Produk
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Tipe Mutasi
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Sebelum
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Perubahan
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Sesudah
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Petugas
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Catatan / Alasan
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="m in movements"
                                    :key="m.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-xs text-slate-600">
                                        {{ m.created_at }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900">
                                            {{ m.branch?.name ?? '-' }}
                                        </div>
                                        <div class="kopdes-num text-xs text-slate-500">
                                            {{ m.branch?.code ?? '' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-900">
                                            {{ m.product?.name ?? '-' }}
                                        </div>
                                        <div class="kopdes-num text-xs text-slate-500">
                                            {{ m.product?.sku ?? '' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <StatusBadge :variant="typeVariant(m.type)" size="xs">
                                            {{ typeLabel(m.type) }}
                                        </StatusBadge>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center text-slate-600">
                                        {{ m.qty_before }}
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center font-bold">
                                        <span
                                            :class="
                                                m.qty_change > 0
                                                    ? 'text-emerald-700'
                                                    : m.qty_change < 0
                                                      ? 'text-rose-700'
                                                      : 'text-slate-600'
                                            "
                                        >
                                            {{ m.qty_change > 0 ? `+${m.qty_change}` : m.qty_change }}
                                        </span>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center font-bold text-slate-900">
                                        {{ m.qty_after }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900">
                                            {{ m.user?.name ?? '-' }}
                                        </div>
                                        <div class="text-[11px] uppercase text-slate-500">
                                            {{ m.user?.role ?? '' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-700">
                                        {{ m.notes || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else
                        title="Belum ada riwayat pergerakan stok"
                        description="Setiap aktivitas restock, penyesuaian opname, penjualan POS, maupun retur akan tercatat otomatis di sini."
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
