<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import AppButton from '@/Components/AppButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
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
const nowMs = ref(Date.now());
let ticker = null;

const handleKeydown = (e) => {
    if (e.key === 'Escape' && props.show) {
        e.preventDefault();
        emit('close');
    }
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            nowMs.value = Date.now();
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', handleKeydown);
            ticker = setInterval(() => {
                nowMs.value = Date.now();
            }, 1000);
        } else {
            document.body.style.overflow = '';
            document.removeEventListener('keydown', handleKeydown);
            if (ticker) {
                clearInterval(ticker);
                ticker = null;
            }
        }
    },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', handleKeydown);
    if (ticker) {
        clearInterval(ticker);
    }
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

const getExpiryInfo = (expiresAt) => {
    if (!expiresAt) {
        return { label: 'Batas 15 menit', expired: false };
    }
    const targetMs = new Date(expiresAt).getTime();
    if (Number.isNaN(targetMs)) {
        return { label: expiresAt, expired: false };
    }
    const diffSec = Math.floor((targetMs - nowMs.value) / 1000);
    if (diffSec <= 0) {
        return { label: 'Kedaluwarsa — Cek Status', expired: true };
    }
    const mins = Math.floor(diffSec / 60);
    const secs = diffSec % 60;
    return {
        label: `Sisa ${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`,
        expired: false,
    };
};

const statusBadgeVariant = (status) => {
    return {
        paid: 'emerald',
        pending: 'amber',
        expired: 'slate',
        cancelled: 'rose',
        failed: 'rose',
    }[status] ?? 'slate';
};

const statusLabel = (status) => {
    return {
        paid: 'Lunas',
        pending: 'Menunggu',
        expired: 'Kedaluwarsa',
        cancelled: 'Dibatalkan',
        failed: 'Gagal',
    }[status] ?? status;
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="no-print print:hidden fixed inset-0 z-50 flex justify-end"
            role="dialog"
            aria-modal="true"
            aria-label="Drawer Transaksi Pending dan Riwayat Hari Ini"
            @click.stop
        >
            <!-- Backdrop -->
            <div
                class="fixed inset-0 bg-slate-900/55 backdrop-blur-sm transition-opacity"
                aria-hidden="true"
                @click="emit('close')"
            ></div>

            <!-- Slide-Over Drawer Panel -->
            <div class="relative z-10 flex h-full w-full max-w-xl flex-col bg-white shadow-2xl">
                <!-- Header -->
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 class="text-base font-extrabold tracking-tight text-slate-900">
                            Pending Midtrans &amp; Riwayat Hari Ini
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Pantau pembayaran tertunda, rekonsiliasi status, atau cetak ulang struk thermal.
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="emit('refresh')"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Muat Ulang</span>
                        </button>
                        <button
                            type="button"
                            aria-label="Tutup drawer"
                            @click="emit('close')"
                            class="cursor-pointer rounded-xl p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- 2 Segmented Tabs -->
                <div class="shrink-0 border-b border-slate-200 bg-slate-50/80 px-5 py-2.5">
                    <div class="grid grid-cols-2 gap-1.5 rounded-xl border border-slate-200 bg-slate-100 p-1">
                        <button
                            type="button"
                            @click="activeTab = 'pending'"
                            class="flex cursor-pointer items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-bold transition-colors"
                            :class="
                                activeTab === 'pending'
                                    ? 'bg-white text-kopdes-700 shadow-sm ring-1 ring-slate-200/80'
                                    : 'text-slate-600 hover:text-slate-900'
                            "
                        >
                            <span>Menunggu Midtrans</span>
                            <span
                                class="kopdes-num rounded-full px-1.5 py-0.5 text-[10px] font-extrabold"
                                :class="
                                    pendingTransactions.length > 0
                                        ? 'bg-amber-100 text-amber-800'
                                        : 'bg-slate-200 text-slate-600'
                                "
                            >
                                {{ pendingTransactions.length }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'today'"
                            class="flex cursor-pointer items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-bold transition-colors"
                            :class="
                                activeTab === 'today'
                                    ? 'bg-white text-kopdes-700 shadow-sm ring-1 ring-slate-200/80'
                                    : 'text-slate-600 hover:text-slate-900'
                            "
                        >
                            <span>Riwayat Lunas Hari Ini</span>
                            <span class="kopdes-num rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-extrabold text-slate-700">
                                {{ todayTransactions.length }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Scrollable Drawer Body -->
                <div class="flex-1 overflow-y-auto p-5">
                    <!-- TAB 1: MENUNGGU PEMBAYARAN MIDTRANS -->
                    <div v-if="activeTab === 'pending'" class="space-y-3">
                        <div
                            v-if="pendingTransactions.length === 0"
                            class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center"
                        >
                            <p class="text-sm font-bold text-slate-700">Tidak ada transaksi tertunda</p>
                            <p class="mt-1 text-xs text-slate-500">
                                Seluruh pembayaran Midtrans pada cabang ini sudah selesai atau belum ada transaksi pending.
                            </p>
                        </div>

                        <div
                            v-for="tx in pendingTransactions"
                            :key="tx.id"
                            class="rounded-2xl border border-amber-200 bg-amber-50/40 p-4 shadow-card"
                        >
                            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="kopdes-num text-sm font-extrabold text-slate-900">
                                            {{ tx.invoice_number }}
                                        </span>
                                        <StatusBadge
                                            :variant="getExpiryInfo(tx.expires_at).expired ? 'rose' : 'amber'"
                                            size="xs"
                                            dot
                                            :pulse="!getExpiryInfo(tx.expires_at).expired"
                                        >
                                            <span class="kopdes-num">{{ getExpiryInfo(tx.expires_at).label }}</span>
                                        </StatusBadge>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-600">
                                        <span class="kopdes-num">{{ tx.created_at }}</span> &bull;
                                        <strong class="text-slate-800">{{ tx.member?.name ?? 'Pembeli Umum' }}</strong>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="kopdes-num text-base font-black text-slate-900">
                                        {{ formatRupiah(tx.total_amount) }}
                                    </div>
                                    <StatusBadge variant="indigo" size="xs" class="mt-0.5">
                                        Midtrans Pending
                                    </StatusBadge>
                                </div>
                            </div>

                            <!-- 3 Aksi Rekonsiliasi -->
                            <div class="mt-3.5 flex flex-wrap items-center justify-end gap-2 border-t border-amber-200/80 pt-3">
                                <button
                                    v-if="tx.snap_token"
                                    type="button"
                                    :disabled="processingId === tx.id"
                                    @click="emit('reopen-snap', tx)"
                                    class="cursor-pointer rounded-xl bg-indigo-700 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition-colors hover:bg-indigo-800 disabled:opacity-50"
                                >
                                    Buka Ulang Snap
                                </button>
                                <button
                                    type="button"
                                    :disabled="processingId === tx.id"
                                    @click="emit('check-status', tx)"
                                    class="cursor-pointer rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 shadow-sm transition-colors hover:bg-emerald-100 disabled:opacity-50"
                                >
                                    {{ processingId === tx.id ? 'Memeriksa...' : 'Cek Status' }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="processingId === tx.id"
                                    @click="emit('cancel-transaction', tx)"
                                    class="cursor-pointer rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 shadow-sm transition-colors hover:bg-rose-100 disabled:opacity-50"
                                >
                                    Batalkan
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: RIWAYAT LUNAS HARI INI -->
                    <div v-else class="space-y-2.5">
                        <div
                            v-if="todayTransactions.length === 0"
                            class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center"
                        >
                            <p class="text-sm font-bold text-slate-700">Belum ada transaksi hari ini</p>
                            <p class="mt-1 text-xs text-slate-500">
                                Transaksi lunas yang diproses hari ini akan tampil di sini untuk cetak ulang struk.
                            </p>
                        </div>

                        <div
                            v-for="tx in todayTransactions"
                            :key="tx.id"
                            class="flex flex-col justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-card sm:flex-row sm:items-center"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="kopdes-num text-xs font-extrabold text-slate-900">
                                        {{ tx.invoice_number }}
                                    </span>
                                    <StatusBadge :variant="statusBadgeVariant(tx.payment_status)" size="xs">
                                        {{ statusLabel(tx.payment_status) }}
                                    </StatusBadge>
                                    <StatusBadge
                                        :variant="tx.payment_method === 'cash' ? 'slate' : 'indigo'"
                                        size="xs"
                                    >
                                        {{ tx.payment_method === 'cash' ? 'Tunai' : 'Midtrans' }}
                                    </StatusBadge>
                                </div>
                                <div class="mt-1 truncate text-xs text-slate-500">
                                    <span class="kopdes-num">{{ tx.paid_at || tx.created_at }}</span> &bull;
                                    {{ tx.member ? `${tx.member.name} (${tx.member.home_branch_name})` : 'Pembeli Umum' }}
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
                                <div class="kopdes-num text-sm font-black text-slate-900">
                                    {{ formatRupiah(tx.total_amount) }}
                                </div>

                                <button
                                    v-if="tx.payment_status === 'paid'"
                                    type="button"
                                    @click="emit('reprint', tx)"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm transition-colors hover:border-kopdes-300 hover:bg-kopdes-50 hover:text-kopdes-700"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    <span>Cetak Struk</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Drawer Footer -->
                <div class="flex shrink-0 items-center justify-end border-t border-slate-200 bg-slate-50/80 px-5 py-3.5">
                    <AppButton type="button" variant="secondary" @click="emit('close')">
                        Tutup (Esc)
                    </AppButton>
                </div>
            </div>
        </div>
    </Teleport>
</template>
