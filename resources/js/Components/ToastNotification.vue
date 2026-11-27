<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    flash: {
        type: Object,
        default: () => ({}),
    },
});

const dismissed = ref({
    success: false,
    error: false,
    warning: false,
    info: false,
});

watch(
    () => [props.flash?.success, props.flash?.error, props.flash?.warning, props.flash?.info],
    () => {
        dismissed.value = {
            success: false,
            error: false,
            warning: false,
            info: false,
        };
    },
    { deep: true },
);

const items = computed(() => {
    const list = [];
    if (props.flash?.success && !dismissed.value.success) {
        list.push({
            key: 'success',
            role: 'status',
            title: 'Berhasil',
            message: props.flash.success,
            wrapper: 'border-emerald-200 bg-emerald-50/95 text-emerald-950',
            accent: 'bg-emerald-600',
            iconColor: 'text-emerald-600',
        });
    }
    if (props.flash?.error && !dismissed.value.error) {
        list.push({
            key: 'error',
            role: 'alert',
            title: 'Terjadi Kesalahan',
            message: props.flash.error,
            wrapper: 'border-rose-200 bg-rose-50/95 text-rose-950',
            accent: 'bg-rose-600',
            iconColor: 'text-rose-600',
        });
    }
    if (props.flash?.warning && !dismissed.value.warning) {
        list.push({
            key: 'warning',
            role: 'alert',
            title: 'Perhatian',
            message: props.flash.warning,
            wrapper: 'border-amber-200 bg-amber-50/95 text-amber-950',
            accent: 'bg-amber-500',
            iconColor: 'text-amber-600',
        });
    }
    if (props.flash?.info && !dismissed.value.info) {
        list.push({
            key: 'info',
            role: 'status',
            title: 'Informasi',
            message: props.flash.info,
            wrapper: 'border-indigo-200 bg-indigo-50/95 text-indigo-950',
            accent: 'bg-indigo-600',
            iconColor: 'text-indigo-600',
        });
    }
    return list;
});

const dismiss = (key) => {
    dismissed.value[key] = true;
};
</script>

<template>
    <div
        v-if="items.length > 0"
        class="mx-auto w-full max-w-7xl space-y-2.5 px-4 pt-4 sm:px-6 lg:px-8"
    >
        <div
            v-for="item in items"
            :key="item.key"
            :role="item.role"
            class="animate-fade-in-up relative flex items-start gap-3 overflow-hidden rounded-xl border px-4 py-3.5 shadow-card"
            :class="item.wrapper"
        >
            <div class="absolute inset-y-0 left-0 w-1" :class="item.accent" aria-hidden="true"></div>

            <!-- Semantic SVG Icon -->
            <svg
                v-if="item.key === 'success'"
                xmlns="http://www.w3.org/2000/svg"
                class="mt-0.5 h-5 w-5 shrink-0"
                :class="item.iconColor"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg
                v-else-if="item.key === 'error'"
                xmlns="http://www.w3.org/2000/svg"
                class="mt-0.5 h-5 w-5 shrink-0"
                :class="item.iconColor"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg
                v-else-if="item.key === 'warning'"
                xmlns="http://www.w3.org/2000/svg"
                class="mt-0.5 h-5 w-5 shrink-0"
                :class="item.iconColor"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                class="mt-0.5 h-5 w-5 shrink-0"
                :class="item.iconColor"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>

            <div class="min-w-0 flex-1 text-sm leading-snug">
                <span class="font-bold">{{ item.title }}:</span>
                <span class="ml-1 font-medium">{{ item.message }}</span>
            </div>

            <button
                type="button"
                @click="dismiss(item.key)"
                aria-label="Tutup notifikasi"
                class="cursor-pointer rounded-lg p-1 text-slate-500 transition-colors hover:bg-black/5 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</template>
