<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    users: {
        type: Array,
        required: true,
    },
    branches: {
        type: Array,
        default: () => [],
    },
    creatableRoles: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const roleBadgeMap = {
    admin_nasional: {
        label: 'Admin Nasional',
        classes: 'bg-purple-100 text-purple-800',
    },
    admin_provinsi: {
        label: 'Admin Provinsi',
        classes: 'bg-indigo-100 text-indigo-800',
    },
    admin_kabupaten: {
        label: 'Admin Kabupaten',
        classes: 'bg-blue-100 text-blue-800',
    },
    admin_cabang: {
        label: 'Admin Cabang',
        classes: 'bg-amber-100 text-amber-800',
    },
    kasir: {
        label: 'Kasir POS',
        classes: 'bg-emerald-100 text-emerald-800',
    },
};

const filterForm = reactive({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    branch_id: props.filters.branch_id ?? '',
});

const applyFilters = () => {
    router.get(
        route('users.index'),
        {
            search: filterForm.search || undefined,
            role: filterForm.role || undefined,
            branch_id: filterForm.branch_id || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.role = '';
    filterForm.branch_id = '';
    router.get(route('users.index'));
};

const toggleUserActive = (user) => {
    const actionLabel = user.is_active ? 'menonaktifkan' : 'mengaktifkan';
    if (
        !window.confirm(
            `Apakah Anda yakin ingin ${actionLabel} akun ${user.name}?`,
        )
    ) {
        return;
    }

    router.patch(
        route('users.toggle-active', user.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

const formatScope = (user) => {
    if (user.role === 'admin_nasional') {
        return 'Nasional (Seluruh Indonesia)';
    }

    if (user.role === 'admin_provinsi') {
        return user.province?.name ?? `Provinsi #${user.province_id}`;
    }

    if (user.role === 'admin_kabupaten') {
        return `${user.regency?.name ?? user.regency_id} (${user.province?.name ?? user.province_id})`;
    }

    if (user.branch) {
        return `${user.branch.name} (${user.branch.code})`;
    }

    return '-';
};
</script>

<template>
    <Head title="Manajemen Pengguna & Kasir" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Manajemen Pengguna Admin &amp; Kasir POS
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Pengelolaan akun berjenjang sesuai RBAC wilayah serta binding Kartu RFID &amp; PIN 6 Digit Kasir
                    </p>
                </div>

                <div v-if="creatableRoles.length > 0">
                    <Link
                        :href="route('users.create')"
                        class="inline-flex items-center rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800"
                    >
                        + Tambah Pengguna / Kasir
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
                                Cari Nama / Email / UID Kartu
                            </label>
                            <TextInput
                                v-model="filterForm.search"
                                type="text"
                                class="mt-1 block w-full text-sm"
                                placeholder="Nama, email, atau UID RFID..."
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Filter Role
                            </label>
                            <select
                                v-model="filterForm.role"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="">Semua Role</option>
                                <option value="admin_nasional">Admin Nasional</option>
                                <option value="admin_provinsi">Admin Provinsi</option>
                                <option value="admin_kabupaten">Admin Kabupaten</option>
                                <option value="admin_cabang">Admin Cabang</option>
                                <option value="kasir">Kasir POS</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Filter Cabang KOPDES
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

                <!-- Users Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Nama Pengguna
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Role
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Cakupan Wilayah / Cabang
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Metode Login / Kartu RFID
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
                                    v-for="u in users"
                                    :key="u.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">
                                            {{ u.name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ u.email || 'Tanpa Email (Login via RFID + PIN)' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                            :class="
                                                roleBadgeMap[u.role]?.classes ??
                                                'bg-gray-100 text-gray-800'
                                            "
                                        >
                                            {{ roleBadgeMap[u.role]?.label ?? u.role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ formatScope(u) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div v-if="u.role === 'kasir'">
                                            <span
                                                class="inline-flex items-center rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-mono text-xs font-bold text-emerald-800"
                                            >
                                                UID: {{ u.card_uid }}
                                            </span>
                                            <div class="mt-0.5 text-[11px] text-gray-500">
                                                + PIN 6 Digit Terenkripsi
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-gray-600">
                                            Email + Kata Sandi
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <span
                                            v-if="u.is_active"
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
                                            v-if="u.can_update"
                                            class="inline-flex items-center gap-2"
                                        >
                                            <Link
                                                :href="route('users.edit', u.id)"
                                                class="rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                v-if="u.can_toggle_active"
                                                type="button"
                                                @click="toggleUserActive(u)"
                                                class="rounded px-2.5 py-1 text-xs font-semibold shadow-sm"
                                                :class="
                                                    u.is_active
                                                        ? 'border border-red-200 bg-red-50 text-red-700 hover:bg-red-100'
                                                        : 'border border-green-200 bg-green-50 text-green-700 hover:bg-green-100'
                                                "
                                            >
                                                {{ u.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
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
                                <tr v-if="users.length === 0">
                                    <td
                                        colspan="6"
                                        class="px-4 py-8 text-center text-sm text-gray-500"
                                    >
                                        Tidak ada pengguna atau kasir yang sesuai dengan filter.
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
