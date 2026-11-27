<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
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
        variant: 'kopdes',
    },
    admin_provinsi: {
        label: 'Admin Provinsi',
        variant: 'indigo',
    },
    admin_kabupaten: {
        label: 'Admin Kabupaten',
        variant: 'indigo',
    },
    admin_cabang: {
        label: 'Admin Cabang',
        variant: 'amber',
    },
    kasir: {
        label: 'Kasir POS',
        variant: 'emerald',
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
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        Manajemen Pengguna Admin &amp; Kasir POS
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Pengelolaan akun berjenjang sesuai RBAC wilayah serta binding Kartu RFID &amp; PIN 6 Digit Kasir
                    </p>
                </div>

                <div v-if="creatableRoles.length > 0">
                    <Link
                        :href="route('users.create')"
                        class="btn-kopdes-primary"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Pengguna / Kasir
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
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <label for="user-search" class="block text-xs font-semibold text-slate-700">
                                Cari Nama / Email / UID Kartu
                            </label>
                            <input
                                id="user-search"
                                v-model="filterForm.search"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="Nama, email, atau UID RFID..."
                            />
                        </div>

                        <div>
                            <label for="user-role" class="block text-xs font-semibold text-slate-700">
                                Filter Role
                            </label>
                            <select
                                id="user-role"
                                v-model="filterForm.role"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
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
                            <label for="user-branch" class="block text-xs font-semibold text-slate-700">
                                Filter Cabang KOPDES
                            </label>
                            <select
                                id="user-branch"
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

                <!-- Users Table -->
                <div class="kopdes-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Daftar Pengguna &amp; Kasir
                        </h2>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ users.length }} Akun</span>
                        </StatusBadge>
                    </div>
                    <div v-if="users.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Nama Pengguna
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Role
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Cakupan Wilayah / Cabang
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Metode Login / Kartu RFID
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
                                    v-for="u in users"
                                    :key="u.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-900">
                                            {{ u.name }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ u.email || 'Tanpa Email (Login via RFID + PIN)' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <StatusBadge :variant="roleBadgeMap[u.role]?.variant ?? 'slate'" size="xs">
                                            {{ roleBadgeMap[u.role]?.label ?? u.role }}
                                        </StatusBadge>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-700">
                                        {{ formatScope(u) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <div v-if="u.role === 'kasir'">
                                            <StatusBadge variant="emerald" size="xs">
                                                <span class="kopdes-num">UID: {{ u.card_uid }}</span>
                                            </StatusBadge>
                                            <div class="mt-1 text-[11px] text-slate-500">
                                                + PIN 6 Digit Terenkripsi
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-slate-600">
                                            Email + Kata Sandi
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <StatusBadge :variant="u.is_active ? 'emerald' : 'rose'" size="xs" dot>
                                            {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </StatusBadge>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                        <div
                                            v-if="u.can_update"
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <Link
                                                :href="route('users.edit', u.id)"
                                                class="cursor-pointer rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                v-if="u.can_toggle_active"
                                                type="button"
                                                @click="toggleUserActive(u)"
                                                class="cursor-pointer rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                                :class="
                                                    u.is_active
                                                        ? 'border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100'
                                                        : 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                                "
                                            >
                                                {{ u.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
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
                        title="Tidak ada pengguna atau kasir"
                        description="Belum ada akun yang sesuai dengan filter pencarian, role, atau cabang terpilih."
                    >
                        <template v-if="creatableRoles.length > 0" #actions>
                            <Link :href="route('users.create')" class="btn-kopdes-primary !text-xs">
                                + Tambah Pengguna / Kasir
                            </Link>
                        </template>
                    </EmptyState>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
