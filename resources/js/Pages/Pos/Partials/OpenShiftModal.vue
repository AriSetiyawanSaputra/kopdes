<script setup>
import { nextTick, ref, watch } from 'vue';
import AppButton from '@/Components/AppButton.vue';
import Modal from '@/Components/Modal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

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

const quickPresets = [0, 100000, 200000, 500000];

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

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
    { immediate: true },
);

const setPresetCash = (val) => {
    openingCash.value = val;
    nextTick(() => {
        openingCashInputRef.value?.focus();
    });
};

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
        <form @submit.prevent="submitOpenShift" class="relative overflow-hidden p-6">
            <div
                class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-kopdes-800 via-kopdes-600 to-kopdes-700"
                aria-hidden="true"
            ></div>

            <div class="border-b border-slate-200 pb-4">
                <StatusBadge variant="kopdes" size="xs" dot>
                    Wajib Buka Shift Sebelum Transaksi
                </StatusBadge>
                <h3 class="mt-2 text-lg font-extrabold tracking-tight text-slate-900">
                    Buka Shift Kasir POS
                </h3>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    <span>Kasir: <strong class="font-bold text-slate-900">{{ cashier?.name ?? '-' }}</strong></span>
                    <span class="text-slate-300">&bull;</span>
                    <span>Cabang: <strong class="font-bold text-slate-900">{{ branch?.name ?? '-' }}</strong></span>
                </div>
            </div>

            <div class="mt-4 space-y-4">
                <div>
                    <label
                        for="open-shift-cash"
                        class="block text-xs font-extrabold uppercase tracking-wider text-slate-700"
                    >
                        Modal Awal Laci Tunai (Rp) *
                    </label>
                    <div class="relative mt-1.5">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 font-mono text-base font-bold text-slate-400">
                            Rp
                        </span>
                        <input
                            id="open-shift-cash"
                            ref="openingCashInputRef"
                            v-model.number="openingCash"
                            type="number"
                            min="0"
                            step="100"
                            required
                            :disabled="isSubmitting"
                            class="kopdes-input kopdes-num py-3 pl-12 pr-4 text-2xl font-black text-slate-900"
                            placeholder="0"
                        />
                    </div>
                    <div class="mt-2 grid grid-cols-4 gap-1.5">
                        <button
                            v-for="preset in quickPresets"
                            :key="preset"
                            type="button"
                            @click="setPresetCash(preset)"
                            class="kopdes-num cursor-pointer rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-[11px] font-bold text-slate-700 shadow-sm transition-colors hover:border-kopdes-300 hover:bg-kopdes-50/60"
                        >
                            {{ preset === 0 ? 'Rp 0' : `${preset / 1000}rb` }}
                        </button>
                    </div>
                    <p class="mt-1.5 text-[11px] leading-relaxed text-slate-500">
                        Masukkan nominal uang tunai modal / receh awal di laci kasir saat ini ({{ formatRupiah(openingCash) }}).
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
                        class="kopdes-input mt-1.5"
                    ></textarea>
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
            </div>

            <div class="mt-6 flex items-center justify-between gap-2 border-t border-slate-200 pt-4">
                <AppButton
                    type="button"
                    variant="secondary"
                    :disabled="isSubmitting"
                    @click="emit('logout')"
                >
                    Keluar Sesi
                </AppButton>
                <AppButton
                    type="submit"
                    variant="primary"
                    :loading="isSubmitting"
                >
                    {{ isSubmitting ? 'Membuka Shift...' : 'Buka Shift & Mulai POS' }}
                </AppButton>
            </div>
        </form>
    </Modal>
</template>
