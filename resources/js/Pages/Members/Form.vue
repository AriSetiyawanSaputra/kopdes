<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RfidCaptureModal from '@/Components/RfidCaptureModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { normalizeCardUid } from '@/Utils/normalizeCardUid';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    member: {
        type: Object,
        default: null,
    },
    branches: {
        type: Array,
        default: () => [],
    },
    defaultBranchId: {
        type: [Number, String],
        default: '',
    },
    isBranchLocked: {
        type: Boolean,
        default: false,
    },
});

const isEdit = computed(() => props.member !== null);
const showRfidModal = ref(false);

const todayDate = new Date().toISOString().slice(0, 10);

const form = useForm({
    home_branch_id: props.member?.home_branch_id ?? props.defaultBranchId ?? '',
    name: props.member?.name ?? '',
    card_uid: props.member?.card_uid ?? '',
    phone: props.member?.phone ?? '',
    nik: props.member?.nik ?? '',
    status: props.member?.status ?? 'active',
    pin: '',
    joined_at: props.member?.joined_at ?? todayDate,
});

const onCardCaptured = (normalizedUid) => {
    form.card_uid = normalizeCardUid(normalizedUid);
    showRfidModal.value = false;
};

const submit = () => {
    form.card_uid = normalizeCardUid(form.card_uid);

    if (isEdit.value) {
        form.put(route('members.update', props.member.id));
    } else {
        form.post(route('members.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Data Member' : 'Registrasi Member KOPDES'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        {{
                            isEdit
                                ? `Edit Member: ${member.name} (${member.member_code})`
                                : 'Registrasi Anggota (Member) KOPDES Baru'
                        }}
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Kode member dibuat otomatis (`MBR-{KODE_CABANG}-{SEQ5}`) dan kartu RFID berlaku lintas cabang
                    </p>
                </div>

                <Link
                    :href="route('members.index')"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    &larr; Kembali
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel
                                    for="home_branch_id"
                                    value="KOPDES Asal Pendaftaran *"
                                />
                                <select
                                    id="home_branch_id"
                                    v-model="form.home_branch_id"
                                    :disabled="isBranchLocked"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 disabled:bg-gray-100"
                                    required
                                >
                                    <option value="">-- Pilih Cabang KOPDES --</option>
                                    <option
                                        v-for="b in branches"
                                        :key="b.id"
                                        :value="b.id"
                                    >
                                        {{ b.code }} — {{ b.name }}
                                    </option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.home_branch_id"
                                />
                            </div>

                            <div>
                                <InputLabel for="joined_at" value="Tanggal Bergabung *" />
                                <TextInput
                                    id="joined_at"
                                    v-model="form.joined_at"
                                    type="date"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.joined_at" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="name" value="Nama Lengkap Anggota *" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Contoh: Budi Santoso"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <!-- Binding Kartu RFID Member (Opsional) -->
                        <div class="rounded-md border border-blue-200 bg-blue-50/50 p-4">
                            <InputLabel
                                for="card_uid"
                                value="UID Kartu RFID Anggota (Opsional — dapat dipasang nanti)"
                            />
                            <div class="mt-1 flex gap-2">
                                <TextInput
                                    id="card_uid"
                                    v-model="form.card_uid"
                                    type="text"
                                    class="block w-full font-mono uppercase"
                                    placeholder="Kosongkan jika belum ada kartu fisik, atau klik Scan..."
                                />
                                <SecondaryButton
                                    type="button"
                                    class="shrink-0"
                                    @click="showRfidModal = true"
                                >
                                    Scan Kartu Member
                                </SecondaryButton>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Jika diisi, UID kartu otomatis dinormalisasi (huruf kapital alfanumerik) dan dijamin unik lintas Kasir &amp; Member.
                            </p>
                            <InputError class="mt-2" :message="form.errors.card_uid" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel for="phone" value="Nomor HP (Opsional)" />
                                <TextInput
                                    id="phone"
                                    v-model="form.phone"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="0812xxxxxxxx"
                                />
                                <InputError class="mt-2" :message="form.errors.phone" />
                            </div>

                            <div>
                                <InputLabel
                                    for="nik"
                                    value="NIK Kependudukan (Opsional)"
                                />
                                <TextInput
                                    id="nik"
                                    v-model="form.nik"
                                    type="text"
                                    maxlength="20"
                                    class="mt-1 block w-full font-mono"
                                    placeholder="16 digit NIK"
                                />
                                <InputError class="mt-2" :message="form.errors.nik" />
                            </div>

                            <div>
                                <InputLabel for="status" value="Status Kartu *" />
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                                >
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.status" />
                            </div>
                        </div>

                        <!-- PIN Portal Member Awal (Opsional, khusus saat registrasi baru) -->
                        <div
                            v-if="!isEdit"
                            class="rounded-md border border-emerald-200 bg-emerald-50/50 p-4"
                        >
                            <InputLabel
                                for="pin"
                                value="PIN Portal Member 6 Digit (Opsional — untuk login Portal Mandiri)"
                            />
                            <TextInput
                                id="pin"
                                v-model="form.pin"
                                type="password"
                                inputmode="numeric"
                                maxlength="6"
                                pattern="\d{6}"
                                autocomplete="new-password"
                                class="kopdes-num mt-1 block w-full max-w-xs text-center text-base font-bold tracking-[0.4em]"
                                placeholder="••••••"
                            />
                            <p class="mt-1 text-xs text-slate-600">
                                Kosongkan jika belum ingin mengaktifkan akses Portal Member; PIN dapat diatur/direset kapan saja dari daftar Member.
                            </p>
                            <InputError class="mt-2" :message="form.errors.pin" />
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                            <Link
                                :href="route('members.index')"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                            >
                                Batal
                            </Link>
                            <PrimaryButton
                                class="bg-red-700 hover:bg-red-800"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ isEdit ? 'Simpan Perubahan' : 'Daftarkan Member' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <RfidCaptureModal
            :show="showRfidModal"
            title="Scan Kartu RFID Anggota (Member)"
            description="Tempelkan kartu RFID anggota pada USB RFID Reader."
            :current-uid="form.card_uid"
            @close="showRfidModal = false"
            @captured="onCardCaptured"
        />
    </AuthenticatedLayout>
</template>
