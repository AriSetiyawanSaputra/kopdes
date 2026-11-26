<script setup>
import { ref } from 'vue';

const emit = defineEmits(['scan-barcode', 'tap-member']);

const isOpen = ref(false);
const loading = ref(false);
const loaded = ref(false);
const errorMsg = ref('');
const sampleProducts = ref([]);
const sampleMembers = ref([]);

const formatRp = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(val || 0));
};

const fetchSamples = async () => {
    loading.value = true;
    errorMsg.value = '';
    try {
        const res = await window.axios.get(route('demo.pos-samples'));
        sampleProducts.value = res.data.products ?? [];
        sampleMembers.value = res.data.members ?? [];
        loaded.value = true;
    } catch {
        errorMsg.value = 'Gagal memuat sampel simulator hardware.';
    } finally {
        loading.value = false;
    }
};

const togglePopover = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value && !loaded.value) {
        fetchSamples();
    }
};

const triggerScan = (barcode) => {
    emit('scan-barcode', barcode);
};

const triggerTapMember = (cardUid) => {
    emit('tap-member', cardUid);
};
</script>

<template>
    <div class="no-print fixed bottom-5 right-5 z-40" @click.stop>
        <!-- Popover Panel -->
        <div
            v-if="isOpen"
            class="mb-3 w-80 overflow-hidden rounded-2xl border border-amber-300 bg-white shadow-2xl sm:w-96"
        >
            <div class="flex items-center justify-between border-b border-amber-200 bg-amber-50 px-4 py-3">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wider text-amber-900">
                        Simulator Hardware POS
                    </div>
                    <div class="text-[10px] text-amber-800">
                        Klik untuk simulasi USB Barcode Scanner &amp; USB RFID Reader
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        @click="fetchSamples"
                        class="rounded-md border border-amber-300 bg-white px-2 py-1 text-[10px] font-bold text-amber-900 hover:bg-amber-100"
                        title="Muat ulang daftar sampel"
                    >
                        Muat Ulang
                    </button>
                    <button
                        type="button"
                        @click="isOpen = false"
                        class="rounded-md p-1 text-amber-900 hover:bg-amber-200/70"
                        aria-label="Tutup simulator"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="max-h-[65vh] space-y-4 overflow-y-auto p-3.5">
                <div v-if="loading" class="py-6 text-center text-xs text-slate-500">
                    Memuat sampel produk &amp; kartu member...
                </div>

                <div
                    v-else-if="errorMsg"
                    class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700"
                >
                    {{ errorMsg }}
                </div>

                <template v-else>
                    <!-- Simulasi Tap Kartu Member RFID -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-700">
                                1. Simulasi Tap Kartu RFID Member
                            </span>
                            <span class="text-[10px] font-medium text-slate-400">
                                {{ sampleMembers.length }} kartu
                            </span>
                        </div>
                        <div v-if="sampleMembers.length > 0" class="space-y-1.5">
                            <button
                                v-for="m in sampleMembers"
                                :key="m.id"
                                type="button"
                                @click="triggerTapMember(m.card_uid)"
                                class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-left transition hover:border-emerald-400 hover:bg-emerald-50"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate text-xs font-bold text-slate-900">{{ m.name }}</span>
                                        <span
                                            v-if="m.is_cross_branch"
                                            class="shrink-0 rounded bg-blue-100 px-1.5 py-0.2 text-[9px] font-bold text-blue-700"
                                        >
                                            Lintas Cabang
                                        </span>
                                        <span
                                            v-else
                                            class="shrink-0 rounded bg-emerald-100 px-1.5 py-0.2 text-[9px] font-bold text-emerald-700"
                                        >
                                            Asal
                                        </span>
                                    </div>
                                    <div class="kopdes-num truncate text-[10px] text-slate-500">
                                        UID: {{ m.card_uid }} · {{ m.home_branch_name }}
                                    </div>
                                </div>
                                <span class="shrink-0 rounded bg-emerald-600 px-2 py-1 text-[10px] font-bold text-white">
                                    Tap
                                </span>
                            </button>
                        </div>
                        <div v-else class="text-xs text-slate-400">
                            Belum ada member aktif dengan kartu RFID.
                        </div>
                    </div>

                    <!-- Simulasi Scan Barcode Produk -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-700">
                                2. Simulasi Scan Barcode Produk
                            </span>
                            <span class="text-[10px] font-medium text-slate-400">
                                {{ sampleProducts.length }} produk
                            </span>
                        </div>
                        <div v-if="sampleProducts.length > 0" class="grid grid-cols-1 gap-1.5">
                            <button
                                v-for="p in sampleProducts"
                                :key="p.id"
                                type="button"
                                @click="triggerScan(p.barcode)"
                                class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left transition hover:border-kopdes-400 hover:bg-kopdes-50/40"
                            >
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-bold text-slate-900">
                                        {{ p.name }}
                                    </div>
                                    <div class="kopdes-num flex flex-wrap items-center gap-1.5 text-[10px] text-slate-500">
                                        <span>{{ p.barcode }}</span>
                                        <span>·</span>
                                        <span>{{ formatRp(p.selling_price) }}</span>
                                        <span
                                            v-if="p.member_price !== null"
                                            class="rounded bg-emerald-100 px-1 text-[9px] font-bold text-emerald-700"
                                        >
                                            Member {{ formatRp(p.member_price) }}
                                        </span>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded bg-kopdes-700 px-2 py-1 text-[10px] font-bold text-white">
                                    Scan
                                </span>
                            </button>
                        </div>
                        <div v-else class="text-xs text-slate-400">
                            Tidak ada produk berstok pada cabang ini.
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Floating Trigger Button -->
        <div class="flex justify-end">
            <button
                type="button"
                @click="togglePopover"
                class="inline-flex items-center gap-2 rounded-full border-2 border-amber-300 bg-amber-400 px-4 py-2.5 text-xs font-extrabold text-slate-950 shadow-lg transition hover:bg-amber-300"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
                <span>Simulator Hardware</span>
            </button>
        </div>
    </div>
</template>
