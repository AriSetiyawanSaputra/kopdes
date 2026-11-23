<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    pendingTransactions: {
        type: Array,
        default: () => [],
    },
    todayTransactions: {
        type: Array,
        default: () => [],
    },
    processingId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits([
    'close',
    'refresh',
    'reopen-snap',
    'check-status',
    'cancel-transaction',
    'reprint',
]);

const activeTab = ref('pending');

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

const statusBadgeClass = (status) => {
    return {
        paid: 'bg-green-100 text-green-800 border-green-300',
        pending: 'bg-amber-100 text-amber-800 border-amber-300',
        expired: 'bg-gray-100 text-gray-700 border-gray-300',
        cancelled: 'bg-red-100 text-red-800 border-red-300',
        failed: 'bg-red-100 text-red-800 border-red-300',
    }[status] ?? 'bg-gray-100 text-gray-800 border-gray-300';
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        Transaksi Pending &amp; Riwayat Hari Ini
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Rekonsiliasi pembayaran Midtrans atau cetak ulang struk transaksi lunas hari berjalan
                    </p>
                </div>
                <button
                    type="button"
                    @click="emit('refresh')"
                    class="rounded border border-gray-300 bg-white px-3 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Muat Ulang Data
                </button>
            </div>

            <!-- Tabs -->
            <div class="mt-4 flex border-b border-gray-200 text-sm font-semibold">
                <button
                    type="button"
                    @click="activeTab = 'pending'"
                    class="border-b-2 px-4 py-2 transition"
                    :class="
                        activeTab === 'pending'
                            ? 'border-red-700 text-red-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    "
                >
                    Transaksi Pending Cabang ({{ pendingTransactions.length }})
                </button>
                <button
                    type="button"
                    @click="activeTab = 'today'"
                    class="border-b-2 px-4 py-2 transition"
                    :class="
                        activeTab === 'today'
                            ? 'border-red-700 text-red-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    "
                >
                    Riwayat Hari Ini ({{ todayTransactions.length }})
                </button>
            </div>

            <!-- Tab 1: Transaksi Pending Cabang -->
            <div v-if="activeTab === 'pending'" class="mt-4 max-h-96 space-y-3 overflow-y-auto">
                <div
                    v-if="pendingTransactions.length === 0"
                    class="rounded-lg border border-gray-200 bg-gray-50 p-6 text-center text-sm text-gray-500"
                >
                    Tidak ada transaksi Midtrans yang sedang menunggu pembayaran (`pending`).
                </div>

                <div
                    v-for="tx in pendingTransactions"
                    :key="tx.id"
                    class="rounded-lg border border-amber-300 bg-amber-50/40 p-4"
                >
                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <div>
                            <div class="font-mono text-sm font-extrabold text-gray-900">
                                {{ tx.invoice_number }}
                            </div>
                            <div class="text-xs text-gray-600">
                                Waktu: {{ tx.created_at }} • Pembeli:
                                <strong>{{ tx.member?.name ?? 'Pembeli Umum' }}</strong>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-base font-extrabold text-gray-900">
                                {{ formatRupiah(tx.total_amount) }}
                            </div>
                            <span
                                class="inline-flex items-center rounded border border-amber-300 bg-amber-100 px-2 py-0.5 text-[11px] font-bold uppercase text-amber-800"
                            >
                                PENDING MIDTRANS
                            </span>
                        </div>
                    </div>

                    <!-- 3 Tombol Aksi Wajib FR-13 -->
                    <div class="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-amber-200 pt-3">
                        <button
                            v-if="tx.snap_token"
                            type="button"
                            :disabled="processingId === tx.id"
                            @click="emit('reopen-snap', tx)"
                            class="rounded bg-blue-700 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-blue-800 disabled:opacity-50"
                        >
                            Buka Ulang Pembayaran
                        </button>
                        <button
                            type="button"
                            :disabled="processingId === tx.id"
                            @click="emit('check-status', tx)"
                            class="rounded border border-green-400 bg-green-50 px-3 py-1.5 text-xs font-bold text-green-800 shadow-sm hover:bg-green-100 disabled:opacity-50"
                        >
                            {{ processingId === tx.id ? 'Memeriksa...' : 'Cek Status Pembayaran' }}
                        </button>
                        <button
                            type="button"
                            :disabled="processingId === tx.id"
                            @click="emit('cancel-transaction', tx)"
                            class="rounded border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700 shadow-sm hover:bg-red-100 disabled:opacity-50"
                        >
                            Batalkan Transaksi
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Riwayat Hari Ini (Cetak Ulang Struk Paid) -->
            <div v-else class="mt-4 max-h-96 space-y-2.5 overflow-y-auto">
                <div
                    v-if="todayTransactions.length === 0"
                    class="rounded-lg border border-gray-200 bg-gray-50 p-6 text-center text-sm text-gray-500"
                >
                    Belum ada transaksi pada cabang ini hari ini.
                </div>

                <div
                    v-for="tx in todayTransactions"
                    :key="tx.id"
                    class="flex flex-col justify-between gap-2 rounded-lg border border-gray-200 bg-white p-3 sm:flex-row sm:items-center"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-extrabold text-gray-900">
                                {{ tx.invoice_number }}
                            </span>
                            <span
                                class="inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-bold uppercase"
                                :class="statusBadgeClass(tx.payment_status)"
                            >
                                {{ tx.payment_status }}
                            </span>
                            <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-[10px] font-semibold uppercase text-gray-700">
                                {{ tx.payment_method }}
                            </span>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ tx.paid_at || tx.created_at }} •
                            {{ tx.member ? `${tx.member.name} (${tx.member.home_branch_name})` : 'Pembeli Umum' }}
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="text-right font-mono text-sm font-extrabold text-gray-900">
                            {{ formatRupiah(tx.total_amount) }}
                        </div>

                        <button
                            v-if="tx.payment_status === 'paid'"
                            type="button"
                            @click="emit('reprint', tx)"
                            class="rounded border border-gray-300 bg-gray-50 px-3 py-1.5 text-xs font-bold text-gray-800 shadow-sm hover:bg-gray-100"
                        >
                            Cetak Ulang Struk
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end border-t border-gray-200 pt-4">
                <SecondaryButton type="button" @click="emit('close')">
                    Tutup
                </SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
