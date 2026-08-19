<script setup lang="ts">
import ArchivePanel from '@/components/boards/ArchivePanel.vue';
import BoardListColumn from '@/components/boards/BoardListColumn.vue';
import CardDetailModal from '@/components/boards/CardDetailModal.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BoardList, BreadcrumbItem, Card } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    board: Board;
    archivedLists: BoardList[];
    archivedCards: Card[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Boards', href: route('boards.index') },
    { title: props.board.name, href: route('boards.show', props.board.id) },
]);

const lists = ref<BoardList[]>(props.board.lists ?? []);
const cardGroup = `cards-board-${props.board.id}`;
const reorderError = ref<string | null>(null);

watch(
    () => props.board.lists,
    (newLists: BoardList[] | undefined) => {
        lists.value = newLists ?? [];
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

        <div class="flex items-center justify-between p-4">
            <h1 class="text-lg font-semibold">{{ board.name }}</h1>

            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="showArchive = true">View archive</Button>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button type="button" aria-label="Board actions">
                            <MoreHorizontal class="size-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem @click="archiveBoard">Archive board</DropdownMenuItem>
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
            <Button v-if="!showAddList" variant="secondary" size="sm" @click="showAddList = true">Add list</Button>
            <form v-else class="flex max-w-xs gap-2" @submit.prevent="submitAddList">
                <Input v-model="addListForm.name" placeholder="List name" autofocus />
                <Button type="submit" size="sm" :disabled="addListForm.processing">Add</Button>
                <Button type="button" variant="ghost" size="sm" @click="showAddList = false">Cancel</Button>
            </form>
        </div>

        <CardDetailModal v-model:open="showCardModal" :card="activeCard" />
        <ArchivePanel v-model:open="showArchive" :lists="archivedLists" :cards="archivedCards" />
    </AppLayout>
</template>
