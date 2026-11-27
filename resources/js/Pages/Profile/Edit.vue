<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profil & Keamanan Akun" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                    Profil &amp; Keamanan Akun
                </h1>
                <p class="mt-0.5 text-sm text-slate-600">
                    Kelola informasi identitas akun dan kata sandi login Anda
                </p>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="kopdes-card p-6 sm:p-8">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="kopdes-card p-6 sm:p-8">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="kopdes-card p-6 sm:p-8">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
