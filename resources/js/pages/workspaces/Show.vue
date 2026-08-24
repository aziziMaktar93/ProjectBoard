<script setup lang="ts">
import HoverLabel from '@/components/HoverLabel.vue';
import InputError from '@/components/InputError.vue';
import MemberAvatar from '@/components/MemberAvatar.vue';
import ColorSwatchPicker from '@/components/boards/ColorSwatchPicker.vue';
import WorkspaceMemberPanel from '@/components/boards/WorkspaceMemberPanel.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { tileGradient, washGradient } from '@/lib/colorGradient';
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

function onWorkspaceColorChange(color: string | null) {
    router.patch(route('workspaces.update', props.workspace.id), { background_color: color }, { preserveScroll: true });
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
        <div class="flex flex-1 flex-col gap-4 rounded-xl p-4" :style="{ backgroundImage: washGradient(workspace.background_color) }">
            <div class="flex flex-wrap items-center justify-between gap-2">
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
                        <HoverLabel label="Workspace actions">
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                    aria-label="Workspace actions"
                                >
                                    <MoreHorizontal class="size-4" />
                                </button>
                            </DropdownMenuTrigger>
                        </HoverLabel>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuItem @click="startEditingWorkspaceName">Rename workspace</DropdownMenuItem>
                            <DropdownMenuItem @click="deleteWorkspace">Delete workspace</DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel>Workspace color</DropdownMenuLabel>
                            <div class="px-2 pb-1">
                                <ColorSwatchPicker :model-value="workspace.background_color" @update:model-value="onWorkspaceColorChange" />
                            </div>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <p v-if="boards.length === 0" class="text-sm text-muted-foreground">No boards yet — create your first one.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link v-for="board in boards" :key="board.id" :href="route('boards.show', board.id)" class="group block">
                    <div
                        class="flex h-28 flex-col justify-between rounded-lg p-4 shadow-sm transition group-hover:shadow-md group-hover:brightness-110"
                        :style="{ backgroundImage: tileGradient(board.background_color) }"
                    >
                        <p class="line-clamp-2 font-semibold text-white drop-shadow-sm">{{ board.name }}</p>

                        <div class="space-y-1.5">
                            <div
                                v-if="board.checklist_progress !== null && board.checklist_progress !== undefined"
                                class="flex items-center gap-2"
                            >
                                <div class="h-1 flex-1 overflow-hidden rounded-full bg-white/30">
                                    <div class="h-full rounded-full bg-white" :style="{ width: `${board.checklist_progress}%` }" />
                                </div>
                                <span class="shrink-0 text-[10px] font-medium text-white/90 drop-shadow-sm">{{ board.checklist_progress }}%</span>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <div v-if="board.members?.length" class="flex -space-x-1.5">
                                    <MemberAvatar v-for="member in board.members.slice(0, 4)" :key="member.id" :user="member" size="xs" />
                                    <span
                                        v-if="board.members.length > 4"
                                        class="flex size-6 items-center justify-center rounded-full bg-white/30 text-[10px] font-semibold text-white ring-2 ring-white/50"
                                    >
                                        +{{ board.members.length - 4 }}
                                    </span>
                                </div>
                                <span v-else />
                                <span v-if="board.cards_count" class="shrink-0 text-xs font-medium text-white/90 drop-shadow-sm">
                                    {{ board.cards_count }} card{{ board.cards_count === 1 ? '' : 's' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <WorkspaceMemberPanel v-model:open="showMembers" :workspace="workspace" :members="members" :is-owner="isOwner" />
    </AppLayout>
</template>
