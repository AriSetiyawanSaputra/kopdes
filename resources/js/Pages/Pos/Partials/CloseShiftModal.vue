<script setup>
import { computed, nextTick, ref, watch } from 'vue';
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
    pendingTransactions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'closed', 'open-pending-drawer', 'print-closing', 'open-new-shift', 'logout']);

const actualClosingCash = ref('');
const closingNotes = ref('');
const errorMessage = ref('');
const isLoadingCheck = ref(false);
const isSubmitting = ref(false);
const localPending = ref([]);
const closedSummary = ref(null);
const actualInputRef = ref(null);

const hasPendingTransactions = computed(() => localPending.value.length > 0);

const varianceStatus = computed(() => {
    const val = Number(closedSummary.value?.variance || 0);
    if (val === 0) {
        return {
            label: 'Pas (Sesuai)',
            variant: 'emerald',
            cardClass: 'border-emerald-300 bg-emerald-50 text-emerald-950',
            amountClass: 'text-emerald-700',
            hint: 'Uang fisik di laci cocok 100% dengan catatan sistem.',
        };
    }
    if (val > 0) {
        return {
            label: 'Lebih (Surplus)',
            variant: 'amber',
            cardClass: 'border-amber-300 bg-amber-50 text-amber-950',
            amountClass: 'text-amber-700',
            hint: 'Uang fisik di laci melebihi ekspektasi sistem. Catat untuk audit.',
        };
    }
    return {
        label: 'Kurang (Defisit)',
        variant: 'rose',
        cardClass: 'border-rose-300 bg-rose-50 text-rose-950',
        amountClass: 'text-rose-700',
        hint: 'Uang fisik di laci kurang dari ekspektasi sistem. Laporkan ke Admin Cabang.',
    };
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

const refreshPendingCheck = async () => {
    if (!props.shift) {
        return;
    }

    isLoadingCheck.value = true;
    errorMessage.value = '';
    try {
        const res = await window.axios.get(route('pos.shifts.summary', props.shift.id));
        localPending.value = res.data.pending_transactions_for_shift || [];
    } catch {
        localPending.value = [...props.pendingTransactions];
    } finally {
        isLoadingCheck.value = false;
        if (localPending.value.length === 0) {
            nextTick(() => {
                actualInputRef.value?.focus();
            });
        }
    }
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            actualClosingCash.value = '';
            closingNotes.value = '';
            errorMessage.value = '';
            closedSummary.value = null;
            localPending.value = [...props.pendingTransactions];
            refreshPendingCheck();
        }
    },
);

const submitCloseShift = async () => {
    if (isSubmitting.value || hasPendingTransactions.value || !props.shift) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.post(
            route('pos.shifts.close', props.shift.id),
            {
                actual_closing_cash: Number(actualClosingCash.value),
                closing_notes: closingNotes.value.trim() || null,
            },
        );

        closedSummary.value = response.data.shift;
        emit('closed', {
            shift: response.data.shift,
            message: response.data.message,
        });
    } catch (error) {
        const data = error?.response?.data || {};
        if (data?.errors?.pending_invoices) {
            localPending.value = data.errors.pending_invoices.map((inv) => ({
                invoice_number: inv,
            }));
        }
        const firstErr = data?.errors ? Object.values(data.errors).flat()[0] : null;
        errorMessage.value = firstErr || data?.message || 'Gagal menutup shift kasir.';
    } finally {
        isSubmitting.value = false;
    }
};

const handleClose = () => {
    if (isSubmitting.value || closedSummary.value) {
        return;
    }
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="lg" :closeable="!closedSummary" @close="handleClose">
        <div class="p-6">
            <!-- STATE 1: FORM BLIND COUNT (SEBELUM SHIFT DITUTUP — TANPA EXPECTED CASH) -->
            <template v-if="!closedSummary">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <StatusBadge variant="amber" size="xs" dot>
                                Mode Blind Count (Hitungan Fisik Mandiri)
                            </StatusBadge>
                            <kbd class="kopdes-num rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 ring-1 ring-slate-200">
                                F10
                            </kbd>
                        </div>
                        <h3 class="mt-2 text-lg font-extrabold tracking-tight text-slate-900">
                            Tutup Shift Kasir
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Shift <span class="kopdes-num font-bold text-slate-800">{{ shift?.shift_code }}</span> &bull;
                            Dibuka <span class="kopdes-num">{{ shift?.opened_at }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Tutup modal tutup shift"
                        @click="handleClose"
                        class="cursor-pointer rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- BLOKIR JIKA ADA TRANSAKSI MIDTRANS PENDING -->
                <div
                    v-if="hasPendingTransactions"
                    class="mt-4 rounded-2xl border border-rose-200 bg-rose-50/90 p-4 text-xs text-rose-900"
                >
                    <div class="flex items-center gap-2 font-extrabold text-rose-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Tutup Shift Diblokir &mdash; {{ localPending.length }} Transaksi Midtrans Pending</span>
                    </div>
                    <p class="mt-1 leading-relaxed text-rose-800">
                        Selesaikan pembayaran, cek status, atau batalkan transaksi Midtrans berikut terlebih dahulu:
                    </p>
                    <ul class="mt-2.5 space-y-1.5">
                        <li
                            v-for="(tx, idx) in localPending"
                            :key="tx.id || idx"
                            class="kopdes-num flex items-center justify-between rounded-xl bg-white px-3 py-2 text-xs font-bold text-rose-900 ring-1 ring-rose-200"
                        >
                            <span>{{ tx.invoice_number }}</span>
                            <span v-if="tx.total_amount !== undefined">{{ formatRupiah(tx.total_amount) }}</span>
                        </li>
                    </ul>
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <AppButton type="button" variant="secondary" @click="handleClose">
                            Kembali
                        </AppButton>
                        <AppButton
                            type="button"
                            variant="primary"
                            @click="
                                emit('close');
                                emit('open-pending-drawer');
                            "
                        >
                            Buka Drawer Pending
                        </AppButton>
                    </div>
                </div>

                <!-- FORM BLIND COUNT TANPA MENAMPILKAN EXPECTED CASH -->
                <form v-else @submit.prevent="submitCloseShift" class="mt-4 space-y-4">
                    <div class="flex items-start gap-2.5 rounded-xl border border-indigo-200 bg-indigo-50/70 p-3.5 text-xs leading-relaxed text-indigo-950">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>
                            Hitung seluruh uang tunai fisik yang ada di laci kasir lalu masukkan totalnya di bawah.
                            Perbandingan ekspektasi sistem &amp; selisih kas baru akan muncul <strong>setelah shift dikunci</strong>.
                        </span>
                    </div>

                    <div>
                        <label
                            for="close-shift-actual-cash"
                            class="block text-xs font-extrabold uppercase tracking-wider text-slate-700"
                        >
                            Total Uang Tunai Fisik di Laci (Rp) *
                        </label>
                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 font-mono text-base font-bold text-slate-400">
                                Rp
                            </span>
                            <input
                                id="close-shift-actual-cash"
                                ref="actualInputRef"
                                v-model.number="actualClosingCash"
                                type="number"
                                min="0"
                                step="100"
                                required
                                :disabled="isSubmitting || isLoadingCheck"
                                placeholder="Masukkan hasil hitung fisik..."
                                class="kopdes-input kopdes-num py-3 pl-12 pr-4 text-2xl font-black text-slate-900"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            for="close-shift-notes"
                            class="block text-xs font-semibold text-slate-700"
                        >
                            Catatan Tutup Shift (Opsional)
                        </label>
                        <textarea
                            id="close-shift-notes"
                            v-model="closingNotes"
                            rows="2"
                            maxlength="500"
                            :disabled="isSubmitting"
                            placeholder="Catatan penyerahan uang tunai atau keterangan selisih bila ada..."
                            class="kopdes-input mt-1.5"
                        ></textarea>
                    </div>

                    <div
                        v-if="errorMessage"
                        role="alert"
                        class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-800"
                    >
                        {{ errorMessage }}
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                        <AppButton type="button" variant="secondary" :disabled="isSubmitting" @click="handleClose">
                            Batal (Esc)
                        </AppButton>
                        <AppButton
                            type="submit"
                            variant="primary"
                            :loading="isSubmitting"
                            :disabled="isLoadingCheck || actualClosingCash === ''"
                        >
                            {{ isSubmitting ? 'Menutup Shift...' : 'Kunci & Tutup Shift' }}
                        </AppButton>
                    </div>
                </form>
            </template>

            <!-- STATE 2: HASIL REKONSILIASI SELISIH KAS (PAS / LEBIH / KURANG) -->
            <template v-else>
                <div class="border-b border-slate-200 pb-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <StatusBadge variant="emerald" size="xs" dot>
                            Shift Resmi Ditutup
                        </StatusBadge>
                        <StatusBadge :variant="varianceStatus.variant" size="xs">
                            Status Kas: {{ varianceStatus.label }}
                        </StatusBadge>
                    </div>
                    <h3 class="mt-2 text-lg font-extrabold tracking-tight text-slate-900">
                        Rekonsiliasi Shift {{ closedSummary.shift_code }}
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Ditutup pada <span class="kopdes-num">{{ closedSummary.closed_at }}</span> oleh
                        <strong class="text-slate-800">{{ closedSummary.closed_by?.name || closedSummary.cashier?.name }}</strong>
                    </p>
                </div>

                <!-- Highlight Kartu Hasil Selisih Kas (Pas / Lebih / Kurang) -->
                <div
                    class="mt-4 rounded-2xl border-2 p-4"
                    :class="varianceStatus.cardClass"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-extrabold uppercase tracking-wider">
                                Selisih Kas &mdash; {{ varianceStatus.label }}
                            </div>
                            <p class="mt-0.5 text-xs opacity-85">
                                {{ varianceStatus.hint }}
                            </p>
                        </div>
                        <div class="kopdes-num shrink-0 text-2xl font-black" :class="varianceStatus.amountClass">
                            {{ Number(closedSummary.variance) > 0 ? '+' : '' }}{{ formatRupiah(closedSummary.variance) }}
                        </div>
                    </div>
                </div>

                <!-- Rincian Perhitungan Laci Tunai -->
                <dl class="mt-4 space-y-1.5 text-xs">
                    <div class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2">
                        <dt class="text-slate-600">Modal Awal Laci</dt>
                        <dd class="kopdes-num font-bold text-slate-900">{{ formatRupiah(closedSummary.opening_cash) }}</dd>
                    </div>
                    <div class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2">
                        <dt class="text-slate-600">(+) Penjualan Tunai (Paid)</dt>
                        <dd class="kopdes-num font-bold text-emerald-700">{{ formatRupiah(closedSummary.cash_sales_total) }}</dd>
                    </div>
                    <div class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2">
                        <dt class="text-slate-600">(+) Kas Masuk Laci</dt>
                        <dd class="kopdes-num font-semibold text-emerald-700">{{ formatRupiah(closedSummary.cash_in_total) }}</dd>
                    </div>
                    <div class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2">
                        <dt class="text-slate-600">(&minus;) Kas Keluar Laci</dt>
                        <dd class="kopdes-num font-semibold text-rose-700">{{ formatRupiah(closedSummary.cash_out_total) }}</dd>
                    </div>
                    <div
                        v-if="Number(closedSummary.cash_refund_total || 0) > 0"
                        class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2"
                    >
                        <dt class="text-slate-600">(&minus;) Refund Tunai Laci</dt>
                        <dd class="kopdes-num font-semibold text-rose-700">{{ formatRupiah(closedSummary.cash_refund_total) }}</dd>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <div class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                Ekspektasi Sistem
                            </dt>
                            <dd class="kopdes-num mt-1 text-base font-black text-slate-900">
                                {{ formatRupiah(closedSummary.expected_closing_cash) }}
                            </dd>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                Fisik Blind Count
                            </dt>
                            <dd class="kopdes-num mt-1 text-base font-black text-slate-900">
                                {{ formatRupiah(closedSummary.actual_closing_cash) }}
                            </dd>
                        </div>
                    </div>

                    <div class="flex justify-between rounded-xl border border-indigo-200 bg-indigo-50/70 px-3.5 py-2.5">
                        <dt class="font-semibold text-indigo-900">Info Penjualan Midtrans (Non-Tunai)</dt>
                        <dd class="kopdes-num font-extrabold text-indigo-800">{{ formatRupiah(closedSummary.midtrans_sales_total) }}</dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-4">
                    <AppButton
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="emit('print-closing', closedSummary)"
                    >
                        Cetak Struk Shift
                    </AppButton>
                    <div class="flex items-center gap-2">
                        <AppButton type="button" variant="secondary" size="sm" @click="emit('open-new-shift')">
                            Buka Shift Baru
                        </AppButton>
                        <AppButton type="button" variant="primary" size="sm" @click="emit('logout')">
                            Keluar (Logout)
                        </AppButton>
                    </div>
                </div>
            </template>
        </div>
    </Modal>
</template>
