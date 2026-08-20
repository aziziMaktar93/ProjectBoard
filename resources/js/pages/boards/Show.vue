<script setup lang="ts">
import ArchivePanel from '@/components/boards/ArchivePanel.vue';
import BoardListColumn from '@/components/boards/BoardListColumn.vue';
import BoardMemberPanel from '@/components/boards/BoardMemberPanel.vue';
import CardDetailModal from '@/components/boards/CardDetailModal.vue';
import ColorSwatchPicker from '@/components/boards/ColorSwatchPicker.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BoardList, BreadcrumbItem, Card } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{
    board: Board;
    archivedLists: BoardList[];
    archivedCards: Card[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Workspaces', href: route('workspaces.index') },
    { title: props.board.workspace?.name ?? '', href: route('workspaces.show', props.board.workspace_id) },
    { title: props.board.name, href: route('boards.show', props.board.id) },
]);

const lists = ref<BoardList[]>(props.board.lists ?? []);
const cardGroup = `cards-board-${props.board.id}`;
const reorderError = ref<string | null>(null);

watch(
    () => props.board.lists,
    (newLists: BoardList[] | undefined) => {
        lists.value = newLists ?? [];

        // The card detail modal holds its own snapshot (`activeCard`) of whichever
        // card is open. Without this, checklist/edit actions taken while the modal
        // is open would persist correctly server-side but never appear in the
        // modal itself, since `activeCard` would keep pointing at the pre-update
        // object — the same staleness this file's `lists` resync exists to avoid.
        if (activeCard.value) {
            for (const list of lists.value) {
                const match = list.cards.find((card: Card) => card.id === activeCard.value?.id);

                if (match) {
                    activeCard.value = match;
                    break;
                }
            }
        }
    },
);

// Snapshots captured at drag-start, since vue-draggable-plus mutates the
// v-model array as soon as the item is dropped — by the time the "end"
// handlers below run, `lists.value` already reflects the new (unsaved)
// order. These snapshots preserve the last known-good order so we can roll
// back if the server rejects or fails to persist the change.
let listOrderSnapshot: BoardList[] | null = null;
let cardOrderSnapshot: Map<number, Card[]> | null = null;

function onListDragStart() {
    listOrderSnapshot = [...lists.value];
}

function onListDragEnd() {
    const previousOrder = listOrderSnapshot ?? [...lists.value];

    router.patch(
        route('board-lists.reorder', props.board.id),
        { ordered_ids: lists.value.map((list) => list.id) },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reorderError.value = null;
            },
            onError: () => {
                lists.value = previousOrder;
                reorderError.value = 'Could not save the new list order. Please try again.';
            },
        },
    );
}

function onCardDragStart() {
    cardOrderSnapshot = new Map(lists.value.map((list: BoardList): [number, Card[]] => [list.id, [...list.cards]]));
}

function onCardDragEnd(event: { from: HTMLElement; to: HTMLElement }) {
    const fromListId = Number(event.from.dataset.listId);
    const toListId = Number(event.to.dataset.listId);
    const targetList = lists.value.find((list) => list.id === toListId);

    if (!targetList) {
        return;
    }

    const snapshot = cardOrderSnapshot;

    const payload: Record<string, unknown> = {
        target_list_id: toListId,
        target_ordered_ids: targetList.cards.map((card) => card.id),
    };

    if (fromListId !== toListId) {
        const sourceList = lists.value.find((list) => list.id === fromListId);

        if (sourceList) {
            payload.source_list_id = fromListId;
            payload.source_ordered_ids = sourceList.cards.map((card) => card.id);
        }
    }

    router.patch(route('cards.reorder', props.board.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            reorderError.value = null;
        },
        onError: () => {
            if (snapshot) {
                const target = lists.value.find((list: BoardList) => list.id === toListId);
                if (target) {
                    target.cards = snapshot.get(toListId) ?? target.cards;
                }

                if (fromListId !== toListId) {
                    const source = lists.value.find((list: BoardList) => list.id === fromListId);
                    if (source) {
                        source.cards = snapshot.get(fromListId) ?? source.cards;
                    }
                }
            }

            reorderError.value = 'Could not save the new card order. Please try again.';
        },
    });
}

const showAddList = ref(false);

const addListForm = useForm({
    name: '',
});

function submitAddList() {
    addListForm.post(route('board-lists.store', props.board.id), {
        preserveScroll: true,
        onSuccess: () => {
            addListForm.reset();
            showAddList.value = false;
        },
    });
}

const activeCard = ref<Card | null>(null);
const showCardModal = ref(false);
const showArchive = ref(false);
const showMembers = ref(false);

function openCard(card: Card) {
    activeCard.value = card;
    showCardModal.value = true;
}

function archiveBoard() {
    if (!confirm(`Archive the board "${props.board.name}"?`)) {
        return;
    }

    router.patch(route('boards.archive', props.board.id));
}

const isEditingBoardName = ref(false);
const boardNameInput = ref<HTMLInputElement | null>(null);

const boardNameForm = useForm({
    name: props.board.name,
});

async function startEditingBoardName() {
    boardNameForm.name = props.board.name;
    isEditingBoardName.value = true;
    await nextTick();
    boardNameInput.value?.focus();
    boardNameInput.value?.select();
}

function saveBoardName() {
    isEditingBoardName.value = false;

    if (!boardNameForm.name.trim() || boardNameForm.name === props.board.name) {
        boardNameForm.name = props.board.name;
        return;
    }

    boardNameForm.patch(route('boards.update', props.board.id), { preserveScroll: true });
}

function onBoardColorChange(color: string | null) {
    router.patch(route('boards.update', props.board.id), { background_color: color }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="board.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div v-if="reorderError" class="mx-4 mt-4 flex items-center justify-between gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 dark:border-red-900 dark:bg-red-950">
            <p class="text-sm text-red-600 dark:text-red-500">{{ reorderError }}</p>
            <button
                type="button"
                class="text-sm text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-300"
                aria-label="Dismiss error"
                @click="reorderError = null"
            >
                &times;
            </button>
        </div>

        <div
            class="flex flex-1 flex-col rounded-xl"
            :style="board.background_color ? { backgroundColor: `${board.background_color}1a` } : undefined"
        >
            <div class="flex items-center justify-between p-4">
                <div class="flex min-w-0 items-center gap-2">
                    <span v-if="board.background_color" class="size-3 shrink-0 rounded-full" :style="{ backgroundColor: board.background_color }" />
                    <input
                        v-if="isEditingBoardName"
                        ref="boardNameInput"
                        v-model="boardNameForm.name"
                        class="min-w-0 rounded bg-transparent px-1 text-xl font-semibold tracking-tight outline-none ring-2 ring-ring"
                        @blur="saveBoardName"
                        @keydown.enter="saveBoardName"
                        @keydown.escape="isEditingBoardName = false"
                    />
                    <h1
                        v-else
                        class="min-w-0 cursor-text truncate rounded px-1 text-xl font-semibold tracking-tight hover:bg-accent"
                        @click="startEditingBoardName"
                    >
                        {{ board.name }}
                    </h1>
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="showMembers = true">Members ({{ (board.members ?? []).length }})</Button>
                    <Button variant="outline" size="sm" @click="showArchive = true">View archive</Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                aria-label="Board actions"
                            >
                                <MoreHorizontal class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuItem @click="startEditingBoardName">Rename board</DropdownMenuItem>
                            <DropdownMenuItem @click="archiveBoard">Archive board</DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel>Board color</DropdownMenuLabel>
                            <div class="px-2 pb-1">
                                <ColorSwatchPicker :model-value="board.background_color" @update:model-value="onBoardColorChange" />
                            </div>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <VueDraggable
                v-model="lists"
                item-key="id"
                :animation="150"
                handle=".list-drag-handle"
                class="flex flex-1 items-start gap-4 overflow-x-auto p-4 pt-0"
                @start="onListDragStart"
                @end="onListDragEnd"
            >
                <BoardListColumn
                    v-for="list in lists"
                    :key="list.id"
                    :list="list"
                    :group="cardGroup"
                    @open-card="openCard"
                    @card-drag-start="onCardDragStart"
                    @card-drag-end="onCardDragEnd"
                />
            </VueDraggable>

            <div class="p-4 pt-0">
                <Button
                    v-if="!showAddList"
                    variant="ghost"
                    size="sm"
                    class="border border-dashed border-neutral-300 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 dark:border-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                    @click="showAddList = true"
                >
                    + Add another list
                </Button>
                <form v-else class="flex max-w-xs gap-2" @submit.prevent="submitAddList">
                    <Input v-model="addListForm.name" placeholder="List name" autofocus />
                    <Button type="submit" size="sm" :disabled="addListForm.processing">Add</Button>
                    <Button type="button" variant="ghost" size="sm" @click="showAddList = false">Cancel</Button>
                </form>
            </div>
        </div>

        <CardDetailModal v-model:open="showCardModal" :card="activeCard" />
        <ArchivePanel v-model:open="showArchive" :lists="archivedLists" :cards="archivedCards" />
        <BoardMemberPanel v-model:open="showMembers" :board="board" :workspace-members="board.workspace?.members ?? []" />
    </AppLayout>
</template>
