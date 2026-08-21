<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatTimestamp, sentenceFor } from '@/lib/activitySentence';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BoardTaskCount, BreadcrumbItem, CardActivity, DashboardStats, MemberWorkload } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Clock, Columns3, Kanban, ListChecks, ListFilter, Percent, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    stats: DashboardStats;
    tasksByBoard: BoardTaskCount[];
    tasksByList: BoardTaskCount[] | null;
    workload: MemberWorkload[];
    recentActivity: CardActivity[];
    hasBoards: boolean;
    workspaces: { id: number; name: string }[];
    boards: { id: number; name: string; workspace_id: number }[];
    filters: { workspace_id: number | null; board_id: number | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const maxBoardCount = computed(() => Math.max(1, ...props.tasksByBoard.map((b) => b.count)));
const maxListCount = computed(() => Math.max(1, ...(props.tasksByList ?? []).map((l) => l.count)));
const maxWorkloadCount = computed(() => Math.max(1, ...props.workload.map((w) => w.count)));

function activityLine(activity: CardActivity): string {
    if (activity.type === 'comment') {
        return `commented on ${activity.card?.name ?? 'a card'}`;
    }

    return sentenceFor(activity);
}

const ALL = 'all';
const selectedWorkspace = ref<string>(props.filters.workspace_id ? String(props.filters.workspace_id) : ALL);
const selectedBoard = ref<string>(props.filters.board_id ? String(props.filters.board_id) : ALL);

const availableBoards = computed(() =>
    selectedWorkspace.value === ALL ? props.boards : props.boards.filter((b) => String(b.workspace_id) === selectedWorkspace.value),
);

function fetchDashboard() {
    router.get(
        route('dashboard'),
        {
            workspace_id: selectedWorkspace.value === ALL ? undefined : selectedWorkspace.value,
            board_id: selectedBoard.value === ALL ? undefined : selectedBoard.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onWorkspaceChange(value: string) {
    selectedWorkspace.value = value;

    if (selectedBoard.value !== ALL && !availableBoards.value.some((b) => String(b.id) === selectedBoard.value)) {
        selectedBoard.value = ALL;
    }

    fetchDashboard();
}

function onBoardChange(value: string) {
    selectedBoard.value = value;
    fetchDashboard();
}

const hasActiveFilters = computed(() => selectedWorkspace.value !== ALL || selectedBoard.value !== ALL);

function clearFilters() {
    selectedWorkspace.value = ALL;
    selectedBoard.value = ALL;
    fetchDashboard();
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-lg font-semibold">Dashboard</h1>
                    <p class="text-sm text-muted-foreground">An overview of your active tasks across every board you belong to.</p>
                </div>

                <div
                    v-if="hasBoards"
                    class="flex flex-wrap items-center gap-1.5 rounded-xl border border-black/5 bg-black/[0.03] p-1.5 backdrop-blur-sm dark:border-white/10 dark:bg-white/5"
                >
                    <div class="flex items-center gap-1.5 pl-2 text-xs font-medium text-muted-foreground">
                        <ListFilter class="size-3.5" />
                        Filter
                    </div>

                    <Select :model-value="selectedWorkspace" @update:model-value="onWorkspaceChange">
                        <SelectTrigger
                            class="h-8 w-48 gap-1.5 border-transparent bg-white/80 text-xs shadow-sm transition hover:bg-white dark:bg-neutral-900/80 dark:hover:bg-neutral-900"
                        >
                            <span class="flex min-w-0 items-center gap-1.5 truncate">
                                <Kanban class="size-3.5 shrink-0 text-muted-foreground" />
                                <SelectValue placeholder="All workspaces" />
                            </span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All workspaces</SelectItem>
                            <SelectItem v-for="workspace in workspaces" :key="workspace.id" :value="String(workspace.id)">
                                {{ workspace.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select :model-value="selectedBoard" @update:model-value="onBoardChange">
                        <SelectTrigger
                            class="h-8 w-48 gap-1.5 border-transparent bg-white/80 text-xs shadow-sm transition hover:bg-white dark:bg-neutral-900/80 dark:hover:bg-neutral-900"
                        >
                            <span class="flex min-w-0 items-center gap-1.5 truncate">
                                <Columns3 class="size-3.5 shrink-0 text-muted-foreground" />
                                <SelectValue placeholder="All boards" />
                            </span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All boards</SelectItem>
                            <SelectItem v-for="board in availableBoards" :key="board.id" :value="String(board.id)">
                                {{ board.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Button v-if="hasActiveFilters" variant="ghost" size="sm" class="h-8 gap-1 text-xs" @click="clearFilters">
                        <X class="size-3.5" />
                        Clear
                    </Button>
                </div>
            </div>

            <div v-if="!hasBoards" class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-neutral-300 p-12 text-center dark:border-neutral-700">
                <p class="text-sm text-muted-foreground">No boards yet — create a workspace and board to see your task overview here.</p>
                <Button as-child size="sm">
                    <Link :href="route('workspaces.index')">Go to Workspaces</Link>
                </Button>
            </div>

            <template v-else>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <div
                        class="rounded-xl border border-blue-200/60 bg-gradient-to-br from-blue-50 to-indigo-100 p-4 shadow-sm dark:border-blue-900/40 dark:from-blue-950/70 dark:to-indigo-950/70"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wide text-blue-700/80 dark:text-blue-300/80">Total tasks</span>
                            <div class="rounded-full bg-white/70 p-1.5 dark:bg-white/10">
                                <ListChecks class="size-4 text-blue-600 dark:text-blue-400" />
                            </div>
                        </div>
                        <p class="mt-2 text-2xl font-semibold text-blue-950 dark:text-blue-50">{{ stats.total }}</p>
                    </div>
                    <div
                        class="rounded-xl border border-emerald-200/60 bg-gradient-to-br from-emerald-50 to-teal-100 p-4 shadow-sm dark:border-emerald-900/40 dark:from-emerald-950/70 dark:to-teal-950/70"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wide text-emerald-700/80 dark:text-emerald-300/80">Completed</span>
                            <div class="rounded-full bg-white/70 p-1.5 dark:bg-white/10">
                                <CheckCircle2 class="size-4 text-emerald-600 dark:text-emerald-400" />
                            </div>
                        </div>
                        <p class="mt-2 text-2xl font-semibold text-emerald-950 dark:text-emerald-50">{{ stats.completed }}</p>
                    </div>
                    <div
                        class="rounded-xl border border-red-200/60 bg-gradient-to-br from-red-50 to-rose-100 p-4 shadow-sm dark:border-red-900/40 dark:from-red-950/70 dark:to-rose-950/70"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wide text-red-700/80 dark:text-red-300/80">Overdue</span>
                            <div class="rounded-full bg-white/70 p-1.5 dark:bg-white/10">
                                <AlertTriangle class="size-4 text-red-600 dark:text-red-400" />
                            </div>
                        </div>
                        <p class="mt-2 text-2xl font-semibold text-red-950 dark:text-red-50">{{ stats.overdue }}</p>
                    </div>
                    <div
                        class="rounded-xl border border-amber-200/60 bg-gradient-to-br from-amber-50 to-orange-100 p-4 shadow-sm dark:border-amber-900/40 dark:from-amber-950/70 dark:to-orange-950/70"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wide text-amber-700/80 dark:text-amber-300/80">Due within 7 days</span>
                            <div class="rounded-full bg-white/70 p-1.5 dark:bg-white/10">
                                <Clock class="size-4 text-amber-600 dark:text-amber-400" />
                            </div>
                        </div>
                        <p class="mt-2 text-2xl font-semibold text-amber-950 dark:text-amber-50">{{ stats.dueSoon }}</p>
                    </div>
                    <div
                        class="rounded-xl border border-violet-200/60 bg-gradient-to-br from-violet-50 to-purple-100 p-4 shadow-sm dark:border-violet-900/40 dark:from-violet-950/70 dark:to-purple-950/70"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wide text-violet-700/80 dark:text-violet-300/80">Checklist progress</span>
                            <div class="rounded-full bg-white/70 p-1.5 dark:bg-white/10">
                                <Percent class="size-4 text-violet-600 dark:text-violet-400" />
                            </div>
                        </div>
                        <p class="mt-2 text-2xl font-semibold text-violet-950 dark:text-violet-50">
                            {{ stats.checklistProgress === null ? '—' : `${stats.checklistProgress}%` }}
                        </p>
                        <div v-if="stats.checklistProgress !== null" class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/60 dark:bg-white/10">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-violet-600 to-purple-500"
                                :style="{ width: `${stats.checklistProgress}%` }"
                            />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div
                        v-if="tasksByList"
                        class="rounded-xl border border-teal-200/60 bg-gradient-to-br from-white to-teal-50 p-4 shadow-sm dark:border-teal-900/40 dark:from-neutral-900 dark:to-teal-950/40"
                    >
                        <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Tasks by list</h2>
                        <p v-if="tasksByList.length === 0" class="mt-3 text-sm text-muted-foreground">This board has no active lists.</p>
                        <ul v-else class="mt-3 space-y-3">
                            <li v-for="list in tasksByList" :key="list.name">
                                <div class="mb-1 flex items-center justify-between text-sm">
                                    <span class="truncate text-neutral-700 dark:text-neutral-300">{{ list.name }}</span>
                                    <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ list.count }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-teal-500 to-teal-400"
                                        :style="{ width: `${(list.count / maxListCount) * 100}%` }"
                                    />
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div
                        v-else
                        class="rounded-xl border border-blue-200/60 bg-gradient-to-br from-white to-blue-50 p-4 shadow-sm dark:border-blue-900/40 dark:from-neutral-900 dark:to-blue-950/40"
                    >
                        <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Tasks by board</h2>
                        <p v-if="tasksByBoard.length === 0" class="mt-3 text-sm text-muted-foreground">No active tasks yet.</p>
                        <ul v-else class="mt-3 space-y-3">
                            <li v-for="board in tasksByBoard" :key="board.name">
                                <div class="mb-1 flex items-center justify-between text-sm">
                                    <span class="truncate text-neutral-700 dark:text-neutral-300">{{ board.name }}</span>
                                    <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ board.count }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-blue-500 to-blue-400"
                                        :style="{ width: `${(board.count / maxBoardCount) * 100}%` }"
                                    />
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div
                        class="rounded-xl border border-violet-200/60 bg-gradient-to-br from-white to-violet-50 p-4 shadow-sm dark:border-violet-900/40 dark:from-neutral-900 dark:to-violet-950/40"
                    >
                        <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Workload per member</h2>
                        <p v-if="workload.length === 0" class="mt-3 text-sm text-muted-foreground">No assigned tasks yet.</p>
                        <ul v-else class="mt-3 space-y-3">
                            <li v-for="entry in workload" :key="entry.user.id" class="flex items-center gap-3">
                                <MemberAvatar :user="entry.user" size="xs" />
                                <div class="min-w-0 flex-1">
                                    <div class="mb-1 flex items-center justify-between text-sm">
                                        <span class="truncate text-neutral-700 dark:text-neutral-300">{{ entry.user.name }}</span>
                                        <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ entry.count }}</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-violet-500 to-violet-400"
                                            :style="{ width: `${(entry.count / maxWorkloadCount) * 100}%` }"
                                        />
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-indigo-200/60 bg-gradient-to-br from-white to-indigo-50 p-4 shadow-sm dark:border-indigo-900/40 dark:from-neutral-900 dark:to-indigo-950/40"
                >
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Recent activity</h2>
                    <p v-if="recentActivity.length === 0" class="mt-3 text-sm text-muted-foreground">Nothing has happened yet.</p>
                    <ul v-else class="mt-3 space-y-3">
                        <li v-for="activity in recentActivity" :key="activity.id" class="flex items-start gap-2">
                            <MemberAvatar :user="activity.user" size="xs" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-neutral-700 dark:text-neutral-300">
                                    <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ activity.user.name }}</span>
                                    {{ ' ' }}{{ activityLine(activity) }}
                                    <span v-if="activity.card?.board_list?.board" class="text-muted-foreground">
                                        · {{ activity.card.board_list.board.name }}
                                    </span>
                                </p>
                                <p class="text-xs text-muted-foreground">{{ formatTimestamp(activity.created_at) }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
