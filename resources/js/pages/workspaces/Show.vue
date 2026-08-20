<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkspaceMemberPanel from '@/components/boards/WorkspaceMemberPanel.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BreadcrumbItem, SharedData, User, Workspace } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

const props = defineProps<{
    workspace: Workspace;
    boards: Board[];
    members: User[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Workspaces', href: route('workspaces.index') },
    { title: props.workspace.name, href: route('workspaces.show', props.workspace.id) },
]);

const currentUserId = usePage<SharedData>().props.auth.user.id;
const isOwner = computed(() => props.workspace.owner_id === currentUserId);

const showCreateBoard = ref(false);

const boardForm = useForm({
    name: '',
    background_color: '#0079BF',
});

function submitBoard() {
    boardForm.post(route('workspaces.boards.store', props.workspace.id), {
        onSuccess: () => {
            showCreateBoard.value = false;
            boardForm.reset();
        },
    });
}

const showMembers = ref(false);

const isEditingWorkspaceName = ref(false);
const workspaceNameInput = ref<HTMLInputElement | null>(null);

const workspaceNameForm = useForm({
    name: props.workspace.name,
});

async function startEditingWorkspaceName() {
    workspaceNameForm.name = props.workspace.name;
    isEditingWorkspaceName.value = true;
    await nextTick();
    workspaceNameInput.value?.focus();
    workspaceNameInput.value?.select();
}

function saveWorkspaceName() {
    isEditingWorkspaceName.value = false;

    if (!workspaceNameForm.name.trim() || workspaceNameForm.name === props.workspace.name) {
        workspaceNameForm.name = props.workspace.name;
        return;
    }

    workspaceNameForm.patch(route('workspaces.update', props.workspace.id), { preserveScroll: true });
}

function deleteWorkspace() {
    if (!confirm(`Delete the workspace "${props.workspace.name}"? This permanently deletes all its boards too.`)) {
        return;
    }

    router.delete(route('workspaces.destroy', props.workspace.id));
}
</script>

<template>
    <Head :title="workspace.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div class="flex min-w-0 items-center gap-2">
                    <input
                        v-if="isEditingWorkspaceName"
                        ref="workspaceNameInput"
                        v-model="workspaceNameForm.name"
                        class="min-w-0 rounded bg-transparent px-1 text-lg font-semibold outline-none ring-2 ring-ring"
                        @blur="saveWorkspaceName"
                        @keydown.enter="saveWorkspaceName"
                        @keydown.escape="isEditingWorkspaceName = false"
                    />
                    <h1
                        v-else
                        class="min-w-0 truncate rounded px-1 text-lg font-semibold"
                        :class="isOwner ? 'cursor-text hover:bg-accent' : undefined"
                        @click="isOwner && startEditingWorkspaceName()"
                    >
                        {{ workspace.name }}
                    </h1>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('boards.archived', workspace.id)" class="text-sm text-muted-foreground underline">Archived boards</Link>
                    <Button variant="outline" size="sm" @click="showMembers = true">Members ({{ members.length }})</Button>
                    <Dialog v-model:open="showCreateBoard">
                        <DialogTrigger as-child>
                            <Button size="sm">New board</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>New board</DialogTitle>
                            </DialogHeader>
                            <form class="space-y-4" @submit.prevent="submitBoard">
                                <div class="grid gap-2">
                                    <Label for="board-name">Name</Label>
                                    <Input id="board-name" v-model="boardForm.name" required autofocus />
                                    <InputError :message="boardForm.errors.name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="board-color">Color</Label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            id="board-color"
                                            v-model="boardForm.background_color"
                                            type="color"
                                            class="h-9 w-14 cursor-pointer rounded-md border border-input bg-transparent p-1"
                                        />
                                        <span class="text-sm text-muted-foreground">{{ boardForm.background_color }}</span>
                                    </div>
                                    <InputError :message="boardForm.errors.background_color" />
                                </div>
                                <DialogFooter>
                                    <Button type="submit" :disabled="boardForm.processing">Create</Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                    <DropdownMenu v-if="isOwner">
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                aria-label="Workspace actions"
                            >
                                <MoreHorizontal class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="startEditingWorkspaceName">Rename workspace</DropdownMenuItem>
                            <DropdownMenuItem @click="deleteWorkspace">Delete workspace</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <p v-if="boards.length === 0" class="text-sm text-muted-foreground">No boards yet — create your first one.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link v-for="board in boards" :key="board.id" :href="route('boards.show', board.id)" class="group block">
                    <div
                        class="flex h-24 flex-col justify-between rounded-lg p-4 shadow-sm transition group-hover:shadow-md group-hover:brightness-110"
                        :style="{ backgroundColor: board.background_color || '#44546f' }"
                    >
                        <p class="line-clamp-2 font-semibold text-white drop-shadow-sm">{{ board.name }}</p>
                    </div>
                </Link>
            </div>
        </div>

        <WorkspaceMemberPanel v-model:open="showMembers" :workspace="workspace" :members="members" :is-owner="isOwner" />
    </AppLayout>
</template>
