<script setup>
import { computed } from 'vue';
import MemberLayout from '@/Layouts/MemberLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    transaction: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const appSettings = computed(
    () =>
        page.props.app_settings ?? {
            institution_name: 'Koperasi Desa Merah Putih',
            receipt_footer: 'Terima kasih telah berbelanja di KOPDES Merah Putih.',
        },
);

const formatRp = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(val || 0));
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return dateStr;
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
};

const returnStatusLabel = computed(() => {
    if (props.transaction.return_status === 'full') return 'Dibatalkan Penuh (Full Void)';
    if (props.transaction.return_status === 'partial') return 'Sebagian Diretur';
    return null;
});
</script>

<template>
    <Head :title="`Struk ${transaction.invoice_number}`" />

    <MemberLayout>
        <div class="mx-auto max-w-xl space-y-4">
            <!-- Back & Portability Badge -->
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Link
                    :href="route('member.transactions.index')"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-kopdes-200 hover:text-kopdes-700"
                >
                    &larr; Kembali ke Riwayat
                </Link>
                <StatusBadge v-if="transaction.is_cross_branch" variant="indigo" size="xs" dot>
                    Belanja Lintas Cabang
                </StatusBadge>
                <StatusBadge v-else variant="slate" size="xs">
                    Belanja KOPDES Asal
                </StatusBadge>
            </div>

            <!-- Digital Receipt Card -->
            <article class="kopdes-card relative overflow-hidden">
                <div class="h-1.5 w-full bg-gradient-to-r from-kopdes-800 via-kopdes-600 to-rose-600"></div>

                <!-- Receipt Header -->
                <div class="border-b border-dashed border-slate-200 bg-slate-50/70 px-5 py-5 text-center">
                    <div class="text-[11px] font-extrabold uppercase tracking-widest text-kopdes-700">
                        {{ appSettings.institution_name }}
                    </div>
                    <div class="mt-1 text-base font-extrabold text-slate-900">
                        {{ transaction.branch?.name ?? '-' }}
                    </div>
                    <div v-if="transaction.branch?.address" class="mt-0.5 text-[11px] text-slate-500">
                        {{ transaction.branch.address }}
                    </div>
                    <div class="kopdes-num mt-3 inline-block rounded-lg bg-white px-3 py-1 text-xs font-extrabold text-slate-800 shadow-sm ring-1 ring-slate-200">
                        {{ transaction.invoice_number }}
                    </div>
                    <div v-if="returnStatusLabel" class="mt-2.5">
                        <StatusBadge :variant="transaction.return_status === 'full' ? 'rose' : 'amber'" size="xs">
                            {{ returnStatusLabel }}
                        </StatusBadge>
                    </div>
                </div>

                <!-- Metadata -->
                <div class="grid grid-cols-2 gap-3 border-b border-dashed border-slate-200 px-5 py-3.5 text-xs">
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Waktu Bayar</div>
                        <div class="kopdes-num mt-0.5 font-semibold text-slate-800">{{ formatDateTime(transaction.paid_at) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Kasir</div>
                        <div class="mt-0.5 font-semibold text-slate-800">{{ transaction.cashier_name }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Metode Bayar</div>
                        <div class="mt-0.5 font-semibold text-slate-800">
                            {{ transaction.payment_method === 'cash' ? 'Tunai' : `Midtrans (${transaction.midtrans_payment_type || 'Digital'})` }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Status</div>
                        <div class="mt-0.5">
                            <StatusBadge variant="emerald" size="xs" dot>LUNAS</StatusBadge>
                        </div>
                    </div>
                </div>

                <!-- Item List -->
                <div class="divide-y divide-slate-100 px-5 py-3">
                    <div
                        v-for="item in transaction.items"
                        :key="item.id"
                        class="py-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-900">
                                    {{ item.product_name_snapshot }}
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                                    <span class="kopdes-num">{{ item.qty }} {{ item.unit_snapshot }} &times;</span>
                                    <template v-if="item.is_member_price_applied && item.regular_price_snapshot > item.unit_price_snapshot">
                                        <span class="kopdes-num text-slate-400 line-through">
                                            {{ formatRp(item.regular_price_snapshot) }}
                                        </span>
                                        <span class="kopdes-num font-bold text-emerald-700">
                                            {{ formatRp(item.unit_price_snapshot) }}
                                        </span>
                                        <StatusBadge variant="emerald" size="xs">
                                            Harga Member
                                        </StatusBadge>
                                    </template>
                                    <span v-else class="kopdes-num">
                                        {{ formatRp(item.unit_price_snapshot) }}
                                    </span>
                                </div>
                            </div>
                            <div class="kopdes-num shrink-0 text-xs font-extrabold text-slate-900">
                                {{ formatRp(item.subtotal) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Totals -->
                <div class="space-y-2 border-t border-dashed border-slate-200 bg-slate-50/70 px-5 py-4 text-xs">
                    <div
                        v-if="transaction.member_discount_total > 0"
                        class="flex items-center justify-between text-slate-600"
                    >
                        <span>Subtotal Harga Reguler</span>
                        <span class="kopdes-num">{{ formatRp(transaction.regular_subtotal) }}</span>
                    </div>
                    <div
                        v-if="transaction.member_discount_total > 0"
                        class="flex items-center justify-between rounded-lg bg-emerald-50 px-2.5 py-1.5 font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200/80"
                    >
                        <span>Total Hemat Harga Member</span>
                        <span class="kopdes-num">&minus;{{ formatRp(transaction.member_discount_total) }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-1 text-sm font-extrabold text-slate-900">
                        <span>Total Bayar</span>
                        <span class="kopdes-num text-base text-kopdes-700">{{ formatRp(transaction.total_amount) }}</span>
                    </div>
                    <template v-if="transaction.payment_method === 'cash' && transaction.cash_received !== null">
                        <div class="flex items-center justify-between border-t border-slate-200/70 pt-2 text-[11px] text-slate-500">
                            <span>Tunai Diterima</span>
                            <span class="kopdes-num">{{ formatRp(transaction.cash_received) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500">
                            <span>Kembalian</span>
                            <span class="kopdes-num">{{ formatRp(transaction.change_amount) }}</span>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="border-t border-slate-100 px-5 py-3.5 text-center text-[11px] text-slate-500">
                    {{ appSettings.receipt_footer }}
                </div>
            </article>

            <!-- Return History (if any) -->
            <section
                v-if="transaction.returns && transaction.returns.length > 0"
                class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4"
            >
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-rose-800">
                    Riwayat Retur / Pembatalan Transaksi
                </h2>
                <div class="mt-3 space-y-3">
                    <div
                        v-for="ret in transaction.returns"
                        :key="ret.id"
                        class="rounded-xl bg-white p-3.5 shadow-sm ring-1 ring-rose-100"
                    >
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="kopdes-num font-bold text-rose-800">{{ ret.return_number }}</span>
                            <span class="kopdes-num text-[11px] text-slate-500">{{ formatDateTime(ret.returned_at) }}</span>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-600">
                            Alasan: {{ ret.reason }}
                        </p>
                        <div class="mt-2 divide-y divide-slate-100 border-t border-slate-100 pt-1.5 text-xs">
                            <div
                                v-for="ri in ret.items"
                                :key="ri.id"
                                class="flex items-center justify-between py-1"
                            >
                                <span class="text-slate-700">
                                    {{ ri.product_name_snapshot }} (&times;{{ ri.qty_returned }})
                                </span>
                                <span class="kopdes-num font-bold text-rose-700">
                                    &minus;{{ formatRp(ri.subtotal_refund) }}
                                </span>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t border-rose-100 pt-2 text-xs font-extrabold text-rose-800">
                            <span>Total Refund ({{ ret.refund_method === 'cash_refund' ? 'Tunai' : 'Transfer' }})</span>
                            <span class="kopdes-num">&minus;{{ formatRp(ret.total_refund_amount) }}</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </MemberLayout>
</template>
