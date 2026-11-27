<script setup>
import { computed, ref } from 'vue';
import AppButton from '@/Components/AppButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const isDemoMode = computed(() => Boolean(page.props.app?.demo_mode));
const showPassword = ref(false);
const selectedDemoEmail = ref('');

const demoAdminAccounts = [
    {
        label: 'Admin Nasional',
        tier: 'Nasional',
        sub: 'Seluruh Indonesia',
        email: 'admin.nasional@kopdes.id',
        password: 'password',
    },
    {
        label: 'Admin Prov. Jatim',
        tier: 'Provinsi',
        sub: 'Scope Jawa Timur',
        email: 'admin.jatim@kopdes.id',
        password: 'password',
    },
    {
        label: 'Admin Kab. Ponorogo',
        tier: 'Kabupaten',
        sub: 'Scope Kab. Ponorogo',
        email: 'admin.ponorogo@kopdes.id',
        password: 'password',
    },
    {
        label: 'Admin Cabang Ngunut',
        tier: 'Cabang Desa',
        sub: 'KOPDES Desa Ngunut',
        email: 'admin.ngunut@kopdes.id',
        password: 'password',
    },
];

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const fillDemoAccount = (account) => {
    form.clearErrors();
    form.email = account.email;
    form.password = account.password;
    selectedDemoEmail.value = account.email;
};

const hasPosLoginRoute = () => {
    try {
        return route().has('pos.login');
    } catch {
        return false;
    }
};

const hasMemberLoginRoute = () => {
    try {
        return route().has('member.login');
    } catch {
        return false;
    }
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Login Admin — KOPDES Merah Putih" />

        <!-- Tri-Mode Auth Switcher -->
        <nav
            aria-label="Pilih Portal Masuk"
            class="mb-6 grid grid-cols-3 gap-1 rounded-xl border border-slate-200/90 bg-slate-100/90 p-1"
        >
            <span
                class="flex items-center justify-center gap-1.5 rounded-lg bg-white px-2.5 py-2 text-xs font-bold text-kopdes-700 shadow-sm ring-1 ring-slate-200/80"
                aria-current="page"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Admin</span>
            </span>

            <Link
                :href="hasPosLoginRoute() ? route('pos.login') : '/pos/login'"
                class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-white/70 hover:text-slate-900"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Kasir POS</span>
            </Link>

            <Link
                :href="hasMemberLoginRoute() ? route('member.login') : '/member/login'"
                class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-white/70 hover:text-slate-900"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" />
                </svg>
                <span>Member</span>
            </Link>
        </nav>

        <!-- Form Heading -->
        <div class="mb-6">
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900">
                Masuk Portal Admin
            </h1>
            <p class="mt-1 text-xs leading-relaxed text-slate-600">
                Akses manajemen keuangan, inventori, dan laporan berjenjang KOPDES dari tingkat Desa hingga Nasional.
            </p>
        </div>

        <!-- Status Notification -->
        <div
            v-if="status"
            role="status"
            class="mb-5 flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs font-semibold text-emerald-800"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ status }}</span>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Email Input -->
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700">
                    Alamat Email Admin
                </label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </span>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="admin.nasional@kopdes.id"
                        class="kopdes-input pl-10"
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <!-- Password Input -->
            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-xs font-semibold text-slate-700">
                        Kata Sandi
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="cursor-pointer text-xs font-semibold text-kopdes-700 transition-colors hover:text-kopdes-900 hover:underline focus:outline-none focus:ring-2 focus:ring-kopdes-500 focus:ring-offset-1"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>

                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </span>
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        placeholder="Masukkan kata sandi"
                        class="kopdes-input pl-10 pr-10"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex cursor-pointer items-center pr-3.5 text-slate-400 transition-colors hover:text-slate-700 focus:outline-none"
                        :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Lihat kata sandi'"
                        @click="showPassword = !showPassword"
                    >
                        <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <!-- Remember Session -->
            <div class="pt-0.5">
                <label class="inline-flex cursor-pointer items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-xs font-medium text-slate-600">
                        Ingat sesi login di perangkat ini
                    </span>
                </label>
            </div>

            <!-- Primary CTA -->
            <div class="pt-1.5">
                <AppButton
                    type="submit"
                    variant="primary"
                    size="lg"
                    block
                    :loading="form.processing"
                >
                    <span>{{ form.processing ? 'Memverifikasi...' : 'Masuk Dashboard Keuangan' }}</span>
                </AppButton>
            </div>
        </form>

        <!-- Interactive Quick-Fill Demo Cards (Hanya dirender saat APP_DEMO_MODE=true) -->
        <div
            v-if="isDemoMode"
            class="mt-6 rounded-2xl border border-amber-200/90 bg-gradient-to-b from-amber-50/90 to-amber-50/40 p-4"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-100 text-amber-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                    <span class="text-xs font-extrabold tracking-tight text-amber-950">
                        Quick-Fill Akun Demo
                    </span>
                </div>
                <StatusBadge variant="amber" size="xs">1-Klik Isi</StatusBadge>
            </div>

            <p class="mt-1.5 text-[11px] leading-relaxed text-amber-900/80">
                Pilih peran berjenjang di bawah untuk mengisi kredensial demo secara otomatis, lalu klik tombol Masuk.
            </p>

            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button
                    v-for="acc in demoAdminAccounts"
                    :key="acc.email"
                    type="button"
                    @click="fillDemoAccount(acc)"
                    class="group relative flex cursor-pointer flex-col rounded-xl border p-2.5 text-left transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-kopdes-500/40"
                    :class="
                        selectedDemoEmail === acc.email && form.email === acc.email
                            ? 'border-kopdes-500 bg-kopdes-50/70 ring-1 ring-kopdes-200'
                            : 'border-amber-200/90 bg-white hover:border-kopdes-300 hover:bg-kopdes-50/30'
                    "
                >
                    <div class="flex items-center justify-between gap-1.5">
                        <span class="truncate text-xs font-bold text-slate-900">
                            {{ acc.label }}
                        </span>
                        <span
                            v-if="selectedDemoEmail === acc.email && form.email === acc.email"
                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white"
                            aria-label="Dipilih"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        <span
                            v-else
                            class="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-slate-600"
                        >
                            {{ acc.tier }}
                        </span>
                    </div>
                    <span class="mt-0.5 truncate text-[11px] text-slate-500">
                        {{ acc.sub }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Compact Security Note -->
        <div class="mt-5 flex items-start gap-2.5 rounded-xl border border-slate-200/80 bg-slate-50 px-3.5 py-2.5 text-[11px] leading-relaxed text-slate-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span>
                Pengurus login via email di halaman ini. Kasir wajib menggunakan tab
                <Link :href="hasPosLoginRoute() ? route('pos.login') : '/pos/login'" class="font-semibold text-kopdes-700 hover:underline">Kasir POS</Link>,
                dan anggota desa via <Link :href="hasMemberLoginRoute() ? route('member.login') : '/member/login'" class="font-semibold text-kopdes-700 hover:underline">Portal Member</Link>.
            </span>
        </div>
    </GuestLayout>
</template>
