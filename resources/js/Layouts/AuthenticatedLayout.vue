<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ToastNotification from '@/Components/ToastNotification.vue';
import { Link, usePage } from '@inertiajs/vue3';

const SIDEBAR_STORAGE_KEY = 'kopdes_sidebar_collapsed';

const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);
const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash ?? {});
const appSettings = computed(() => page.props.app_settings ?? {});
const isDemoMode = computed(() => Boolean(page.props.app?.demo_mode));

const institutionName = computed(
    () => appSettings.value?.institution_name || 'KOPDES Merah Putih',
);

const roleLabel = computed(() => {
    const map = {
        admin_nasional: 'Admin Nasional',
        admin_provinsi: 'Admin Provinsi',
        admin_kabupaten: 'Admin Kabupaten',
        admin_cabang: 'Admin Cabang',
        kasir: 'Kasir POS',
    };

    return map[user.value?.role] ?? user.value?.role ?? '-';
});

const scopeLabel = computed(() => {
    if (!user.value) {
        return '';
    }

    if (user.value.role === 'admin_nasional') {
        return 'Nasional (Seluruh Indonesia)';
    }

    if (user.value.role === 'admin_provinsi') {
        return user.value.province?.name ?? `Provinsi #${user.value.province_id}`;
    }

    if (user.value.role === 'admin_kabupaten') {
        return user.value.regency?.name ?? `Kabupaten #${user.value.regency_id}`;
    }

    if (user.value.branch) {
        return `${user.value.branch.name} (${user.value.branch.code})`;
    }

    return '';
});

const userInitials = computed(() => {
    const name = user.value?.name ?? 'AD';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
});

const hasRoute = (name) => {
    try {
        return route().has(name);
    } catch {
        return false;
    }
};

const isCurrent = (pattern) => {
    try {
        return route().current(pattern);
    } catch {
        return false;
    }
};

const toggleSidebarCollapse = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    try {
        window.localStorage.setItem(
            SIDEBAR_STORAGE_KEY,
            sidebarCollapsed.value ? '1' : '0',
        );
    } catch {
        // Ignore storage errors in restricted contexts
    }
};

const handleKeydown = (e) => {
    if (e.key === 'Escape' && sidebarOpen.value) {
        sidebarOpen.value = false;
    }
};

onMounted(() => {
    try {
        sidebarCollapsed.value =
            window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
    } catch {
        sidebarCollapsed.value = false;
    }
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown);
});

const allAdminRoles = [
    'admin_nasional',
    'admin_provinsi',
    'admin_kabupaten',
    'admin_cabang',
];

const sidebarGroups = computed(() => {
    const role = user.value?.role;
    const groups = [
        {
            title: 'Utama',
            items: [
                {
                    label: 'Dashboard Keuangan',
                    routeName: 'dashboard',
                    activePattern: 'dashboard',
                    icon: 'dashboard',
                    roles: allAdminRoles,
                },
            ],
        },
        {
            title: 'Operasional & Stok',
            items: [
                {
                    label: 'Shift Kasir',
                    routeName: 'shifts.index',
                    activePattern: 'shifts.*',
                    icon: 'shifts',
                    roles: allAdminRoles,
                },
                {
                    label: 'Retur & Void',
                    routeName: 'returns.index',
                    activePattern: 'returns.*',
                    icon: 'returns',
                    roles: allAdminRoles,
                },
                {
                    label: 'Stok Cabang',
                    routeName: 'inventory.index',
                    activePattern: 'inventory.index',
                    icon: 'stock',
                    roles: allAdminRoles,
                },
                {
                    label: 'Audit Log Stok',
                    routeName: 'inventory.movements',
                    activePattern: 'inventory.movements*',
                    icon: 'movements',
                    roles: allAdminRoles,
                },
            ],
        },
        {
            title: 'Keuangan & Laporan',
            items: [
                {
                    label: 'Penjualan & Laba Rugi',
                    routeName: 'reports.sales',
                    activePattern: 'reports.sales*',
                    icon: 'sales',
                    roles: allAdminRoles,
                },
                {
                    label: 'Biaya Operasional',
                    routeName: 'expenses.index',
                    activePattern: 'expenses.*',
                    icon: 'expenses',
                    roles: allAdminRoles,
                },
                {
                    label: 'Rekap Member (SHU)',
                    routeName: 'reports.members',
                    activePattern: 'reports.members*',
                    icon: 'shu',
                    roles: allAdminRoles,
                },
            ],
        },
        {
            title: 'Master Data',
            items: [
                {
                    label: 'Cabang KOPDES',
                    routeName: 'branches.index',
                    activePattern: 'branches.*',
                    icon: 'branches',
                    roles: allAdminRoles,
                },
                {
                    label: 'Anggota (Member)',
                    routeName: 'members.index',
                    activePattern: 'members.*',
                    icon: 'members',
                    roles: allAdminRoles,
                },
                {
                    label: 'Katalog Produk',
                    routeName: 'products.index',
                    activePattern: 'products.*',
                    icon: 'products',
                    roles: allAdminRoles,
                },
                {
                    label: 'Kategori Produk',
                    routeName: 'categories.index',
                    activePattern: 'categories.*',
                    icon: 'categories',
                    roles: ['admin_nasional'],
                },
                {
                    label: 'Pengguna & Kasir',
                    routeName: 'users.index',
                    activePattern: 'users.*',
                    icon: 'users',
                    roles: allAdminRoles,
                },
            ],
        },
        {
            title: 'Sistem',
            items: [
                {
                    label: 'Pengaturan Sistem',
                    routeName: 'settings.index',
                    activePattern: 'settings.*',
                    icon: 'settings',
                    roles: ['admin_nasional'],
                },
                {
                    label: 'Audit Trail',
                    routeName: 'activity-logs.index',
                    activePattern: 'activity-logs.*',
                    icon: 'audit',
                    roles: allAdminRoles,
                },
            ],
        },
    ];

    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => item.roles.includes(role) && hasRoute(item.routeName),
            ),
        }))
        .filter((group) => group.items.length > 0);
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <!-- Mobile Sidebar Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
                @click="sidebarOpen = false"
            ></div>
        </Transition>

        <!-- Sidebar Navigation (Collapsible Desktop + Slide-Over Mobile) -->
        <aside
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                sidebarCollapsed ? 'lg:w-[76px]' : 'lg:w-64',
            ]"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-800/90 bg-slate-900 text-slate-200 shadow-2xl transition-all duration-200 ease-out lg:shadow-none"
            aria-label="Navigasi Utama KOPDES"
        >
            <!-- Brand Header -->
            <div
                class="relative flex h-16 shrink-0 items-center justify-between border-b border-slate-800/90 bg-slate-950/60 px-3.5"
            >
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-kopdes-700 via-kopdes-500 to-kopdes-700"
                    aria-hidden="true"
                ></div>

                <Link
                    :href="route('dashboard')"
                    :title="institutionName"
                    class="flex min-w-0 cursor-pointer items-center gap-3 focus:outline-none"
                    :class="sidebarCollapsed ? 'lg:mx-auto' : ''"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl shadow-sm ring-1 ring-white/15"
                        :class="appSettings.institution_logo_url ? 'bg-white p-1' : 'bg-gradient-to-br from-kopdes-600 to-kopdes-800'"
                    >
                        <img
                            v-if="appSettings.institution_logo_url"
                            :src="appSettings.institution_logo_url"
                            :alt="institutionName"
                            class="h-full w-full object-contain"
                        />
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="h-5 w-5 text-white"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3L4 7v6c0 4.418 3.582 8 8 8s8-3.582 8-8V7l-8-4z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                        </svg>
                    </div>
                    <div
                        class="min-w-0 leading-tight"
                        :class="sidebarCollapsed ? 'lg:hidden' : ''"
                    >
                        <span class="block truncate text-sm font-extrabold tracking-tight text-white">
                            KOPDES
                        </span>
                        <span class="block truncate text-[11px] font-medium text-slate-400">
                            Merah Putih &bull; Keuangan
                        </span>
                    </div>
                </Link>

                <!-- Mobile Close Button -->
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white lg:hidden"
                    aria-label="Tutup menu navigasi"
                    @click="sidebarOpen = false"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Grouped Sidebar Navigation -->
            <nav class="kopdes-scrollbar flex-1 space-y-5 overflow-y-auto px-3 py-4">
                <div v-for="group in sidebarGroups" :key="group.title">
                    <p
                        v-if="!sidebarCollapsed"
                        class="mb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400"
                    >
                        {{ group.title }}
                    </p>
                    <div
                        v-else
                        class="mx-auto mb-2 hidden h-px w-6 bg-slate-800 lg:block"
                        aria-hidden="true"
                    ></div>
                    <p
                        v-if="sidebarCollapsed"
                        class="mb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400 lg:hidden"
                    >
                        {{ group.title }}
                    </p>

                    <ul class="space-y-1">
                        <li v-for="item in group.items" :key="item.routeName">
                            <Link
                                :href="route(item.routeName)"
                                :title="sidebarCollapsed ? item.label : undefined"
                                @click="sidebarOpen = false"
                                class="group relative flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors duration-150"
                                :class="[
                                    sidebarCollapsed ? 'lg:justify-center lg:px-0' : '',
                                    isCurrent(item.activePattern)
                                        ? 'bg-kopdes-700 text-white shadow-sm ring-1 ring-kopdes-500/50'
                                        : 'text-slate-300 hover:bg-slate-800/80 hover:text-white',
                                ]"
                                :aria-current="isCurrent(item.activePattern) ? 'page' : undefined"
                            >
                                <!-- Consistent 24x24 Heroicons Outline SVG -->
                                <svg
                                    v-if="item.icon === 'dashboard'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10zm10 8h8V11h-8v10zM3 21h8v-6H3v6zm10-18v6h8V3h-8z" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'sales'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'expenses'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'shifts'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'returns'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'shu'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'stock'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'movements'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'branches'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'members'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'products'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'categories'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'settings'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg
                                    v-else-if="item.icon === 'audit'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0 transition-colors"
                                    :class="isCurrent(item.activePattern) ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                </svg>

                                <span
                                    class="truncate"
                                    :class="sidebarCollapsed ? 'lg:hidden' : ''"
                                >
                                    {{ item.label }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Sidebar Footer: Scope & Collapse Control -->
            <div v-if="user" class="shrink-0 border-t border-slate-800/90 bg-slate-950/60 p-3">
                <div
                    class="rounded-xl border border-slate-800 bg-slate-900/90 p-2.5"
                    :class="sidebarCollapsed ? 'lg:flex lg:justify-center lg:p-2' : ''"
                    :title="`${roleLabel} — ${scopeLabel}`"
                >
                    <div
                        class="flex items-center gap-2"
                        :class="sidebarCollapsed ? 'lg:justify-center' : ''"
                    >
                        <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-400"></span>
                        <span
                            class="truncate text-[11px] font-bold uppercase tracking-wider text-kopdes-300"
                            :class="sidebarCollapsed ? 'lg:hidden' : ''"
                        >
                            {{ roleLabel }}
                        </span>
                    </div>
                    <p
                        class="mt-1 truncate text-xs font-medium text-slate-300"
                        :class="sidebarCollapsed ? 'lg:hidden' : ''"
                    >
                        {{ scopeLabel || 'Cakupan Aktif' }}
                    </p>
                </div>
            </div>
        </aside>

        <!-- Main Workspace Wrapper -->
        <div
            class="flex min-h-screen min-w-0 flex-col overflow-x-hidden transition-[padding] duration-200 ease-out"
            :class="sidebarCollapsed ? 'lg:pl-[76px]' : 'lg:pl-64'"
        >
            <!-- Clean Sticky Topbar -->
            <div class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200/90 bg-white/95 px-4 backdrop-blur-md sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-2.5">
                    <!-- Mobile Drawer Button -->
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white p-2 text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-kopdes-500 lg:hidden"
                        aria-label="Buka menu navigasi"
                        @click="sidebarOpen = true"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Desktop Collapse Sidebar Button -->
                    <button
                        type="button"
                        class="hidden cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white p-2 text-slate-500 transition-colors hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-kopdes-500 lg:inline-flex"
                        :aria-label="sidebarCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'"
                        :title="sidebarCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'"
                        @click="toggleSidebarCollapse"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 transition-transform duration-200"
                            :class="sidebarCollapsed ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                        </svg>
                    </button>

                    <!-- Role & Active Region Context -->
                    <div class="hidden min-w-0 items-center gap-2.5 sm:flex">
                        <StatusBadge variant="kopdes" size="sm">
                            {{ roleLabel }}
                        </StatusBadge>
                        <div
                            v-if="scopeLabel"
                            class="flex min-w-0 items-center gap-1.5 text-xs font-medium text-slate-600"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="truncate">{{ scopeLabel }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right Controls: Subtle Demo Mode Pill, POS Shortcut & Profile Dropdown -->
                <div class="flex shrink-0 items-center gap-2.5">
                    <StatusBadge
                        v-if="isDemoMode"
                        variant="amber"
                        size="sm"
                        dot
                        pulse
                        title="Mode Demo Portofolio aktif — akun bawaan dilindungi"
                    >
                        Mode Demo
                    </StatusBadge>

                    <a
                        v-if="hasRoute('pos.login')"
                        :href="route('pos.login')"
                        class="hidden cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700 md:inline-flex"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>Kasir POS</span>
                    </a>

                    <div class="relative">
                        <Dropdown align="right" width="56">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-kopdes-500 focus:ring-offset-1"
                                >
                                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-kopdes-600 to-kopdes-800 text-xs font-bold text-white shadow-sm">
                                        {{ userInitials }}
                                    </span>
                                    <span class="hidden max-w-[140px] truncate text-left text-xs font-semibold text-slate-800 sm:block">
                                        {{ user?.name }}
                                    </span>
                                    <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="border-b border-slate-100 px-4 py-2.5">
                                    <p class="truncate text-xs font-bold text-slate-900">
                                        {{ user?.name }}
                                    </p>
                                    <p class="truncate text-[11px] text-slate-500">
                                        {{ user?.email }}
                                    </p>
                                    <p v-if="scopeLabel" class="mt-1 truncate text-[10px] font-semibold text-kopdes-700">
                                        {{ roleLabel }} &bull; {{ scopeLabel }}
                                    </p>
                                </div>
                                <DropdownLink :href="route('profile.edit')">
                                    Profil Akun
                                </DropdownLink>
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    Keluar (Logout)
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </div>

            <!-- Page Header Slot -->
            <header
                v-if="$slots.header"
                class="border-b border-slate-200/80 bg-white"
            >
                <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Flash / Toast Notifications -->
            <ToastNotification :flash="flash" />

            <!-- Main Page Content -->
            <main class="flex-1">
                <slot />
            </main>

            <!-- Compact Footer -->
            <footer class="border-t border-slate-200/80 bg-white/80 py-3.5 text-center text-xs text-slate-500">
                Sistem Kasir &amp; Manajemen Keuangan
                <strong class="font-semibold text-slate-700">{{ institutionName }}</strong>
            </footer>
        </div>
    </div>
</template>
