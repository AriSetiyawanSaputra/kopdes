<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Manajemen Cabang KOPDES
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Daftar Koperasi Desa Merah Putih terdaftar (Aturan Mutlak: 1 Desa = 1 KOPDES)
                    </p>
                </div>

                <div v-if="permissions.can_create">
                    <Link
                        :href="route('branches.create')"
                        class="inline-flex items-center rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2"
                    >
                        + Tambah Cabang KOPDES
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <!-- Filter Panel -->
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Cari Kode / Nama Cabang
                            </label>
                            <TextInput
                                v-model="filterForm.search"
                                type="text"
                                class="mt-1 block w-full text-sm"
                                placeholder="KOP-3502 / Ngunut..."
                            />
                        </div>

                        <div v-if="userRole === 'admin_nasional'">
                            <label class="block text-xs font-semibold text-gray-700">
                                Provinsi
                            </label>
                            <select
                                v-model="filterForm.province_id"
                                @change="onProvinceFilterChange"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
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
                            <label class="block text-xs font-semibold text-gray-700">
                                Kabupaten/Kota
                            </label>
                            <select
                                v-model="filterForm.regency_id"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
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
                            <label class="block text-xs font-semibold text-gray-700">
                                Status Operasional
                            </label>
                            <select
                                v-model="filterForm.status"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
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

                <!-- Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Kode
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Nama Cabang KOPDES
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Desa &amp; Kecamatan
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Kabupaten/Kota &amp; Provinsi
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Telepon
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
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-bold text-gray-900">
                                        {{ branch.code }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">
                                            {{ branch.name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ branch.address }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">
                                            Desa {{ branch.village?.name ?? branch.village_id }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Kec. {{ branch.district?.name ?? branch.district_id }}
                                            <span class="font-mono text-[11px] text-gray-400">
                                                ({{ branch.village_id }})
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-gray-900">
                                            {{ branch.regency?.name ?? branch.regency_id }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ branch.province?.name ?? branch.province_id }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                        {{ branch.phone || '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <span
                                            v-if="branch.is_active"
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
                                            v-if="branch.can_update"
                                            class="inline-flex items-center gap-2"
                                        >
                                            <Link
                                                :href="route('branches.edit', branch.id)"
                                                class="rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                @click="toggleBranchActive(branch)"
                                                class="rounded px-2.5 py-1 text-xs font-semibold shadow-sm"
                                                :class="
                                                    branch.is_active
                                                        ? 'border border-red-200 bg-red-50 text-red-700 hover:bg-red-100'
                                                        : 'border border-green-200 bg-green-50 text-green-700 hover:bg-green-100'
                                                "
                                            >
                                                {{ branch.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </div>
                                        <span
                                            v-else
                                            class="text-xs text-gray-400"
                                        >
                                            Hanya Lihat
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="branches.length === 0">
                                    <td
                                        colspan="7"
                                        class="px-4 py-8 text-center text-sm text-gray-500"
                                    >
                                        Tidak ada data cabang KOPDES yang sesuai dengan filter.
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
