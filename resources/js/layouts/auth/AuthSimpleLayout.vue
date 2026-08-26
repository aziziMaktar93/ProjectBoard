<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { KanbanSquare } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();

const hour = new Date().getHours();
const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
const year = new Date().getFullYear();
</script>

<template>
    <div class="relative flex min-h-svh flex-col bg-neutral-950 lg:flex-row">
        <svg
            class="pointer-events-none absolute inset-y-0 z-20 hidden w-32 lg:block"
            style="left: calc(55% - 4rem)"
            viewBox="0 0 128 1000"
            preserveAspectRatio="none"
            fill="none"
        >
            <path
                d="M96,0 C32,140 32,300 84,440 C136,580 136,720 48,1000"
                stroke="#38bdf8"
                stroke-opacity="0.55"
                stroke-width="18"
                stroke-linecap="round"
                style="filter: blur(10px)"
            />
            <path d="M96,0 C32,140 32,300 84,440 C136,580 136,720 48,1000" stroke="#bfdbfe" stroke-width="2.5" stroke-linecap="round" />
        </svg>

        <div
            class="relative hidden overflow-hidden bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 px-10 py-10 lg:flex lg:w-[55%] lg:flex-col lg:justify-between xl:px-16"
        >
            <div class="pointer-events-none absolute -left-24 top-1/3 h-72 w-72 rounded-full bg-blue-500/25 blur-3xl" />
            <div class="pointer-events-none absolute right-10 top-10 h-56 w-56 rounded-full bg-sky-400/10 blur-3xl" />
            <div class="pointer-events-none absolute bottom-0 right-1/3 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl" />

            <div class="pointer-events-none absolute inset-x-10 top-32 grid grid-cols-3 gap-4 opacity-70 xl:inset-x-16">
                <div class="flex flex-col gap-3">
                    <div class="board-float h-20 rounded-xl bg-white/10 ring-1 ring-white/10" style="animation-delay: 0s" />
                    <div class="board-float h-14 rounded-xl bg-white/10 ring-1 ring-white/10" style="animation-delay: 0.6s" />
                    <div class="board-float h-24 rounded-xl bg-white/5 ring-1 ring-white/10" style="animation-delay: 1.2s" />
                </div>
                <div class="mt-8 flex flex-col gap-3">
                    <div class="board-float h-16 rounded-xl bg-blue-500/15 ring-1 ring-blue-400/20" style="animation-delay: 0.3s" />
                    <div class="board-float h-20 rounded-xl bg-white/10 ring-1 ring-white/10" style="animation-delay: 0.9s" />
                </div>
                <div class="mt-4 flex flex-col gap-3">
                    <div class="board-float h-14 rounded-xl bg-white/10 ring-1 ring-white/10" style="animation-delay: 0.45s" />
                    <div class="board-float h-20 rounded-xl bg-sky-400/15 ring-1 ring-sky-300/20" style="animation-delay: 1.05s" />
                    <div class="board-float h-14 rounded-xl bg-white/5 ring-1 ring-white/10" style="animation-delay: 0.15s" />
                </div>
            </div>

            <Link :href="route('home')" class="relative z-10 flex items-center gap-3">
                <img src="/logo.png" alt="ProjectBoard" class="size-9 rounded-lg" />
                <span class="text-lg font-semibold text-white">ProjectBoard</span>
            </Link>

            <div class="relative z-10 space-y-3">
                <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ greeting }},</h1>
                <p class="max-w-sm text-sm leading-relaxed text-blue-100/80">
                    Plan sprints, track cards, and ship work together — all in one board.
                </p>
            </div>
        </div>

        <div
            class="relative flex flex-1 flex-col justify-between bg-gradient-to-b from-blue-50 via-white to-white px-6 py-10 sm:px-10 lg:px-16 dark:from-neutral-950 dark:via-neutral-950 dark:to-neutral-950"
        >
            <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-6">
                <Link :href="route('home')" class="flex flex-col items-center gap-3 lg:hidden">
                    <img src="/logo.png" alt="ProjectBoard" class="w-12 rounded-lg" />
                    <span class="text-lg font-semibold text-neutral-900 dark:text-white">ProjectBoard</span>
                </Link>

                <div
                    class="rounded-2xl border border-neutral-200 bg-white/90 p-6 shadow-xl shadow-blue-900/5 backdrop-blur sm:p-8 dark:border-neutral-800 dark:bg-neutral-900/90"
                >
                    <div class="mb-6 flex flex-col items-center gap-3 text-center">
                        <span class="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <KanbanSquare class="size-6" />
                        </span>
                        <div class="space-y-1">
                            <h2 v-if="title" class="text-xl font-semibold text-neutral-900 dark:text-white">{{ title }}</h2>
                            <p v-if="description" class="text-sm text-neutral-500 dark:text-neutral-400">{{ description }}</p>
                        </div>
                    </div>

                    <slot />
                </div>
            </div>

            <p class="pt-6 text-center text-xs text-neutral-400 dark:text-neutral-600">&copy; {{ year }} ProjectBoard. All rights reserved.</p>
        </div>
    </div>
</template>

<style scoped>
@keyframes board-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}

.board-float {
    animation: board-float 6s ease-in-out infinite;
}
</style>
