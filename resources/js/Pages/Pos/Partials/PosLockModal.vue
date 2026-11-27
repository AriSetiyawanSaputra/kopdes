<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppButton from '@/Components/AppButton.vue';
import Modal from '@/Components/Modal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { normalizeCardUid } from '@/Utils/normalizeCardUid';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    currentCashier: {
        type: Object,
        default: null,
    },
    branch: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['unlocked']);

const cardUid = ref('');
const pin = ref('');
const pinFocused = ref(false);
const errorMessage = ref('');
const isSubmitting = ref(false);
const retryAfterSeconds = ref(0);
const lockoutTotalSeconds = ref(900);
const cardInputRef = ref(null);
const pinInputRef = ref(null);
let countdownTimer = null;

const normalizedUidPreview = computed(() => normalizeCardUid(cardUid.value));
const isLockedOut = computed(() => retryAfterSeconds.value > 0);
const pinSlots = computed(() =>
    Array.from({ length: 6 }, (_, i) => i < String(pin.value || '').length),
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

const clearCountdown = () => {
    if (countdownTimer !== null) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
};

const startLockoutCountdown = (seconds, message) => {
    clearCountdown();
    const total = Number(seconds) || 900;
    lockoutTotalSeconds.value = total;
    retryAfterSeconds.value = total;
    errorMessage.value =
        message ||
        `Terlalu banyak percobaan PIN salah. Silakan tunggu ${retryAfterSeconds.value} detik.`;

    countdownTimer = setInterval(() => {
        if (retryAfterSeconds.value > 1) {
            retryAfterSeconds.value -= 1;
        } else {
            retryAfterSeconds.value = 0;
            errorMessage.value = '';
            clearCountdown();
        }
    }, 1000);
};

const focusCardInput = () => {
    nextTick(() => {
        if (cardInputRef.value) {
            cardInputRef.value.focus();
            cardInputRef.value.select();
        }
    });
};

const focusPinInput = () => {
    if (isSubmitting.value || isLockedOut.value) return;
    nextTick(() => {
        pinInputRef.value?.focus();
    });
};

const onPinInput = (event) => {
    pin.value = String(event.target.value || '').replace(/\D/g, '').slice(0, 6);
};

watch(
    () => props.show,
    (isOpen) => {
        errorMessage.value = '';
        pin.value = '';
        if (isOpen) {
            cardUid.value = props.currentCashier?.card_uid
                ? normalizeCardUid(props.currentCashier.card_uid)
                : '';
            focusCardInput();
        }
    },
    { immediate: true },
);

const onCardEnter = () => {
    cardUid.value = normalizeCardUid(cardUid.value);
    if (cardUid.value) {
        focusPinInput();
    }
};

const submitUnlock = async () => {
    if (isSubmitting.value || isLockedOut.value) {
        return;
    }

    const normalized = normalizeCardUid(cardUid.value);
    if (!normalized) {
        errorMessage.value = 'Silakan tempelkan kartu RFID kasir terlebih dahulu.';
        focusCardInput();
        return;
    }

    if (!/^\d{6}$/.test(pin.value)) {
        errorMessage.value = 'PIN kasir wajib terdiri dari tepat 6 digit angka.';
        focusPinInput();
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.post(route('pos.unlock'), {
            card_uid: normalized,
            pin: pin.value,
        });

        const data = response.data;
        pin.value = '';

        emit('unlocked', {
            switchedCashier: Boolean(data.switched_cashier),
            cashier: data.cashier ?? props.currentCashier,
            activeShift: data.active_shift ?? null,
            pendingTransactionsForShift: data.pending_transactions_for_shift ?? [],
            message: data.message,
        });
    } catch (error) {
        const status = error?.response?.status;
        const data = error?.response?.data || {};

        if (status === 429) {
            startLockoutCountdown(data.retry_after || 900, data.message);
        } else {
            errorMessage.value =
                data?.errors?.pin?.[0] ||
                data?.errors?.card_uid?.[0] ||
                data?.message ||
                'Kartu RFID kasir atau PIN 6 digit tidak valid.';
        }

        pin.value = '';
    } finally {
        isSubmitting.value = false;
    }
};

onBeforeUnmount(() => {
    clearCountdown();
});
</script>

<template>
    <Modal :show="show" max-width="md" :closeable="false">
        <div class="relative overflow-hidden p-6">
            <div
                class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-500 via-kopdes-700 to-amber-500"
                aria-hidden="true"
            ></div>

            <!-- Lock Header -->
            <div class="text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-kopdes-50 text-kopdes-700 ring-1 ring-kopdes-200/80">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>

                <div class="mt-3 flex justify-center">
                    <StatusBadge variant="amber" size="xs" dot>
                        Quick Lock F12 / Idle Auto-Lock
                    </StatusBadge>
                </div>

                <h3 class="mt-2 text-lg font-extrabold tracking-tight text-slate-900">
                    Layar POS Terkunci
                </h3>

                <!-- Last Cashier Info Card -->
                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-left">
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="text-slate-500">Kasir Terakhir:</span>
                        <strong class="truncate font-bold text-slate-900">{{ currentCashier?.name ?? '-' }}</strong>
                    </div>
                    <div class="mt-0.5 flex items-center justify-between gap-2 text-[11px] text-slate-500">
                        <span>Cabang:</span>
                        <span class="truncate font-semibold text-slate-700">{{ branch?.name ?? '-' }}</span>
                    </div>
                </div>
                <p class="mt-2 text-[11px] leading-relaxed text-slate-500">
                    Keranjang dipertahankan jika dibuka kasir yang sama, atau otomatis direset jika kasir berganti.
                </p>
            </div>

            <!-- Lockout Countdown Banner -->
            <div
                v-if="isLockedOut"
                role="alert"
                class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3.5"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-extrabold text-rose-900">Diblokir Sementara (5x Salah PIN)</span>
                    <span class="kopdes-num rounded-lg bg-rose-700 px-2 py-0.5 text-xs font-extrabold text-white">
                        {{ formattedCountdown }}
                    </span>
                </div>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-rose-200">
                    <div
                        class="h-full rounded-full bg-rose-600 transition-all duration-1000"
                        :style="{ width: `${lockoutProgressPercent}%` }"
                    ></div>
                </div>
            </div>

            <!-- Unlock Form -->
            <form @submit.prevent="submitUnlock" class="mt-5 space-y-4">
                <div>
                    <div class="flex items-center justify-between">
                        <label for="pos-unlock-card-uid" class="block text-xs font-semibold text-slate-700">
                            1. Tap Kartu RFID Kasir
                        </label>
                        <span class="kopdes-num text-[11px] font-semibold text-slate-500">
                            {{ normalizedUidPreview || '-' }}
                        </span>
                    </div>
                    <input
                        id="pos-unlock-card-uid"
                        ref="cardInputRef"
                        v-model="cardUid"
                        type="text"
                        autocomplete="off"
                        :disabled="isSubmitting || isLockedOut"
                        @keydown.enter.prevent="onCardEnter"
                        class="kopdes-input kopdes-num mt-1.5 text-sm font-bold uppercase tracking-widest disabled:bg-slate-100"
                        placeholder="Tempelkan kartu kasir..."
                        required
                    />
                </div>

                <div>
                    <label for="pos-unlock-pin" class="block text-xs font-semibold text-slate-700">
                        2. PIN Kasir (6 Digit Angka)
                    </label>
                    <div class="relative mt-2" @click="focusPinInput">
                        <input
                            id="pos-unlock-pin"
                            ref="pinInputRef"
                            :value="pin"
                            @input="onPinInput"
                            @focus="pinFocused = true"
                            @blur="pinFocused = false"
                            type="password"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="\d{6}"
                            autocomplete="off"
                            :disabled="isSubmitting || isLockedOut"
                            required
                            class="peer absolute inset-0 h-full w-full cursor-pointer opacity-0"
                            aria-label="PIN Kasir 6 Digit untuk Buka Kunci"
                        />
                        <div class="grid grid-cols-6 gap-2">
                            <div
                                v-for="(filled, idx) in pinSlots"
                                :key="idx"
                                class="flex h-11 items-center justify-center rounded-xl border transition-all duration-150"
                                :class="[
                                    isLockedOut
                                        ? 'border-slate-200 bg-slate-100'
                                        : pinFocused && idx === String(pin || '').length
                                          ? 'border-kopdes-600 bg-kopdes-50/40 ring-2 ring-kopdes-500/25'
                                          : filled
                                            ? 'border-slate-300 bg-white shadow-sm'
                                            : 'border-slate-200 bg-slate-50/80',
                                ]"
                            >
                                <span v-if="filled" class="h-2.5 w-2.5 rounded-full bg-slate-900"></span>
                                <span
                                    v-else-if="pinFocused && idx === String(pin || '').length"
                                    class="h-4 w-0.5 animate-pulse rounded-full bg-kopdes-600"
                                ></span>
                                <span v-else class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="errorMessage"
                    role="alert"
                    class="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-800"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ errorMessage }}</span>
                </div>

                <AppButton
                    type="submit"
                    variant="primary"
                    size="lg"
                    block
                    :loading="isSubmitting"
                    :disabled="isLockedOut"
                >
                    <span>
                        {{
                            isLockedOut
                                ? `Terkunci (${formattedCountdown})`
                                : isSubmitting
                                  ? 'Memverifikasi...'
                                  : 'Buka Kunci Layar POS'
                        }}
                    </span>
                </AppButton>
            </form>
        </div>
    </Modal>
</template>
