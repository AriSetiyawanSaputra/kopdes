<script setup>
import { computed, nextTick, ref, watch } from 'vue';
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
            <!-- STATE 1: FORM BLIND COUNT (SEBELUM SHIFT DITUTUP) -->
            <template v-if="!closedSummary">
                <div class="flex items-start justify-between border-b border-slate-200 pb-4">
                    <div>
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-bold text-amber-800 ring-1 ring-inset ring-amber-200">
                            Mode Blind Count (Hitungan Fisik Mandiri)
                        </span>
                        <h3 class="mt-1.5 text-lg font-extrabold text-slate-900">
                            Tutup Shift Kasir (F10)
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-600">
                            Shift: <span class="kopdes-num font-bold text-slate-900">{{ shift?.shift_code }}</span> &bull;
                            Dibuka: {{ shift?.opened_at }}
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Tutup modal tutup shift"
                        @click="handleClose"
                        class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- BLOKIR JIKA ADA TRANSAKSI MIDTRANS PENDING -->
                <div
                    v-if="hasPendingTransactions"
                    class="mt-4 rounded-xl border border-rose-300 bg-rose-50 p-4 text-xs text-rose-900"
                >
                    <div class="font-extrabold text-rose-800">
                        Tutup Shift Diblokir &mdash; Terdapat {{ localPending.length }} Transaksi Midtrans Pending
                    </div>
                    <p class="mt-1 text-rose-700">
                        Selesaikan pembayaran, cek status, atau batalkan transaksi Midtrans berikut sebelum menutup shift:
                    </p>
                    <ul class="mt-2 space-y-1">
                        <li
                            v-for="(tx, idx) in localPending"
                            :key="tx.id || idx"
                            class="flex items-center justify-between rounded bg-white px-2.5 py-1.5 font-mono text-xs font-bold text-rose-800 ring-1 ring-rose-200"
                        >
                            <span>{{ tx.invoice_number }}</span>
                            <span v-if="tx.total_amount !== undefined">{{ formatRupiah(tx.total_amount) }}</span>
                        </li>
                    </ul>
                    <div class="mt-3 flex items-center justify-end gap-2">
                        <SecondaryButton type="button" @click="handleClose">
                            Kembali
                        </SecondaryButton>
                        <PrimaryButton
                            type="button"
                            @click="
                                emit('close');
                                emit('open-pending-drawer');
                            "
                        >
                            Buka Drawer Pending
                        </PrimaryButton>
                    </div>
                </div>

                <!-- FORM BLIND COUNT TANPA MENAMPILKAN EXPECTED CASH -->
                <form v-else @submit.prevent="submitCloseShift" class="mt-4 space-y-4">
                    <div class="rounded-lg border border-blue-200 bg-blue-50/70 p-3 text-xs text-blue-900">
                        Hitung seluruh uang tunai fisik di laci kasir saat ini lalu masukkan nominalnya di bawah.
                        Sistem akan menampilkan perbandingan ekspektasi dan selisih kas setelah shift resmi dikunci.
                    </div>

                    <div>
                        <label
                            for="close-shift-actual-cash"
                            class="block text-xs font-bold uppercase tracking-wider text-slate-700"
                        >
                            Total Uang Tunai Fisik di Laci (Rp) *
                        </label>
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
                            class="kopdes-input kopdes-num mt-1 text-xl font-extrabold"
                        />
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

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                        <SecondaryButton type="button" :disabled="isSubmitting" @click="handleClose">
                            Batal (Esc)
                        </SecondaryButton>
                        <PrimaryButton type="submit" :disabled="isSubmitting || isLoadingCheck || actualClosingCash === ''">
                            {{ isSubmitting ? 'Menutup Shift...' : 'Kunci & Tutup Shift' }}
                        </PrimaryButton>
                    </div>
                </form>
            </template>

            <!-- STATE 2: RINGKASAN PENUTUPAN SHIFT SETELAH CLOSED -->
            <template v-else>
                <div class="border-b border-slate-200 pb-4">
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-800 ring-1 ring-inset ring-emerald-200">
                        Shift Berhasil Ditutup
                    </span>
                    <h3 class="mt-1.5 text-lg font-extrabold text-slate-900">
                        Ringkasan Rekonsiliasi Shift {{ closedSummary.shift_code }}
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-600">
                        Ditutup pada {{ closedSummary.closed_at }} oleh {{ closedSummary.closed_by?.name || closedSummary.cashier?.name }}
                    </p>
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-slate-600">Modal Awal Laci</dt>
                        <dd class="kopdes-num font-bold text-slate-900">{{ formatRupiah(closedSummary.opening_cash) }}</dd>
                    </div>
                    <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-slate-600">(+) Penjualan Tunai (Paid)</dt>
                        <dd class="kopdes-num font-bold text-emerald-700">{{ formatRupiah(closedSummary.cash_sales_total) }}</dd>
                    </div>
                    <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-slate-600">(+) Kas Masuk Laci</dt>
                        <dd class="kopdes-num font-semibold text-emerald-700">{{ formatRupiah(closedSummary.cash_in_total) }}</dd>
                    </div>
                    <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-slate-600">(&minus;) Kas Keluar Laci</dt>
                        <dd class="kopdes-num font-semibold text-rose-700">{{ formatRupiah(closedSummary.cash_out_total) }}</dd>
                    </div>
                    <div
                        v-if="Number(closedSummary.cash_refund_total || 0) > 0"
                        class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"
                    >
                        <dt class="text-slate-600">(&minus;) Refund Tunai Laci</dt>
                        <dd class="kopdes-num font-semibold text-rose-700">{{ formatRupiah(closedSummary.cash_refund_total) }}</dd>
                    </div>
                    <div class="flex justify-between rounded-lg border border-slate-200 bg-white px-3 py-2">
                        <dt class="font-bold text-slate-800">Ekspektasi Tunai Sistem</dt>
                        <dd class="kopdes-num font-extrabold text-slate-900">{{ formatRupiah(closedSummary.expected_closing_cash) }}</dd>
                    </div>
                    <div class="flex justify-between rounded-lg border border-slate-200 bg-white px-3 py-2">
                        <dt class="font-bold text-slate-800">Uang Tunai Fisik (Blind Count)</dt>
                        <dd class="kopdes-num font-extrabold text-slate-900">{{ formatRupiah(closedSummary.actual_closing_cash) }}</dd>
                    </div>
                    <div
                        class="flex justify-between rounded-lg border px-3 py-2.5"
                        :class="
                            Number(closedSummary.variance) === 0
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                                : Number(closedSummary.variance) < 0
                                    ? 'border-rose-200 bg-rose-50 text-rose-900'
                                    : 'border-amber-200 bg-amber-50 text-amber-900'
                        "
                    >
                        <dt class="font-extrabold">Selisih Kas (Variance)</dt>
                        <dd class="kopdes-num text-base font-extrabold">
                            {{ Number(closedSummary.variance) > 0 ? '+' : '' }}{{ formatRupiah(closedSummary.variance) }}
                        </dd>
                    </div>
                    <div class="flex justify-between rounded-lg bg-blue-50/70 px-3 py-2 text-xs">
                        <dt class="font-semibold text-blue-900">Info Penjualan Midtrans (Non-Tunai)</dt>
                        <dd class="kopdes-num font-bold text-blue-800">{{ formatRupiah(closedSummary.midtrans_sales_total) }}</dd>
                    </div>
                </dl>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-4">
                    <button
                        type="button"
                        @click="emit('print-closing', closedSummary)"
                        class="btn-kopdes-outline"
                    >
                        Cetak Struk Shift
                    </button>
                    <div class="flex items-center gap-2">
                        <SecondaryButton type="button" @click="emit('open-new-shift')">
                            Buka Shift Baru
                        </SecondaryButton>
                        <PrimaryButton type="button" @click="emit('logout')">
                            Keluar (Logout)
                        </PrimaryButton>
                    </div>
                </div>
            </template>
        </div>
    </Modal>
</template>
