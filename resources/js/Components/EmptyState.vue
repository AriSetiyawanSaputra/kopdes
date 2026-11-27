<script setup>
defineProps({
    title: {
        type: String,
        default: 'Belum ada data',
    },
    description: {
        type: String,
        default: 'Data akan muncul di sini setelah pencatatan atau filter disesuaikan.',
    },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-400">
            <slot name="icon">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-6 w-6"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.6"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
            </slot>
        </div>

        <h3 class="mt-3.5 text-sm font-bold text-slate-900">
            {{ title }}
        </h3>
        <p class="mt-1 max-w-sm text-xs leading-relaxed text-slate-500">
            {{ description }}
        </p>

        <div v-if="$slots.actions" class="mt-4 flex flex-wrap items-center justify-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
