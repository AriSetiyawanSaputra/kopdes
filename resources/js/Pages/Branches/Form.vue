<script setup>
import { computed, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    branch: {
        type: Object,
        default: null,
    },
    provinces: {
        type: Array,
        default: () => [],
    },
    lockedScope: {
        type: Object,
        default: () => ({ province_id: null, regency_id: null }),
    },
});

const isEdit = computed(() => props.branch !== null);

const selectedProvinceId = ref(
    props.branch?.province_id ?? props.lockedScope?.province_id ?? '',
);
const selectedRegencyId = ref(
    props.branch?.regency_id ?? props.lockedScope?.regency_id ?? '',
);
const selectedDistrictId = ref(props.branch?.district_id ?? '');

const regencies = ref([]);
const districts = ref([]);
const villages = ref([]);

const loadingRegencies = ref(false);
const loadingDistricts = ref(false);
const loadingVillages = ref(false);

const form = useForm({
    name: props.branch?.name ?? '',
    code: props.branch?.code ?? '',
    village_id: props.branch?.village_id ?? '',
    address: props.branch?.address ?? '',
    phone: props.branch?.phone ?? '',
    is_active: props.branch?.is_active ?? true,
});

const fetchJson = async (url) => {
    const response = await window.fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return [];
    }

    const json = await response.json();
    return json.data ?? [];
};

const loadRegencies = async (provinceId) => {
    if (!provinceId) {
        regencies.value = [];
        return;
    }

    loadingRegencies.value = true;
    try {
        regencies.value = await fetchJson(
            `${route('regions.regencies')}?province_id=${encodeURIComponent(provinceId)}`,
        );
    } finally {
        loadingRegencies.value = false;
    }
};

const loadDistricts = async (regencyId) => {
    if (!regencyId) {
        districts.value = [];
        return;
    }

    loadingDistricts.value = true;
    try {
        districts.value = await fetchJson(
            `${route('regions.districts')}?regency_id=${encodeURIComponent(regencyId)}`,
        );
    } finally {
        loadingDistricts.value = false;
    }
};

const loadVillages = async (districtId) => {
    if (!districtId) {
        villages.value = [];
        return;
    }

    loadingVillages.value = true;
    try {
        villages.value = await fetchJson(
            `${route('regions.villages')}?district_id=${encodeURIComponent(districtId)}`,
        );
    } finally {
        loadingVillages.value = false;
    }
};

const onProvinceChange = async () => {
    selectedRegencyId.value = '';
    selectedDistrictId.value = '';
    form.village_id = '';
    districts.value = [];
    villages.value = [];
    await loadRegencies(selectedProvinceId.value);
};

const onRegencyChange = async () => {
    selectedDistrictId.value = '';
    form.village_id = '';
    villages.value = [];
    await loadDistricts(selectedRegencyId.value);
};

const onDistrictChange = async () => {
    form.village_id = '';
    await loadVillages(selectedDistrictId.value);
};

const isVillageTakenByOtherBranch = (village) => {
    if (!village.has_branch) {
        return false;
    }

    if (isEdit.value && Number(village.branch_id) === Number(props.branch.id)) {
        return false;
    }

    return true;
};

onMounted(async () => {
    if (selectedProvinceId.value) {
        await loadRegencies(selectedProvinceId.value);
    }

    if (selectedRegencyId.value) {
        await loadDistricts(selectedRegencyId.value);
    }

    if (selectedDistrictId.value) {
        await loadVillages(selectedDistrictId.value);
    }
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('branches.update', props.branch.id));
    } else {
        form.post(route('branches.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Cabang KOPDES' : 'Tambah Cabang KOPDES'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        {{ isEdit ? `Edit Cabang: ${branch.name}` : 'Registrasi Cabang KOPDES Baru' }}
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Setiap Desa hanya dapat memiliki maksimal 1 Cabang KOPDES terdaftar.
                    </p>
                </div>

                <Link
                    :href="route('branches.index')"
                    class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs"
                >
                    &larr; Kembali ke Daftar Cabang
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="kopdes-card p-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Dependent Dropdown Wilayah (4 Tingkat) -->
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <h2 class="text-sm font-bold text-slate-900">
                                1. Wilayah Administratif Desa (Referensi Baku)
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-600">
                                Pilih Provinsi &rarr; Kabupaten/Kota &rarr; Kecamatan &rarr; Desa secara berurutan.
                            </p>

                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="province_id" class="block text-xs font-semibold text-slate-700">
                                        Provinsi <span class="text-rose-600">*</span>
                                    </label>
                                    <select
                                        id="province_id"
                                        v-model="selectedProvinceId"
                                        @change="onProvinceChange"
                                        :disabled="Boolean(lockedScope?.province_id)"
                                        class="kopdes-input mt-1 cursor-pointer disabled:bg-slate-100"
                                        required
                                    >
                                        <option value="">-- Pilih Provinsi --</option>
                                        <option
                                            v-for="prov in provinces"
                                            :key="prov.id"
                                            :value="prov.id"
                                        >
                                            {{ prov.id }} — {{ prov.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label for="regency_id" class="block text-xs font-semibold text-slate-700">
                                        Kabupaten / Kota <span class="text-rose-600">*</span>
                                    </label>
                                    <select
                                        id="regency_id"
                                        v-model="selectedRegencyId"
                                        @change="onRegencyChange"
                                        :disabled="
                                            !selectedProvinceId ||
                                            loadingRegencies ||
                                            Boolean(lockedScope?.regency_id)
                                        "
                                        class="kopdes-input mt-1 cursor-pointer disabled:bg-slate-100"
                                        required
                                    >
                                        <option value="">
                                            {{
                                                loadingRegencies
                                                    ? 'Memuat Kabupaten...'
                                                    : '-- Pilih Kabupaten/Kota --'
                                            }}
                                        </option>
                                        <option
                                            v-for="reg in regencies"
                                            :key="reg.id"
                                            :value="reg.id"
                                        >
                                            {{ reg.id }} — {{ reg.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label for="district_id" class="block text-xs font-semibold text-slate-700">
                                        Kecamatan <span class="text-rose-600">*</span>
                                    </label>
                                    <select
                                        id="district_id"
                                        v-model="selectedDistrictId"
                                        @change="onDistrictChange"
                                        :disabled="!selectedRegencyId || loadingDistricts"
                                        class="kopdes-input mt-1 cursor-pointer disabled:bg-slate-100"
                                        required
                                    >
                                        <option value="">
                                            {{
                                                loadingDistricts
                                                    ? 'Memuat Kecamatan...'
                                                    : '-- Pilih Kecamatan --'
                                            }}
                                        </option>
                                        <option
                                            v-for="dist in districts"
                                            :key="dist.id"
                                            :value="dist.id"
                                        >
                                            {{ dist.id }} — {{ dist.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label for="village_id" class="block text-xs font-semibold text-slate-700">
                                        Desa / Kelurahan <span class="text-rose-600">*</span>
                                    </label>
                                    <select
                                        id="village_id"
                                        v-model="form.village_id"
                                        :disabled="!selectedDistrictId || loadingVillages"
                                        class="kopdes-input mt-1 cursor-pointer disabled:bg-slate-100"
                                        required
                                    >
                                        <option value="">
                                            {{
                                                loadingVillages
                                                    ? 'Memuat Desa...'
                                                    : '-- Pilih Desa/Kelurahan --'
                                            }}
                                        </option>
                                        <option
                                            v-for="vil in villages"
                                            :key="vil.id"
                                            :value="vil.id"
                                        >
                                            {{ vil.id }} — {{ vil.name }}
                                            {{
                                                isVillageTakenByOtherBranch(vil)
                                                    ? `(Sudah Terdaftar: ${vil.branch_code})`
                                                    : ''
                                            }}
                                        </option>
                                    </select>
                                    <InputError class="mt-1.5" :message="form.errors.village_id" />
                                </div>
                            </div>
                        </div>

                        <!-- Identitas Cabang KOPDES -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="name" class="block text-xs font-semibold text-slate-700">
                                    Nama Resmi Cabang KOPDES <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="kopdes-input mt-1"
                                    placeholder="Contoh: KOPDES Desa Ngunut"
                                    required
                                />
                                <InputError class="mt-1.5" :message="form.errors.name" />
                            </div>

                            <div>
                                <label for="code" class="block text-xs font-semibold text-slate-700">
                                    Kode Cabang (Opsional / Otomatis)
                                </label>
                                <input
                                    id="code"
                                    v-model="form.code"
                                    type="text"
                                    class="kopdes-input kopdes-num mt-1 uppercase"
                                    placeholder="Otomatis: KOP-3502-XXX"
                                />
                                <p class="mt-1 text-xs text-slate-500">
                                    Kosongkan agar sistem membuat kode otomatis berformat
                                    <span class="kopdes-num font-semibold text-slate-700">KOP-KODE_KAB-URUT3</span>.
                                </p>
                                <InputError class="mt-1.5" :message="form.errors.code" />
                            </div>
                        </div>

                        <div>
                            <label for="address" class="block text-xs font-semibold text-slate-700">
                                Alamat Lengkap Koperasi Desa <span class="text-rose-600">*</span>
                            </label>
                            <textarea
                                id="address"
                                v-model="form.address"
                                rows="3"
                                class="kopdes-input mt-1"
                                placeholder="Jl. Raya Desa ..., RT/RW ..., Kecamatan ..., Kabupaten ..."
                                required
                            ></textarea>
                            <InputError class="mt-1.5" :message="form.errors.address" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="phone" class="block text-xs font-semibold text-slate-700">
                                    Nomor Telepon Cabang (Opsional)
                                </label>
                                <input
                                    id="phone"
                                    v-model="form.phone"
                                    type="text"
                                    class="kopdes-input kopdes-num mt-1"
                                    placeholder="0352-xxxxxx / 0812xxxxxxxx"
                                />
                                <InputError class="mt-1.5" :message="form.errors.phone" />
                            </div>

                            <div class="flex items-center pt-5">
                                <label class="inline-flex cursor-pointer items-center">
                                    <Checkbox
                                        name="is_active"
                                        v-model:checked="form.is_active"
                                    />
                                    <span class="ms-2 text-xs font-semibold text-slate-700">
                                        Cabang Aktif Beroperasi (Kasir Dapat Login &amp; Transaksi)
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2.5 border-t border-slate-200 pt-4">
                            <Link
                                :href="route('branches.index')"
                                class="btn-kopdes-secondary"
                            >
                                Batal
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Daftarkan Cabang KOPDES') }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
