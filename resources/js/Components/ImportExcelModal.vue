<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        required: true, // 'products' | 'stocks' | 'members'
    },
    title: {
        type: String,
        default: 'Import Massal Excel / CSV',
    },
    description: {
        type: String,
        default: 'Unduh template resmi, isi data, lalu unggah file .xlsx atau .csv (maks 2.000 baris, validasi all-or-nothing).',
    },
    branches: {
        type: Array,
        default: () => [],
    },
    requireBranchSelection: {
        type: Boolean,
        default: false,
    },
    defaultBranchId: {
        type: [Number, String],
        default: '',
    },
});

const emit = defineEmits(['close', 'imported']);

const fileInputRef = ref(null);
const selectedFile = ref(null);
const selectedBranchId = ref(props.defaultBranchId ?? '');
const isSubmitting = ref(false);
const generalError = ref('');
const rowErrors = ref([]);
const successMessage = ref('');

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            selectedFile.value = null;
            selectedBranchId.value = props.defaultBranchId || props.branches?.[0]?.id || '';
            generalError.value = '';
            rowErrors.value = [];
            successMessage.value = '';
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
        }
    },
);

const onFileChange = (event) => {
    selectedFile.value = event.target.files?.[0] ?? null;
    generalError.value = '';
    rowErrors.value = [];
};

const submitImport = async () => {
    if (isSubmitting.value) {
        return;
    }

    if (!selectedFile.value) {
        generalError.value = 'Silakan pilih file Excel (.xlsx) atau CSV (.csv) terlebih dahulu.';
        return;
    }

    if (props.requireBranchSelection && !selectedBranchId.value) {
        generalError.value = 'Silakan pilih cabang KOPDES target terlebih dahulu.';
        return;
    }

    isSubmitting.value = true;
    generalError.value = '';
    rowErrors.value = [];
    successMessage.value = '';

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    if (props.requireBranchSelection && selectedBranchId.value) {
        formData.append('branch_id', String(selectedBranchId.value));
    }

    try {
        const response = await window.axios.post(route('imports.store', props.type), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Accept: 'application/json',
            },
        });

        successMessage.value = response.data?.message || 'Import massal berhasil disimpan.';
        emit('imported', response.data);

        router.reload({
            preserveScroll: true,
            onFinish: () => {
                setTimeout(() => {
                    emit('close');
                }, 700);
            },
        });
    } catch (error) {
        const data = error?.response?.data || {};
        const errorsBag = data?.errors || {};

        if (Array.isArray(errorsBag.import_errors)) {
            rowErrors.value = errorsBag.import_errors;
        }

        const firstMsg = Array.isArray(errorsBag.file)
            ? errorsBag.file[0]
            : Array.isArray(errorsBag.branch_id)
                ? errorsBag.branch_id[0]
                : data?.message;

        generalError.value = firstMsg || 'Gagal memproses file import.';
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-extrabold text-slate-900">
                            {{ title }}
                        </h3>
                        <StatusBadge variant="emerald" size="xs">
                            All-or-Nothing
                        </StatusBadge>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-600">
                        {{ description }}
                    </p>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    aria-label="Tutup modal"
                    class="cursor-pointer rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submitImport" class="mt-5 space-y-4">
                <!-- Langkah 1: Unduh Template -->
                <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="kopdes-num flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-kopdes-700 text-xs font-extrabold text-white">
                            1
                        </span>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                Unduh Template Resmi (.xlsx)
                            </p>
                            <p class="mt-0.5 text-xs text-slate-600">
                                Gunakan header kolom sesuai template agar validasi baris berjalan lancar.
                            </p>
                        </div>
                    </div>
                    <a
                        :href="route('imports.template', type)"
                        class="btn-kopdes-emerald shrink-0"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Unduh Template Excel
                    </a>
                </div>

                <!-- Target Cabang (Khusus Admin Nasional pada import stok/member) -->
                <div v-if="requireBranchSelection" class="rounded-xl border border-slate-200 bg-white p-4">
                    <label for="import-branch-id" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Cabang KOPDES Target <span class="text-rose-600">*</span>
                    </label>
                    <select
                        id="import-branch-id"
                        v-model="selectedBranchId"
                        required
                        class="kopdes-input mt-1.5 cursor-pointer"
                    >
                        <option value="">-- Pilih Cabang KOPDES --</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">
                            {{ b.code }} — {{ b.name }}
                        </option>
                    </select>
                </div>

                <!-- Langkah 2: Pilih File -->
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-4">
                    <div class="flex items-center gap-2.5">
                        <span class="kopdes-num flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-kopdes-700 text-xs font-extrabold text-white">
                            2
                        </span>
                        <label for="import-file-input" class="block text-xs font-bold uppercase tracking-wider text-slate-800">
                            Unggah File Excel / CSV (Maks 5MB, 2.000 Baris) <span class="text-rose-600">*</span>
                        </label>
                    </div>
                    <input
                        id="import-file-input"
                        ref="fileInputRef"
                        type="file"
                        accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                        @change="onFileChange"
                        class="mt-3 block w-full cursor-pointer rounded-xl border border-slate-300 bg-white text-sm text-slate-700 file:mr-4 file:cursor-pointer file:border-0 file:bg-kopdes-50 file:px-4 file:py-2.5 file:text-xs file:font-bold file:text-kopdes-700 hover:file:bg-kopdes-100"
                    />
                    <p v-if="selectedFile" class="mt-2 text-xs text-slate-600">
                        File terpilih: <strong class="text-slate-900">{{ selectedFile.name }}</strong>
                    </p>
                </div>

                <!-- Sukses -->
                <div
                    v-if="successMessage"
                    class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs font-semibold text-emerald-800"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ successMessage }}</span>
                </div>

                <!-- Error Umum -->
                <div
                    v-if="generalError"
                    role="alert"
                    class="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-xs font-semibold text-rose-800"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ generalError }}</span>
                </div>

                <!-- Tabel Laporan Error Per Baris (All-or-Nothing) -->
                <div
                    v-if="rowErrors.length > 0"
                    class="overflow-hidden rounded-xl border border-rose-200"
                >
                    <div class="flex items-center justify-between border-b border-rose-200 bg-rose-50 px-3.5 py-2">
                        <span class="text-xs font-bold text-rose-900">
                            Preview Kesalahan Validasi per Baris (Data Dibatalkan Seluruhnya)
                        </span>
                        <StatusBadge variant="rose" size="xs">
                            <span class="kopdes-num">{{ rowErrors.length }} Error</span>
                        </StatusBadge>
                    </div>
                    <div class="max-h-60 overflow-y-auto">
                        <table class="min-w-full divide-y divide-rose-100 text-xs">
                            <thead class="sticky top-0 bg-rose-50/90">
                                <tr>
                                    <th class="px-3 py-2 text-center font-bold text-rose-800">Baris</th>
                                    <th class="px-3 py-2 text-left font-bold text-rose-800">Kolom</th>
                                    <th class="px-3 py-2 text-left font-bold text-rose-800">Keterangan Kesalahan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-rose-100 bg-white">
                                <tr v-for="(err, idx) in rowErrors" :key="idx">
                                    <td class="whitespace-nowrap px-3 py-2 text-center">
                                        <span class="kopdes-num rounded bg-rose-100 px-1.5 py-0.5 font-bold text-rose-800">
                                            #{{ err.row }}
                                        </span>
                                    </td>
                                    <td class="kopdes-num whitespace-nowrap px-3 py-2 font-semibold text-slate-800">
                                        {{ err.field }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">
                                        {{ err.message }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                    <SecondaryButton type="button" :disabled="isSubmitting" @click="emit('close')">
                        Tutup
                    </SecondaryButton>
                    <PrimaryButton type="submit" :disabled="isSubmitting || !selectedFile">
                        {{ isSubmitting ? 'Memvalidasi & Mengimpor...' : 'Mulai Import Massal' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
