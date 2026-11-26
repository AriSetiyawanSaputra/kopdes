<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import QRCode from 'qrcode';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    members: {
        type: Array,
        default: () => [],
    },
    isBatch: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const appSettings = computed(() => page.props.app_settings || {});
const institutionName = computed(
    () => appSettings.value.institution_name || 'Koperasi Desa Merah Putih',
);
const institutionLogoUrl = computed(() => appSettings.value.institution_logo_url || null);

const qrDataUrls = ref({});

const generateQrs = async () => {
    const map = {};
    for (const member of props.members) {
        try {
            map[member.id] = await QRCode.toDataURL(String(member.member_code), {
                width: 120,
                margin: 1,
                color: {
                    dark: '#111827',
                    light: '#FFFFFF',
                },
            });
        } catch {
            map[member.id] = '';
        }
    }
    qrDataUrls.value = map;
};

watch(() => props.members, generateQrs, { deep: true });
onMounted(generateQrs);

const triggerPrint = () => {
    window.print();
};
</script>

<template>
    <Head title="Cetak Kartu Anggota (Member) KOPDES" />

    <AuthenticatedLayout>
        <template #header>
            <div class="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                        Cetak Kartu Anggota Fisik (ID-1)
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Ukuran standar kartu ID-1 (85.6mm &times; 53.98mm) dengan QR Code kode member (maks 20 kartu per sesi cetak)
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('members.index')" class="btn-kopdes-secondary !px-3.5 !py-2 !text-xs">
                        &larr; Daftar Member
                    </Link>
                    <PrimaryButton
                        type="button"
                        :disabled="members.length === 0"
                        @click="triggerPrint"
                    >
                        Cetak {{ members.length }} Kartu
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div id="member-cards-print-area" class="kopdes-card p-6">
                    <div
                        v-if="members.length > 0"
                        class="member-cards-grid flex flex-wrap justify-center gap-5"
                    >
                        <article
                            v-for="m in members"
                            :key="m.id"
                            class="member-id1-card relative flex flex-col justify-between overflow-hidden rounded-xl border border-slate-300 bg-white shadow-md"
                        >
                            <!-- Header Merah KOPDES -->
                            <div class="flex items-center justify-between bg-gradient-to-r from-kopdes-800 to-kopdes-700 px-3.5 py-2 text-white">
                                <div class="flex min-w-0 items-center gap-2">
                                    <img
                                        v-if="institutionLogoUrl"
                                        :src="institutionLogoUrl"
                                        :alt="institutionName"
                                        class="h-6 w-6 shrink-0 rounded bg-white object-contain p-0.5"
                                    />
                                    <div class="min-w-0">
                                        <div class="truncate text-[10px] font-extrabold uppercase tracking-wider leading-tight">
                                            {{ institutionName }}
                                        </div>
                                        <div class="text-[8px] font-semibold uppercase tracking-widest text-red-100">
                                            KARTU ANGGOTA RESMI
                                        </div>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded bg-white/15 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider">
                                    NASIONAL
                                </span>
                            </div>

                            <!-- Body Kartu: Identitas + QR Code -->
                            <div class="flex flex-1 items-center justify-between gap-3 px-3.5 py-2">
                                <div class="min-w-0 flex-1">
                                    <div class="text-[8px] font-bold uppercase tracking-wider text-slate-500">
                                        Nama Anggota
                                    </div>
                                    <div class="truncate text-sm font-extrabold text-slate-900">
                                        {{ m.name }}
                                    </div>

                                    <div class="mt-1.5 text-[8px] font-bold uppercase tracking-wider text-slate-500">
                                        Kode Member
                                    </div>
                                    <div class="kopdes-num truncate text-[11px] font-extrabold text-kopdes-700">
                                        {{ m.member_code }}
                                    </div>

                                    <div class="mt-1.5 text-[8px] font-bold uppercase tracking-wider text-slate-500">
                                        KOPDES Asal
                                    </div>
                                    <div class="truncate text-[10px] font-semibold text-slate-800">
                                        {{ m.home_branch?.name ?? '-' }}
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col items-center">
                                    <img
                                        v-if="qrDataUrls[m.id]"
                                        :src="qrDataUrls[m.id]"
                                        :alt="`QR ${m.member_code}`"
                                        class="h-[68px] w-[68px] rounded border border-slate-200 bg-white p-0.5"
                                    />
                                    <span class="mt-0.5 text-[7px] font-semibold uppercase tracking-wider text-slate-400">
                                        Scan QR Member
                                    </span>
                                </div>
                            </div>

                            <!-- Footer Kartu -->
                            <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-3.5 py-1 text-[8px] text-slate-600">
                                <span>Bergabung: <strong class="kopdes-num">{{ m.joined_at || '-' }}</strong></span>
                                <span class="font-bold text-kopdes-700">Berlaku di Seluruh KOPDES</span>
                            </div>
                        </article>
                    </div>

                    <p v-else class="py-10 text-center text-sm text-slate-500">
                        Tidak ada data anggota yang dipilih untuk dicetak.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.member-id1-card {
    width: 85.6mm;
    height: 53.98mm;
    box-sizing: border-box;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 8mm;
    }

    body * {
        visibility: hidden !important;
    }

    .no-print {
        display: none !important;
    }

    #member-cards-print-area,
    #member-cards-print-area * {
        visibility: visible !important;
    }

    #member-cards-print-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        background: #ffffff !important;
    }

    .member-cards-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 85.6mm) !important;
        gap: 6mm !important;
        justify-content: center !important;
    }

    .member-id1-card {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        box-shadow: none !important;
    }
}
</style>
