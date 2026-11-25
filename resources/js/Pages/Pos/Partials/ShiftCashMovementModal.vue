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
    shift: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'recorded']);

const type = ref('cash_in');
const amount = ref('');
const notes = ref('');
const errorMessage = ref('');
const isSubmitting = ref(false);
const amountInputRef = ref(null);

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
            type.value = 'cash_in';
            amount.value = '';
            notes.value = '';
            errorMessage.value = '';
            nextTick(() => {
                amountInputRef.value?.focus();
            });
        }
    },
);

const submitMovement = async () => {
    if (isSubmitting.value || !props.shift) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.post(
            route('pos.shifts.cash-movements.store', props.shift.id),
            {
                type: type.value,
                amount: Number(amount.value),
                notes: notes.value.trim(),
            },
        );

        amount.value = '';
        notes.value = '';

        emit('recorded', {
            shift: response.data.shift,
            movement: response.data.movement,
            message: response.data.message,
        });
    } catch (error) {
        const data = error?.response?.data || {};
        const firstErr = data?.errors ? Object.values(data.errors).flat()[0] : null;
        errorMessage.value = firstErr || data?.message || 'Gagal mencatat mutasi kas laci.';
    } finally {
        isSubmitting.value = false;
    }
};

const handleClose = () => {
    if (isSubmitting.value) {
        return;
    }
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="handleClose">
        <div class="p-6">
            <div class="flex items-start justify-between border-b border-slate-200 pb-4">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">
                        Mutasi Kas Laci Shift (F4)
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-600">
                        Shift: <span class="kopdes-num font-bold text-slate-900">{{ shift?.shift_code }}</span> &mdash;
                        Mutasi ini hanya memengaruhi rekonsiliasi laci tunai dan tidak mengubah Laba Bersih / Biaya Operasional.
                    </p>
                </div>
                <button
                    type="button"
                    aria-label="Tutup modal kas laci"
                    @click="handleClose"
                    class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submitMovement" class="mt-4 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        @click="type = 'cash_in'"
                        class="cursor-pointer rounded-lg border-2 p-3 text-left transition-colors"
                        :class="
                            type === 'cash_in'
                                ? 'border-emerald-600 bg-emerald-50 text-emerald-900'
                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                        "
                    >
                        <div class="text-sm font-extrabold">Kas Masuk</div>
                        <div class="mt-0.5 text-xs text-slate-600">
                            Tambah modal / uang receh kembalian ke laci
                        </div>
                    </button>

                    <button
                        type="button"
                        @click="type = 'cash_out'"
                        class="cursor-pointer rounded-lg border-2 p-3 text-left transition-colors"
                        :class="
                            type === 'cash_out'
                                ? 'border-kopdes-700 bg-kopdes-50 text-kopdes-900'
                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                        "
                    >
                        <div class="text-sm font-extrabold">Kas Keluar</div>
                        <div class="mt-0.5 text-xs text-slate-600">
                            Setor uang tunai ke brankas Admin Cabang
                        </div>
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="shift-mov-amount" class="block text-xs font-semibold text-slate-700">
                            Nominal (Rp) *
                        </label>
                        <input
                            id="shift-mov-amount"
                            ref="amountInputRef"
                            v-model.number="amount"
                            type="number"
                            min="1"
                            step="100"
                            required
                            :disabled="isSubmitting"
                            placeholder="200000"
                            class="kopdes-input kopdes-num mt-1 text-base font-bold"
                        />
                    </div>

                    <div>
                        <label for="shift-mov-notes" class="block text-xs font-semibold text-slate-700">
                            Keterangan Wajib *
                        </label>
                        <input
                            id="shift-mov-notes"
                            v-model="notes"
                            type="text"
                            maxlength="255"
                            required
                            :disabled="isSubmitting"
                            placeholder="Contoh: Setor tunai siang ke Admin Cabang"
                            class="kopdes-input mt-1"
                        />
                    </div>
                </div>

                <div
                    v-if="errorMessage"
                    role="alert"
                    class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-800"
                >
                    {{ errorMessage }}
                </div>

                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" :disabled="isSubmitting" @click="handleClose">
                        Tutup (Esc)
                    </SecondaryButton>
                    <PrimaryButton type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Menyimpan...' : 'Catat Mutasi Kas' }}
                    </PrimaryButton>
                </div>
            </form>

            <!-- Riwayat Mutasi Kas Shift Berjalan (Append-Only) -->
            <div class="mt-5 border-t border-slate-200 pt-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600">
                    Riwayat Mutasi Laci Shift Ini ({{ shift?.cash_movements?.length || 0 }})
                </h4>

                <div
                    v-if="!shift?.cash_movements || shift.cash_movements.length === 0"
                    class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-4 text-center text-xs text-slate-500"
                >
                    Belum ada mutasi kas masuk/keluar pada shift ini.
                </div>

                <div v-else class="mt-2 max-h-44 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
                    <div
                        v-for="mov in shift.cash_movements"
                        :key="mov.id"
                        class="flex items-center justify-between gap-3 px-3 py-2 text-xs"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                    :class="
                                        mov.type === 'cash_in'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-rose-100 text-rose-800'
                                    "
                                >
                                    {{ mov.type === 'cash_in' ? 'Kas Masuk' : 'Kas Keluar' }}
                                </span>
                                <span class="truncate font-semibold text-slate-800">
                                    {{ mov.notes }}
                                </span>
                            </div>
                            <div class="mt-0.5 text-[11px] text-slate-500">
                                {{ mov.created_at }}
                            </div>
                        </div>
                        <span
                            class="kopdes-num shrink-0 font-bold"
                            :class="mov.type === 'cash_in' ? 'text-emerald-700' : 'text-rose-700'"
                        >
                            {{ mov.type === 'cash_in' ? '+' : '-' }}{{ formatRupiah(mov.amount) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
