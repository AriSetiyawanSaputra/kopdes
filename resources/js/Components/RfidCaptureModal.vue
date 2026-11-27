<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { normalizeCardUid } from '@/Utils/normalizeCardUid';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Pindai Kartu RFID / NFC',
    },
    description: {
        type: String,
        default:
            'Tempelkan kartu pada USB RFID Reader. Input di bawah ini sudah terfokus otomatis.',
    },
    currentUid: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    errorMessage: {
        type: String,
        default: '',
    },
    autoSubmitOnScan: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close', 'captured']);

const page = usePage();
const isDemoMode = computed(() => Boolean(page.props.app?.demo_mode));

const rawInput = ref('');
const localError = ref('');
const isDebouncing = ref(false);
const rfidInputRef = ref(null);
let debounceTimer = null;

const normalizedPreview = computed(() => normalizeCardUid(rawInput.value));

const clearDebounceTimer = () => {
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }
    isDebouncing.value = false;
};

const focusInput = () => {
    nextTick(() => {
        if (rfidInputRef.value) {
            rfidInputRef.value.focus();
            rfidInputRef.value.select();
        }
    });
};

watch(
    () => props.show,
    (isOpen) => {
        clearDebounceTimer();
        localError.value = '';

        if (isOpen) {
            rawInput.value = props.currentUid ? normalizeCardUid(props.currentUid) : '';
            focusInput();
        } else {
            rawInput.value = '';
        }
    },
);

const finalizeCapture = () => {
    clearDebounceTimer();

    if (props.loading) {
        return;
    }

    const normalized = normalizeCardUid(rawInput.value);

    if (!normalized) {
        localError.value =
            'UID kartu tidak boleh kosong dan wajib berisi karakter alfanumerik.';
        focusInput();
        return;
    }

    if (normalized.length > 64) {
        localError.value = 'UID kartu maksimal 64 karakter alfanumerik.';
        focusInput();
        return;
    }

    localError.value = '';
    rawInput.value = normalized;
    emit('captured', normalized);
};

/**
 * Debounce 350ms when USB RFID Reader streams characters or sends Enter key,
 * preventing duplicate submissions due to card tap vibration.
 */
const scheduleDebouncedCapture = () => {
    if (props.loading) {
        return;
    }

    localError.value = '';
    clearDebounceTimer();

    const candidate = normalizeCardUid(rawInput.value);
    if (!candidate) {
        return;
    }

    isDebouncing.value = true;
    debounceTimer = setTimeout(() => {
        isDebouncing.value = false;
        finalizeCapture();
    }, 350);
};

const onEnterKey = () => {
    scheduleDebouncedCapture();
};

const onInputChange = () => {
    localError.value = '';

    if (props.autoSubmitOnScan && normalizeCardUid(rawInput.value).length >= 4) {
        clearDebounceTimer();
        isDebouncing.value = true;
        debounceTimer = setTimeout(() => {
            isDebouncing.value = false;
        }, 350);
    }
};

const simulateTapNewCard = () => {
    if (props.loading) {
        return;
    }

    const randomPart = Math.floor(100000 + Math.random() * 900000).toString();
    const timePart = Date.now().toString().slice(-4);
    rawInput.value = normalizeCardUid(`DEMO${timePart}${randomPart}`);
    scheduleDebouncedCapture();
};

const handleClose = () => {
    if (props.loading) {
        return;
    }

    clearDebounceTimer();
    emit('close');
};

onBeforeUnmount(() => {
    clearDebounceTimer();
});
</script>

<template>
    <Modal :show="show" max-width="md" @close="handleClose">
        <div class="p-6" @click="focusInput">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">
                        {{ title }}
                    </h3>
                    <p class="mt-1 text-xs text-slate-600">
                        {{ description }}
                    </p>
                </div>
                <StatusBadge variant="kopdes" size="xs" dot pulse>
                    RFID Aktif
                </StatusBadge>
            </div>

            <div class="mt-5 rounded-2xl border-2 border-dashed border-kopdes-200 bg-kopdes-50/40 p-5 text-center">
                <div class="relative mx-auto flex h-14 w-14 items-center justify-center">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-kopdes-400 opacity-20"></span>
                    <div class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-kopdes-100 text-kopdes-700 ring-1 ring-inset ring-kopdes-200">
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
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                            />
                        </svg>
                    </div>
                </div>

                <label for="rfid-capture-input" class="mt-3 block text-xs font-bold uppercase tracking-wider text-slate-700">
                    UID Kartu RFID / NFC (Otomatis Ternormalisasi)
                </label>

                <input
                    id="rfid-capture-input"
                    ref="rfidInputRef"
                    v-model="rawInput"
                    type="text"
                    autocomplete="off"
                    :disabled="loading"
                    @input="onInputChange"
                    @keydown.enter.prevent="onEnterKey"
                    class="kopdes-input kopdes-num mt-2 text-center text-base font-extrabold uppercase tracking-wider"
                    placeholder="Tempelkan kartu ke reader..."
                />

                <div class="mt-2.5 text-xs text-slate-600">
                    Hasil Normalisasi:
                    <span class="kopdes-num rounded bg-white px-2 py-0.5 font-bold text-slate-900 ring-1 ring-inset ring-slate-200">
                        {{ normalizedPreview || '-' }}
                    </span>
                </div>

                <!-- Tombol Simulasi Tap Kartu Baru (Hanya saat APP_DEMO_MODE=true) -->
                <div v-if="isDemoMode" class="mt-3.5 border-t border-kopdes-200/70 pt-3">
                    <button
                        type="button"
                        :disabled="loading"
                        @click.stop="simulateTapNewCard"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-900 shadow-sm transition hover:bg-amber-200 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Simulasi Tap Kartu Baru (Mode Demo)</span>
                    </button>
                </div>
            </div>

            <div
                v-if="localError || errorMessage"
                role="alert"
                class="mt-3.5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700"
            >
                {{ localError || errorMessage }}
            </div>

            <div class="mt-6 flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                <SecondaryButton
                    type="button"
                    :disabled="loading"
                    @click.stop="handleClose"
                >
                    Batal
                </SecondaryButton>

                <PrimaryButton
                    type="button"
                    :disabled="loading || isDebouncing || !normalizedPreview"
                    @click.stop="finalizeCapture"
                >
                    {{ loading ? 'Memproses...' : 'Gunakan UID Kartu' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
