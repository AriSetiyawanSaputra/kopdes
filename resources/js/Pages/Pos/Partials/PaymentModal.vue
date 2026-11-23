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
    totalAmount: {
        type: Number,
        required: true,
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
            <div class="flex items-start justify-between border-b border-gray-200 pb-3">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        Penyelesaian Pembayaran POS (F9)
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ itemsCount }} item • Pembeli:
                        <span class="font-semibold text-gray-800">
                            {{
                                selectedMember
                                    ? `${selectedMember.name} (${selectedMember.home_branch_name})`
                                    : 'Pembeli Umum (Non-Member)'
                            }}
                        </span>
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-500">Total Tagihan</div>
                    <div class="font-mono text-xl font-extrabold text-red-700">
                        {{ formatRupiah(totalAmount) }}
                    </div>
                </div>
            </div>

            <!-- Pilihan Metode Pembayaran -->
            <div class="mt-4 grid grid-cols-2 gap-3">
                <button
                    type="button"
                    @click="selectPaymentMethod('cash')"
                    class="flex flex-col items-start rounded-lg border-2 p-3 text-left transition"
                    :class="
                        paymentMethod === 'cash'
                            ? 'border-red-700 bg-red-50/70 text-red-900'
                            : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                    "
                >
                    <span class="text-sm font-extrabold">1. Tunai (`cash`)</span>
                    <span class="mt-0.5 text-xs text-gray-500">
                        Bayar uang tunai dengan hitung kembalian otomatis &amp; langsung cetak struk
                    </span>
                </button>

                <button
                    type="button"
                    @click="selectPaymentMethod('midtrans')"
                    class="flex flex-col items-start rounded-lg border-2 p-3 text-left transition"
                    :class="
                        paymentMethod === 'midtrans'
                            ? 'border-blue-700 bg-blue-50/70 text-blue-900'
                            : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                    "
                >
                    <span class="text-sm font-extrabold">2. Midtrans Snap (`midtrans`)</span>
                    <span class="mt-0.5 text-xs text-gray-500">
                        QRIS, GoPay, ShopeePay, Virtual Account (Stok ditahan 15 menit)
                    </span>
                </button>
            </div>

            <!-- Panel Mode Tunai -->
            <div v-if="paymentMethod === 'cash'" class="mt-5 space-y-4">
                <div>
                    <label
                        for="pos-cash-received-input"
                        class="block text-xs font-bold uppercase tracking-wider text-gray-700"
                    >
                        Nominal Uang Tunai Diterima (Rp) *
                    </label>
                    <input
                        id="pos-cash-received-input"
                        ref="cashInputRef"
                        v-model.number="cashReceived"
                        type="number"
                        min="0"
                        step="100"
                        :disabled="isSubmitting"
                        class="mt-1 block w-full rounded-md border-gray-300 font-mono text-2xl font-extrabold text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"
                        required
                    />
                </div>

                <!-- Tombol Pecahan Cepat -->
                <div>
                    <div class="text-xs font-semibold text-gray-500">
                        Pecahan Cepat:
                    </div>
                    <div class="mt-1.5 flex flex-wrap gap-2">
                        <button
                            type="button"
                            @click="setExactCash"
                            class="rounded-md border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-800 hover:bg-red-100"
                        >
                            Uang Pas ({{ formatRupiah(totalAmount) }})
                        </button>
                        <button
                            v-for="denom in quickDenominations"
                            :key="denom"
                            type="button"
                            @click="setQuickCash(denom)"
                            class="rounded-md border border-gray-300 bg-white px-3 py-1.5 font-mono text-xs font-bold text-gray-700 hover:bg-gray-50"
                        >
                            {{ formatRupiah(denom) }}
                        </button>
                    </div>
                </div>

                <!-- Kalkulasi Kembalian Real-Time -->
                <div
                    class="flex items-center justify-between rounded-lg border p-4"
                    :class="
                        isCashSufficient
                            ? 'border-green-300 bg-green-50 text-green-900'
                            : 'border-red-300 bg-red-50 text-red-900'
                    "
                >
                    <div>
                        <div class="text-xs font-bold uppercase">
                            {{ isCashSufficient ? 'Uang Kembalian (`change_amount`)' : 'Kekurangan Pembayaran' }}
                        </div>
                        <div class="text-xs opacity-80">
                            Diterima: {{ formatRupiah(cashReceived) }}
                        </div>
                    </div>
                    <div class="font-mono text-2xl font-extrabold">
                        {{ formatRupiah(Math.abs(changeAmount)) }}
                    </div>
                </div>
            </div>

            <!-- Panel Mode Midtrans Snap -->
            <div
                v-else
                class="mt-5 rounded-lg border border-blue-200 bg-blue-50/60 p-4 text-xs text-blue-900"
            >
                <div class="font-bold">Alur Pembayaran Non-Tunai Midtrans Snap:</div>
                <ul class="mt-1.5 list-inside list-disc space-y-1 text-blue-800">
                    <li>
                        Stok barang di keranjang akan direservasi (<code>reserved_qty</code>) selama
                        <strong>15 menit</strong> agar tidak terjual ke kasir lain.
                    </li>
                    <li>
                        Pop-up <strong>Midtrans Snap</strong> akan tampil otomatis untuk pembayaran QRIS, GoPay,
                        ShopeePay, atau Virtual Account.
                    </li>
                    <li>
                        Jika pop-up tertutup sebelum selesai, transaksi tetap dapat dipantau melalui panel
                        <strong>Transaksi Pending Cabang</strong>.
                    </li>
                </ul>
            </div>

            <div
                v-if="errorMessage"
                class="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-xs font-semibold text-red-700"
            >
                {{ errorMessage }}
            </div>

            <div class="mt-6 flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
                <SecondaryButton
                    type="button"
                    :disabled="isSubmitting"
                    @click="handleClose"
                >
                    Batal (Esc)
                </SecondaryButton>

                <PrimaryButton
                    type="submit"
                    :class="[
                        paymentMethod === 'cash'
                            ? 'bg-green-700 hover:bg-green-800'
                            : 'bg-blue-700 hover:bg-blue-800',
                        { 'opacity-25': !canSubmit },
                    ]"
                    :disabled="!canSubmit"
                >
                    <span v-if="isSubmitting">Memproses Transaksi...</span>
                    <span v-else-if="paymentMethod === 'cash'">
                        Selesaikan Transaksi Tunai &amp; Cetak Struk
                    </span>
                    <span v-else>
                        Bayar via Midtrans Snap
                    </span>
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
