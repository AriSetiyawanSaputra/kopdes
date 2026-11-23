<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { normalizeCardUid } from '@/Utils/normalizeCardUid';

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
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ title }}
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ description }}
                    </p>
                </div>
                <span
                    class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800"
                >
                    Mode RFID Aktif
                </span>
            </div>

            <div class="mt-5 rounded-lg border-2 border-dashed border-red-300 bg-red-50/50 p-4 text-center">
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
                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                        />
                    </svg>
                </div>

                <label for="rfid-capture-input" class="mt-2 block text-xs font-semibold text-gray-700">
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
                    class="mt-2 block w-full rounded-md border-gray-300 text-center font-mono text-base font-bold uppercase tracking-wider text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"
                    placeholder="Tempelkan kartu ke reader..."
                />

                <div class="mt-2 text-xs text-gray-600">
                    Hasil Normalisasi:
                    <span class="font-mono font-bold text-gray-900">
                        {{ normalizedPreview || '-' }}
                    </span>
                </div>
            </div>

            <div
                v-if="localError || errorMessage"
                class="mt-3 rounded-md border border-red-200 bg-red-50 p-2.5 text-xs font-medium text-red-700"
            >
                {{ localError || errorMessage }}
            </div>

            <div class="mt-6 flex items-center justify-end gap-2">
                <SecondaryButton
                    type="button"
                    :disabled="loading"
                    @click.stop="handleClose"
                >
                    Batal
                </SecondaryButton>

                <PrimaryButton
                    type="button"
                    class="bg-red-700 hover:bg-red-800"
                    :class="{ 'opacity-25': loading || isDebouncing }"
                    :disabled="loading || isDebouncing || !normalizedPreview"
                    @click.stop="finalizeCapture"
                >
                    {{ loading ? 'Memproses...' : 'Gunakan UID Kartu' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
