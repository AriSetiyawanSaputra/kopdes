<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
    is_active: props.product?.is_active ?? true,
});

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
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        {{
                            isEdit
                                ? `Edit Produk Global: ${product.name}`
                                : 'Tambah Produk ke Katalog Nasional'
                        }}
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Perubahan HPP &amp; Harga Jual tidak akan mengubah laporan laba kotor transaksi historis (Snapshot HPP)
                    </p>
                </div>

                <Link
                    :href="route('products.index')"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    &larr; Kembali ke Katalog
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="category_id" value="Kategori Produk *" />
                                <select
                                    id="category_id"
                                    v-model="form.category_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
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
                                <InputError class="mt-2" :message="form.errors.category_id" />
                            </div>

                            <div>
                                <InputLabel for="unit" value="Satuan Jual *" />
                                <select
                                    id="unit"
                                    v-model="form.unit"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
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
                                <InputError class="mt-2" :message="form.errors.unit" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="name" value="Nama Produk *" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Contoh: Beras Merah Putih Premium 5 Kg"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="sku" value="Kode SKU Internal Pusat *" />
                                <TextInput
                                    id="sku"
                                    v-model="form.sku"
                                    type="text"
                                    class="mt-1 block w-full font-mono uppercase"
                                    placeholder="SKU-BRS-001"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.sku" />
                            </div>

                            <div>
                                <InputLabel
                                    for="barcode"
                                    value="Kode Barcode Fisik (EAN-13 / Code-128) *"
                                />
                                <TextInput
                                    id="barcode"
                                    v-model="form.barcode"
                                    type="text"
                                    class="mt-1 block w-full font-mono"
                                    placeholder="8991234567890"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.barcode" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel
                                    for="cost_price"
                                    value="Harga Pokok Penjualan / HPP (Rp) *"
                                />
                                <TextInput
                                    id="cost_price"
                                    v-model="form.cost_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full font-mono"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.cost_price" />
                            </div>

                            <div>
                                <InputLabel
                                    for="selling_price"
                                    value="Harga Jual Seragam Nasional (Rp) *"
                                />
                                <TextInput
                                    id="selling_price"
                                    v-model="form.selling_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full font-mono"
                                    required
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.selling_price"
                                />
                            </div>
                        </div>

                        <div class="flex items-center">
                            <label class="inline-flex items-center">
                                <Checkbox
                                    name="is_active"
                                    v-model:checked="form.is_active"
                                />
                                <span class="ms-2 text-sm font-medium text-gray-700">
                                    Produk Aktif (Dapat Dipindai &amp; Dijual di Layar POS Cabang)
                                </span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                            <Link
                                :href="route('products.index')"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                            >
                                Batal
                            </Link>
                            <PrimaryButton
                                class="bg-red-700 hover:bg-red-800"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ isEdit ? 'Simpan Perubahan' : 'Simpan Produk' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
