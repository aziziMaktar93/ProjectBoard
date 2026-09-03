<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, Kanban, ListFilter } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    workspaces: { id: number; name: string }[];
    boards: { id: number; name: string; workspace_id: number }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

const ALL = 'all';
const selectedWorkspace = ref<string>(ALL);
const selectedBoard = ref<string>(ALL);

const availableBoards = computed(() =>
    selectedWorkspace.value === ALL ? props.boards : props.boards.filter((b) => String(b.workspace_id) === selectedWorkspace.value),
);

function onWorkspaceChange(value: string) {
    selectedWorkspace.value = value;

    if (selectedBoard.value !== ALL && !availableBoards.value.some((b) => String(b.id) === selectedBoard.value)) {
        selectedBoard.value = ALL;
    }
}

function reportUrl(name: string): string {
    return route(name, {
        workspace_id: selectedWorkspace.value === ALL ? undefined : selectedWorkspace.value,
        board_id: selectedBoard.value === ALL ? undefined : selectedBoard.value,
    });
}

const onTimeUrl = computed(() => reportUrl('reports.on-time-completion'));
const memberPerformanceUrl = computed(() => reportUrl('reports.member-performance'));
const activityLogUrl = computed(() => reportUrl('reports.activity-log'));
const activityLogCsvUrl = computed(() => reportUrl('reports.activity-log-csv'));
const checklistTimelineUrl = computed(() => reportUrl('reports.checklist-timeline'));
const progressUrl = computed(() => reportUrl('reports.progress'));

const reportCards = computed(() => [
    { title: 'Progress %', description: 'Checklist completion percentage per board and per card.', href: progressUrl.value },
    { title: 'On-Time vs Late Completion', description: 'Checklist items compared against their due date.', href: onTimeUrl.value },
    { title: 'Member Performance', description: 'Completed, overdue, and average days late per member.', href: memberPerformanceUrl.value },
    { title: 'Checklist Completion Timeline', description: 'Every checklist item grouped by board, card, and checklist.', href: checklistTimelineUrl.value },
]);
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-lg font-semibold">Reports</h1>
                    <p class="text-sm text-muted-foreground">Download reports across every board you belong to.</p>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 rounded-xl border border-black/5 bg-black/[0.03] p-1.5 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-center gap-1.5 pl-2 text-xs font-medium text-muted-foreground">
                        <ListFilter class="size-3.5" />
                        Scope
                    </div>

                    <Select :model-value="selectedWorkspace" @update:model-value="onWorkspaceChange">
                        <SelectTrigger class="h-8 w-48 gap-1.5 text-xs">
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

                    <Select :model-value="selectedBoard" @update:model-value="(value) => (selectedBoard = String(value))">
                        <SelectTrigger class="h-8 w-48 text-xs">
                            <SelectValue placeholder="All boards" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All boards</SelectItem>
                            <SelectItem v-for="board in availableBoards" :key="board.id" :value="String(board.id)">
                                {{ board.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-border p-4">
                    <h3 class="font-semibold">Dashboard Overview</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Overall stats, tasks by board, and workload — the general report.</p>
                    <Button as-child class="mt-3" variant="outline">
                        <Link :href="route('dashboard')">Go to Dashboard</Link>
                    </Button>
                </div>

                <div v-for="card in reportCards" :key="card.title" class="rounded-xl border border-border p-4">
                    <h3 class="font-semibold">{{ card.title }}</h3>
                    <p class="mt-1 text-sm text-muted-foreground">{{ card.description }}</p>
                    <Button as-child class="mt-3">
                        <a :href="card.href"><Download class="size-3.5" /> Download PDF</a>
                    </Button>
                </div>

                <div class="rounded-xl border border-border p-4">
                    <h3 class="font-semibold">Activity Log</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Every logged card activity in scope.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button as-child>
                            <a :href="activityLogUrl"><Download class="size-3.5" /> PDF</a>
                        </Button>
                        <Button as-child variant="secondary" class="bg-emerald-600 text-white hover:bg-emerald-700">
                            <a :href="activityLogCsvUrl"><FileSpreadsheet class="size-3.5" /> CSV</a>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
