<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, CheckSquare2, Kanban, LayoutDashboard, Paperclip, Users } from 'lucide-vue-next';

const features = [
    {
        title: 'Boards & cards',
        description: 'Drag-and-drop kanban boards to move work through every stage, from idea to done.',
        icon: Kanban,
        gradient: 'linear-gradient(135deg, #3b82f6, #6366f1)',
    },
    {
        title: 'Checklists & due dates',
        description: 'Break work into steps, set due dates, and get a little celebration when a checklist hits 100%.',
        icon: CheckSquare2,
        gradient: 'linear-gradient(135deg, #10b981, #059669)',
    },
    {
        title: 'Calendar',
        description: 'See every due date and event across all your boards in one shared calendar.',
        icon: CalendarDays,
        gradient: 'linear-gradient(135deg, #f59e0b, #ea580c)',
    },
    {
        title: 'Team collaboration',
        description: 'Invite teammates into a workspace, assign cards, and keep everyone on the same page.',
        icon: Users,
        gradient: 'linear-gradient(135deg, #ec4899, #db2777)',
    },
    {
        title: 'Attachments',
        description: 'Attach files to any card, with inline preview for PDFs and images.',
        icon: Paperclip,
        gradient: 'linear-gradient(135deg, #8b5cf6, #7c3aed)',
    },
    {
        title: 'Dashboard & reports',
        description: 'Track overdue work and progress at a glance, and export a PDF report whenever you need one.',
        icon: LayoutDashboard,
        gradient: 'linear-gradient(135deg, #0ea5e9, #0284c7)',
    },
];
</script>

<template>
    <Head title="ProjectBoard — Organize your team's work" />

    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 text-white">
        <div class="pointer-events-none absolute -left-24 top-1/3 h-72 w-72 rounded-full bg-blue-500/25 blur-3xl" />
        <div class="pointer-events-none absolute right-10 top-10 h-56 w-56 rounded-full bg-sky-400/10 blur-3xl" />
        <div class="pointer-events-none absolute bottom-0 right-1/3 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl" />

        <header class="relative z-10 border-b border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-2.5">
                    <img src="/logo.png" alt="ProjectBoard" class="size-9 rounded-lg" />
                    <span class="text-lg font-semibold tracking-tight text-white">ProjectBoard</span>
                </div>

                <nav class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link :href="route('login')" class="text-sm font-medium text-blue-100/70 transition hover:text-white"> Log in </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                        >
                            Get started
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main class="relative z-10">
            <section class="mx-auto max-w-6xl px-6 py-20 text-center sm:py-28">
                <div class="mx-auto mb-8 flex flex-col items-center gap-4">
                    <img src="/logo.png" alt="ProjectBoard" class="size-20 rounded-2xl shadow-lg shadow-primary/30" />
                    <span class="text-2xl font-semibold tracking-tight text-white">ProjectBoard</span>
                </div>

                <h1 class="mx-auto max-w-2xl text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    Organize your team's work, <span class="text-blue-400">one board at a time</span>
                </h1>
                <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-blue-100/70">
                    Boards, checklists, calendars, and reports — everything your team needs to plan, track, and ship, in one place.
                </p>

                <div class="mt-9 flex items-center justify-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        Go to Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('register')"
                            class="rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                        >
                            Get started free
                        </Link>
                        <Link
                            :href="route('login')"
                            class="rounded-lg border border-white/15 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            Log in
                        </Link>
                    </template>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-6 pb-24">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="feature in features" :key="feature.title" class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur">
                        <div class="mb-4 flex size-10 items-center justify-center rounded-lg" :style="{ backgroundImage: feature.gradient }">
                            <component :is="feature.icon" class="size-5 text-white" />
                        </div>
                        <h3 class="font-semibold text-white">{{ feature.title }}</h3>
                        <p class="mt-1.5 text-sm text-blue-100/60">{{ feature.description }}</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="relative z-10 border-t border-white/10 py-8 text-center text-sm text-blue-100/50">
            &copy; {{ new Date().getFullYear() }} ProjectBoard
        </footer>
    </div>
</template>
