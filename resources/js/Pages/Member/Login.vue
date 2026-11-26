<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const page = usePage();
const isDemoMode = computed(() => Boolean(page.props.app?.demo_mode));

const demoMemberAccounts = [
    { label: 'Ari Setiyawan (Ngunut)', sub: 'Member Asal', identifier: 'MBR-KOP-3502-001-00001', pin: '123456' },
    { label: 'Cintya Bella (Cekok)', sub: 'Member Lintas Cabang', identifier: 'MBR-KOP-3502-002-00001', pin: '123456' },
];

const fillDemoMember = (account) => {
    form.clearErrors();
    form.identifier = account.identifier;
    form.pin = account.pin;
};

const retryAfterSeconds = ref(0);
const lockoutMessage = ref('');
const submitting = ref(false);
let countdownTimer = null;

const form = useForm({
    identifier: '',
    pin: '',
    remember: false,
});

const isLockedOut = computed(() => retryAfterSeconds.value > 0);

const startLockoutCountdown = (seconds, message) => {
    if (countdownTimer) {
        clearInterval(countdownTimer);
    }

    retryAfterSeconds.value = Number(seconds) || 900;
    lockoutMessage.value =
        message ||
        `Terlalu banyak percobaan PIN salah. Silakan tunggu ${retryAfterSeconds.value} detik.`;

    countdownTimer = setInterval(() => {
        if (retryAfterSeconds.value > 1) {
            retryAfterSeconds.value -= 1;
        } else {
            retryAfterSeconds.value = 0;
            lockoutMessage.value = '';
            clearInterval(countdownTimer);
            countdownTimer = null;
        }
    }, 1000);
};

const submit = async () => {
    if (isLockedOut.value || submitting.value) {
        return;
    }

    form.clearErrors();
    submitting.value = true;

    try {
        const response = await window.fetch(route('member.login.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                identifier: form.identifier.trim(),
                pin: form.pin,
                remember: form.remember,
            }),
        });

        if (response.status === 429) {
            const data = await response.json().catch(() => ({}));
            const retrySec =
                data.retry_after ||
                Number(response.headers.get('Retry-After')) ||
                900;
            startLockoutCountdown(retrySec, data.message);
            form.reset('pin');
            return;
        }

        if (response.status === 422) {
            const data = await response.json().catch(() => ({}));
            const errors = data.errors || {};
            if (errors.identifier?.[0]) {
                form.setError('identifier', errors.identifier[0]);
            }
            if (errors.pin?.[0]) {
                form.setError('pin', errors.pin[0]);
            }
            form.reset('pin');
            return;
        }

        if (response.ok || response.redirected) {
            router.visit(route('member.dashboard'));
            return;
        }
    } catch {
        form.post(route('member.login.store'), {
            onFinish: () => form.reset('pin'),
        });
    } finally {
        submitting.value = false;
    }
};

onBeforeUnmount(() => {
    if (countdownTimer) {
        clearInterval(countdownTimer);
    }
});
</script>

<template>
    <GuestLayout>
        <Head title="Login Portal Member — KOPDES Merah Putih" />

        <!-- Tri-Mode Auth Switcher -->
        <div class="mb-6 grid grid-cols-3 gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1">
            <Link
                :href="route('login')"
                class="flex cursor-pointer items-center justify-center gap-1 rounded-lg px-2 py-2 text-[11px] font-semibold text-slate-600 transition-colors hover:bg-white/60 hover:text-slate-900"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Admin</span>
            </Link>

            <Link
                :href="route('pos.login')"
                class="flex cursor-pointer items-center justify-center gap-1 rounded-lg px-2 py-2 text-[11px] font-semibold text-slate-600 transition-colors hover:bg-white/60 hover:text-slate-900"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Kasir POS</span>
            </Link>

            <span
                class="flex items-center justify-center gap-1 rounded-lg bg-white px-2 py-2 text-[11px] font-bold text-kopdes-700 shadow-sm ring-1 ring-slate-200/80"
                aria-current="page"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                </svg>
                <span>Member</span>
            </span>
        </div>

        <!-- Heading -->
        <div class="mb-5">
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900">
                Portal Mandiri Anggota
            </h1>
            <p class="mt-1 text-xs leading-relaxed text-slate-600">
                Cek Kartu Member Digital, riwayat belanja lintas KOPDES, total hemat harga anggota, dan estimasi SHU berjalan Anda.
            </p>
        </div>

        <div
            v-if="status"
            role="status"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs font-semibold text-emerald-800"
        >
            {{ status }}
        </div>

        <!-- Banner Blokir Sementara RateLimiter (HTTP 429) -->
        <div
            v-if="isLockedOut"
            role="alert"
            class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-center"
        >
            <div class="text-xs font-bold uppercase tracking-wider text-rose-800">
                Akses Portal Diblokir Sementara (5x Salah PIN)
            </div>
            <p class="mt-1 text-xs text-rose-700">
                {{ lockoutMessage }}
            </p>
            <div class="kopdes-num mt-2.5 inline-block rounded-md bg-kopdes-700 px-3 py-1 text-xs font-extrabold text-white">
                Tunggu {{ retryAfterSeconds }} detik
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="identifier" class="block text-xs font-semibold text-slate-700">
                    Kode Member atau Nomor HP
                </label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" />
                        </svg>
                    </span>
                    <input
                        id="identifier"
                        v-model="form.identifier"
                        type="text"
                        required
                        autofocus
                        autocomplete="username"
                        :disabled="isLockedOut || submitting"
                        placeholder="MBR-KOP-... atau 0812xxxx"
                        class="kopdes-input pl-9 disabled:bg-slate-100"
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.identifier" />
            </div>

            <div>
                <label for="pin" class="block text-xs font-semibold text-slate-700">
                    PIN Portal Member (6 Digit Angka)
                </label>
                <input
                    id="pin"
                    v-model="form.pin"
                    type="password"
                    inputmode="numeric"
                    maxlength="6"
                    pattern="\d{6}"
                    autocomplete="current-password"
                    :disabled="isLockedOut || submitting"
                    placeholder="••••••"
                    required
                    class="kopdes-input kopdes-num mt-1.5 text-center text-xl font-bold tracking-[0.5em] disabled:bg-slate-100"
                />
                <InputError class="mt-1.5" :message="form.errors.pin" />
            </div>

            <div class="pt-0.5">
                <label class="inline-flex cursor-pointer items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-xs font-medium text-slate-600">
                        Tetap masuk di perangkat ini
                    </span>
                </label>
            </div>

            <div class="pt-1">
                <PrimaryButton
                    class="w-full py-2.5 text-sm font-bold"
                    :disabled="submitting || isLockedOut"
                >
                    {{
                        isLockedOut
                            ? `Terkunci (${retryAfterSeconds}d)`
                            : submitting
                              ? 'Memverifikasi...'
                              : 'Masuk Portal Member'
                    }}
                </PrimaryButton>
            </div>
        </form>

        <!-- Panel Quick-Fill Mode Demo Portofolio (Hanya saat APP_DEMO_MODE=true) -->
        <div
            v-if="isDemoMode"
            class="mt-5 rounded-xl border border-amber-200 bg-amber-50/80 p-3.5"
        >
            <div class="flex items-center justify-between gap-2">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-amber-900">
                    Mode Demo Portofolio — Quick-Fill Member
                </span>
                <span class="rounded bg-amber-200/80 px-1.5 py-0.5 text-[10px] font-bold text-amber-900">
                    Portal Member
                </span>
            </div>
            <p class="mt-1 text-[11px] text-amber-800">
                Isi Kode Member &amp; PIN 6 digit demo untuk mencoba Kartu Digital, Riwayat Belanja, dan Estimasi SHU.
            </p>
            <div class="mt-2.5 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                <button
                    v-for="acc in demoMemberAccounts"
                    :key="acc.identifier"
                    type="button"
                    @click="fillDemoMember(acc)"
                    class="rounded-lg border border-amber-300 bg-white px-2.5 py-2 text-left transition hover:border-kopdes-400 hover:bg-kopdes-50"
                >
                    <div class="text-[11px] font-bold text-slate-900">{{ acc.label }}</div>
                    <div class="kopdes-num truncate text-[10px] text-slate-500">{{ acc.identifier }} · PIN {{ acc.pin }}</div>
                </button>
            </div>
        </div>

        <!-- Help Note -->
        <div class="mt-5 flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-[11px] text-slate-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>
                Belum punya PIN atau lupa PIN? Hubungi <strong>Admin KOPDES asal</strong> Anda untuk aktivasi atau reset PIN 6 digit.
            </span>
        </div>
    </GuestLayout>
</template>
