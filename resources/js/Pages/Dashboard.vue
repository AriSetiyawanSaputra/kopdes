<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const roleTitle = computed(() => {
    const labels = {
        admin_nasional: 'Admin Nasional',
        admin_provinsi: 'Admin Provinsi',
        admin_kabupaten: 'Admin Kabupaten/Kota',
        admin_cabang: 'Admin Cabang KOPDES Desa',
    };

    return labels[user.value?.role] ?? user.value?.role ?? 'Administrator';
});

const regionDescription = computed(() => {
    if (!user.value) {
        return '-';
    }

    if (user.value.role === 'admin_nasional') {
        return 'Akses Global Seluruh Cabang KOPDES Indonesia';
    }

    if (user.value.role === 'admin_provinsi') {
        return `Wilayah Provinsi: ${user.value.province?.name ?? user.value.province_id}`;
    }

    if (user.value.role === 'admin_kabupaten') {
        return `Wilayah Kabupaten/Kota: ${user.value.regency?.name ?? user.value.regency_id}`;
    }

    if (user.value.branch) {
        return `Cabang: ${user.value.branch.name} (${user.value.branch.code})`;
    }

    return '-';
});
</script>

<template>
    <Head title="Dashboard KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Dashboard Manajemen KOPDES
                    </h2>
                    <p class="text-sm text-gray-500">
                        Koperasi Desa Merah Putih — Sistem Kasir, Inventori &amp; Pelaporan Berjenjang
                    </p>
                </div>
                <div class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/20">
                    {{ roleTitle }}
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-base font-semibold text-gray-900">
                            Selamat datang, {{ user?.name }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ regionDescription }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
