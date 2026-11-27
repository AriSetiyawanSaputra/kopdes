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
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-extrabold tracking-tight text-slate-900">
                            Mutasi Kas Laci Shift
                        </h3>
                        <kbd class="kopdes-num rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 ring-1 ring-slate-200">
                            F4
                        </kbd>
                    </div>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">
                        Shift <span class="kopdes-num font-bold text-slate-800">{{ shift?.shift_code }}</span> &bull;
                        Hanya memengaruhi rekonsiliasi laci tunai, tidak mengubah Laba Bersih / Biaya Operasional.
                    </p>
                </div>
                <button
                    type="button"
                    aria-label="Tutup modal kas laci"
                    @click="handleClose"
                    class="cursor-pointer rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submitMovement" class="mt-4 space-y-4">
                <!-- Jenis Mutasi Selector -->
                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        @click="type = 'cash_in'"
                        class="flex cursor-pointer flex-col items-start rounded-2xl border-2 p-3.5 text-left transition-colors"
                        :class="
                            type === 'cash_in'
                                ? 'border-emerald-600 bg-emerald-50/80 text-emerald-950 shadow-sm'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                        "
                    >
                        <div class="flex items-center gap-2 text-sm font-extrabold">
                            <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m0 0l-6-6m6 6l6-6" />
                                </svg>
                            </span>
                            <span>Kas Masuk</span>
                        </div>
                        <span class="mt-1 text-[11px] leading-snug text-slate-500">
                            Tambah modal atau uang receh kembalian ke laci.
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="type = 'cash_out'"
                        class="flex cursor-pointer flex-col items-start rounded-2xl border-2 p-3.5 text-left transition-colors"
                        :class="
                            type === 'cash_out'
                                ? 'border-kopdes-700 bg-kopdes-50/80 text-kopdes-950 shadow-sm'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                        "
                    >
                        <div class="flex items-center gap-2 text-sm font-extrabold">
                            <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-kopdes-100 text-kopdes-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 20V4m0 0l-6 6m6-6l6 6" />
                                </svg>
                            </span>
                            <span>Kas Keluar</span>
                        </div>
                        <span class="mt-1 text-[11px] leading-snug text-slate-500">
                            Setor uang tunai ke brankas Admin Cabang.
                        </span>
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
                            class="kopdes-input kopdes-num mt-1.5 text-base font-extrabold"
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
                            class="kopdes-input mt-1.5"
                        />
                    </div>
                </div>

                <div
                    v-if="errorMessage"
                    role="alert"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-800"
                >
                    {{ errorMessage }}
                </div>

                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" :disabled="isSubmitting" @click="handleClose">
                        Tutup (Esc)
                    </AppButton>
                    <AppButton type="submit" variant="primary" :loading="isSubmitting">
                        {{ isSubmitting ? 'Menyimpan...' : 'Catat Mutasi Kas' }}
                    </AppButton>
                </div>
            </form>

            <!-- Riwayat Mutasi Kas Shift Berjalan -->
            <div class="mt-5 border-t border-slate-200 pt-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-600">
                        Riwayat Mutasi Shift Ini
                    </h4>
                    <StatusBadge variant="slate" size="xs">
                        {{ shift?.cash_movements?.length || 0 }} catatan
                    </StatusBadge>
                </div>

                <div
                    v-if="!shift?.cash_movements || shift.cash_movements.length === 0"
                    class="mt-2.5 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-center text-xs text-slate-500"
                >
                    Belum ada mutasi kas masuk atau kas keluar pada shift ini.
                </div>

                <div v-else class="mt-2.5 max-h-44 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                    <div
                        v-for="mov in shift.cash_movements"
                        :key="mov.id"
                        class="flex items-center justify-between gap-3 px-3.5 py-2.5 text-xs"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <StatusBadge
                                    :variant="mov.type === 'cash_in' ? 'emerald' : 'rose'"
                                    size="xs"
                                >
                                    {{ mov.type === 'cash_in' ? 'Kas Masuk' : 'Kas Keluar' }}
                                </StatusBadge>
                                <span class="truncate font-semibold text-slate-800">
                                    {{ mov.notes }}
                                </span>
                            </div>
                            <div class="kopdes-num mt-0.5 text-[11px] text-slate-500">
                                {{ mov.created_at }}
                            </div>
                        </div>
                        <span
                            class="kopdes-num shrink-0 text-sm font-extrabold"
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
