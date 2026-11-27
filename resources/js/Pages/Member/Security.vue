<script setup>
import { computed } from 'vue';
import InputError from '@/Components/InputError.vue';
import MemberLayout from '@/Layouts/MemberLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const isDemoMode = computed(() => Boolean(page.props.app?.demo_mode));

const form = useForm({
    current_pin: '',
    new_pin: '',
    new_pin_confirmation: '',
});

const submit = () => {
    form.put(route('member.security.pin.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            form.reset('current_pin');
        },
    });
};
</script>

<template>
    <Head title="Keamanan & Ganti PIN Member" />

    <MemberLayout>
        <div class="mx-auto max-w-xl space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900">
                        Keamanan Akun &amp; Ganti PIN
                    </h1>
                    <p class="mt-0.5 text-xs text-slate-600">
                        Perbarui PIN 6 digit angka Portal Mandiri Member Anda secara berkala
                    </p>
                </div>
                <StatusBadge variant="emerald" size="xs" dot>
                    PIN 6 Digit Terenkripsi
                </StatusBadge>
            </div>

            <!-- Info Mode Demo (Non-blocking UI indicator matching server guard) -->
            <div
                v-if="isDemoMode"
                role="status"
                class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>
                    <strong>Mode Demo Aktif:</strong> Demi keamanan akses akun portofolio bersama, perubahan PIN pada akun demo dibatasi oleh server.
                </span>
            </div>

            <div class="kopdes-card p-5 sm:p-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label for="current_pin" class="block text-xs font-semibold text-slate-700">
                            PIN Lama (6 Digit) <span class="text-rose-600">*</span>
                        </label>
                        <input
                            id="current_pin"
                            v-model="form.current_pin"
                            type="password"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="\d{6}"
                            autocomplete="current-password"
                            placeholder="••••••"
                            required
                            class="kopdes-input kopdes-num mt-1.5 text-center text-lg font-bold tracking-[0.45em]"
                        />
                        <InputError class="mt-1.5" :message="form.errors.current_pin" />
                    </div>

                    <div>
                        <label for="new_pin" class="block text-xs font-semibold text-slate-700">
                            PIN Baru (6 Digit Angka) <span class="text-rose-600">*</span>
                        </label>
                        <input
                            id="new_pin"
                            v-model="form.new_pin"
                            type="password"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="\d{6}"
                            autocomplete="new-password"
                            placeholder="••••••"
                            required
                            class="kopdes-input kopdes-num mt-1.5 text-center text-lg font-bold tracking-[0.45em]"
                        />
                        <InputError class="mt-1.5" :message="form.errors.new_pin" />
                    </div>

                    <div>
                        <label for="new_pin_confirmation" class="block text-xs font-semibold text-slate-700">
                            Konfirmasi PIN Baru <span class="text-rose-600">*</span>
                        </label>
                        <input
                            id="new_pin_confirmation"
                            v-model="form.new_pin_confirmation"
                            type="password"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="\d{6}"
                            autocomplete="new-password"
                            placeholder="••••••"
                            required
                            class="kopdes-input kopdes-num mt-1.5 text-center text-lg font-bold tracking-[0.45em]"
                        />
                    </div>

                    <div class="pt-2">
                        <PrimaryButton
                            class="w-full py-2.5 text-sm font-bold"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Memperbarui PIN...' : 'Simpan PIN Baru' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="kopdes-card flex items-start gap-2.5 px-4 py-3.5 text-xs text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-kopdes-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span>
                    Jangan bagikan PIN 6 digit Anda kepada siapa pun. Jika lupa PIN, kunjungi Admin KOPDES asal Anda untuk melakukan reset PIN dengan aman.
                </span>
            </div>
        </div>
    </MemberLayout>
</template>
