<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { confirmDialog } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData, Workspace } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps<{
    workspaces: Workspace[];
}>();

const currentUserId = usePage<SharedData>().props.auth.user.id;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspaces', href: route('workspaces.index') },
    { title: 'Archived', href: route('workspaces.archived') },
];

function restore(workspace: Workspace) {
    router.patch(
        route('workspaces.restore', workspace.id),
        {},
        {
            onSuccess: () => showToast('Workspace restored'),
            onError: () => showToast('Could not restore workspace, try again.', 'error'),
        },
    );
}

async function destroy(workspace: Workspace) {
    if (
        !(await confirmDialog({
            title: `Permanently delete the workspace "${workspace.name}"?`,
            description: 'This deletes all its boards too and cannot be undone.',
            confirmText: 'Delete',
            variant: 'destructive',
        }))
    ) {
        return;
    }

    router.delete(route('workspaces.destroy', workspace.id), {
        onSuccess: () => showToast('Workspace deleted'),
        onError: () => showToast('Could not delete workspace, try again.', 'error'),
    });
}
</script>

<template>
    <Head title="Archived workspaces" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold">Archived workspaces</h1>
                <Button as-child variant="outline" size="sm">
                    <Link :href="route('workspaces.index')">
                        <ArrowLeft class="size-3.5" />
                        Back to workspaces
                    </Link>
                </Button>
            </div>

            <p v-if="workspaces.length === 0" class="text-sm text-muted-foreground">No archived workspaces.</p>

            <ul class="space-y-2">
                <li
                    v-for="workspace in workspaces"
                    :key="workspace.id"
                    class="flex items-center justify-between gap-2 rounded-md border border-neutral-200 bg-card p-3 text-sm shadow-sm dark:border-neutral-700"
                >
                    <span>{{ workspace.name }}</span>
                    <div v-if="workspace.owner_id === currentUserId" class="flex gap-2">
                        <Button variant="ghost" size="sm" @click="restore(workspace)">Restore</Button>
                        <Button variant="ghost" size="sm" @click="destroy(workspace)">Delete permanently</Button>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
