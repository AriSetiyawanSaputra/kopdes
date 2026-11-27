<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
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
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                    Master Kategori Produk Global
                </h1>
                <p class="mt-0.5 text-sm text-slate-600">
                    Klasifikasi produk terpusat untuk seluruh cabang KOPDES
                </p>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
                <!-- Form Tambah / Edit Kategori -->
                <div
                    v-if="permissions.can_manage"
                    class="kopdes-card self-start p-5 lg:col-span-1"
                >
                    <h2 class="text-base font-bold text-slate-900">
                        {{
                            editingCategory
                                ? `Edit Kategori: ${editingCategory.name}`
                                : 'Tambah Kategori Baru'
                        }}
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Slug otomatis dibuat dari nama jika dikosongkan.
                    </p>

                    <form @submit.prevent="submit" class="mt-4 space-y-4">
                        <div>
                            <label for="cat-name" class="block text-xs font-semibold text-slate-700">
                                Nama Kategori <span class="text-rose-600">*</span>
                            </label>
                            <input
                                id="cat-name"
                                v-model="form.name"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="Contoh: Sembako, Pupuk, Minuman"
                                required
                            />
                            <InputError class="mt-1.5" :message="form.errors.name" />
                        </div>

                        <div>
                            <label for="cat-slug" class="block text-xs font-semibold text-slate-700">
                                Slug Kategori (Opsional / Otomatis)
                            </label>
                            <input
                                id="cat-slug"
                                v-model="form.slug"
                                type="text"
                                class="kopdes-input kopdes-num mt-1"
                                placeholder="Otomatis dari nama kategori"
                            />
                            <InputError class="mt-1.5" :message="form.errors.slug" />
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : (editingCategory ? 'Simpan Perubahan' : 'Tambah Kategori') }}
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
                    class="kopdes-card overflow-hidden"
                    :class="permissions.can_manage ? 'lg:col-span-2' : 'lg:col-span-3'"
                >
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Daftar Kategori Produk
                        </h2>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ categories.length }} Kategori</span>
                        </StatusBadge>
                    </div>
                    <div v-if="categories.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Nama Kategori
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Slug
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Jumlah Produk
                                    </th>
                                    <th
                                        v-if="permissions.can_manage"
                                        class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="px-4 py-3.5 font-semibold text-slate-900">
                                        {{ cat.name }}
                                    </td>
                                    <td class="kopdes-num px-4 py-3.5 text-xs text-slate-500">
                                        {{ cat.slug }}
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <StatusBadge variant="slate" size="xs">
                                            <span class="kopdes-num">{{ cat.products_count }} produk</span>
                                        </StatusBadge>
                                    </td>
                                    <td
                                        v-if="permissions.can_manage"
                                        class="whitespace-nowrap px-4 py-3.5 text-right"
                                    >
                                        <div class="inline-flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                @click="startEdit(cat)"
                                                class="cursor-pointer rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                @click="deleteCategory(cat)"
                                                class="cursor-pointer rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-100"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else
                        title="Belum ada kategori produk"
                        description="Tambahkan kategori produk untuk mengelompokkan katalog nasional KOPDES."
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
