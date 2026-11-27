<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RfidCaptureModal from '@/Components/RfidCaptureModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
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
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        {{
                            isEdit
                                ? `Edit Member: ${member.name} (${member.member_code})`
                                : 'Registrasi Anggota (Member) KOPDES Baru'
                        }}
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Kode member dibuat otomatis dan kartu RFID berlaku lintas cabang KOPDES seluruh Indonesia
                    </p>
                </div>

                <Link
                    :href="route('members.index')"
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
                                <label for="home_branch_id" class="block text-xs font-semibold text-slate-700">
                                    KOPDES Asal Pendaftaran <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    id="home_branch_id"
                                    v-model="form.home_branch_id"
                                    :disabled="isBranchLocked"
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
                                <InputError class="mt-1.5" :message="form.errors.home_branch_id" />
                            </div>

                            <div>
                                <label for="joined_at" class="block text-xs font-semibold text-slate-700">
                                    Tanggal Bergabung <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    id="joined_at"
                                    v-model="form.joined_at"
                                    type="date"
                                    class="kopdes-input mt-1"
                                    required
                                />
                                <InputError class="mt-1.5" :message="form.errors.joined_at" />
                            </div>
                        </div>

                        <div>
                            <label for="name" class="block text-xs font-semibold text-slate-700">
                                Nama Lengkap Anggota <span class="text-rose-600">*</span>
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="Contoh: Budi Santoso"
                                required
                            />
                            <InputError class="mt-1.5" :message="form.errors.name" />
                        </div>

                        <!-- Binding Kartu RFID Member (Opsional) -->
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4">
                            <label for="card_uid" class="block text-xs font-bold text-indigo-900">
                                UID Kartu RFID Anggota (Opsional — dapat dipasang nanti)
                            </label>
                            <div class="mt-1.5 flex gap-2">
                                <input
                                    id="card_uid"
                                    v-model="form.card_uid"
                                    type="text"
                                    class="kopdes-input kopdes-num uppercase"
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
                            <p class="mt-1.5 text-xs text-slate-600">
                                Jika diisi, UID kartu otomatis dinormalisasi (huruf kapital alfanumerik) dan dijamin unik lintas Kasir &amp; Member.
                            </p>
                            <InputError class="mt-1.5" :message="form.errors.card_uid" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label for="phone" class="block text-xs font-semibold text-slate-700">
                                    Nomor HP (Opsional)
                                </label>
                                <input
                                    id="phone"
                                    v-model="form.phone"
                                    type="text"
                                    class="kopdes-input kopdes-num mt-1"
                                    placeholder="0812xxxxxxxx"
                                />
                                <InputError class="mt-1.5" :message="form.errors.phone" />
                            </div>

                            <div>
                                <label for="nik" class="block text-xs font-semibold text-slate-700">
                                    NIK Kependudukan (Opsional)
                                </label>
                                <input
                                    id="nik"
                                    v-model="form.nik"
                                    type="text"
                                    maxlength="20"
                                    class="kopdes-input kopdes-num mt-1"
                                    placeholder="16 digit NIK"
                                />
                                <InputError class="mt-1.5" :message="form.errors.nik" />
                            </div>

                            <div>
                                <label for="status" class="block text-xs font-semibold text-slate-700">
                                    Status Kartu <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="kopdes-input mt-1 cursor-pointer"
                                >
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                                <InputError class="mt-1.5" :message="form.errors.status" />
                            </div>
                        </div>

                        <!-- PIN Portal Member Awal (Opsional, khusus saat registrasi baru) -->
                        <div
                            v-if="!isEdit"
                            class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4"
                        >
                            <label for="pin" class="block text-xs font-bold text-emerald-900">
                                PIN Portal Member 6 Digit (Opsional — untuk login Portal Mandiri)
                            </label>
                            <input
                                id="pin"
                                v-model="form.pin"
                                type="password"
                                inputmode="numeric"
                                maxlength="6"
                                pattern="\d{6}"
                                autocomplete="new-password"
                                class="kopdes-input kopdes-num mt-1.5 max-w-xs text-center text-base font-bold tracking-[0.4em]"
                                placeholder="••••••"
                            />
                            <p class="mt-1.5 text-xs text-slate-600">
                                Kosongkan jika belum ingin mengaktifkan akses Portal Member; PIN dapat diatur/direset kapan saja dari daftar Member.
                            </p>
                            <InputError class="mt-1.5" :message="form.errors.pin" />
                        </div>

                        <div class="flex items-center justify-end gap-2.5 border-t border-slate-200 pt-4">
                            <Link
                                :href="route('members.index')"
                                class="btn-kopdes-secondary"
                            >
                                Batal
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Daftarkan Member') }}
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
