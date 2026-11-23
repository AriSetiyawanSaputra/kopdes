<script setup>
import { computed, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        {{ isEdit ? `Edit Cabang: ${branch.name}` : 'Registrasi Cabang KOPDES Baru' }}
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Setiap Desa hanya dapat memiliki maksimal 1 Cabang KOPDES terdaftar.
                    </p>
                </div>

                <Link
                    :href="route('branches.index')"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    &larr; Kembali ke Daftar Cabang
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Dependent Dropdown Wilayah (4 Tingkat) -->
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <h3 class="text-sm font-bold text-gray-800">
                                1. Wilayah Administratif Desa (Referensi Baku)
                            </h3>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Pilih Provinsi &rarr; Kabupaten/Kota &rarr; Kecamatan &rarr; Desa secara berurutan.
                            </p>

                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="province_id" value="Provinsi *" />
                                    <select
                                        id="province_id"
                                        v-model="selectedProvinceId"
                                        @change="onProvinceChange"
                                        :disabled="Boolean(lockedScope?.province_id)"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 disabled:bg-gray-100"
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
                                    <InputLabel for="regency_id" value="Kabupaten / Kota *" />
                                    <select
                                        id="regency_id"
                                        v-model="selectedRegencyId"
                                        @change="onRegencyChange"
                                        :disabled="
                                            !selectedProvinceId ||
                                            loadingRegencies ||
                                            Boolean(lockedScope?.regency_id)
                                        "
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 disabled:bg-gray-100"
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
                                    <InputLabel for="district_id" value="Kecamatan *" />
                                    <select
                                        id="district_id"
                                        v-model="selectedDistrictId"
                                        @change="onDistrictChange"
                                        :disabled="!selectedRegencyId || loadingDistricts"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 disabled:bg-gray-100"
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
                                    <InputLabel for="village_id" value="Desa / Kelurahan *" />
                                    <select
                                        id="village_id"
                                        v-model="form.village_id"
                                        :disabled="!selectedDistrictId || loadingVillages"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 disabled:bg-gray-100"
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
                                    <InputError class="mt-2" :message="form.errors.village_id" />
                                </div>
                            </div>
                        </div>

                        <!-- Identitas Cabang KOPDES -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-1">
                                <InputLabel for="name" value="Nama Resmi Cabang KOPDES *" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Contoh: KOPDES Desa Ngunut"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div class="sm:col-span-1">
                                <InputLabel
                                    for="code"
                                    value="Kode Cabang (Opsional / Otomatis)"
                                />
                                <TextInput
                                    id="code"
                                    v-model="form.code"
                                    type="text"
                                    class="mt-1 block w-full font-mono uppercase"
                                    placeholder="Otomatis: KOP-3502-XXX"
                                />
                                <p class="mt-1 text-xs text-gray-500">
                                    Kosongkan agar sistem membuat kode otomatis berformat
                                    <code>KOP-{KODE_KAB}-{URUT3}</code>.
                                </p>
                                <InputError class="mt-2" :message="form.errors.code" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="address" value="Alamat Lengkap Koperasi Desa *" />
                            <textarea
                                id="address"
                                v-model="form.address"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                                placeholder="Jl. Raya Desa ..., RT/RW ..., Kecamatan ..., Kabupaten ..."
                                required
                            ></textarea>
                            <InputError class="mt-2" :message="form.errors.address" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="phone" value="Nomor Telepon Cabang (Opsional)" />
                                <TextInput
                                    id="phone"
                                    v-model="form.phone"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="0352-xxxxxx / 0812xxxxxxxx"
                                />
                                <InputError class="mt-2" :message="form.errors.phone" />
                            </div>

                            <div class="flex items-center pt-6">
                                <label class="inline-flex items-center">
                                    <Checkbox
                                        name="is_active"
                                        v-model:checked="form.is_active"
                                    />
                                    <span class="ms-2 text-sm font-medium text-gray-700">
                                        Cabang Aktif Beroperasi (Kasir Dapat Login &amp; Transaksi)
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                            <Link
                                :href="route('branches.index')"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                            >
                                Batal
                            </Link>
                            <PrimaryButton
                                class="bg-red-700 hover:bg-red-800"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ isEdit ? 'Simpan Perubahan' : 'Daftarkan Cabang KOPDES' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
