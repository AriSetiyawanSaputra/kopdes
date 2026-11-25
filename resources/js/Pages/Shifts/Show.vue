<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
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
                        <span
                            v-if="shift.status === 'open'"
                            class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 ring-1 ring-inset ring-blue-200"
                        >
                            Aktif (Open)
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200"
                        >
                            Selesai (Closed)
                        </span>
                        <span
                            v-if="shift.is_force_closed"
                            class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800"
                        >
                            Ditutup Paksa (Force-Close)
                        </span>
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
                    class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900"
                >
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
                            <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                                <dt class="text-slate-600">Modal Awal Laci</dt>
                                <dd class="kopdes-num font-bold text-slate-900">{{ formatRupiah(shift.opening_cash) }}</dd>
                            </div>
                            <template v-if="shift.status === 'closed'">
                                <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                                    <dt class="text-slate-600">(+) Penjualan Tunai (Paid)</dt>
                                    <dd class="kopdes-num font-bold text-emerald-700">{{ formatRupiah(shift.cash_sales_total) }}</dd>
                                </div>
                                <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                                    <dt class="text-slate-600">(+) Kas Masuk Laci</dt>
                                    <dd class="kopdes-num font-semibold text-emerald-700">{{ formatRupiah(shift.cash_in_total) }}</dd>
                                </div>
                                <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                                    <dt class="text-slate-600">(&minus;) Kas Keluar Laci</dt>
                                    <dd class="kopdes-num font-semibold text-rose-700">{{ formatRupiah(shift.cash_out_total) }}</dd>
                                </div>
                                <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                                    <dt class="text-slate-600">(&minus;) Refund Tunai dari Laci</dt>
                                    <dd class="kopdes-num font-semibold text-rose-700">{{ formatRupiah(shift.cash_refund_total) }}</dd>
                                </div>
                                <div class="flex justify-between rounded-lg border border-slate-200 px-3 py-2">
                                    <dt class="font-bold text-slate-800">Ekspektasi Tunai Sistem</dt>
                                    <dd class="kopdes-num font-extrabold text-slate-900">{{ formatRupiah(shift.expected_closing_cash) }}</dd>
                                </div>
                                <div class="flex justify-between rounded-lg border border-slate-200 px-3 py-2">
                                    <dt class="font-bold text-slate-800">Uang Tunai Fisik Aktual</dt>
                                    <dd class="kopdes-num font-extrabold text-slate-900">{{ formatRupiah(shift.actual_closing_cash) }}</dd>
                                </div>
                                <div
                                    class="flex justify-between rounded-lg border px-3 py-2.5"
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
                                <div class="flex justify-between rounded-lg bg-blue-50/70 px-3 py-2 text-xs">
                                    <dt class="font-semibold text-blue-900">Info Penjualan Midtrans (Tidak Masuk Laci)</dt>
                                    <dd class="kopdes-num font-bold text-blue-800">{{ formatRupiah(shift.midtrans_sales_total) }}</dd>
                                </div>
                            </template>
                            <div
                                v-else
                                class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-xs text-slate-500"
                            >
                                Rekap ekspektasi, fisik aktual, dan selisih kas akan dihitung otomatis saat shift ditutup.
                            </div>
                        </dl>
                    </div>
                </section>

                <!-- Mutasi Kas Laci -->
                <section class="kopdes-card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Mutasi Kas Laci ({{ shift.cash_movements.length }})
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase text-slate-600">Waktu</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase text-slate-600">Jenis</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase text-slate-600">Keterangan</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase text-slate-600">Pencatat</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase text-slate-600">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="m in shift.cash_movements" :key="m.id">
                                    <td class="kopdes-num whitespace-nowrap px-4 py-2.5 text-xs text-slate-600">{{ m.created_at }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-center">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold"
                                            :class="m.type === 'cash_in' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                        >
                                            {{ m.type === 'cash_in' ? 'Kas Masuk' : 'Kas Keluar' }}
                                        </span>
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
                                <tr v-if="shift.cash_movements.length === 0">
                                    <td colspan="5" class="px-4 py-6 text-center text-xs text-slate-500">
                                        Tidak ada mutasi kas laci pada shift ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Transaksi Shift -->
                <section class="kopdes-card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Daftar Transaksi pada Shift Ini ({{ shift.transactions.length }})
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase text-slate-600">No. Invoice</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase text-slate-600">Pembeli</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase text-slate-600">Metode</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase text-slate-600">Status</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase text-slate-600">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="tx in shift.transactions" :key="tx.id">
                                    <td class="whitespace-nowrap px-4 py-2.5">
                                        <div class="kopdes-num text-xs font-bold text-slate-900">{{ tx.invoice_number }}</div>
                                        <div class="kopdes-num text-[11px] text-slate-500">{{ tx.paid_at || tx.created_at }}</div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-800">{{ tx.member_name }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-center uppercase text-xs font-bold text-slate-700">
                                        {{ tx.payment_method }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-center">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold uppercase"
                                            :class="
                                                tx.payment_status === 'paid'
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : tx.payment_status === 'pending'
                                                        ? 'bg-amber-50 text-amber-800'
                                                        : 'bg-slate-100 text-slate-600'
                                            "
                                        >
                                            {{ tx.payment_status }}
                                        </span>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-2.5 text-right font-bold text-slate-900">
                                        {{ formatRupiah(tx.total_amount) }}
                                    </td>
                                </tr>
                                <tr v-if="shift.transactions.length === 0">
                                    <td colspan="5" class="px-4 py-6 text-center text-xs text-slate-500">
                                        Belum ada transaksi tercatat pada shift ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <!-- Modal Force-Close Shift -->
        <Modal :show="showForceCloseModal" max-width="md" @close="showForceCloseModal = false">
            <form @submit.prevent="submitForceClose" class="p-6">
                <h3 class="text-lg font-extrabold text-slate-900">
                    Force-Close Shift {{ shift.shift_code }}
                </h3>
                <p class="mt-1 text-xs text-slate-600">
                    Gunakan fitur ini bila kasir berhalangan atau lupa menutup shift. Masukkan hasil hitung fisik uang laci serta alasan penutupan paksa.
                </p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="fc-actual-cash" class="block text-xs font-semibold text-slate-700">
                            Uang Tunai Fisik Aktual di Laci (Rp) *
                        </label>
                        <input
                            id="fc-actual-cash"
                            v-model.number="forceCloseForm.actual_closing_cash"
                            type="number"
                            min="0"
                            step="100"
                            required
                            class="kopdes-input kopdes-num mt-1 font-bold"
                        />
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

                <div class="mt-6 flex items-center justify-end gap-2">
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
