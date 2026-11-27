<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-4">
        <header>
            <h2 class="text-base font-bold text-slate-900">
                Hapus Akun
            </h2>

            <p class="mt-1 text-xs leading-relaxed text-slate-600">
                Setelah akun dihapus, seluruh akses login akan dinonaktifkan secara permanen. Pastikan Anda telah mencadangkan data penting sebelum melanjutkan.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Hapus Akun</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h3 class="text-lg font-extrabold text-slate-900">
                    Yakin ingin menghapus akun ini?
                </h3>

                <p class="mt-1.5 text-xs leading-relaxed text-slate-600">
                    Masukkan kata sandi Anda untuk mengonfirmasi penghapusan akun secara permanen.
                </p>

                <div class="mt-5">
                    <InputLabel
                        for="delete_account_password"
                        value="Kata Sandi"
                        class="sr-only"
                    />

                    <TextInput
                        id="delete_account_password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        placeholder="Masukkan kata sandi Anda"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-1.5" />
                </div>

                <div class="mt-6 flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                    <SecondaryButton @click="closeModal">
                        Batal
                    </SecondaryButton>

                    <DangerButton
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        {{ form.processing ? 'Menghapus...' : 'Ya, Hapus Akun' }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
