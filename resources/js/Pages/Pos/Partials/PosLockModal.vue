<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
const errorMessage = ref('');
const isSubmitting = ref(false);
const retryAfterSeconds = ref(0);
const cardInputRef = ref(null);
const pinInputRef = ref(null);
let countdownTimer = null;

const normalizedUidPreview = computed(() => normalizeCardUid(cardUid.value));
const isLockedOut = computed(() => retryAfterSeconds.value > 0);

const clearCountdown = () => {
    if (countdownTimer !== null) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
};

const startLockoutCountdown = (seconds, message) => {
    clearCountdown();
    retryAfterSeconds.value = Number(seconds) || 900;
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
);

const onCardEnter = () => {
    cardUid.value = normalizeCardUid(cardUid.value);
    if (cardUid.value) {
        nextTick(() => {
            pinInputRef.value?.focus();
        });
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
        <div class="p-6">
            <div class="text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-700">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-6 w-6"
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
                </div>

                <h3 class="mt-3 text-lg font-extrabold text-gray-900">
                    Layar POS Terkunci (Quick Lock F12 / Idle)
                </h3>
                <p class="mt-1 text-xs text-gray-500">
                    Kasir Aktif Sebelumnya:
                    <strong class="text-gray-800">{{ currentCashier?.name ?? '-' }}</strong>
                    ({{ branch?.name ?? '' }})
                </p>
                <p class="mt-0.5 text-[11px] text-gray-500">
                    Keranjang dipertahankan jika dibuka oleh kasir yang sama, atau direset jika kasir berbeda.
                </p>
            </div>

            <div
                v-if="isLockedOut"
                class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-center text-xs text-red-800"
            >
                <div class="font-bold">Diblokir Sementara (5x Salah PIN)</div>
                <div class="mt-1 font-mono font-extrabold">
                    Tunggu {{ retryAfterSeconds }} detik
                </div>
            </div>

            <form @submit.prevent="submitUnlock" class="mt-5 space-y-4">
                <div>
                    <label
                        for="pos-unlock-card-uid"
                        class="block text-xs font-semibold text-gray-700"
                    >
                        1. Tap Kartu RFID Kasir
                    </label>
                    <input
                        id="pos-unlock-card-uid"
                        ref="cardInputRef"
                        v-model="cardUid"
                        type="text"
                        autocomplete="off"
                        :disabled="isSubmitting || isLockedOut"
                        @keydown.enter.prevent="onCardEnter"
                        class="mt-1 block w-full rounded-md border-gray-300 font-mono text-base font-bold uppercase tracking-wider text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"
                        placeholder="Tempelkan kartu kasir..."
                        required
                    />
                    <div class="mt-1 text-[11px] text-gray-500">
                        UID Ternormalisasi:
                        <span class="font-mono font-bold text-gray-800">
                            {{ normalizedUidPreview || '-' }}
                        </span>
                    </div>
                </div>

                <div>
                    <label
                        for="pos-unlock-pin"
                        class="block text-xs font-semibold text-gray-700"
                    >
                        2. PIN Kasir (6 Digit Angka)
                    </label>
                    <input
                        id="pos-unlock-pin"
                        ref="pinInputRef"
                        v-model="pin"
                        type="password"
                        inputmode="numeric"
                        maxlength="6"
                        pattern="\d{6}"
                        autocomplete="off"
                        :disabled="isSubmitting || isLockedOut"
                        class="mt-1 block w-full rounded-md border-gray-300 text-center font-mono text-xl font-bold tracking-[0.5em] text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"
                        placeholder="••••••"
                        required
                    />
                </div>

                <div
                    v-if="errorMessage"
                    class="rounded-md border border-red-300 bg-red-50 p-2.5 text-xs font-semibold text-red-700"
                >
                    {{ errorMessage }}
                </div>

                <PrimaryButton
                    type="submit"
                    class="w-full justify-center bg-red-700 py-2.5 text-sm font-bold hover:bg-red-800"
                    :class="{ 'opacity-25': isSubmitting || isLockedOut }"
                    :disabled="isSubmitting || isLockedOut"
                >
                    {{ isSubmitting ? 'Memverifikasi...' : 'Buka Kunci Layar POS' }}
                </PrimaryButton>
            </form>
        </div>
    </Modal>
</template>
