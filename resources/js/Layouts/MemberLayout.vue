<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();

const member = computed(() => page.props.auth?.member ?? null);
const appSettings = computed(
    () =>
        page.props.app_settings ?? {
            institution_name: 'Koperasi Desa Merah Putih',
            institution_logo_url: null,
        },
);
const isDemoMode = computed(() => Boolean(page.props.app?.demo_mode));
const flash = computed(() => page.props.flash ?? {});

const isDashboard = computed(() => route().current('member.dashboard'));
const isTransactions = computed(() => route().current('member.transactions.*'));
const isSecurity = computed(() => route().current('member.security'));

const logout = () => {
    router.post(route('member.logout'));
};
</script>

<template>
    <div class="min-h-screen overflow-x-hidden bg-slate-50 pb-24 text-slate-900 lg:pb-12">
        <!-- Sticky Mobile-First & Desktop Top Bar -->
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur-sm">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <!-- Brand Identity -->
                <Link
                    :href="route('member.dashboard')"
                    class="flex min-w-0 items-center gap-2.5 focus:outline-none"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-kopdes-700 text-white shadow-sm ring-1 ring-inset ring-kopdes-800/20"
                    >
                        <img
                            v-if="appSettings.institution_logo_url"
                            :src="appSettings.institution_logo_url"
                            :alt="appSettings.institution_name"
                            class="h-full w-full bg-white object-contain p-0.5"
                        />
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                            />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="truncate text-sm font-extrabold tracking-tight text-slate-900">
                                Portal Anggota
                            </span>
                            <span
                                v-if="isDemoMode"
                                class="hidden rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-800 sm:inline-flex"
                            >
                                Demo
                            </span>
                        </div>
                        <div class="truncate text-[11px] font-medium text-slate-500">
                            {{ appSettings.institution_name }}
                        </div>
                    </div>
                </Link>

                <!-- Desktop Pill Navigation -->
                <nav
                    aria-label="Navigasi Utama Portal Member"
                    class="hidden items-center gap-1 rounded-xl border border-slate-200/80 bg-slate-100/80 p-1 lg:flex"
                >
                    <Link
                        :href="route('member.dashboard')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition-colors"
                        :class="
                            isDashboard
                                ? 'bg-white text-kopdes-700 shadow-sm ring-1 ring-slate-200/80'
                                : 'text-slate-600 hover:text-slate-900'
                        "
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Beranda</span>
                    </Link>

                    <Link
                        :href="route('member.transactions.index')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition-colors"
                        :class="
                            isTransactions
                                ? 'bg-white text-kopdes-700 shadow-sm ring-1 ring-slate-200/80'
                                : 'text-slate-600 hover:text-slate-900'
                        "
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span>Riwayat Belanja</span>
                    </Link>

                    <Link
                        :href="route('member.security')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition-colors"
                        :class="
                            isSecurity
                                ? 'bg-white text-kopdes-700 shadow-sm ring-1 ring-slate-200/80'
                                : 'text-slate-600 hover:text-slate-900'
                        "
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Ganti PIN</span>
                    </Link>
                </nav>

                <!-- Right Actions: Member Info + Ganti PIN (mobile/sm) + Logout -->
                <div class="flex shrink-0 items-center gap-2">
                    <div v-if="member" class="hidden text-right md:block">
                        <div class="max-w-[150px] truncate text-xs font-bold text-slate-900">
                            {{ member.name }}
                        </div>
                        <div class="kopdes-num text-[10px] font-medium text-slate-500">
                            {{ member.member_code }}
                        </div>
                    </div>

                    <Link
                        :href="route('member.security')"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700 lg:hidden"
                        title="Keamanan & Ganti PIN"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span class="hidden sm:inline">PIN</span>
                    </Link>

                    <button
                        type="button"
                        @click="logout"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                        title="Keluar dari Portal Member"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                            />
                        </svg>
                        <span>Keluar</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content Container (App-like centered on mobile, responsive 2-col ready on desktop) -->
        <main class="mx-auto w-full max-w-xl px-4 pt-5 sm:px-6 lg:max-w-5xl">
            <!-- Flash Notifications -->
            <div
                v-if="flash.success"
                role="status"
                class="mb-4 flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800 shadow-sm"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 shrink-0 text-emerald-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <span>{{ flash.success }}</span>
            </div>

            <div
                v-if="flash.error"
                role="alert"
                class="mb-4 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800 shadow-sm"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 shrink-0 text-rose-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <span>{{ flash.error }}</span>
            </div>

            <slot />
        </main>

        <!-- Fixed Bottom Mobile-First Tab Bar (Hidden on Desktop where top nav is visible) -->
        <nav
            aria-label="Navigasi Bawah Portal Member"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 shadow-[0_-4px_16px_rgba(15,23,42,0.06)] backdrop-blur-sm lg:hidden"
        >
            <div class="mx-auto grid max-w-xl grid-cols-3">
                <Link
                    :href="route('member.dashboard')"
                    class="relative flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-bold transition"
                    :class="
                        isDashboard
                            ? 'text-kopdes-700'
                            : 'text-slate-500 hover:text-slate-800'
                    "
                >
                    <span
                        v-if="isDashboard"
                        class="absolute inset-x-6 top-0 h-0.5 rounded-b-full bg-kopdes-700"
                        aria-hidden="true"
                    ></span>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                        />
                    </svg>
                    <span>Beranda</span>
                </Link>

                <Link
                    :href="route('member.transactions.index')"
                    class="relative flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-bold transition"
                    :class="
                        isTransactions
                            ? 'text-kopdes-700'
                            : 'text-slate-500 hover:text-slate-800'
                    "
                >
                    <span
                        v-if="isTransactions"
                        class="absolute inset-x-6 top-0 h-0.5 rounded-b-full bg-kopdes-700"
                        aria-hidden="true"
                    ></span>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                        />
                    </svg>
                    <span>Riwayat</span>
                </Link>

                <Link
                    :href="route('member.security')"
                    class="relative flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-bold transition"
                    :class="
                        isSecurity
                            ? 'text-kopdes-700'
                            : 'text-slate-500 hover:text-slate-800'
                    "
                >
                    <span
                        v-if="isSecurity"
                        class="absolute inset-x-6 top-0 h-0.5 rounded-b-full bg-kopdes-700"
                        aria-hidden="true"
                    ></span>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                        />
                    </svg>
                    <span>Keamanan</span>
                </Link>
            </div>
        </nav>
    </div>
</template>
