<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    noPadding: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <section class="kopdes-card overflow-hidden">
        <div
            v-if="title || subtitle || $slots.header || $slots.actions"
            class="flex flex-col gap-2 border-b border-slate-200/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="min-w-0">
                <slot name="header">
                    <h2 v-if="title" class="text-base font-bold tracking-tight text-slate-900">
                        {{ title }}
                    </h2>
                    <p v-if="subtitle" class="mt-0.5 text-xs text-slate-500">
                        {{ subtitle }}
                    </p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <div :class="noPadding ? '' : 'p-5'">
            <slot />
        </div>

        <div
            v-if="$slots.footer"
            class="border-t border-slate-200/80 bg-slate-50/70 px-5 py-3.5"
        >
            <slot name="footer" />
        </div>
    </section>
</template>
