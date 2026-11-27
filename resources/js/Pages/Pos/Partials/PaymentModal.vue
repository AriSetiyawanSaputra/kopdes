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
    totalAmount: {
        type: Number,
        required: true,
    },
    regularSubtotal: {
        type: Number,
        default: 0,
    },
    memberDiscountTotal: {
        type: Number,
        default: 0,
    },
    itemsCount: {
        type: Number,
        default: 0,
    },
    selectedMember: {
        type: Object,
        default: null,
    },
    isSubmitting: {
        type: Boolean,
        default: false,
    },
    errorMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'submit']);

const paymentMethod = ref('cash');
const cashReceived = ref(0);
const cashInputRef = ref(null);

const quickDenominations = [20000, 50000, 100000, 150000, 200000];

const changeAmount = computed(() => {
    return Number(cashReceived.value || 0) - Number(props.totalAmount || 0);
});

const isCashSufficient = computed(() => {
    return Number(cashReceived.value || 0) >= Number(props.totalAmount || 0);
});

const canSubmit = computed(() => {
    if (props.isSubmitting || props.totalAmount <= 0) {
        return false;
    }

    if (paymentMethod.value === 'cash') {
        return isCashSufficient.value;
    }

    return true;
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

const focusCashInput = () => {
    if (paymentMethod.value !== 'cash') {
        return;
    }

    nextTick(() => {
        if (cashInputRef.value) {
            cashInputRef.value.focus();
            cashInputRef.value.select();
        }
    });
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            paymentMethod.value = 'cash';
            cashReceived.value = Number(props.totalAmount || 0);
            focusCashInput();
        }
    },
);

const selectPaymentMethod = (method) => {
    paymentMethod.value = method;
    if (method === 'cash') {
        if (Number(cashReceived.value || 0) < Number(props.totalAmount || 0)) {
            cashReceived.value = Number(props.totalAmount || 0);
        }
        focusCashInput();
    }
};

const setExactCash = () => {
    cashReceived.value = Number(props.totalAmount || 0);
    focusCashInput();
};

const setQuickCash = (amount) => {
    cashReceived.value = Number(amount);
    focusCashInput();
};

const handleSubmit = () => {
    if (!canSubmit.value) {
        return;
    }

    emit('submit', {
        payment_method: paymentMethod.value,
        cash_received: paymentMethod.value === 'cash' ? Number(cashReceived.value) : null,
    });
};

const handleClose = () => {
    if (props.isSubmitting) {
        return;
    }

    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="handleClose">
        <form @submit.prevent="handleSubmit" class="p-6">
            <!-- Header Tagihan -->
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-extrabold tracking-tight text-slate-900">
                            Penyelesaian Pembayaran
                        </h3>
                        <kbd class="kopdes-num rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 ring-1 ring-slate-200">
                            F9
                        </kbd>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        <span class="kopdes-num font-bold text-slate-700">{{ itemsCount }}</span> item &bull;
                        <span class="font-semibold text-slate-800">
                            {{
                                selectedMember
                                    ? `${selectedMember.name} (${selectedMember.home_branch_name})`
                                    : 'Pembeli Umum (Non-Member)'
                            }}
                        </span>
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Total Tagihan
                    </div>
                    <div class="kopdes-num text-2xl font-black tracking-tight text-kopdes-700">
                        {{ formatRupiah(totalAmount) }}
                    </div>
                    <div
                        v-if="memberDiscountTotal > 0"
                        class="mt-0.5 inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-200/80"
                    >
                        Hemat Member <span class="kopdes-num ml-1">{{ formatRupiah(memberDiscountTotal) }}</span>
                    </div>
                </div>
            </div>

            <!-- Tab Pemilih Metode: Tunai vs Midtrans -->
            <div class="mt-4 grid grid-cols-2 gap-3" role="tablist" aria-label="Metode Pembayaran">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="paymentMethod === 'cash'"
                    @click="selectPaymentMethod('cash')"
                    class="flex cursor-pointer flex-col items-start rounded-2xl border-2 p-3.5 text-left transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-kopdes-500/30"
                    :class="
                        paymentMethod === 'cash'
                            ? 'border-kopdes-700 bg-kopdes-50/70 text-kopdes-950 shadow-sm'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                    "
                >
                    <div class="flex w-full items-center justify-between">
                        <span class="inline-flex items-center gap-2 text-sm font-extrabold">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span>Tunai</span>
                        </span>
                        <StatusBadge v-if="paymentMethod === 'cash'" variant="kopdes" size="xs">Aktif</StatusBadge>
                    </div>
                    <span class="mt-1 text-[11px] leading-snug text-slate-500">
                        Hitung kembalian otomatis &amp; langsung cetak struk thermal.
                    </span>
                </button>

                <button
                    type="button"
                    role="tab"
                    :aria-selected="paymentMethod === 'midtrans'"
                    @click="selectPaymentMethod('midtrans')"
                    class="flex cursor-pointer flex-col items-start rounded-2xl border-2 p-3.5 text-left transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    :class="
                        paymentMethod === 'midtrans'
                            ? 'border-indigo-600 bg-indigo-50/70 text-indigo-950 shadow-sm'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                    "
                >
                    <div class="flex w-full items-center justify-between">
                        <span class="inline-flex items-center gap-2 text-sm font-extrabold">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                            <span>Midtrans Snap</span>
                        </span>
                        <StatusBadge v-if="paymentMethod === 'midtrans'" variant="indigo" size="xs">Aktif</StatusBadge>
                    </div>
                    <span class="mt-1 text-[11px] leading-snug text-slate-500">
                        QRIS, GoPay, ShopeePay &amp; Virtual Account (stok ditahan 15 menit).
                    </span>
                </button>
            </div>

            <!-- Panel Pembayaran Tunai -->
            <div v-if="paymentMethod === 'cash'" class="mt-5 space-y-4">
                <div>
                    <label
                        for="pos-cash-received-input"
                        class="block text-xs font-extrabold uppercase tracking-wider text-slate-700"
                    >
                        Nominal Uang Tunai Diterima (Rp)
                    </label>
                    <div class="relative mt-1.5">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 font-mono text-base font-bold text-slate-400">
                            Rp
                        </span>
                        <input
                            id="pos-cash-received-input"
                            ref="cashInputRef"
                            v-model.number="cashReceived"
                            type="number"
                            min="0"
                            step="100"
                            :disabled="isSubmitting"
                            class="kopdes-input kopdes-num py-3 pl-12 pr-4 text-2xl font-black text-slate-900"
                            required
                        />
                    </div>
                </div>

                <!-- Tombol Pecahan Uang Cepat Tactile -->
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Pecahan Cepat
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            @click="setExactCash"
                            class="cursor-pointer rounded-xl border border-kopdes-200 bg-kopdes-50 px-3 py-2.5 text-center text-xs font-extrabold text-kopdes-800 shadow-sm transition-colors hover:border-kopdes-300 hover:bg-kopdes-100 active:bg-kopdes-200"
                        >
                            Uang Pas
                        </button>
                        <button
                            v-for="denom in quickDenominations"
                            :key="denom"
                            type="button"
                            @click="setQuickCash(denom)"
                            class="kopdes-num cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-center text-xs font-bold text-slate-800 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50 active:bg-slate-100"
                        >
                            {{ formatRupiah(denom) }}
                        </button>
                    </div>
                </div>

                <!-- Kotak Kembalian Besar Berwarna Emerald -->
                <div
                    class="flex items-center justify-between rounded-2xl border-2 px-5 py-4 transition-colors"
                    :class="
                        isCashSufficient
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-950'
                            : 'border-rose-300 bg-rose-50 text-rose-950'
                    "
                >
                    <div>
                        <div class="text-xs font-extrabold uppercase tracking-wider">
                            {{ isCashSufficient ? 'Uang Kembalian' : 'Kekurangan Pembayaran' }}
                        </div>
                        <div class="mt-0.5 text-xs opacity-80">
                            Diterima: <span class="kopdes-num font-bold">{{ formatRupiah(cashReceived) }}</span>
                        </div>
                    </div>
                    <div
                        class="kopdes-num text-3xl font-black tracking-tight"
                        :class="isCashSufficient ? 'text-emerald-700' : 'text-rose-700'"
                    >
                        {{ formatRupiah(Math.abs(changeAmount)) }}
                    </div>
                </div>
            </div>

            <!-- Panel Midtrans Snap -->
            <div
                v-else
                class="mt-5 rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4 text-xs text-indigo-950"
            >
                <div class="flex items-center gap-2 font-extrabold text-indigo-900">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Alur Pembayaran Non-Tunai Midtrans Snap</span>
                </div>
                <ul class="mt-2 list-inside list-disc space-y-1.5 leading-relaxed text-indigo-900/90">
                    <li>
                        Stok di keranjang akan direservasi selama <strong>15 menit</strong> agar tidak terjual ke kasir lain.
                    </li>
                    <li>
                        Jendela <strong>Midtrans Snap</strong> akan terbuka otomatis untuk pembayaran QRIS, GoPay, ShopeePay, atau Virtual Account.
                    </li>
                    <li>
                        Jika jendela tertutup sebelum lunas, pantau atau cek status kapan saja di drawer <strong>Pending &amp; Riwayat</strong>.
                    </li>
                </ul>
            </div>

            <!-- Error Message -->
            <div
                v-if="errorMessage"
                role="alert"
                class="mt-4 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-800"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ errorMessage }}</span>
            </div>

            <!-- Footer Actions -->
            <div class="mt-6 flex items-center justify-end gap-2.5 border-t border-slate-200 pt-4">
                <AppButton
                    type="button"
                    variant="secondary"
                    :disabled="isSubmitting"
                    @click="handleClose"
                >
                    Batal (Esc)
                </AppButton>

                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-extrabold text-white shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                    :class="
                        paymentMethod === 'cash'
                            ? 'bg-emerald-700 hover:bg-emerald-800 focus:ring-emerald-500'
                            : 'bg-indigo-700 hover:bg-indigo-800 focus:ring-indigo-500'
                    "
                >
                    <svg
                        v-if="isSubmitting"
                        class="h-4 w-4 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <span v-if="isSubmitting">Memproses Transaksi...</span>
                    <span v-else-if="paymentMethod === 'cash'">Selesaikan Tunai &amp; Cetak Struk</span>
                    <span v-else>Lanjut ke Midtrans Snap</span>
                </button>
            </div>
        </form>
    </Modal>
</template>
