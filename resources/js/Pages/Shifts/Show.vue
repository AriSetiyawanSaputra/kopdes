<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    shift: {
        type: Object,
        required: true,
    },
    pendingTransactions: {
        type: Array,
        default: () => [],
    },
    permissions: {
        type: Object,
        default: () => ({ can_force_close: false }),
    },
});

const showForceCloseModal = ref(false);

const forceCloseForm = useForm({
    actual_closing_cash: 0,
    closing_notes: '',
});

const formatRupiah = (value) => {
    const num = Number(value || 0);
    const prefix = num < 0 ? '-' : '';
    return (
        prefix +
        new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Math.abs(num))
    );
};

const openForceCloseModal = () => {
    forceCloseForm.reset();
    forceCloseForm.clearErrors();
    showForceCloseModal.value = true;
};

const submitForceClose = () => {
    forceCloseForm.post(route('shifts.force-close', props.shift.id), {
        preserveScroll: true,
        onSuccess: () => {
            showForceCloseModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Detail Shift ${shift.shift_code}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="kopdes-num text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                            {{ shift.shift_code }}
                        </h1>
                        <StatusBadge v-if="shift.status === 'open'" variant="indigo" dot pulse>
                            Aktif (Open)
                        </StatusBadge>
                        <StatusBadge v-else variant="emerald" dot>
                            Selesai (Closed)
                        </StatusBadge>
                        <StatusBadge v-if="shift.is_force_closed" variant="amber">
                            Ditutup Paksa (Force-Close)
                        </StatusBadge>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        Kasir: <strong class="text-slate-900">{{ shift.cashier?.name }}</strong> &bull;
                        Cabang: <strong class="text-slate-900">{{ shift.branch?.name }} ({{ shift.branch?.code }})</strong>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('shifts.index')"
                        class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                    >
                        &larr; Kembali ke Riwayat Shift
                    </Link>
                    <DangerButton
                        v-if="permissions.can_force_close"
                        type="button"
                        :disabled="pendingTransactions.length > 0"
                        @click="openForceCloseModal"
                    >
                        Force-Close Shift
                    </DangerButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Peringatan Pending Transactions -->
                <div
                    v-if="shift.status === 'open' && pendingTransactions.length > 0"
                    role="alert"
                    class="flex items-start gap-3 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p class="font-bold">
                            Force-Close Diblokir: Masih ada {{ pendingTransactions.length }} transaksi Midtrans berstatus pending pada shift ini.
                        </p>
                        <p class="mt-1 text-xs text-rose-700">
                            Invoice pending:
                            <span class="kopdes-num font-bold">
                                {{ pendingTransactions.map((t) => t.invoice_number).join(', ') }}
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Ringkasan Rekonsiliasi & Metadata Shift -->
                <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <div class="kopdes-card p-5 lg:col-span-5">
                        <h2 class="text-base font-bold text-slate-900">Informasi Shift</h2>
                        <dl class="mt-4 space-y-2.5 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Waktu Buka</dt>
                                <dd class="kopdes-num font-semibold text-slate-900">{{ shift.opened_at }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Waktu Tutup</dt>
                                <dd class="kopdes-num font-semibold text-slate-900">{{ shift.closed_at || 'Masih Berjalan' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Ditutup Oleh</dt>
                                <dd class="font-semibold text-slate-900">{{ shift.closed_by?.name || '-' }}</dd>
                            </div>
                            <div class="border-t border-slate-100 pt-2">
                                <dt class="text-xs font-semibold text-slate-500">Catatan Buka Shift</dt>
                                <dd class="mt-0.5 text-xs text-slate-700">{{ shift.opening_notes || '-' }}</dd>
                            </div>
                            <div class="border-t border-slate-100 pt-2">
                                <dt class="text-xs font-semibold text-slate-500">Catatan Tutup Shift</dt>
                                <dd class="mt-0.5 text-xs text-slate-700">{{ shift.closing_notes || '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="kopdes-card p-5 lg:col-span-7">
                        <h2 class="text-base font-bold text-slate-900">Rekonsiliasi Laci Tunai</h2>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2">
                                <dt class="text-slate-600">Modal Awal Laci</dt>
                                <dd class="kopdes-num font-bold text-slate-900">{{ formatRupiah(shift.opening_cash) }}</dd>
                            </div>
                            <template v-if="shift.status === 'closed'">
                                <div class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2">
                                    <dt class="text-slate-600">(+) Penjualan Tunai (Paid)</dt>
                                    <dd class="kopdes-num font-bold text-emerald-700">{{ formatRupiah(shift.cash_sales_total) }}</dd>
                                </div>
                                <div class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2">
                                    <dt class="text-slate-600">(+) Kas Masuk Laci</dt>
                                    <dd class="kopdes-num font-semibold text-emerald-700">{{ formatRupiah(shift.cash_in_total) }}</dd>
                                </div>
                                <div class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2">
                                    <dt class="text-slate-600">(&minus;) Kas Keluar Laci</dt>
                                    <dd class="kopdes-num font-semibold text-rose-700">{{ formatRupiah(shift.cash_out_total) }}</dd>
                                </div>
                                <div class="flex justify-between rounded-xl bg-slate-50 px-3.5 py-2">
                                    <dt class="text-slate-600">(&minus;) Refund Tunai dari Laci</dt>
                                    <dd class="kopdes-num font-semibold text-rose-700">{{ formatRupiah(shift.cash_refund_total) }}</dd>
                                </div>
                                <div class="flex justify-between rounded-xl border border-slate-200 px-3.5 py-2">
                                    <dt class="font-bold text-slate-800">Ekspektasi Tunai Sistem</dt>
                                    <dd class="kopdes-num font-extrabold text-slate-900">{{ formatRupiah(shift.expected_closing_cash) }}</dd>
                                </div>
                                <div class="flex justify-between rounded-xl border border-slate-200 px-3.5 py-2">
                                    <dt class="font-bold text-slate-800">Uang Tunai Fisik Aktual</dt>
                                    <dd class="kopdes-num font-extrabold text-slate-900">{{ formatRupiah(shift.actual_closing_cash) }}</dd>
                                </div>
                                <div
                                    class="flex justify-between rounded-xl border px-3.5 py-2.5"
                                    :class="
                                        Number(shift.variance) === 0
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                                            : Number(shift.variance) < 0
                                                ? 'border-rose-200 bg-rose-50 text-rose-900'
                                                : 'border-amber-200 bg-amber-50 text-amber-900'
                                    "
                                >
                                    <dt class="font-extrabold">Selisih Kas (Variance)</dt>
                                    <dd class="kopdes-num text-base font-extrabold">
                                        {{ Number(shift.variance) > 0 ? '+' : '' }}{{ formatRupiah(shift.variance) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between rounded-xl border border-indigo-100 bg-indigo-50/70 px-3.5 py-2 text-xs">
                                    <dt class="font-semibold text-indigo-900">Info Penjualan Midtrans (Tidak Masuk Laci)</dt>
                                    <dd class="kopdes-num font-bold text-indigo-800">{{ formatRupiah(shift.midtrans_sales_total) }}</dd>
                                </div>
                            </template>
                            <div
                                v-else
                                class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-xs text-slate-500"
                            >
                                Rekap ekspektasi, fisik aktual, dan selisih kas akan dihitung otomatis saat shift ditutup.
                            </div>
                        </dl>
                    </div>
                </section>

                <!-- Mutasi Kas Laci -->
                <section class="kopdes-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Mutasi Kas Laci
                        </h2>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ shift.cash_movements.length }} Mutasi</span>
                        </StatusBadge>
                    </div>
                    <div v-if="shift.cash_movements.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Waktu</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Jenis</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Keterangan</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Pencatat</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="m in shift.cash_movements" :key="m.id" class="hover:bg-slate-50/80">
                                    <td class="kopdes-num whitespace-nowrap px-4 py-2.5 text-xs text-slate-600">{{ m.created_at }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-center">
                                        <StatusBadge :variant="m.type === 'cash_in' ? 'emerald' : 'rose'" size="xs">
                                            {{ m.type === 'cash_in' ? 'Kas Masuk' : 'Kas Keluar' }}
                                        </StatusBadge>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-800">{{ m.notes }}</td>
                                    <td class="px-4 py-2.5 text-xs text-slate-600">{{ m.user_name }}</td>
                                    <td
                                        class="kopdes-num whitespace-nowrap px-4 py-2.5 text-right font-bold"
                                        :class="m.type === 'cash_in' ? 'text-emerald-700' : 'text-rose-700'"
                                    >
                                        {{ m.type === 'cash_in' ? '+' : '-' }}{{ formatRupiah(m.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else
                        title="Tidak ada mutasi kas laci"
                        description="Belum ada pencatatan kas masuk atau kas keluar manual selama shift ini berlangsung."
                    />
                </section>

                <!-- Transaksi Shift -->
                <section class="kopdes-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Daftar Transaksi pada Shift Ini
                        </h2>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ shift.transactions.length }} Transaksi</span>
                        </StatusBadge>
                    </div>
                    <div v-if="shift.transactions.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">No. Invoice</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Pembeli</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Metode</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="tx in shift.transactions" :key="tx.id" class="hover:bg-slate-50/80">
                                    <td class="whitespace-nowrap px-4 py-2.5">
                                        <div class="kopdes-num text-xs font-bold text-slate-900">{{ tx.invoice_number }}</div>
                                        <div class="kopdes-num text-[11px] text-slate-500">{{ tx.paid_at || tx.created_at }}</div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-800">{{ tx.member_name }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-center">
                                        <StatusBadge :variant="tx.payment_method === 'cash' ? 'emerald' : 'indigo'" size="xs">
                                            {{ tx.payment_method === 'cash' ? 'Tunai' : 'Midtrans' }}
                                        </StatusBadge>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-center">
                                        <StatusBadge
                                            :variant="tx.payment_status === 'paid' ? 'emerald' : (tx.payment_status === 'pending' ? 'amber' : 'slate')"
                                            size="xs"
                                        >
                                            {{ tx.payment_status }}
                                        </StatusBadge>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-2.5 text-right font-bold text-slate-900">
                                        {{ formatRupiah(tx.total_amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else
                        title="Belum ada transaksi tercatat"
                        description="Transaksi POS yang diproses dalam shift ini akan muncul secara otomatis di sini."
                    />
                </section>
            </div>
        </div>

        <!-- Modal Force-Close Shift -->
        <Modal :show="showForceCloseModal" max-width="md" @close="showForceCloseModal = false">
            <form @submit.prevent="submitForceClose" class="p-6">
                <div class="flex items-start gap-3 border-b border-slate-200 pb-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-900">
                            Force-Close Shift <span class="kopdes-num">{{ shift.shift_code }}</span>
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-600">
                            Gunakan fitur ini bila kasir berhalangan atau lupa menutup shift. Masukkan hasil hitung fisik uang laci serta alasan penutupan paksa.
                        </p>
                    </div>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <div class="flex items-center justify-between">
                            <label for="fc-actual-cash" class="block text-xs font-semibold text-slate-700">
                                Uang Tunai Fisik Aktual di Laci *
                            </label>
                            <span class="kopdes-num text-xs font-bold text-slate-800">
                                {{ formatRupiah(forceCloseForm.actual_closing_cash) }}
                            </span>
                        </div>
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-slate-500">
                                Rp
                            </span>
                            <input
                                id="fc-actual-cash"
                                v-model.number="forceCloseForm.actual_closing_cash"
                                type="number"
                                min="0"
                                step="100"
                                required
                                class="kopdes-input kopdes-num pl-9 font-bold"
                            />
                        </div>
                        <InputError :message="forceCloseForm.errors.actual_closing_cash" class="mt-1" />
                    </div>

                    <div>
                        <label for="fc-notes" class="block text-xs font-semibold text-slate-700">
                            Alasan Force-Close (Wajib) *
                        </label>
                        <textarea
                            id="fc-notes"
                            v-model="forceCloseForm.closing_notes"
                            rows="3"
                            required
                            maxlength="500"
                            placeholder="Contoh: Kasir pulang mendadak, uang fisik dihitung bersama Bendahara..."
                            class="kopdes-input mt-1"
                        ></textarea>
                        <InputError :message="forceCloseForm.errors.closing_notes" class="mt-1" />
                        <InputError :message="forceCloseForm.errors.pending_transactions" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                    <SecondaryButton type="button" @click="showForceCloseModal = false">
                        Batal
                    </SecondaryButton>
                    <DangerButton type="submit" :disabled="forceCloseForm.processing">
                        {{ forceCloseForm.processing ? 'Memproses...' : 'Konfirmasi Force-Close' }}
                    </DangerButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
