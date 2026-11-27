<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    product: {
        type: Object,
        default: null,
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

const isEdit = computed(() => props.product !== null);

const unitOptions = ['pcs', 'kg', 'liter', 'karung', 'dus', 'pak', 'botol'];

const form = useForm({
    category_id: props.product?.category_id ?? '',
    sku: props.product?.sku ?? '',
    barcode: props.product?.barcode ?? '',
    name: props.product?.name ?? '',
    unit: props.product?.unit ?? 'pcs',
    cost_price: props.product?.cost_price ?? 0,
    selling_price: props.product?.selling_price ?? 0,
    member_price: props.product?.member_price ?? '',
    is_active: props.product?.is_active ?? true,
});

const hasMemberPrice = computed(() => {
    return (
        form.member_price !== '' &&
        form.member_price !== null &&
        form.member_price !== undefined &&
        !Number.isNaN(Number(form.member_price))
    );
});

const isMemberPriceBelowCost = computed(() => {
    if (!hasMemberPrice.value) {
        return false;
    }

    return Number(form.member_price) < Number(form.cost_price || 0);
});

const isMemberPriceAboveSelling = computed(() => {
    if (!hasMemberPrice.value) {
        return false;
    }

    return Number(form.member_price) > Number(form.selling_price || 0);
});

const normalMargin = computed(() => Number(form.selling_price || 0) - Number(form.cost_price || 0));
const memberMargin = computed(() =>
    hasMemberPrice.value ? Number(form.member_price) - Number(form.cost_price || 0) : null,
);

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('products.update', props.product.id));
    } else {
        form.post(route('products.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Produk Global' : 'Tambah Produk Global'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        {{
                            isEdit
                                ? `Edit Produk Global: ${product.name}`
                                : 'Tambah Produk ke Katalog Nasional'
                        }}
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Perubahan HPP &amp; Harga Jual tidak akan mengubah laporan laba kotor transaksi historis (Snapshot HPP)
                    </p>
                </div>

                <Link
                    :href="route('products.index')"
                    class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                >
                    &larr; Kembali ke Katalog
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="kopdes-card p-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="category_id" class="block text-xs font-semibold text-slate-700">
                                    Kategori Produk <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    id="category_id"
                                    v-model="form.category_id"
                                    class="kopdes-input mt-1 cursor-pointer"
                                    required
                                >
                                    <option value="">-- Pilih Kategori --</option>
                                    <option
                                        v-for="cat in categories"
                                        :key="cat.id"
                                        :value="cat.id"
                                    >
                                        {{ cat.name }}
                                    </option>
                                </select>
                                <InputError class="mt-1.5" :message="form.errors.category_id" />
                            </div>

                            <div>
                                <label for="unit" class="block text-xs font-semibold text-slate-700">
                                    Satuan Jual <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    id="unit"
                                    v-model="form.unit"
                                    class="kopdes-input mt-1 cursor-pointer"
                                    required
                                >
                                    <option
                                        v-for="u in unitOptions"
                                        :key="u"
                                        :value="u"
                                    >
                                        {{ u }}
                                    </option>
                                </select>
                                <InputError class="mt-1.5" :message="form.errors.unit" />
                            </div>
                        </div>

                        <div>
                            <label for="name" class="block text-xs font-semibold text-slate-700">
                                Nama Produk <span class="text-rose-600">*</span>
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="Contoh: Beras Merah Putih Premium 5 Kg"
                                required
                            />
                            <InputError class="mt-1.5" :message="form.errors.name" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="sku" class="block text-xs font-semibold text-slate-700">
                                    Kode SKU Internal Pusat <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    id="sku"
                                    v-model="form.sku"
                                    type="text"
                                    class="kopdes-input kopdes-num mt-1 uppercase"
                                    placeholder="SKU-BRS-001"
                                    required
                                />
                                <InputError class="mt-1.5" :message="form.errors.sku" />
                            </div>

                            <div>
                                <label for="barcode" class="block text-xs font-semibold text-slate-700">
                                    Kode Barcode Fisik (EAN-13 / Code-128) <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    id="barcode"
                                    v-model="form.barcode"
                                    type="text"
                                    class="kopdes-input kopdes-num mt-1"
                                    placeholder="8991234567890"
                                    required
                                />
                                <InputError class="mt-1.5" :message="form.errors.barcode" />
                            </div>
                        </div>

                        <!-- Struktur Harga Nasional -->
                        <div class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h2 class="text-sm font-bold text-slate-900">
                                    Struktur Harga Nasional &amp; Margin
                                </h2>
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span class="rounded-full bg-white px-2.5 py-0.5 font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">
                                        Margin Normal:
                                        <strong class="kopdes-num" :class="normalMargin >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                            {{ formatRupiah(normalMargin) }}
                                        </strong>
                                    </span>
                                    <span
                                        v-if="memberMargin !== null"
                                        class="rounded-full bg-white px-2.5 py-0.5 font-semibold text-slate-700 ring-1 ring-inset ring-slate-200"
                                    >
                                        Margin Member:
                                        <strong class="kopdes-num" :class="memberMargin >= 0 ? 'text-emerald-700' : 'text-amber-700'">
                                            {{ formatRupiah(memberMargin) }}
                                        </strong>
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label for="cost_price" class="block text-xs font-semibold text-slate-700">
                                        HPP (Rp) <span class="text-rose-600">*</span>
                                    </label>
                                    <input
                                        id="cost_price"
                                        v-model="form.cost_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="kopdes-input kopdes-num mt-1 font-bold"
                                        required
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.cost_price" />
                                </div>

                                <div>
                                    <label for="selling_price" class="block text-xs font-semibold text-slate-700">
                                        Harga Jual Normal (Rp) <span class="text-rose-600">*</span>
                                    </label>
                                    <input
                                        id="selling_price"
                                        v-model="form.selling_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="kopdes-input kopdes-num mt-1 font-bold"
                                        required
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.selling_price" />
                                </div>

                                <div>
                                    <label for="member_price" class="block text-xs font-semibold text-slate-700">
                                        Harga Khusus Member (Rp)
                                    </label>
                                    <input
                                        id="member_price"
                                        v-model="form.member_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="kopdes-input kopdes-num mt-1 font-bold text-emerald-700"
                                        placeholder="Opsional"
                                    />
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Berlaku nasional saat kartu member aktif di-tap di POS.
                                    </p>
                                    <InputError class="mt-1.5" :message="form.errors.member_price" />
                                </div>
                            </div>
                        </div>

                        <!-- Peringatan Non-Blocking bila Harga Member < HPP (FR-P2-09) -->
                        <div
                            v-if="isMemberPriceBelowCost"
                            role="alert"
                            class="flex items-start gap-3 rounded-xl border border-amber-300 border-l-4 border-l-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                            <div>
                                <p class="font-bold">
                                    Perhatian: Harga Member lebih rendah dari HPP (potensi jual rugi)
                                </p>
                                <p class="mt-0.5 text-xs text-amber-800">
                                    Sistem tetap memperbolehkan penyimpanan bila ini merupakan subsidi/promo khusus anggota koperasi.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="isMemberPriceAboveSelling"
                            role="alert"
                            class="flex items-start gap-3 rounded-xl border border-rose-300 border-l-4 border-l-rose-600 bg-rose-50 px-4 py-3 text-sm text-rose-900"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="mt-0.5 h-5 w-5 shrink-0 text-rose-600"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <span>
                                Harga khusus member tidak boleh melebihi harga jual normal.
                            </span>
                        </div>

                        <div class="flex items-center">
                            <label class="inline-flex cursor-pointer items-center">
                                <Checkbox
                                    name="is_active"
                                    v-model:checked="form.is_active"
                                />
                                <span class="ms-2 text-sm font-medium text-slate-700">
                                    Produk Aktif (Dapat Dipindai &amp; Dijual di Layar POS Cabang)
                                </span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-2.5 border-t border-slate-200 pt-4">
                            <Link
                                :href="route('products.index')"
                                class="btn-kopdes-secondary"
                            >
                                Batal
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Simpan Produk') }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
