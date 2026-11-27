<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RfidCaptureModal from '@/Components/RfidCaptureModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
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
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        {{
                            isEdit
                                ? `Edit Pengguna: ${targetUser.name}`
                                : 'Tambah Pengguna Admin / Kasir POS'
                        }}
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Sesuai hierarki RBAC KOPDES dan standar keamanan PIN 6 digit &amp; Kartu RFID
                    </p>
                </div>

                <Link
                    :href="route('users.index')"
                    class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                >
                    &larr; Kembali
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="kopdes-card p-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="name" class="block text-xs font-semibold text-slate-700">
                                    Nama Lengkap <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="kopdes-input mt-1"
                                    placeholder="Nama lengkap admin / kasir"
                                    required
                                />
                                <InputError class="mt-1.5" :message="form.errors.name" />
                            </div>

                            <div>
                                <label for="role" class="block text-xs font-semibold text-slate-700">
                                    Peran (Role) <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    id="role"
                                    v-model="form.role"
                                    :disabled="creatableRoles.length === 1"
                                    class="kopdes-input mt-1 cursor-pointer disabled:bg-slate-100"
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
                                <InputError class="mt-1.5" :message="form.errors.role" />
                            </div>
                        </div>

                        <!-- Cakupan Wilayah / Cabang Dinamis Berdasarkan Role -->
                        <div
                            v-if="needsProvince || needsRegency || needsBranch"
                            class="rounded-xl border border-slate-200 bg-slate-50/70 p-4"
                        >
                            <h2 class="text-sm font-bold text-slate-900">
                                Penugasan Wilayah / Cabang KOPDES
                            </h2>

                            <div v-if="needsProvince" class="mt-3">
                                <label for="province_id" class="block text-xs font-semibold text-slate-700">
                                    Provinsi <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    id="province_id"
                                    v-model="form.province_id"
                                    class="kopdes-input mt-1 cursor-pointer"
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
                                <InputError class="mt-1.5" :message="form.errors.province_id" />
                            </div>

                            <div v-if="needsRegency" class="mt-3">
                                <label for="regency_id" class="block text-xs font-semibold text-slate-700">
                                    Kabupaten / Kota <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    id="regency_id"
                                    v-model="form.regency_id"
                                    class="kopdes-input mt-1 cursor-pointer"
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
                                <InputError class="mt-1.5" :message="form.errors.regency_id" />
                            </div>

                            <div v-if="needsBranch" class="mt-3">
                                <label for="branch_id" class="block text-xs font-semibold text-slate-700">
                                    Cabang KOPDES Desa <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    id="branch_id"
                                    v-model="form.branch_id"
                                    :disabled="actor?.role === 'admin_cabang'"
                                    class="kopdes-input mt-1 cursor-pointer disabled:bg-slate-100"
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
                                <InputError class="mt-1.5" :message="form.errors.branch_id" />
                            </div>
                        </div>

                        <!-- Kredensial Kasir (RFID + PIN 6 Digit) -->
                        <div
                            v-if="isCashierRole"
                            class="space-y-4 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4"
                        >
                            <div>
                                <h2 class="text-sm font-bold text-emerald-900">
                                    Kredensial Kasir POS (Kartu RFID &amp; PIN 6 Digit)
                                </h2>
                                <p class="mt-0.5 text-xs text-emerald-700">
                                    Kasir login di layar POS menggunakan Tap Kartu RFID dan PIN 6 digit angka.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="card_uid" class="block text-xs font-semibold text-slate-700">
                                        UID Kartu RFID Kasir <span class="text-rose-600">*</span>
                                    </label>
                                    <div class="mt-1 flex gap-2">
                                        <input
                                            id="card_uid"
                                            v-model="form.card_uid"
                                            type="text"
                                            class="kopdes-input kopdes-num uppercase"
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
                                    <InputError class="mt-1.5" :message="form.errors.card_uid" />
                                </div>

                                <div>
                                    <label for="pin" class="block text-xs font-semibold text-slate-700">
                                        {{
                                            isEdit
                                                ? 'PIN 6 Digit Baru (Kosongkan jika tetap)'
                                                : 'PIN 6 Digit Angka *'
                                        }}
                                    </label>
                                    <input
                                        id="pin"
                                        v-model="form.pin"
                                        type="password"
                                        inputmode="numeric"
                                        maxlength="6"
                                        pattern="\d{6}"
                                        class="kopdes-input kopdes-num mt-1 tracking-widest"
                                        placeholder="6 digit angka (contoh: 123456)"
                                        :required="!isEdit"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.pin" />
                                </div>
                            </div>

                            <div>
                                <label for="cashier_email" class="block text-xs font-semibold text-slate-700">
                                    Email Kasir (Opsional)
                                </label>
                                <input
                                    id="cashier_email"
                                    v-model="form.email"
                                    type="email"
                                    class="kopdes-input mt-1"
                                    placeholder="Opsional (boleh dikosongkan)"
                                />
                                <InputError class="mt-1.5" :message="form.errors.email" />
                            </div>
                        </div>

                        <!-- Kredensial Admin (Email + Password) -->
                        <div
                            v-else
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label for="email" class="block text-xs font-semibold text-slate-700">
                                    Email Login Admin <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    class="kopdes-input mt-1"
                                    placeholder="admin@kopdes.id"
                                    required
                                />
                                <InputError class="mt-1.5" :message="form.errors.email" />
                            </div>

                            <div>
                                <label for="password" class="block text-xs font-semibold text-slate-700">
                                    {{
                                        isEdit
                                            ? 'Kata Sandi Baru (Kosongkan jika tetap)'
                                            : 'Kata Sandi *'
                                    }}
                                </label>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    class="kopdes-input mt-1"
                                    placeholder="Minimal 8 karakter"
                                    :required="!isEdit"
                                />
                                <InputError class="mt-1.5" :message="form.errors.password" />
                            </div>
                        </div>

                        <div class="flex items-center">
                            <label class="inline-flex cursor-pointer items-center">
                                <Checkbox
                                    name="is_active"
                                    v-model:checked="form.is_active"
                                />
                                <span class="ms-2 text-sm font-medium text-slate-700">
                                    Akun Aktif (Dapat Login dan Beroperasi)
                                </span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-2.5 border-t border-slate-200 pt-4">
                            <Link
                                :href="route('users.index')"
                                class="btn-kopdes-secondary"
                            >
                                Batal
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Simpan Pengguna') }}
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
