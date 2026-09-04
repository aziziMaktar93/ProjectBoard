<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { showToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BreadcrumbItem, Workspace } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    workspace: Workspace;
    boards: Board[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspaces', href: '/workspaces' },
    { title: props.workspace.name, href: route('workspaces.show', props.workspace.id) },
    { title: 'Archived', href: route('boards.archived', props.workspace.id) },
];

function restore(board: Board) {
    router.patch(
        route('boards.restore', board.id),
        {},
        {
            onSuccess: () => showToast('Board restored'),
            onError: () => showToast('Could not restore board, try again.', 'error'),
        },
    );
}

function destroy(board: Board) {
    if (!confirm(`Permanently delete the board "${board.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(route('boards.destroy', board.id), {
        onSuccess: () => showToast('Board deleted'),
        onError: () => showToast('Could not delete board, try again.', 'error'),
    });
}
</script>

<template>
    <Head title="Archived boards" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold">Archived boards — {{ workspace.name }}</h1>
                <Link :href="route('workspaces.show', workspace.id)" class="text-sm text-muted-foreground underline">Back to workspace</Link>
            </div>

            <p v-if="boards.length === 0" class="text-sm text-muted-foreground">No archived boards.</p>

            <ul class="space-y-2">
                <li v-for="board in boards" :key="board.id" class="flex items-center justify-between gap-2 rounded-md border p-3 text-sm">
                    <span>{{ board.name }}</span>
                    <div class="flex gap-2">
                        <Button variant="ghost" size="sm" @click="restore(board)">Restore</Button>
                        <Button variant="ghost" size="sm" @click="destroy(board)">Delete permanently</Button>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
