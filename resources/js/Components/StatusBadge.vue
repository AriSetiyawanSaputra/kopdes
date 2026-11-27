<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'slate',
        validator: (val) =>
            ['kopdes', 'emerald', 'amber', 'rose', 'indigo', 'slate'].includes(val),
    },
    size: {
        type: String,
        default: 'sm',
        validator: (val) => ['xs', 'sm', 'md'].includes(val),
    },
    dot: {
        type: Boolean,
        default: false,
    },
    pulse: {
        type: Boolean,
        default: false,
    },
});

const styleMap = {
    kopdes: {
        badge: 'bg-kopdes-50 text-kopdes-700 ring-kopdes-200/90',
        dot: 'bg-kopdes-600',
    },
    emerald: {
        badge: 'bg-emerald-50 text-emerald-800 ring-emerald-200/90',
        dot: 'bg-emerald-500',
    },
    amber: {
        badge: 'bg-amber-50 text-amber-800 ring-amber-200/90',
        dot: 'bg-amber-500',
    },
    rose: {
        badge: 'bg-rose-50 text-rose-700 ring-rose-200/90',
        dot: 'bg-rose-500',
    },
    indigo: {
        badge: 'bg-indigo-50 text-indigo-700 ring-indigo-200/90',
        dot: 'bg-indigo-500',
    },
    slate: {
        badge: 'bg-slate-100 text-slate-700 ring-slate-200/90',
        dot: 'bg-slate-500',
    },
};

const sizeClass = computed(() => ({
    xs: 'px-2 py-0.5 text-[10px] gap-1',
    sm: 'px-2.5 py-0.5 text-xs gap-1.5',
    md: 'px-3 py-1 text-xs gap-1.5',
})[props.size]);

const currentStyle = computed(() => styleMap[props.variant] ?? styleMap.slate);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full font-semibold ring-1 ring-inset"
        :class="[currentStyle.badge, sizeClass]"
    >
        <span
            v-if="dot"
            class="h-1.5 w-1.5 shrink-0 rounded-full"
            :class="[currentStyle.dot, pulse ? 'animate-pulse' : '']"
            aria-hidden="true"
        ></span>
        <slot />
    </span>
</template>
