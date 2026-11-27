<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    helper: {
        type: String,
        default: '',
    },
    accent: {
        type: String,
        default: 'slate',
        validator: (val) =>
            ['kopdes', 'emerald', 'amber', 'rose', 'indigo', 'slate'].includes(val),
    },
    badge: {
        type: String,
        default: '',
    },
});

const accentStyles = computed(() => ({
    kopdes: {
        bar: 'bg-kopdes-700',
        iconBg: 'bg-kopdes-50 text-kopdes-700 ring-kopdes-200/70',
        value: 'text-slate-900',
        badge: 'bg-kopdes-50 text-kopdes-700 ring-kopdes-200/80',
    },
    emerald: {
        bar: 'bg-emerald-600',
        iconBg: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70',
        value: 'text-emerald-700',
        badge: 'bg-emerald-50 text-emerald-800 ring-emerald-200/80',
    },
    amber: {
        bar: 'bg-amber-500',
        iconBg: 'bg-amber-50 text-amber-700 ring-amber-200/70',
        value: 'text-amber-700',
        badge: 'bg-amber-50 text-amber-800 ring-amber-200/80',
    },
    rose: {
        bar: 'bg-rose-600',
        iconBg: 'bg-rose-50 text-rose-700 ring-rose-200/70',
        value: 'text-rose-700',
        badge: 'bg-rose-50 text-rose-700 ring-rose-200/80',
    },
    indigo: {
        bar: 'bg-indigo-600',
        iconBg: 'bg-indigo-50 text-indigo-700 ring-indigo-200/70',
        value: 'text-indigo-700',
        badge: 'bg-indigo-50 text-indigo-700 ring-indigo-200/80',
    },
    slate: {
        bar: 'bg-slate-700',
        iconBg: 'bg-slate-100 text-slate-700 ring-slate-200/70',
        value: 'text-slate-900',
        badge: 'bg-slate-100 text-slate-700 ring-slate-200/80',
    },
})[props.accent]);
</script>

<template>
    <div class="kopdes-card relative overflow-hidden p-5">
        <div
            class="absolute inset-x-0 top-0 h-1"
            :class="accentStyles.bar"
            aria-hidden="true"
        ></div>

        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    {{ label }}
                </p>
                <p
                    class="kopdes-num mt-2 truncate text-2xl font-extrabold tracking-tight"
                    :class="accentStyles.value"
                >
                    {{ value }}
                </p>
            </div>

            <div
                v-if="$slots.icon"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset"
                :class="accentStyles.iconBg"
            >
                <slot name="icon" />
            </div>
        </div>

        <div
            v-if="helper || badge || $slots.footer"
            class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2.5"
        >
            <span v-if="helper" class="text-xs text-slate-500">
                {{ helper }}
            </span>
            <span
                v-if="badge"
                class="kopdes-num inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset"
                :class="accentStyles.badge"
            >
                {{ badge }}
            </span>
            <slot name="footer" />
        </div>
    </div>
</template>
