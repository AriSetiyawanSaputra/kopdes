<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import AppButton from '@/Components/AppButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const page = usePage();
const isDemoMode = computed(() => Boolean(page.props.app?.demo_mode));
const appSettings = computed(() => page.props.app_settings ?? {});
const institutionName = computed(
    () => appSettings.value?.institution_name || 'Koperasi Desa Merah Putih',
);

const demoMemberAccounts = [
    {
        label: 'Ari Setiyawan',
        branch: 'Desa Ngunut',
        tag: 'Member Asal',
        identifier: 'MBR-KOP-3502-001-00001',
        pin: '123456',
    },
    {
        label: 'Cintya Bella',
        branch: 'Desa Cekok',
        tag: 'Lintas Cabang',
        identifier: 'MBR-KOP-3502-002-00001',
        pin: '123456',
    },
];

const pinInputRef = ref(null);
const pinFocused = ref(false);
const selectedDemoCode = ref('');
const retryAfterSeconds = ref(0);
const lockoutTotalSeconds = ref(900);
const lockoutMessage = ref('');
const submitting = ref(false);
let countdownTimer = null;

const form = useForm({
    identifier: '',
    pin: '',
    remember: false,
});

const isLockedOut = computed(() => retryAfterSeconds.value > 0);
const pinSlots = computed(() =>
    Array.from({ length: 6 }, (_, i) => i < String(form.pin || '').length),
);

const formattedCountdown = computed(() => {
    const mins = Math.floor(retryAfterSeconds.value / 60);
    const secs = retryAfterSeconds.value % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

const lockoutProgressPercent = computed(() => {
    if (!lockoutTotalSeconds.value) return 0;
    return Math.min(100, Math.round((retryAfterSeconds.value / lockoutTotalSeconds.value) * 100));
});

const fillDemoMember = (account) => {
    form.clearErrors();
    form.identifier = account.identifier;
    form.pin = account.pin;
    selectedDemoCode.value = account.identifier;
};

const focusPinInput = () => {
    if (isLockedOut.value || submitting.value) return;
    nextTick(() => {
        pinInputRef.value?.focus();
    });
};

const onPinInput = (event) => {
    form.pin = String(event.target.value || '').replace(/\D/g, '').slice(0, 6);
};

const startLockoutCountdown = (seconds, message) => {
    if (countdownTimer) {
        clearInterval(countdownTimer);
    }

    const total = Number(seconds) || 900;
    lockoutTotalSeconds.value = total;
    retryAfterSeconds.value = total;
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
    <Head title="Login Portal Member — KOPDES Merah Putih" />

    <!-- Warm Mobile-First Village Member Shell -->
    <div class="relative flex min-h-screen flex-col justify-between bg-gradient-to-b from-kopdes-50/70 via-slate-50 to-slate-100/90 px-4 py-6 sm:px-6">
        <!-- Soft Top Accent Ribbon -->
        <div
            class="pointer-events-none fixed inset-x-0 top-0 h-1.5 bg-gradient-to-r from-kopdes-800 via-kopdes-600 to-kopdes-700"
            aria-hidden="true"
        ></div>

        <!-- Top Compact Brand Bar -->
        <header class="mx-auto flex w-full max-w-md items-center justify-between gap-3 pt-1">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl shadow-sm"
                    :class="appSettings.institution_logo_url ? 'border border-slate-200 bg-white p-1' : 'bg-gradient-to-br from-kopdes-600 to-kopdes-800'"
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
                <div class="leading-tight">
                    <span class="block text-sm font-extrabold tracking-tight text-slate-900">
                        {{ institutionName }}
                    </span>
                    <span class="block text-[11px] font-medium text-slate-500">
                        Portal Mandiri Anggota Desa
                    </span>
                </div>
            </div>

            <StatusBadge v-if="isDemoMode" variant="amber" size="xs" dot pulse>
                Mode Demo
            </StatusBadge>
        </header>

        <!-- Main Mobile-First Card -->
        <main class="mx-auto my-6 w-full max-w-md">
            <div class="kopdes-card overflow-hidden p-5 sm:p-7">
                <!-- Tri-Mode Auth Switcher -->
                <nav
                    aria-label="Pilih Portal Masuk"
                    class="mb-5 grid grid-cols-3 gap-1 rounded-xl border border-slate-200/90 bg-slate-100/90 p-1"
                >
                    <Link
                        :href="route('login')"
                        class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-white/70 hover:text-slate-900"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Admin</span>
                    </Link>

                    <Link
                        :href="route('pos.login')"
                        class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-white/70 hover:text-slate-900"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <span>Kasir POS</span>
                    </Link>

                    <span
                        class="flex items-center justify-center gap-1.5 rounded-lg bg-white px-2 py-2 text-xs font-bold text-kopdes-700 shadow-sm ring-1 ring-slate-200/80"
                        aria-current="page"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" />
                        </svg>
                        <span>Member</span>
                    </span>
                </nav>

                <!-- Warm Greeting & Benefit Strip -->
                <div class="mb-5 rounded-2xl border border-kopdes-100 bg-gradient-to-br from-kopdes-50/90 via-white to-slate-50 p-4">
                    <h1 class="text-lg font-extrabold tracking-tight text-slate-900">
                        Selamat Datang, Anggota KOPDES
                    </h1>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600">
                        Lihat Kartu Member Digital, riwayat belanja lintas desa, total hemat harga anggota, dan estimasi SHU Anda.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <StatusBadge variant="emerald" size="xs">Harga Member</StatusBadge>
                        <StatusBadge variant="indigo" size="xs">Belanja Lintas Desa</StatusBadge>
                        <StatusBadge variant="kopdes" size="xs">Estimasi SHU</StatusBadge>
                    </div>
                </div>

                <!-- Flash Status -->
                <div
                    v-if="status"
                    role="status"
                    class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs font-semibold text-emerald-800"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ status }}</span>
                </div>

                <!-- Friendly HTTP 429 Lockout Banner -->
                <div
                    v-if="isLockedOut"
                    role="alert"
                    class="mb-5 rounded-2xl border border-rose-200 bg-rose-50/90 p-4"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-extrabold text-rose-900">
                            Akses Diblokir Sementara (5x Salah PIN)
                        </span>
                        <span class="kopdes-num rounded-lg bg-rose-700 px-2.5 py-0.5 text-xs font-extrabold text-white">
                            {{ formattedCountdown }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs leading-relaxed text-rose-800">
                        {{ lockoutMessage }}
                    </p>
                    <div class="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-rose-200">
                        <div
                            class="h-full rounded-full bg-rose-600 transition-all duration-1000"
                            :style="{ width: `${lockoutProgressPercent}%` }"
                        ></div>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Identifier Input -->
                    <div>
                        <label for="identifier" class="block text-xs font-semibold text-slate-700">
                            Kode Member atau Nomor HP
                        </label>
                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
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
                                placeholder="Contoh: MBR-KOP-... atau 0812xxxx"
                                class="kopdes-input pl-10 disabled:bg-slate-100"
                            />
                        </div>
                        <InputError class="mt-1.5" :message="form.errors.identifier" />
                    </div>

                    <!-- 6-Box Masked PIN Input -->
                    <div>
                        <label for="pin" class="block text-xs font-semibold text-slate-700">
                            PIN Portal Member (6 Digit Angka)
                        </label>
                        <div class="relative mt-2" @click="focusPinInput">
                            <input
                                id="pin"
                                ref="pinInputRef"
                                :value="form.pin"
                                @input="onPinInput"
                                @focus="pinFocused = true"
                                @blur="pinFocused = false"
                                type="password"
                                inputmode="numeric"
                                maxlength="6"
                                pattern="\d{6}"
                                autocomplete="current-password"
                                :disabled="isLockedOut || submitting"
                                required
                                class="peer absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                aria-label="PIN Portal Member 6 Digit Angka"
                            />
                            <div class="grid grid-cols-6 gap-2">
                                <div
                                    v-for="(filled, idx) in pinSlots"
                                    :key="idx"
                                    class="flex h-12 items-center justify-center rounded-xl border transition-all duration-150"
                                    :class="[
                                        isLockedOut
                                            ? 'border-slate-200 bg-slate-100'
                                            : pinFocused && idx === String(form.pin || '').length
                                              ? 'border-kopdes-600 bg-kopdes-50/40 ring-2 ring-kopdes-500/25'
                                              : filled
                                                ? 'border-slate-300 bg-white shadow-sm'
                                                : 'border-slate-200 bg-slate-50/80',
                                    ]"
                                >
                                    <span
                                        v-if="filled"
                                        class="h-2.5 w-2.5 rounded-full bg-slate-900"
                                    ></span>
                                    <span
                                        v-else-if="pinFocused && idx === String(form.pin || '').length"
                                        class="h-5 w-0.5 animate-pulse rounded-full bg-kopdes-600"
                                    ></span>
                                    <span v-else class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                                </div>
                            </div>
                        </div>
                        <InputError class="mt-1.5" :message="form.errors.pin" />
                    </div>

                    <!-- Remember Device -->
                    <div class="pt-0.5">
                        <label class="inline-flex cursor-pointer items-center">
                            <Checkbox name="remember" v-model:checked="form.remember" />
                            <span class="ms-2 text-xs font-medium text-slate-600">
                                Tetap masuk di HP / perangkat ini
                            </span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-1">
                        <AppButton
                            type="submit"
                            variant="primary"
                            size="lg"
                            block
                            :loading="submitting"
                            :disabled="isLockedOut"
                        >
                            <span>
                                {{
                                    isLockedOut
                                        ? `Terkunci (${formattedCountdown})`
                                        : submitting
                                          ? 'Memverifikasi...'
                                          : 'Masuk Portal Member'
                                }}
                            </span>
                        </AppButton>
                    </div>
                </form>

                <!-- Quick-Fill Demo Member Cards (Hanya saat APP_DEMO_MODE=true) -->
                <div
                    v-if="isDemoMode"
                    class="mt-5 rounded-2xl border border-amber-200/90 bg-gradient-to-b from-amber-50/90 to-amber-50/40 p-4"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-100 text-amber-800">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </span>
                            <span class="text-xs font-extrabold tracking-tight text-amber-950">
                                Quick-Fill Demo Member
                            </span>
                        </div>
                        <StatusBadge variant="amber" size="xs">1-Klik Isi</StatusBadge>
                    </div>

                    <p class="mt-1.5 text-[11px] leading-relaxed text-amber-900/80">
                        Pilih anggota demo untuk mencoba Kartu Digital, Riwayat Belanja, dan Estimasi SHU.
                    </p>

                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <button
                            v-for="acc in demoMemberAccounts"
                            :key="acc.identifier"
                            type="button"
                            @click="fillDemoMember(acc)"
                            class="flex cursor-pointer flex-col rounded-xl border p-2.5 text-left transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-kopdes-500/40"
                            :class="
                                selectedDemoCode === acc.identifier && form.identifier === acc.identifier
                                    ? 'border-kopdes-500 bg-kopdes-50/70 ring-1 ring-kopdes-200'
                                    : 'border-amber-200/90 bg-white hover:border-kopdes-300 hover:bg-kopdes-50/30'
                            "
                        >
                            <div class="flex items-center justify-between gap-1.5">
                                <span class="truncate text-xs font-bold text-slate-900">
                                    {{ acc.label }}
                                </span>
                                <span
                                    v-if="selectedDemoCode === acc.identifier && form.identifier === acc.identifier"
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <span
                                    v-else
                                    class="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-600"
                                >
                                    {{ acc.tag }}
                                </span>
                            </div>
                            <span class="mt-0.5 text-[11px] text-slate-500">{{ acc.branch }}</span>
                            <span class="kopdes-num mt-1 truncate text-[10px] font-semibold text-slate-600">
                                {{ acc.identifier }} &bull; PIN {{ acc.pin }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Warm Village Help Notice -->
                <div class="mt-5 flex items-start gap-2.5 rounded-xl border border-slate-200/80 bg-slate-50 px-3.5 py-3 text-[11px] leading-relaxed text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-kopdes-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        Belum memiliki PIN atau lupa PIN? Silakan hubungi pengurus <strong>Admin KOPDES desa asal</strong> Anda untuk aktivasi atau reset PIN 6 digit.
                    </span>
                </div>
            </div>
        </main>

        <!-- Minimal Footer -->
        <footer class="mx-auto w-full max-w-md pb-1 text-center text-xs text-slate-500">
            &copy; {{ new Date().getFullYear() }} {{ institutionName }} &bull; Layanan Anggota Desa
        </footer>
    </div>
</template>
