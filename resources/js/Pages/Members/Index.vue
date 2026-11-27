<script setup>
import { reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImportExcelModal from '@/Components/ImportExcelModal.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RfidCaptureModal from '@/Components/RfidCaptureModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
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
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                            Keanggotaan KOPDES
                        </h1>
                        <StatusBadge variant="indigo" size="xs">
                            Portabel Lintas Desa
                        </StatusBadge>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Kartu RFID anggota berlaku nasional untuk berbelanja &amp; akses Portal Mandiri di seluruh cabang KOPDES
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('members.print-cards')"
                        class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                    >
                        Cetak Kartu Batch
                    </Link>
                    <template v-if="permissions.can_create">
                        <button
                            type="button"
                            @click="showImportModal = true"
                            class="btn-kopdes-emerald"
                        >
                            Import Member
                        </button>
                        <Link
                            :href="route('members.create')"
                            class="btn-kopdes-primary !px-3.5 !py-2 !text-xs"
                        >
                            + Registrasi Member Baru
                        </Link>
                    </template>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Filter Bar -->
                <div class="kopdes-card p-5">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <label for="member-search" class="block text-xs font-semibold text-slate-700">
                                Cari Kode / Nama / UID Kartu
                            </label>
                            <input
                                id="member-search"
                                v-model="filterForm.search"
                                type="text"
                                class="kopdes-input mt-1"
                                placeholder="MBR-KOP / Budi / UID..."
                            />
                        </div>

                        <div v-if="branches.length > 1">
                            <label for="member-branch" class="block text-xs font-semibold text-slate-700">
                                KOPDES Asal
                            </label>
                            <select
                                id="member-branch"
                                v-model="filterForm.home_branch_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
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
                            <label for="member-status" class="block text-xs font-semibold text-slate-700">
                                Status Kartu
                            </label>
                            <select
                                id="member-status"
                                v-model="filterForm.status"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <PrimaryButton type="submit">
                                Filter
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="resetFilters">
                                Reset
                            </SecondaryButton>
                        </div>
                    </form>
                </div>

                <!-- Members Table -->
                <div class="kopdes-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            Daftar Anggota KOPDES
                        </h2>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ members.length }} Anggota</span>
                        </StatusBadge>
                    </div>
                    <div v-if="members.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Kode Member
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Nama Anggota &amp; Kontak
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        KOPDES Asal
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        UID Kartu RFID
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Tgl Bergabung
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Status &amp; Portal
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="member in members"
                                    :key="member.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-xs font-bold text-slate-900">
                                        {{ member.member_code }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-900">
                                            {{ member.name }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            HP: <span class="kopdes-num">{{ member.phone || '-' }}</span>
                                            <span v-if="member.nik" class="ms-2">
                                                | NIK: <span class="kopdes-num">{{ member.nik }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-medium text-slate-900">
                                            {{ member.home_branch?.name ?? '-' }}
                                        </div>
                                        <div class="kopdes-num text-xs text-slate-500">
                                            {{ member.home_branch?.code ?? '' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <StatusBadge v-if="member.card_uid" variant="indigo" size="xs">
                                            <span class="kopdes-num">{{ member.card_uid }}</span>
                                        </StatusBadge>
                                        <StatusBadge v-else variant="amber" size="xs">
                                            Belum Binding RFID
                                        </StatusBadge>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-4 py-3.5 text-xs text-slate-600">
                                        {{ member.joined_at }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <StatusBadge :variant="member.status === 'active' ? 'emerald' : 'rose'" size="xs" dot>
                                                {{ member.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                            </StatusBadge>
                                            <StatusBadge :variant="member.has_portal_pin ? 'emerald' : 'slate'" size="xs">
                                                {{ member.has_portal_pin ? 'PIN Portal Aktif' : 'Belum Ada PIN' }}
                                            </StatusBadge>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                        <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                            <Link
                                                :href="route('members.card', member.id)"
                                                class="cursor-pointer rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-100"
                                            >
                                                Cetak Kartu
                                            </Link>
                                            <template v-if="member.can_update">
                                                <button
                                                    type="button"
                                                    @click="openResetPinModal(member)"
                                                    class="cursor-pointer rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 transition-colors hover:bg-emerald-100"
                                                >
                                                    {{ member.has_portal_pin ? 'Reset PIN' : 'Set PIN' }}
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="openReplaceCardModal(member)"
                                                    class="cursor-pointer rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-800 transition-colors hover:bg-indigo-100"
                                                >
                                                    {{ member.card_uid ? 'Ganti RFID' : 'Pasang RFID' }}
                                                </button>
                                                <Link
                                                    :href="route('members.edit', member.id)"
                                                    class="cursor-pointer rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    type="button"
                                                    @click="toggleMemberStatus(member)"
                                                    class="cursor-pointer rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                                    :class="
                                                        member.status === 'active'
                                                            ? 'border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100'
                                                            : 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                                    "
                                                >
                                                    {{ member.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else
                        title="Belum ada anggota KOPDES terdaftar"
                        description="Daftarkan anggota baru atau unggah daftar anggota massal menggunakan fitur Import Excel/CSV."
                    >
                        <template v-if="permissions.can_create" #actions>
                            <Link :href="route('members.create')" class="btn-kopdes-primary !text-xs">
                                + Registrasi Member Baru
                            </Link>
                        </template>
                    </EmptyState>
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
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="text-lg font-extrabold text-slate-900">
                        {{ resetPinMember?.has_portal_pin ? 'Reset PIN Portal Member' : 'Set PIN Portal Member' }}
                    </h3>
                    <p class="mt-1 text-xs text-slate-600">
                        Atur PIN 6 digit angka untuk akses Portal Mandiri Member
                        <strong class="text-slate-900">{{ resetPinMember?.name }}</strong>
                        (<span class="kopdes-num">{{ resetPinMember?.member_code }}</span>).
                        Aksi ini dicatat di Audit Trail tanpa menyimpan nilai PIN.
                    </p>
                </div>

                <form @submit.prevent="submitResetPin" class="mt-5 space-y-4">
                    <div>
                        <label for="reset_member_pin" class="block text-xs font-semibold text-slate-700">
                            PIN Portal Baru (6 Digit Angka) <span class="text-rose-600">*</span>
                        </label>
                        <input
                            id="reset_member_pin"
                            v-model="resetPinForm.pin"
                            type="password"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="\d{6}"
                            autocomplete="new-password"
                            class="kopdes-input kopdes-num mt-1 text-center text-lg font-bold tracking-[0.4em]"
                            placeholder="••••••"
                            required
                        />
                        <InputError class="mt-1.5" :message="resetPinForm.errors.pin" />
                    </div>

                    <div>
                        <label for="reset_member_pin_confirmation" class="block text-xs font-semibold text-slate-700">
                            Konfirmasi PIN Baru <span class="text-rose-600">*</span>
                        </label>
                        <input
                            id="reset_member_pin_confirmation"
                            v-model="resetPinForm.pin_confirmation"
                            type="password"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="\d{6}"
                            autocomplete="new-password"
                            class="kopdes-input kopdes-num mt-1 text-center text-lg font-bold tracking-[0.4em]"
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
