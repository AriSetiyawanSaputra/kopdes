<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RfidCaptureModal from '@/Components/RfidCaptureModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { normalizeCardUid } from '@/Utils/normalizeCardUid';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    targetUser: {
        type: Object,
        default: null,
    },
    creatableRoles: {
        type: Array,
        default: () => [],
    },
    provinces: {
        type: Array,
        default: () => [],
    },
    regencies: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const actor = computed(() => page.props.auth?.user ?? null);
const isEdit = computed(() => props.targetUser !== null);

const showRfidModal = ref(false);

const defaultRole =
    props.targetUser?.role ??
    (props.creatableRoles.length === 1 ? props.creatableRoles[0].value : '');

const form = useForm({
    name: props.targetUser?.name ?? '',
    role: defaultRole,
    email: props.targetUser?.email ?? '',
    password: '',
    province_id:
        props.targetUser?.province_id ||
        (actor.value?.province_id ?? ''),
    regency_id:
        props.targetUser?.regency_id ||
        (actor.value?.regency_id ?? ''),
    branch_id:
        props.targetUser?.branch_id ||
        (actor.value?.branch_id ?? ''),
    card_uid: props.targetUser?.card_uid ?? '',
    pin: '',
    is_active: props.targetUser?.is_active ?? true,
});

const isCashierRole = computed(() => form.role === 'kasir');
const needsProvince = computed(() => form.role === 'admin_provinsi');
const needsRegency = computed(() => form.role === 'admin_kabupaten');
const needsBranch = computed(
    () => form.role === 'admin_cabang' || form.role === 'kasir',
);

const onCardCaptured = (normalizedUid) => {
    form.card_uid = normalizeCardUid(normalizedUid);
    showRfidModal.value = false;
};

const submit = () => {
    if (form.card_uid) {
        form.card_uid = normalizeCardUid(form.card_uid);
    }

    if (isEdit.value) {
        form.put(route('users.update', props.targetUser.id));
    } else {
        form.post(route('users.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Pengguna / Kasir' : 'Tambah Pengguna / Kasir'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        {{
                            isEdit
                                ? `Edit Pengguna: ${targetUser.name}`
                                : 'Tambah Pengguna Admin / Kasir POS'
                        }}
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Sesuai hierarki RBAC KOPDES dan standar keamanan PIN 6 digit &amp; Kartu RFID
                    </p>
                </div>

                <Link
                    :href="route('users.index')"
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
                                <InputLabel for="name" value="Nama Lengkap *" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Nama lengkap admin / kasir"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="role" value="Peran (Role) *" />
                                <select
                                    id="role"
                                    v-model="form.role"
                                    :disabled="creatableRoles.length === 1"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 disabled:bg-gray-100"
                                    required
                                >
                                    <option value="">-- Pilih Role --</option>
                                    <option
                                        v-for="r in creatableRoles"
                                        :key="r.value"
                                        :value="r.value"
                                    >
                                        {{ r.label }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.role" />
                            </div>
                        </div>

                        <!-- Cakupan Wilayah / Cabang Dinamis Berdasarkan Role -->
                        <div
                            v-if="needsProvince || needsRegency || needsBranch"
                            class="rounded-md border border-gray-200 bg-gray-50 p-4"
                        >
                            <h3 class="text-sm font-bold text-gray-800">
                                Penugasan Wilayah / Cabang KOPDES
                            </h3>

                            <div v-if="needsProvince" class="mt-3">
                                <InputLabel for="province_id" value="Provinsi *" />
                                <select
                                    id="province_id"
                                    v-model="form.province_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                                    required
                                >
                                    <option value="">-- Pilih Provinsi --</option>
                                    <option
                                        v-for="p in provinces"
                                        :key="p.id"
                                        :value="p.id"
                                    >
                                        {{ p.id }} — {{ p.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.province_id" />
                            </div>

                            <div v-if="needsRegency" class="mt-3">
                                <InputLabel for="regency_id" value="Kabupaten / Kota *" />
                                <select
                                    id="regency_id"
                                    v-model="form.regency_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                                    required
                                >
                                    <option value="">-- Pilih Kabupaten/Kota --</option>
                                    <option
                                        v-for="reg in regencies"
                                        :key="reg.id"
                                        :value="reg.id"
                                    >
                                        {{ reg.id }} — {{ reg.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.regency_id" />
                            </div>

                            <div v-if="needsBranch" class="mt-3">
                                <InputLabel for="branch_id" value="Cabang KOPDES Desa *" />
                                <select
                                    id="branch_id"
                                    v-model="form.branch_id"
                                    :disabled="actor?.role === 'admin_cabang'"
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
                                <InputError class="mt-2" :message="form.errors.branch_id" />
                            </div>
                        </div>

                        <!-- Kredensial Kasir (RFID + PIN 6 Digit) -->
                        <div
                            v-if="isCashierRole"
                            class="rounded-md border border-emerald-200 bg-emerald-50/50 p-4 space-y-4"
                        >
                            <div>
                                <h3 class="text-sm font-bold text-emerald-900">
                                    Kredensial Kasir POS (Kartu RFID &amp; PIN 6 Digit)
                                </h3>
                                <p class="mt-0.5 text-xs text-emerald-700">
                                    Kasir login di layar POS menggunakan Tap Kartu RFID dan PIN 6 digit angka.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="card_uid" value="UID Kartu RFID Kasir *" />
                                    <div class="mt-1 flex gap-2">
                                        <TextInput
                                            id="card_uid"
                                            v-model="form.card_uid"
                                            type="text"
                                            class="block w-full font-mono uppercase"
                                            placeholder="KSR3502..."
                                            required
                                        />
                                        <SecondaryButton
                                            type="button"
                                            class="shrink-0"
                                            @click="showRfidModal = true"
                                        >
                                            Scan Kartu
                                        </SecondaryButton>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.card_uid" />
                                </div>

                                <div>
                                    <InputLabel
                                        for="pin"
                                        :value="
                                            isEdit
                                                ? 'PIN 6 Digit Baru (Kosongkan jika tidak diubah)'
                                                : 'PIN 6 Digit Angka *'
                                        "
                                    />
                                    <TextInput
                                        id="pin"
                                        v-model="form.pin"
                                        type="password"
                                        inputmode="numeric"
                                        maxlength="6"
                                        pattern="\d{6}"
                                        class="mt-1 block w-full font-mono tracking-widest"
                                        placeholder="6 digit angka (contoh: 123456)"
                                        :required="!isEdit"
                                    />
                                    <InputError class="mt-2" :message="form.errors.pin" />
                                </div>
                            </div>

                            <div>
                                <InputLabel
                                    for="cashier_email"
                                    value="Email Kasir (Opsional)"
                                />
                                <TextInput
                                    id="cashier_email"
                                    v-model="form.email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    placeholder="Opsional (boleh dikosongkan)"
                                />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>
                        </div>

                        <!-- Kredensial Admin (Email + Password) -->
                        <div
                            v-else
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <InputLabel for="email" value="Email Login Admin *" />
                                <TextInput
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    placeholder="admin@kopdes.id"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div>
                                <InputLabel
                                    for="password"
                                    :value="
                                        isEdit
                                            ? 'Kata Sandi Baru (Kosongkan jika tetap)'
                                            : 'Kata Sandi *'
                                    "
                                />
                                <TextInput
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    placeholder="Minimal 8 karakter"
                                    :required="!isEdit"
                                />
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>
                        </div>

                        <div class="flex items-center">
                            <label class="inline-flex items-center">
                                <Checkbox
                                    name="is_active"
                                    v-model:checked="form.is_active"
                                />
                                <span class="ms-2 text-sm font-medium text-gray-700">
                                    Akun Aktif (Dapat Login dan Beroperasi)
                                </span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                            <Link
                                :href="route('users.index')"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                            >
                                Batal
                            </Link>
                            <PrimaryButton
                                class="bg-red-700 hover:bg-red-800"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ isEdit ? 'Simpan Perubahan' : 'Simpan Pengguna' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <RfidCaptureModal
            :show="showRfidModal"
            title="Scan Kartu RFID Kasir"
            description="Tempelkan kartu RFID kasir pada USB RFID Reader untuk mengikat UID kartu ke akun kasir ini."
            :current-uid="form.card_uid"
            @close="showRfidModal = false"
            @captured="onCardCaptured"
        />
    </AuthenticatedLayout>
</template>
