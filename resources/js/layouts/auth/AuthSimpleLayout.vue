<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-gradient-to-b from-blue-50 via-white to-white p-6 md:p-10 dark:from-neutral-950 dark:via-neutral-950 dark:to-neutral-950"
    >
        <div
            class="pointer-events-none absolute left-1/2 top-[-120px] h-[420px] w-[420px] -translate-x-1/2 rounded-full bg-blue-400/25 blur-3xl dark:bg-blue-600/20"
        />

        <div class="relative w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link :href="route('home')" class="rounded-2xl bg-white p-4 shadow-md">
                        <img src="/logo-wordmark.png" alt="Trellow" class="w-40" />
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-1 text-center">
                        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">{{ title }}</h1>
                        <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">{{ description }}</p>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xl shadow-blue-900/5 sm:p-8 dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
