<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({
    categories: {
        type: Array,
        required: true,
    },
    permissions: {
        type: Object,
        default: () => ({ can_manage: false }),
    },
});

const editingCategory = ref(null);

const form = useForm({
    name: '',
    slug: '',
});

const startEdit = (category) => {
    editingCategory.value = category;
    form.clearErrors();
    form.name = category.name;
    form.slug = category.slug;
};

const cancelEdit = () => {
    editingCategory.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (editingCategory.value) {
        form.put(route('categories.update', editingCategory.value.id), {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
    } else {
        form.post(route('categories.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }
};

const deleteCategory = (category) => {
    if (
        !window.confirm(
            `Apakah Anda yakin ingin menghapus kategori "${category.name}"?`,
        )
    ) {
        return;
    }

    router.delete(route('categories.destroy', category.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Master Kategori Produk" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight text-gray-900">
                    Master Kategori Produk Global
                </h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    Klasifikasi produk terpusat untuk seluruh cabang KOPDES
                </p>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 sm:px-6 lg:grid-cols-3 lg:px-8">
                <!-- Form Tambah / Edit Kategori -->
                <div
                    v-if="permissions.can_manage"
                    class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 lg:col-span-1"
                >
                    <h3 class="text-base font-bold text-gray-900">
                        {{
                            editingCategory
                                ? `Edit Kategori: ${editingCategory.name}`
                                : 'Tambah Kategori Baru'
                        }}
                    </h3>

                    <form @submit.prevent="submit" class="mt-4 space-y-4">
                        <div>
                            <InputLabel for="name" value="Nama Kategori *" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Contoh: Sembako, Pupuk, Minuman"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel
                                for="slug"
                                value="Slug Kategori (Opsional / Otomatis)"
                            />
                            <TextInput
                                id="slug"
                                v-model="form.slug"
                                type="text"
                                class="mt-1 block w-full font-mono text-sm"
                                placeholder="Otomatis dari nama kategori"
                            />
                            <InputError class="mt-2" :message="form.errors.slug" />
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <PrimaryButton
                                class="bg-red-700 hover:bg-red-800"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ editingCategory ? 'Simpan Perubahan' : 'Tambah Kategori' }}
                            </PrimaryButton>

                            <SecondaryButton
                                v-if="editingCategory"
                                type="button"
                                @click="cancelEdit"
                            >
                                Batal
                            </SecondaryButton>
                        </div>
                    </form>
                </div>

                <!-- Tabel Daftar Kategori -->
                <div
                    class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200"
                    :class="permissions.can_manage ? 'lg:col-span-2' : 'lg:col-span-3'"
                >
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                    Nama Kategori
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                    Slug
                                </th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                    Jumlah Produk
                                </th>
                                <th
                                    v-if="permissions.can_manage"
                                    class="px-4 py-3 text-right font-semibold text-gray-700"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="cat in categories"
                                :key="cat.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-4 py-3 font-semibold text-gray-900">
                                    {{ cat.name }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">
                                    {{ cat.slug }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">
                                        {{ cat.products_count }} produk
                                    </span>
                                </td>
                                <td
                                    v-if="permissions.can_manage"
                                    class="whitespace-nowrap px-4 py-3 text-right"
                                >
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="startEdit(cat)"
                                            class="rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteCategory(cat)"
                                            class="rounded border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="categories.length === 0">
                                <td
                                    colspan="4"
                                    class="px-4 py-8 text-center text-sm text-gray-500"
                                >
                                    Belum ada kategori produk terdaftar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
