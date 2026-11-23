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
            <div class="flex items-start justify-between border-b border-gray-200 pb-3">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        Identifikasi Kartu Member KOPDES (F2)
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Kartu anggota KOPDES berlaku lintas desa di seluruh Indonesia (tanpa PIN)
                    </p>
                </div>
                <span
                    class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-bold text-red-800"
                >
                    Fokus RFID Terisolasi
                </span>
            </div>

            <!-- Info Member Aktif Saat Ini -->
            <div
                v-if="selectedMember"
                class="mt-4 flex items-center justify-between rounded-lg border border-green-300 bg-green-50 p-3 text-xs"
            >
                <div>
                    <div class="font-bold text-green-900">
                        Member Tertaut: {{ selectedMember.name }}
                    </div>
                    <div class="text-green-700">
                        {{ selectedMember.member_code }} — {{ selectedMember.home_branch_name }}
                    </div>
                </div>
                <button
                    type="button"
                    @click.stop="handleDetachMember"
                    class="rounded border border-red-300 bg-white px-2.5 py-1 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50"
                >
                    Lepas Member
                </button>
            </div>

            <!-- Box Input USB RFID Reader -->
            <div class="mt-4 rounded-lg border-2 border-dashed border-red-300 bg-red-50/40 p-4 text-center">
                <label
                    for="pos-member-rfid-input"
                    class="block text-xs font-semibold text-gray-700"
                >
                    Tempelkan Kartu Member pada USB RFID Reader
                </label>

                <input
                    id="pos-member-rfid-input"
                    ref="rfidInputRef"
                    v-model="rawCardUid"
                    type="text"
                    autocomplete="off"
                    :disabled="isLookingUpCard"
                    @keydown.enter.prevent="scheduleDebouncedLookup"
                    class="mt-2 block w-full rounded-md border-gray-300 text-center font-mono text-base font-bold uppercase tracking-wider text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"
                    placeholder="Menunggu tap kartu member..."
                />

                <div class="mt-2 text-xs text-gray-500">
                    UID Ternormalisasi:
                    <span class="font-mono font-bold text-gray-800">
                        {{ normalizedUidPreview || '-' }}
                    </span>
                </div>
            </div>

            <div
                v-if="errorMessage"
                class="mt-3 rounded-md border border-red-300 bg-red-50 p-2.5 text-xs font-semibold text-red-700"
            >
                {{ errorMessage }}
            </div>

            <div class="mt-6 flex items-center justify-end gap-2">
                <SecondaryButton
                    type="button"
                    :disabled="isLookingUpCard"
                    @click.stop="handleClose"
                >
                    Tutup (Esc)
                </SecondaryButton>

                <PrimaryButton
                    type="button"
                    class="bg-red-700 hover:bg-red-800"
                    :class="{ 'opacity-25': isLookingUpCard || !normalizedUidPreview }"
                    :disabled="isLookingUpCard || !normalizedUidPreview"
                    @click.stop="performLookup"
                >
                    {{ isLookingUpCard ? 'Mencari Member...' : 'Cari Kartu Member' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
