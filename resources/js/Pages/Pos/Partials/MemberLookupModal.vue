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
    selectedMember: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'selected', 'detach']);

const rawCardUid = ref('');
const errorMessage = ref('');
const isLookingUpCard = ref(false);
const rfidInputRef = ref(null);
let debounceTimer = null;

const normalizedUidPreview = computed(() => normalizeCardUid(rawCardUid.value));

const clearDebounce = () => {
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }
};

const focusRfidInput = () => {
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
        clearDebounce();
        errorMessage.value = '';
        rawCardUid.value = '';
        isLookingUpCard.value = false;

        if (isOpen) {
            focusRfidInput();
        }
    },
);

const performLookup = async () => {
    clearDebounce();

    if (isLookingUpCard.value) {
        return;
    }

    const normalized = normalizeCardUid(rawCardUid.value);
    if (!normalized) {
        errorMessage.value = 'Silakan tempelkan kartu RFID member terlebih dahulu.';
        focusRfidInput();
        return;
    }

    isLookingUpCard.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.get(route('pos.members.lookup'), {
            params: { card_uid: normalized },
        });

        const memberData = response.data;
        emit('selected', memberData);
        emit('close');
    } catch (error) {
        const msg =
            error?.response?.data?.message ||
            'Kartu member tidak ditemukan atau sedang tidak aktif.';
        errorMessage.value = msg;
        rawCardUid.value = '';
        focusRfidInput();
    } finally {
        isLookingUpCard.value = false;
    }
};

/**
 * Apply 350ms debounce on RFID reader Enter/stream to prevent duplicate lookup requests (PRD 6.4).
 */
const scheduleDebouncedLookup = () => {
    if (isLookingUpCard.value) {
        return;
    }

    errorMessage.value = '';
    clearDebounce();

    if (!normalizeCardUid(rawCardUid.value)) {
        return;
    }

    debounceTimer = setTimeout(() => {
        performLookup();
    }, 350);
};

const handleDetachMember = () => {
    emit('detach');
    emit('close');
};

const handleClose = () => {
    if (isLookingUpCard.value) {
        return;
    }

    clearDebounce();
    emit('close');
};

onBeforeUnmount(() => {
    clearDebounce();
});
</script>

<template>
    <Modal :show="show" max-width="md" @close="handleClose">
        <div class="p-6" @click="focusRfidInput">
            <!-- Header -->
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <StatusBadge variant="kopdes" size="xs" dot :pulse="!isLookingUpCard">
                            Fokus RFID Terisolasi
                        </StatusBadge>
                        <kbd class="kopdes-num rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 ring-1 ring-slate-200">
                            F2
                        </kbd>
                    </div>
                    <h3 class="mt-2 text-lg font-extrabold tracking-tight text-slate-900">
                        Tap Kartu Member KOPDES
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Berlaku lintas desa seluruh Indonesia tanpa PIN untuk harga member &amp; akumulasi SHU.
                    </p>
                </div>

                <button
                    type="button"
                    aria-label="Tutup modal member"
                    :disabled="isLookingUpCard"
                    @click.stop="handleClose"
                    class="cursor-pointer rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-40"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Info Member Aktif Saat Ini -->
            <div
                v-if="selectedMember"
                class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50/80 p-3.5"
            >
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                        Member Sedang Tertaut
                    </div>
                    <div class="truncate text-sm font-extrabold text-emerald-950">
                        {{ selectedMember.name }}
                    </div>
                    <div class="kopdes-num truncate text-[11px] text-emerald-800">
                        {{ selectedMember.member_code }} &bull; {{ selectedMember.home_branch_name }}
                    </div>
                </div>
                <button
                    type="button"
                    @click.stop="handleDetachMember"
                    class="shrink-0 cursor-pointer rounded-xl border border-rose-200 bg-white px-3 py-1.5 text-xs font-bold text-rose-700 shadow-sm transition-colors hover:bg-rose-50"
                >
                    Lepas
                </button>
            </div>

            <!-- Visual NFC/RFID Tap Zone -->
            <div
                class="mt-4 rounded-2xl border-2 border-dashed p-5 text-center transition-colors"
                :class="
                    isLookingUpCard
                        ? 'border-indigo-300 bg-indigo-50/50'
                        : errorMessage
                          ? 'border-rose-300 bg-rose-50/40'
                          : 'border-kopdes-200 bg-gradient-to-b from-kopdes-50/60 to-white'
                "
            >
                <div class="relative mx-auto flex h-14 w-14 items-center justify-center">
                    <span
                        v-if="!isLookingUpCard"
                        class="animate-rfid-ping absolute inline-flex h-full w-full rounded-2xl bg-kopdes-400/35"
                        aria-hidden="true"
                    ></span>
                    <div
                        class="relative flex h-12 w-12 items-center justify-center rounded-2xl shadow-sm"
                        :class="
                            isLookingUpCard
                                ? 'bg-indigo-600 text-white'
                                : 'bg-kopdes-700 text-white'
                        "
                    >
                        <svg
                            v-if="isLookingUpCard"
                            class="h-6 w-6 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.9"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                    </div>
                </div>

                <label
                    for="pos-member-rfid-input"
                    class="mt-3 block text-xs font-bold text-slate-800"
                >
                    {{ isLookingUpCard ? 'Memverifikasi Kartu Member...' : 'Tempelkan Kartu Member pada USB RFID Reader' }}
                </label>

                <input
                    id="pos-member-rfid-input"
                    ref="rfidInputRef"
                    v-model="rawCardUid"
                    type="text"
                    autocomplete="off"
                    :disabled="isLookingUpCard"
                    @keydown.enter.prevent="scheduleDebouncedLookup"
                    class="kopdes-input kopdes-num mt-2.5 text-center text-base font-bold uppercase tracking-widest disabled:bg-slate-100"
                    placeholder="Menunggu tap kartu member..."
                />

                <div class="mt-2 flex items-center justify-center gap-1.5 text-xs text-slate-500">
                    <span>UID Ternormalisasi:</span>
                    <span class="kopdes-num rounded-md bg-slate-100 px-2 py-0.5 font-bold text-slate-800">
                        {{ normalizedUidPreview || '-' }}
                    </span>
                </div>
            </div>

            <!-- Instant Error Feedback -->
            <div
                v-if="errorMessage"
                role="alert"
                class="mt-3.5 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-800"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ errorMessage }}</span>
            </div>

            <!-- Actions -->
            <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                <AppButton
                    type="button"
                    variant="secondary"
                    :disabled="isLookingUpCard"
                    @click.stop="handleClose"
                >
                    Tutup (Esc)
                </AppButton>

                <AppButton
                    type="button"
                    variant="primary"
                    :loading="isLookingUpCard"
                    :disabled="!normalizedUidPreview"
                    @click.stop="performLookup"
                >
                    {{ isLookingUpCard ? 'Mencari...' : 'Tautkan Member' }}
                </AppButton>
            </div>
        </div>
    </Modal>
</template>
