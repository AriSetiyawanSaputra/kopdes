<script setup>
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    transaction: {
        type: Object,
        required: true,
    },
    openShifts: {
        type: Array,
        default: () => [],
    },
    eligibility: {
        type: Object,
        default: () => ({ is_eligible: false, reason: null, max_days: 7 }),
    },
});

const itemRows = reactive(
    props.transaction.items.map((item) => ({
        transaction_item_id: item.id,
        product_name_snapshot: item.product_name_snapshot,
        unit_snapshot: item.unit_snapshot,
        qty: item.qty,
        returned_qty: item.returned_qty,
        remaining_returnable_qty: item.remaining_returnable_qty,
        unit_price_snapshot: Number(item.unit_price_snapshot),
        cost_price_snapshot: Number(item.cost_price_snapshot),
        selected: false,
        qty_returned: item.remaining_returnable_qty > 0 ? 1 : 0,
        condition: 'restockable',
    })),
);

const form = useForm({
    transaction_id: props.transaction.id,
    reason: '',
    refund_method: 'cash_refund',
    cash_source: 'branch_cash',
    refund_cashier_shift_id: props.openShifts[0]?.id ?? '',
    refund_reference: '',
    items: [],
});

const selectedItems = computed(() =>
    itemRows.filter((row) => row.selected && row.remaining_returnable_qty > 0 && Number(row.qty_returned) > 0),
);

const estimatedTotalRefund = computed(() =>
    selectedItems.value.reduce((sum, r) => sum + Number(r.unit_price_snapshot) * Number(r.qty_returned), 0),
);

const estimatedRestockedCogs = computed(() =>
    selectedItems.value
        .filter((r) => r.condition === 'restockable')
        .reduce((sum, r) => sum + Number(r.cost_price_snapshot) * Number(r.qty_returned), 0),
);

const estimatedDamagedCogs = computed(() =>
    selectedItems.value
        .filter((r) => r.condition === 'damaged')
        .reduce((sum, r) => sum + Number(r.cost_price_snapshot) * Number(r.qty_returned), 0),
);

const selectAllFullVoid = () => {
    itemRows.forEach((row) => {
        if (row.remaining_returnable_qty > 0) {
            row.selected = true;
            row.qty_returned = row.remaining_returnable_qty;
        }
    });
};

const clearSelection = () => {
    itemRows.forEach((row) => {
        row.selected = false;
        row.qty_returned = row.remaining_returnable_qty > 0 ? 1 : 0;
    });
};

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

const submitReturn = () => {
    if (!props.eligibility.is_eligible || selectedItems.value.length === 0) {
        return;
    }

    form.items = selectedItems.value.map((r) => ({
        transaction_item_id: r.transaction_item_id,
        qty_returned: Number(r.qty_returned),
        condition: r.condition,
    }));

    if (form.refund_method === 'manual_transfer') {
        form.cash_source = null;
        form.refund_cashier_shift_id = null;
    } else if (form.cash_source !== 'active_shift') {
        form.refund_cashier_shift_id = null;
    }

    form.post(route('returns.store'));
};
</script>

<template>
    <Head :title="`Proses Retur — ${transaction.invoice_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                            Proses Retur &amp; Pembatalan Transaksi
                        </h1>
                        <StatusBadge :variant="eligibility.is_eligible ? 'emerald' : 'rose'" size="xs" dot>
                            {{ eligibility.is_eligible ? `Eligible (Batas ${eligibility.max_days} Hari)` : 'Tidak Eligible' }}
                        </StatusBadge>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Invoice: <span class="kopdes-num font-bold text-slate-900">{{ transaction.invoice_number }}</span> &bull;
                        Cabang: <strong class="text-slate-900">{{ transaction.branch?.name }}</strong>
                    </p>
                </div>
                <Link
                    :href="route('reports.sales')"
                    class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                >
                    &larr; Kembali ke Laporan Penjualan
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Peringatan Tidak Memenuhi Syarat -->
                <div
                    v-if="!eligibility.is_eligible"
                    role="alert"
                    class="flex items-start gap-3 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ eligibility.reason }}</span>
                </div>

                <!-- Ringkasan Transaksi Asal -->
                <section class="kopdes-card p-5">
                    <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">Waktu Pembayaran</p>
                            <p class="kopdes-num mt-0.5 font-bold text-slate-900">{{ transaction.paid_at || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500">Pembeli / Anggota</p>
                            <p class="mt-0.5 font-bold text-slate-900">
                                {{ transaction.member ? `${transaction.member.name} (${transaction.member.member_code})` : 'Pembeli Umum' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500">Metode Bayar Asal</p>
                            <div class="mt-1">
                                <StatusBadge :variant="transaction.payment_method === 'cash' ? 'emerald' : 'indigo'" size="xs">
                                    {{ transaction.payment_method === 'cash' ? 'Tunai' : 'Midtrans' }}
                                </StatusBadge>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500">Total Transaksi Asal</p>
                            <p class="kopdes-num mt-0.5 text-base font-extrabold text-kopdes-700">{{ formatRupiah(transaction.total_amount) }}</p>
                        </div>
                    </div>
                </section>

                <!-- Form Retur -->
                <form @submit.prevent="submitReturn" class="space-y-6">
                    <!-- Pilih Item -->
                    <section class="kopdes-card overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">
                                    1. Pilih Item &amp; Kondisi Barang Retur
                                </h2>
                                <p class="text-xs text-slate-600">
                                    Nilai refund dihitung berdasarkan harga snapshot transaksi asal (batas retur {{ eligibility.max_days }} hari). Pastikan kondisi barang dipilih dengan tepat.
                                </p>
                            </div>
                            <div v-if="eligibility.is_eligible" class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="selectAllFullVoid"
                                    class="btn-kopdes-outline !px-3 !py-1.5 !text-xs"
                                >
                                    Pilih Semua (Full Void)
                                </button>
                                <button
                                    type="button"
                                    @click="clearSelection"
                                    class="btn-kopdes-secondary !px-3 !py-1.5 !text-xs"
                                >
                                    Bersihkan
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Pilih</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Produk &amp; Harga Snapshot</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Beli / Retur / Sisa</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Qty Retur</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Kondisi Barang (Restock / Rusak)</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Subtotal Refund</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr
                                        v-for="row in itemRows"
                                        :key="row.transaction_item_id"
                                        :class="
                                            row.remaining_returnable_qty === 0
                                                ? 'bg-slate-50 opacity-60'
                                                : row.selected
                                                    ? 'bg-kopdes-50/30'
                                                    : 'hover:bg-slate-50/70'
                                        "
                                    >
                                        <td class="px-4 py-3 text-center">
                                            <input
                                                v-model="row.selected"
                                                type="checkbox"
                                                :disabled="!eligibility.is_eligible || row.remaining_returnable_qty === 0"
                                                class="h-4 w-4 cursor-pointer rounded border-slate-300 text-kopdes-700 focus:ring-kopdes-500"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-slate-900">{{ row.product_name_snapshot }}</div>
                                            <div class="kopdes-num text-xs text-slate-500">
                                                {{ formatRupiah(row.unit_price_snapshot) }} / {{ row.unit_snapshot }}
                                            </div>
                                        </td>
                                        <td class="kopdes-num whitespace-nowrap px-4 py-3 text-center text-xs">
                                            <span class="font-bold text-slate-900">{{ row.qty }}</span> /
                                            <span class="text-rose-700">{{ row.returned_qty }}</span> /
                                            <span class="font-extrabold text-emerald-700">{{ row.remaining_returnable_qty }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <input
                                                v-model.number="row.qty_returned"
                                                type="number"
                                                min="1"
                                                :max="row.remaining_returnable_qty"
                                                :disabled="!row.selected || row.remaining_returnable_qty === 0"
                                                class="kopdes-input kopdes-num mx-auto w-20 text-center font-bold"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <!-- Tactile Segmented Toggle: Layak Jual vs Rusak (anti salah pilih) -->
                                            <div
                                                class="inline-flex rounded-xl border border-slate-200 bg-slate-100/80 p-1"
                                                :class="!row.selected || row.remaining_returnable_qty === 0 ? 'pointer-events-none opacity-50' : ''"
                                            >
                                                <button
                                                    type="button"
                                                    :disabled="!row.selected || row.remaining_returnable_qty === 0"
                                                    @click="row.condition = 'restockable'"
                                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-bold transition-all"
                                                    :class="
                                                        row.condition === 'restockable'
                                                            ? 'bg-emerald-600 text-white shadow-sm'
                                                            : 'text-slate-600 hover:text-slate-900'
                                                    "
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Layak Jual (Restock)
                                                </button>
                                                <button
                                                    type="button"
                                                    :disabled="!row.selected || row.remaining_returnable_qty === 0"
                                                    @click="row.condition = 'damaged'"
                                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-bold transition-all"
                                                    :class="
                                                        row.condition === 'damaged'
                                                            ? 'bg-rose-600 text-white shadow-sm'
                                                            : 'text-slate-600 hover:text-slate-900'
                                                    "
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Rusak (Tidak Restock)
                                                </button>
                                            </div>
                                        </td>
                                        <td class="kopdes-num whitespace-nowrap px-4 py-3 text-right font-bold text-slate-900">
                                            {{ row.selected ? formatRupiah(row.unit_price_snapshot * row.qty_returned) : '-' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="px-5 py-2">
                            <InputError :message="form.errors.items" />
                            <InputError :message="form.errors.transaction_id" />
                        </div>
                    </section>

                    <!-- Alasan & Metode Refund -->
                    <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                        <div class="kopdes-card space-y-4 p-5 lg:col-span-7">
                            <h2 class="text-base font-bold text-slate-900">
                                2. Alasan Retur &amp; Metode Pengembalian Dana
                            </h2>

                            <div>
                                <label for="ret-reason" class="block text-xs font-semibold text-slate-700">
                                    Alasan Retur / Pembatalan <span class="text-rose-600">*</span>
                                </label>
                                <textarea
                                    id="ret-reason"
                                    v-model="form.reason"
                                    rows="3"
                                    required
                                    :disabled="!eligibility.is_eligible"
                                    placeholder="Contoh: Kemasan bocor saat diterima pembeli / salah pilih ukuran..."
                                    class="kopdes-input mt-1"
                                ></textarea>
                                <InputError :message="form.errors.reason" class="mt-1" />
                            </div>

                            <div>
                                <span class="mb-2 block text-xs font-semibold text-slate-700">
                                    Pilih Metode Pengembalian Dana <span class="text-rose-600">*</span>
                                </span>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        @click="form.refund_method = 'cash_refund'"
                                        class="cursor-pointer rounded-xl border-2 p-3.5 text-left transition-all"
                                        :class="
                                            form.refund_method === 'cash_refund'
                                                ? 'border-kopdes-700 bg-kopdes-50/80 text-kopdes-900 ring-2 ring-kopdes-500/20'
                                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                                        "
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-extrabold">Tunai (Cash Refund)</span>
                                            <span
                                                class="flex h-5 w-5 items-center justify-center rounded-full border"
                                                :class="form.refund_method === 'cash_refund' ? 'border-kopdes-700 bg-kopdes-700 text-white' : 'border-slate-300'"
                                            >
                                                <svg v-if="form.refund_method === 'cash_refund'" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="mt-1 text-xs text-slate-600">
                                            Uang dikembalikan tunai dari Kas Cabang atau Laci Shift Aktif
                                        </div>
                                    </button>

                                    <button
                                        type="button"
                                        @click="form.refund_method = 'manual_transfer'"
                                        class="cursor-pointer rounded-xl border-2 p-3.5 text-left transition-all"
                                        :class="
                                            form.refund_method === 'manual_transfer'
                                                ? 'border-indigo-700 bg-indigo-50/80 text-indigo-900 ring-2 ring-indigo-500/20'
                                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                                        "
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-extrabold">Transfer Manual</span>
                                            <span
                                                class="flex h-5 w-5 items-center justify-center rounded-full border"
                                                :class="form.refund_method === 'manual_transfer' ? 'border-indigo-700 bg-indigo-700 text-white' : 'border-slate-300'"
                                            >
                                                <svg v-if="form.refund_method === 'manual_transfer'" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="mt-1 text-xs text-slate-600">
                                            Refund manual via transfer bank / e-wallet dengan nomor referensi
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Sumber Kas jika Tunai -->
                            <div v-if="form.refund_method === 'cash_refund'" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div>
                                    <label for="ret-cash-source" class="block text-xs font-semibold text-slate-700">
                                        Sumber Uang Tunai <span class="text-rose-600">*</span>
                                    </label>
                                    <select
                                        id="ret-cash-source"
                                        v-model="form.cash_source"
                                        class="kopdes-input mt-1 cursor-pointer"
                                    >
                                        <option value="branch_cash">Kas / Brankas Admin Cabang (Tidak ubah laci kasir)</option>
                                        <option value="active_shift">Laci Shift Kasir Aktif (Potong ekspektasi shift)</option>
                                    </select>
                                    <InputError :message="form.errors.cash_source" class="mt-1" />
                                </div>

                                <div v-if="form.cash_source === 'active_shift'">
                                    <label for="ret-shift-id" class="block text-xs font-semibold text-slate-700">
                                        Pilih Shift Kasir Terbuka di Cabang Ini <span class="text-rose-600">*</span>
                                    </label>
                                    <select
                                        v-if="openShifts.length > 0"
                                        id="ret-shift-id"
                                        v-model="form.refund_cashier_shift_id"
                                        required
                                        class="kopdes-input mt-1 cursor-pointer"
                                    >
                                        <option value="" disabled>-- Pilih Shift Aktif --</option>
                                        <option v-for="s in openShifts" :key="s.id" :value="s.id">
                                            {{ s.shift_code }} — {{ s.cashier_name }} (Buka: {{ s.opened_at }})
                                        </option>
                                    </select>
                                    <p v-else class="mt-1 text-xs font-semibold text-rose-700">
                                        Tidak ada shift kasir berstatus open di cabang ini. Silakan pilih Kas Cabang atau Transfer Manual.
                                    </p>
                                    <InputError :message="form.errors.refund_cashier_shift_id" class="mt-1" />
                                </div>
                            </div>

                            <!-- Referensi jika Transfer Manual -->
                            <div v-else class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                                <label for="ret-reference" class="block text-xs font-semibold text-indigo-900">
                                    Nomor Referensi / Keterangan Transfer <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    id="ret-reference"
                                    v-model="form.refund_reference"
                                    type="text"
                                    required
                                    maxlength="120"
                                    placeholder="Contoh: TRF-BRI-20261125-001 a.n. Budi"
                                    class="kopdes-input mt-1"
                                />
                                <InputError :message="form.errors.refund_reference" class="mt-1" />
                            </div>
                        </div>

                        <!-- Ringkasan Estimasi & Submit -->
                        <div class="kopdes-card flex flex-col justify-between p-5 lg:col-span-5">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">
                                    Ringkasan Retur
                                </h2>
                                <dl class="mt-4 space-y-2.5 text-sm">
                                    <div class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2">
                                        <dt class="text-slate-600">Item Dipilih</dt>
                                        <dd class="kopdes-num font-bold text-slate-900">{{ selectedItems.length }} baris</dd>
                                    </div>
                                    <div class="flex justify-between rounded-xl border border-emerald-200/80 bg-emerald-50/70 px-3.5 py-2">
                                        <dt class="text-emerald-900">HPP Kembali ke Stok (Layak Jual)</dt>
                                        <dd class="kopdes-num font-bold text-emerald-700">{{ formatRupiah(estimatedRestockedCogs) }}</dd>
                                    </div>
                                    <div class="flex justify-between rounded-xl border border-amber-200/80 bg-amber-50/70 px-3.5 py-2">
                                        <dt class="text-amber-900">HPP Barang Rusak (Tidak Restock)</dt>
                                        <dd class="kopdes-num font-bold text-amber-800">{{ formatRupiah(estimatedDamagedCogs) }}</dd>
                                    </div>
                                    <div class="flex justify-between rounded-xl border border-kopdes-200 bg-kopdes-50 px-3.5 py-3">
                                        <dt class="font-extrabold text-kopdes-900">TOTAL REFUND</dt>
                                        <dd class="kopdes-num text-lg font-extrabold text-kopdes-700">{{ formatRupiah(estimatedTotalRefund) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="mt-6 flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                                <Link
                                    :href="route('reports.sales')"
                                    class="btn-kopdes-secondary"
                                >
                                    Batal
                                </Link>
                                <PrimaryButton
                                    type="submit"
                                    :disabled="
                                        !eligibility.is_eligible ||
                                        selectedItems.length === 0 ||
                                        form.processing ||
                                        (form.refund_method === 'cash_refund' && form.cash_source === 'active_shift' && openShifts.length === 0)
                                    "
                                >
                                    {{ form.processing ? 'Memproses...' : 'Eksekusi Retur / Void' }}
                                </PrimaryButton>
                            </div>
                        </div>
                    </section>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
