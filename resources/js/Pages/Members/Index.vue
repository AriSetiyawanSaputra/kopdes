<script setup>
import { reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImportExcelModal from '@/Components/ImportExcelModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RfidCaptureModal from '@/Components/RfidCaptureModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    members: {
        type: Array,
        required: true,
    },
    branches: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    permissions: {
        type: Object,
        default: () => ({ can_create: false }),
    },
});

const filterForm = reactive({
    search: props.filters.search ?? '',
    home_branch_id: props.filters.home_branch_id ?? '',
    status: props.filters.status ?? '',
});

const replaceCardMember = ref(null);
const resetPinMember = ref(null);
const showImportModal = ref(false);
const replaceCardForm = useForm({
    card_uid: '',
});
const resetPinForm = useForm({
    pin: '',
    pin_confirmation: '',
});

const applyFilters = () => {
    router.get(
        route('members.index'),
        {
            search: filterForm.search || undefined,
            home_branch_id: filterForm.home_branch_id || undefined,
            status: filterForm.status || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.home_branch_id = '';
    filterForm.status = '';
    router.get(route('members.index'));
};

const openReplaceCardModal = (member) => {
    replaceCardForm.clearErrors();
    replaceCardForm.card_uid = '';
    replaceCardMember.value = member;
};

const closeReplaceCardModal = () => {
    replaceCardMember.value = null;
    replaceCardForm.reset();
};

const onReplaceCardCaptured = (normalizedUid) => {
    if (!replaceCardMember.value) {
        return;
    }

    replaceCardForm.card_uid = normalizedUid;
    replaceCardForm.patch(
        route('members.replace-card', replaceCardMember.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeReplaceCardModal();
            },
        },
    );
};

const openResetPinModal = (member) => {
    resetPinForm.clearErrors();
    resetPinForm.reset();
    resetPinMember.value = member;
};

const closeResetPinModal = () => {
    resetPinMember.value = null;
    resetPinForm.reset();
    resetPinForm.clearErrors();
};

const submitResetPin = () => {
    if (!resetPinMember.value) {
        return;
    }

    resetPinForm.post(route('members.reset-pin', resetPinMember.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeResetPinModal();
        },
    });
};

const toggleMemberStatus = (member) => {
    const actionLabel =
        member.status === 'active' ? 'menonaktifkan' : 'mengaktifkan';
    if (
        !window.confirm(
            `Apakah Anda yakin ingin ${actionLabel} kartu anggota ${member.name} (${member.member_code})?`,
        )
    ) {
        return;
    }

    router.patch(
        route('members.toggle-status', member.id),
        {},
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Manajemen Anggota (Member) KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Keanggotaan KOPDES (Portabel Lintas Desa)
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Kartu RFID anggota berlaku nasional untuk berbelanja di seluruh cabang KOPDES
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('members.print-cards')"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        Cetak Kartu Batch
                    </Link>
                    <template v-if="permissions.can_create">
                        <button
                            type="button"
                            @click="showImportModal = true"
                            class="btn-kopdes-emerald !py-2"
                        >
                            Import Member (Excel/CSV)
                        </button>
                        <Link
                            :href="route('members.create')"
                            class="inline-flex items-center rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800"
                        >
                            + Registrasi Member Baru
                        </Link>
                    </template>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <!-- Filter Bar -->
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Cari Kode / Nama / UID Kartu
                            </label>
                            <TextInput
                                v-model="filterForm.search"
                                type="text"
                                class="mt-1 block w-full text-sm"
                                placeholder="MBR-KOP / Budi / UID..."
                            />
                        </div>

                        <div v-if="branches.length > 1">
                            <label class="block text-xs font-semibold text-gray-700">
                                KOPDES Asal (`home_branch_id`)
                            </label>
                            <select
                                v-model="filterForm.home_branch_id"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="">Semua KOPDES Asal</option>
                                <option
                                    v-for="b in branches"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.code }} — {{ b.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">
                                Status Kartu
                            </label>
                            <select
                                v-model="filterForm.status"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <PrimaryButton
                                type="submit"
                                class="bg-red-700 hover:bg-red-800"
                            >
                                Filter
                            </PrimaryButton>
                            <SecondaryButton
                                type="button"
                                @click="resetFilters"
                            >
                                Reset
                            </SecondaryButton>
                        </div>
                    </form>
                </div>

                <!-- Members Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Kode Member
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Nama Anggota &amp; Kontak
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        KOPDES Asal
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        UID Kartu RFID
                                    </th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">
                                        Tgl Bergabung
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                        Status
                                    </th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="member in members"
                                    :key="member.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-bold text-gray-900">
                                        {{ member.member_code }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">
                                            {{ member.name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            HP: {{ member.phone || '-' }}
                                            <span v-if="member.nik" class="ms-2">
                                                | NIK: {{ member.nik }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">
                                            {{ member.home_branch?.name ?? '-' }}
                                        </div>
                                        <div class="font-mono text-xs text-gray-500">
                                            {{ member.home_branch?.code ?? '' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span
                                            v-if="member.card_uid"
                                            class="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-2 py-0.5 font-mono text-xs font-bold text-blue-800"
                                        >
                                            {{ member.card_uid }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-800"
                                        >
                                            Belum Binding RFID
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                        {{ member.joined_at }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <span
                                                v-if="member.status === 'active'"
                                                class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800"
                                            >
                                                Aktif
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800"
                                            >
                                                Nonaktif
                                            </span>
                                            <span
                                                v-if="member.has_portal_pin"
                                                class="inline-flex items-center rounded border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700"
                                            >
                                                PIN Portal Aktif
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-500"
                                            >
                                                Belum Ada PIN
                                            </span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <Link
                                                :href="route('members.card', member.id)"
                                                class="rounded border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-100"
                                            >
                                                Cetak Kartu
                                            </Link>
                                            <template v-if="member.can_update">
                                                <button
                                                    type="button"
                                                    @click="openResetPinModal(member)"
                                                    class="rounded border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100"
                                                >
                                                    {{ member.has_portal_pin ? 'Reset PIN' : 'Set PIN' }}
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="openReplaceCardModal(member)"
                                                    class="rounded border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 shadow-sm hover:bg-blue-100"
                                                >
                                                    {{ member.card_uid ? 'Ganti Kartu RFID' : 'Pasang Kartu RFID' }}
                                                </button>
                                                <Link
                                                    :href="route('members.edit', member.id)"
                                                    class="rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    type="button"
                                                    @click="toggleMemberStatus(member)"
                                                    class="rounded px-2.5 py-1 text-xs font-semibold shadow-sm"
                                                    :class="
                                                        member.status === 'active'
                                                            ? 'border border-red-200 bg-red-50 text-red-700 hover:bg-red-100'
                                                            : 'border border-green-200 bg-green-50 text-green-700 hover:bg-green-100'
                                                    "
                                                >
                                                    {{ member.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="members.length === 0">
                                    <td
                                        colspan="7"
                                        class="px-4 py-8 text-center text-sm text-gray-500"
                                    >
                                        Belum ada anggota KOPDES yang terdaftar pada cakupan wilayah ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <RfidCaptureModal
            :show="replaceCardMember !== null"
            :title="
                replaceCardMember
                    ? `Ganti Kartu RFID: ${replaceCardMember.name}`
                    : 'Ganti Kartu RFID Member'
            "
            description="Tempelkan kartu RFID pengganti yang baru. UID kartu lama akan langsung dinonaktifkan dan digantikan."
            :loading="replaceCardForm.processing"
            :error-message="replaceCardForm.errors.card_uid"
            @close="closeReplaceCardModal"
            @captured="onReplaceCardCaptured"
        />
        <Modal :show="resetPinMember !== null" max-width="md" @close="closeResetPinModal">
            <div class="p-6">
                <h3 class="text-lg font-bold text-slate-900">
                    {{ resetPinMember?.has_portal_pin ? 'Reset PIN Portal Member' : 'Set PIN Portal Member' }}
                </h3>
                <p class="mt-1 text-xs text-slate-600">
                    Atur PIN 6 digit angka untuk akses Portal Mandiri Member
                    <strong class="text-slate-900">{{ resetPinMember?.name }}</strong>
                    (<span class="kopdes-num">{{ resetPinMember?.member_code }}</span>).
                    Aksi ini dicatat di Audit Trail tanpa menyimpan nilai PIN.
                </p>

                <form @submit.prevent="submitResetPin" class="mt-5 space-y-4">
                    <div>
                        <InputLabel for="reset_member_pin" value="PIN Portal Baru (6 Digit Angka) *" />
                        <TextInput
                            id="reset_member_pin"
                            v-model="resetPinForm.pin"
                            type="password"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="\d{6}"
                            autocomplete="new-password"
                            class="kopdes-num mt-1 block w-full text-center text-lg font-bold tracking-[0.4em]"
                            placeholder="••••••"
                            required
                        />
                        <InputError class="mt-1.5" :message="resetPinForm.errors.pin" />
                    </div>

                    <div>
                        <InputLabel for="reset_member_pin_confirmation" value="Konfirmasi PIN Baru *" />
                        <TextInput
                            id="reset_member_pin_confirmation"
                            v-model="resetPinForm.pin_confirmation"
                            type="password"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="\d{6}"
                            autocomplete="new-password"
                            class="kopdes-num mt-1 block w-full text-center text-lg font-bold tracking-[0.4em]"
                            placeholder="••••••"
                            required
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                        <SecondaryButton type="button" @click="closeResetPinModal">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton :disabled="resetPinForm.processing">
                            {{ resetPinForm.processing ? 'Menyimpan...' : 'Simpan PIN Portal' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <ImportExcelModal
            :show="showImportModal"
            type="members"
            title="Import Massal Anggota (Member) KOPDES"
            description="Kolom card_uid bersifat opsional (boleh kosong untuk di-binding RFID nanti). Validasi all-or-nothing."
            :branches="branches"
            :require-branch-selection="Boolean(permissions.is_admin_nasional)"
            :default-branch-id="filterForm.home_branch_id || branches?.[0]?.id || ''"
            @close="showImportModal = false"
        />
    </AuthenticatedLayout>
</template>
