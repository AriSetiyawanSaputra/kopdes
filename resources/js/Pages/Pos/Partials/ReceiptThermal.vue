<script setup>
import { computed } from 'vue';

const props = defineProps({
    transaction: {
        type: Object,
        default: null,
    },
    branch: {
        type: Object,
        default: null,
    },
    paperWidth: {
        type: String,
        default: '58mm',
    },
});

const receiptBranch = computed(() => {
    return props.transaction?.branch ?? props.branch ?? {};
});

const widthClass = computed(() => {
    return props.paperWidth === '80mm' ? 'thermal-80mm' : 'thermal-58mm';
});

const formatNumeric = (value) => {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
};
</script>

<template>
    <div
        v-if="transaction"
        id="kopdes-thermal-receipt"
        class="thermal-receipt-container font-mono text-black"
        :class="widthClass"
    >
        <!-- Header Cabang KOPDES -->
        <div class="text-center">
            <div class="text-sm font-bold uppercase leading-tight">
                {{ receiptBranch.name || 'KOPDES MERAH PUTIH' }}
            </div>
            <div class="text-[11px] leading-tight">
                Kode: {{ receiptBranch.code || '-' }}
            </div>
            <div class="mt-0.5 text-[11px] leading-tight">
                {{ receiptBranch.address || '' }}
            </div>
            <div v-if="receiptBranch.phone" class="text-[11px] leading-tight">
                Telp: {{ receiptBranch.phone }}
            </div>
        </div>

        <div class="my-1.5 border-t border-dashed border-black"></div>

        <!-- Metadata Transaksi & Member Lintas Cabang -->
        <div class="space-y-0.5 text-[11px] leading-snug">
            <div class="flex justify-between">
                <span>No:</span>
                <span class="font-bold">{{ transaction.invoice_number }}</span>
            </div>
            <div class="flex justify-between">
                <span>Tgl:</span>
                <span>{{ transaction.paid_at || transaction.created_at }}</span>
            </div>
            <div class="flex justify-between">
                <span>Kasir:</span>
                <span>{{ transaction.cashier?.name || '-' }}</span>
            </div>
            <div class="flex justify-between">
                <span>Pembeli:</span>
                <span class="font-bold">
                    {{ transaction.member ? transaction.member.name : 'Pembeli Umum' }}
                </span>
            </div>
            <div v-if="transaction.member" class="flex justify-between">
                <span>KOPDES Asal:</span>
                <span>{{ transaction.member.home_branch_name || '-' }}</span>
            </div>
        </div>

        <div class="my-1.5 border-t border-dashed border-black"></div>

        <!-- Daftar Item Belanja (Snapshot) -->
        <div class="space-y-1 text-[11px]">
            <div
                v-for="item in transaction.items"
                :key="item.id || item.product_id"
                class="leading-tight"
            >
                <div class="font-semibold">
                    {{ item.product_name_snapshot }}
                </div>
                <div class="flex justify-between">
                    <span>
                        {{ item.qty }} {{ item.unit_snapshot }} x Rp{{ formatNumeric(item.unit_price_snapshot) }}
                    </span>
                    <span class="font-bold">
                        Rp{{ formatNumeric(item.subtotal) }}
                    </span>
                </div>
                <div
                    v-if="item.is_member_price_applied && Number(item.member_discount_snapshot || 0) > 0"
                    class="flex justify-between text-[10px]"
                >
                    <span>Harga Member (Normal Rp{{ formatNumeric(item.regular_price_snapshot) }})</span>
                    <span>-Rp{{ formatNumeric(item.member_discount_snapshot) }}</span>
                </div>
            </div>
        </div>

        <div class="my-1.5 border-t border-dashed border-black"></div>

        <!-- Ringkasan Pembayaran -->
        <div class="space-y-0.5 text-[11px]">
            <template v-if="Number(transaction.member_discount_total || 0) > 0">
                <div class="flex justify-between">
                    <span>Subtotal Normal</span>
                    <span>Rp{{ formatNumeric(transaction.regular_subtotal) }}</span>
                </div>
                <div class="flex justify-between font-bold">
                    <span>Total Hemat Member</span>
                    <span>-Rp{{ formatNumeric(transaction.member_discount_total) }}</span>
                </div>
            </template>
            <div class="flex justify-between text-xs font-extrabold">
                <span>TOTAL</span>
                <span>Rp{{ formatNumeric(transaction.total_amount) }}</span>
            </div>
            <div class="flex justify-between">
                <span>Metode Bayar</span>
                <span class="uppercase font-semibold">
                    {{
                        transaction.payment_method === 'cash'
                            ? 'TUNAI'
                            : `MIDTRANS (${transaction.midtrans_payment_type || 'NON-TUNAI'})`
                    }}
                </span>
            </div>
            <template v-if="transaction.payment_method === 'cash'">
                <div class="flex justify-between">
                    <span>Tunai Diterima</span>
                    <span>Rp{{ formatNumeric(transaction.cash_received) }}</span>
                </div>
                <div class="flex justify-between font-bold">
                    <span>Kembalian</span>
                    <span>Rp{{ formatNumeric(transaction.change_amount) }}</span>
                </div>
            </template>
        </div>

        <div class="my-1.5 border-t border-dashed border-black"></div>

        <!-- Footer Koperasi -->
        <div class="text-center text-[10px] leading-tight">
            <div class="font-bold">TERIMA KASIH ATAS KUNJUNGAN ANDA</div>
            <div>Koperasi Desa Merah Putih</div>
            <div>Bersama Membangun Ekonomi Desa</div>
        </div>
    </div>
</template>

<style>
.thermal-receipt-container {
    display: none;
}

.thermal-58mm {
    width: 58mm;
    max-width: 58mm;
}

.thermal-80mm {
    width: 80mm;
    max-width: 80mm;
}

@media print {
    @page {
        margin: 0;
    }

    body * {
        visibility: hidden !important;
    }

    .no-print {
        display: none !important;
    }

    #kopdes-thermal-receipt,
    #kopdes-thermal-receipt * {
        visibility: visible !important;
    }

    #kopdes-thermal-receipt {
        display: block !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        padding: 2mm 3mm !important;
        background: #ffffff !important;
        color: #000000 !important;
    }
}
</style>
