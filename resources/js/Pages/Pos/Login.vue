<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
        // Fallback to standard Inertia form post
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

        <div class="mb-6 border-b border-gray-200 pb-4 text-center">
            <span
                class="inline-flex items-center rounded-full bg-red-100 px-3 py-0.5 text-xs font-bold text-red-800"
            >
                TERMINAL POS KASIR
            </span>
            <h1 class="mt-2 text-xl font-extrabold text-gray-900">
                KOPDES Merah Putih
            </h1>
            <p class="mt-1 text-xs text-gray-500">
                Tempelkan Kartu RFID Kasir pada USB Reader &amp; Masukkan PIN 6 Digit
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 rounded-md bg-green-50 p-3 text-sm font-medium text-green-700"
        >
            {{ status }}
        </div>

        <!-- Banner Blokir Sementara RateLimiter (HTTP 429) -->
        <div
            v-if="isLockedOut"
            class="mb-5 rounded-lg border border-red-300 bg-red-50 p-4 text-center"
        >
            <div class="text-sm font-bold text-red-800">
                Akses Kartu Kasir Diblokir Sementara (5x Salah PIN)
            </div>
            <p class="mt-1 text-xs text-red-700">
                {{ lockoutMessage }}
            </p>
            <div class="mt-2 inline-block rounded bg-red-700 px-3 py-1 font-mono text-sm font-extrabold text-white">
                Sisa Waktu Tunggu: {{ retryAfterSeconds }} detik
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel
                    for="card_uid"
                    value="1. Tap Kartu RFID Kasir (USB Reader)"
                />
                <input
                    id="card_uid"
                    ref="cardInputRef"
                    v-model="form.card_uid"
                    type="text"
                    autocomplete="off"
                    :disabled="isLockedOut || form.processing"
                    @keydown.enter.prevent="onCardEnter"
                    class="mt-1 block w-full rounded-md border-gray-300 font-mono text-base font-bold uppercase tracking-wider text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 disabled:bg-gray-100"
                    placeholder="Tempelkan kartu kasir..."
                    required
                />
                <div class="mt-1 flex items-center justify-between text-xs text-gray-500">
                    <span>UID Ternormalisasi:</span>
                    <span class="font-mono font-bold text-gray-800">
                        {{ normalizedUidPreview || '-' }}
                    </span>
                </div>
                <InputError class="mt-2" :message="form.errors.card_uid" />
            </div>

            <div>
                <InputLabel for="pin" value="2. PIN Kasir (6 Digit Angka)" />
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
                    class="mt-1 block w-full rounded-md border-gray-300 text-center font-mono text-xl font-bold tracking-[0.5em] text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 disabled:bg-gray-100"
                    placeholder="••••••"
                    required
                />
                <InputError class="mt-2" :message="form.errors.pin" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full justify-center bg-red-700 py-3 text-sm font-bold hover:bg-red-800"
                    :class="{ 'opacity-25': form.processing || isLockedOut }"
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

        <div class="mt-6 border-t border-gray-200 pt-4 text-center text-xs text-gray-500">
            Pengurus / Admin KOPDES?
            <Link
                :href="route('login')"
                class="font-semibold text-red-700 underline hover:text-red-800"
            >
                Login Dashboard Admin (/login)
            </Link>
        </div>
    </GuestLayout>
</template>
