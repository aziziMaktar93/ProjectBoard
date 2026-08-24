<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
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

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-2.5">
                    <AppLogoIcon class="size-9 rounded-lg" />
                    <span class="text-lg font-semibold tracking-tight">ProjectBoard</span>
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
                        <Link :href="route('login')" class="text-sm font-medium text-muted-foreground transition hover:text-foreground">
                            Log in
                        </Link>
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

        <main>
            <section class="mx-auto max-w-6xl px-6 py-20 text-center sm:py-28">
                <div class="mx-auto mb-8 flex flex-col items-center gap-4">
                    <AppLogoIcon class="size-20 rounded-2xl shadow-lg shadow-blue-500/20" />
                    <img src="/logo-text.png" alt="ProjectBoard" class="h-10 w-auto" />
                </div>

                <h1 class="mx-auto max-w-2xl text-4xl font-bold tracking-tight sm:text-5xl">
                    Organize your team's work, <span class="text-blue-500">one board at a time</span>
                </h1>
                <p class="mx-auto mt-5 max-w-xl text-lg text-muted-foreground">
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
                            class="rounded-lg border border-border px-6 py-3 text-sm font-semibold transition hover:bg-accent"
                        >
                            Log in
                        </Link>
                    </template>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-6 pb-24">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="feature in features" :key="feature.title" class="rounded-2xl border border-border bg-card p-6">
                        <div class="mb-4 flex size-10 items-center justify-center rounded-lg" :style="{ backgroundImage: feature.gradient }">
                            <component :is="feature.icon" class="size-5 text-white" />
                        </div>
                        <h3 class="font-semibold">{{ feature.title }}</h3>
                        <p class="mt-1.5 text-sm text-muted-foreground">{{ feature.description }}</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-border py-8 text-center text-sm text-muted-foreground">
            &copy; {{ new Date().getFullYear() }} ProjectBoard
        </footer>
    </div>
</template>
