<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    branches: {
        type: Array,
        required: true,
    },
    provinces: {
        type: Array,
        default: () => [],
    },
    regencies: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    permissions: {
        type: Object,
        default: () => ({ can_create: false }),
    },
});

const page = usePage();
const userRole = page.props.auth?.user?.role ?? '';

const filterForm = reactive({
    search: props.filters.search ?? '',
    province_id: props.filters.province_id ?? '',
    regency_id: props.filters.regency_id ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get(
        route('branches.index'),
        {
            search: filterForm.search || undefined,
            province_id: filterForm.province_id || undefined,
            regency_id: filterForm.regency_id || undefined,
            status: filterForm.status || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const onProvinceFilterChange = () => {
    filterForm.regency_id = '';
    applyFilters();
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.province_id = '';
    filterForm.regency_id = '';
    filterForm.status = '';
    router.get(route('branches.index'));
};

const toggleBranchActive = (branch) => {
    const actionLabel = branch.is_active ? 'menonaktifkan' : 'mengaktifkan';
    if (
        !window.confirm(
            `Apakah Anda yakin ingin ${actionLabel} cabang ${branch.name} (${branch.code})?`,
        )
    ) {
        return;
    }

    router.patch(
        route('branches.toggle-active', branch.id),
        {},
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Manajemen Cabang KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                            Manajemen Cabang KOPDES
                        </h1>
                        <StatusBadge variant="kopdes" size="xs">
                            1 Desa = 1 KOPDES
                        </StatusBadge>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Daftar Koperasi Desa Merah Putih terdaftar beserta cakupan wilayah administratif
                    </p>
                </div>

                <div v-if="permissions.can_create">
                    <Link
                        :href="route('branches.create')"
                        class="btn-kopdes-primary"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Cabang KOPDES
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Filter Panel -->
                <div class="kopdes-card p-5">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <div>
                            <label for="branch-search" class="block text-xs font-semibold text-slate-700">
                                Cari Kode / Nama Cabang
                            </label>
                            <input
                                id="branch-search"
                                v-model="filterForm.search"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="KOP-3502 / Ngunut..."
                            />
                        </div>

                        <div v-if="userRole === 'admin_nasional'">
                            <label for="branch-province" class="block text-xs font-semibold text-slate-700">
                                Provinsi
                            </label>
                            <select
                                id="branch-province"
                                v-model="filterForm.province_id"
                                @change="onProvinceFilterChange"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Provinsi</option>
                                <option
                                    v-for="prov in provinces"
                                    :key="prov.id"
                                    :value="prov.id"
                                >
                                    {{ prov.id }} — {{ prov.name }}
                                </option>
                            </select>
                        </div>

                        <div v-if="userRole === 'admin_nasional' || userRole === 'admin_provinsi'">
                            <label for="branch-regency" class="block text-xs font-semibold text-slate-700">
                                Kabupaten/Kota
                            </label>
                            <select
                                id="branch-regency"
                                v-model="filterForm.regency_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Kabupaten/Kota</option>
                                <option
                                    v-for="reg in regencies"
                                    :key="reg.id"
                                    :value="reg.id"
                                >
                                    {{ reg.id }} — {{ reg.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="branch-status" class="block text-xs font-semibold text-slate-700">
                                Status Operasional
                            </label>
                            <select
                                id="branch-status"
                                v-model="filterForm.status"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
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

                <!-- Table -->
                <div class="kopdes-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Daftar Cabang KOPDES
                        </h2>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ branches.length }} Cabang</span>
                        </StatusBadge>
                    </div>
                    <div v-if="branches.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Kode
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Nama Cabang KOPDES
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Desa &amp; Kecamatan
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Kabupaten/Kota &amp; Provinsi
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Telepon
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
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-xs font-bold text-slate-900">
                                        {{ branch.code }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-900">
                                            {{ branch.name }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ branch.address }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-medium text-slate-900">
                                            Desa {{ branch.village?.name ?? branch.village_id }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            Kec. {{ branch.district?.name ?? branch.district_id }}
                                            <span class="kopdes-num text-[11px] text-slate-400">
                                                ({{ branch.village_id }})
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="text-slate-900">
                                            {{ branch.regency?.name ?? branch.regency_id }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ branch.province?.name ?? branch.province_id }}
                                        </div>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-xs text-slate-600">
                                        {{ branch.phone || '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <StatusBadge :variant="branch.is_active ? 'emerald' : 'rose'" size="xs" dot>
                                            {{ branch.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </StatusBadge>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                        <div
                                            v-if="branch.can_update"
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <Link
                                                :href="route('branches.edit', branch.id)"
                                                class="cursor-pointer rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                @click="toggleBranchActive(branch)"
                                                class="cursor-pointer rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                                :class="
                                                    branch.is_active
                                                        ? 'border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100'
                                                        : 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                                "
                                            >
                                                {{ branch.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </div>
                                        <span v-else class="text-xs text-slate-400">
                                            Hanya Lihat
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else
                        title="Tidak ada data cabang KOPDES"
                        description="Belum ada cabang yang sesuai dengan filter wilayah atau status operasional terpilih."
                    >
                        <template v-if="permissions.can_create" #actions>
                            <Link :href="route('branches.create')" class="btn-kopdes-primary !text-xs">
                                + Tambah Cabang KOPDES
                            </Link>
                        </template>
                    </EmptyState>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
