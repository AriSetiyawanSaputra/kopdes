<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
    from: {
        type: Number,
        default: 0,
    },
    to: {
        type: Number,
        default: 0,
    },
    total: {
        type: Number,
        default: 0,
    },
    itemLabel: {
        type: String,
        default: 'data',
    },
});

const formatLabel = (rawLabel) => {
    const cleaned = String(rawLabel ?? '')
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&amp;/g, '&')
        .replace(/<[^>]*>/g, '')
        .trim();

    if (/previous/i.test(cleaned)) {
        return '« Sebelumnya';
    }
    if (/next/i.test(cleaned)) {
        return 'Berikutnya »';
    }
    return cleaned;
};
</script>

<template>
    <nav
        v-if="links && links.length > 3"
        aria-label="Navigasi Halaman"
        class="flex flex-col items-center justify-between gap-3 border-t border-slate-200/90 bg-slate-50/70 px-5 py-3.5 sm:flex-row"
    >
        <p class="text-xs text-slate-600">
            Menampilkan
            <span class="kopdes-num font-bold text-slate-900">{{ from ?? 0 }}</span>–<span class="kopdes-num font-bold text-slate-900">{{ to ?? 0 }}</span>
            dari
            <span class="kopdes-num font-bold text-slate-900">{{ total ?? 0 }}</span>
            {{ itemLabel }}
        </p>

        <div class="flex flex-wrap items-center justify-center gap-1">
            <template v-for="(link, idx) in links" :key="idx">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="kopdes-num inline-flex min-w-[32px] cursor-pointer items-center justify-center rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-kopdes-500/40"
                    :class="
                        link.active
                            ? 'border-kopdes-700 bg-kopdes-700 text-white shadow-sm'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-100'
                    "
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ formatLabel(link.label) }}
                </Link>
                <span
                    v-else
                    class="kopdes-num inline-flex min-w-[32px] cursor-not-allowed items-center justify-center rounded-lg border border-slate-200/80 bg-slate-100/80 px-2.5 py-1.5 text-xs font-medium text-slate-400"
                    aria-disabled="true"
                >
                    {{ formatLabel(link.label) }}
                </span>
            </template>
        </div>
    </nav>
</template>
