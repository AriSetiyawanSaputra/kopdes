<script setup>
import { computed } from 'vue';

const props = defineProps({
    shift: {
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

const receiptBranch = computed(() => props.shift?.branch ?? props.branch ?? {});

const widthClass = computed(() =>
    props.paperWidth === '80mm' ? 'thermal-80mm' : 'thermal-58mm',
);

const formatNumeric = (value) => {
    const num = Number(value || 0);
    const sign = num < 0 ? '-' : '';
    return (
        sign +
        new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(Math.abs(num))
    );
};
</script>

<template>
    <div
        v-if="shift"
        id="kopdes-thermal-receipt"
        class="thermal-receipt-container font-mono text-black"
        :class="widthClass"
    >
        <div class="text-center">
            <div class="text-sm font-bold uppercase leading-tight">
                {{ receiptBranch.name || 'KOPDES MERAH PUTIH' }}
            </div>
            <div class="text-[11px] font-bold">
                STRUK RINGKASAN TUTUP SHIFT
            </div>
            <div class="text-[10px]">
                Kode: {{ receiptBranch.code || '-' }}
            </div>
        </div>

        <div class="my-1.5 border-t border-dashed border-black"></div>

        <div class="space-y-0.5 text-[11px] leading-snug">
            <div class="flex justify-between">
                <span>Shift:</span>
                <span class="font-bold">{{ shift.shift_code }}</span>
            </div>
            <div class="flex justify-between">
                <span>Kasir:</span>
                <span>{{ shift.cashier?.name || '-' }}</span>
            </div>
            <div class="flex justify-between">
                <span>Buka:</span>
                <span>{{ shift.opened_at }}</span>
            </div>
            <div class="flex justify-between">
                <span>Tutup:</span>
                <span>{{ shift.closed_at }}</span>
            </div>
            <div v-if="shift.is_force_closed" class="flex justify-between font-bold">
                <span>Tipe:</span>
                <span>FORCE-CLOSE ADMIN</span>
            </div>
        </div>

        <div class="my-1.5 border-t border-dashed border-black"></div>

        <div class="space-y-0.5 text-[11px]">
            <div class="flex justify-between">
                <span>Modal Awal</span>
                <span>Rp{{ formatNumeric(shift.opening_cash) }}</span>
            </div>
            <div class="flex justify-between">
                <span>Jual Tunai</span>
                <span>Rp{{ formatNumeric(shift.cash_sales_total) }}</span>
            </div>
            <div class="flex justify-between">
                <span>Kas Masuk</span>
                <span>Rp{{ formatNumeric(shift.cash_in_total) }}</span>
            </div>
            <div class="flex justify-between">
                <span>Kas Keluar</span>
                <span>-Rp{{ formatNumeric(shift.cash_out_total) }}</span>
            </div>
            <div v-if="Number(shift.cash_refund_total || 0) > 0" class="flex justify-between">
                <span>Refund Laci</span>
                <span>-Rp{{ formatNumeric(shift.cash_refund_total) }}</span>
            </div>
        </div>

        <div class="my-1.5 border-t border-dashed border-black"></div>

        <div class="space-y-0.5 text-[11px]">
            <div class="flex justify-between font-bold">
                <span>Ekspektasi</span>
                <span>Rp{{ formatNumeric(shift.expected_closing_cash) }}</span>
            </div>
            <div class="flex justify-between font-bold">
                <span>Fisik Aktual</span>
                <span>Rp{{ formatNumeric(shift.actual_closing_cash) }}</span>
            </div>
            <div class="flex justify-between text-xs font-extrabold">
                <span>SELISIH</span>
                <span>Rp{{ formatNumeric(shift.variance) }}</span>
            </div>
            <div class="flex justify-between text-[10px]">
                <span>Jual Midtrans</span>
                <span>Rp{{ formatNumeric(shift.midtrans_sales_total) }}</span>
            </div>
        </div>

        <div v-if="shift.closing_notes" class="mt-1.5 border-t border-dashed border-black pt-1 text-[10px]">
            Catatan: {{ shift.closing_notes }}
        </div>

        <div class="my-1.5 border-t border-dashed border-black"></div>

        <div class="text-center text-[10px]">
            <div>Arsip Rekonsiliasi Kasir KOPDES</div>
        </div>
    </div>
</template>
