<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash ?? {});

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

const hasRoute = (name) => {
    try {
        return route().has(name);
    } catch {
        return false;
    }
};

const navItems = computed(() => {
    const role = user.value?.role;
    const items = [
        {
            label: 'Dashboard',
            routeName: 'dashboard',
            activePattern: 'dashboard',
            roles: ['admin_nasional', 'admin_provinsi', 'admin_kabupaten', 'admin_cabang'],
        },
        {
            label: 'Cabang KOPDES',
            routeName: 'branches.index',
            activePattern: 'branches.*',
            roles: ['admin_nasional', 'admin_provinsi', 'admin_kabupaten', 'admin_cabang'],
        },
        {
            label: 'Pengguna & Kasir',
            routeName: 'users.index',
            activePattern: 'users.*',
            roles: ['admin_nasional', 'admin_provinsi', 'admin_kabupaten', 'admin_cabang'],
        },
        {
            label: 'Anggota (Member)',
            routeName: 'members.index',
            activePattern: 'members.*',
            roles: ['admin_nasional', 'admin_provinsi', 'admin_kabupaten', 'admin_cabang'],
        },
        {
            label: 'Kategori',
            routeName: 'categories.index',
            activePattern: 'categories.*',
            roles: ['admin_nasional'],
        },
        {
            label: 'Katalog Produk',
            routeName: 'products.index',
            activePattern: 'products.*',
            roles: ['admin_nasional', 'admin_provinsi', 'admin_kabupaten', 'admin_cabang'],
        },
        {
            label: 'Stok Cabang',
            routeName: 'inventory.index',
            activePattern: 'inventory.*',
            roles: ['admin_nasional', 'admin_provinsi', 'admin_kabupaten', 'admin_cabang'],
        },
        {
            label: 'Laporan Penjualan',
            routeName: 'reports.sales',
            activePattern: 'reports.sales*',
            roles: ['admin_nasional', 'admin_provinsi', 'admin_kabupaten', 'admin_cabang'],
        },
        {
            label: 'Rekap Member',
            routeName: 'reports.members',
            activePattern: 'reports.members*',
            roles: ['admin_nasional', 'admin_provinsi', 'admin_kabupaten', 'admin_cabang'],
        },
    ];

    return items.filter(
        (item) => item.roles.includes(role) && hasRoute(item.routeName),
    );
});
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-red-800 bg-red-700 text-white shadow-sm">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex items-center">
                            <!-- Logo / Brand -->
                            <div class="flex shrink-0 items-center">
                                <Link
                                    :href="route('dashboard')"
                                    class="flex items-center gap-2 font-bold tracking-tight text-white"
                                >
                                    <ApplicationLogo
                                        class="block h-8 w-auto fill-current text-white"
                                    />
                                    <div class="leading-tight">
                                        <span class="block text-base font-extrabold">KOPDES</span>
                                        <span class="block text-[11px] font-medium text-red-100">
                                            Merah Putih
                                        </span>
                                    </div>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-4 sm:-my-px sm:ms-8 sm:flex"
                            >
                                <NavLink
                                    v-for="item in navItems"
                                    :key="item.routeName"
                                    :href="route(item.routeName)"
                                    :active="route().current(item.activePattern)"
                                    class="!text-white hover:!border-red-200 hover:!text-red-100"
                                >
                                    {{ item.label }}
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-3">
                            <!-- Role & Region Badge -->
                            <div
                                v-if="user"
                                class="rounded-md bg-red-800/80 px-3 py-1 text-right text-xs text-red-50"
                            >
                                <div class="font-semibold">{{ roleLabel }}</div>
                                <div v-if="scopeLabel" class="text-[11px] text-red-200">
                                    {{ scopeLabel }}
                                </div>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="relative ms-1">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-red-600 bg-red-800 px-3 py-2 text-sm font-medium leading-4 text-white transition duration-150 ease-in-out hover:bg-red-900 focus:outline-none"
                                            >
                                                {{ user?.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
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

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-red-100 transition duration-150 ease-in-out hover:bg-red-800 hover:text-white focus:bg-red-800 focus:text-white focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="border-t border-red-800 bg-red-800 sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            v-for="item in navItems"
                            :key="item.routeName"
                            :href="route(item.routeName)"
                            :active="route().current(item.activePattern)"
                        >
                            {{ item.label }}
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-red-700 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-white">
                                {{ user?.name }}
                            </div>
                            <div class="text-xs font-medium text-red-200">
                                {{ roleLabel }} — {{ scopeLabel }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profil Akun
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Keluar (Logout)
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                v-if="$slots.header"
                class="bg-white shadow"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Flash Notifications -->
            <div
                v-if="flash.success || flash.error || flash.warning || flash.info"
                class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8"
            >
                <div
                    v-if="flash.success"
                    class="rounded-md border border-green-300 bg-green-50 p-4 text-sm font-medium text-green-800"
                >
                    {{ flash.success }}
                </div>
                <div
                    v-if="flash.error"
                    class="rounded-md border border-red-300 bg-red-50 p-4 text-sm font-medium text-red-800"
                >
                    {{ flash.error }}
                </div>
                <div
                    v-if="flash.warning"
                    class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm font-medium text-amber-800"
                >
                    {{ flash.warning }}
                </div>
                <div
                    v-if="flash.info"
                    class="rounded-md border border-blue-300 bg-blue-50 p-4 text-sm font-medium text-blue-800"
                >
                    {{ flash.info }}
                </div>
            </div>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
