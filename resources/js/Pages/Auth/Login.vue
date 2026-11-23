<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const hasPosLoginRoute = () => {
    try {
        return route().has('pos.login');
    } catch {
        return false;
    }
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Login Admin KOPDES" />

        <div class="mb-6 border-b border-gray-200 pb-4 text-center">
            <h1 class="text-xl font-bold text-gray-900">
                KOPDES Merah Putih
            </h1>
            <p class="mt-1 text-xs text-gray-500">
                Portal Manajemen & Pelaporan Berjenjang (Nasional — Provinsi — Kabupaten — Desa)
            </p>
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email Admin" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="admin@kopdes.id"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Kata Sandi" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600">
                        Ingat sesi saya
                    </span>
                </label>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                >
                    Lupa kata sandi?
                </Link>

                <PrimaryButton
                    class="ms-auto bg-red-700 hover:bg-red-800 focus:bg-red-800 active:bg-red-900"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Masuk Dashboard
                </PrimaryButton>
            </div>
        </form>

        <div class="mt-6 rounded-md border border-gray-200 bg-gray-50 p-3 text-center text-xs text-gray-600">
            <span class="font-medium text-gray-800">Petugas Kasir POS?</span>
            <p class="mt-0.5">
                Gunakan halaman khusus Kasir dengan Tap Kartu RFID &amp; PIN 6 Digit:
                <Link
                    v-if="hasPosLoginRoute()"
                    :href="route('pos.login')"
                    class="font-semibold text-red-700 underline hover:text-red-800"
                >
                    Buka Layar Login POS (/pos/login)
                </Link>
                <a
                    v-else
                    href="/pos/login"
                    class="font-semibold text-red-700 underline hover:text-red-800"
                >
                    /pos/login
                </a>
            </p>
        </div>
    </GuestLayout>
</template>
