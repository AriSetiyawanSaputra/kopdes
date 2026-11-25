<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    transactionReturn: {
        type: Object,
        required: true,
    },
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

const printProof = () => {
    window.print();
};
</script>

<template>
    <Head :title="`Bukti Retur ${transactionReturn.return_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
                <div>
                    <h1 class="kopdes-num text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        Bukti Retur {{ transactionReturn.return_number }}
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Dokumen retur bersifat immutable (final) &bull; Invoice asal: {{ transactionReturn.transaction?.invoice_number }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('returns.index')"
                        class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                    >
                        &larr; Riwayat Retur
                    </Link>
                    <PrimaryButton type="button" @click="printProof">
                        Cetak Bukti Retur
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="kopdes-card p-6 print:border-0 print:shadow-none">
                    <!-- Kop Bukti Retur -->
                    <div class="flex items-start justify-between border-b-2 border-kopdes-700 pb-4">
                        <div>
                            <div class="text-lg font-extrabold text-kopdes-700">
                                KOPERASI DESA MERAH PUTIH (KOPDES)
                            </div>
                            <div class="text-sm font-bold text-slate-900">
                                {{ transactionReturn.branch?.name }} ({{ transactionReturn.branch?.code }})
                            </div>
                            <div class="text-xs text-slate-600">
                                {{ transactionReturn.branch?.address || '' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-extrabold uppercase text-slate-900">
                                BUKTI RETUR &amp; PENGEMBALIAN DANA
                            </div>
                            <div class="kopdes-num mt-1 text-xs font-bold text-kopdes-700">
                                {{ transactionReturn.return_number }}
                            </div>
                            <div class="kopdes-num text-xs text-slate-500">
                                {{ transactionReturn.returned_at }}
                            </div>
                        </div>
                    </div>

                    <!-- Metadata -->
                    <dl class="mt-4 grid grid-cols-1 gap-3 rounded-lg bg-slate-50 p-4 text-xs sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold text-slate-500">No. Invoice Asal</dt>
                            <dd class="kopdes-num mt-0.5 font-bold text-slate-900">{{ transactionReturn.transaction?.invoice_number }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Jenis Retur</dt>
                            <dd class="mt-0.5 font-bold uppercase text-slate-900">
                                {{ transactionReturn.return_type === 'full' ? 'Full Void (Retur Penuh)' : 'Retur Parsial' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Pembeli / Anggota</dt>
                            <dd class="mt-0.5 font-bold text-slate-900">
                                {{
                                    transactionReturn.member
                                        ? `${transactionReturn.member.name} (${transactionReturn.member.member_code})`
                                        : 'Pembeli Umum'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Admin Pengeksekusi</dt>
                            <dd class="mt-0.5 font-bold text-slate-900">{{ transactionReturn.processed_by?.name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Metode &amp; Sumber Refund</dt>
                            <dd class="mt-0.5 font-bold text-slate-900">
                                <template v-if="transactionReturn.refund_method === 'cash_refund'">
                                    Tunai &mdash;
                                    {{
                                        transactionReturn.cash_source === 'active_shift'
                                            ? `Laci Shift ${transactionReturn.refund_shift?.shift_code} (${transactionReturn.refund_shift?.cashier_name})`
                                            : 'Kas / Brankas Admin Cabang'
                                    }}
                                </template>
                                <template v-else>
                                    Transfer Manual (Ref: {{ transactionReturn.refund_reference }})
                                </template>
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Alasan Retur</dt>
                            <dd class="mt-0.5 font-medium text-slate-800">{{ transactionReturn.reason }}</dd>
                        </div>
                    </dl>

                    <!-- Daftar Item Retur -->
                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase text-slate-600">Produk</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase text-slate-600">Kondisi</th>
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase text-slate-600">Qty</th>
                                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase text-slate-600">Harga Snapshot</th>
                                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase text-slate-600">Subtotal Refund</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in transactionReturn.items" :key="item.id">
                                    <td class="px-3 py-2.5 font-semibold text-slate-900">{{ item.product_name_snapshot }}</td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold"
                                            :class="
                                                item.condition === 'restockable'
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-amber-50 text-amber-800'
                                            "
                                        >
                                            {{ item.condition === 'restockable' ? 'Layak Jual (Restock)' : 'Rusak / Cacat' }}
                                        </span>
                                    </td>
                                    <td class="kopdes-num px-3 py-2.5 text-center font-bold">{{ item.qty_returned }}</td>
                                    <td class="kopdes-num px-3 py-2.5 text-right text-slate-700">{{ formatRupiah(item.unit_price_snapshot) }}</td>
                                    <td class="kopdes-num px-3 py-2.5 text-right font-bold text-slate-900">{{ formatRupiah(item.subtotal_refund) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                                <tr>
                                    <td colspan="4" class="px-3 py-3 text-right text-xs font-extrabold uppercase text-slate-700">
                                        Total Pengembalian Dana (Refund)
                                    </td>
                                    <td class="kopdes-num px-3 py-3 text-right text-base font-extrabold text-kopdes-700">
                                        {{ formatRupiah(transactionReturn.total_refund_amount) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Ringkasan Dampak HPP & Tanda Tangan -->
                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3">
                            <span class="font-semibold text-emerald-800">HPP Kembali ke Stok:</span>
                            <span class="kopdes-num ms-1 font-extrabold text-emerald-900">
                                {{ formatRupiah(transactionReturn.total_restocked_cogs) }}
                            </span>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-3">
                            <span class="font-semibold text-amber-800">HPP Barang Rusak (Tidak Restock):</span>
                            <span class="kopdes-num ms-1 font-extrabold text-amber-900">
                                {{ formatRupiah(transactionReturn.total_damaged_cogs) }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-10 grid grid-cols-2 gap-8 text-center text-xs text-slate-700">
                        <div>
                            <div>Admin Pengeksekusi,</div>
                            <div class="mt-12 border-t border-slate-400 pt-1 font-bold">
                                {{ transactionReturn.processed_by?.name ?? '-' }}
                            </div>
                        </div>
                        <div>
                            <div>Penerima Dana Refund,</div>
                            <div class="mt-12 border-t border-slate-400 pt-1 font-bold">
                                {{ transactionReturn.member?.name || 'Pembeli' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
