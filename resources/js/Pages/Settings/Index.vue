<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    _method: 'put',
    institution_name: props.settings.institution_name ?? 'Koperasi Desa Merah Putih',
    logo: null,
    remove_logo: false,
    receipt_footer: props.settings.receipt_footer ?? 'Terima kasih telah berbelanja di KOPDES Merah Putih.',
    default_receipt_paper: props.settings.default_receipt_paper ?? '58mm',
    return_max_days: Number(props.settings.return_max_days ?? 7),
    shu_allocation_member_usaha: Number(props.settings.shu_allocation_member_usaha ?? 100),
    shu_allocation_reserve: Number(props.settings.shu_allocation_reserve ?? 0),
    shu_allocation_management: Number(props.settings.shu_allocation_management ?? 0),
    shu_allocation_education: Number(props.settings.shu_allocation_education ?? 0),
});

const logoInputRef = ref(null);
const localPreviewUrl = ref(null);

const activeLogoPreview = computed(() => {
    if (localPreviewUrl.value) {
        return localPreviewUrl.value;
    }
    if (form.remove_logo) {
        return null;
    }
    return props.settings.institution_logo_url ?? null;
});

const totalShuPercent = computed(() => {
    const sum =
        Number(form.shu_allocation_member_usaha || 0) +
        Number(form.shu_allocation_reserve || 0) +
        Number(form.shu_allocation_management || 0) +
        Number(form.shu_allocation_education || 0);
    return Math.round(sum * 100) / 100;
});

const isShuTotalValid = computed(() => Math.abs(totalShuPercent.value - 100) < 0.001);

const handleLogoChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.logo = file;
    form.remove_logo = false;

    if (localPreviewUrl.value) {
        URL.revokeObjectURL(localPreviewUrl.value);
        localPreviewUrl.value = null;
    }

    if (file) {
        localPreviewUrl.value = URL.createObjectURL(file);
    }
};

const clearLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    if (localPreviewUrl.value) {
        URL.revokeObjectURL(localPreviewUrl.value);
        localPreviewUrl.value = null;
    }
    if (logoInputRef.value) {
        logoInputRef.value.value = '';
    }
};

const applyStandardShuPreset = () => {
    form.shu_allocation_member_usaha = 40;
    form.shu_allocation_reserve = 30;
    form.shu_allocation_management = 20;
    form.shu_allocation_education = 10;
};

const submit = () => {
    form.post(route('settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.logo = null;
            form.remove_logo = false;
            if (localPreviewUrl.value) {
                URL.revokeObjectURL(localPreviewUrl.value);
                localPreviewUrl.value = null;
            }
            if (logoInputRef.value) {
                logoInputRef.value.value = '';
            }
        },
    });
};
</script>

<template>
    <Head title="Pengaturan Sistem KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        Pengaturan Sistem Dinamis
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Konfigurasi terpusat identitas koperasi, struk thermal, batas hari retur, dan persentase alokasi SHU (khusus Admin Nasional)
                    </p>
                </div>
                <StatusBadge variant="kopdes" dot>
                    Cached &bull; Tercatat di Audit Trail
                </StatusBadge>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Bagian 1: Identitas & Logo Koperasi -->
                    <section class="kopdes-card p-6">
                        <div class="border-b border-slate-200 pb-4">
                            <h2 class="text-base font-bold text-slate-900">
                                1. Identitas Lembaga &amp; Logo Koperasi
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-600">
                                Ditampilkan pada header aplikasi, struk thermal POS, serta kop dokumen ekspor PDF &amp; Excel.
                            </p>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-12">
                            <div class="space-y-4 lg:col-span-7">
                                <div>
                                    <label for="setting-institution-name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                        Nama Lembaga Koperasi <span class="text-rose-600">*</span>
                                    </label>
                                    <input
                                        id="setting-institution-name"
                                        v-model="form.institution_name"
                                        type="text"
                                        maxlength="150"
                                        required
                                        class="kopdes-input mt-1.5"
                                        placeholder="Koperasi Desa Merah Putih"
                                    />
                                    <InputError :message="form.errors.institution_name" class="mt-1.5" />
                                </div>

                                <div>
                                    <label for="setting-logo" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                        Logo Koperasi (PNG, JPG, WebP &bull; Maks 2MB)
                                    </label>
                                    <input
                                        id="setting-logo"
                                        ref="logoInputRef"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        @change="handleLogoChange"
                                        class="mt-1.5 block w-full cursor-pointer rounded-xl border border-slate-300 bg-white text-sm text-slate-700 file:mr-4 file:cursor-pointer file:border-0 file:bg-kopdes-50 file:px-4 file:py-2 file:text-xs file:font-bold file:text-kopdes-700 hover:file:bg-kopdes-100"
                                    />
                                    <InputError :message="form.errors.logo" class="mt-1.5" />
                                </div>
                            </div>

                            <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 lg:col-span-5">
                                <template v-if="activeLogoPreview">
                                    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200/80">
                                        <img
                                            :src="activeLogoPreview"
                                            alt="Pratinjau Logo Koperasi"
                                            class="max-h-24 w-auto object-contain"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        @click="clearLogo"
                                        class="mt-3 cursor-pointer text-xs font-semibold text-rose-700 hover:underline"
                                    >
                                        Hapus Logo Saat Ini
                                    </button>
                                </template>
                                <div v-else class="text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-10 w-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="mt-1.5 text-xs font-medium text-slate-500">
                                        Belum ada logo diunggah
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Bagian 2: Struk Thermal & Kebijakan Retur -->
                    <section class="kopdes-card p-6">
                        <div class="border-b border-slate-200 pb-4">
                            <h2 class="text-base font-bold text-slate-900">
                                2. Pengaturan Struk Thermal &amp; Kebijakan Retur
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-600">
                                Perubahan batas hari retur langsung berlaku pada validasi proses retur di seluruh cabang.
                            </p>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="setting-receipt-footer" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                    Teks Footer Struk Thermal <span class="text-rose-600">*</span>
                                </label>
                                <textarea
                                    id="setting-receipt-footer"
                                    v-model="form.receipt_footer"
                                    rows="2"
                                    maxlength="255"
                                    required
                                    class="kopdes-input mt-1.5"
                                    placeholder="Terima kasih telah berbelanja di KOPDES Merah Putih."
                                ></textarea>
                                <div class="mt-1 flex justify-between text-[11px] text-slate-500">
                                    <span>Tampil di bagian bawah struk penjualan &amp; struk tutup shift.</span>
                                    <span class="kopdes-num">{{ form.receipt_footer.length }}/255</span>
                                </div>
                                <InputError :message="form.errors.receipt_footer" class="mt-1" />
                            </div>

                            <div>
                                <label for="setting-paper" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                    Ukuran Kertas Struk Default <span class="text-rose-600">*</span>
                                </label>
                                <select
                                    id="setting-paper"
                                    v-model="form.default_receipt_paper"
                                    class="kopdes-input mt-1.5 cursor-pointer"
                                >
                                    <option value="58mm">Thermal 58mm (Standar Mini)</option>
                                    <option value="80mm">Thermal 80mm (Lebar)</option>
                                </select>
                                <InputError :message="form.errors.default_receipt_paper" class="mt-1.5" />
                            </div>

                            <div>
                                <label for="setting-return-days" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                    Batas Maksimal Hari Retur (1–90 Hari) <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    id="setting-return-days"
                                    v-model.number="form.return_max_days"
                                    type="number"
                                    min="1"
                                    max="90"
                                    required
                                    class="kopdes-input kopdes-num mt-1.5 font-bold"
                                />
                                <p class="mt-1 text-[11px] text-slate-500">
                                    Jumlah hari kalender sejak transaksi dibayar lunas yang masih diizinkan untuk retur/void.
                                </p>
                                <InputError :message="form.errors.return_max_days" class="mt-1.5" />
                            </div>
                        </div>
                    </section>

                    <!-- Bagian 3: Komposisi Persentase Alokasi SHU -->
                    <section class="kopdes-card p-6">
                        <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">
                                    3. Komposisi Alokasi Sisa Hasil Usaha (SHU)
                                </h2>
                                <p class="mt-0.5 text-xs text-slate-600">
                                    Total penjumlahan 4 pos alokasi SHU <strong>wajib tepat 100%</strong>. Pos Jasa Usaha Anggota menjadi dasar estimasi SHU per anggota.
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="applyStandardShuPreset"
                                class="cursor-pointer self-start rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-kopdes-200 hover:bg-kopdes-50 hover:text-kopdes-700"
                            >
                                Preset Koperasi (40/30/20/10%)
                            </button>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label for="shu-member" class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                    <span class="h-2.5 w-2.5 rounded-full bg-kopdes-700"></span>
                                    Jasa Usaha Anggota (%)
                                </label>
                                <input
                                    id="shu-member"
                                    v-model.number="form.shu_allocation_member_usaha"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    required
                                    class="kopdes-input kopdes-num mt-1.5 font-bold text-kopdes-700"
                                />
                            </div>

                            <div>
                                <label for="shu-reserve" class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                    <span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span>
                                    Dana Cadangan (%)
                                </label>
                                <input
                                    id="shu-reserve"
                                    v-model.number="form.shu_allocation_reserve"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    required
                                    class="kopdes-input kopdes-num mt-1.5 font-bold text-indigo-700"
                                />
                            </div>

                            <div>
                                <label for="shu-mgmt" class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-600"></span>
                                    Pengurus &amp; Pengawas (%)
                                </label>
                                <input
                                    id="shu-mgmt"
                                    v-model.number="form.shu_allocation_management"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    required
                                    class="kopdes-input kopdes-num mt-1.5 font-bold text-emerald-700"
                                />
                            </div>

                            <div>
                                <label for="shu-edu" class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                    <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                    Pendidikan &amp; Sosial (%)
                                </label>
                                <input
                                    id="shu-edu"
                                    v-model.number="form.shu_allocation_education"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    required
                                    class="kopdes-input kopdes-num mt-1.5 font-bold text-amber-700"
                                />
                            </div>
                        </div>

                        <!-- Real-time Stacked Visual Bar & Total Indicator -->
                        <div
                            class="mt-4 rounded-xl border p-4"
                            :class="
                                isShuTotalValid
                                    ? 'border-emerald-200 bg-emerald-50/70'
                                    : 'border-rose-300 bg-rose-50'
                            "
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-bold uppercase tracking-wider"
                                    :class="isShuTotalValid ? 'text-emerald-800' : 'text-rose-800'"
                                >
                                    Total Komposisi Alokasi SHU
                                </span>
                                <span
                                    class="kopdes-num text-lg font-extrabold"
                                    :class="isShuTotalValid ? 'text-emerald-700' : 'text-rose-700'"
                                >
                                    {{ totalShuPercent.toFixed(2) }}% / 100.00%
                                </span>
                            </div>
                            <div class="mt-2.5 flex h-2.5 w-full overflow-hidden rounded-full bg-white/90 ring-1 ring-inset ring-slate-200/80">
                                <div
                                    class="h-full bg-kopdes-700 transition-all duration-200"
                                    :style="{ width: `${Math.max(0, Math.min(100, Number(form.shu_allocation_member_usaha || 0)))}%` }"
                                ></div>
                                <div
                                    class="h-full bg-indigo-600 transition-all duration-200"
                                    :style="{ width: `${Math.max(0, Math.min(100, Number(form.shu_allocation_reserve || 0)))}%` }"
                                ></div>
                                <div
                                    class="h-full bg-emerald-600 transition-all duration-200"
                                    :style="{ width: `${Math.max(0, Math.min(100, Number(form.shu_allocation_management || 0)))}%` }"
                                ></div>
                                <div
                                    class="h-full bg-amber-500 transition-all duration-200"
                                    :style="{ width: `${Math.max(0, Math.min(100, Number(form.shu_allocation_education || 0)))}%` }"
                                ></div>
                            </div>
                            <p v-if="!isShuTotalValid" class="mt-2 text-xs font-semibold text-rose-700">
                                Penjumlahan seluruh pos alokasi SHU harus tepat 100% agar dapat disimpan.
                            </p>
                        </div>
                        <InputError :message="form.errors.shu_allocation_member_usaha" class="mt-2" />
                    </section>

                    <!-- Action Bar -->
                    <div class="flex items-center justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            :disabled="form.processing"
                            @click="form.reset()"
                        >
                            Batalkan Perubahan
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="form.processing || !isShuTotalValid"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan Sistem' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
