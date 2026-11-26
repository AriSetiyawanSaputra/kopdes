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
const flash = computed(() => page.props.flash ?? {});

const isDashboard = computed(() => route().current('member.dashboard'));
const isTransactions = computed(() => route().current('member.transactions.*'));
const isSecurity = computed(() => route().current('member.security'));

const logout = () => {
    router.post(route('member.logout'));
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 pb-24 text-slate-900">
        <!-- Sticky Mobile-First Top Bar -->
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-xl items-center justify-between px-4 py-3">
                <Link
                    :href="route('member.dashboard')"
                    class="flex items-center gap-2.5 focus:outline-none"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-kopdes-700 text-white shadow-sm"
                    >
                        <img
                            v-if="appSettings.institution_logo_url"
                            :src="appSettings.institution_logo_url"
                            :alt="appSettings.institution_name"
                            class="h-full w-full object-contain bg-white p-0.5"
                        />
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                            />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-extrabold tracking-tight text-slate-900">
                            Portal Anggota
                        </div>
                        <div class="truncate text-[11px] font-medium text-slate-500">
                            {{ appSettings.institution_name }}
                        </div>
                    </div>
                </Link>

                <div class="flex items-center gap-2">
                    <div v-if="member" class="hidden text-right sm:block">
                        <div class="max-w-[140px] truncate text-xs font-bold text-slate-800">
                            {{ member.name }}
                        </div>
                        <div class="kopdes-num text-[10px] font-medium text-slate-500">
                            {{ member.member_code }}
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="logout"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                        title="Keluar dari Portal Member"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
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

        <!-- Main Content Container -->
        <main class="mx-auto max-w-xl px-4 pt-4">
            <!-- Flash Notifications -->
            <div
                v-if="flash.success"
                role="status"
                class="mb-4 flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-xs font-semibold text-emerald-800"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 shrink-0 text-emerald-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
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
                class="mb-4 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs font-semibold text-rose-800"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 shrink-0 text-rose-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
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

        <!-- Fixed Bottom Mobile-First Tab Bar -->
        <nav
            aria-label="Navigasi Portal Member"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 shadow-[0_-4px_16px_rgba(15,23,42,0.06)] backdrop-blur"
        >
            <div class="mx-auto grid max-w-xl grid-cols-3">
                <Link
                    :href="route('member.dashboard')"
                    class="flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-semibold transition"
                    :class="
                        isDashboard
                            ? 'text-kopdes-700'
                            : 'text-slate-500 hover:text-slate-800'
                    "
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
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
                    class="flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-semibold transition"
                    :class="
                        isTransactions
                            ? 'text-kopdes-700'
                            : 'text-slate-500 hover:text-slate-800'
                    "
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
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
                    class="flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-semibold transition"
                    :class="
                        isSecurity
                            ? 'text-kopdes-700'
                            : 'text-slate-500 hover:text-slate-800'
                    "
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
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
