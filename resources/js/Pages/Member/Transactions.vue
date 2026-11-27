<script setup>
import MemberLayout from '@/Layouts/MemberLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
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
        <div class="space-y-5">
            <!-- Header & Scope Filter -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900">
                        Riwayat Belanja &amp; Struk Digital
                    </h1>
                    <p class="mt-0.5 text-xs text-slate-600">
                        Seluruh transaksi lunas Anda di KOPDES asal maupun cabang desa lain
                    </p>
                </div>

                <div
                    role="group"
                    aria-label="Filter Lokasi Belanja"
                    class="grid grid-cols-3 gap-1 rounded-xl border border-slate-200/90 bg-white p-1 shadow-sm sm:min-w-[290px]"
                >
                    <button
                        v-for="item in scopes"
                        :key="item.value"
                        type="button"
                        @click="setScope(item.value)"
                        class="cursor-pointer rounded-lg px-2.5 py-1.5 text-xs font-bold transition"
                        :class="
                            filters.scope === item.value
                                ? 'bg-kopdes-700 text-white shadow-sm'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                        "
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>

            <!-- Transaction Card List (Zero Horizontal Scroll on Mobile, 2-Col Grid on Desktop) -->
            <div v-if="transactions.data.length > 0" class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                <Link
                    v-for="tx in transactions.data"
                    :key="tx.id"
                    :href="route('member.transactions.show', tx.id)"
                    class="kopdes-card group block p-4 transition-all hover:border-kopdes-300 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="truncate text-sm font-extrabold text-slate-900 group-hover:text-kopdes-700">
                                    {{ tx.branch_name }}
                                </span>
                                <StatusBadge v-if="tx.is_cross_branch" variant="indigo" size="xs">
                                    Lintas Cabang
                                </StatusBadge>
                                <StatusBadge v-else variant="slate" size="xs">
                                    KOPDES Asal
                                </StatusBadge>
                                <StatusBadge
                                    v-if="returnBadgeLabel(tx.return_status)"
                                    :variant="tx.return_status === 'full' ? 'rose' : 'amber'"
                                    size="xs"
                                >
                                    {{ returnBadgeLabel(tx.return_status) }}
                                </StatusBadge>
                            </div>

                            <div class="kopdes-num mt-1.5 text-xs font-bold text-slate-600">
                                {{ tx.invoice_number }}
                            </div>

                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                                <span>{{ formatDateTime(tx.paid_at) }}</span>
                                <span>&middot;</span>
                                <span
                                    class="font-semibold"
                                    :class="tx.payment_method === 'cash' ? 'text-emerald-700' : 'text-indigo-700'"
                                >
                                    {{ tx.payment_method === 'cash' ? 'Tunai' : 'Midtrans' }}
                                </span>
                                <span>&middot;</span>
                                <span class="kopdes-num">{{ tx.items_count }} item</span>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <div class="kopdes-num text-sm font-extrabold text-slate-900">
                                {{ formatRp(tx.total_amount) }}
                            </div>
                            <div
                                v-if="tx.member_discount_total > 0"
                                class="kopdes-num mt-0.5 text-[11px] font-bold text-emerald-700"
                            >
                                Hemat {{ formatRp(tx.member_discount_total) }}
                            </div>
                            <div class="mt-2 inline-flex items-center gap-0.5 text-[11px] font-bold text-kopdes-700 group-hover:underline">
                                <span>Lihat Struk</span>
                                <span aria-hidden="true">&rarr;</span>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>

            <div v-else class="kopdes-card">
                <EmptyState
                    title="Belum ada transaksi di kategori ini"
                    description="Transaksi lunas Anda di KOPDES asal maupun lintas cabang akan tercatat otomatis di sini."
                />
            </div>

            <!-- Mobile-Friendly Pagination -->
            <div
                v-if="transactions.last_page > 1"
                class="flex items-center justify-between gap-2 pt-1 text-xs"
            >
                <Link
                    v-if="transactions.prev_page_url"
                    :href="transactions.prev_page_url"
                    preserve-scroll
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    &larr; Sebelumnya
                </Link>
                <span v-else class="rounded-xl border border-slate-200 bg-slate-100 px-3.5 py-2 text-slate-400">
                    &larr; Sebelumnya
                </span>

                <span class="kopdes-num font-bold text-slate-600">
                    Hal {{ transactions.current_page }} / {{ transactions.last_page }}
                </span>

                <Link
                    v-if="transactions.next_page_url"
                    :href="transactions.next_page_url"
                    preserve-scroll
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    Berikutnya &rarr;
                </Link>
                <span v-else class="rounded-xl border border-slate-200 bg-slate-100 px-3.5 py-2 text-slate-400">
                    Berikutnya &rarr;
                </span>
            </div>
        </div>
    </MemberLayout>
</template>
