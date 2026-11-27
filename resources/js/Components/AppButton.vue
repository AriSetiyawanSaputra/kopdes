<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: {
        type: String,
        default: 'button',
    },
    variant: {
        type: String,
        default: 'primary',
        validator: (val) =>
            ['primary', 'secondary', 'outline', 'emerald', 'indigo', 'danger', 'ghost'].includes(val),
    },
    size: {
        type: String,
        default: 'md',
        validator: (val) => ['xs', 'sm', 'md', 'lg'].includes(val),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    block: {
        type: Boolean,
        default: false,
    },
});

const variantClasses = computed(() => ({
    primary:
        'border-transparent bg-kopdes-700 text-white shadow-sm hover:bg-kopdes-800 active:bg-kopdes-900 focus:ring-kopdes-500',
    secondary:
        'border-slate-300 bg-white text-slate-700 shadow-sm hover:border-slate-400 hover:bg-slate-50 focus:ring-kopdes-500',
    outline:
        'border-kopdes-200 bg-kopdes-50 text-kopdes-700 shadow-sm hover:border-kopdes-300 hover:bg-kopdes-100 focus:ring-kopdes-500',
    emerald:
        'border-emerald-200 bg-emerald-50 text-emerald-800 shadow-sm hover:border-emerald-300 hover:bg-emerald-100 focus:ring-emerald-500',
    indigo:
        'border-indigo-200 bg-indigo-50 text-indigo-800 shadow-sm hover:border-indigo-300 hover:bg-indigo-100 focus:ring-indigo-500',
    danger:
        'border-transparent bg-rose-600 text-white shadow-sm hover:bg-rose-700 active:bg-rose-800 focus:ring-rose-500',
    ghost:
        'border-transparent bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:ring-slate-400',
})[props.variant]);

const sizeClasses = computed(() => ({
    xs: 'px-2.5 py-1.5 text-xs rounded-lg gap-1.5',
    sm: 'px-3 py-2 text-xs rounded-lg gap-1.5',
    md: 'px-4 py-2.5 text-sm rounded-xl gap-2',
    lg: 'px-5 py-3 text-sm rounded-xl gap-2.5',
})[props.size]);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex cursor-pointer items-center justify-center border font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        :class="[variantClasses, sizeClasses, block ? 'w-full' : '']"
    >
        <svg
            v-if="loading"
            class="h-4 w-4 shrink-0 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <slot />
    </button>
</template>
