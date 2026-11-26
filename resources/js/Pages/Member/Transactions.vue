<script setup>
import MemberLayout from '@/Layouts/MemberLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    transactions: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ scope: 'all' }),
    },
});

const scopes = [
    { value: 'all', label: 'Semua' },
    { value: 'home', label: 'KOPDES Asal' },
    { value: 'cross', label: 'Lintas Cabang' },
];

const setScope = (scope) => {
    router.get(
        route('member.transactions.index'),
        { scope: scope === 'all' ? undefined : scope },
        { preserveState: true, replace: true },
    );
};

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

const returnBadgeLabel = (status) => {
    if (status === 'full') return 'Dibatalkan Penuh';
    if (status === 'partial') return 'Sebagian Diretur';
    return null;
};
</script>

<template>
    <Head title="Riwayat Belanja Member" />

    <MemberLayout>
        <div class="space-y-4">
            <!-- Header & Scope Filter -->
            <div>
                <h1 class="text-lg font-extrabold tracking-tight text-slate-900">
                    Riwayat Belanja
                </h1>
                <p class="mt-0.5 text-xs text-slate-500">
                    Seluruh transaksi lunas Anda di KOPDES asal maupun cabang desa lain
                </p>
            </div>

            <div class="grid grid-cols-3 gap-1 rounded-xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200/80">
                <button
                    v-for="item in scopes"
                    :key="item.value"
                    type="button"
                    @click="setScope(item.value)"
                    class="rounded-lg px-2 py-1.5 text-xs font-bold transition"
                    :class="
                        filters.scope === item.value
                            ? 'bg-kopdes-700 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    "
                >
                    {{ item.label }}
                </button>
            </div>

            <!-- Transaction List -->
            <div v-if="transactions.data.length > 0" class="space-y-2.5">
                <Link
                    v-for="tx in transactions.data"
                    :key="tx.id"
                    :href="route('member.transactions.show', tx.id)"
                    class="block rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/80 transition hover:ring-kopdes-300"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="kopdes-num text-xs font-extrabold text-slate-900">
                                    {{ tx.invoice_number }}
                                </span>
                                <span
                                    v-if="tx.is_cross_branch"
                                    class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700"
                                >
                                    Lintas Cabang
                                </span>
                                <span
                                    v-else
                                    class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600"
                                >
                                    KOPDES Asal
                                </span>
                                <span
                                    v-if="returnBadgeLabel(tx.return_status)"
                                    class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700"
                                >
                                    {{ returnBadgeLabel(tx.return_status) }}
                                </span>
                            </div>

                            <div class="mt-1 text-xs font-semibold text-slate-700">
                                {{ tx.branch_name }}
                            </div>
                            <div class="mt-0.5 text-[11px] text-slate-500">
                                {{ formatDateTime(tx.paid_at) }} ·
                                {{ tx.payment_method === 'cash' ? 'Tunai' : 'Non-Tunai (Midtrans)' }} ·
                                {{ tx.items_count }} item
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <div class="kopdes-num text-sm font-extrabold text-slate-900">
                                {{ formatRp(tx.total_amount) }}
                            </div>
                            <div
                                v-if="tx.member_discount_total > 0"
                                class="kopdes-num mt-0.5 text-[11px] font-bold text-emerald-600"
                            >
                                Hemat {{ formatRp(tx.member_discount_total) }}
                            </div>
                            <div class="mt-1 text-[10px] font-semibold text-kopdes-700">
                                Lihat Struk &rarr;
                            </div>
                        </div>
                    </div>
                </Link>
            </div>

            <div
                v-else
                class="rounded-xl bg-white px-4 py-10 text-center text-xs text-slate-500 shadow-sm ring-1 ring-slate-200/80"
            >
                Belum ada transaksi pada filter lokasi ini.
            </div>

            <!-- Pagination -->
            <div
                v-if="transactions.last_page > 1"
                class="flex items-center justify-between pt-2 text-xs"
            >
                <Link
                    v-if="transactions.prev_page_url"
                    :href="transactions.prev_page_url"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                >
                    &larr; Sebelumnya
                </Link>
                <span v-else class="text-slate-400">&larr; Sebelumnya</span>

                <span class="kopdes-num font-semibold text-slate-600">
                    Hal {{ transactions.current_page }} / {{ transactions.last_page }}
                </span>

                <Link
                    v-if="transactions.next_page_url"
                    :href="transactions.next_page_url"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                >
                    Berikutnya &rarr;
                </Link>
                <span v-else class="text-slate-400">Berikutnya &rarr;</span>
            </div>
        </div>
    </MemberLayout>
</template>
