<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { normalizeCardUid } from '@/Utils/normalizeCardUid';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const cardInputRef = ref(null);
const pinInputRef = ref(null);
const retryAfterSeconds = ref(0);
const lockoutMessage = ref('');
let countdownTimer = null;

const form = useForm({
    card_uid: '',
    pin: '',
});

const normalizedUidPreview = computed(() => normalizeCardUid(form.card_uid));
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

const focusCardInput = () => {
    nextTick(() => {
        cardInputRef.value?.focus();
    });
};

const onCardEnter = () => {
    form.card_uid = normalizeCardUid(form.card_uid);
    if (form.card_uid) {
        nextTick(() => {
            pinInputRef.value?.focus();
        });
    }
};

const submit = async () => {
    if (isLockedOut.value || form.processing) {
        return;
    }

    form.card_uid = normalizeCardUid(form.card_uid);
    form.clearErrors();

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
    <GuestLayout>
        <Head title="Login Kasir POS — RFID & PIN" />

        <!-- Dual-Mode Auth Switcher -->
        <div class="mb-6 grid grid-cols-2 gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1">
            <Link
                :href="route('login')"
                class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-white/60 hover:text-slate-900"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Admin Dashboard
            </Link>

            <span
                class="flex items-center justify-center gap-1.5 rounded-lg bg-white px-3 py-2 text-xs font-bold text-kopdes-700 shadow-sm ring-1 ring-slate-200/80"
                aria-current="page"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                Kasir POS (RFID)
            </span>
        </div>

        <!-- Heading -->
        <div class="mb-5">
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900">
                Terminal Kasir POS
            </h1>
            <p class="mt-1 text-xs leading-relaxed text-slate-600">
                Tempelkan Kartu RFID Kasir pada USB Reader lalu masukkan PIN 6 digit angka.
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
                Akses Kartu Diblokir Sementara (5x Salah PIN)
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
                <label for="card_uid" class="block text-xs font-semibold text-slate-700">
                    1. Tap Kartu RFID Kasir (USB Reader)
                </label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </span>
                    <input
                        id="card_uid"
                        ref="cardInputRef"
                        v-model="form.card_uid"
                        type="text"
                        autocomplete="off"
                        :disabled="isLockedOut || form.processing"
                        @keydown.enter.prevent="onCardEnter"
                        class="kopdes-input kopdes-num pl-9 text-base font-bold uppercase tracking-wider disabled:bg-slate-100"
                        placeholder="Tempelkan kartu kasir..."
                        required
                    />
                </div>
                <div class="mt-1.5 flex items-center justify-between text-xs text-slate-500">
                    <span>UID Ternormalisasi:</span>
                    <span class="kopdes-num font-bold text-slate-800">
                        {{ normalizedUidPreview || '-' }}
                    </span>
                </div>
                <InputError class="mt-1.5" :message="form.errors.card_uid" />
            </div>

            <div>
                <label for="pin" class="block text-xs font-semibold text-slate-700">
                    2. PIN Kasir (6 Digit Angka)
                </label>
                <input
                    id="pin"
                    ref="pinInputRef"
                    v-model="form.pin"
                    type="password"
                    inputmode="numeric"
                    maxlength="6"
                    pattern="\d{6}"
                    autocomplete="current-password"
                    :disabled="isLockedOut || form.processing"
                    class="kopdes-input kopdes-num mt-1.5 text-center text-xl font-bold tracking-[0.5em] disabled:bg-slate-100"
                    placeholder="••••••"
                    required
                />
                <InputError class="mt-1.5" :message="form.errors.pin" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full py-2.5 text-sm font-bold"
                    :disabled="form.processing || isLockedOut"
                >
                    {{
                        isLockedOut
                            ? `Terkunci (${retryAfterSeconds}d)`
                            : 'Buka Layar Kasir POS'
                    }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
