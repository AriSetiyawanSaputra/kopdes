<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import JsBarcode from 'jsbarcode';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    isCashier: {
        type: Boolean,
        default: false,
    },
});

const MAX_TOTAL_LABELS = 200;

const page = usePage();
const institutionName = computed(
    () => page.props.app_settings?.institution_name || 'Koperasi Desa Merah Putih',
);

const search = ref('');
const categoryId = ref('');
const layoutMode = ref('a4'); // 'a4' | 'thermal'
const showMemberPrice = ref(true);
const selectedItems = ref([]); // [{ product, copies }]
const barcodeSvgRefs = ref({});

const filteredProducts = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.products.filter((p) => {
        if (categoryId.value && String(p.category_id) !== String(categoryId.value)) {
            return false;
        }
        if (!q) {
            return true;
        }
        return (
            p.name.toLowerCase().includes(q) ||
            p.sku.toLowerCase().includes(q) ||
            p.barcode.toLowerCase().includes(q)
        );
    });
});

const totalLabels = computed(() =>
    selectedItems.value.reduce((sum, entry) => sum + Math.max(0, Number(entry.copies || 0)), 0),
);

const isOverLimit = computed(() => totalLabels.value > MAX_TOTAL_LABELS);

const expandedLabels = computed(() => {
    const list = [];
    let seq = 0;
    for (const entry of selectedItems.value) {
        const count = Math.max(0, Math.min(MAX_TOTAL_LABELS, Number(entry.copies || 0)));
        for (let i = 0; i < count; i++) {
            if (list.length >= MAX_TOTAL_LABELS) {
                break;
            }
            list.push({
                uid: `${entry.product.id}-${seq++}`,
                product: entry.product,
            });
        }
    }
    return list;
});

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));

const addProduct = (product) => {
    const existing = selectedItems.value.find((e) => e.product.id === product.id);
    if (existing) {
        if (totalLabels.value < MAX_TOTAL_LABELS) {
            existing.copies += 1;
        }
        return;
    }

    if (totalLabels.value >= MAX_TOTAL_LABELS) {
        return;
    }

    selectedItems.value.push({
        product,
        copies: 3,
    });
};

const removeProduct = (productId) => {
    selectedItems.value = selectedItems.value.filter((e) => e.product.id !== productId);
};

const clearAll = () => {
    selectedItems.value = [];
};

const setSvgRef = (uid, el) => {
    if (el) {
        barcodeSvgRefs.value[uid] = el;
    } else {
        delete barcodeSvgRefs.value[uid];
    }
};

const renderBarcodes = () => {
    nextTick(() => {
        for (const label of expandedLabels.value) {
            const svgEl = barcodeSvgRefs.value[label.uid];
            if (!svgEl) {
                continue;
            }
            const code = String(label.product.barcode || label.product.sku || '').trim();
            if (!code) {
                continue;
            }

            const baseOpts = {
                width: layoutMode.value === 'thermal' ? 1.3 : 1.5,
                height: layoutMode.value === 'thermal' ? 32 : 40,
                displayValue: true,
                fontSize: 11,
                margin: 2,
                background: '#ffffff',
                lineColor: '#000000',
            };

            // Auto-pilih EAN13 untuk 13 digit numerik valid; fallback otomatis ke CODE128
            if (/^\d{13}$/.test(code)) {
                try {
                    JsBarcode(svgEl, code, { ...baseOpts, format: 'EAN13' });
                    continue;
                } catch {
                    // Checksum EAN-13 tidak valid -> fallback ke CODE128
                }
            }

            try {
                JsBarcode(svgEl, code, { ...baseOpts, format: 'CODE128' });
            } catch {
                // Ignore unrenderable barcode safely
            }
        }
    });
};

watch([expandedLabels, layoutMode, showMemberPrice], renderBarcodes, { deep: true });
onMounted(renderBarcodes);

const triggerPrint = () => {
    if (expandedLabels.value.length === 0 || isOverLimit.value) {
        return;
    }
    window.print();
};
</script>

<template>
    <Head title="Cetak Stiker Label Barcode Produk KOPDES" />

    <component :is="isCashier ? 'div' : AuthenticatedLayout" :class="isCashier ? 'min-h-screen bg-slate-50' : ''">
        <!-- Compact Header khusus Kasir -->
        <header v-if="isCashier" class="no-print border-b border-kopdes-900 bg-kopdes-800 px-4 py-3 text-white shadow">
            <div class="mx-auto flex max-w-7xl items-center justify-between">
                <div>
                    <h1 class="text-base font-extrabold">Cetak Stiker Label Barcode Produk</h1>
                    <p class="text-xs text-red-100">{{ institutionName }}</p>
                </div>
                <Link
                    :href="route('pos.index')"
                    class="rounded-md bg-white px-3 py-1.5 text-xs font-bold text-kopdes-800 hover:bg-red-50"
                >
                    &larr; Kembali ke Layar POS
                </Link>
            </div>
        </header>

        <template v-if="!isCashier" #header>
            <div class="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        Cetak Stiker Label Barcode Produk
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Generator barcode SVG tajam (EAN-13 / Code-128) untuk produk lokal &amp; curah (maks {{ MAX_TOTAL_LABELS }} label per cetak)
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('products.index')" class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs">
                        &larr; Katalog Produk
                    </Link>
                    <PrimaryButton
                        type="button"
                        :disabled="expandedLabels.length === 0 || isOverLimit"
                        @click="triggerPrint"
                    >
                        Cetak {{ expandedLabels.length }} Label
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Panel Kontrol (Disembunyikan saat print) -->
                <div class="no-print grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <!-- Kiri: Pilih Produk -->
                    <section class="kopdes-card p-5 lg:col-span-7">
                        <h2 class="text-sm font-bold text-slate-900">1. Pilih Produk Aktif</h2>
                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Cari nama, SKU, atau barcode..."
                                class="kopdes-input"
                            />
                            <select v-model="categoryId" class="kopdes-input cursor-pointer">
                                <option value="">Semua Kategori</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>

                        <div class="mt-3 max-h-72 overflow-y-auto rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="sticky top-0 bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-bold text-slate-600">Produk &amp; Barcode</th>
                                        <th class="px-3 py-2 text-right font-bold text-slate-600">Harga</th>
                                        <th class="px-3 py-2 text-center font-bold text-slate-600">Pilih</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="p in filteredProducts" :key="p.id" class="hover:bg-slate-50">
                                        <td class="px-3 py-2">
                                            <div class="font-semibold text-slate-900">{{ p.name }}</div>
                                            <div class="kopdes-num text-[11px] text-slate-500">
                                                {{ p.barcode }} &bull; {{ p.sku }}
                                            </div>
                                        </td>
                                        <td class="kopdes-num whitespace-nowrap px-3 py-2 text-right">
                                            <div class="font-bold text-slate-900">{{ formatRupiah(p.selling_price) }}</div>
                                            <div v-if="p.member_price !== null" class="text-[11px] font-semibold text-emerald-700">
                                                Mbr: {{ formatRupiah(p.member_price) }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button
                                                type="button"
                                                @click="addProduct(p)"
                                                class="cursor-pointer rounded border border-kopdes-200 bg-kopdes-50 px-2.5 py-1 text-xs font-bold text-kopdes-700 hover:bg-kopdes-100"
                                            >
                                                + Tambah
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Kanan: Antrean Label & Pengaturan Layout -->
                    <section class="kopdes-card flex flex-col justify-between p-5 lg:col-span-5">
                        <div>
                            <div class="flex items-center justify-between">
                                <h2 class="text-sm font-bold text-slate-900">
                                    2. Antrean Cetak &amp; Preset Layout
                                </h2>
                                <span
                                    class="kopdes-num rounded-full px-2.5 py-0.5 text-xs font-bold"
                                    :class="
                                        isOverLimit
                                            ? 'bg-rose-100 text-rose-700'
                                            : 'bg-slate-100 text-slate-700'
                                    "
                                >
                                    {{ totalLabels }} / {{ MAX_TOTAL_LABELS }} Label
                                </span>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="layoutMode = 'a4'"
                                    class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold transition"
                                    :class="
                                        layoutMode === 'a4'
                                            ? 'border-kopdes-600 bg-kopdes-50 text-kopdes-800'
                                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                                    "
                                >
                                    Kertas Stiker A4 (Grid 3 Kolom)
                                </button>
                                <button
                                    type="button"
                                    @click="layoutMode = 'thermal'"
                                    class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold transition"
                                    :class="
                                        layoutMode === 'thermal'
                                            ? 'border-kopdes-600 bg-kopdes-50 text-kopdes-800'
                                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                                    "
                                >
                                    Label Thermal (50x30mm)
                                </button>
                            </div>

                            <label class="mt-3 flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-700">
                                <input
                                    v-model="showMemberPrice"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-kopdes-700 focus:ring-kopdes-600"
                                />
                                Tampilkan Harga Khusus Member pada label (jika tersedia)
                            </label>

                            <div class="mt-3 max-h-52 space-y-2 overflow-y-auto">
                                <div
                                    v-for="entry in selectedItems"
                                    :key="entry.product.id"
                                    class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-bold text-slate-900">{{ entry.product.name }}</div>
                                        <div class="kopdes-num text-[11px] text-slate-500">{{ entry.product.barcode }}</div>
                                    </div>
                                    <input
                                        v-model.number="entry.copies"
                                        type="number"
                                        min="1"
                                        :max="MAX_TOTAL_LABELS"
                                        class="kopdes-input kopdes-num w-16 !py-1 text-center font-bold"
                                    />
                                    <button
                                        type="button"
                                        @click="removeProduct(entry.product.id)"
                                        class="cursor-pointer text-rose-600 hover:text-rose-800"
                                        title="Hapus"
                                    >
                                        &times;
                                    </button>
                                </div>
                                <p v-if="selectedItems.length === 0" class="py-6 text-center text-xs text-slate-500">
                                    Klik "+ Tambah" pada daftar produk untuk mulai menyiapkan label barcode.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-200 pt-3">
                            <SecondaryButton type="button" :disabled="selectedItems.length === 0" @click="clearAll">
                                Kosongkan
                            </SecondaryButton>
                            <PrimaryButton
                                type="button"
                                :disabled="expandedLabels.length === 0 || isOverLimit"
                                @click="triggerPrint"
                            >
                                Cetak Label ({{ expandedLabels.length }})
                            </PrimaryButton>
                        </div>
                    </section>
                </div>

                <!-- Area Pratinjau & Cetak (@media print) -->
                <section id="barcode-print-area" class="kopdes-card p-5">
                    <div class="no-print mb-4 flex items-center justify-between border-b border-slate-200 pb-3">
                        <h2 class="text-sm font-bold text-slate-900">
                            Pratinjau Cetak ({{ layoutMode === 'a4' ? 'A4 Grid 3 Kolom' : 'Thermal Label 50x30mm' }})
                        </h2>
                        <span class="text-xs text-slate-500">{{ expandedLabels.length }} stiker siap cetak</span>
                    </div>

                    <div
                        v-if="expandedLabels.length > 0"
                        :class="
                            layoutMode === 'a4'
                                ? 'barcode-grid-a4 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3'
                                : 'barcode-stack-thermal flex flex-col items-start gap-2'
                        "
                    >
                        <div
                            v-for="label in expandedLabels"
                            :key="label.uid"
                            class="barcode-label-card flex flex-col items-center justify-between rounded border border-slate-300 bg-white p-2.5 text-center"
                            :class="layoutMode === 'thermal' ? 'barcode-label-thermal' : 'barcode-label-a4'"
                        >
                            <div class="w-full truncate text-[10px] font-bold uppercase tracking-tight text-slate-600">
                                {{ institutionName }}
                            </div>
                            <div class="mt-0.5 line-clamp-1 w-full text-xs font-extrabold text-slate-900">
                                {{ label.product.name }}
                            </div>
                            <svg
                                :ref="(el) => setSvgRef(label.uid, el)"
                                class="my-1 max-w-full"
                            ></svg>
                            <div class="flex w-full items-baseline justify-center gap-2 border-t border-dashed border-slate-200 pt-1">
                                <span class="kopdes-num text-xs font-extrabold text-slate-900">
                                    {{ formatRupiah(label.product.selling_price) }}
                                </span>
                                <span
                                    v-if="showMemberPrice && label.product.member_price !== null"
                                    class="kopdes-num rounded bg-emerald-50 px-1 py-0.5 text-[10px] font-bold text-emerald-800"
                                >
                                    Member: {{ formatRupiah(label.product.member_price) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <p v-else class="no-print py-10 text-center text-sm text-slate-500">
                        Belum ada stiker barcode pada pratinjau cetak.
                    </p>
                </section>
            </div>
        </div>
    </component>
</template>

<style>
.barcode-label-thermal {
    width: 50mm;
    min-height: 30mm;
}

@media print {
    @page {
        margin: 6mm;
    }

    body * {
        visibility: hidden !important;
    }

    .no-print {
        display: none !important;
    }

    #barcode-print-area,
    #barcode-print-area * {
        visibility: visible !important;
    }

    #barcode-print-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        background: #ffffff !important;
    }

    .barcode-grid-a4 {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 4mm !important;
    }

    .barcode-label-card {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        border: 0.5pt dashed #94a3b8 !important;
    }

    .barcode-label-thermal {
        width: 50mm !important;
        height: 30mm !important;
        page-break-after: always !important;
    }
}
</style>
