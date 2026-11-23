<script setup>
import { reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
const replaceCardForm = useForm({
    card_uid: '',
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

                <div v-if="permissions.can_create">
                    <Link
                        :href="route('members.create')"
                        class="inline-flex items-center rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800"
                    >
                        + Registrasi Member Baru
                    </Link>
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
                                            class="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-2 py-0.5 font-mono text-xs font-bold text-blue-800"
                                        >
                                            {{ member.card_uid }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                        {{ member.joined_at }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
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
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div
                                            v-if="member.can_update"
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <button
                                                type="button"
                                                @click="openReplaceCardModal(member)"
                                                class="rounded border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 shadow-sm hover:bg-blue-100"
                                            >
                                                Ganti Kartu RFID
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
                                        </div>
                                        <span
                                            v-else
                                            class="text-xs text-gray-400"
                                        >
                                            Hanya Lihat
                                        </span>
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
    </AuthenticatedLayout>
</template>
