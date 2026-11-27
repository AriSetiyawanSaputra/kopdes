<script setup>
import { computed, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AppPagination from '@/Components/AppPagination.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    actors: {
        type: Array,
        default: () => [],
    },
    actionOptions: {
        type: Array,
        default: () => [],
    },
    drillDown: {
        type: Object,
        default: () => ({
            provinces: [],
            regencies: [],
            branches: [],
            can_filter_province: false,
            can_filter_regency: false,
            can_filter_branch: false,
        }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const filterForm = reactive({
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
    action: props.filters.action ?? '',
    actor_id: props.filters.actor_id ?? '',
    province_id: props.filters.province_id ?? '',
    regency_id: props.filters.regency_id ?? '',
    branch_id: props.filters.branch_id ?? '',
    search: props.filters.search ?? '',
});

const selectedLog = ref(null);

const buildQueryParams = () => ({
    start_date: filterForm.start_date || undefined,
    end_date: filterForm.end_date || undefined,
    action: filterForm.action || undefined,
    actor_id: filterForm.actor_id || undefined,
    province_id:
        props.drillDown.can_filter_province && filterForm.province_id
            ? filterForm.province_id
            : undefined,
    regency_id:
        props.drillDown.can_filter_regency && filterForm.regency_id
            ? filterForm.regency_id
            : undefined,
    branch_id:
        props.drillDown.can_filter_branch && filterForm.branch_id
            ? filterForm.branch_id
            : undefined,
    search: filterForm.search || undefined,
});

const applyFilters = () => {
    router.get(route('activity-logs.index'), buildQueryParams(), {
        preserveState: true,
        replace: true,
    });
};

const onProvinceChange = () => {
    filterForm.regency_id = '';
    filterForm.branch_id = '';
    applyFilters();
};

const onRegencyChange = () => {
    filterForm.branch_id = '';
    applyFilters();
};

const resetFilters = () => {
    router.get(route('activity-logs.index'));
};

const actionBadgeVariant = (action) => {
    if (action?.startsWith('settings') || action?.startsWith('product')) {
        return 'indigo';
    }
    if (action?.includes('return') || action?.includes('force_closed')) {
        return 'rose';
    }
    if (action?.includes('status') || action?.includes('card')) {
        return 'amber';
    }
    return 'slate';
};

const diffRows = computed(() => {
    if (!selectedLog.value) {
        return [];
    }
    const oldObj = selectedLog.value.old_values || {};
    const newObj = selectedLog.value.new_values || {};
    const keys = Array.from(new Set([...Object.keys(oldObj), ...Object.keys(newObj)]));

    return keys.map((key) => ({
        key,
        oldVal: Object.prototype.hasOwnProperty.call(oldObj, key) ? oldObj[key] : undefined,
        newVal: Object.prototype.hasOwnProperty.call(newObj, key) ? newObj[key] : undefined,
    }));
});

const formatDiffValue = (val) => {
    if (val === undefined) {
        return '—';
    }
    if (val === null) {
        return 'null';
    }
    if (typeof val === 'boolean') {
        return val ? 'true' : 'false';
    }
    if (typeof val === 'object') {
        return JSON.stringify(val, null, 2);
    }
    return String(val);
};
</script>

<template>
    <Head title="Audit Trail Admin KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        Audit Trail Aktivitas Admin
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Catatan immutable (append-only) seluruh perubahan harga, aktivasi akun/cabang, force-close shift, retur, dan pengaturan sistem
                    </p>
                </div>
                <StatusBadge variant="slate" dot>
                    Read-Only &bull; Tersensor dari Kredensial
                </StatusBadge>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Filter Bar -->
                <section class="kopdes-card p-5" aria-label="Filter Audit Trail">
                    <form
                        @submit.prevent="applyFilters"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <label for="log-start" class="block text-xs font-semibold text-slate-700">Tanggal Mulai</label>
                            <input id="log-start" v-model="filterForm.start_date" type="date" class="kopdes-input mt-1" />
                        </div>

                        <div>
                            <label for="log-end" class="block text-xs font-semibold text-slate-700">Tanggal Akhir</label>
                            <input id="log-end" v-model="filterForm.end_date" type="date" class="kopdes-input mt-1" />
                        </div>

                        <div>
                            <label for="log-action" class="block text-xs font-semibold text-slate-700">Jenis Aksi</label>
                            <select
                                id="log-action"
                                v-model="filterForm.action"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Jenis Aksi</option>
                                <option v-for="opt in actionOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="log-actor" class="block text-xs font-semibold text-slate-700">Pelaku (Aktor)</label>
                            <select
                                id="log-actor"
                                v-model="filterForm.actor_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Aktor</option>
                                <option v-for="a in actors" :key="a.id" :value="a.id">
                                    {{ a.name }} ({{ a.role }})
                                </option>
                            </select>
                        </div>

                        <div v-if="drillDown.can_filter_province">
                            <label for="log-province" class="block text-xs font-semibold text-slate-700">Provinsi</label>
                            <select
                                id="log-province"
                                v-model="filterForm.province_id"
                                @change="onProvinceChange"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Provinsi / Global</option>
                                <option v-for="prov in drillDown.provinces" :key="prov.id" :value="prov.id">
                                    {{ prov.name }}
                                </option>
                            </select>
                        </div>

                        <div v-if="drillDown.can_filter_regency">
                            <label for="log-regency" class="block text-xs font-semibold text-slate-700">Kabupaten / Kota</label>
                            <select
                                id="log-regency"
                                v-model="filterForm.regency_id"
                                @change="onRegencyChange"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Kabupaten/Kota</option>
                                <option v-for="reg in drillDown.regencies" :key="reg.id" :value="reg.id">
                                    {{ reg.name }}
                                </option>
                            </select>
                        </div>

                        <div v-if="drillDown.can_filter_branch">
                            <label for="log-branch" class="block text-xs font-semibold text-slate-700">Cabang KOPDES</label>
                            <select
                                id="log-branch"
                                v-model="filterForm.branch_id"
                                @change="applyFilters"
                                class="kopdes-input mt-1 cursor-pointer"
                            >
                                <option value="">Semua Cabang</option>
                                <option v-for="b in drillDown.branches" :key="b.id" :value="b.id">
                                    {{ b.code }} — {{ b.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="log-search" class="block text-xs font-semibold text-slate-700">Cari Deskripsi / Aktor</label>
                            <input
                                id="log-search"
                                v-model="filterForm.search"
                                type="text"
                                placeholder="Cari deskripsi aksi..."
                                class="kopdes-input mt-1"
                            />
                        </div>

                        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                            <PrimaryButton type="submit">Terapkan Filter</PrimaryButton>
                            <SecondaryButton type="button" @click="resetFilters">Reset</SecondaryButton>
                        </div>
                    </form>
                </section>

                <!-- Tabel Log Audit -->
                <section class="kopdes-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Riwayat Aktivitas
                        </h2>
                        <StatusBadge variant="slate" size="xs">
                            <span class="kopdes-num">{{ logs.total }} Catatan</span>
                        </StatusBadge>
                    </div>

                    <div v-if="logs.data.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Waktu &amp; IP</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Aktor</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Aksi</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Deskripsi</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Cakupan</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Diff</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="log in logs.data"
                                    :key="log.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <div class="kopdes-num text-xs font-bold text-slate-900">{{ log.created_at }}</div>
                                        <div class="kopdes-num text-[11px] text-slate-500">{{ log.ip_address || '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-900">{{ log.actor?.name ?? 'Sistem' }}</div>
                                        <div class="text-[11px] uppercase text-slate-500">{{ log.actor_role || '-' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <StatusBadge :variant="actionBadgeVariant(log.action)" size="xs">
                                            {{ log.action_label }}
                                        </StatusBadge>
                                        <div class="kopdes-num mt-0.5 text-[10px] text-slate-400">{{ log.action }}</div>
                                    </td>
                                    <td class="max-w-md px-4 py-3.5 text-xs text-slate-700">
                                        {{ log.description }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-xs text-slate-600">
                                        <template v-if="log.branch">
                                            <div class="font-semibold text-slate-900">{{ log.branch.name }}</div>
                                            <div class="kopdes-num text-[11px] text-slate-500">{{ log.branch.code }}</div>
                                        </template>
                                        <span v-else-if="log.regency_name">{{ log.regency_name }}</span>
                                        <span v-else-if="log.province_name">{{ log.province_name }}</span>
                                        <StatusBadge v-else variant="slate" size="xs">
                                            Global / Nasional
                                        </StatusBadge>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                        <button
                                            type="button"
                                            @click="selectedLog = log"
                                            class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                                        >
                                            Inspeksi Diff
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <EmptyState
                        v-else
                        title="Belum ada catatan audit"
                        description="Belum ada aktivitas admin yang tercatat pada periode dan filter yang dipilih."
                    />

                    <!-- Safe Pagination -->
                    <AppPagination
                        :links="logs.links"
                        :from="logs.from ?? 0"
                        :to="logs.to ?? 0"
                        :total="logs.total ?? 0"
                        item-label="catatan audit"
                    />
                </section>
            </div>
        </div>

        <!-- Modal Inspeksi Diff JSON -->
        <Modal :show="Boolean(selectedLog)" max-width="2xl" @close="selectedLog = null">
            <div v-if="selectedLog" class="p-6">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-4">
                    <div>
                        <StatusBadge :variant="actionBadgeVariant(selectedLog.action)" size="xs">
                            {{ selectedLog.action_label }}
                        </StatusBadge>
                        <h3 class="mt-1.5 text-base font-extrabold text-slate-900">
                            {{ selectedLog.description }}
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Pelaku: <strong class="text-slate-800">{{ selectedLog.actor?.name ?? 'Sistem' }}</strong>
                            &bull; <span class="kopdes-num">{{ selectedLog.created_at }}</span> &bull; IP <span class="kopdes-num">{{ selectedLog.ip_address || '-' }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="selectedLog = null"
                        aria-label="Tutup modal"
                        class="cursor-pointer rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3.5 py-2.5 text-left font-bold uppercase tracking-wider text-slate-600">Field</th>
                                <th class="px-3.5 py-2.5 text-left font-bold uppercase tracking-wider text-rose-700">Nilai Lama</th>
                                <th class="px-3.5 py-2.5 text-left font-bold uppercase tracking-wider text-emerald-700">Nilai Baru</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="row in diffRows" :key="row.key">
                                <td class="kopdes-num px-3.5 py-2.5 font-bold text-slate-800">{{ row.key }}</td>
                                <td class="kopdes-num whitespace-pre-wrap bg-rose-50/40 px-3.5 py-2.5 text-rose-800">
                                    {{ formatDiffValue(row.oldVal) }}
                                </td>
                                <td class="kopdes-num whitespace-pre-wrap bg-emerald-50/40 px-3.5 py-2.5 text-emerald-800">
                                    {{ formatDiffValue(row.newVal) }}
                                </td>
                            </tr>
                            <tr v-if="diffRows.length === 0">
                                <td colspan="3" class="px-3.5 py-6 text-center text-slate-500">
                                    Tidak ada rincian perubahan field yang dicatat pada log ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 flex justify-end">
                    <SecondaryButton type="button" @click="selectedLog = null">Tutup</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
