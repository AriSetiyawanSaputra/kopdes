<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppButton from '@/Components/AppButton.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { normalizeCardUid } from '@/Utils/normalizeCardUid';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const page = usePage();
const isDemoMode = computed(() => Boolean(page.props.app?.demo_mode));

const demoCashierAccounts = [
    { label: 'Kasir KOPDES Ngunut', sub: 'Cabang Desa Ngunut', card_uid: '0008493873', pin: '123456' },
    { label: 'Kasir KOPDES Cekok', sub: 'Cabang Desa Cekok', card_uid: '0008305215', pin: '123456' },
];

const cardInputRef = ref(null);
const pinInputRef = ref(null);
const pinFocused = ref(false);
const showKeypad = ref(false);
const submitting = ref(false);
const selectedDemoUid = ref('');
const retryAfterSeconds = ref(0);
const lockoutTotalSeconds = ref(900);
const lockoutMessage = ref('');
let countdownTimer = null;

const form = useForm({
    card_uid: '',
    pin: '',
});

const normalizedUidPreview = computed(() => normalizeCardUid(form.card_uid));
const hasCardUid = computed(() => normalizedUidPreview.value.length > 0);
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

const fillDemoCashier = (account) => {
    form.clearErrors();
    form.card_uid = account.card_uid;
    form.pin = account.pin;
    selectedDemoUid.value = account.card_uid;
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

const focusCardInput = () => {
    nextTick(() => {
        cardInputRef.value?.focus();
    });
};

const focusPinInput = () => {
    if (isLockedOut.value || submitting.value) return;
    nextTick(() => {
        pinInputRef.value?.focus();
    });
};

const onPinInput = (event) => {
    const digits = String(event.target.value || '').replace(/\D/g, '').slice(0, 6);
    form.pin = digits;
};

const pressKeypadDigit = (digit) => {
    if (isLockedOut.value || submitting.value) return;
    const current = String(form.pin || '').replace(/\D/g, '');
    if (current.length < 6) {
        form.pin = current + String(digit);
    }
    focusPinInput();
};

const pressKeypadBackspace = () => {
    if (isLockedOut.value || submitting.value) return;
    form.pin = String(form.pin || '').slice(0, -1);
    focusPinInput();
};

const clearPin = () => {
    if (isLockedOut.value || submitting.value) return;
    form.pin = '';
    focusPinInput();
};

const onCardEnter = () => {
    form.card_uid = normalizeCardUid(form.card_uid);
    if (form.card_uid) {
        focusPinInput();
    }
};

const submit = async () => {
    if (isLockedOut.value || submitting.value || form.processing) {
        return;
    }

    form.card_uid = normalizeCardUid(form.card_uid);
    form.clearErrors();
    submitting.value = true;

    try {
        const response = await window.fetch(route('pos.login.store'), {
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
                card_uid: form.card_uid,
                pin: form.pin,
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
            if (errors.card_uid?.[0]) {
                form.setError('card_uid', errors.card_uid[0]);
            }
            if (errors.pin?.[0]) {
                form.setError('pin', errors.pin[0]);
            }
            form.reset('pin');
            return;
        }

        if (response.ok || response.redirected) {
            router.visit(route('pos.index'));
            return;
        }
    } catch {
        form.post(route('pos.login.store'), {
            onFinish: () => form.reset('pin'),
        });
    } finally {
        submitting.value = false;
    }
};

onMounted(() => {
    focusCardInput();
});

onBeforeUnmount(() => {
    if (countdownTimer) {
        clearInterval(countdownTimer);
    }
});
</script>

<template>
    <GuestLayout max-width="lg">
        <Head title="Login Kasir POS — RFID & PIN" />

        <!-- Tri-Mode Auth Switcher -->
        <nav
            aria-label="Pilih Portal Masuk"
            class="mb-5 grid grid-cols-3 gap-1 rounded-xl border border-slate-200/90 bg-slate-100/90 p-1"
        >
            <Link
                :href="route('login')"
                class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-white/70 hover:text-slate-900"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Admin</span>
            </Link>

            <span
                class="flex items-center justify-center gap-1.5 rounded-lg bg-white px-2.5 py-2 text-xs font-bold text-kopdes-700 shadow-sm ring-1 ring-slate-200/80"
                aria-current="page"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Kasir POS</span>
            </span>

            <Link
                :href="route().has('member.login') ? route('member.login') : '/member/login'"
                class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-white/70 hover:text-slate-900"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" />
                </svg>
                <span>Member</span>
            </Link>
        </nav>

        <!-- Terminal Header -->
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900">
                    Terminal Kasir POS
                </h1>
                <p class="mt-1 text-xs leading-relaxed text-slate-600">
                    Tempelkan Kartu RFID Kasir pada USB Reader lalu masukkan PIN 6 digit.
                </p>
            </div>
            <StatusBadge
                :variant="isLockedOut ? 'rose' : hasCardUid ? 'emerald' : 'kopdes'"
                size="xs"
                dot
                :pulse="!isLockedOut && !hasCardUid"
            >
                {{ isLockedOut ? 'Terkunci' : hasCardUid ? 'Kartu Siap' : 'Siap Tap' }}
            </StatusBadge>
        </div>

        <!-- Status Flash -->
        <div
            v-if="status"
            role="status"
            class="mb-4 flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs font-semibold text-emerald-800"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ status }}</span>
        </div>

        <!-- Informative HTTP 429 Lockout Countdown Banner -->
        <div
            v-if="isLockedOut"
            role="alert"
            class="mb-5 overflow-hidden rounded-2xl border border-rose-200 bg-rose-50/90 p-4"
        >
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-rose-900">
                            Akses Kartu Diblokir Sementara
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
            </div>
        </div>

        <!-- RFID Tap Status Visual Card -->
        <button
            type="button"
            @click="focusCardInput"
            :disabled="isLockedOut"
            class="mb-4 flex w-full cursor-pointer items-center gap-4 rounded-2xl border p-3.5 text-left transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-kopdes-500/30 disabled:cursor-not-allowed disabled:opacity-60"
            :class="
                hasCardUid
                    ? 'border-emerald-200 bg-emerald-50/60 hover:bg-emerald-50'
                    : 'border-kopdes-200/80 bg-gradient-to-r from-kopdes-50/70 to-white hover:border-kopdes-300'
            "
        >
            <div class="relative flex h-12 w-12 shrink-0 items-center justify-center">
                <span
                    v-if="!hasCardUid && !isLockedOut"
                    class="animate-rfid-ping absolute inline-flex h-full w-full rounded-2xl bg-kopdes-400/40"
                    aria-hidden="true"
                ></span>
                <div
                    class="relative flex h-11 w-11 items-center justify-center rounded-xl shadow-sm"
                    :class="hasCardUid ? 'bg-emerald-600 text-white' : 'bg-kopdes-700 text-white'"
                >
                    <svg v-if="hasCardUid" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                    </svg>
                </div>
            </div>

            <div class="min-w-0 flex-1">
                <div class="text-xs font-extrabold" :class="hasCardUid ? 'text-emerald-900' : 'text-slate-900'">
                    {{ hasCardUid ? 'Kartu RFID Kasir Terbaca' : 'Siap Menerima Tap Kartu RFID' }}
                </div>
                <div class="mt-0.5 text-[11px]" :class="hasCardUid ? 'text-emerald-700' : 'text-slate-600'">
                    <template v-if="hasCardUid">
                        UID: <span class="kopdes-num font-bold">{{ normalizedUidPreview }}</span> &bull; Lanjut isi PIN
                    </template>
                    <template v-else>
                        Tempelkan kartu pada USB RFID Reader atau ketik UID di bawah.
                    </template>
                </div>
            </div>
        </button>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Step 1: RFID Card UID Input -->
            <div>
                <div class="flex items-center justify-between">
                    <label for="card_uid" class="block text-xs font-semibold text-slate-700">
                        1. UID Kartu RFID Kasir
                    </label>
                    <span class="kopdes-num text-[11px] font-semibold text-slate-500">
                        {{ normalizedUidPreview ? `UID: ${normalizedUidPreview}` : 'Menunggu scan...' }}
                    </span>
                </div>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </span>
                    <input
                        id="card_uid"
                        ref="cardInputRef"
                        v-model="form.card_uid"
                        type="text"
                        autocomplete="off"
                        :disabled="isLockedOut || submitting"
                        @keydown.enter.prevent="onCardEnter"
                        class="kopdes-input kopdes-num pl-10 text-sm font-bold uppercase tracking-widest disabled:bg-slate-100"
                        placeholder="Tempelkan kartu kasir..."
                        required
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.card_uid" />
            </div>

            <!-- Step 2: 6-Digit PIN Masked Boxes + Optional Touch Keypad -->
            <div>
                <div class="flex items-center justify-between">
                    <label for="pin" class="block text-xs font-semibold text-slate-700">
                        2. PIN Kasir (6 Digit Angka)
                    </label>
                    <button
                        type="button"
                        @click="showKeypad = !showKeypad"
                        class="cursor-pointer text-[11px] font-semibold text-indigo-700 transition-colors hover:text-indigo-900 hover:underline focus:outline-none"
                    >
                        {{ showKeypad ? 'Sembunyikan Keypad' : 'Tampilkan Keypad Sentuh' }}
                    </button>
                </div>

                <!-- Interactive 6 Masked PIN Boxes synced with real <input> -->
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
                        aria-label="PIN Kasir 6 Digit Angka"
                    />
                    <div class="grid grid-cols-6 gap-2">
                        <div
                            v-for="(filled, idx) in pinSlots"
                            :key="idx"
                            class="flex h-12 items-center justify-center rounded-xl border text-lg font-bold transition-all duration-150"
                            :class="[
                                isLockedOut
                                    ? 'border-slate-200 bg-slate-100 text-slate-400'
                                    : pinFocused && idx === String(form.pin || '').length
                                      ? 'border-kopdes-600 bg-kopdes-50/40 ring-2 ring-kopdes-500/25'
                                      : filled
                                        ? 'border-slate-300 bg-white text-slate-900 shadow-sm'
                                        : 'border-slate-200 bg-slate-50/80 text-slate-300',
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

                <!-- Compact Touch Keypad for POS Terminals -->
                <div
                    v-if="showKeypad"
                    class="mt-3 rounded-2xl border border-slate-200 bg-slate-50/90 p-2.5"
                >
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="digit in [1, 2, 3, 4, 5, 6, 7, 8, 9]"
                            :key="digit"
                            type="button"
                            :disabled="isLockedOut || submitting"
                            @click="pressKeypadDigit(digit)"
                            class="kopdes-num flex h-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white text-sm font-bold text-slate-800 shadow-sm transition-colors hover:border-kopdes-300 hover:bg-kopdes-50/50 active:bg-kopdes-100 disabled:opacity-50"
                        >
                            {{ digit }}
                        </button>
                        <button
                            type="button"
                            :disabled="isLockedOut || submitting"
                            @click="clearPin"
                            class="flex h-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-200 disabled:opacity-50"
                        >
                            Reset
                        </button>
                        <button
                            type="button"
                            :disabled="isLockedOut || submitting"
                            @click="pressKeypadDigit(0)"
                            class="kopdes-num flex h-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white text-sm font-bold text-slate-800 shadow-sm transition-colors hover:border-kopdes-300 hover:bg-kopdes-50/50 active:bg-kopdes-100 disabled:opacity-50"
                        >
                            0
                        </button>
                        <button
                            type="button"
                            :disabled="isLockedOut || submitting"
                            @click="pressKeypadBackspace"
                            aria-label="Hapus 1 digit PIN"
                            class="flex h-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-700 transition-colors hover:bg-slate-200 disabled:opacity-50"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Submit CTA -->
            <div class="pt-1.5">
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
                                  ? 'Memverifikasi Kartu & PIN...'
                                  : 'Buka Layar Kasir POS'
                        }}
                    </span>
                </AppButton>
            </div>
        </form>

        <!-- Quick-Fill Demo Kasir (Hanya saat APP_DEMO_MODE=true) -->
        <div
            v-if="isDemoMode"
            class="mt-5 rounded-2xl border border-amber-200/90 bg-gradient-to-b from-amber-50/90 to-amber-50/40 p-4"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-100 text-amber-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </span>
                    <span class="text-xs font-extrabold tracking-tight text-amber-950">
                        Quick-Fill Demo Kasir
                    </span>
                </div>
                <StatusBadge variant="amber" size="xs">Simulasi RFID</StatusBadge>
            </div>

            <p class="mt-1.5 text-[11px] leading-relaxed text-amber-900/80">
                Isi UID kartu &amp; PIN kasir demo tanpa pembaca fisik, lalu klik Buka Layar Kasir POS.
            </p>

            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button
                    v-for="acc in demoCashierAccounts"
                    :key="acc.card_uid"
                    type="button"
                    @click="fillDemoCashier(acc)"
                    class="flex cursor-pointer flex-col rounded-xl border p-2.5 text-left transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-kopdes-500/40"
                    :class="
                        selectedDemoUid === acc.card_uid && normalizedUidPreview === acc.card_uid
                            ? 'border-kopdes-500 bg-kopdes-50/70 ring-1 ring-kopdes-200'
                            : 'border-amber-200/90 bg-white hover:border-kopdes-300 hover:bg-kopdes-50/30'
                    "
                >
                    <div class="flex items-center justify-between gap-1.5">
                        <span class="truncate text-xs font-bold text-slate-900">{{ acc.label }}</span>
                        <span
                            v-if="selectedDemoUid === acc.card_uid && normalizedUidPreview === acc.card_uid"
                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                    </div>
                    <span class="mt-0.5 text-[11px] text-slate-500">{{ acc.sub }}</span>
                    <span class="kopdes-num mt-1 text-[10px] font-semibold text-slate-600">
                        UID {{ acc.card_uid }} &bull; PIN {{ acc.pin }}
                    </span>
                </button>
            </div>
        </div>
    </GuestLayout>
</template>
