<script setup>
import { ref } from 'vue';
import StatusBadge from '@/Components/StatusBadge.vue';

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

const toggleBar = () => {
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
    <div
        class="no-print print:hidden overflow-hidden rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50/90 via-amber-50/60 to-white shadow-card"
        @click.stop
    >
        <!-- Compact Collapsible Trigger Header -->
        <div class="flex items-center justify-between gap-2 px-3.5 py-2.5">
            <button
                type="button"
                @click="toggleBar"
                class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 text-left focus:outline-none"
            >
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-800 ring-1 ring-amber-200/80">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="truncate text-xs font-extrabold text-amber-950">
                            Simulator Hardware Demo
                        </span>
                        <StatusBadge variant="amber" size="xs">Barcode &amp; RFID</StatusBadge>
                    </div>
                    <p class="truncate text-[11px] text-amber-800/80">
                        Klik untuk simulasi scan barcode produk &amp; tap kartu RFID member tanpa alat fisik
                    </p>
                </div>
            </button>

            <div class="flex shrink-0 items-center gap-1.5">
                <button
                    v-if="isOpen"
                    type="button"
                    @click="fetchSamples"
                    class="cursor-pointer rounded-lg border border-amber-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-amber-900 transition-colors hover:bg-amber-100"
                    title="Muat ulang sampel"
                >
                    Muat Ulang
                </button>
                <button
                    type="button"
                    @click="toggleBar"
                    :aria-expanded="isOpen"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-amber-200 bg-white px-2.5 py-1 text-[11px] font-bold text-amber-900 transition-colors hover:bg-amber-100"
                >
                    <span>{{ isOpen ? 'Tutup' : 'Buka' }}</span>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5 transition-transform duration-200"
                        :class="isOpen ? 'rotate-180' : ''"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Expanded Simulation Chips -->
        <div
            v-if="isOpen"
            class="border-t border-amber-200/80 bg-white/95 px-3.5 py-3"
        >
            <div v-if="loading" class="py-3 text-center text-xs text-slate-500">
                Memuat sampel produk &amp; kartu member...
            </div>

            <div
                v-else-if="errorMsg"
                class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700"
            >
                {{ errorMsg }}
            </div>

            <div v-else class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                <!-- 1. Simulasi Tap Kartu Member RFID -->
                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600">
                            Simulasi Tap Kartu Member RFID
                        </span>
                        <span class="kopdes-num text-[10px] text-slate-400">
                            {{ sampleMembers.length }} kartu
                        </span>
                    </div>
                    <div v-if="sampleMembers.length > 0" class="flex flex-wrap gap-1.5">
                        <button
                            v-for="m in sampleMembers"
                            :key="m.id"
                            type="button"
                            @click="triggerTapMember(m.card_uid)"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-left text-xs transition-colors hover:border-emerald-300 hover:bg-emerald-50"
                        >
                            <span class="font-bold text-slate-800">{{ m.name }}</span>
                            <span
                                class="rounded-md px-1.5 py-0.5 text-[9px] font-bold"
                                :class="
                                    m.is_cross_branch
                                        ? 'bg-indigo-100 text-indigo-700'
                                        : 'bg-emerald-100 text-emerald-700'
                                "
                            >
                                {{ m.is_cross_branch ? 'Lintas Desa' : 'Asal' }}
                            </span>
                            <span class="kopdes-num text-[10px] text-slate-500">{{ m.card_uid }}</span>
                        </button>
                    </div>
                    <div v-else class="text-xs text-slate-400">
                        Belum ada member aktif dengan kartu RFID.
                    </div>
                </div>

                <!-- 2. Simulasi Scan Barcode Produk -->
                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600">
                            Simulasi Scan Barcode Produk
                        </span>
                        <span class="kopdes-num text-[10px] text-slate-400">
                            {{ sampleProducts.length }} produk
                        </span>
                    </div>
                    <div v-if="sampleProducts.length > 0" class="flex flex-wrap gap-1.5">
                        <button
                            v-for="p in sampleProducts.slice(0, 6)"
                            :key="p.id"
                            type="button"
                            @click="triggerScan(p.barcode)"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-left text-xs transition-colors hover:border-kopdes-300 hover:bg-kopdes-50/60"
                        >
                            <span class="max-w-[140px] truncate font-bold text-slate-800">{{ p.name }}</span>
                            <span class="kopdes-num text-[10px] font-semibold text-kopdes-700">
                                {{ formatRp(p.selling_price) }}
                            </span>
                        </button>
                    </div>
                    <div v-else class="text-xs text-slate-400">
                        Tidak ada produk berstok pada cabang ini.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
