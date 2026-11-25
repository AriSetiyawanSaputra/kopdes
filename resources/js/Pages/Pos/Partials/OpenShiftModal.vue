<script setup>
import { nextTick, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    branch: {
        type: Object,
        default: null,
    },
    cashier: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['opened', 'logout']);

const openingCash = ref(0);
const openingNotes = ref('');
const errorMessage = ref('');
const isSubmitting = ref(false);
const openingCashInputRef = ref(null);

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            openingCash.value = 0;
            openingNotes.value = '';
            errorMessage.value = '';
            nextTick(() => {
                openingCashInputRef.value?.focus();
                openingCashInputRef.value?.select();
            });
        }
    },
);

const submitOpenShift = async () => {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.post(route('pos.shifts.open'), {
            opening_cash: Number(openingCash.value || 0),
            opening_notes: openingNotes.value.trim() || null,
        });

        emit('opened', {
            shift: response.data.shift,
            pendingTransactions: response.data.pending_transactions_for_shift || [],
            message: response.data.message,
        });
    } catch (error) {
        const data = error?.response?.data || {};
        const firstErr = data?.errors ? Object.values(data.errors).flat()[0] : null;
        errorMessage.value = firstErr || data?.message || 'Gagal membuka shift kasir.';
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Modal :show="show" max-width="md" :closeable="false">
        <form @submit.prevent="submitOpenShift" class="p-6">
            <div class="border-b border-slate-200 pb-4">
                <div class="inline-flex items-center rounded-full bg-kopdes-50 px-2.5 py-0.5 text-xs font-bold text-kopdes-700 ring-1 ring-inset ring-kopdes-200">
                    Wajib Buka Shift Sebelum Transaksi
                </div>
                <h3 class="mt-2 text-lg font-extrabold text-slate-900">
                    Buka Shift Kasir POS
                </h3>
                <p class="mt-0.5 text-xs text-slate-600">
                    Kasir: <strong class="text-slate-900">{{ cashier?.name ?? '-' }}</strong> &bull;
                    Cabang: <strong class="text-slate-900">{{ branch?.name ?? '-' }}</strong>
                </p>
            </div>

            <div class="mt-4 space-y-4">
                <div>
                    <label
                        for="open-shift-cash"
                        class="block text-xs font-bold uppercase tracking-wider text-slate-700"
                    >
                        Modal Awal Laci Tunai (Rp) *
                    </label>
                    <input
                        id="open-shift-cash"
                        ref="openingCashInputRef"
                        v-model.number="openingCash"
                        type="number"
                        min="0"
                        step="100"
                        required
                        :disabled="isSubmitting"
                        class="kopdes-input kopdes-num mt-1 text-xl font-extrabold"
                        placeholder="0"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Masukkan jumlah uang tunai modal/receh awal yang ada di laci kasir saat ini (minimal Rp 0).
                    </p>
                </div>

                <div>
                    <label
                        for="open-shift-notes"
                        class="block text-xs font-semibold text-slate-700"
                    >
                        Catatan Buka Shift (Opsional)
                    </label>
                    <textarea
                        id="open-shift-notes"
                        v-model="openingNotes"
                        rows="2"
                        maxlength="500"
                        :disabled="isSubmitting"
                        placeholder="Contoh: Shift pagi terminal 1, pecahan receh lengkap..."
                        class="kopdes-input mt-1"
                    ></textarea>
                </div>

                <div
                    v-if="errorMessage"
                    role="alert"
                    class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-800"
                >
                    {{ errorMessage }}
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between gap-2 border-t border-slate-200 pt-4">
                <SecondaryButton
                    type="button"
                    :disabled="isSubmitting"
                    @click="emit('logout')"
                >
                    Keluar Sesi
                </SecondaryButton>
                <PrimaryButton type="submit" :disabled="isSubmitting">
                    {{ isSubmitting ? 'Membuka Shift...' : 'Buka Shift & Mulai POS' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
