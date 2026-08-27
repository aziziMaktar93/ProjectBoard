<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, Moon, Sun, Sunrise, Users } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();

const hour = new Date().getHours();
const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
const greetingIcon = hour < 12 ? Sunrise : hour < 18 ? Sun : Moon;
const greetingIconColor = hour < 18 ? 'text-amber-300' : 'text-slate-300';
const year = new Date().getFullYear();
</script>

<template>
    <div class="relative flex min-h-svh flex-col overflow-hidden bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 lg:flex-row">
        <div class="pointer-events-none absolute -left-24 top-1/3 h-72 w-72 rounded-full bg-blue-500/25 blur-3xl" />
        <div class="pointer-events-none absolute right-10 top-10 h-56 w-56 rounded-full bg-sky-400/10 blur-3xl" />
        <div class="pointer-events-none absolute bottom-0 right-1/3 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl" />
        <div class="pointer-events-none absolute right-[12%] top-1/2 hidden h-80 w-80 -translate-y-1/2 rounded-full bg-blue-500/20 blur-3xl lg:block" />

        <div
            class="pointer-events-none absolute left-0 top-0 hidden h-64 w-64 opacity-30 lg:block"
            style="
                background-image: radial-gradient(circle, rgba(255, 255, 255, 0.4) 1px, transparent 1px);
                background-size: 18px 18px;
                clip-path: polygon(0 0, 100% 0, 0 100%);
            "
        />
        <div
            class="pointer-events-none absolute bottom-0 right-0 hidden h-64 w-64 opacity-30 lg:block"
            style="
                background-image: radial-gradient(circle, rgba(255, 255, 255, 0.4) 1px, transparent 1px);
                background-size: 18px 18px;
                clip-path: polygon(100% 100%, 100% 0, 0 100%);
            "
        />

        <div class="relative hidden px-10 py-10 lg:flex lg:w-[55%] lg:flex-col lg:justify-between xl:px-16">
            <Link :href="route('home')" class="relative z-10 flex items-center gap-3">
                <img src="/logo.png" alt="ProjectBoard" class="size-9 rounded-lg" />
                <span class="text-lg font-semibold text-white">ProjectBoard</span>
            </Link>

            <div class="pointer-events-none relative z-10 mt-8">
                <div class="grid grid-cols-4 gap-4">
                    <div class="flex flex-col gap-2.5">
                        <div class="flex items-center gap-1.5 px-0.5">
                            <span class="size-2 rounded-full bg-blue-400" />
                            <span class="text-xs font-semibold uppercase tracking-wider text-blue-200/80">To Do</span>
                        </div>
                        <div class="board-float rounded-lg bg-white/10 p-4 ring-1 ring-white/10" style="animation-delay: 0s">
                            <p class="truncate text-sm font-medium text-white/90">Research & Planning</p>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/15">
                                    <div class="h-full rounded-full bg-blue-400" style="width: 35%" />
                                </div>
                                <span class="text-[11px] text-white/40">35%</span>
                            </div>
                            <div class="mt-2 flex items-center gap-1.5 text-xs text-white/40">
                                <Users class="size-3.5" />
                                <span>3</span>
                            </div>
                        </div>
                        <div class="board-float rounded-lg bg-white/5 p-4 ring-1 ring-white/10" style="animation-delay: 0.6s">
                            <p class="truncate text-sm font-medium text-white/90">UI/UX Design</p>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/15">
                                    <div class="h-full rounded-full bg-blue-400" style="width: 60%" />
                                </div>
                                <span class="text-[11px] text-white/40">60%</span>
                            </div>
                            <div class="mt-2 flex items-center gap-1.5 text-xs text-white/40">
                                <Users class="size-3.5" />
                                <span>2</span>
                            </div>
                        </div>
                        <div class="board-float rounded-lg bg-white/5 p-4 ring-1 ring-white/10" style="animation-delay: 1.2s">
                            <p class="truncate text-sm font-medium text-white/90">Requirement Analysis</p>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/15">
                                    <div class="h-full rounded-full bg-blue-400" style="width: 20%" />
                                </div>
                                <span class="text-[11px] text-white/40">20%</span>
                            </div>
                            <div class="mt-2 flex items-center gap-1.5 text-xs text-white/40">
                                <Users class="size-3.5" />
                                <span>2</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-1.5 rounded-lg border border-dashed border-white/15 px-3 py-2 text-left text-xs text-white/30 hover:border-white/30 hover:text-white/50"
                        >
                            + Add task
                        </button>
                    </div>

                    <div class="flex flex-col gap-2.5">
                        <div class="flex items-center gap-1.5 px-0.5">
                            <span class="size-2 rounded-full bg-violet-400" />
                            <span class="text-xs font-semibold uppercase tracking-wider text-violet-200/80">In Progress</span>
                        </div>
                        <div class="board-float rounded-lg bg-white/10 p-4 ring-1 ring-violet-400/20" style="animation-delay: 0.3s">
                            <p class="truncate text-sm font-medium text-white/90">API Integration</p>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/15">
                                    <div class="h-full rounded-full bg-violet-400" style="width: 60%" />
                                </div>
                                <span class="text-[11px] text-white/40">60%</span>
                            </div>
                            <div class="mt-2 flex items-center gap-1.5 text-xs text-white/40">
                                <Users class="size-3.5" />
                                <span>2</span>
                            </div>
                        </div>
                        <div class="board-float rounded-lg bg-white/5 p-4 ring-1 ring-white/10" style="animation-delay: 0.6s">
                            <p class="truncate text-sm font-medium text-white/90">Database Setup</p>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/15">
                                    <div class="h-full rounded-full bg-violet-400" style="width: 40%" />
                                </div>
                                <span class="text-[11px] text-white/40">40%</span>
                            </div>
                            <div class="mt-2 flex items-center gap-1.5 text-xs text-white/40">
                                <Users class="size-3.5" />
                                <span>1</span>
                            </div>
                        </div>
                        <div class="board-float rounded-lg bg-white/5 p-4 ring-1 ring-white/10" style="animation-delay: 0.9s">
                            <p class="truncate text-sm font-medium text-white/90">Frontend Development</p>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/15">
                                    <div class="h-full rounded-full bg-violet-400" style="width: 75%" />
                                </div>
                                <span class="text-[11px] text-white/40">75%</span>
                            </div>
                            <div class="mt-2 flex items-center gap-1.5 text-xs text-white/40">
                                <Users class="size-3.5" />
                                <span>3</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-1.5 rounded-lg border border-dashed border-white/15 px-3 py-2 text-left text-xs text-white/30 hover:border-white/30 hover:text-white/50"
                        >
                            + Add task
                        </button>
                    </div>

                    <div class="flex flex-col gap-2.5">
                        <div class="flex items-center gap-1.5 px-0.5">
                            <span class="size-2 rounded-full bg-amber-400" />
                            <span class="text-xs font-semibold uppercase tracking-wider text-amber-200/80">Review</span>
                        </div>
                        <div class="board-float rounded-lg bg-white/5 p-4 ring-1 ring-amber-400/20" style="animation-delay: 0.45s">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-medium text-white/90">Testing & QA</p>
                                <CheckCircle2 class="size-4 shrink-0 text-emerald-400" />
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/15">
                                    <div class="h-full rounded-full bg-amber-400" style="width: 80%" />
                                </div>
                                <span class="text-[11px] text-white/40">80%</span>
                            </div>
                        </div>
                        <div class="board-float rounded-lg bg-white/5 p-4 ring-1 ring-amber-400/20" style="animation-delay: 0.9s">
                            <p class="truncate text-sm font-medium text-white/90">Code Review</p>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/15">
                                    <div class="h-full rounded-full bg-amber-400" style="width: 40%" />
                                </div>
                                <span class="text-[11px] text-white/40">40%</span>
                            </div>
                            <div class="mt-2 flex items-center gap-1.5 text-xs text-white/40">
                                <Users class="size-3.5" />
                                <span>1</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-1.5 rounded-lg border border-dashed border-white/15 px-3 py-2 text-left text-xs text-white/30 hover:border-white/30 hover:text-white/50"
                        >
                            + Add task
                        </button>
                    </div>

                    <div class="flex flex-col gap-2.5">
                        <div class="flex items-center gap-1.5 px-0.5">
                            <span class="size-2 rounded-full bg-emerald-400" />
                            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-200/80">Done</span>
                        </div>
                        <div
                            class="board-float flex items-center justify-between gap-2 rounded-lg bg-white/5 p-4 ring-1 ring-emerald-400/20"
                            style="animation-delay: 0.3s"
                        >
                            <p class="truncate text-sm font-medium text-white/90">Project Kickoff</p>
                            <CheckCircle2 class="size-4 shrink-0 text-emerald-400" />
                        </div>
                        <div
                            class="board-float flex items-center justify-between gap-2 rounded-lg bg-white/5 p-4 ring-1 ring-emerald-400/20"
                            style="animation-delay: 1.05s"
                        >
                            <p class="truncate text-sm font-medium text-white/90">Environment Setup</p>
                            <CheckCircle2 class="size-4 shrink-0 text-emerald-400" />
                        </div>
                        <button
                            type="button"
                            class="mt-1.5 rounded-lg border border-dashed border-white/15 px-3 py-2 text-left text-xs text-white/30 hover:border-white/30 hover:text-white/50"
                        >
                            + Add task
                        </button>
                    </div>
                </div>
            </div>

            <div class="relative z-10 mt-8 space-y-3">
                <h1 class="flex items-center gap-3 text-3xl font-bold text-white sm:text-4xl">
                    {{ greeting }},
                    <component :is="greetingIcon" class="size-7 shrink-0 sm:size-8" :class="greetingIconColor" />
                </h1>
                <p class="max-w-sm text-sm leading-relaxed text-blue-100/80">
                    Plan sprints, track cards, and ship work together — all in one board.
                </p>
                <div class="h-1 w-24 overflow-hidden rounded-full bg-white/10">
                    <div class="h-full w-2/3 rounded-full bg-blue-400" />
                </div>
            </div>
        </div>

        <div class="relative z-10 flex flex-1 flex-col justify-between px-6 py-10 sm:px-10 lg:px-16">
            <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-6">
                <Link :href="route('home')" class="flex flex-col items-center gap-3 lg:hidden">
                    <img src="/logo.png" alt="ProjectBoard" class="w-12 rounded-lg" />
                    <span class="text-lg font-semibold text-white">ProjectBoard</span>
                </Link>

                <div class="auth-light-panel rounded-2xl bg-white/95 p-6 backdrop-blur sm:p-8">
                    <div class="mb-6 flex flex-col items-center gap-3 text-center">
                        <img src="/logo.png" alt="ProjectBoard" class="size-14 rounded-2xl shadow-lg shadow-primary/30" />
                        <div class="space-y-1">
                            <h2 v-if="title" class="text-xl font-semibold text-neutral-900">{{ title }}</h2>
                            <p v-if="description" class="text-sm text-neutral-500">{{ description }}</p>
                        </div>
                    </div>

                    <slot />
                </div>
            </div>

            <p class="pt-6 text-center text-xs text-blue-100/50">&copy; {{ year }} ProjectBoard. All rights reserved.</p>
        </div>
    </div>
</template>

<style scoped>
.auth-light-panel {
    color: hsl(0 0% 3.9%);
    border: 2px solid rgba(96, 165, 250, 0.7);
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.4),
        0 0 15px rgba(59, 130, 246, 0.7),
        0 0 40px rgba(59, 130, 246, 0.6),
        0 0 80px rgba(59, 130, 246, 0.4),
        0 0 140px rgba(59, 130, 246, 0.2);
    --background: 0 0% 100%;
    --foreground: 0 0% 3.9%;
    --card: 0 0% 100%;
    --card-foreground: 0 0% 3.9%;
    --popover: 0 0% 100%;
    --popover-foreground: 0 0% 3.9%;
    --secondary: 0 0% 92.1%;
    --secondary-foreground: 0 0% 9%;
    --muted: 0 0% 96.1%;
    --muted-foreground: 0 0% 45.1%;
    --accent: 0 0% 96.1%;
    --accent-foreground: 0 0% 9%;
    --border: 0 0% 92.8%;
    --input: 0 0% 89.8%;
}

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
